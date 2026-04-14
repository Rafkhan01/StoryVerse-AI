<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../admin_login.php'); exit;
}
require_once '../db_connect.php';

// ── Admin secret key (lives only in code, never in DB) ─────────────────
// Admin must enter this key when creating another admin account.
// Change this to your own secret string.
define('ADMIN_SECRET_KEY', 'SV@Admin#2026!');

// ── Sidebar badge counts ────────────────────────────────────────────────
$badge_stories = $badge_comments = 0;
try {
    $badge_stories = (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status='pending'")->fetchColumn()
                   + (int)$pdo->query("SELECT COUNT(*) FROM story_parts WHERE status='pending'")->fetchColumn();
} catch (Exception $e) {}
try {
    $badge_comments = (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE manually_flagged=1")->fetchColumn();
} catch (Exception $e) {}

// ── ML status (topbar pill) ─────────────────────────────────────────────
$ml_status = 'offline';
$ch = curl_init('http://127.0.0.1:8000/');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
curl_exec($ch);
$ml_status = curl_errno($ch) === 0 ? 'online' : 'offline';
curl_close($ch);

$today = date('l, F j, Y');
$flash = ['type' => '', 'msg' => ''];

// ── Helper: activity log ────────────────────────────────────────────────
function log_admin_action(PDO $pdo, string $text, string $type = 'update'): void {
    try {
        $pdo->prepare("INSERT INTO admin_activity_log (action_text, action_type, created_at) VALUES (?,?,NOW())")
            ->execute([$text, $type]);
    } catch (Exception $e) {}
}

// ── Handle Add User (POST, full page submit) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    $first    = trim($_POST['first_name']  ?? '');
    $last     = trim($_POST['last_name']   ?? '');
    $uname    = trim($_POST['user_name']   ?? '');
    $email    = trim($_POST['email']       ?? '');
    $pw       = trim($_POST['password']    ?? '');
    $utype    = in_array($_POST['user_type'] ?? '', ['reader','author']) ? $_POST['user_type'] : 'reader';
    // Admin accounts: user_type stays 'author' in users table but they get an
    // admin session via admin_login.php — we don't store 'admin' in users.user_type
    // because the ENUM only has reader|author. Instead we track admins separately.
    // The "Admin" tab in the filter just shows users flagged via a separate mechanism.
    // For now, only reader and author can be created here.
    $verif    = isset($_POST['is_verified']) ? 1 : 0;

    if (!$first || !$uname || !$email || !$pw) {
        $flash = ['type' => 'error', 'msg' => 'First name, username, email and password are required.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash = ['type' => 'error', 'msg' => 'Invalid email address.'];
    } else {
        $c1 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_name = ?");
        $c1->execute([$uname]);
        $c2 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $c2->execute([$email]);

        if ($c1->fetchColumn() > 0) {
            $flash = ['type' => 'error', 'msg' => 'Username already taken.'];
        } elseif ($c2->fetchColumn() > 0) {
            $flash = ['type' => 'error', 'msg' => 'Email already registered.'];
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO users (first_name, last_name, user_name, email, password, user_type, email_verified, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([$first, $last, $uname, $email, $hash, $utype, $verif]);
            log_admin_action($pdo, "New $utype '$uname' created by admin", 'add');
            $flash = ['type' => 'success', 'msg' => ucfirst($utype) . " \"$uname\" created successfully."];
        }
    }
}

// ── Fetch users with filter + search ───────────────────────────────────
$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['q'] ?? '');
if (!in_array($filter, ['all','reader','author'])) $filter = 'all';

$where_parts = [];
$params      = [];

if ($filter !== 'all') {
    $where_parts[] = 'user_type = ?';
    $params[]      = $filter;
}
if ($search !== '') {
    $where_parts[] = '(user_name LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
    $params[] = "%$search%"; $params[] = "%$search%";
}

$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';
$stmt  = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Count per filter tab ────────────────────────────────────────────────
$counts = ['all' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()];
foreach (['reader','author'] as $t) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = ?");
    $q->execute([$t]);
    $counts[$t] = $q->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users — StoryVerse Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
    --sidebar-bg:#0B1120; --sidebar-w:240px; --sidebar-collapsed:64px; --topbar-h:56px;
    --bg:#F4F6FB; --card-bg:#ffffff; --card-shadow:0 2px 16px rgba(15,20,50,0.07);
    --card-radius:12px; --card-border:#EEF0F7;
    --text-primary:#1A1D2E; --text-secondary:#6B7280; --text-muted:#9CA3AF;
    --gold:#F5A623; --gold-dark:#E8920F; --gold-glow:rgba(245,166,35,0.18);
    --azure:#3B82F6; --success:#10B981; --danger:#EF4444; --warning:#F59E0B; --purple:#8B5CF6;
    --border:#E8EAF0; --divider:#F3F4F8;
    --tr:0.26s cubic-bezier(0.4,0,0.2,1);
    --font-display:'Cinzel Decorative',serif; --font-body:'Rajdhani',sans-serif; --font-mono:'Space Mono',monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-body);background:var(--bg);color:var(--text-primary);min-height:100vh;display:flex;overflow-x:hidden}

