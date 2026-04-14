<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../admin_login.php'); exit;
}
require_once '../db_connect.php';

// ── Sidebar badge counts ─────────────────────────────────────────
$badge_stories = $badge_comments = 0;
try {
    $badge_stories = (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status='pending'")->fetchColumn()
                   + (int)$pdo->query("SELECT COUNT(*) FROM story_parts WHERE status='pending'")->fetchColumn();
} catch (Exception $e) {}
try {
    $badge_comments = (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE manually_flagged=1")->fetchColumn();
} catch (Exception $e) {}

// ── ML status ────────────────────────────────────────────────────
$ml_status = 'offline';
$ch = curl_init('http://127.0.0.1:8000/');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
curl_exec($ch);
$ml_status = curl_errno($ch) === 0 ? 'online' : 'offline';
curl_close($ch);

$today    = date('l, F j, Y');
// ── Resolve admin user_id ────────────────────────────────────────
// If admin_user_id is already stored in session from a previous page load, use it.
// Otherwise find or create an admin row in users table.
if (isset($_SESSION['admin_user_id']) && $_SESSION['admin_user_id'] > 0) {
    $admin_id = (int)$_SESSION['admin_user_id'];
} else {
    // Try to find existing admin row in users table
    $admin_id = (int)$pdo->query("SELECT user_id FROM users WHERE user_type='admin' LIMIT 1")->fetchColumn();
    if (!$admin_id) {
        // No admin row exists — insert a placeholder admin user
        // so private chat has a real sender_id to store in DB
        try {
            $pdo->exec("INSERT INTO users (user_name, password, first_name, user_type, email, created_at)
                        VALUES ('admin', 'admin_placeholder', 'Admin', 'admin', 'admin@storyverse.local', NOW())");
            $admin_id = (int)$pdo->lastInsertId();
        } catch (Exception $e) {
            // Row may have been inserted by another request — try fetching again
            $admin_id = (int)$pdo->query("SELECT user_id FROM users WHERE user_type='admin' LIMIT 1")->fetchColumn();
        }
    }
    // Cache in session so we don't repeat this lookup on every page
    $_SESSION['admin_user_id'] = $admin_id;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat — StoryVerse Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
.topbar{height:var(--topbar-h);background:var(--card-bg);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;gap:16px;position:sticky;top:0;z-index:50;flex-shrink:0}
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

/* ── Chat shell fills remaining height ── */
.chat-area{display:flex;flex:1;overflow:hidden;height:calc(100vh - var(--topbar-h))}

/* ── Author list panel (35%) ── */
/* Change the width value below to adjust the left panel size */
.author-panel{
    width:35%;          /* ← ADJUST LEFT PANEL WIDTH HERE */
    min-width:220px;
    max-width:420px;
    background:var(--card-bg);
    border-right:1px solid var(--border);
    display:flex;
    flex-direction:column;
    flex-shrink:0;
}
.author-panel-head{padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.author-panel-title{font-family:var(--font-display);font-size:11px;color:var(--text-primary);margin-bottom:10px}
.author-search{display:flex;align-items:center;gap:7px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:6px 10px}
.author-search svg{width:13px;height:13px;stroke:var(--text-muted);fill:none;stroke-width:2;flex-shrink:0}
.author-search input{border:none;background:none;outline:none;font-family:var(--font-body);font-size:13px;color:var(--text-primary);width:100%}
.author-list{flex:1;overflow-y:auto}
.author-list::-webkit-scrollbar{width:3px}
.author-list::-webkit-scrollbar-thumb{background:var(--border);border-radius:99px}

.convo-item{display:flex;align-items:center;gap:10px;padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--divider);transition:background 0.15s;position:relative}
.convo-item:hover{background:rgba(245,166,35,0.04)}
.convo-item.active{background:linear-gradient(90deg,rgba(245,166,35,0.1) 0%,transparent 100%);border-left:3px solid var(--gold)}
.convo-item.group-pin{background:linear-gradient(90deg,rgba(59,130,246,0.06) 0%,transparent 100%)}
.convo-item.group-pin.active{border-left-color:var(--azure)}

.convo-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-body);font-size:13px;font-weight:700;color:#fff;flex-shrink:0}
.convo-avatar.av-group{background:linear-gradient(135deg,var(--azure),#6366F1)}
.convo-avatar.av-author{background:linear-gradient(135deg,var(--gold),var(--gold-dark))}

.convo-info{flex:1;min-width:0}
.convo-name{font-family:var(--font-body);font-size:13px;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.convo-preview{font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
.convo-time{font-family:var(--font-mono);font-size:9px;color:var(--text-muted);white-space:nowrap;flex-shrink:0}
.unread-badge{min-width:18px;height:18px;padding:0 5px;border-radius:99px;background:var(--danger);color:#fff;font-family:var(--font-mono);font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}

/* ── Draggable divider ── */
.chat-divider{width:5px;cursor:col-resize;background:var(--border);flex-shrink:0;transition:background 0.15s}
.chat-divider:hover,.chat-divider.dragging{background:var(--gold)}

/* ── Chat panel (remaining) ── */
.chat-panel{flex:1;display:flex;flex-direction:column;min-width:0;background:var(--card-bg)}
.chat-panel-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0;background:linear-gradient(90deg,rgba(245,166,35,0.04),transparent)}
.chat-panel-title{font-family:var(--font-display);font-size:13px;color:var(--text-primary)}
.chat-panel-sub{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:2px}
.chat-icon-wrap{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.chat-icon-wrap svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.chat-icon-wrap.gold{background:rgba(245,166,35,0.12);color:var(--gold)}
.chat-icon-wrap.blue{background:rgba(59,130,246,0.12);color:var(--azure)}

/* ── Messages ── */
.msg-list{flex:1;overflow-y:auto;padding:16px 18px;display:flex;flex-direction:column;gap:10px;background:var(--bg)}
.msg-list::-webkit-scrollbar{width:4px}
.msg-list::-webkit-scrollbar-thumb{background:var(--border);border-radius:99px}

.msg-row{display:flex;flex-direction:column;max-width:72%}
.msg-row.mine{align-self:flex-end;align-items:flex-end}
.msg-row.theirs{align-self:flex-start;align-items:flex-start}

.msg-sender-label{font-family:var(--font-mono);font-size:9px;color:var(--text-muted);margin-bottom:3px;display:flex;align-items:center;gap:5px}
.admin-badge-chat{font-size:8px;font-weight:700;letter-spacing:0.06em;padding:1px 5px;border-radius:3px;background:rgba(245,166,35,0.15);border:1px solid rgba(245,166,35,0.3);color:var(--gold-dark)}

.msg-bubble{padding:10px 15px;border-radius:16px;font-family:var(--font-body);font-size:13.5px;line-height:1.55;word-break:break-word}
.mine .msg-bubble{background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;border-bottom-right-radius:4px}
.theirs .msg-bubble{background:var(--card-bg);border:1px solid var(--border);color:var(--text-primary);border-bottom-left-radius:4px}
.msg-deleted .msg-bubble{background:var(--divider) !important;color:var(--text-muted) !important;font-style:italic;border:1px solid var(--border) !important}

.msg-meta{font-family:var(--font-mono);font-size:9px;color:var(--text-muted);margin-top:3px;display:flex;align-items:center;gap:6px}
.mine .msg-meta{flex-direction:row-reverse}
.edited-tag{font-style:italic}

/* Admin action buttons — always visible (no hover restriction) */
.msg-actions{display:flex;gap:4px;align-items:center;margin-top:2px}
.msg-act-btn{padding:3px 8px;border-radius:5px;border:none;font-family:var(--font-mono);font-size:9px;font-weight:700;cursor:pointer;transition:all 0.15s;letter-spacing:0.04em}
.edit-btn{background:rgba(59,130,246,0.1);color:var(--azure)}
.del-btn {background:rgba(239,68,68,0.08);color:var(--danger);border:1px solid rgba(239,68,68,0.2)}
.edit-btn:hover{background:rgba(59,130,246,0.18)}
.del-btn:hover {background:rgba(239,68,68,0.16)}

/* ── Input bar ── */
.msg-input-bar{padding:12px 18px;border-top:1px solid var(--border);background:var(--card-bg);display:flex;align-items:flex-end;gap:10px;flex-shrink:0}
.msg-textarea{flex:1;resize:none;border:1px solid var(--border);border-radius:10px;padding:9px 14px;font-family:var(--font-body);font-size:13.5px;color:var(--text-primary);background:var(--bg);outline:none;max-height:120px;overflow-y:auto;line-height:1.5;transition:border-color 0.15s,box-shadow 0.15s}
.msg-textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.send-btn{width:38px;height:38px;border-radius:9px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity 0.15s,transform 0.15s}
.send-btn:hover{opacity:0.88;transform:scale(1.05)}
.send-btn:disabled{opacity:0.4;cursor:not-allowed;transform:none}
.send-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}

/* Edit banner */
.edit-banner{display:none;background:rgba(245,166,35,0.08);border-top:1px solid rgba(245,166,35,0.2);padding:6px 18px;font-family:var(--font-mono);font-size:10px;color:var(--gold-dark);font-weight:700;align-items:center;justify-content:space-between;flex-shrink:0}
.edit-banner.active{display:flex}
.edit-cancel{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:13px}

/* Empty chat state */
.empty-chat{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;color:var(--text-muted);gap:10px}
.empty-chat svg{width:40px;height:40px;stroke:var(--border);fill:none;stroke-width:1.2}

/* ── Toast ── */
.toast-container{position:fixed;bottom:28px;right:28px;z-index:999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;padding:12px 18px;background:#1A1D2E;color:#fff;border-radius:10px;font-family:var(--font-body);font-size:13.5px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,0.2);animation:toast-in 0.3s cubic-bezier(0.34,1.56,0.64,1) forwards;pointer-events:all;border-left:3px solid var(--gold)}
@keyframes toast-in{from{opacity:0;transform:translateY(16px) scale(0.96)}to{opacity:1;transform:none}}
.toast.fade-out{animation:toast-out 0.25s ease forwards}
@keyframes toast-out{to{opacity:0;transform:translateY(8px) scale(0.97)}}
.toast.err{border-left-color:var(--danger)}
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
        <a href="admin_bonus.php" class="nav-item">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/><path d="M8 14h.01M12 14h.01M16 14h.01"/><path d="M9 9h6M9 12h3"/></svg>
            <span class="nav-label">Bonus Supply</span><span class="tooltip">Bonus Supply</span>
        </a>
        <a href="admin_chat.php" class="nav-item active">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h8M8 14h5"/></svg>
            <span class="nav-label">Chat</span><span class="tooltip">Chat</span>
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

<!-- ═══ MAIN WRAPPER ════════════════════════════════════════════ -->
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

    <!-- ── Chat area ── -->
    <div class="chat-area" id="chatArea">

        <!-- Left: Author list -->
        <div class="author-panel" id="authorPanel">
            <div class="author-panel-head">
                <div class="author-panel-title">Conversations</div>
                <div class="author-search">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" placeholder="Search authors..." id="authorSearch" oninput="filterAuthors()">
                </div>
            </div>
            <div class="author-list" id="authorList">
                <!-- Group chat pinned -->
                <div class="convo-item group-pin active" id="convo-group" onclick="openChat('group', 0, 'Quill &amp; Compass')">
                    <div class="convo-avatar av-group">
                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    </div>
                    <div class="convo-info">
                        <div class="convo-name">Quill &amp; Compass</div>
                        <div class="convo-preview" id="group-preview">Author community group</div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px">
                        <div class="convo-time" id="group-time"></div>
                        <div class="unread-badge" id="group-unread" style="display:none"></div>
                    </div>
                </div>
                <!-- Author list populated by JS -->
                <div id="authorItems"></div>
            </div>
        </div>

        <!-- Divider -->
        <div class="chat-divider" id="chatDivider"></div>

        <!-- Right: Chat panel -->
        <div class="chat-panel" id="chatPanel">
            <!-- Header -->
            <div class="chat-panel-head" id="chatPanelHead">
                <div class="chat-icon-wrap blue">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                </div>
                <div>
                    <div class="chat-panel-title" id="chatPanelTitle">Quill &amp; Compass</div>
                    <div class="chat-panel-sub" id="chatPanelSub">Author community group</div>
                </div>
            </div>

            <!-- Messages -->
            <div class="msg-list" id="adminMessages">
                <div class="empty-chat">
                    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span>Loading messages...</span>
                </div>
            </div>

            <!-- Edit banner -->
            <div class="edit-banner" id="adminEditBanner">
                <span>
                    <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;display:inline;margin-right:4px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Editing message
                </span>
                <button class="edit-cancel" onclick="cancelAdminEdit()">
                    <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <!-- Input -->
            <div class="msg-input-bar">
                <textarea class="msg-textarea" id="adminInput" rows="1"
                          placeholder="Send a message..."
                          onkeydown="handleAdminKey(event)"
                          oninput="adminAutoResize(this)"></textarea>
                <button class="send-btn" id="adminSendBtn" onclick="adminSend()">
                    <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
        </div>

    </div><!-- /chat-area -->
</div><!-- /main-wrapper -->

<div class="toast-container" id="toastContainer"></div>

<script>
// ── Constants ─────────────────────────────────────────────────────
const ADMIN_ID = <?= $admin_id ?>;
const AJAX_URL = '../chat_ajax.php';
const POLL_MS  = 3000;

let currentMode   = 'group';   // 'group' | 'private'
let currentTarget = 0;         // author_id when private
let currentName   = 'Quill & Compass';
let lastMsgId     = 0;
let adminEditId   = null;
let allAuthors    = [];
let pollTimer     = null;

// ── Sidebar ───────────────────────────────────────────────────────
const sidebar = document.getElementById('sidebar');
const mainW   = document.getElementById('mainWrapper');
const burger  = document.getElementById('hamburger');
burger.addEventListener('click', () => {
    const c = sidebar.classList.toggle('collapsed');
    mainW.classList.toggle('expanded', c);
    burger.classList.toggle('active', c);
    localStorage.setItem('sv_sidebar', c ? '1' : '0');
});
if (localStorage.getItem('sv_sidebar') === '1') {
    sidebar.classList.add('collapsed'); mainW.classList.add('expanded'); burger.classList.add('active');
}
document.getElementById('globalSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && this.value.trim()) location.href = 'admin_users.php?q=' + encodeURIComponent(this.value.trim());
});

// ── Toast ─────────────────────────────────────────────────────────
function showToast(msg, type = 'default') {
    const tc = document.getElementById('toastContainer');
    const t  = document.createElement('div');
    t.className = 'toast' + (type === 'error' ? ' err' : '');
    t.style.borderLeftColor = type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--gold)';
    t.textContent = msg;
    tc.appendChild(t);
    setTimeout(() => { t.classList.add('fade-out'); setTimeout(() => t.remove(), 250); }, 3200);
}

// ── Helpers ───────────────────────────────────────────────────────
function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/\n/g,'<br>');
}
function fmtTime(ts) {
    const d = new Date(ts.replace(' ','T'));
    return d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
}
function fmtDate(ts) {
    if (!ts) return '';
    const d = new Date(ts.replace(' ','T'));
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return fmtTime(ts);
    return d.toLocaleDateString([], { day:'numeric', month:'short' });
}
function scrollBottom() {
    const el = document.getElementById('adminMessages');
    el.scrollTop = el.scrollHeight;
}
function adminAutoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}
function handleAdminKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); adminSend(); }
}

