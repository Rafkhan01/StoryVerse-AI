<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../admin_login.php'); exit;
}
require_once '../db_connect.php';

// ── Sidebar badge counts ────────────────────────────────────────────────
$badge_stories = $badge_comments = 0;
try {
    $badge_stories = (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status='pending'")->fetchColumn()
                   + (int)$pdo->query("SELECT COUNT(*) FROM story_parts WHERE status='pending'")->fetchColumn();
} catch (Exception $e) {}
try {
    $badge_comments = (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE manually_flagged=1")->fetchColumn();
} catch (Exception $e) {}

// ── ML status ───────────────────────────────────────────────────────────
$ml_status = 'offline';
$ch = curl_init('http://127.0.0.1:8000/');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
curl_exec($ch);
$ml_status = curl_errno($ch) === 0 ? 'online' : 'offline';
curl_close($ch);

$today = date('l, F j, Y');

// ── Filter & search ─────────────────────────────────────────────────────
$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['q'] ?? '');
if (!in_array($filter, ['all','pending','approved','rejected'])) $filter = 'all';

$where_parts = [];
$params      = [];

if ($filter !== 'all') {
    $where_parts[] = 's.status = ?';
    $params[]      = $filter;
}
if ($search !== '') {
    $where_parts[] = '(s.title LIKE ? OR s.created_by LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// ── Fetch stories with stats ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        s.story_id, s.title, s.created_by, s.category, s.description,
        s.cover_image_url, s.total_parts, s.current_part_no,
        s.status, s.created_at, s.like_count,
        (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.story_id) AS total_views,
        (SELECT COUNT(*) FROM story_parts sp WHERE sp.story_id = s.story_id) AS part_count,
        (SELECT COUNT(*) FROM story_parts sp WHERE sp.story_id = s.story_id AND sp.status = 'pending') AS parts_pending,
        (SELECT COUNT(*) FROM comments c WHERE c.story_id = s.story_id) AS comment_count
    FROM stories s
    $where
    ORDER BY
        CASE s.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
        s.created_at DESC
");
$stmt->execute($params);
$stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Filter tab counts ────────────────────────────────────────────────────
$counts = [];
$counts['all']      = (int)$pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn();
$counts['pending']  = (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status='pending'")->fetchColumn();
$counts['approved'] = (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status='approved'")->fetchColumn();
$counts['rejected'] = (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status='rejected'")->fetchColumn();

// ── Pending parts count (for the tab label) ──────────────────────────────
$pending_parts_total = (int)$pdo->query("SELECT COUNT(*) FROM story_parts WHERE status='pending'")->fetchColumn();

// ── Categories (for edit modal dropdown) ────────────────────────────────
$categories = ['Fantasy','Science Fiction','Mystery','Thriller','Romance','Horror','Adventure','Drama','Comedy','General'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stories — StoryVerse Admin</title>
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
    --font-display:'Cinzel Decorative',serif;
    --font-body:'Rajdhani',sans-serif;
    --font-mono:'Space Mono',monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-body);background:var(--bg);color:var(--text-primary);min-height:100vh;display:flex;overflow-x:hidden}

/* ══ SIDEBAR (identical to other admin pages) ══ */
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

/* ══ MAIN LAYOUT ══ */
.main-wrapper{margin-left:var(--sidebar-w);width:calc(100% - var(--sidebar-w));min-height:100vh;display:flex;flex-direction:column;transition:margin-left var(--tr),width var(--tr)}
.main-wrapper.expanded{margin-left:var(--sidebar-collapsed);width:calc(100% - var(--sidebar-collapsed))}

/* ══ TOPBAR ══ */
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

/* ══ PAGE CONTENT ══ */
.page-content{padding:28px 28px 48px;flex:1}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;flex-wrap:wrap}
.page-header-left h1{font-family:var(--font-display);font-size:22px;color:var(--text-primary);margin-bottom:4px}
.page-header-left p{font-size:14px;color:var(--text-secondary)}

/* ══ PENDING ALERT ══ */
.pending-alert{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,#FFFBEB,#FEF3C7);border:1px solid #FCD34D;border-radius:10px;padding:12px 18px;margin-bottom:22px;font-size:13px;font-weight:600;color:#92400E}
.pending-alert svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}
.pending-alert a{color:#92400E;text-decoration:underline;text-underline-offset:2px;cursor:pointer}

/* ══ FILTER TABS ══ */
.filter-bar{display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.filter-tab{display:inline-flex;align-items:center;gap:7px;padding:7px 16px;border-radius:8px;border:1px solid var(--border);background:var(--card-bg);font-family:var(--font-body);font-size:13px;font-weight:600;color:var(--text-secondary);text-decoration:none;transition:all 0.15s;white-space:nowrap}
.filter-tab:hover{border-color:var(--gold);color:var(--text-primary)}
.filter-tab.active{background:var(--text-primary);color:#fff;border-color:var(--text-primary)}
.filter-tab.pending-tab.active{background:#92400E;border-color:#92400E}
.filter-tab .tab-count{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;background:rgba(255,255,255,0.18)}
.filter-tab:not(.active) .tab-count{background:var(--divider);color:var(--text-muted)}
.filter-tab:not(.active).pending-tab .tab-count{background:#FEF3C7;color:#92400E}

/* ══ TABLE CONTROLS ══ */
.table-controls{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.table-search{display:flex;align-items:center;gap:8px;background:var(--card-bg);border:1px solid var(--border);border-radius:9px;padding:8px 14px;flex:1;max-width:360px;transition:border-color 0.18s,box-shadow 0.18s}
.table-search:focus-within{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.table-search svg{width:15px;height:15px;stroke:var(--text-muted);fill:none;stroke-width:2;flex-shrink:0}
.table-search input{border:none;background:none;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);width:100%}
.table-search input::placeholder{color:var(--text-muted)}
.results-count{font-family:var(--font-mono);font-size:11px;color:var(--text-muted);margin-left:auto;white-space:nowrap}

/* ══ STORY CARDS ══ */
.stories-list{display:flex;flex-direction:column;gap:14px}

.story-card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);overflow:hidden;transition:box-shadow 0.18s}
.story-card:hover{box-shadow:0 6px 24px rgba(15,20,50,0.10)}

.story-main{display:flex;align-items:flex-start;gap:16px;padding:18px 20px;cursor:pointer;user-select:none}
.story-cover{width:52px;height:70px;border-radius:7px;object-fit:cover;flex-shrink:0;background:var(--divider);display:flex;align-items:center;justify-content:center;overflow:hidden}
.story-cover img{width:100%;height:100%;object-fit:cover}
.story-cover-placeholder{width:52px;height:70px;border-radius:7px;background:linear-gradient(135deg,var(--divider),var(--border));display:flex;align-items:center;justify-content:center;flex-shrink:0}
.story-cover-placeholder svg{width:22px;height:22px;stroke:var(--text-muted);fill:none;stroke-width:1.5}

.story-info{flex:1;min-width:0}
.story-title{font-family:var(--font-body);font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.story-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px}
.story-author{font-family:var(--font-mono);font-size:11px;color:var(--text-muted)}
.story-category{font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;background:var(--divider);color:var(--text-secondary)}
.story-date{font-family:var(--font-mono);font-size:10px;color:var(--text-muted)}

.story-stats{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.stat-item{display:flex;align-items:center;gap:5px;font-family:var(--font-mono);font-size:11px;color:var(--text-muted)}
.stat-item svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round}
.parts-pending-badge{background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;font-family:var(--font-mono);font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;margin-left:4px}

.story-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0}

/* Status badge */
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-family:var(--font-body);font-size:12px;font-weight:700;border:1px solid;white-space:nowrap}
.status-badge.pending  {background:#FEF3C7;color:#92400E;border-color:#FCD34D}
.status-badge.approved {background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.status-badge.rejected {background:#FEF2F2;color:#991B1B;border-color:#FCA5A5}
.status-badge svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}

/* Story action buttons */
.story-actions{display:flex;align-items:center;gap:6px}
.saction-btn{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1px solid var(--border);background:var(--card-bg);cursor:pointer;transition:all 0.15s;flex-shrink:0}
.saction-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.saction-btn.approve{color:var(--success)}
.saction-btn.approve:hover{background:#ECFDF5;border-color:#A7F3D0}
.saction-btn.reject{color:var(--warning)}
.saction-btn.reject:hover{background:#FFFBEB;border-color:#FCD34D}
.saction-btn.edit{color:var(--azure)}
.saction-btn.edit:hover{background:#EFF6FF;border-color:#BFDBFE}
.saction-btn.read{color:var(--purple)}
.saction-btn.read:hover{background:#F5F3FF;border-color:#DDD6FE}

/* Read Part Modal — wider, scroll for long content */
.modal.modal-read{max-width:780px}
.read-part-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px 24px;background:var(--divider);border-bottom:1px solid var(--border)}
.read-meta-chip{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:3px 10px;border-radius:5px;background:var(--card-bg);border:1px solid var(--border);color:var(--text-secondary)}
.read-meta-chip.status-pending  {background:#FEF3C7;color:#92400E;border-color:#FCD34D}
.read-meta-chip.status-approved {background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.read-meta-chip.status-rejected {background:#FEF2F2;color:#991B1B;border-color:#FCA5A5}
.read-part-content{font-family:'Georgia',serif;font-size:15px;line-height:1.85;color:var(--text-primary);white-space:pre-wrap;word-break:break-word;padding:4px 0}

/* Expand chevron */
.expand-btn{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1px solid var(--border);background:var(--divider);cursor:pointer;transition:transform 0.2s,background 0.15s;flex-shrink:0}
.expand-btn svg{width:13px;height:13px;stroke:var(--text-muted);fill:none;stroke-width:2.5;stroke-linecap:round;transition:transform 0.25s}
.expand-btn.open svg{transform:rotate(180deg)}

/* ══ PARTS PANEL ══ */
.parts-panel{display:none;border-top:1px solid var(--divider);background:#FAFBFF}
.parts-panel.open{display:block}

.parts-header{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 10px;border-bottom:1px solid var(--divider)}
.parts-header-label{font-family:var(--font-mono);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);font-weight:700}
.parts-header-note{font-size:12px;color:var(--text-muted)}

.part-row{display:flex;align-items:center;gap:14px;padding:11px 20px;border-bottom:1px solid var(--divider);transition:background 0.12s}
.part-row:last-child{border-bottom:none}
.part-row:hover{background:#F0F4FF}

.part-num{font-family:var(--font-mono);font-size:11px;font-weight:700;color:var(--text-muted);flex-shrink:0;width:48px}
.part-preview{flex:1;font-size:13px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
.part-deadline{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);flex-shrink:0;white-space:nowrap}
.part-actions{display:flex;align-items:center;gap:5px;flex-shrink:0}

.part-status-badge{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;border:1px solid;flex-shrink:0;white-space:nowrap}
.part-status-badge.pending  {background:#FEF3C7;color:#92400E;border-color:#FCD34D}
.part-status-badge.approved {background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.part-status-badge.rejected {background:#FEF2F2;color:#991B1B;border-color:#FCA5A5}

/* ══ EMPTY STATE ══ */
.empty-state{text-align:center;padding:64px 24px;color:var(--text-muted)}
.empty-state svg{width:52px;height:52px;stroke:var(--border);fill:none;stroke-width:1.4;margin:0 auto 16px;display:block}
.empty-state p{font-size:14px}

/* ══ MODAL ══ */
.modal-overlay{position:fixed;inset:0;background:rgba(11,17,32,0.55);backdrop-filter:blur(4px);z-index:200;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;pointer-events:none;transition:opacity 0.22s}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--card-bg);border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,0.18);width:100%;max-width:560px;transform:translateY(16px) scale(0.98);transition:transform 0.22s cubic-bezier(0.34,1.56,0.64,1);overflow:hidden;max-height:92vh;display:flex;flex-direction:column}
.modal-overlay.open .modal{transform:translateY(0) scale(1)}
.modal.modal-sm{max-width:420px}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 16px;border-bottom:1px solid var(--divider);flex-shrink:0}
.modal-title{font-family:var(--font-display);font-size:15px;color:var(--text-primary)}
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
.field-textarea{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:9px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);resize:vertical;min-height:90px;transition:border-color 0.18s,box-shadow 0.18s;line-height:1.5}
.field-textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-select{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:9px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239CA3AF' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:38px;transition:border-color 0.18s}
.field-select:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field-hint{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:4px}
.modal-section{font-family:var(--font-mono);font-size:9px;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-muted);margin:18px 0 12px;display:flex;align-items:center;gap:8px}
.modal-section::after{content:'';flex:1;height:1px;background:var(--divider)}

/* Warning box inside modal */
.modal-warn{display:flex;align-items:flex-start;gap:10px;background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:12px 14px;font-size:13px;color:#92400E;margin-bottom:16px}
.modal-warn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;margin-top:1px}

/* Buttons */
.btn-cancel{padding:9px 18px;border-radius:8px;border:1px solid var(--border);background:var(--card-bg);font-family:var(--font-body);font-size:13px;font-weight:700;color:var(--text-secondary);cursor:pointer;transition:background 0.15s}
.btn-cancel:hover{background:var(--border)}
.btn-save{padding:9px 22px;border-radius:8px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:opacity 0.15s,transform 0.15s}
.btn-save:hover{opacity:0.9;transform:translateY(-1px)}
.btn-approve{padding:9px 22px;border-radius:8px;background:var(--success);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:background 0.15s}
.btn-approve:hover{background:#059669}
.btn-reject{padding:9px 22px;border-radius:8px;background:var(--warning);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:background 0.15s}
.btn-reject:hover{background:#D97706}
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

@media(max-width:900px){.field-row{grid-template-columns:1fr}.story-right{flex-direction:row;align-items:center}}
@media(max-width:600px){.page-content{padding:16px 14px 40px}.topbar-date{display:none}.topbar-search{max-width:160px}.story-stats{display:none}}
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
        <a href="admin_users.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="nav-label">Users</span><span class="tooltip">Users</span>
        </a>
        <a href="admin_stories.php" class="nav-item active">
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
            <input type="text" placeholder="Search stories, authors..." id="globalSearch">
        </div>
        <div class="topbar-right">
            <div class="ml-pill <?= $ml_status ?>">
                <span class="ml-dot"></span>ML API &nbsp;<?= strtoupper($ml_status) ?>
            </div>
            <span class="topbar-date"><?= $today ?></span>
        </div>
    </header>

    <main class="page-content">

        <!-- Page header -->
        <div class="page-header fade-up">
            <div class="page-header-left">
                <h1>Stories</h1>
                <p>Review, approve, edit and manage all stories and their parts.</p>
            </div>
        </div>

        <!-- Pending alert -->
        <?php if ($badge_stories > 0): ?>
        <div class="pending-alert fade-up" style="animation-delay:0.04s">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?php
                $parts = [];
                if ($counts['pending'] > 0) $parts[] = $counts['pending'] . ' ' . ($counts['pending'] === 1 ? 'story' : 'stories');
                if ($pending_parts_total > 0) $parts[] = $pending_parts_total . ' ' . ($pending_parts_total === 1 ? 'part' : 'parts');
                echo implode(' and ', $parts) . ' waiting for review.';
            ?>
            &nbsp;<a onclick="setFilter('pending')">Show pending</a>
        </div>
        <?php endif; ?>

        <!-- Filter tabs -->
        <div class="filter-bar fade-up" style="animation-delay:0.08s">
            <?php
            $tabs = [
                'all'      => ['label' => 'All Stories',  'extra' => ''],
                'pending'  => ['label' => 'Pending',      'extra' => 'pending-tab'],
                'approved' => ['label' => 'Approved',     'extra' => ''],
                'rejected' => ['label' => 'Rejected',     'extra' => ''],
            ];
            foreach ($tabs as $key => $tab):
                $url = '?filter='.$key.($search ? '&q='.urlencode($search) : '');
            ?>
            <a href="<?= $url ?>" class="filter-tab <?= $tab['extra'] ?> <?= $filter===$key?'active':'' ?>" id="tab-<?= $key ?>">
                <?= $tab['label'] ?>
                <span class="tab-count"><?= $counts[$key] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Table search row -->
        <div class="table-controls fade-up" style="animation-delay:0.12s">
            <div class="table-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="tableSearch" placeholder="Filter by title or author..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <span class="results-count" id="resultsCount"><?= count($stories) ?> <?= count($stories)===1?'story':'stories' ?></span>
        </div>

        <!-- Stories list -->
        <div class="stories-list fade-up" style="animation-delay:0.16s" id="storiesList">

        <?php if (empty($stories)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <p>No stories found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</p>
            </div>
        <?php else: ?>

        <?php foreach ($stories as $s):
            $sid      = $s['story_id'];
            $status   = $s['status'] ?? 'pending';
            $joined   = !empty($s['created_at']) ? date('M j, Y', strtotime($s['created_at'])) : '—';
            $deadline_fmt = '';

            // Fetch parts for this story
            $pstmt = $pdo->prepare("
                SELECT part_id, part_number, content, upload_date, prediction_deadline, status
                FROM story_parts WHERE story_id = ? ORDER BY part_number ASC
            ");
            $pstmt->execute([$sid]);
            $parts = $pstmt->fetchAll(PDO::FETCH_ASSOC);

            // Build JS data for edit story modal
            $js_story = json_encode([
                'id'             => $sid,
                'title'          => $s['title'],
                'category'       => $s['category'] ?? '',
                'description'    => $s['description'] ?? '',
                'cover_image_url'=> $s['cover_image_url'] ?? '',
                'total_parts'    => $s['total_parts'],
            ]);
        ?>
        <div class="story-card status-<?= $status ?>" id="story-card-<?= $sid ?>"
             data-title="<?= htmlspecialchars(strtolower($s['title'])) ?>"
             data-author="<?= htmlspecialchars(strtolower($s['created_by'])) ?>">

            <!-- Main row -->
            <div class="story-main" onclick="toggleParts(<?= $sid ?>)">

                <!-- Cover -->
                <?php if (!empty($s['cover_image_url'])): ?>
                    <div class="story-cover"><img src="<?= htmlspecialchars($s['cover_image_url']) ?>" alt="" loading="lazy" onerror="this.parentElement.innerHTML='<svg viewBox=\'0 0 24 24\'><path d=\'M4 19.5A2.5 2.5 0 0 1 6.5 17H20\'/><path d=\'M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z\'/></svg>'"></div>
                <?php else: ?>
                    <div class="story-cover-placeholder"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
                <?php endif; ?>

                <!-- Info -->
                <div class="story-info">
                    <div class="story-title" title="<?= htmlspecialchars($s['title']) ?>"><?= htmlspecialchars($s['title']) ?></div>
                    <div class="story-meta">
                        <span class="story-author">by @<?= htmlspecialchars($s['created_by']) ?></span>
                        <?php if (!empty($s['category'])): ?>
                            <span class="story-category"><?= htmlspecialchars($s['category']) ?></span>
                        <?php endif; ?>
                        <span class="story-date"><?= $joined ?></span>
                    </div>
                    <div class="story-stats">
                        <span class="stat-item">
                            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            <?= $s['part_count'] ?>/<?= $s['total_parts'] ?> parts
                            <?php if ($s['parts_pending'] > 0): ?>
                                <span class="parts-pending-badge"><?= $s['parts_pending'] ?> pending</span>
                            <?php endif; ?>
                        </span>
                        <span class="stat-item">
                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <?= number_format($s['total_views'] ?? 0) ?>
                        </span>
                        <span class="stat-item">
                            <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                            <?= number_format($s['like_count'] ?? 0) ?>
                        </span>
                        <span class="stat-item">
                            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <?= number_format($s['comment_count'] ?? 0) ?>
                        </span>
                    </div>
                </div>

                <!-- Right: status + actions -->
                <div class="story-right" onclick="event.stopPropagation()">
                    <!-- Status badge -->
                    <span class="status-badge <?= $status ?>">
                        <?php if ($status === 'approved'): ?>
                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Approved
                        <?php elseif ($status === 'rejected'): ?>
                            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Rejected
                        <?php else: ?>
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Pending
                        <?php endif; ?>
                    </span>

                    <!-- Action buttons -->
                    <div class="story-actions">
                        <?php if ($status !== 'approved'): ?>
                        <button class="saction-btn approve" title="Approve story"
                            onclick="ajaxStory('approve_story',<?= $sid ?>)">
                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        </button>
                        <?php endif; ?>
                        <?php if ($status !== 'rejected'): ?>
                        <button class="saction-btn reject" title="Reject story"
                            onclick="openRejectModal('story',<?= $sid ?>)">
                            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                        <?php endif; ?>
                        <button class="saction-btn edit" title="Edit story"
                            onclick='openEditStoryModal(<?= $js_story ?>)'>
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="saction-btn delete" title="Delete story"
                            onclick="openDeleteModal('story',<?= $sid ?>,'<?= htmlspecialchars(addslashes($s['title'])) ?>')">
                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
                        <button class="expand-btn" id="expand-<?= $sid ?>" title="View parts">
                            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Parts panel -->
            <div class="parts-panel" id="parts-<?= $sid ?>">
                <div class="parts-header">
                    <span class="parts-header-label">Parts (<?= count($parts) ?>)</span>
                    <span class="parts-header-note">Click parts to expand &amp; manage</span>
                </div>

                <?php if (empty($parts)): ?>
                    <div style="padding:16px 20px;font-size:13px;color:var(--text-muted);font-style:italic">No parts submitted yet.</div>
                <?php else: ?>
                    <?php foreach ($parts as $p):
                        $pstatus   = $p['status'] ?? 'pending';
                        $preview   = mb_substr(strip_tags($p['content'] ?? ''), 0, 80) . '...';
                        $dl        = !empty($p['prediction_deadline'])
                                     ? date('M j, Y', strtotime($p['prediction_deadline']))
                                     : '—';
                        $ud        = !empty($p['upload_date'])
                                     ? date('M j, Y', strtotime($p['upload_date']))
                                     : '—';
                        $js_part   = json_encode([
                            'id'                  => (int)$p['part_id'],
                            'part_number'         => (int)$p['part_number'],
                            'content'             => $p['content'] ?? '',
                            'upload_date'         => $p['upload_date'] ?? '',
                            'prediction_deadline' => $p['prediction_deadline'] ?? '',
                        ]);
                    ?>
                    <div class="part-row" id="part-row-<?= $p['part_id'] ?>">
                        <span class="part-num">Part <?= $p['part_number'] ?></span>
                        <span class="part-preview" title="<?= htmlspecialchars($preview) ?>"><?= htmlspecialchars($preview) ?></span>
                        <span class="part-deadline" title="Upload: <?= $ud ?>">Deadline: <?= $dl ?></span>
                        <span class="part-status-badge <?= $pstatus ?>"><?= ucfirst($pstatus) ?></span>
                        <div class="part-actions">
                            <?php if ($pstatus !== 'approved'): ?>
                            <button class="saction-btn approve" title="Approve part"
                                onclick="ajaxPart('approve_part',<?= $p['part_id'] ?>,<?= $sid ?>)">
                                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                            <?php endif; ?>
                            <?php if ($pstatus !== 'rejected'): ?>
                            <button class="saction-btn reject" title="Reject part"
                                onclick="openRejectModal('part',<?= $p['part_id'] ?>,<?= $sid ?>)">
                                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                            <?php endif; ?>
                            <button class="saction-btn read" title="Read full content"
                                onclick='openReadPartModal(<?= json_encode([
                                    "part_id"          => (int)$p["part_id"],
                                    "part_number"      => (int)$p["part_number"],
                                    "status"           => $pstatus,
                                    "upload_date"      => $ud,
                                    "upload_date_raw"  => $p["upload_date"] ?? "",
                                    "deadline"         => $dl,
                                    "deadline_raw"     => $p["prediction_deadline"] ?? "",
                                    "content"          => $p["content"] ?? "",
                                    "story_title"      => $s["title"],
                                ]) ?>)'>
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button class="saction-btn edit" title="Edit part"
                                onclick='openEditPartModal(<?= $js_part ?>)'>
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="saction-btn delete" title="Delete part"
                                onclick="openDeleteModal('part',<?= $p['part_id'] ?>,'Part <?= $p['part_number'] ?> of this story',<?= $sid ?>)">
                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div><!-- /story-card -->
        <?php endforeach; ?>

        <?php endif; ?>
        </div><!-- /stories-list -->

    </main>
</div><!-- /main-wrapper -->

<!-- ═══ EDIT STORY MODAL ══════════════════════════════════════════ -->
<div class="modal-overlay" id="editStoryModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Story</span>
            <button class="modal-close" onclick="closeModal('editStoryModal')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="field-group">
                <label class="field-label">Story Title *</label>
                <input type="text" id="es_title" class="field-input" placeholder="Story title">
            </div>
            <div class="field-row">
                <div class="field-group">
                    <label class="field-label">Category</label>
                    <select id="es_category" class="field-select">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Total Parts Planned</label>
                    <input type="number" id="es_total_parts" class="field-input" min="1" value="10">
                </div>
            </div>
            <div class="field-group">
                <label class="field-label">Cover Image URL</label>
                <input type="url" id="es_cover" class="field-input" placeholder="https://example.com/cover.jpg">
                <div class="field-hint">Leave blank for default cover</div>
            </div>
            <div class="field-group">
                <label class="field-label">Description</label>
                <textarea id="es_desc" class="field-textarea" rows="4" placeholder="Story description..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('editStoryModal')">Cancel</button>
            <button class="btn-save" onclick="submitEditStory()">Save Changes</button>
        </div>
    </div>
</div>

<!-- ═══ EDIT PART MODAL ═══════════════════════════════════════════ -->
<div class="modal-overlay" id="editPartModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Part</span>
            <button class="modal-close" onclick="closeModal('editPartModal')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="field-row">
                <div class="field-group">
                    <label class="field-label">Upload Date</label>
                    <input type="date" id="ep_upload_date" class="field-input">
                </div>
                <div class="field-group">
                    <label class="field-label">Prediction Deadline</label>
                    <input type="datetime-local" id="ep_deadline" class="field-input">
                </div>
            </div>
            <div class="field-group">
                <label class="field-label">Content *</label>
                <textarea id="ep_content" class="field-textarea" rows="10" placeholder="Part content..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('editPartModal')">Cancel</button>
            <button class="btn-save" onclick="submitEditPart()">Save Changes</button>
        </div>
    </div>
</div>

<!-- ═══ READ PART MODAL ════════════════════════════════════════════ -->
<div class="modal-overlay" id="readPartModal">
    <div class="modal modal-read">
        <div class="modal-header">
            <span class="modal-title" id="rp_title">Read Part</span>
            <button class="modal-close" onclick="closeModal('readPartModal')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <!-- Meta strip: part number, status, dates -->
        <div class="read-part-meta">
            <span class="read-meta-chip" id="rp_partnum">Part —</span>
            <span class="read-meta-chip" id="rp_status">—</span>
            <span class="read-meta-chip" id="rp_upload">Upload: —</span>
            <span class="read-meta-chip" id="rp_deadline">Deadline: —</span>
            <span style="margin-left:auto;display:flex;gap:8px">
                <!-- Quick-action approve/reject buttons injected by JS -->
                <span id="rp_action_area"></span>
            </span>
        </div>
        <div class="modal-body">
            <div class="read-part-content" id="rp_content"></div>
        </div>
        <div class="modal-footer">
            <span style="font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-right:auto" id="rp_wordcount"></span>
            <button class="btn-cancel" onclick="closeModal('readPartModal')">Close</button>
            <button class="btn-save" id="rp_edit_btn" onclick="switchReadToEdit()">Edit This Part</button>
        </div>
    </div>
</div>

<!-- ═══ REJECT MODAL ═════════════════════════════════════════════ -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <span class="modal-title" id="rejectTitle">Reject Story</span>
            <button class="modal-close" onclick="closeModal('rejectModal')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-warn">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                The author will not be notified automatically. The status will change to rejected and they can see it on their dashboard.
            </div>
            <div class="field-group">
                <label class="field-label">Reason (optional)</label>
                <textarea id="rejectReason" class="field-textarea" rows="3" placeholder="e.g. Content guidelines violation, inappropriate language..."></textarea>
                <div class="field-hint">Logged in activity log for admin reference.</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('rejectModal')">Cancel</button>
            <button class="btn-reject" onclick="submitReject()">Confirm Reject</button>
        </div>
    </div>
</div>

<!-- ═══ DELETE MODAL ═════════════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <span class="modal-title" id="deleteTitle">Delete</span>
            <button class="modal-close" onclick="closeModal('deleteModal')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p style="font-size:14px;color:var(--text-secondary);line-height:1.7">
                You are about to permanently delete
                <strong id="deleteTargetName" style="color:var(--text-primary)"></strong>.
                <span id="deleteExtraNote" style="display:none"><br><br>Deleting a story will also remove all its parts, comments, predictions and scores.</span>
                <br><br><span style="color:var(--danger);font-weight:700">This cannot be undone.</span>
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
            <button class="btn-danger" id="deleteConfirmBtn">Delete Permanently</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// ── Sidebar toggle ─────────────────────────────────────────────
const sidebar=document.getElementById('sidebar'),mainWrapper=document.getElementById('mainWrapper'),hamburger=document.getElementById('hamburger');
hamburger.addEventListener('click',()=>{const c=sidebar.classList.toggle('collapsed');mainWrapper.classList.toggle('expanded',c);hamburger.classList.toggle('active',c);localStorage.setItem('sv_sidebar',c?'1':'0')});
if(localStorage.getItem('sv_sidebar')==='1'){sidebar.classList.add('collapsed');mainWrapper.classList.add('expanded');hamburger.classList.add('active')}

document.getElementById('globalSearch').addEventListener('keydown',function(e){if(e.key==='Enter'&&this.value.trim())location.href='admin_stories.php?q='+encodeURIComponent(this.value.trim())});

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg,type='default'){
    const tc=document.getElementById('toastContainer'),t=document.createElement('div');
    t.className='toast';
    t.style.borderLeftColor=type==='success'?'var(--success)':type==='error'?'var(--danger)':'var(--gold)';
    t.textContent=msg;tc.appendChild(t);
    setTimeout(()=>{t.classList.add('fade-out');setTimeout(()=>t.remove(),280)},3500);
}

// ── Modal helpers ──────────────────────────────────────────────
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden'}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow=''}
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',function(e){if(e.target===this)closeModal(this.id)}));
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-overlay.open').forEach(m=>closeModal(m.id))});

// ── Parts expand/collapse ──────────────────────────────────────
function toggleParts(sid){
    const panel=document.getElementById('parts-'+sid);
    const btn=document.getElementById('expand-'+sid);
    const isOpen=panel.classList.toggle('open');
    btn.classList.toggle('open',isOpen);
}

// ── Filter shortcut (from pending alert link) ──────────────────
function setFilter(f){location.href='admin_stories.php?filter='+f}

// ── Live client-side search ────────────────────────────────────
document.getElementById('tableSearch').addEventListener('input',function(){
    const q=this.value.toLowerCase().trim();
    let vis=0;
    document.querySelectorAll('.story-card[data-title]').forEach(card=>{
        const match=!q||card.dataset.title.includes(q)||card.dataset.author.includes(q);
        card.style.display=match?'':'none';
        if(match)vis++;
    });
    document.getElementById('resultsCount').textContent=vis+(vis===1?' story':' stories');
});

// ── Generic AJAX helper ────────────────────────────────────────
function ajax(data, onSuccess, btnEl){
    if(btnEl){btnEl.disabled=true}
    const fd=new FormData();
    Object.entries(data).forEach(([k,v])=>fd.append(k,v));
    return fetch('admin_ajax.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(d=>{
            if(d.success){onSuccess(d)}
            else showToast(d.msg||'Action failed','error');
            return d;
        })
        .catch(()=>showToast('Request failed — check admin_ajax.php path','error'))
        .finally(()=>{if(btnEl)btnEl.disabled=false});
}

// ── Story: Approve ─────────────────────────────────────────────
function ajaxStory(action, sid){
    ajax({action, id:sid}, d=>{
        showToast('Story approved','success');
        // Refresh card status
        const card=document.getElementById('story-card-'+sid);
        if(card){
            card.className=card.className.replace(/status-\w+/,'status-approved');
            card.querySelector('.status-badge').className='status-badge approved';
            card.querySelector('.status-badge').innerHTML='<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Approved';
        }
        // Auto-expand to show part status changes
        const panel=document.getElementById('parts-'+sid);
        if(panel&&!panel.classList.contains('open')) toggleParts(sid);
        setTimeout(()=>location.reload(),1200);
    });
}

// ── Part: Approve ──────────────────────────────────────────────
function ajaxPart(action, pid, sid){
    ajax({action, id:pid}, d=>{
        showToast('Part approved','success');
        const badge=document.querySelector('#part-row-'+pid+' .part-status-badge');
        if(badge){badge.className='part-status-badge approved';badge.textContent='Approved'}
        setTimeout(()=>location.reload(),1000);
    });
}

// ── Reject modal ───────────────────────────────────────────────
let _rejectType=null, _rejectId=null, _rejectStoryId=null;

function openRejectModal(type, id, storyId){
    _rejectType=type; _rejectId=id; _rejectStoryId=storyId||null;
    document.getElementById('rejectTitle').textContent = type==='story' ? 'Reject Story' : 'Reject Part';
    document.getElementById('rejectReason').value='';
    openModal('rejectModal');
}

function submitReject(){
    const reason=document.getElementById('rejectReason').value.trim();
    const action=_rejectType==='story' ? 'reject_story' : 'reject_part';
    const btn=document.querySelector('#rejectModal .btn-reject');
    btn.textContent='Rejecting...';btn.disabled=true;
    ajax({action, id:_rejectId, reason}, d=>{
        closeModal('rejectModal');
        showToast((_rejectType==='story'?'Story':'Part')+' rejected','success');
        setTimeout(()=>location.reload(),900);
    }, btn);
    btn.textContent='Confirm Reject';btn.disabled=false;
}

// ── Edit Story modal ───────────────────────────────────────────
let _editStoryId=null;

function openEditStoryModal(s){
    _editStoryId=s.id;
    document.getElementById('es_title').value       = s.title||'';
    document.getElementById('es_desc').value        = s.description||'';
    document.getElementById('es_cover').value       = s.cover_image_url||'';
    document.getElementById('es_total_parts').value = s.total_parts||10;
    // Set category
    const sel=document.getElementById('es_category');
    for(let o of sel.options) if(o.value===s.category) o.selected=true;
    openModal('editStoryModal');
}

function submitEditStory(){
    const title=document.getElementById('es_title').value.trim();
    if(!title){showToast('Title is required','error');return}
    const btn=document.querySelector('#editStoryModal .btn-save');
    btn.textContent='Saving...';btn.disabled=true;
    ajax({
        action:'edit_story', id:_editStoryId,
        title, description:document.getElementById('es_desc').value.trim(),
        category:document.getElementById('es_category').value,
        cover_image_url:document.getElementById('es_cover').value.trim(),
        total_parts:document.getElementById('es_total_parts').value,
    }, d=>{
        closeModal('editStoryModal');
        showToast('Story updated','success');
        // Update the title in the card live
        const card=document.getElementById('story-card-'+_editStoryId);
        if(card) card.querySelector('.story-title').textContent=title;
        setTimeout(()=>location.reload(),900);
    });
    btn.textContent='Save Changes';btn.disabled=false;
}

// ── Read Part modal ────────────────────────────────────────────
let _readPartData = null; // holds the data so "Edit This Part" can pre-fill

function openReadPartModal(p) {
    _readPartData = p;

    // Header title
    document.getElementById('rp_title').textContent =
        '"' + p.story_title + '" — Part ' + p.part_number;

    // Meta chips
    document.getElementById('rp_partnum').textContent = 'Part ' + p.part_number;

    const statusChip = document.getElementById('rp_status');
    statusChip.textContent = p.status.charAt(0).toUpperCase() + p.status.slice(1);
    statusChip.className = 'read-meta-chip status-' + p.status;

    document.getElementById('rp_upload').textContent   = 'Upload: '   + (p.upload_date || '—');
    document.getElementById('rp_deadline').textContent = 'Deadline: ' + (p.deadline    || '—');

    // Full content — preserve line breaks
    const content = p.content || '(No content)';
    document.getElementById('rp_content').textContent = content;

    // Word count
    const wc = content.trim().split(/\s+/).filter(Boolean).length;
    document.getElementById('rp_wordcount').textContent = wc.toLocaleString() + ' words';

    openModal('readPartModal');
}

// "Edit This Part" button inside the read modal — switches to edit modal
function switchReadToEdit() {
    if (!_readPartData) return;
    closeModal('readPartModal');
    // openEditPartModal expects the same shape as js_part
    openEditPartModal({
        id:                  _readPartData.part_id  || 0,
        part_number:         _readPartData.part_number,
        content:             _readPartData.content  || '',
        upload_date:         _readPartData.upload_date_raw || '',
        prediction_deadline: _readPartData.deadline_raw   || '',
    });
}

// ── Edit Part modal ────────────────────────────────────────────
let _editPartId=null;

function openEditPartModal(p){
    _editPartId=p.id;
    document.getElementById('ep_content').value     = p.content||'';
    document.getElementById('ep_upload_date').value = p.upload_date ? p.upload_date.substring(0,10) : '';
    // datetime-local needs format YYYY-MM-DDTHH:mm
    const dl=p.prediction_deadline||'';
    document.getElementById('ep_deadline').value = dl ? dl.replace(' ','T').substring(0,16) : '';
    openModal('editPartModal');
}

function submitEditPart(){
    const content=document.getElementById('ep_content').value.trim();
    if(!content){showToast('Content is required','error');return}
    const btn=document.querySelector('#editPartModal .btn-save');
    btn.textContent='Saving...';btn.disabled=true;
    ajax({
        action:'edit_part', id:_editPartId,
        content,
        upload_date:document.getElementById('ep_upload_date').value,
        prediction_deadline:document.getElementById('ep_deadline').value,
    }, d=>{
        closeModal('editPartModal');
        showToast('Part updated','success');
        setTimeout(()=>location.reload(),900);
    });
    btn.textContent='Save Changes';btn.disabled=false;
}

// ── Delete modal ───────────────────────────────────────────────
let _delType=null, _delId=null, _delStoryId=null;

function openDeleteModal(type, id, name, storyId){
    _delType=type; _delId=id; _delStoryId=storyId||null;
    document.getElementById('deleteTitle').textContent = type==='story' ? 'Delete Story' : 'Delete Part';
    document.getElementById('deleteTargetName').textContent = '"'+name+'"';
    document.getElementById('deleteExtraNote').style.display = type==='story' ? '' : 'none';
    openModal('deleteModal');
}

document.getElementById('deleteConfirmBtn').addEventListener('click',function(){
    const action = _delType==='story' ? 'delete_story' : 'delete_part';
    this.textContent='Deleting...';this.disabled=true;
    ajax({action, id:_delId}, d=>{
        closeModal('deleteModal');
        if(_delType==='story'){
            const card=document.getElementById('story-card-'+_delId);
            if(card){card.style.transition='opacity 0.3s,transform 0.3s';card.style.opacity='0';card.style.transform='translateY(-6px)';setTimeout(()=>card.remove(),320)}
        } else {
            const row=document.getElementById('part-row-'+_delId);
            if(row){row.style.transition='opacity 0.3s';row.style.opacity='0';setTimeout(()=>row.remove(),320)}
        }
        showToast((_delType==='story'?'Story':'Part')+' deleted','success');
    });
    this.textContent='Delete Permanently';this.disabled=false;
});

// Auto-expand story if filter=pending (show all pending parts immediately)
<?php if ($filter === 'pending'): ?>
document.querySelectorAll('.story-card').forEach(card=>{
    const sid=card.id.replace('story-card-','');
    const panel=document.getElementById('parts-'+sid);
    if(panel){panel.classList.add('open');document.getElementById('expand-'+sid).classList.add('open')}
});
<?php endif; ?>
</script>
</body>
</html>
