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

// ── Game stats ──────────────────────────────────────────────────────────

// Who Said It — count saved character_quiz questions
$wsi_count = 0;
try {
    $wsi_count = (int)$pdo->query("SELECT COUNT(*) FROM character_quiz")->fetchColumn();
} catch (Exception $e) {}

// Flash Words — count arena_questions total & per type
$fw_total    = 0;
$fw_story    = 0;
$fw_external = 0;
try {
    $fw_total    = (int)$pdo->query("SELECT COUNT(*) FROM arena_questions")->fetchColumn();
    $fw_story    = (int)$pdo->query("SELECT COUNT(*) FROM arena_questions WHERE question_type='story_chunk'")->fetchColumn();
    $fw_external = (int)$pdo->query("SELECT COUNT(*) FROM arena_questions WHERE question_type='external'")->fetchColumn();
} catch (Exception $e) {}

// Story Scramble — auto-generated from story_parts, no admin action needed
// Just count how many parts are available for scramble play
$scramble_parts = 0;
try {
    $scramble_parts = (int)$pdo->query("SELECT COUNT(*) FROM story_parts WHERE status='approved'")->fetchColumn();
} catch (Exception $e) {}

// Game scores counts per game
$scores_scramble  = 0;
$scores_flashwords = 0;
try {
    $scores_scramble   = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM game_scores")->fetchColumn();
    $scores_flashwords = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM arena_progress")->fetchColumn();
} catch (Exception $e) {}

// Total arena questions played
$arena_attempts = 0;
try {
    $arena_attempts = (int)$pdo->query("SELECT SUM(total_attempts) FROM arena_progress")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Game Arena — StoryVerse Admin</title>
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
.page-header{margin-bottom:28px}
.page-header h1{font-family:var(--font-display);font-size:22px;color:var(--text-primary);margin-bottom:4px}
.page-header p{font-size:14px;color:var(--text-secondary)}

/* ── Summary stat strip ── */
.stat-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:32px}
.stat-chip{background:var(--card-bg);border:1px solid var(--card-border);border-radius:10px;box-shadow:var(--card-shadow);padding:16px 18px;display:flex;flex-direction:column;gap:4px}
.stat-chip-label{font-family:var(--font-mono);font-size:9px;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted)}
.stat-chip-value{font-family:var(--font-body);font-size:26px;font-weight:700;color:var(--text-primary);line-height:1}
.stat-chip-sub{font-size:11px;color:var(--text-muted);font-weight:500}

/* ── Section label ── */
.section-label{font-family:var(--font-mono);font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:16px;display:flex;align-items:center;gap:10px}
.section-label::after{content:'';flex:1;height:1px;background:var(--border)}

/* ── Game cards grid ── */
.games-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;margin-bottom:40px}

.game-card{background:var(--card-bg);border-radius:16px;border:1px solid var(--card-border);box-shadow:var(--card-shadow);overflow:hidden;display:flex;flex-direction:column;transition:transform 0.18s,box-shadow 0.18s;position:relative}
.game-card:hover{transform:translateY(-4px);box-shadow:0 12px 36px rgba(15,20,50,0.12)}

/* Top gradient banner per game */
.game-banner{height:110px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;flex-shrink:0}
.game-banner::before{content:'';position:absolute;inset:0;opacity:0.08}