// ── Build bubble ──────────────────────────────────────────────────
function buildBubble(msg) {
    const isMine    = parseInt(msg.sender_id) === ADMIN_ID;
    const isDeleted = parseInt(msg.is_deleted) === 1;
    const isAdmin   = msg.user_type === 'admin';
    const name      = msg.first_name + (msg.last_name ? ' ' + msg.last_name : '');
    const adminBadge = isAdmin ? `<span class="admin-badge-chat">ADMIN</span>` : '';
    const editedTag  = (parseInt(msg.is_edited) === 1 && !isDeleted) ? `<span class="edited-tag">edited</span>` : '';
    const bubbleText = isDeleted
        ? '<svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2;display:inline;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Message deleted'
        : escHtml(msg.message_text);

    // Admin can always edit/delete any message
    const actBtns = !isDeleted ? `
        <span class="msg-actions">
            <button class="msg-act-btn edit-btn" onclick="startAdminEdit(${msg.message_id}, this)">
                <svg viewBox="0 0 24 24" style="width:9px;height:9px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;display:inline"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </button>
            <button class="msg-act-btn del-btn" onclick="adminDelete(${msg.message_id})">
                <svg viewBox="0 0 24 24" style="width:9px;height:9px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;display:inline"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                Delete
            </button>
        </span>` : '';

    return `
    <div class="msg-row ${isMine ? 'mine' : 'theirs'} ${isDeleted ? 'msg-deleted' : ''}"
         id="amsg-${msg.message_id}" data-created="${msg.created_at}">
        ${!isMine ? `<div class="msg-sender-label">${escHtml(name)} ${adminBadge}</div>` : ''}
        <div class="msg-bubble">${bubbleText}</div>
        <div class="msg-meta">
            <span>${fmtTime(msg.created_at)}</span>
            ${editedTag}
            ${actBtns}
        </div>
    </div>`;
}

