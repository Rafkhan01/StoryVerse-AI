<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../admin_login.php'); exit;
}
require_once '../db_connect.php';

// ── Sidebar badge counts ───────────────────────────────────────
$badge_stories = $badge_comments = 0;
try {
    $badge_stories = (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status='pending'")->fetchColumn()
                   + (int)$pdo->query("SELECT COUNT(*) FROM story_parts WHERE status='pending'")->fetchColumn();
} catch (Exception $e) {}
try {
    $badge_comments = (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE manually_flagged=1")->fetchColumn();
} catch (Exception $e) {}

// ── ML status ──────────────────────────────────────────────────
$ml_status = 'offline';
$ch = curl_init('http://127.0.0.1:8000/');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>2]);
curl_exec($ch);
$ml_status = curl_errno($ch) === 0 ? 'online' : 'offline';
curl_close($ch);

$today = date('l, F j, Y');
$flash = ['type'=>'', 'msg'=>''];

// ── Helper ─────────────────────────────────────────────────────
function log_admin(PDO $pdo, string $text, string $type='update'): void {
    try {
        $pdo->prepare("INSERT INTO admin_activity_log(action_text,action_type,created_at)VALUES(?,?,NOW())")
            ->execute([$text,$type]);
    } catch(Exception $e){}
}

// ── POST handlers ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['form_action'] ?? '';

    if ($act === 'add' || $act === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $heading = trim($_POST['heading']  ?? '');
        $message = trim($_POST['message']  ?? '');
        $type    = in_array($_POST['type']??'', ['warning','update','info']) ? $_POST['type'] : 'info';
        $active  = isset($_POST['is_active']) ? 1 : 0;
        $ends_at = trim($_POST['ends_at'] ?? '');
        $ends_at = ($ends_at !== '') ? $ends_at : null;

        if (!$heading || !$message) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Heading and message are required.'];
        } else {
            if ($act === 'add') {
                $pdo->prepare("INSERT INTO announcements (heading,message,type,is_active,ends_at) VALUES(?,?,?,?,?)")
                    ->execute([$heading,$message,$type,$active,$ends_at]);
                log_admin($pdo,"Announcement \"$heading\" created",'add');
                $_SESSION['flash'] = ['type'=>'success','msg'=>"Announcement \"$heading\" created."];
            } else {
                if (!$id) {
                    $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid ID.'];
                } else {
                    $pdo->prepare("UPDATE announcements SET heading=?,message=?,type=?,is_active=?,ends_at=? WHERE id=?")
                        ->execute([$heading,$message,$type,$active,$ends_at,$id]);
                    log_admin($pdo,"Announcement #$id updated",'update');
                    $_SESSION['flash'] = ['type'=>'success','msg'=>"Announcement updated."];
                }
            }
        }
        header('Location: admin_announcement.php'); exit;
    }

    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Get heading for log
            $row = $pdo->prepare("SELECT heading FROM announcements WHERE id=?");
            $row->execute([$id]);
            $h = $row->fetchColumn() ?: "#$id";
            $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
            log_admin($pdo,"Announcement \"$h\" deleted",'delete');
            $_SESSION['flash'] = ['type'=>'success','msg'=>"Announcement deleted."];
        }
        header('Location: admin_announcement.php'); exit;
    }

    if ($act === 'toggle') {
        $id    = (int)($_POST['id']    ?? 0);
        $state = (int)($_POST['state'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE announcements SET is_active=? WHERE id=?")->execute([$state,$id]);
            log_admin($pdo,"Announcement #$id ".($state?'activated':'deactivated'),'update');
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Status updated.'];
        }
        header('Location: admin_announcement.php'); exit;
    }
}

// Pick up flash
if (!empty($_SESSION['flash'])) { $flash=$_SESSION['flash']; unset($_SESSION['flash']); }