.game-card.flash-words .game-banner{background:linear-gradient(135deg,#0B1120 0%,#162040 100%)}
.game-card.flash-words .game-banner::before{background:radial-gradient(circle at 30% 50%,#3B82F6 0%,transparent 60%)}
.game-card.flash-words .game-icon-wrap{background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);color:#60A5FA}

.game-card.who-said-it .game-banner{background:linear-gradient(135deg,#0B1120 0%,#1a1040 100%)}
.game-card.who-said-it .game-banner::before{background:radial-gradient(circle at 30% 50%,#8B5CF6 0%,transparent 60%)}
.game-card.who-said-it .game-icon-wrap{background:rgba(139,92,246,0.15);border:1px solid rgba(139,92,246,0.3);color:#A78BFA}

.game-card.scramble .game-banner{background:linear-gradient(135deg,#0B1120 0%,#1a2a10 100%)}
.game-card.scramble .game-banner::before{background:radial-gradient(circle at 30% 50%,#10B981 0%,transparent 60%)}
.game-card.scramble .game-icon-wrap{background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#34D399}

.game-icon-wrap{width:60px;height:60px;border-radius:14px;display:flex;align-items:center;justify-content:center;position:relative;z-index:1}
.game-icon-wrap svg{width:28px;height:28px;stroke:currentColor;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}

/* Admin badge on banner */
.admin-required-badge{position:absolute;top:10px;right:12px;font-family:var(--font-mono);font-size:9px;letter-spacing:0.08em;padding:3px 8px;border-radius:4px;background:rgba(245,166,35,0.15);border:1px solid rgba(245,166,35,0.35);color:var(--gold);z-index:2}
.auto-badge{background:rgba(16,185,129,0.12);border-color:rgba(16,185,129,0.3);color:#34D399}

.game-body{padding:20px 22px;flex:1;display:flex;flex-direction:column}
.game-name{font-family:var(--font-display);font-size:14px;color:var(--text-primary);margin-bottom:6px;line-height:1.3}
.game-desc{font-size:13px;color:var(--text-secondary);line-height:1.6;flex:1;margin-bottom:16px}

/* Game mini stats row */
.game-stats{display:flex;align-items:center;gap:16px;margin-bottom:18px;flex-wrap:wrap}
.gstat{display:flex;align-items:center;gap:5px;font-family:var(--font-mono);font-size:10px;color:var(--text-muted)}
.gstat-val{font-weight:700;color:var(--text-primary)}
.gstat svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round}

/* Manage button */
.game-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:11px 18px;border-radius:9px;border:none;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;transition:all 0.18s;letter-spacing:0.02em}
.game-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}

.game-card.flash-words .game-btn{background:linear-gradient(135deg,#3B82F6,#2563EB);color:#fff}
.game-card.flash-words .game-btn:hover{opacity:0.9;transform:translateY(-1px)}

.game-card.who-said-it .game-btn{background:linear-gradient(135deg,#8B5CF6,#7C3AED);color:#fff}
.game-card.who-said-it .game-btn:hover{opacity:0.9;transform:translateY(-1px)}

.game-card.scramble .game-btn-disabled{background:var(--divider);color:var(--text-muted);cursor:default;border:1px solid var(--border)}
.game-card.scramble .game-btn-disabled:hover{transform:none;opacity:1}

/* Divider line on card footer */
.game-footer-note{font-family:var(--font-mono);font-size:9px;color:var(--text-muted);text-align:center;padding:10px 22px;border-top:1px solid var(--divider);letter-spacing:0.06em}

/* ── Leaderboard reset section ── */
.reset-section{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);padding:22px 24px}
.reset-section-title{font-family:var(--font-body);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-primary);margin-bottom:4px}
.reset-section-sub{font-size:13px;color:var(--text-muted);margin-bottom:18px}
.reset-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--divider)}
.reset-row:last-child{border-bottom:none}
.reset-game-name{font-size:14px;font-weight:600;color:var(--text-primary)}
.reset-game-note{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:2px}
.reset-btn{padding:7px 16px;border-radius:7px;border:1px solid rgba(239,68,68,0.3);background:rgba(239,68,68,0.06);color:var(--danger);font-family:var(--font-body);font-size:12px;font-weight:700;cursor:pointer;transition:all 0.15s}
.reset-btn:hover{background:rgba(239,68,68,0.12);border-color:rgba(239,68,68,0.5)}

/* ── Toast ── */
.toast-container{position:fixed;bottom:28px;right:28px;z-index:999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;padding:12px 18px;background:#1A1D2E;color:#fff;border-radius:10px;font-family:var(--font-body);font-size:13.5px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,0.2);animation:toast-in 0.3s cubic-bezier(0.34,1.56,0.64,1) forwards;pointer-events:all;border-left:3px solid var(--gold)}
@keyframes toast-in{from{opacity:0;transform:translateY(16px) scale(0.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.toast.fade-out{animation:toast-out 0.25s ease forwards}
@keyframes toast-out{to{opacity:0;transform:translateY(8px) scale(0.97)}}

.fade-up{opacity:0;transform:translateY(14px);animation:fade-up-in 0.38s ease forwards}
@keyframes fade-up-in{to{opacity:1;transform:translateY(0)}}
.game-card:nth-child(1){animation-delay:0.04s}
.game-card:nth-child(2){animation-delay:0.08s}
.game-card:nth-child(3){animation-delay:0.12s}

@media(max-width:1100px){.games-grid{grid-template-columns:repeat(2,1fr)}.stat-strip{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.games-grid{grid-template-columns:1fr}.stat-strip{grid-template-columns:1fr 1fr}.page-content{padding:16px 14px 40px}.topbar-date{display:none}}
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
        <a href="admin_games.php" class="nav-item active">
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

    <main class="page-content">

        <div class="page-header fade-up">
            <h1>Game Arena</h1>
            <p>Manage content and settings for all three StoryVerse games.</p>
        </div>

        <!-- Summary stat strip -->
        <div class="stat-strip fade-up" style="animation-delay:0.05s">
            <div class="stat-chip">
                <span class="stat-chip-label">Flash Words Questions</span>
                <span class="stat-chip-value"><?= number_format($fw_total) ?></span>
                <span class="stat-chip-sub"><?= $fw_story ?> story &middot; <?= $fw_external ?> external</span>
            </div>
            <div class="stat-chip">
                <span class="stat-chip-label">Who Said It Questions</span>
                <span class="stat-chip-value"><?= number_format($wsi_count) ?></span>
                <span class="stat-chip-sub">Across all stories</span>
            </div>
            <div class="stat-chip">
                <span class="stat-chip-label">Scramble Parts Available</span>
                <span class="stat-chip-value"><?= number_format($scramble_parts) ?></span>
                <span class="stat-chip-sub">Approved story parts</span>
            </div>
            <div class="stat-chip">
                <span class="stat-chip-label">Flash Words Attempts</span>
                <span class="stat-chip-value"><?= number_format($arena_attempts) ?></span>
                <span class="stat-chip-sub"><?= $scores_flashwords ?> unique players</span>
            </div>
        </div>

        <!-- Game cards -->
        <div class="section-label fade-up" style="animation-delay:0.08s">Select a game to manage</div>

        <div class="games-grid">

            <!-- Flash Words -->
            <div class="game-card flash-words fade-up">
                <div class="game-banner">
                    <span class="admin-required-badge">Admin Required</span>
                    <div class="game-icon-wrap">
                        <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                </div>
                <div class="game-body">
                    <div class="game-name">Flash Words</div>
                    <div class="game-desc">
                        Speed-reading comprehension game. Readers see a passage at set WPM, then answer a multiple-choice question.
                        Admin controls question banks — both story-chunk derived and custom external passages.
                    </div>
                    <div class="game-stats">
                        <span class="gstat">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>WPM tiers:</span><span class="gstat-val">60 / 100 / 140 / 180 / 220</span>
                        </span>
                        <span class="gstat">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span class="gstat-val"><?= $fw_total ?></span><span>questions total</span>
                        </span>
                    </div>
                    <a href="arena_questions.php" class="game-btn">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Manage Questions
                    </a>
                </div>
                <div class="game-footer-note">arena_questions.php</div>
            </div>

            <!-- Who Said It -->
            <div class="game-card who-said-it fade-up">
                <div class="game-banner">
                    <span class="admin-required-badge">Admin / Author</span>
                    <div class="game-icon-wrap">
                        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                </div>
                <div class="game-body">
                    <div class="game-name">Who Said It?</div>
                    <div class="game-desc">
                        Character attribution quiz. Players are shown a dialogue line and must identify which character said it.
                        Admin and authors can auto-extract dialogues from story parts or add them manually.
                    </div>
                    <div class="game-stats">
                        <span class="gstat">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <span>Accessible to</span><span class="gstat-val">Admin + Authors</span>
                        </span>
                        <span class="gstat">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                            <span class="gstat-val"><?= $wsi_count ?></span><span>questions saved</span>
                        </span>
                    </div>
                    <a href="admin_who_said_it.php" class="game-btn">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Manage Questions
                    </a>
                </div>
                <div class="game-footer-note">admin_who_said_it.php</div>
            </div>

            <!-- Story Scramble -->
            <div class="game-card scramble fade-up">
                <div class="game-banner">
                    <span class="admin-required-badge auto-badge">Auto-Generated</span>
                    <div class="game-icon-wrap">
                        <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                    </div>
                </div>
                <div class="game-body">
                    <div class="game-name">Story Scramble</div>
                    <div class="game-desc">
                        Drag-and-drop sentence reordering game. Questions are generated automatically from approved story parts —
                        no manual admin input required. Approve more story parts to expand the question pool.
                    </div>
                    <div class="game-stats">
                        <span class="gstat">
                            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            <span class="gstat-val"><?= $scramble_parts ?></span><span>approved parts available</span>
                        </span>
                        <span class="gstat">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <span class="gstat-val"><?= $scores_scramble ?></span><span>players scored</span>
                        </span>
                    </div>
                    <a href="admin_stories.php?filter=approved" class="game-btn" style="background:linear-gradient(135deg,#10B981,#059669);color:#fff">
                        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        Manage Story Parts
                    </a>
                </div>
                <div class="game-footer-note">Questions auto-built from approved story parts</div>
            </div>

        </div><!-- /games-grid -->

        <!-- Leaderboard Reset section -->
        <div class="section-label fade-up" style="animation-delay:0.16s">Leaderboard Management</div>

        <div class="reset-section fade-up" style="animation-delay:0.20s">
            <div class="reset-section-title">Reset Game Leaderboards</div>
            <div class="reset-section-sub">Permanently removes all scores for the selected game. This cannot be undone.</div>

            <div class="reset-row">
                <div>
                    <div class="reset-game-name">Flash Words</div>
                    <div class="reset-game-note">Clears arena_progress scores</div>
                </div>
                <button class="reset-btn" onclick="resetLeaderboard('flashwords','Flash Words')">Reset Scores</button>
            </div>
            <div class="reset-row">
                <div>
                    <div class="reset-game-name">Who Said It?</div>
                    <div class="reset-game-note">No persistent score table — quiz is session-based</div>
                </div>
                <button class="reset-btn" style="opacity:0.4;cursor:not-allowed" disabled>Not Applicable</button>
            </div>
            <div class="reset-row">
                <div>
                    <div class="reset-game-name">Story Scramble</div>
                    <div class="reset-game-note">Clears game_scores table</div>
                </div>
                <button class="reset-btn" onclick="resetLeaderboard('scramble','Story Scramble')">Reset Scores</button>
            </div>
        </div>

    </main>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// ── Sidebar ────────────────────────────────────────────────────
const sidebar=document.getElementById('sidebar'),mainWrapper=document.getElementById('mainWrapper'),hamburger=document.getElementById('hamburger');
hamburger.addEventListener('click',()=>{const c=sidebar.classList.toggle('collapsed');mainWrapper.classList.toggle('expanded',c);hamburger.classList.toggle('active',c);localStorage.setItem('sv_sidebar',c?'1':'0')});
if(localStorage.getItem('sv_sidebar')==='1'){sidebar.classList.add('collapsed');mainWrapper.classList.add('expanded');hamburger.classList.add('active')}

document.getElementById('globalSearch').addEventListener('keydown',function(e){if(e.key==='Enter'&&this.value.trim())location.href='admin_users.php?q='+encodeURIComponent(this.value.trim())});

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg,type='default'){
    const tc=document.getElementById('toastContainer'),t=document.createElement('div');
    t.className='toast';
    t.style.borderLeftColor=type==='success'?'var(--success)':type==='error'?'var(--danger)':'var(--gold)';
    t.textContent=msg;tc.appendChild(t);
    setTimeout(()=>{t.classList.add('fade-out');setTimeout(()=>t.remove(),280)},3500);
}

// ── Leaderboard reset ──────────────────────────────────────────
function resetLeaderboard(game, label){
    if(!confirm(`Reset ALL scores for "${label}"? This cannot be undone.`)) return;
    const fd=new FormData();
    fd.append('action','reset_leaderboard');
    fd.append('game', game);
    fetch('admin_ajax.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(d=>{
            if(d.success) showToast(label+' leaderboard cleared','success');
            else showToast(d.msg||'Reset failed','error');
        })
        .catch(()=>showToast('Request failed','error'));
}
</script>
</body>
</html>