// ── Render messages ───────────────────────────────────────────────
function renderMessages(messages, append = false) {
    const container = document.getElementById('adminMessages');
    const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 40;
    if (!append) container.innerHTML = '';
    if (!messages.length && !append) {
        container.innerHTML = '<div class="empty-chat"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>No messages yet</span></div>';
        return;
    }
    messages.forEach(msg => {
        const existing = document.getElementById(`amsg-${msg.message_id}`);
        if (existing) existing.outerHTML = buildBubble(msg);
        else container.insertAdjacentHTML('beforeend', buildBubble(msg));
        if (parseInt(msg.message_id) > lastMsgId) lastMsgId = parseInt(msg.message_id);
    });
    if (!append || wasAtBottom) scrollBottom();
}

// ── Open a chat (group or author private) ─────────────────────────
async function openChat(mode, authorId, name) {
    currentMode   = mode;
    currentTarget = authorId;
    currentName   = name;
    lastMsgId     = 0;
    adminEditId   = null;
    cancelAdminEdit();

    // Update active state
    document.querySelectorAll('.convo-item').forEach(el => el.classList.remove('active'));
    const activeEl = mode === 'group' ? document.getElementById('convo-group') : document.getElementById(`convo-author-${authorId}`);
    if (activeEl) activeEl.classList.add('active');

    // Update header
    document.getElementById('chatPanelTitle').textContent = name;
    document.getElementById('chatPanelSub').textContent   = mode === 'group' ? 'Author community group' : 'Private conversation';
    document.getElementById('adminInput').placeholder     = `Message ${name}...`;

    // Clear poll and reload
    if (pollTimer) clearInterval(pollTimer);
    document.getElementById('adminMessages').innerHTML = '<div class="empty-chat"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Loading...</span></div>';

    await loadMessages();
    // If opening group chat, immediately clear group unread badge
    if (mode === 'group') {
        const gb = document.getElementById('group-unread');
        if (gb) { gb.style.display = 'none'; gb.textContent = ''; }
    }
    pollTimer = setInterval(pollMessages, POLL_MS);
}