// ── Fetch all announcements ─────────────────────────────────────
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// ── Stats ───────────────────────────────────────────────────────
$total_ann   = count($announcements);
$active_ann  = count(array_filter($announcements, fn($a)=>$a['is_active']==1));
$expired_ann = count(array_filter($announcements, fn($a)=>$a['ends_at'] && $a['ends_at'] < date('Y-m-d')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements — StoryVerse Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
    --sidebar-bg:#0B1120; --sidebar-w:240px; --sidebar-collapsed:64px; --topbar-h:56px;
    --bg:#F4F6FB; --card-bg:#fff; --card-shadow:0 2px 16px rgba(15,20,50,.07);
    --card-radius:12px; --card-border:#EEF0F7;
    --text-primary:#1A1D2E; --text-secondary:#6B7280; --text-muted:#9CA3AF;
    --gold:#F5A623; --gold-dark:#E8920F; --gold-glow:rgba(245,166,35,.18);
    --azure:#3B82F6; --success:#10B981; --danger:#EF4444; --warning:#F59E0B; --purple:#8B5CF6;
    --border:#E8EAF0; --divider:#F3F4F8;
    --tr:.26s cubic-bezier(.4,0,.2,1);
    --font-display:'Cinzel Decorative',serif;
    --font-body:'Rajdhani',sans-serif;
    --font-mono:'Space Mono',monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-body);background:var(--bg);color:var(--text-primary);min-height:100vh;display:flex;overflow-x:hidden}

