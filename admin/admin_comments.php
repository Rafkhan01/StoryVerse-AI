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

// ══════════════════════════════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['form_action'] ?? '';

    // ── Edit comment ─────────────────────────────────────────
    if ($act === 'edit_comment') {
        $id        = (int)($_POST['comment_id'] ?? 0);
        $text      = trim($_POST['comment_text'] ?? '');
        $sentiment = in_array($_POST['sentiment']??'', ['positive','negative','neutral'])
                     ? $_POST['sentiment'] : 'neutral';
        $flagged   = isset($_POST['manually_flagged']) ? 1 : 0;

        if (!$id || !$text) {
            $flash = ['type'=>'error','msg'=>'Comment ID and text are required.'];
        } else {
            $pdo->prepare("UPDATE comments SET comment_text=?, sentiment=?, manually_flagged=? WHERE comment_id=?")
                ->execute([$text, $sentiment, $flagged, $id]);
            log_admin($pdo, "Comment #$id edited by admin", 'update');
            $flash = ['type'=>'success','msg'=>"Comment #$id updated."];
        }
    }

    // ── Delete comment ────────────────────────────────────────
    if ($act === 'delete_comment') {
        $id = (int)($_POST['comment_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM comments WHERE comment_id=?")->execute([$id]);
            log_admin($pdo, "Comment #$id deleted by admin", 'delete');
            $flash = ['type'=>'success','msg'=>"Comment #$id deleted."];
        }
    }

    // ── Edit prediction ───────────────────────────────────────
    if ($act === 'edit_prediction') {
        $id       = (int)($_POST['prediction_id'] ?? 0);
        $text     = trim($_POST['prediction_text'] ?? '');
        $sbert    = $_POST['sbert_score']    !== '' ? (float)$_POST['sbert_score']    : null;
        $ml       = $_POST['ml_score']       !== '' ? (float)$_POST['ml_score']       : null;
        $manual   = $_POST['manual_accuracy']!== '' ? (float)$_POST['manual_accuracy']: null;

        if (!$id || !$text) {
            $flash = ['type'=>'error','msg'=>'Prediction ID and text are required.'];
        } else {
            $pdo->prepare("UPDATE predictions SET prediction_text=?, sbert_score=?, ml_score=?, manual_accuracy=? WHERE prediction_id=?")
                ->execute([$text, $sbert, $ml, $manual, $id]);
            log_admin($pdo, "Prediction #$id edited by admin", 'update');
            $flash = ['type'=>'success','msg'=>"Prediction #$id updated."];
        }
    }

    // ── Delete prediction ─────────────────────────────────────
    if ($act === 'delete_prediction') {
        $id = (int)($_POST['prediction_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM predictions WHERE prediction_id=?")->execute([$id]);
            log_admin($pdo, "Prediction #$id deleted by admin", 'delete');
            $flash = ['type'=>'success','msg'=>"Prediction #$id deleted."];
        }
    }

    // After any POST, redirect to keep URL clean (PRG pattern)
    if ($flash['msg']) {
        // Pass flash via session to survive redirect
        $_SESSION['admin_flash'] = $flash;
        $qs = $_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : '';
        header("Location: admin_comments.php$qs");
        exit;
    }
}

// Pick up flash from session after redirect
if (!empty($_SESSION['admin_flash'])) {
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}

// ══════════════════════════════════════════════════════════════
// COMMENTS SECTION — fetch & filter
// ══════════════════════════════════════════════════════════════
$c_story    = (int)($_GET['c_story']    ?? 0);
$c_sent     = $_GET['c_sent']     ?? 'all';
$c_flagged  = $_GET['c_flagged']  ?? 'all';
$c_search   = trim($_GET['c_q']   ?? '');
$c_page     = max(1,(int)($_GET['c_page'] ?? 1));
$c_per      = 15;

$cw = ['1=1']; $cp = [];
if ($c_story) { $cw[]='c.story_id=?'; $cp[]=$c_story; }
if (in_array($c_sent,['positive','negative','neutral'])) { $cw[]='c.sentiment=?'; $cp[]=$c_sent; }
if ($c_flagged === 'flagged') { $cw[]='c.manually_flagged=1'; }
if ($c_flagged === 'clean')   { $cw[]='c.manually_flagged=0'; }
if ($c_search !== '') { $cw[]='c.comment_text LIKE ?'; $cp[]="%$c_search%"; }

$cwhere = implode(' AND ', $cw);

$c_total_stmt = $pdo->prepare("SELECT COUNT(*) FROM comments c WHERE $cwhere");
$c_total_stmt->execute($cp);
$c_total = (int)$c_total_stmt->fetchColumn();
$c_pages = max(1,ceil($c_total/$c_per));
$c_page  = min($c_page,$c_pages);
$c_off   = ($c_page-1)*$c_per;

$c_params = array_merge($cp,[$c_per,$c_off]);
$c_stmt = $pdo->prepare("
    SELECT c.*,
           u.user_name, u.user_type,
           s.title AS story_title,
           sp.part_number
    FROM comments c
    LEFT JOIN users u ON u.user_id=c.user_id
    LEFT JOIN stories s ON s.story_id=c.story_id
    LEFT JOIN story_parts sp ON sp.story_id=c.story_id AND sp.part_number=1
    WHERE $cwhere
    ORDER BY c.manually_flagged DESC, c.created_at DESC
    LIMIT ? OFFSET ?
");
$c_stmt->execute($c_params);
$comments = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

// Comment summary stats
$c_stats = [
    'total'     => (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'flagged'   => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE manually_flagged=1")->fetchColumn(),
    'positive'  => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE sentiment='positive'")->fetchColumn(),
    'negative'  => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE sentiment='negative'")->fetchColumn(),
    'neutral'   => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE sentiment='neutral'")->fetchColumn(),
];

// ══════════════════════════════════════════════════════════════
// PREDICTIONS SECTION — fetch & filter
// ══════════════════════════════════════════════════════════════
$p_story  = (int)($_GET['p_story']  ?? 0);
$p_search = trim($_GET['p_q']       ?? '');
$p_sort   = in_array($_GET['p_sort']??'',['newest','score_high','score_low','manual_high'])
            ? $_GET['p_sort'] : 'newest';
$p_page   = max(1,(int)($_GET['p_page'] ?? 1));
$p_per    = 15;

$pw = ['1=1']; $pp = [];
if ($p_story) { $pw[]='p.story_id=?'; $pp[]=$p_story; }
if ($p_search !== '') { $pw[]='p.prediction_text LIKE ?'; $pp[]="%$p_search%"; }

$pwhere = implode(' AND ', $pw);
$p_order = match($p_sort) {
    'score_high'   => 'p.sbert_score DESC',
    'score_low'    => 'p.sbert_score ASC',
    'manual_high'  => 'p.manual_accuracy DESC',
    default        => 'p.prediction_id DESC',
};

$p_total_stmt = $pdo->prepare("SELECT COUNT(*) FROM predictions p WHERE $pwhere");
$p_total_stmt->execute($pp);
$p_total = (int)$p_total_stmt->fetchColumn();
$p_pages = max(1,ceil($p_total/$p_per));
$p_page  = min($p_page,$p_pages);
$p_off   = ($p_page-1)*$p_per;

$p_params = array_merge($pp,[$p_per,$p_off]);
$p_stmt = $pdo->prepare("
    SELECT p.*,
           u.user_name,
           s.title AS story_title,
           b.bonus_amount
    FROM predictions p
    LEFT JOIN users u ON u.user_id=p.user_id
    LEFT JOIN stories s ON s.story_id=p.story_id
    LEFT JOIN bonus b ON b.prediction_id=p.prediction_id
    WHERE $pwhere
    ORDER BY $p_order
    LIMIT ? OFFSET ?
");
$p_stmt->execute($p_params);
$predictions = $p_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prediction stats
$p_stats = [
    'total'      => (int)$pdo->query("SELECT COUNT(*) FROM predictions")->fetchColumn(),
    'with_bonus' => (int)$pdo->query("SELECT COUNT(*) FROM bonus")->fetchColumn(),
    // 'avg_sbert'  => round((float)$pdo->query("SELECT AVG(sbert_score) FROM predictions WHERE sbert_score IS NOT NULL")->fetchColumn(), 2),
    'avg_manual' => round((float)$pdo->query("SELECT AVG(manual_accuracy) FROM predictions WHERE manual_accuracy IS NOT NULL")->fetchColumn(), 2),
];

// Stories for filter dropdowns
$stories_list = $pdo->query("SELECT story_id, title FROM stories WHERE status='approved' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

// Active section (anchor)
$section = $_GET['section'] ?? 'comments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Comments & Predictions — StoryVerse Admin</title>
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

/* ── MAIN LAYOUT ── */
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
.page-content{padding:28px 28px 60px;flex:1}

.page-header{margin-bottom:22px}
.page-header h1{font-family:var(--font-display);font-size:22px;color:var(--text-primary);margin-bottom:4px}
.page-header p{font-size:14px;color:var(--text-secondary)}

/* Flash */
.flash{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;margin-bottom:18px;font-size:14px;font-weight:600;border:1px solid}
.flash.success{background:#ECFDF5;border-color:rgba(16,185,129,.3);color:#065F46}
.flash.error{background:#FEF2F2;border-color:rgba(239,68,68,.3);color:#991B1B}
.flash svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}

/* Section anchor tabs */
.section-tabs{display:flex;gap:6px;margin-bottom:24px;border-bottom:2px solid var(--border);padding-bottom:0}
.section-tab{padding:10px 20px;font-family:var(--font-body);font-size:14px;font-weight:700;color:var(--text-muted);cursor:pointer;border:none;background:none;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .18s,border-color .18s;display:flex;align-items:center;gap:8px}
.section-tab:hover{color:var(--text-primary)}
.section-tab.active{color:var(--text-primary);border-bottom-color:var(--gold)}
.section-tab svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round}
.section-tab .tab-count{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px;background:var(--divider);color:var(--text-muted)}
.section-tab.active .tab-count{background:rgba(245,166,35,.15);color:var(--gold-dark)}

/* Stat strip */
.stat-strip{display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap}
.stat-chip{background:var(--card-bg);border:1px solid var(--card-border);border-radius:10px;box-shadow:var(--card-shadow);padding:12px 16px;flex:1;min-width:90px}
.stat-chip-label{font-family:var(--font-mono);font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px}
.stat-chip-value{font-family:var(--font-body);font-size:22px;font-weight:700;color:var(--text-primary);line-height:1}
.stat-chip-value.flagged{color:var(--danger)}
.stat-chip-value.pos{color:var(--success)}
.stat-chip-value.neg{color:var(--danger)}
.stat-chip-value.neu{color:var(--azure)}

/* Filter row */
.filter-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.filter-select{padding:7px 28px 7px 10px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;font-family:var(--font-body);font-size:13px;color:var(--text-primary);outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%239CA3AF' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;transition:border-color .15s}
.filter-select:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.filter-search-wrap{display:flex;align-items:center;gap:7px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;padding:7px 12px;flex:1;max-width:300px;transition:border-color .18s}
.filter-search-wrap:focus-within{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.filter-search-wrap svg{width:13px;height:13px;stroke:var(--text-muted);fill:none;stroke-width:2;flex-shrink:0}
.filter-search-wrap input{border:none;background:none;outline:none;font-family:var(--font-body);font-size:13px;color:var(--text-primary);width:100%}
.filter-search-wrap input::placeholder{color:var(--text-muted)}
.filter-btn{padding:7px 16px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border:none;border-radius:8px;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:opacity .15s}
.filter-btn:hover{opacity:.9}
.filter-clear{font-family:var(--font-mono);font-size:11px;color:var(--text-muted);text-decoration:none;padding:7px 8px}
.filter-clear:hover{color:var(--text-secondary)}
.results-count{font-family:var(--font-mono);font-size:11px;color:var(--text-muted);margin-left:auto;white-space:nowrap}

/* Card container */
.card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);overflow:hidden}

/* ══════════════════════
   COMMENT ROWS
══════════════════════ */
.comment-row{padding:16px 20px;border-bottom:1px solid var(--divider);transition:background .1s}
.comment-row:last-child{border-bottom:none}
.comment-row:hover{background:#FAFBFF}
.comment-row.flagged-row{border-left:3px solid var(--danger);background:#FFF8F8}
.comment-row.flagged-row:hover{background:#FFF0F0}

.comment-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:8px}
.comment-avatar{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-family:var(--font-body);font-size:13px;font-weight:700;flex-shrink:0;background:#EFF6FF;color:var(--azure)}
.comment-meta{flex:1;min-width:0}
.comment-user{font-size:13px;font-weight:700;color:var(--text-primary)}
.comment-story{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.comment-date{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);white-space:nowrap;flex-shrink:0}

.comment-body{font-size:14px;color:var(--text-primary);line-height:1.6;margin-bottom:10px;padding:10px 12px;background:var(--bg);border-radius:8px;border:1px solid var(--border)}

.comment-footer{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.sentiment-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:5px;font-family:var(--font-body);font-size:11px;font-weight:700;border:1px solid}
.sentiment-badge.positive{background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.sentiment-badge.negative{background:#FEF2F2;color:#991B1B;border-color:#FCA5A5}
.sentiment-badge.neutral  {background:#EFF6FF;color:#1D4ED8;border-color:#BFDBFE}
.sentiment-badge svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round}

.flagged-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:5px;font-family:var(--font-mono);font-size:10px;font-weight:700;background:#FEF3C7;color:#92400E;border:1px solid #FCD34D}

.comment-actions{margin-left:auto;display:flex;align-items:center;gap:6px}
.act-btn{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border);background:var(--card-bg);cursor:pointer;transition:all .15s;flex-shrink:0}
.act-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.act-btn.edit-btn{color:var(--azure)}
.act-btn.edit-btn:hover{background:#EFF6FF;border-color:#BFDBFE}
.act-btn.flag-btn{color:var(--warning)}
.act-btn.flag-btn:hover{background:#FFFBEB;border-color:#FCD34D}
.act-btn.unflag-btn{color:var(--success)}
.act-btn.unflag-btn:hover{background:#ECFDF5;border-color:#A7F3D0}
.act-btn.del-btn{color:var(--danger)}
.act-btn.del-btn:hover{background:#FEF2F2;border-color:#FCA5A5}

/* Inline edit form for comment */
.comment-edit-form{display:none;margin-top:12px;background:var(--bg);border:1px solid var(--gold-glow);border-radius:10px;padding:16px;animation:slideDown .2s ease}
.comment-edit-form.open{display:block}
@keyframes slideDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

.edit-field-label{font-family:var(--font-body);font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-secondary);display:block;margin-bottom:5px}
.edit-textarea{width:100%;padding:9px 12px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);resize:vertical;min-height:70px;line-height:1.5;transition:border-color .18s}
.edit-textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.edit-select{padding:7px 28px 7px 10px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;font-family:var(--font-body);font-size:13px;color:var(--text-primary);outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%239CA3AF' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;transition:border-color .15s}
.edit-select:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}

.edit-row{display:flex;align-items:center;gap:12px;margin-top:12px;flex-wrap:wrap}
.edit-checkbox-label{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:var(--text-secondary);cursor:pointer}
.edit-checkbox-label input[type=checkbox]{width:15px;height:15px;cursor:pointer;accent-color:var(--danger)}

.edit-actions{display:flex;gap:8px;margin-top:14px}
.btn-save-sm{padding:8px 18px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border:none;border-radius:7px;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:opacity .15s}
.btn-save-sm:hover{opacity:.9}
.btn-cancel-sm{padding:8px 14px;background:var(--divider);border:1px solid var(--border);border-radius:7px;color:var(--text-secondary);font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:background .15s}
.btn-cancel-sm:hover{background:var(--border)}
.btn-danger-sm{padding:8px 14px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:7px;color:var(--danger);font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:background .15s}
.btn-danger-sm:hover{background:rgba(239,68,68,.15)}

/* Delete confirm inline */
.del-confirm{display:none;align-items:center;gap:10px;padding:8px 12px;background:#FEF2F2;border:1px solid #FCA5A5;border-radius:7px;font-size:13px;font-weight:600;color:var(--danger);margin-top:10px}
.del-confirm.open{display:flex}
.del-confirm button{padding:4px 12px;border-radius:5px;border:1px solid;font-family:var(--font-body);font-size:12px;font-weight:700;cursor:pointer}
.dc-yes{background:var(--danger);color:#fff;border-color:var(--danger)}
.dc-yes:hover{background:#DC2626}
.dc-no{background:var(--card-bg);color:var(--text-secondary);border-color:var(--border)}

/* ══════════════════════
   PREDICTION ROWS
══════════════════════ */
.prediction-row{padding:16px 20px;border-bottom:1px solid var(--divider);transition:background .1s}
.prediction-row:last-child{border-bottom:none}
.prediction-row:hover{background:#FAFBFF}

.pred-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:8px}
.pred-num{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);flex-shrink:0;padding-top:2px;min-width:28px}
.pred-meta{flex:1;min-width:0}
.pred-user{font-size:13px;font-weight:700;color:var(--text-primary)}
.pred-story{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:2px}

.pred-body{font-size:14px;color:var(--text-primary);line-height:1.6;padding:10px 12px;background:var(--bg);border-radius:8px;border:1px solid var(--border);margin-bottom:10px}

.pred-scores{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.score-pill{display:inline-flex;flex-direction:column;align-items:center;padding:5px 12px;border-radius:7px;border:1px solid;min-width:72px;text-align:center}
.score-pill-label{font-family:var(--font-mono);font-size:9px;letter-spacing:.08em;text-transform:uppercase;margin-bottom:2px}
.score-pill-value{font-family:var(--font-body);font-size:15px;font-weight:700;line-height:1}
.score-pill.sbert{background:#EFF6FF;border-color:#BFDBFE;color:#1D4ED8}
.score-pill.ml{background:#F5F3FF;border-color:#DDD6FE;color:#6D28D9}
.score-pill.manual{background:#ECFDF5;border-color:#A7F3D0;color:#065F46}
.score-pill.bonus{background:rgba(245,166,35,.1);border-color:rgba(245,166,35,.35);color:var(--gold-dark)}
.score-pill.none{background:var(--divider);border-color:var(--border);color:var(--text-muted)}

/* Inline edit form for prediction */
.pred-edit-form{display:none;margin-top:12px;background:var(--bg);border:1px solid var(--gold-glow);border-radius:10px;padding:16px;animation:slideDown .2s ease}
.pred-edit-form.open{display:block}

.score-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:12px}
.score-input-wrap .edit-field-label{margin-bottom:4px}
.score-input{width:100%;padding:7px 10px;background:var(--card-bg);border:1px solid var(--border);border-radius:7px;outline:none;font-family:var(--font-mono);font-size:13px;color:var(--text-primary);transition:border-color .18s}
.score-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.score-input-hint{font-family:var(--font-mono);font-size:9px;color:var(--text-muted);margin-top:3px}

/* Empty state */
.empty-state{text-align:center;padding:48px 24px;color:var(--text-muted)}
.empty-state svg{width:44px;height:44px;stroke:var(--border);fill:none;stroke-width:1.4;margin:0 auto 14px;display:block}
.empty-state p{font-size:14px}

/* Pagination */
.pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--divider);flex-wrap:wrap;gap:10px}
.page-info{font-family:var(--font-mono);font-size:11px;color:var(--text-muted)}
.page-btns{display:flex;align-items:center;gap:5px}
.page-btn{padding:5px 11px;border-radius:6px;border:1px solid var(--border);background:var(--card-bg);font-family:var(--font-mono);font-size:11px;color:var(--text-secondary);cursor:pointer;text-decoration:none;transition:all .15s}
.page-btn:hover{border-color:var(--gold);color:var(--text-primary)}
.page-btn.active{background:var(--text-primary);color:#fff;border-color:var(--text-primary)}
.page-btn.disabled{opacity:.35;cursor:not-allowed;pointer-events:none}

/* Section divider header */
.section-card-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px;border-bottom:1px solid var(--divider)}
.section-card-title{font-family:var(--font-body);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-primary)}
.section-card-count{font-family:var(--font-mono);font-size:11px;color:var(--text-muted)}

/* Toast */
.toast-container{position:fixed;bottom:28px;right:28px;z-index:999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;padding:12px 18px;background:#1A1D2E;color:#fff;border-radius:10px;font-family:var(--font-body);font-size:13.5px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,.2);animation:toast-in .3s cubic-bezier(.34,1.56,.64,1) forwards;pointer-events:all;border-left:3px solid var(--gold)}
@keyframes toast-in{from{opacity:0;transform:translateY(16px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.toast.fade-out{animation:toast-out .25s ease forwards}
@keyframes toast-out{to{opacity:0;transform:translateY(8px) scale(.97)}}

.fade-up{opacity:0;transform:translateY(14px);animation:fade-up-in .38s ease forwards}
@keyframes fade-up-in{to{opacity:1;transform:translateY(0)}}

@media(max-width:900px){.score-grid{grid-template-columns:1fr 1fr}.stat-strip{gap:8px}}
@media(max-width:600px){.page-content{padding:16px 14px 40px}.topbar-date{display:none}.topbar-search{max-width:160px}.score-grid{grid-template-columns:1fr}}
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
        <a href="admin_comments.php" class="nav-item active">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="nav-label">Comments</span>
            <?php if($badge_comments>0):?><span class="nav-badge"><?=$badge_comments?></span><?php endif;?>
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
            <input type="text" placeholder="Search comments or predictions..." id="globalSearch">
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
            <h1>Comments & Predictions</h1>
            <p>Moderate user comments, manage sentiments, and review prediction scores.</p>
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

        <!-- Section tabs -->
        <div class="section-tabs fade-up" style="animation-delay:.04s">
            <a href="#comments" class="section-tab <?=$section==='comments'?'active':''?>" onclick="setSection('comments')">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Comments
                <span class="tab-count"><?=number_format($c_stats['total'])?></span>
                <?php if($c_stats['flagged']>0):?>
                    <span style="background:var(--danger);color:#fff;font-family:var(--font-mono);font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px"><?=$c_stats['flagged']?> flagged</span>
                <?php endif;?>
            </a>
            <a href="#predictions" class="section-tab <?=$section==='predictions'?'active':''?>" onclick="setSection('predictions')">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                Predictions
                <span class="tab-count"><?=number_format($p_stats['total'])?></span>
            </a>
        </div>

        <!-- ══════════════════════════════════════════════════════
             SECTION 1: COMMENTS
        ══════════════════════════════════════════════════════ -->
        <div id="section-comments" class="<?=$section!=='comments'?'hidden':''?>">

            <!-- Stat strip -->
            <div class="stat-strip fade-up" style="animation-delay:.07s">
                <div class="stat-chip">
                    <div class="stat-chip-label">Total</div>
                    <div class="stat-chip-value"><?=number_format($c_stats['total'])?></div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-label">Flagged</div>
                    <div class="stat-chip-value flagged"><?=number_format($c_stats['flagged'])?></div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-label">Positive</div>
                    <div class="stat-chip-value pos"><?=number_format($c_stats['positive'])?></div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-label">Negative</div>
                    <div class="stat-chip-value neg"><?=number_format($c_stats['negative'])?></div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-label">Neutral</div>
                    <div class="stat-chip-value neu"><?=number_format($c_stats['neutral'])?></div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" action="" id="cFilterForm">
                <input type="hidden" name="section" value="comments">
                <div class="filter-row fade-up" style="animation-delay:.10s">
                    <select name="c_story" class="filter-select" onchange="document.getElementById('cFilterForm').submit()">
                        <option value="">All Stories</option>
                        <?php foreach($stories_list as $sl):?>
                            <option value="<?=$sl['story_id']?>" <?=$c_story==(int)$sl['story_id']?'selected':''?>>
                                <?=htmlspecialchars(mb_substr($sl['title'],0,28))?>
                            </option>
                        <?php endforeach;?>
                    </select>
                    <select name="c_sent" class="filter-select" onchange="document.getElementById('cFilterForm').submit()">
                        <option value="all" <?=$c_sent==='all'?'selected':''?>>All Sentiments</option>
                        <option value="positive" <?=$c_sent==='positive'?'selected':''?>>Positive</option>
                        <option value="negative" <?=$c_sent==='negative'?'selected':''?>>Negative</option>
                        <option value="neutral"  <?=$c_sent==='neutral'?'selected':''?>>Neutral</option>
                    </select>
                    <select name="c_flagged" class="filter-select" onchange="document.getElementById('cFilterForm').submit()">
                        <option value="all"     <?=$c_flagged==='all'?'selected':''?>>All Comments</option>
                        <option value="flagged" <?=$c_flagged==='flagged'?'selected':''?>>Flagged Only</option>
                        <option value="clean"   <?=$c_flagged==='clean'?'selected':''?>>Not Flagged</option>
                    </select>
                    <div class="filter-search-wrap">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" name="c_q" placeholder="Search comment text..." value="<?=htmlspecialchars($c_search)?>">
                    </div>
                    <button type="submit" class="filter-btn">Filter</button>
                    <?php if($c_story||$c_sent!=='all'||$c_flagged!=='all'||$c_search):?>
                        <a href="admin_comments.php?section=comments" class="filter-clear">Clear</a>
                    <?php endif;?>
                    <span class="results-count"><?=number_format($c_total)?> <?=$c_total===1?'comment':'comments'?></span>
                </div>
            </form>

            <!-- Comments list -->
            <div class="card fade-up" style="animation-delay:.13s">
                <div class="section-card-header">
                    <span class="section-card-title">Comments</span>
                    <span class="section-card-count">Page <?=$c_page?>/<?=$c_pages?></span>
                </div>

                <?php if(empty($comments)):?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <p>No comments found<?=$c_search?' matching "'.htmlspecialchars($c_search).'"':''?>.</p>
                    </div>
                <?php else:?>
                <?php foreach($comments as $c):
                    $initials = strtoupper(substr($c['user_name']??'U',0,1));
                    $sent = $c['sentiment'] ?? 'neutral';
                    $flagged = !empty($c['manually_flagged']);
                    $cdate = date('M j, Y g:i A', strtotime($c['created_at']));
                    $js_c = htmlspecialchars(json_encode([
                        'id'        => (int)$c['comment_id'],
                        'text'      => $c['comment_text'],
                        'sentiment' => $sent,
                        'flagged'   => $flagged ? 1 : 0,
                    ]), ENT_QUOTES);
                ?>
                <div class="comment-row <?=$flagged?'flagged-row':''?>" id="crow-<?=$c['comment_id']?>">
                    <!-- Header -->
                    <div class="comment-header">
                        <div class="comment-avatar"><?=$initials?></div>
                        <div class="comment-meta">
                            <div class="comment-user">@<?=htmlspecialchars($c['user_name']??'Unknown')?></div>
                            <div class="comment-story">
                                <?=htmlspecialchars($c['story_title']??'Unknown story')?>
                                <?php if($c['parent_comment_id']):?>
                                    &middot; <span style="color:var(--azure)">Reply</span>
                                <?php endif;?>
                            </div>
                        </div>
                        <div class="comment-date"><?=$cdate?></div>
                    </div>

                    <!-- Body -->
                    <div class="comment-body"><?=htmlspecialchars($c['comment_text'])?></div>

                    <!-- Footer: badges + actions -->
                    <div class="comment-footer">
                        <span class="sentiment-badge <?=$sent?>">
                            <?php if($sent==='positive'):?>
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            <?php elseif($sent==='negative'):?>
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            <?php else:?>
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            <?php endif;?>
                            <?=ucfirst($sent)?>
                        </span>

                        <?php if($flagged):?>
                            <span class="flagged-badge">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                Flagged
                            </span>
                        <?php endif;?>

                        <div class="comment-actions">
                            <button class="act-btn edit-btn" title="Edit comment"
                                onclick='toggleCommentEdit(<?=$js_c?>)'>
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <?php if($flagged):?>
                                <button class="act-btn unflag-btn" title="Remove flag" onclick="ajaxFlag(<?=$c['comment_id']?>,0,this)">
                                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            <?php else:?>
                                <button class="act-btn flag-btn" title="Flag as spam" onclick="ajaxFlag(<?=$c['comment_id']?>,1,this)">
                                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                </button>
                            <?php endif;?>
                            <button class="act-btn del-btn" title="Delete comment" onclick="openDelConfirm('cdel-<?=$c['comment_id']?>')">
                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Inline edit form -->
                    <div class="comment-edit-form" id="cedit-<?=$c['comment_id']?>">
                        <form method="POST" action="admin_comments.php?section=comments<?=$c_story?"&c_story=$c_story":''?><?=$c_sent!=='all'?"&c_sent=$c_sent":''?><?=$c_flagged!=='all'?"&c_flagged=$c_flagged":''?><?=$c_page>1?"&c_page=$c_page":''?>">
                            <input type="hidden" name="form_action" value="edit_comment">
                            <input type="hidden" name="comment_id" value="<?=$c['comment_id']?>">

                            <label class="edit-field-label">Comment Text</label>
                            <textarea name="comment_text" class="edit-textarea" rows="3"><?=htmlspecialchars($c['comment_text'])?></textarea>

                            <div class="edit-row">
                                <div>
                                    <label class="edit-field-label" style="margin-bottom:4px">Sentiment</label>
                                    <select name="sentiment" class="edit-select">
                                        <option value="positive" <?=$sent==='positive'?'selected':''?>>Positive</option>
                                        <option value="negative" <?=$sent==='negative'?'selected':''?>>Negative</option>
                                        <option value="neutral"  <?=$sent==='neutral' ?'selected':''?>>Neutral</option>
                                    </select>
                                </div>
                                <label class="edit-checkbox-label">
                                    <input type="checkbox" name="manually_flagged" <?=$flagged?'checked':''?>>
                                    Mark as flagged spam
                                </label>
                            </div>

                            <div class="edit-actions">
                                <button type="submit" class="btn-save-sm">Save Changes</button>
                                <button type="button" class="btn-cancel-sm"
                                    onclick="document.getElementById('cedit-<?=$c['comment_id']?>').classList.remove('open')">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Delete confirm -->
                    <div class="del-confirm" id="cdel-<?=$c['comment_id']?>">
                        <span>Delete this comment permanently?</span>
                        <form method="POST" action="admin_comments.php?section=comments" style="display:contents">
                            <input type="hidden" name="form_action" value="delete_comment">
                            <input type="hidden" name="comment_id" value="<?=$c['comment_id']?>">
                            <button type="submit" class="dc-yes">Delete</button>
                            <button type="button" class="dc-no" onclick="closeDelConfirm('cdel-<?=$c['comment_id']?>')">Cancel</button>
                        </form>
                    </div>
                </div>
                <?php endforeach;?>
                <?php endif;?>

                <!-- Pagination -->
                <?php if($c_pages>1):?>
                <div class="pagination">
                    <span class="page-info">Showing <?=$c_off+1?>–<?=min($c_off+$c_per,$c_total)?> of <?=$c_total?></span>
                    <div class="page-btns">
                        <?php
                        $cpurl = fn($p)=>'admin_comments.php?section=comments&c_page='.$p
                            .($c_story?"&c_story=$c_story":'')
                            .($c_sent!=='all'?"&c_sent=$c_sent":'')
                            .($c_flagged!=='all'?"&c_flagged=$c_flagged":'')
                            .($c_search?"&c_q=".urlencode($c_search):'');
                        ?>
                        <a href="<?=$cpurl(1)?>" class="page-btn <?=$c_page===1?'disabled':''?>">&laquo;</a>
                        <a href="<?=$cpurl(max(1,$c_page-1))?>" class="page-btn <?=$c_page===1?'disabled':''?>">Prev</a>
                        <?php for($pp=max(1,$c_page-2);$pp<=min($c_pages,$c_page+2);$pp++):?>
                            <a href="<?=$cpurl($pp)?>" class="page-btn <?=$pp===$c_page?'active':''?>"><?=$pp?></a>
                        <?php endfor;?>
                        <a href="<?=$cpurl(min($c_pages,$c_page+1))?>" class="page-btn <?=$c_page===$c_pages?'disabled':''?>">Next</a>
                        <a href="<?=$cpurl($c_pages)?>" class="page-btn <?=$c_page===$c_pages?'disabled':''?>">&raquo;</a>
                    </div>
                </div>
                <?php endif;?>
            </div>
        </div><!-- /section-comments -->

        <!-- ══════════════════════════════════════════════════════
             SECTION 2: PREDICTIONS
        ══════════════════════════════════════════════════════ -->
        <div id="section-predictions" class="<?=$section!=='predictions'?'hidden':''?>">

            <!-- Stat strip -->
            <div class="stat-strip fade-up" style="animation-delay:.07s">
                <div class="stat-chip">
                    <div class="stat-chip-label">Total</div>
                    <div class="stat-chip-value"><?=number_format($p_stats['total'])?></div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-label">With Bonus</div>
                    <div class="stat-chip-value"><?=number_format($p_stats['with_bonus'])?></div>
                </div>
                <!-- <div class="stat-chip">
                    <div class="stat-chip-label">Avg SBERT</div>
                    <div class="stat-chip-value"><?=$p_stats['avg_sbert']?:'—'?></div>
                </div> -->
                <div class="stat-chip">
                    <div class="stat-chip-label">Avg Manual</div>
                    <div class="stat-chip-value"><?=$p_stats['avg_manual']?:'—'?></div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" action="" id="pFilterForm">
                <input type="hidden" name="section" value="predictions">
                <div class="filter-row fade-up" style="animation-delay:.10s">
                    <select name="p_story" class="filter-select" onchange="document.getElementById('pFilterForm').submit()">
                        <option value="">All Stories</option>
                        <?php foreach($stories_list as $sl):?>
                            <option value="<?=$sl['story_id']?>" <?=$p_story==(int)$sl['story_id']?'selected':''?>>
                                <?=htmlspecialchars(mb_substr($sl['title'],0,28))?>
                            </option>
                        <?php endforeach;?>
                    </select>
                    <select name="p_sort" class="filter-select" onchange="document.getElementById('pFilterForm').submit()">
                        <option value="newest"      <?=$p_sort==='newest'?'selected':''?>>Newest First</option>
                        <option value="score_high"  <?=$p_sort==='score_high'?'selected':''?>>SBERT High</option>
                        <option value="score_low"   <?=$p_sort==='score_low'?'selected':''?>>SBERT Low</option>
                        <option value="manual_high" <?=$p_sort==='manual_high'?'selected':''?>>Manual High</option>
                    </select>
                    <div class="filter-search-wrap">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" name="p_q" placeholder="Search predictions..." value="<?=htmlspecialchars($p_search)?>">
                    </div>
                    <button type="submit" class="filter-btn">Filter</button>
                    <?php if($p_story||$p_search||$p_sort!=='newest'):?>
                        <a href="admin_comments.php?section=predictions" class="filter-clear">Clear</a>
                    <?php endif;?>
                    <span class="results-count"><?=number_format($p_total)?> <?=$p_total===1?'prediction':'predictions'?></span>
                </div>
            </form>

            <!-- Predictions list -->
            <div class="card fade-up" style="animation-delay:.13s">
                <div class="section-card-header">
                    <span class="section-card-title">Predictions</span>
                    <span class="section-card-count">Page <?=$p_page?>/<?=$p_pages?></span>
                </div>

                <?php if(empty($predictions)):?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                        <p>No predictions found<?=$p_search?' matching "'.htmlspecialchars($p_search).'"':''?>.</p>
                    </div>
                <?php else:?>
                <?php foreach($predictions as $i=>$p):
                    $pdate   = date('M j, Y', strtotime($p['created_at']));
                    // $has_sbert  = $p['sbert_score'] !== null;
                    // $has_ml     = $p['ml_score'] !== null;
                    $has_manual = $p['manual_accuracy'] !== null;
                    $has_bonus  = $p['bonus_amount'] !== null;
                    $js_p = htmlspecialchars(json_encode([
                        'id'             => (int)$p['prediction_id'],
                        'text'           => $p['prediction_text'],
                        // 'sbert_score'    => $has_sbert  ? (float)$p['sbert_score']    : '',
                        // 'ml_score'       => $has_ml     ? (float)$p['ml_score']       : '',
                        'manual_accuracy'=> $has_manual ? (float)$p['manual_accuracy']: '',
                    ]),ENT_QUOTES);
                ?>
                <div class="prediction-row" id="prow-<?=$p['prediction_id']?>">
                    <div class="pred-header">
                        <div class="pred-num">#<?=$p['prediction_id']?></div>
                        <div class="pred-meta">
                            <div class="pred-user">@<?=htmlspecialchars($p['user_name']??'Unknown')?></div>
                            <div class="pred-story">
                                <?=htmlspecialchars($p['story_title']??'Unknown')?>
                                &middot; Part <?=$p['prediction_part_no']?>
                                &middot; <?=$pdate?>
                            </div>
                        </div>
                        <div class="comment-actions">
                            <button class="act-btn edit-btn" title="Edit prediction"
                                onclick='togglePredEdit(<?=$js_p?>)'>
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="act-btn del-btn" title="Delete prediction" onclick="openDelConfirm('pdel-<?=$p['prediction_id']?>')">
                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Prediction body -->
                    <div class="pred-body"><?=htmlspecialchars($p['prediction_text'])?></div>

                    <!-- Score pills -->
                    <div class="pred-scores">
                        <!-- <div class="score-pill <?=$has_sbert?'sbert':'none'?>">
                            <div class="score-pill-label">SBERT</div>
                            <div class="score-pill-value"><?=$has_sbert?number_format((float)$p['sbert_score'],1):'—'?></div>
                        </div> -
                        <div class="score-pill <?=$has_ml?'ml':'none'?>">
                            <div class="score-pill-label">ML</div>
                            <div class="score-pill-value"><?=$has_ml?number_format((float)$p['ml_score'],1):'—'?></div>
                        </div> -->
                        <div class="score-pill <?=$has_manual?'manual':'none'?>">
                            <div class="score-pill-label">Manual</div>
                            <div class="score-pill-value"><?=$has_manual?number_format((float)$p['manual_accuracy'],1):'—'?></div>
                        </div>
                        <?php if($has_bonus):?>
                        <div class="score-pill bonus">
                            <div class="score-pill-label">Bonus</div>
                            <div class="score-pill-value">+<?=number_format((float)$p['bonus_amount'],2)?></div>
                        </div>
                        <?php endif;?>
                    </div>

                    <!-- Inline edit form -->
                    <div class="pred-edit-form" id="pedit-<?=$p['prediction_id']?>">
                        <form method="POST" action="admin_comments.php?section=predictions<?=$p_story?"&p_story=$p_story":''?><?=$p_sort!=='newest'?"&p_sort=$p_sort":''?><?=$p_page>1?"&p_page=$p_page":''?>">
                            <input type="hidden" name="form_action" value="edit_prediction">
                            <input type="hidden" name="prediction_id" value="<?=$p['prediction_id']?>">

                            <label class="edit-field-label">Prediction Text</label>
                            <textarea name="prediction_text" class="edit-textarea" rows="3"><?=htmlspecialchars($p['prediction_text'])?></textarea>

                            <div class="score-grid">
                                <div class="score-input-wrap">
                                    <label class="edit-field-label">SBERT Score</label>
                                    <input type="number" name="sbert_score" class="score-input"
                                           step="0.01" min="0" max="100" placeholder="Auto from ML"
                                           value="<?=$has_sbert?htmlspecialchars($p['sbert_score']):''?>">
                                    <div class="score-input-hint">Display-only from /evaluate-story</div>
                                </div>
                                <!--  <div class="score-input-wrap">
                                    <label class="edit-field-label">ML Score</label>
                                    <input type="number" name="ml_score" class="score-input"
                                           step="0.01" min="0" max="100" placeholder="Auto from ML"
                                           value="<?=$has_ml?htmlspecialchars($p['ml_score']):''?>">
                                    <div class="score-input-hint">Display-only from /evaluate-story</div>
                                </div> -->
                                <div class="score-input-wrap">
                                    <label class="edit-field-label">Manual Accuracy</label>
                                    <input type="number" name="manual_accuracy" class="score-input"
                                           step="0.01" min="0" max="100" placeholder="e.g. 85.5"
                                           value="<?=$has_manual?htmlspecialchars($p['manual_accuracy']):''?>">
                                    <div class="score-input-hint" style="color:var(--success)">Drives bonus calculation</div>
                                </div>
                            </div>

                            <div class="edit-actions">
                                <button type="submit" class="btn-save-sm">Save Changes</button>
                                <button type="button" class="btn-cancel-sm"
                                    onclick="document.getElementById('pedit-<?=$p['prediction_id']?>').classList.remove('open')">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Delete confirm -->
                    <div class="del-confirm" id="pdel-<?=$p['prediction_id']?>">
                        <span>Delete prediction #<?=$p['prediction_id']?> permanently?</span>
                        <form method="POST" action="admin_comments.php?section=predictions" style="display:contents">
                            <input type="hidden" name="form_action" value="delete_prediction">
                            <input type="hidden" name="prediction_id" value="<?=$p['prediction_id']?>">
                            <button type="submit" class="dc-yes">Delete</button>
                            <button type="button" class="dc-no" onclick="closeDelConfirm('pdel-<?=$p['prediction_id']?>')">Cancel</button>
                        </form>
                    </div>
                </div>
                <?php endforeach;?>
                <?php endif;?>

                <!-- Pagination -->
                <?php if($p_pages>1):?>
                <div class="pagination">
                    <span class="page-info">Showing <?=$p_off+1?>–<?=min($p_off+$p_per,$p_total)?> of <?=$p_total?></span>
                    <div class="page-btns">
                        <?php
                        $ppurl = fn($pg)=>'admin_comments.php?section=predictions&p_page='.$pg
                            .($p_story?"&p_story=$p_story":'')
                            .($p_sort!=='newest'?"&p_sort=$p_sort":'')
                            .($p_search?"&p_q=".urlencode($p_search):'');
                        ?>
                        <a href="<?=$ppurl(1)?>" class="page-btn <?=$p_page===1?'disabled':''?>">&laquo;</a>
                        <a href="<?=$ppurl(max(1,$p_page-1))?>" class="page-btn <?=$p_page===1?'disabled':''?>">Prev</a>
                        <?php for($pg=max(1,$p_page-2);$pg<=min($p_pages,$p_page+2);$pg++):?>
                            <a href="<?=$ppurl($pg)?>" class="page-btn <?=$pg===$p_page?'active':''?>"><?=$pg?></a>
                        <?php endfor;?>
                        <a href="<?=$ppurl(min($p_pages,$p_page+1))?>" class="page-btn <?=$p_page===$p_pages?'disabled':''?>">Next</a>
                        <a href="<?=$ppurl($p_pages)?>" class="page-btn <?=$p_page===$p_pages?'disabled':''?>">&raquo;</a>
                    </div>
                </div>
                <?php endif;?>
            </div>
        </div><!-- /section-predictions -->

    </main>
</div>

<div class="toast-container" id="toastContainer"></div>

<style>.hidden{display:none}</style>

<script>
// ── Sidebar ────────────────────────────────────────────────────
const sidebar=document.getElementById('sidebar'),mainWrapper=document.getElementById('mainWrapper'),hamburger=document.getElementById('hamburger');
hamburger.addEventListener('click',()=>{const c=sidebar.classList.toggle('collapsed');mainWrapper.classList.toggle('expanded',c);hamburger.classList.toggle('active',c);localStorage.setItem('sv_sidebar',c?'1':'0')});
if(localStorage.getItem('sv_sidebar')==='1'){sidebar.classList.add('collapsed');mainWrapper.classList.add('expanded');hamburger.classList.add('active')}

// ── Section tabs ───────────────────────────────────────────────
function setSection(s) {
    document.getElementById('section-comments').classList.toggle('hidden', s!=='comments');
    document.getElementById('section-predictions').classList.toggle('hidden', s!=='predictions');
    document.querySelectorAll('.section-tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.section-tab').forEach(t=>{if(t.getAttribute('href')==='#'+s)t.classList.add('active')});
    // Update URL without reload
    const url=new URL(location.href);url.searchParams.set('section',s);history.replaceState(null,'',url);
}

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg,type='default'){
    const tc=document.getElementById('toastContainer'),t=document.createElement('div');
    t.className='toast';t.style.borderLeftColor=type==='success'?'var(--success)':type==='error'?'var(--danger)':'var(--gold)';
    t.textContent=msg;tc.appendChild(t);
    setTimeout(()=>{t.classList.add('fade-out');setTimeout(()=>t.remove(),280)},3500);
}

// ── Toggle inline forms ────────────────────────────────────────
function toggleCommentEdit(c) {
    const el = document.getElementById('cedit-'+c.id);
    const isOpen = el.classList.contains('open');
    // Close all open edit forms first
    document.querySelectorAll('.comment-edit-form.open,.pred-edit-form.open').forEach(f=>f.classList.remove('open'));
    if (!isOpen) el.classList.add('open');
}

function togglePredEdit(p) {
    const el = document.getElementById('pedit-'+p.id);
    const isOpen = el.classList.contains('open');
    document.querySelectorAll('.comment-edit-form.open,.pred-edit-form.open').forEach(f=>f.classList.remove('open'));
    if (!isOpen) el.classList.add('open');
}

// ── Delete confirm ─────────────────────────────────────────────
function openDelConfirm(id) {
    document.querySelectorAll('.del-confirm.open').forEach(el=>el.classList.remove('open'));
    document.getElementById(id).classList.add('open');
}
function closeDelConfirm(id) {
    document.getElementById(id).classList.remove('open');
}

// ── AJAX: Flag / Unflag comment ────────────────────────────────
function ajaxFlag(commentId, state, btn) {
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'flag_comment');   // uses admin_ajax.php existing action
    if (state === 0) fd.set('action','unflag_comment');
    fd.append('id', commentId);

    fetch('admin_ajax.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(d=>{
            if(d.success) {
                showToast(state===1?'Comment flagged as spam':'Flag removed','success');
                setTimeout(()=>location.reload(),900);
            } else {
                showToast(d.msg||'Failed','error');
                btn.disabled=false;
            }
        })
        .catch(()=>{showToast('Request failed','error');btn.disabled=false;});
}

// ── Global search redirect ─────────────────────────────────────
document.getElementById('globalSearch').addEventListener('keydown',function(e){
    if(e.key==='Enter'&&this.value.trim()){
        const section=document.getElementById('section-predictions').classList.contains('hidden')?'comments':'predictions';
        const param=section==='comments'?'c_q':'p_q';
        location.href=`admin_comments.php?section=${section}&${param}=${encodeURIComponent(this.value.trim())}`;
    }
});
</script>
</body>
</html>