async function loadMessages() {
    const action = currentMode === 'group' ? 'load_group' : `load_private`;
    const extra  = currentMode === 'private' ? `&other_id=${currentTarget}` : '';
    try {
        const r = await fetch(`${AJAX_URL}?action=${action}${extra}&my_id=${ADMIN_ID}`);
        const d = await r.json();
        if (d.success) renderMessages(d.messages);
        else showToast(d.msg || 'Load failed', 'error');
    } catch { showToast('Network error', 'error'); }
    // Clear group unread
    if (currentMode === 'group') {
        const b = document.getElementById('group-unread');
        if (b) { b.style.display = 'none'; b.textContent = ''; }
    }
}

async function pollMessages() {
    const action = currentMode === 'group' ? 'poll_group' : 'poll_private';
    const extra  = currentMode === 'private' ? `&other_id=${currentTarget}` : '';
    try {
        const r = await fetch(`${AJAX_URL}?action=${action}&since_id=${lastMsgId}${extra}&my_id=${ADMIN_ID}`);
        const d = await r.json();
        if (d.success && d.messages.length) renderMessages(d.messages, true);
    } catch {}
}

// ── Send ──────────────────────────────────────────────────────────
async function adminSend() {
    const inputEl = document.getElementById('adminInput');
    const text    = inputEl.value.trim();
    if (!text) return;
    if (adminEditId) { await saveAdminEdit(adminEditId, text); return; }

    document.getElementById('adminSendBtn').disabled = true;
    const fd = new FormData();
    fd.append('action', currentMode === 'group' ? 'send_group' : 'send_private');
    fd.append('message_text', text);
    if (currentMode === 'private') fd.append('other_id', currentTarget);
    fd.append('my_id', ADMIN_ID);

    try {
        const r = await fetch(AJAX_URL, { method:'POST', body:fd });
        const d = await r.json();
        if (d.success) {
            inputEl.value = '';
            adminAutoResize(inputEl);
            renderMessages([d.message], true);
            refreshAuthorList();
        } else showToast(d.msg || 'Send failed', 'error');
    } catch { showToast('Network error', 'error'); }
    document.getElementById('adminSendBtn').disabled = false;
}