/* ── SIDEBAR ── */
.sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--sidebar-bg);display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;transition:width var(--tr);overflow:hidden}
.sidebar.collapsed{width:var(--sidebar-collapsed)}
.sidebar-brand{display:flex;align-items:center;gap:10px;padding:0 20px;height:var(--topbar-h);border-bottom:1px solid rgba(255,255,255,.05);flex-shrink:0;overflow:hidden}
.brand-mark{width:28px;height:28px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-family:var(--font-display);font-size:13px;color:#fff;font-weight:700}
.brand-name{font-family:var(--font-display);font-size:12px;color:#fff;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .brand-name{opacity:0;pointer-events:none}
.sidebar-nav{flex:1;padding:12px 0;overflow-y:auto;overflow-x:hidden}
.sidebar-nav::-webkit-scrollbar{width:3px}
.sidebar-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:99px}
.nav-section-label{font-family:var(--font-mono);font-size:9px;letter-spacing:.12em;color:rgba(255,255,255,.28);padding:14px 20px 6px;text-transform:uppercase;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-section-label{opacity:0}
.nav-item{display:flex;align-items:center;gap:12px;padding:10px 20px;color:rgba(255,255,255,.72);text-decoration:none;font-family:var(--font-body);font-size:14px;font-weight:500;white-space:nowrap;position:relative;transition:color .18s,background .18s;border-left:3px solid transparent}
.nav-item:hover{color:#fff;background:rgba(255,255,255,.05)}
.nav-item.active{color:var(--gold);background:linear-gradient(90deg,rgba(245,166,35,.12) 0%,transparent 100%);border-left-color:var(--gold)}
.nav-item svg{width:18px;height:18px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.nav-label{transition:opacity var(--tr)}
.sidebar.collapsed .nav-label{opacity:0}
.nav-badge{margin-left:auto;background:var(--danger);color:#fff;font-family:var(--font-mono);font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;flex-shrink:0;transition:opacity var(--tr)}
.sidebar.collapsed .nav-badge{opacity:0}
.nav-item .tooltip{position:absolute;left:calc(var(--sidebar-collapsed) + 8px);background:#1E2A45;color:#fff;padding:5px 10px;border-radius:6px;font-size:12px;font-family:var(--font-body);white-space:nowrap;pointer-events:none;opacity:0;transform:translateX(-4px);transition:opacity .15s,transform .15s;z-index:200;box-shadow:0 4px 12px rgba(0,0,0,.3)}
.sidebar.collapsed .nav-item:hover .tooltip{opacity:1;transform:translateX(0)}
.sidebar-bottom{padding:12px 12px 16px;border-top:1px solid rgba(255,255,255,.05)}
.logout-btn{display:flex;align-items:center;gap:10px;width:100%;padding:9px 8px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.18);border-radius:8px;color:#F87171;font-family:var(--font-body);font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;overflow:hidden;transition:background .18s;text-decoration:none}
.logout-btn:hover{background:rgba(239,68,68,.16);color:#FCA5A5}
.logout-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}
.logout-text{transition:opacity var(--tr)}
.sidebar.collapsed .logout-text{opacity:0}

/* ── MAIN ── */
.main-wrapper{margin-left:var(--sidebar-w);width:calc(100% - var(--sidebar-w));min-height:100vh;display:flex;flex-direction:column;transition:margin-left var(--tr),width var(--tr)}
.main-wrapper.expanded{margin-left:var(--sidebar-collapsed);width:calc(100% - var(--sidebar-collapsed))}

/* ── TOPBAR ── */
.topbar{height:var(--topbar-h);background:var(--card-bg);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;gap:16px;position:sticky;top:0;z-index:50}
.hamburger{width:36px;height:36px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;background:none;border:none;cursor:pointer;border-radius:8px;transition:background .15s;flex-shrink:0}
.hamburger:hover{background:var(--divider)}
.hamburger span{display:block;width:18px;height:1.8px;background:var(--text-primary);border-radius:2px;transition:transform .25s,opacity .25s,width .25s;transform-origin:center}
.hamburger.active span:nth-child(1){transform:translateY(6.8px) rotate(45deg)}
.hamburger.active span:nth-child(2){opacity:0;width:0}
.hamburger.active span:nth-child(3){transform:translateY(-6.8px) rotate(-45deg)}
.topbar-search{display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:7px 12px;flex:1;max-width:320px;transition:border-color .18s,box-shadow .18s}
.topbar-search:focus-within{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.topbar-search svg{width:15px;height:15px;stroke:var(--text-muted);fill:none;stroke-width:2}
.topbar-search input{border:none;background:none;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);width:100%}
.topbar-search input::placeholder{color:var(--text-muted)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:12px}
.ml-pill{display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:99px;font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.04em;border:1px solid}
.ml-pill.online{background:#ECFDF5;color:var(--success);border-color:rgba(16,185,129,.2)}
.ml-pill.offline{background:#FEF2F2;color:var(--danger);border-color:rgba(239,68,68,.2)}
.ml-dot{width:7px;height:7px;border-radius:50%;background:currentColor;animation:pulse-dot 2s infinite}
@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.4}}
.topbar-date{font-family:var(--font-mono);font-size:11px;color:var(--text-muted);white-space:nowrap}

/* ── PAGE ── */
.page-content{padding:28px 28px 56px;flex:1}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;flex-wrap:wrap}
.page-header-left h1{font-family:var(--font-display);font-size:22px;color:var(--text-primary);margin-bottom:4px}
.page-header-left p{font-size:14px;color:var(--text-secondary)}

/* Flash */
.flash{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;margin-bottom:20px;font-size:14px;font-weight:600;border:1px solid}
.flash.success{background:#ECFDF5;border-color:rgba(16,185,129,.3);color:#065F46}
.flash.error{background:#FEF2F2;border-color:rgba(239,68,68,.3);color:#991B1B}
.flash svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}

/* Stat strip */
.stat-strip{display:flex;gap:14px;margin-bottom:26px;flex-wrap:wrap}
.stat-chip{background:var(--card-bg);border:1px solid var(--card-border);border-radius:10px;box-shadow:var(--card-shadow);padding:14px 20px;flex:1;min-width:100px}
.stat-chip-label{font-family:var(--font-mono);font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px}
.stat-chip-value{font-family:var(--font-body);font-size:26px;font-weight:700;color:var(--text-primary);line-height:1}

/* ── TWO COLUMN LAYOUT ── */
.main-grid{display:grid;grid-template-columns:420px 1fr;gap:24px;align-items:start}

/* ── FORM CARD ── */
.form-card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);position:sticky;top:calc(var(--topbar-h) + 20px);overflow:hidden}
.form-card-header{padding:18px 22px 14px;border-bottom:1px solid var(--divider);display:flex;align-items:center;justify-content:space-between}
.form-card-title{font-family:var(--font-body);font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-primary)}
.form-mode-badge{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:3px 9px;border-radius:4px;letter-spacing:.04em}
.form-mode-badge.add{background:rgba(245,166,35,.12);color:var(--gold-dark);border:1px solid rgba(245,166,35,.3)}
.form-mode-badge.edit{background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE}
.form-body{padding:20px 22px}

/* Field styles */
.field-group{margin-bottom:16px}
.field-label{display:block;font-family:var(--font-body);font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:6px}
.field-label .req{color:var(--danger)}
.field-input{width:100%;padding:10px 13px;background:var(--bg);border:1px solid var(--border);border-radius:9px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);transition:border-color .18s,box-shadow .18s}
.field-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-input::placeholder{color:var(--text-muted)}
.field-textarea{width:100%;padding:10px 13px;background:var(--bg);border:1px solid var(--border);border-radius:9px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);resize:vertical;min-height:90px;line-height:1.55;transition:border-color .18s,box-shadow .18s}
.field-textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-textarea::placeholder{color:var(--text-muted)}
.field-hint{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:4px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/* Type selector — styled radio cards */
.type-selector{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.type-option{position:relative}
.type-option input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.type-label{display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 8px;border-radius:9px;border:1.5px solid var(--border);cursor:pointer;transition:all .18s;font-family:var(--font-body);font-size:12px;font-weight:700;color:var(--text-muted);user-select:none;text-align:center}
.type-label svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.type-option input:checked + .type-label.warning{border-color:var(--danger);background:rgba(239,68,68,.07);color:var(--danger)}
.type-option input:checked + .type-label.update{border-color:var(--success);background:rgba(16,185,129,.07);color:var(--success)}
.type-option input:checked + .type-label.info{border-color:var(--warning);background:rgba(245,158,11,.07);color:var(--warning)}
.type-label:hover{border-color:var(--gold)}

/* Active toggle */
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:11px 13px;background:var(--bg);border:1px solid var(--border);border-radius:9px}
.toggle-label{font-size:13px;font-weight:600;color:var(--text-primary)}
.toggle-sub{font-size:11px;color:var(--text-muted);margin-top:2px}
.toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;border-radius:99px;background:var(--border);cursor:pointer;transition:background .2s}
.toggle-slider::before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;left:3px;top:3px;background:#fff;transition:transform .2s;box-shadow:0 1px 4px rgba(0,0,0,.15)}
.toggle-switch input:checked + .toggle-slider{background:var(--success)}
.toggle-switch input:checked + .toggle-slider::before{transform:translateX(20px)}

/* Preview box */
.preview-box{border-radius:10px;padding:14px 18px;margin-top:16px;border-left:4px solid;display:none}
.preview-box.show{display:block}
.preview-box.warning{background:rgba(239,68,68,.07);border-color:var(--danger)}
.preview-box.update{background:rgba(16,185,129,.07);border-color:var(--success)}
.preview-box.info{background:rgba(245,158,11,.07);border-color:var(--warning)}
.preview-heading{font-family:var(--font-body);font-size:14px;font-weight:700;margin-bottom:4px}
.preview-box.warning .preview-heading{color:var(--danger)}
.preview-box.update .preview-heading{color:var(--success)}
.preview-box.info .preview-heading{color:var(--warning)}
.preview-msg{font-family:var(--font-body);font-size:13px;color:var(--text-secondary);line-height:1.6}
.preview-label{font-family:var(--font-mono);font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}

.form-footer{padding:14px 22px;border-top:1px solid var(--divider);display:flex;gap:10px;background:var(--divider)}
.btn-save{flex:1;padding:10px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border:none;border-radius:8px;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:opacity .15s,transform .15s}
.btn-save:hover{opacity:.9;transform:translateY(-1px)}
.btn-cancel-edit{padding:10px 16px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;color:var(--text-secondary);font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;transition:background .15s;display:none}
.btn-cancel-edit:hover{background:var(--border)}
.btn-cancel-edit.show{display:block}

/* ── ANNOUNCEMENTS LIST ── */
.ann-list{display:flex;flex-direction:column;gap:14px}

.ann-card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);overflow:hidden;transition:box-shadow .18s}
.ann-card:hover{box-shadow:0 6px 24px rgba(15,20,50,.10)}
.ann-card.inactive{opacity:.65}