/* ── Sidebar ── */
.sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--sidebar-bg);display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;transition:width var(--tr);overflow:hidden}
.sidebar.collapsed{width:var(--sidebar-collapsed)}
.sidebar-brand{display:flex;align-items:center;gap:10px;padding:0 20px;height:var(--topbar-h);border-bottom:1px solid rgba(255,255,255,0.05);flex-shrink:0;overflow:hidden}
.brand-mark{width:28px;height:28px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-family:var(--font-display);font-size:13px;color:#fff;font-weight:700}
.brand-name{font-family:var(--font-display);font-size:12px;color:#fff;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .brand-name{opacity:0;pointer-events:none}
.sidebar-nav{flex:1;padding:12px 0;overflow-y:auto;overflow-x:hidden}
.sidebar-nav::-webkit-scrollbar{width:3px}
.sidebar-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.1);border-radius:99px}
.nav-section-label{font-family:var(--font-mono);font-size:9px;letter-spacing:0.12em;color:rgba(255,255,255,0.28);padding:14px 20px 6px;text-transform:uppercase;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-section-label{opacity:0}
.nav-item{display:flex;align-items:center;gap:12px;padding:10px 20px;color:rgba(255,255,255,0.72);text-decoration:none;font-family:var(--font-body);font-size:14px;font-weight:500;white-space:nowrap;position:relative;transition:color 0.18s,background 0.18s;border-left:3px solid transparent}
.nav-item:hover{color:#fff;background:rgba(255,255,255,0.05)}
.nav-item.active{color:var(--gold);background:linear-gradient(90deg,rgba(245,166,35,0.12) 0%,transparent 100%);border-left-color:var(--gold)}
.nav-item svg{width:18px;height:18px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.nav-label{transition:opacity var(--tr)}
.sidebar.collapsed .nav-label{opacity:0}
.nav-badge{margin-left:auto;background:var(--danger);color:#fff;font-family:var(--font-mono);font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;flex-shrink:0;transition:opacity var(--tr)}
.sidebar.collapsed .nav-badge{opacity:0}
.nav-item .tooltip{position:absolute;left:calc(var(--sidebar-collapsed) + 8px);background:#1E2A45;color:#fff;padding:5px 10px;border-radius:6px;font-size:12px;font-family:var(--font-body);white-space:nowrap;pointer-events:none;opacity:0;transform:translateX(-4px);transition:opacity 0.15s,transform 0.15s;z-index:200;box-shadow:0 4px 12px rgba(0,0,0,0.3)}
.sidebar.collapsed .nav-item:hover .tooltip{opacity:1;transform:translateX(0)}
.sidebar-bottom{padding:12px 12px 16px;border-top:1px solid rgba(255,255,255,0.05)}
.logout-btn{display:flex;align-items:center;gap:10px;width:100%;padding:9px 8px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.18);border-radius:8px;color:#F87171;font-family:var(--font-body);font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;overflow:hidden;transition:background 0.18s;text-decoration:none}
.logout-btn:hover{background:rgba(239,68,68,0.16);color:#FCA5A5}
.logout-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}
.logout-text{transition:opacity var(--tr)}
.sidebar.collapsed .logout-text{opacity:0}

/* ── Main layout ── */
.main-wrapper{margin-left:var(--sidebar-w);width:calc(100% - var(--sidebar-w));min-height:100vh;display:flex;flex-direction:column;transition:margin-left var(--tr),width var(--tr)}
.main-wrapper.expanded{margin-left:var(--sidebar-collapsed);width:calc(100% - var(--sidebar-collapsed))}

/* ── Topbar ── */
.topbar{height:var(--topbar-h);background:var(--card-bg);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;gap:16px;position:sticky;top:0;z-index:50}
.hamburger{width:36px;height:36px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;background:none;border:none;cursor:pointer;border-radius:8px;transition:background 0.15s;flex-shrink:0}
.hamburger:hover{background:var(--divider)}
.hamburger span{display:block;width:18px;height:1.8px;background:var(--text-primary);border-radius:2px;transition:transform 0.25s,opacity 0.25s,width 0.25s;transform-origin:center}
.hamburger.active span:nth-child(1){transform:translateY(6.8px) rotate(45deg)}
.hamburger.active span:nth-child(2){opacity:0;width:0}
.hamburger.active span:nth-child(3){transform:translateY(-6.8px) rotate(-45deg)}
.topbar-search{display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:7px 12px;flex:1;max-width:320px;transition:border-color 0.18s,box-shadow 0.18s}
.topbar-search:focus-within{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.topbar-search svg{width:15px;height:15px;stroke:var(--text-muted);fill:none;stroke-width:2}
.topbar-search input{border:none;background:none;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);width:100%}
.topbar-search input::placeholder{color:var(--text-muted)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:12px}
.ml-pill{display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:99px;font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:0.04em;border:1px solid}
.ml-pill.online{background:#ECFDF5;color:var(--success);border-color:rgba(16,185,129,0.2)}
.ml-pill.offline{background:#FEF2F2;color:var(--danger);border-color:rgba(239,68,68,0.2)}
.ml-dot{width:7px;height:7px;border-radius:50%;background:currentColor;animation:pulse-dot 2s infinite}
@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:0.4}}
.topbar-date{font-family:var(--font-mono);font-size:11px;color:var(--text-muted);white-space:nowrap}

/* ── Page content ── */
.page-content{padding:28px 28px 48px;flex:1}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;flex-wrap:wrap}
.page-header-left h1{font-family:var(--font-display);font-size:22px;color:var(--text-primary);margin-bottom:4px}
.page-header-left p{font-size:14px;color:var(--text-secondary)}
.btn-primary{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border:none;border-radius:9px;font-family:var(--font-body);font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;transition:opacity 0.15s,transform 0.15s;white-space:nowrap}
.btn-primary:hover{opacity:0.9;transform:translateY(-1px)}
.btn-primary svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round}

/* ── Flash ── */
.flash{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;margin-bottom:20px;font-size:14px;font-weight:600;border:1px solid}
.flash.success{background:#ECFDF5;border-color:rgba(16,185,129,0.3);color:#065F46}
.flash.error{background:#FEF2F2;border-color:rgba(239,68,68,0.3);color:#991B1B}
.flash svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}

/* ── Info banner (about verify behaviour) ── */
.info-banner{display:flex;align-items:flex-start;gap:12px;background:linear-gradient(135deg,#EFF6FF,#DBEAFE);border:1px solid rgba(59,130,246,0.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#1D4ED8;line-height:1.6}
.info-banner svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0;margin-top:1px}
.info-banner strong{font-weight:700}

/* ── Filter tabs ── */
.filter-bar{display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.filter-tab{display:inline-flex;align-items:center;gap:7px;padding:7px 16px;border-radius:8px;border:1px solid var(--border);background:var(--card-bg);font-family:var(--font-body);font-size:13px;font-weight:600;color:var(--text-secondary);text-decoration:none;transition:all 0.15s}
.filter-tab:hover{border-color:var(--gold);color:var(--text-primary)}
.filter-tab.active{background:var(--text-primary);color:#fff;border-color:var(--text-primary)}
.filter-tab .tab-count{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;background:rgba(255,255,255,0.18)}
.filter-tab:not(.active) .tab-count{background:var(--divider);color:var(--text-muted)}

/* ── Table controls ── */
.table-controls{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.table-search{display:flex;align-items:center;gap:8px;background:var(--card-bg);border:1px solid var(--border);border-radius:9px;padding:8px 14px;flex:1;max-width:360px;transition:border-color 0.18s,box-shadow 0.18s}
.table-search:focus-within{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.table-search svg{width:15px;height:15px;stroke:var(--text-muted);fill:none;stroke-width:2;flex-shrink:0}
.table-search input{border:none;background:none;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);width:100%}
.table-search input::placeholder{color:var(--text-muted)}
.results-count{font-family:var(--font-mono);font-size:11px;color:var(--text-muted);margin-left:auto;white-space:nowrap}

/* ── Table ── */
.table-card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);overflow:hidden}
.user-table{width:100%;border-collapse:collapse}
.user-table thead tr{background:var(--divider);border-bottom:1px solid var(--border)}
.user-table thead th{padding:12px 16px;text-align:left;font-family:var(--font-mono);font-size:10px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);white-space:nowrap}
.user-table tbody tr{border-bottom:1px solid var(--divider);transition:background 0.12s}
.user-table tbody tr:last-child{border-bottom:none}
.user-table tbody tr:hover{background:#FAFBFF}
.user-table td{padding:13px 16px;vertical-align:middle}

/* User cell */
.user-cell{display:flex;align-items:center;gap:12px}
.avatar{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:var(--font-body);font-size:14px;font-weight:700;flex-shrink:0}
.avatar.reader{background:#EFF6FF;color:#3B82F6}
.avatar.author{background:#F5F3FF;color:#8B5CF6}
.user-name{font-size:14px;font-weight:700;color:var(--text-primary);line-height:1.2}
.user-handle{font-family:var(--font-mono);font-size:10px;color:var(--text-muted)}
.user-email{font-family:var(--font-mono);font-size:10px;color:var(--text-muted)}

/* Role badge */
.role-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-family:var(--font-body);font-size:12px;font-weight:700;text-transform:capitalize;border:1px solid}
.role-badge.reader{background:#EFF6FF;color:#1D4ED8;border-color:#BFDBFE}
.role-badge.author{background:#F5F3FF;color:#6D28D9;border-color:#DDD6FE}
.role-badge svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round}

/* Verify badge */
.verify-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:700;border:1px solid}
.verify-badge.verified{background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.verify-badge.unverified{background:#FEF3C7;color:#92400E;border-color:#FCD34D}
.verify-badge svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}

.date-cell{font-family:var(--font-mono);font-size:11px;color:var(--text-muted)}

/* Actions */
.actions-cell{display:flex;align-items:center;gap:6px}
.action-btn{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border);background:var(--card-bg);cursor:pointer;transition:all 0.15s}
.action-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.action-btn.edit{color:var(--azure)}
.action-btn.edit:hover{background:#EFF6FF;border-color:#BFDBFE}
.action-btn.verify-btn{color:var(--success)}
.action-btn.verify-btn:hover{background:#ECFDF5;border-color:#A7F3D0}
.action-btn.unverify-btn{color:var(--warning)}
.action-btn.unverify-btn:hover{background:#FFFBEB;border-color:#FCD34D}
.action-btn.delete{color:var(--danger)}
.action-btn.delete:hover{background:#FEF2F2;border-color:#FCA5A5}

/* Empty state */
.empty-state{text-align:center;padding:56px 24px;color:var(--text-muted)}
.empty-state svg{width:48px;height:48px;stroke:var(--border);fill:none;stroke-width:1.5;margin:0 auto 14px;display:block}
.empty-state p{font-size:14px}

/* ── Modal ── */
.modal-overlay{position:fixed;inset:0;background:rgba(11,17,32,0.55);backdrop-filter:blur(3px);z-index:200;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;pointer-events:none;transition:opacity 0.22s}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--card-bg);border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,0.18);width:100%;max-width:520px;transform:translateY(16px) scale(0.98);transition:transform 0.22s cubic-bezier(0.34,1.56,0.64,1);overflow:hidden;max-height:90vh;display:flex;flex-direction:column}
.modal-overlay.open .modal{transform:translateY(0) scale(1)}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 16px;border-bottom:1px solid var(--divider);flex-shrink:0}
.modal-title{font-family:var(--font-display);font-size:16px;color:var(--text-primary)}
.modal-close{width:32px;height:32px;border-radius:8px;background:var(--divider);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.15s}
.modal-close:hover{background:var(--border)}
.modal-close svg{width:14px;height:14px;stroke:var(--text-secondary);fill:none;stroke-width:2.5;stroke-linecap:round}
.modal-body{padding:22px 24px;overflow-y:auto;flex:1}
.modal-footer{padding:16px 24px;border-top:1px solid var(--divider);display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-shrink:0;background:var(--divider)}

/* Form fields */
.field-group{margin-bottom:16px}
.field-label{display:block;font-family:var(--font-body);font-size:11px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:6px}
.field-input{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:9px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);transition:border-color 0.18s,box-shadow 0.18s}
.field-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-input::placeholder{color:var(--text-muted)}
.field-input[readonly]{opacity:0.6;cursor:not-allowed}
.field-select{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:9px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);transition:border-color 0.18s;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239CA3AF' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:38px}
.field-select:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field-hint{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:5px}

/* Toggle */
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--bg);border:1px solid var(--border);border-radius:9px;margin-bottom:16px}
.toggle-label{font-size:13px;font-weight:600;color:var(--text-primary)}
.toggle-sub{font-size:11px;color:var(--text-muted);margin-top:2px}
.toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;border-radius:99px;background:var(--border);cursor:pointer;transition:background 0.2s}
.toggle-slider::before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;left:3px;top:3px;background:#fff;transition:transform 0.2s;box-shadow:0 1px 4px rgba(0,0,0,0.15)}
.toggle-switch input:checked + .toggle-slider{background:var(--success)}
.toggle-switch input:checked + .toggle-slider::before{transform:translateX(20px)}

/* Password strength */
.pw-wrap{position:relative}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:0;display:flex}
.pw-toggle svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round}
.pw-strength{margin-top:6px;height:3px;border-radius:99px;background:var(--border);overflow:hidden}
.pw-bar{height:100%;border-radius:99px;width:0;transition:width 0.3s,background 0.3s}
.pw-hint{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:4px}

/* Section label */
.modal-section{font-family:var(--font-mono);font-size:9px;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-muted);margin:20px 0 12px;display:flex;align-items:center;gap:8px}
.modal-section::after{content:'';flex:1;height:1px;background:var(--divider)}