// ── Edit ──────────────────────────────────────────────────────────
function startAdminEdit(msgId, btn) {
    const msgEl  = document.getElementById(`amsg-${msgId}`);
    const bubble = msgEl?.querySelector('.msg-bubble');
    if (!bubble) return;
    const inputEl = document.getElementById('adminInput');
    inputEl.value = bubble.innerText.trim();
    adminAutoResize(inputEl);
    inputEl.focus();
    document.getElementById('adminEditBanner').classList.add('active');
    adminEditId = msgId;
}
function cancelAdminEdit() {
    const inputEl = document.getElementById('adminInput');
    if (inputEl) { inputEl.value = ''; adminAutoResize(inputEl); }
    const banner = document.getElementById('adminEditBanner');
    if (banner) banner.classList.remove('active');
    adminEditId = null;
}
async function saveAdminEdit(msgId, text) {
    const fd = new FormData();
    fd.append('action', currentMode === 'group' ? 'edit_group' : 'edit_private');
    fd.append('message_id', msgId);
    fd.append('message_text', text);
    fd.append('my_id', ADMIN_ID);
    try {
        const r = await fetch(AJAX_URL, { method:'POST', body:fd });
        const d = await r.json();
        if (d.success) { cancelAdminEdit(); await loadMessages(); showToast('Message updated', 'success'); }
        else showToast(d.msg || 'Edit failed', 'error');
    } catch { showToast('Network error', 'error'); }
}