/* Left stripe per type */
.ann-card.type-warning{border-left:4px solid var(--danger)}
.ann-card.type-update {border-left:4px solid var(--success)}
.ann-card.type-info   {border-left:4px solid var(--warning)}

.ann-inner{padding:18px 20px}
.ann-top{display:flex;align-items:flex-start;gap:14px;margin-bottom:10px}

/* Type icon badge */
.ann-type-badge{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ann-type-badge svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.type-warning .ann-type-badge{background:rgba(239,68,68,.1);color:var(--danger)}
.type-update  .ann-type-badge{background:rgba(16,185,129,.1);color:var(--success)}
.type-info    .ann-type-badge{background:rgba(245,158,11,.1);color:var(--warning)}

.ann-meta{flex:1;min-width:0}
.ann-heading{font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:4px;line-height:1.3}
.ann-badges{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.type-pill{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;border:1px solid;text-transform:uppercase;letter-spacing:.04em}
.type-pill.warning{background:rgba(239,68,68,.08);color:var(--danger);border-color:rgba(239,68,68,.25)}
.type-pill.update {background:rgba(16,185,129,.08);color:var(--success);border-color:rgba(16,185,129,.25)}
.type-pill.info   {background:rgba(245,158,11,.08);color:var(--warning);border-color:rgba(245,158,11,.25)}

.status-pill{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;border:1px solid}
.status-pill.active{background:#ECFDF5;color:var(--success);border-color:#A7F3D0}
.status-pill.inactive{background:var(--divider);color:var(--text-muted);border-color:var(--border)}
.status-pill.expired{background:#FEF3C7;color:#92400E;border-color:#FCD34D}

.ann-date-info{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);white-space:nowrap;flex-shrink:0}

.ann-message{font-size:14px;color:var(--text-secondary);line-height:1.6;margin-bottom:14px;padding:10px 12px;background:var(--bg);border-radius:8px;border:1px solid var(--border)}

.ann-footer{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ann-ends{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);flex:1}
.ann-ends strong{color:var(--text-secondary)}

/* Ann action buttons */
.aact-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;border:1px solid var(--border);background:var(--card-bg);font-family:var(--font-body);font-size:12px;font-weight:700;cursor:pointer;transition:all .15s}
.aact-btn svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.aact-btn.edit-btn{color:var(--azure)}
.aact-btn.edit-btn:hover{background:#EFF6FF;border-color:#BFDBFE}
.aact-btn.toggle-on{color:var(--success)}
.aact-btn.toggle-on:hover{background:#ECFDF5;border-color:#A7F3D0}
.aact-btn.toggle-off{color:var(--text-muted)}
.aact-btn.toggle-off:hover{background:var(--divider);border-color:var(--border)}
.aact-btn.del-btn{color:var(--danger)}
.aact-btn.del-btn:hover{background:#FEF2F2;border-color:#FCA5A5}

/* Delete confirm strip */
.del-confirm{display:none;align-items:center;gap:10px;padding:10px 20px;background:#FEF2F2;border-top:1px solid #FCA5A5;font-size:13px;font-weight:600;color:var(--danger)}
.del-confirm.open{display:flex}
.del-confirm button{padding:4px 14px;border-radius:5px;border:1px solid;font-family:var(--font-body);font-size:12px;font-weight:700;cursor:pointer}
.dc-yes{background:var(--danger);color:#fff;border-color:var(--danger)}
.dc-yes:hover{background:#DC2626}
.dc-no{background:var(--card-bg);color:var(--text-secondary);border-color:var(--border)}

/* Empty state */
.empty-state{text-align:center;padding:56px 24px;color:var(--text-muted)}
.empty-state svg{width:44px;height:44px;stroke:var(--border);fill:none;stroke-width:1.4;margin:0 auto 14px;display:block}

/* Toast */
.toast-container{position:fixed;bottom:28px;right:28px;z-index:999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;padding:12px 18px;background:#1A1D2E;color:#fff;border-radius:10px;font-family:var(--font-body);font-size:13.5px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,.2);animation:toast-in .3s cubic-bezier(.34,1.56,.64,1) forwards;pointer-events:all;border-left:3px solid var(--gold)}
@keyframes toast-in{from{opacity:0;transform:translateY(16px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.toast.fade-out{animation:toast-out .25s ease forwards}
@keyframes toast-out{to{opacity:0;transform:translateY(8px) scale(.97)}}

.fade-up{opacity:0;transform:translateY(14px);animation:fade-up-in .38s ease forwards}
@keyframes fade-up-in{to{opacity:1;transform:translateY(0)}}

@media(max-width:1100px){.main-grid{grid-template-columns:1fr}.form-card{position:static}}
@media(max-width:700px){.field-row{grid-template-columns:1fr}.page-content{padding:16px 14px 40px}.topbar-date{display:none}}
</style>
</head>
<body>

<!-- ═══ SIDEBAR ════════════════════════════════════════════════ -->
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
        <a href="admin_users.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="nav-label">Users</span><span class="tooltip">Users</span>
        </a>
        <a href="admin_stories.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <span class="nav-label">Stories</span>
            <?php if($badge_stories>0):?><span class="nav-badge"><?=$badge_stories?></span><?php endif;?>
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
            <?php if($badge_comments>0):?><span class="nav-badge"><?=$badge_comments?></span><?php endif;?>
            <span class="tooltip">Comments</span>
        </a>
        <a href="admin_leaderboard.php" class="nav-item">
            <svg viewBox="0 0 24 24"><polyline points="18 20 18 10"/><polyline points="12 20 12 4"/><polyline points="6 20 6 14"/></svg>
            <span class="nav-label">Leaderboard</span><span class="tooltip">Leaderboard</span>
        </a>
        <a href="admin_announcement.php" class="nav-item active">
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

<!-- ═══ MAIN ════════════════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
        <div class="topbar-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search..." id="globalSearch">
        </div>
        <div class="topbar-right">
            <div class="ml-pill <?=$ml_status?>">
                <span class="ml-dot"></span>ML API &nbsp;<?=strtoupper($ml_status)?>
            </div>
            <span class="topbar-date"><?=$today?></span>
        </div>
    </header>

    <main class="page-content">

        <div class="page-header fade-up">
            <div class="page-header-left">
                <h1>Announcements</h1>
                <p>Create and manage site-wide announcements shown to all users on the home page.</p>
            </div>
        </div>

        <?php if($flash['msg']):?>
        <div class="flash <?=$flash['type']?> fade-up">
            <?php if($flash['type']==='success'):?>
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?php else:?>
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php endif;?>
            <?=htmlspecialchars($flash['msg'])?>
        </div>
        <?php endif;?>

        <!-- Stats -->
        <div class="stat-strip fade-up" style="animation-delay:.04s">
            <div class="stat-chip">
                <div class="stat-chip-label">Total</div>
                <div class="stat-chip-value"><?=$total_ann?></div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-label">Active</div>
                <div class="stat-chip-value" style="color:var(--success)"><?=$active_ann?></div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-label">Expired</div>
                <div class="stat-chip-value" style="color:var(--warning)"><?=$expired_ann?></div>
            </div>
        </div>

        <!-- Two-column grid -->
        <div class="main-grid fade-up" style="animation-delay:.08s">

            <!-- ── Form card ── -->
            <div class="form-card" id="formCard">
                <div class="form-card-header">
                    <span class="form-card-title" id="formTitle">New Announcement</span>
                    <span class="form-mode-badge add" id="formBadge">NEW</span>
                </div>

                <form method="POST" action="" id="annForm">
                    <input type="hidden" name="form_action" id="formAction" value="add">
                    <input type="hidden" name="id" id="editId" value="">

                    <div class="form-body">

                        <div class="field-group">
                            <label class="field-label">Heading <span class="req">*</span></label>
                            <input type="text" name="heading" id="fHeading" class="field-input"
                                   placeholder="e.g. Scheduled Maintenance Tonight"
                                   oninput="updatePreview()" required>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Message <span class="req">*</span></label>
                            <textarea name="message" id="fMessage" class="field-textarea" rows="3"
                                      placeholder="Write the full announcement message here..."
                                      oninput="updatePreview()" required></textarea>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Type <span class="req">*</span></label>
                            <div class="type-selector">
                                <div class="type-option">
                                    <input type="radio" name="type" id="typeWarning" value="warning" onchange="updatePreview()">
                                    <label for="typeWarning" class="type-label warning">
                                        <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        Warning
                                        <span style="font-size:10px;font-weight:400;color:inherit;opacity:.7">Red</span>
                                    </label>
                                </div>
                                <div class="type-option">
                                    <input type="radio" name="type" id="typeUpdate" value="update" checked onchange="updatePreview()">
                                    <label for="typeUpdate" class="type-label update">
                                        <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                                        Update
                                        <span style="font-size:10px;font-weight:400;color:inherit;opacity:.7">Green</span>
                                    </label>
                                </div>
                                <div class="type-option">
                                    <input type="radio" name="type" id="typeInfo" value="info" onchange="updatePreview()">
                                    <label for="typeInfo" class="type-label info">
                                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        Info
                                        <span style="font-size:10px;font-weight:400;color:inherit;opacity:.7">Yellow</span>
                                    </label>
                                </div>
                            </div>
                            <div class="field-hint" style="margin-top:6px">Warning = maintenance/errors &middot; Update = new features &middot; Info = general notices</div>
                        </div>

                        <div class="field-row">
                            <div class="field-group">
                                <label class="field-label">End Date</label>
                                <input type="date" name="ends_at" id="fEndsAt" class="field-input"
                                       min="<?=date('Y-m-d')?>">
                                <div class="field-hint">Auto-hides on user side after this date. Leave blank for no expiry.</div>
                            </div>
                            <div class="field-group" style="display:flex;flex-direction:column;justify-content:center">
                                <div class="toggle-row">
                                    <div>
                                        <div class="toggle-label">Active</div>
                                        <div class="toggle-sub">Show on user home page</div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="is_active" id="fActive" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Live preview -->
                        <div class="preview-label">Live Preview</div>
                        <div class="preview-box update show" id="previewBox">
                            <div class="preview-heading" id="previewHeading">Announcement heading will appear here</div>
                            <div class="preview-msg" id="previewMsg">Your announcement message will appear here as users will see it on the home page.</div>
                        </div>

                    </div><!-- /form-body -->

                    <div class="form-footer">
                        <a href="admin_announcement.php" class="btn-cancel-edit" id="cancelBtn">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">Create Announcement</button>
                    </div>
                </form>
            </div>

            <!-- ── Announcements list ── -->
            <div>
                <?php if(empty($announcements)):?>
                    <div class="empty-state" style="background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border)">
                        <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        <p>No announcements yet. Create your first one.</p>
                    </div>
                <?php else:?>
                <div class="ann-list">
                <?php foreach($announcements as $ann):
                    $is_expired = $ann['ends_at'] && $ann['ends_at'] < date('Y-m-d');
                    $is_active  = $ann['is_active'] == 1 && !$is_expired;

                    $status_label = $is_expired ? 'expired' : ($ann['is_active']==1 ? 'active' : 'inactive');

                    $created = date('M j, Y', strtotime($ann['created_at']));
                    $ends    = $ann['ends_at'] ? date('M j, Y', strtotime($ann['ends_at'])) : null;

                    $js = htmlspecialchars(json_encode([
                        'id'        => (int)$ann['id'],
                        'heading'   => $ann['heading'],
                        'message'   => $ann['message'],
                        'type'      => $ann['type'],
                        'is_active' => (int)$ann['is_active'],
                        'ends_at'   => $ann['ends_at'] ?? '',
                    ]),ENT_QUOTES);

                    // Type icon SVG
                    $icon_svg = match($ann['type']) {
                        'warning' => '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                        'update'  => '<svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
                        default   => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
                    };
                ?>
                <div class="ann-card type-<?=$ann['type']?> <?=$ann['is_active']==0||$is_expired?'inactive':''?>" id="ann-card-<?=$ann['id']?>">
                    <div class="ann-inner">
                        <div class="ann-top">
                            <div class="ann-type-badge"><?=$icon_svg?></div>
                            <div class="ann-meta">
                                <div class="ann-heading"><?=htmlspecialchars($ann['heading'])?></div>
                                <div class="ann-badges">
                                    <span class="type-pill <?=$ann['type']?>"><?=ucfirst($ann['type'])?></span>
                                    <span class="status-pill <?=$status_label?>"><?=ucfirst($status_label)?></span>
                                </div>
                            </div>
                            <div class="ann-date-info">Created <?=$created?></div>
                        </div>

                        <div class="ann-message"><?=htmlspecialchars($ann['message'])?></div>

                        <div class="ann-footer">
                            <div class="ann-ends">
                                <?php if($ends):?>
                                    Ends: <strong><?=$ends?></strong>
                                    <?php if($is_expired):?>
                                        <span style="color:var(--warning);margin-left:4px">(expired)</span>
                                    <?php endif;?>
                                <?php else:?>
                                    <span style="color:var(--text-muted)">No expiry date</span>
                                <?php endif;?>
                            </div>

                            <!-- Toggle active -->
                            <form method="POST" action="" style="display:contents">
                                <input type="hidden" name="form_action" value="toggle">
                                <input type="hidden" name="id" value="<?=$ann['id']?>">
                                <input type="hidden" name="state" value="<?=$ann['is_active']==1?0:1?>">
                                <?php if($ann['is_active']==1):?>
                                    <button type="submit" class="aact-btn toggle-off" title="Deactivate">
                                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                        Hide
                                    </button>
                                <?php else:?>
                                    <button type="submit" class="aact-btn toggle-on" title="Activate">
                                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                        Show
                                    </button>
                                <?php endif;?>
                            </form>

                            <button class="aact-btn edit-btn" onclick='prefillEdit(<?=$js?>)'>
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Edit
                            </button>

                            <button class="aact-btn del-btn" onclick="openDel(<?=$ann['id']?>)">
                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Delete confirm -->
                    <div class="del-confirm" id="del-<?=$ann['id']?>">
                        <span>Delete "<?=htmlspecialchars(mb_substr($ann['heading'],0,40))?>"?</span>
                        <form method="POST" action="" style="display:contents">
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?=$ann['id']?>">
                            <button type="submit" class="dc-yes">Delete</button>
                            <button type="button" class="dc-no" onclick="closeDel(<?=$ann['id']?>)">Cancel</button>
                        </form>
                    </div>
                </div>
                <?php endforeach;?>
                </div>
                <?php endif;?>
            </div>
        </div>

    </main>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// Sidebar
const sidebar=document.getElementById('sidebar'),mainWrapper=document.getElementById('mainWrapper'),hamburger=document.getElementById('hamburger');
hamburger.addEventListener('click',()=>{const c=sidebar.classList.toggle('collapsed');mainWrapper.classList.toggle('expanded',c);hamburger.classList.toggle('active',c);localStorage.setItem('sv_sidebar',c?'1':'0')});
if(localStorage.getItem('sv_sidebar')==='1'){sidebar.classList.add('collapsed');mainWrapper.classList.add('expanded');hamburger.classList.add('active')}

// Live preview
function updatePreview(){
    const heading = document.getElementById('fHeading').value.trim() || 'Announcement heading will appear here';
    const message = document.getElementById('fMessage').value.trim() || 'Your announcement message will appear here as users will see it on the home page.';
    const type    = document.querySelector('input[name="type"]:checked')?.value || 'update';
    const box     = document.getElementById('previewBox');

    document.getElementById('previewHeading').textContent = heading;
    document.getElementById('previewMsg').textContent     = message;

    box.className = `preview-box ${type} show`;
}

// Edit prefill
function prefillEdit(a){
    document.getElementById('formAction').value    = 'edit';
    document.getElementById('editId').value         = a.id;
    document.getElementById('fHeading').value       = a.heading;
    document.getElementById('fMessage').value       = a.message;
    document.getElementById('fEndsAt').value        = a.ends_at || '';
    document.getElementById('fActive').checked      = a.is_active == 1;
    document.getElementById('formTitle').textContent= 'Edit Announcement';
    document.getElementById('formBadge').textContent= 'EDITING';
    document.getElementById('formBadge').className  = 'form-mode-badge edit';
    document.getElementById('saveBtn').textContent  = 'Save Changes';
    document.getElementById('cancelBtn').classList.add('show');

    // Set type radio
    const radio = document.querySelector(`input[name="type"][value="${a.type}"]`);
    if(radio) radio.checked = true;

    updatePreview();
    document.getElementById('formCard').scrollIntoView({behavior:'smooth', block:'start'});
}

// Delete confirm
function openDel(id){
    document.querySelectorAll('.del-confirm.open').forEach(el=>el.classList.remove('open'));
    document.getElementById('del-'+id).classList.add('open');
}
function closeDel(id){ document.getElementById('del-'+id).classList.remove('open'); }

// Init preview
updatePreview();
</script>
</body>
</html>