/* Buttons */
.btn-cancel{padding:9px 18px;border-radius:8px;border:1px solid var(--border);background:var(--card-bg);font-family:var(--font-body);font-size:13px;font-weight:700;color:var(--text-secondary);cursor:pointer;transition:background 0.15s}
.btn-cancel:hover{background:var(--border)}
.btn-save{padding:9px 22px;border-radius:8px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:opacity 0.15s,transform 0.15s}
.btn-save:hover{opacity:0.9;transform:translateY(-1px)}
.btn-danger{padding:9px 22px;border-radius:8px;background:var(--danger);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:background 0.15s}
.btn-danger:hover{background:#DC2626}

/* Toast */
.toast-container{position:fixed;bottom:28px;right:28px;z-index:999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;padding:12px 18px;background:#1A1D2E;color:#fff;border-radius:10px;font-family:var(--font-body);font-size:13.5px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,0.2);animation:toast-in 0.3s cubic-bezier(0.34,1.56,0.64,1) forwards;pointer-events:all;border-left:3px solid var(--gold)}
@keyframes toast-in{from{opacity:0;transform:translateY(16px) scale(0.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.toast.fade-out{animation:toast-out 0.25s ease forwards}
@keyframes toast-out{to{opacity:0;transform:translateY(8px) scale(0.97)}}

.fade-up{opacity:0;transform:translateY(14px);animation:fade-up-in 0.38s ease forwards}
@keyframes fade-up-in{to{opacity:1;transform:translateY(0)}}

@media(max-width:900px){.field-row{grid-template-columns:1fr}}
@media(max-width:600px){.page-content{padding:16px 14px 40px}.topbar-date{display:none}.topbar-search{max-width:160px}}
</style>
</head>
<body>

<!-- ═══ SIDEBAR ══════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">S</div>
        <span class="brand-name">StoryVerse</span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a href="admin_home.php" class="nav-item">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <span class="nav-label">Dashboard</span><span class="tooltip">Dashboard</span>
        </a>
        <div class="nav-section-label">Manage</div>
        <a href="admin_users.php" class="nav-item active">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="nav-label">Users</span><span class="tooltip">Users</span>
        </a>
        <a href="admin_stories.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <span class="nav-label">Stories</span>
            <?php if ($badge_stories > 0): ?><span class="nav-badge"><?= $badge_stories ?></span><?php endif; ?>
            <span class="tooltip">Stories</span>
        </a>
        <a href="admin_games.php" class="nav-item">
            <svg viewBox="0 0 24 24"><line x1="6" y1="12" x2="18" y2="12"/><line x1="12" y1="6" x2="12" y2="18"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>
            <span class="nav-label">Games</span><span class="tooltip">Games</span>
        </a>
        <a href="admin_dataset.php" class="nav-item">
            <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            <span class="nav-label">Dataset & ML</span><span class="tooltip">Dataset & ML</span>
        </a>
        <a href="admin_comments.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="nav-label">Comments</span>
            <?php if ($badge_comments > 0): ?><span class="nav-badge"><?= $badge_comments ?></span><?php endif; ?>
            <span class="tooltip">Comments</span>
        </a>
        <a href="admin_leaderboard.php" class="nav-item">
            <svg viewBox="0 0 24 24"><polyline points="18 20 18 10"/><polyline points="12 20 12 4"/><polyline points="6 20 6 14"/></svg>
            <span class="nav-label">Leaderboard</span><span class="tooltip">Leaderboard</span>
        </a>
        <a href="admin_announcement.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            <span class="nav-label">Announcement</span><span class="tooltip">Announcement</span>
        </a>
    </nav>
    <div class="sidebar-bottom">
        <a href="admin_logout.php" class="logout-btn">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="logout-text">Log Out</span>
        </a>
    </div>
</aside>

<!-- ═══ MAIN WRAPPER ══════════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
        <div class="topbar-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search users, stories..." id="globalSearch">
        </div>
        <div class="topbar-right">
            <div class="ml-pill <?= $ml_status ?>">
                <span class="ml-dot"></span>ML API &nbsp;<?= strtoupper($ml_status) ?>
            </div>
            <span class="topbar-date"><?= $today ?></span>
        </div>
    </header>

    <main class="page-content">

        <div class="page-header fade-up">
            <div class="page-header-left">
                <h1>Users</h1>
                <p>Manage all readers and authors on StoryVerse.</p>
            </div>
            <button class="btn-primary" onclick="openModal('addModal')">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add User
            </button>
        </div>

        <!-- Verify info banner -->
        <div class="info-banner fade-up" style="animation-delay:0.05s">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <strong>About Email Verification:</strong>
                Readers never require OTP — they log in directly regardless of verified status.
                Authors require OTP on registration. Once you mark an author as <strong>Verified</strong> here,
                they skip OTP on their next login. Use this to manually approve an author
                you have already confirmed offline.
            </div>
        </div>

        <?php if ($flash['msg']): ?>
        <div class="flash <?= $flash['type'] ?> fade-up">
            <?php if ($flash['type'] === 'success'): ?>
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?php else: ?>
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php endif; ?>
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- Filter tabs -->
        <div class="filter-bar fade-up" style="animation-delay:0.08s">
            <?php
            $tab_defs = [
                'all'    => ['label'=>'All Users','icon'=>'<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>'],
                'reader' => ['label'=>'Readers',  'icon'=>'<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'],
                'author' => ['label'=>'Authors',  'icon'=>'<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>'],
            ];
            foreach ($tab_defs as $key => $def):
                $url = '?filter='.$key.($search ? '&q='.urlencode($search) : '');
            ?>
            <a href="<?= $url ?>" class="filter-tab <?= $filter===$key?'active':'' ?>">
                <?= $def['icon'] ?>
                <?= $def['label'] ?>
                <span class="tab-count"><?= $counts[$key] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Table controls -->
        <div class="table-controls fade-up" style="animation-delay:0.12s">
            <div class="table-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="tableSearch" placeholder="Filter by name, username or email..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <span class="results-count" id="resultsCount"><?= count($users) ?> <?= count($users)===1?'user':'users' ?></span>
        </div>

        <!-- Table -->
        <div class="table-card fade-up" style="animation-delay:0.16s">
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <p>No users found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</p>
                </div>
            <?php else: ?>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Verification</th>
                        <th>Stories</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                <?php foreach ($users as $u):
                    $utype    = $u['user_type'] ?? 'reader';
                    $verified = !empty($u['email_verified']) && $u['email_verified'] == 1;
                    $fullname = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                    $initials = strtoupper(substr($u['first_name'] ?? $u['user_name'], 0, 1) . substr($u['last_name'] ?? '', 0, 1));
                    $joined   = !empty($u['created_at']) ? date('M j, Y', strtotime($u['created_at'])) : '—';
                    $stories  = 0;
                    if ($utype === 'author') {
                        try {
                            $sc = $pdo->prepare("SELECT COUNT(*) FROM stories WHERE created_by=? AND status='approved'");
                            $sc->execute([$u['user_name']]);
                            $stories = (int)$sc->fetchColumn();
                        } catch(Exception $e){}
                    }
                    // Build JSON for JS — only safe fields
                    $js_user = json_encode([
                        'id'             => (int)$u['user_id'],
                        'first_name'     => $u['first_name'] ?? '',
                        'last_name'      => $u['last_name']  ?? '',
                        'user_name'      => $u['user_name'],
                        'email'          => $u['email'],
                        'user_type'      => $utype,
                        'email_verified' => $verified ? 1 : 0,
                    ]);
                ?>
                <tr id="row-<?= $u['user_id'] ?>"
                    data-name="<?= htmlspecialchars(strtolower($fullname)) ?>"
                    data-uname="<?= htmlspecialchars(strtolower($u['user_name'])) ?>"
                    data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>">

                    <td>
                        <div class="user-cell">
                            <div class="avatar <?= $utype ?>"><?= htmlspecialchars($initials) ?></div>
                            <div>
                                <div class="user-name"><?= htmlspecialchars($fullname ?: $u['user_name']) ?></div>
                                <div class="user-handle">@<?= htmlspecialchars($u['user_name']) ?></div>
                                <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="role-badge <?= $utype ?>">
                            <?php if ($utype === 'reader'): ?>
                                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <?php else: ?>
                                <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            <?php endif; ?>
                            <?= ucfirst($utype) ?>
                        </span>
                    </td>

                    <td>
                        <?php if ($verified): ?>
                            <span class="verify-badge verified">
                                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Verified
                            </span>
                        <?php else: ?>
                            <span class="verify-badge unverified">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Unverified
                            </span>
                        <?php endif; ?>
                    </td>

                    <td class="date-cell">
                        <?= $utype === 'author' ? $stories . ' published' : '—' ?>
                    </td>

                    <td class="date-cell"><?= $joined ?></td>

                    <td>
                        <div class="actions-cell">
                            <button class="action-btn edit" title="Edit user"
                                onclick='openEditModal(<?= $js_user ?>)'>
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>

                            <?php if ($verified): ?>
                            <button class="action-btn unverify-btn" title="Revoke verification"
                                onclick="ajaxVerify(<?= $u['user_id'] ?>, 0)">
                                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                            <?php else: ?>
                            <button class="action-btn verify-btn" title="Mark as verified"
                                onclick="ajaxVerify(<?= $u['user_id'] ?>, 1)">
                                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </button>
                            <?php endif; ?>

                            <button class="action-btn delete" title="Delete user"
                                onclick="openDeleteModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars(addslashes($fullname ?: $u['user_name'])) ?>')">
                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ═══ ADD MODAL ════════════════════════════════════════════════ -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add New User</span>
            <button class="modal-close" onclick="closeModal('addModal')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-body">
                <div class="field-row">
                    <div class="field-group">
                        <label class="field-label">First Name *</label>
                        <input type="text" name="first_name" class="field-input" placeholder="First name" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Last Name</label>
                        <input type="text" name="last_name" class="field-input" placeholder="Last name">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-group">
                        <label class="field-label">Username *</label>
                        <input type="text" name="user_name" class="field-input" placeholder="unique_username" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Role *</label>
                        <select name="user_type" class="field-select" id="addRoleSelect" onchange="toggleVerifyRow()">
                            <option value="reader">Reader</option>
                            <option value="author">Author</option>
                        </select>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">Email Address *</label>
                    <input type="email" name="email" class="field-input" placeholder="user@example.com" required>
                </div>
                <div class="field-group">
                    <label class="field-label">Password *</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="addPw" class="field-input" placeholder="Min. 8 characters"
                               style="padding-right:40px" oninput="checkPw(this,'addBar','addHint')" required>
                        <button type="button" class="pw-toggle" onclick="togglePw('addPw',this)">
                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="pw-strength"><div class="pw-bar" id="addBar"></div></div>
                    <div class="pw-hint" id="addHint">Enter a password</div>
                </div>

                <div class="modal-section">Email Verification</div>

                <div class="toggle-row" id="verifyToggleRow">
                    <div>
                        <div class="toggle-label">Mark as Verified</div>
                        <div class="toggle-sub" id="verifySubText">Skip OTP for this author on first login</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_verified" id="addVerified">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn-save">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ EDIT MODAL ═══════════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit User</span>
            <button class="modal-close" onclick="closeModal('editModal')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" id="editBody"></div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
            <button type="button" class="btn-save" id="editSaveBtn" onclick="submitEdit()">Save Changes</button>
        </div>
    </div>
</div>

<!-- ═══ DELETE MODAL ═════════════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <span class="modal-title">Delete User</span>
            <button class="modal-close" onclick="closeModal('deleteModal')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p style="font-size:14px;color:var(--text-secondary);line-height:1.7">
                You are about to permanently delete
                <strong id="delName" style="color:var(--text-primary)"></strong>.
                All their data — comments, predictions, scores — will also be removed due to cascading foreign keys.
                <br><br>
                <span style="color:var(--danger);font-weight:700">This cannot be undone.</span>
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
            <button type="button" class="btn-danger" id="delConfirmBtn">Delete Permanently</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// ── Sidebar ────────────────────────────────────────────────────
const sidebar=document.getElementById('sidebar'),mainWrapper=document.getElementById('mainWrapper'),hamburger=document.getElementById('hamburger');
hamburger.addEventListener('click',()=>{const c=sidebar.classList.toggle('collapsed');mainWrapper.classList.toggle('expanded',c);hamburger.classList.toggle('active',c);localStorage.setItem('sv_sidebar',c?'1':'0')});
if(localStorage.getItem('sv_sidebar')==='1'){sidebar.classList.add('collapsed');mainWrapper.classList.add('expanded');hamburger.classList.add('active')}

// Global search redirect
document.getElementById('globalSearch').addEventListener('keydown',function(e){if(e.key==='Enter'&&this.value.trim())location.href='admin_users.php?q='+encodeURIComponent(this.value.trim())});

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg,type='default'){
    const tc=document.getElementById('toastContainer'),t=document.createElement('div');
    t.className='toast';
    t.style.borderLeftColor=type==='success'?'var(--success)':type==='error'?'var(--danger)':'var(--gold)';
    t.textContent=msg; tc.appendChild(t);
    setTimeout(()=>{t.classList.add('fade-out');setTimeout(()=>t.remove(),280)},3200);
}

// ── Modal helpers ──────────────────────────────────────────────
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden'}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow=''}
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',function(e){if(e.target===this)closeModal(this.id)}));
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-overlay.open').forEach(m=>closeModal(m.id))});

// ── Password helpers ───────────────────────────────────────────
function checkPw(inp,barId,hintId){
    const v=inp.value,bar=document.getElementById(barId),hint=document.getElementById(hintId);
    let s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
    const cfg=[
        {w:'0%',  bg:'var(--border)',  t:'Enter a password'},
        {w:'25%', bg:'var(--danger)',  t:'Weak'},
        {w:'50%', bg:'var(--warning)', t:'Fair'},
        {w:'75%', bg:'var(--azure)',   t:'Good'},
        {w:'100%',bg:'var(--success)', t:'Strong'},
    ];
    const c=cfg[s]||cfg[0];bar.style.width=c.w;bar.style.background=c.bg;hint.textContent=c.t;hint.style.color=c.bg;
}
function togglePw(inputId,btn){
    const inp=document.getElementById(inputId);
    const show=inp.type==='password';inp.type=show?'text':'password';
    btn.style.opacity=show?'1':'0.5';
}

// ── Add modal: toggle verify row text based on role ────────────
function toggleVerifyRow(){
    const role=document.getElementById('addRoleSelect').value;
    document.getElementById('verifySubText').textContent=
        role==='author'?'Skip OTP for this author on first login':'Readers don\'t use OTP — this is informational only';
}

// ── Edit modal ─────────────────────────────────────────────────
let editId=null;
function openEditModal(u){
    editId=u.id;
    document.getElementById('editBody').innerHTML=`
        <div class="field-row">
            <div class="field-group">
                <label class="field-label">First Name</label>
                <input type="text" id="e_first" class="field-input" value="${esc(u.first_name)}">
            </div>
            <div class="field-group">
                <label class="field-label">Last Name</label>
                <input type="text" id="e_last" class="field-input" value="${esc(u.last_name)}">
            </div>
        </div>
        <div class="field-row">
            <div class="field-group">
                <label class="field-label">Username</label>
                <input type="text" id="e_uname" class="field-input" value="${esc(u.user_name)}">
            </div>
            <div class="field-group">
                <label class="field-label">Role</label>
                <select id="e_type" class="field-select">
                    <option value="reader" ${u.user_type==='reader'?'selected':''}>Reader</option>
                    <option value="author" ${u.user_type==='author'?'selected':''}>Author</option>
                </select>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Email Address</label>
            <input type="email" id="e_email" class="field-input" value="${esc(u.email)}">
        </div>
        <div class="modal-section">Change Password</div>
        <div class="field-group">
            <label class="field-label">New Password <span style="color:var(--text-muted);text-transform:none;font-size:10px;letter-spacing:0">(leave blank to keep current)</span></label>
            <div class="pw-wrap">
                <input type="password" id="e_pw" class="field-input" placeholder="Enter new password..." style="padding-right:40px"
                       oninput="checkPw(this,'editBar','editHint')">
                <button type="button" class="pw-toggle" onclick="togglePw('e_pw',this)">
                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <div class="pw-strength"><div class="pw-bar" id="editBar"></div></div>
            <div class="pw-hint" id="editHint" style="color:var(--text-muted)">Leave blank to keep existing password</div>
        </div>
        <div class="modal-section">Email Verification</div>
        <div class="toggle-row">
            <div>
                <div class="toggle-label">Mark as Verified</div>
                <div class="toggle-sub">Authors: skip OTP on next login. Readers: informational only.</div>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" id="e_verif" ${u.email_verified?'checked':''}>
                <span class="toggle-slider"></span>
            </label>
        </div>
    `;
    openModal('editModal');
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

function submitEdit(){
    const first=document.getElementById('e_first').value.trim();
    const email=document.getElementById('e_email').value.trim();
    if(!first||!email){showToast('First name and email are required','error');return}
    const btn=document.getElementById('editSaveBtn');
    btn.textContent='Saving...';btn.disabled=true;
    const fd=new FormData();
    fd.append('action','edit_user');
    fd.append('id',editId);
    fd.append('first_name',first);
    fd.append('last_name',document.getElementById('e_last').value.trim());
    fd.append('user_name',document.getElementById('e_uname').value.trim());
    fd.append('email',email);
    fd.append('user_type',document.getElementById('e_type').value);
    fd.append('password',document.getElementById('e_pw').value);
    fd.append('email_verified',document.getElementById('e_verif').checked?1:0);
    fetch('admin_ajax.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(d=>{
            if(d.success){showToast('User updated','success');closeModal('editModal');setTimeout(()=>location.reload(),900)}
            else showToast(d.msg||'Update failed','error');
        })
        .catch(()=>showToast('Request failed — check that admin_ajax.php exists in /admin/','error'))
        .finally(()=>{btn.textContent='Save Changes';btn.disabled=false});
}

// ── Verify toggle (AJAX) ───────────────────────────────────────
function ajaxVerify(id,state){
    const fd=new FormData();fd.append('action','toggle_verify');fd.append('id',id);fd.append('state',state);
    fetch('admin_ajax.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(d=>{
            if(d.success){showToast(state?'Email verified':'Verification revoked','success');setTimeout(()=>location.reload(),900)}
            else showToast(d.msg||'Failed','error');
        })
        .catch(()=>showToast('Request failed — check that admin_ajax.php exists in /admin/','error'));
}

// ── Delete modal ───────────────────────────────────────────────
let delId=null;
function openDeleteModal(id,name){
    delId=id;document.getElementById('delName').textContent=name;openModal('deleteModal');
}
document.getElementById('delConfirmBtn').addEventListener('click',function(){
    if(!delId)return;
    this.textContent='Deleting...';this.disabled=true;
    const fd=new FormData();fd.append('action','delete_user');fd.append('id',delId);
    fetch('admin_ajax.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(d=>{
            if(d.success){
                closeModal('deleteModal');
                const row=document.getElementById('row-'+delId);
                if(row){row.style.transition='opacity 0.3s,transform 0.3s';row.style.opacity='0';row.style.transform='translateX(-10px)';setTimeout(()=>row.remove(),320)}
                showToast('User deleted','success');updateCount();
            } else showToast(d.msg||'Delete failed','error');
        })
        .catch(()=>showToast('Request failed — check that admin_ajax.php exists in /admin/','error'))
        .finally(()=>{this.textContent='Delete Permanently';this.disabled=false});
});

// ── Live table filter ──────────────────────────────────────────
document.getElementById('tableSearch').addEventListener('input',function(){
    const q=this.value.toLowerCase().trim();
    let vis=0;
    document.querySelectorAll('#tableBody tr[id]').forEach(row=>{
        const match=!q||row.dataset.name?.includes(q)||row.dataset.uname?.includes(q)||row.dataset.email?.includes(q);
        row.style.display=match?'':'none';if(match)vis++;
    });
    updateCount(vis);
});

function updateCount(n){
    const el=document.getElementById('resultsCount');if(!el)return;
    const c=n!==undefined?n:document.querySelectorAll('#tableBody tr[id]:not([style*="none"])').length;
    el.textContent=c+(c===1?' user':' users');
}
</script>
</body>
</html>