// ── Delete ────────────────────────────────────────────────────────
async function adminDelete(msgId) {
    if (!confirm('Delete this message? It will show as deleted to all users.')) return;
    const fd = new FormData();
    fd.append('action', currentMode === 'group' ? 'delete_group' : 'delete_private');
    fd.append('message_id', msgId);
    fd.append('my_id', ADMIN_ID);
    try {
        const r = await fetch(AJAX_URL, { method:'POST', body:fd });
        const d = await r.json();
        if (d.success) { await loadMessages(); showToast('Message deleted', 'success'); }
        else showToast(d.msg || 'Delete failed', 'error');
    } catch { showToast('Network error', 'error'); }
}

// ── Author list ───────────────────────────────────────────────────
async function refreshAuthorList() {
    try {
        const r = await fetch(`${AJAX_URL}?action=list_authors&my_id=${ADMIN_ID}`);
        const d = await r.json();
        if (!d.success) return;
        allAuthors = d.authors;
        renderAuthorList(allAuthors);

        // Group unread badge — if admin currently has group chat open, count is 0
        let gu = parseInt(d.group_unread || 0);
        if (currentMode === 'group') gu = 0;
        const gb = document.getElementById('group-unread');
        if (gb) { gb.style.display = gu > 0 ? 'flex' : 'none'; gb.textContent = gu > 0 ? gu : ''; }
    } catch {}
}

function renderAuthorList(authors) {
    const container = document.getElementById('authorItems');
    container.innerHTML = '';
    if (!authors.length) {
        container.innerHTML = '<div style="padding:14px 16px;font-size:12px;color:var(--text-muted)">No authors yet</div>';
        return;
    }
    authors.forEach(a => {
        const initials = (a.first_name?.[0] || '') + (a.last_name?.[0] || '');
        const preview  = a.last_message ? (String(a.last_message).substring(0, 35) + (a.last_message.length > 35 ? '...' : '')) : 'No messages yet';
        const timeStr  = a.last_time ? fmtDate(a.last_time) : '';
        const unread   = parseInt(a.unread_count || 0);
        const isActive = currentMode === 'private' && currentTarget === parseInt(a.user_id);
        container.insertAdjacentHTML('beforeend', `
        <div class="convo-item ${isActive ? 'active' : ''}" id="convo-author-${a.user_id}"
             onclick="openChat('private', ${a.user_id}, '${escHtml(a.first_name + ' ' + (a.last_name||''))}')">
            <div class="convo-avatar av-author">${escHtml(initials || '?')}</div>
            <div class="convo-info">
                <div class="convo-name">${escHtml(a.first_name + ' ' + (a.last_name || ''))}</div>
                <div class="convo-preview">@${escHtml(a.user_name)} · ${escHtml(preview)}</div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px">
                <div class="convo-time">${timeStr}</div>
                ${unread > 0 ? `<div class="unread-badge">${unread}</div>` : ''}
            </div>
        </div>`);
    });
}

function filterAuthors() {
    const q = document.getElementById('authorSearch').value.toLowerCase().trim();
    if (!q) { renderAuthorList(allAuthors); return; }
    const filtered = allAuthors.filter(a =>
        (a.first_name + ' ' + (a.last_name||'') + ' ' + a.user_name).toLowerCase().includes(q)
    );
    renderAuthorList(filtered);
}

// ── Draggable divider ─────────────────────────────────────────────
(function() {
    const divider = document.getElementById('chatDivider');
    const shell   = document.getElementById('chatArea');
    const panel   = document.getElementById('authorPanel');
    let   dragging = false;
    divider.addEventListener('mousedown', () => {
        dragging = true; divider.classList.add('dragging');
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
    });
    document.addEventListener('mousemove', e => {
        if (!dragging) return;
        const rect   = shell.getBoundingClientRect();
        let   newPct = ((e.clientX - rect.left) / rect.width) * 100;
        newPct = Math.max(20, Math.min(60, newPct));
        panel.style.width = newPct + '%';
    });
    document.addEventListener('mouseup', () => {
        if (!dragging) return;
        dragging = false; divider.classList.remove('dragging');
        document.body.style.cursor = ''; document.body.style.userSelect = '';
    });
})();

// ── Boot ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    await refreshAuthorList();
    await openChat('group', 0, 'Quill & Compass');
    // Refresh author list every 6 seconds (unread counts)
    setInterval(refreshAuthorList, 6000);
});
</script>
</body>
</html>