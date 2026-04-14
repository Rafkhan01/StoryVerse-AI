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

// ══════════════════════════════════════════════════════════════
// QUERY 1 — STORY PREDICTION
// ══════════════════════════════════════════════════════════════
$pred_rows = [];
try {
    $pred_rows = $pdo->query("
        SELECT
            u.user_id, u.user_name, u.first_name, u.last_name,
            COUNT(p.prediction_id)               AS total_predictions,
            ROUND(AVG(p.manual_accuracy), 2)     AS avg_accuracy,
            COALESCE(SUM(b.bonus_amount), 0)     AS total_bonus,
            COALESCE(lk.total_likes, 0)          AS total_likes
        FROM users u
        JOIN predictions p ON p.user_id = u.user_id
            AND p.manual_accuracy IS NOT NULL
        LEFT JOIN bonus b ON b.user_id = u.user_id
        LEFT JOIN (
            SELECT pr.user_id, COUNT(l.like_id) AS total_likes
            FROM likes l
            JOIN predictions pr ON pr.prediction_id = l.prediction_id
            GROUP BY pr.user_id
        ) lk ON lk.user_id = u.user_id
        GROUP BY u.user_id
        ORDER BY avg_accuracy DESC, total_bonus DESC, total_likes DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

// ══════════════════════════════════════════════════════════════
// QUERY 2 — FLASH WORDS
// ══════════════════════════════════════════════════════════════
$flash_rows = [];
try {
    $flash_rows = $pdo->query("
        SELECT
            u.user_id, u.user_name, u.first_name, u.last_name,
            SUM(ap.score)          AS total_score,
            MAX(ap.best_wpm)       AS best_wpm,
            SUM(ap.total_correct)  AS total_correct,
            SUM(ap.total_attempts) AS total_attempts
        FROM users u
        JOIN arena_progress ap ON ap.user_id = u.user_id
        GROUP BY u.user_id
        ORDER BY total_score DESC, best_wpm DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

// ══════════════════════════════════════════════════════════════
// QUERY 3 — STORY SCRAMBLE
// ══════════════════════════════════════════════════════════════
$scramble_rows = [];
try {
    $scramble_rows = $pdo->query("
        SELECT
            u.user_id, u.user_name, u.first_name, u.last_name,
            SUM(gs.score)         AS total_score,
            SUM(gs.correct_pairs) AS total_correct_pairs,
            COUNT(gs.id)          AS games_played
        FROM users u
        JOIN game_scores gs ON gs.user_id = u.user_id
        GROUP BY u.user_id
        ORDER BY total_score DESC, total_correct_pairs DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

// ══════════════════════════════════════════════════════════════
// QUERY 4 — OVERALL (Composite: Pred 50%, Flash 30%, Scram 20%)
// ══════════════════════════════════════════════════════════════
$overall_rows = [];
try {
    $max_pred  = !empty($pred_rows)     ? max(array_column($pred_rows,    'avg_accuracy')) : 0;
    $max_flash = !empty($flash_rows)    ? max(array_column($flash_rows,   'total_score'))  : 0;
    $max_scram = !empty($scramble_rows) ? max(array_column($scramble_rows,'total_score'))  : 0;

    $pred_map  = []; foreach ($pred_rows     as $r) $pred_map[$r['user_id']]  = $r;
    $flash_map = []; foreach ($flash_rows    as $r) $flash_map[$r['user_id']] = $r;
    $scram_map = []; foreach ($scramble_rows as $r) $scram_map[$r['user_id']] = $r;

    $all_uids = array_unique(array_merge(
        array_column($pred_rows,    'user_id'),
        array_column($flash_rows,   'user_id'),
        array_column($scramble_rows,'user_id')
    ));

    if (!empty($all_uids)) {
        $in = implode(',', array_map('intval', $all_uids));
        $users_info = [];
        foreach ($pdo->query("SELECT user_id,user_name,first_name,last_name FROM users WHERE user_id IN ($in)")
                     ->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $users_info[$u['user_id']] = $u;
        }

        foreach ($all_uids as $uid) {
            if (!isset($users_info[$uid])) continue;
            $u = $users_info[$uid];
            $np = ($max_pred  > 0 && isset($pred_map[$uid]))  ? ($pred_map[$uid]['avg_accuracy']   / $max_pred  * 100) : 0;
            $nf = ($max_flash > 0 && isset($flash_map[$uid])) ? ($flash_map[$uid]['total_score']   / $max_flash * 100) : 0;
            $ns = ($max_scram > 0 && isset($scram_map[$uid])) ? ($scram_map[$uid]['total_score']   / $max_scram * 100) : 0;
            $composite = round($np * 0.50 + $nf * 0.30 + $ns * 0.20, 2);

            $overall_rows[] = [
                'user_id'     => $uid,
                'user_name'   => $u['user_name'],
                'first_name'  => $u['first_name'],
                'last_name'   => $u['last_name'],
                'composite'   => $composite,
                'pred_acc'    => isset($pred_map[$uid])  ? round($pred_map[$uid]['avg_accuracy'], 2) : null,
                'flash_score' => isset($flash_map[$uid]) ? $flash_map[$uid]['total_score']           : null,
                'scram_score' => isset($scram_map[$uid]) ? $scram_map[$uid]['total_score']           : null,
            ];
        }
        usort($overall_rows, fn($a,$b) => $b['composite'] <=> $a['composite']);
    }
} catch (PDOException $e) { error_log($e->getMessage()); }

// ── Summary stats ──────────────────────────────────────────────
$total_pred_players    = count($pred_rows);
$total_flash_players   = count($flash_rows);
$total_scramble_players= count($scramble_rows);
$total_overall_players = count($overall_rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leaderboard — StoryVerse Admin</title>
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
.page-content{padding:28px 28px 60px;flex:1}
.page-header{margin-bottom:22px}
.page-header h1{font-family:var(--font-display);font-size:22px;color:var(--text-primary);margin-bottom:4px}
.page-header p{font-size:14px;color:var(--text-secondary)}

/* ── STAT STRIP ── */
.stat-strip{display:flex;gap:14px;margin-bottom:24px;flex-wrap:wrap}
.stat-chip{background:var(--card-bg);border:1px solid var(--card-border);border-radius:10px;box-shadow:var(--card-shadow);padding:14px 18px;flex:1;min-width:100px;position:relative;overflow:hidden}
.stat-chip::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:var(--card-radius) var(--card-radius) 0 0}
.stat-chip.c-azure::before{background:linear-gradient(90deg,#3B82F6,#60A5FA)}
.stat-chip.c-purple::before{background:linear-gradient(90deg,#8B5CF6,#A78BFA)}
.stat-chip.c-success::before{background:linear-gradient(90deg,#10B981,#34D399)}
.stat-chip.c-gold::before{background:linear-gradient(90deg,#F5A623,#FBD380)}
.stat-chip-label{font-family:var(--font-mono);font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:5px}
.stat-chip-value{font-family:var(--font-body);font-size:26px;font-weight:700;color:var(--text-primary);line-height:1}
.stat-chip-sub{font-size:11px;color:var(--text-muted);margin-top:3px}

/* ── FORMULA INFO BANNER ── */
.formula-banner{display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,#EFF6FF,#DBEAFE);border:1px solid rgba(59,130,246,.25);border-radius:10px;padding:12px 18px;margin-bottom:22px;flex-wrap:wrap}
.formula-banner svg{width:16px;height:16px;stroke:#1D4ED8;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}
.formula-text{font-size:13px;color:#1D4ED8;font-weight:600}
.formula-pills{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-left:auto}
.fpill{font-family:var(--font-mono);font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px}
.fpill.pred{background:rgba(59,130,246,.12);color:#1D4ED8}
.fpill.flash{background:rgba(139,92,246,.12);color:#6D28D9}
.fpill.scram{background:rgba(16,185,129,.12);color:#065F46}

/* ── TABS ── */
.section-tabs{display:flex;gap:4px;margin-bottom:22px;border-bottom:2px solid var(--border);padding-bottom:0}
.section-tab{padding:10px 18px;font-family:var(--font-body);font-size:14px;font-weight:700;color:var(--text-muted);cursor:pointer;border:none;background:none;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .18s,border-color .18s;display:flex;align-items:center;gap:8px;white-space:nowrap}
.section-tab:hover{color:var(--text-primary)}
.section-tab.active{color:var(--text-primary);border-bottom-color:var(--gold)}
.section-tab svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round}
.tab-count{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px;background:var(--divider);color:var(--text-muted)}
.section-tab.active .tab-count{background:rgba(245,166,35,.15);color:var(--gold-dark)}

/* ── TWO-COLUMN GRID ── */
.main-grid{display:grid;grid-template-columns:360px 1fr;gap:22px;align-items:start}

/* ── EDIT FORM CARD ── */
.edit-card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);position:sticky;top:calc(var(--topbar-h) + 20px);overflow:hidden}
.edit-card-header{padding:16px 20px 13px;border-bottom:1px solid var(--divider);display:flex;align-items:center;justify-content:space-between}
.edit-card-title{font-family:var(--font-body);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-primary)}
.edit-mode-badge{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:3px 9px;border-radius:4px}
.edit-mode-badge.idle{background:var(--divider);color:var(--text-muted);border:1px solid var(--border)}
.edit-mode-badge.active{background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE}

/* Empty state inside form */
.edit-empty{padding:28px 20px;text-align:center;color:var(--text-muted)}
.edit-empty svg{width:40px;height:40px;stroke:var(--border);fill:none;stroke-width:1.4;margin:0 auto 12px;display:block}
.edit-empty p{font-size:13px}

.edit-body{padding:18px 20px;display:none}
.edit-body.show{display:block}

/* User info strip */
.edit-user-strip{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg);border:1px solid var(--border);border-radius:9px;margin-bottom:16px}
.edit-user-avatar{width:34px;height:34px;border-radius:8px;background:#EFF6FF;color:var(--azure);display:flex;align-items:center;justify-content:center;font-family:var(--font-body);font-size:13px;font-weight:700;flex-shrink:0}
.edit-user-name{font-size:14px;font-weight:700;color:var(--text-primary)}
.edit-user-rank{font-family:var(--font-mono);font-size:10px;color:var(--text-muted)}

/* Field styles */
.field-group{margin-bottom:14px}
.field-label{display:block;font-family:var(--font-body);font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:5px}
.field-hint{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:3px}
.field-input{width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;outline:none;font-family:var(--font-mono);font-size:14px;color:var(--text-primary);transition:border-color .18s,box-shadow .18s}
.field-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-input::placeholder{color:var(--text-muted)}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-section{font-family:var(--font-mono);font-size:9px;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);margin:14px 0 10px;display:flex;align-items:center;gap:8px}
.form-section::after{content:'';flex:1;height:1px;background:var(--divider)}

/* Current value display */
.current-val{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--divider);border-radius:7px;margin-bottom:14px}
.cv-label{font-family:var(--font-mono);font-size:10px;color:var(--text-muted)}
.cv-value{font-family:var(--font-mono);font-size:12px;font-weight:700;color:var(--text-primary)}

.edit-footer{padding:14px 20px;border-top:1px solid var(--divider);display:flex;gap:8px;background:var(--divider)}
.btn-save{flex:1;padding:10px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border:none;border-radius:8px;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:opacity .15s,transform .15s}
.btn-save:hover{opacity:.9;transform:translateY(-1px)}
.btn-save:disabled{opacity:.5;cursor:not-allowed;transform:none}
.btn-cancel{padding:10px 14px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;color:var(--text-secondary);font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:background .15s}
.btn-cancel:hover{background:var(--border)}

/* ── LEADERBOARD TABLE CARD ── */
.lb-card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);overflow:hidden}
.lb-card-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 13px;border-bottom:1px solid var(--divider)}
.lb-card-title{font-family:var(--font-body);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-primary)}
.lb-card-count{font-family:var(--font-mono);font-size:11px;color:var(--text-muted)}

.lb-table{width:100%;border-collapse:collapse}
.lb-table thead tr{background:var(--divider);border-bottom:1px solid var(--border)}
.lb-table thead th{padding:10px 14px;text-align:left;font-family:var(--font-mono);font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);white-space:nowrap}
.lb-table tbody tr{border-bottom:1px solid var(--divider);transition:background .1s}
.lb-table tbody tr:last-child{border-bottom:none}
.lb-table tbody tr:hover{background:#FAFBFF}
.lb-table tbody tr.editing-row{background:rgba(245,166,35,.05);outline:2px solid rgba(245,166,35,.3);outline-offset:-1px}
.lb-table td{padding:12px 14px;vertical-align:middle}

/* Rank cell */
.rank-cell{font-family:var(--font-mono);font-size:12px;font-weight:700;color:var(--text-muted);text-align:center;width:44px}
.rank-cell.gold{color:var(--gold)}
.rank-cell.silver{color:#9CA3AF}
.rank-cell.bronze{color:#CD7F32}

/* User cell */
.user-cell{display:flex;align-items:center;gap:10px}
.lb-avatar{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-family:var(--font-body);font-size:12px;font-weight:700;flex-shrink:0}
.lb-avatar.rank-1{background:rgba(245,166,35,.15);color:var(--gold-dark)}
.lb-avatar.rank-2{background:rgba(156,163,175,.12);color:#6B7280}
.lb-avatar.rank-3{background:rgba(205,127,50,.12);color:#CD7F32}
.lb-avatar.rank-n{background:#EFF6FF;color:var(--azure)}
.lb-username{font-size:13px;font-weight:700;color:var(--text-primary)}
.lb-fullname{font-size:11px;color:var(--text-muted);margin-top:1px}

/* Score cells */
.score-val{font-family:var(--font-mono);font-size:12px;font-weight:700}
.score-val.primary{color:var(--azure)}
.score-val.secondary{color:var(--purple)}
.score-val.overall{color:var(--gold-dark)}
.mono-val{font-family:var(--font-mono);font-size:11px;color:var(--text-secondary)}

/* Edit action button */
.lb-edit-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:6px;border:1px solid var(--border);background:var(--card-bg);font-family:var(--font-body);font-size:12px;font-weight:700;color:var(--azure);cursor:pointer;transition:all .15s;white-space:nowrap}
.lb-edit-btn:hover{background:#EFF6FF;border-color:#BFDBFE}
.lb-edit-btn.active{background:rgba(245,166,35,.1);border-color:rgba(245,166,35,.4);color:var(--gold-dark)}
.lb-edit-btn svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}

/* Overall read-only notice */
.overall-notice{display:flex;align-items:flex-start;gap:10px;padding:14px 18px;background:#FFFBEB;border:1px solid #FCD34D;border-radius:10px;margin:14px 20px;font-size:13px;color:#92400E;font-weight:600}
.overall-notice svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;margin-top:1px}

/* Empty state */
.empty-state{text-align:center;padding:48px 24px;color:var(--text-muted)}
.empty-state svg{width:40px;height:40px;stroke:var(--border);fill:none;stroke-width:1.4;margin:0 auto 12px;display:block}
.empty-state p{font-size:13px}

/* Activity log */
.activity-card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);overflow:hidden;margin-top:22px}
.activity-header{padding:14px 20px 12px;border-bottom:1px solid var(--divider)}
.activity-title{font-family:var(--font-body);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-primary)}
.activity-row{display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid var(--divider)}
.activity-row:last-child{border-bottom:none}
.activity-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;background:var(--gold)}
.activity-text{font-size:13px;color:var(--text-secondary);flex:1}
.activity-time{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);white-space:nowrap}
.activity-empty{padding:20px;font-size:13px;color:var(--text-muted);font-style:italic}

/* Toast */
.toast-container{position:fixed;bottom:28px;right:28px;z-index:999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;padding:12px 18px;background:#1A1D2E;color:#fff;border-radius:10px;font-family:var(--font-body);font-size:13.5px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,.2);animation:toast-in .3s cubic-bezier(.34,1.56,.64,1) forwards;pointer-events:all;border-left:3px solid var(--gold)}
@keyframes toast-in{from{opacity:0;transform:translateY(16px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.toast.fade-out{animation:toast-out .25s ease forwards}
@keyframes toast-out{to{opacity:0;transform:translateY(8px) scale(.97)}}

.fade-up{opacity:0;transform:translateY(14px);animation:fade-up-in .38s ease forwards}
@keyframes fade-up-in{to{opacity:1;transform:translateY(0)}}
.hidden{display:none}

@media(max-width:1100px){.main-grid{grid-template-columns:1fr}.edit-card{position:static}}
@media(max-width:700px){.page-content{padding:16px 14px 40px}.topbar-date{display:none}.topbar-search{max-width:160px}.field-row{grid-template-columns:1fr}}
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
        <a href="admin_leaderboard.php" class="nav-item active">
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

<!-- ═══ MAIN ════════════════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
        <div class="topbar-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search users..." id="tableSearch">
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
            <h1>Leaderboard</h1>
            <p>View rankings and directly override player scores across all boards.</p>
        </div>

        <!-- Stat strip -->
        <div class="stat-strip fade-up" style="animation-delay:.04s">
            <div class="stat-chip c-azure">
                <div class="stat-chip-label">Overall Players</div>
                <div class="stat-chip-value"><?=number_format($total_overall_players)?></div>
                <div class="stat-chip-sub">Composite ranked</div>
            </div>
            <div class="stat-chip c-azure">
                <div class="stat-chip-label">Prediction Players</div>
                <div class="stat-chip-value"><?=number_format($total_pred_players)?></div>
                <div class="stat-chip-sub">With evaluated preds</div>
            </div>
            <div class="stat-chip c-purple">
                <div class="stat-chip-label">Flash Words Players</div>
                <div class="stat-chip-value"><?=number_format($total_flash_players)?></div>
                <div class="stat-chip-sub">In arena_progress</div>
            </div>
            <div class="stat-chip c-success">
                <div class="stat-chip-label">Scramble Players</div>
                <div class="stat-chip-value"><?=number_format($total_scramble_players)?></div>
                <div class="stat-chip-sub">In game_scores</div>
            </div>
        </div>

        <!-- Formula info -->
        <div class="formula-banner fade-up" style="animation-delay:.07s">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span class="formula-text">Overall composite formula:</span>
            <div class="formula-pills">
                <span class="fpill pred">Prediction Accuracy &times; 50%</span>
                <span style="font-size:12px;color:#1D4ED8;font-weight:700">+</span>
                <span class="fpill flash">Flash Words Score &times; 30%</span>
                <span style="font-size:12px;color:#1D4ED8;font-weight:700">+</span>
                <span class="fpill scram">Story Scramble &times; 20%</span>
            </div>
        </div>

        <!-- Section tabs -->
        <div class="section-tabs fade-up" style="animation-delay:.10s">
            <button class="section-tab active" onclick="switchTab('overall')" id="tab-overall">
                <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                Overall
                <span class="tab-count"><?=$total_overall_players?></span>
            </button>
            <button class="section-tab" onclick="switchTab('pred')" id="tab-pred">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                Story Prediction
                <span class="tab-count"><?=$total_pred_players?></span>
            </button>
            <button class="section-tab" onclick="switchTab('flash')" id="tab-flash">
                <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                Flash Words
                <span class="tab-count"><?=$total_flash_players?></span>
            </button>
            <button class="section-tab" onclick="switchTab('scramble')" id="tab-scramble">
                <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                Story Scramble
                <span class="tab-count"><?=$total_scramble_players?></span>
            </button>
        </div>

        <!-- ═══ SECTION: OVERALL (read-only composite) ═══ -->
        <div id="section-overall" class="fade-up" style="animation-delay:.13s">
            <div class="lb-card">
                <div class="lb-card-header">
                    <span class="lb-card-title">Overall Leaderboard</span>
                    <span class="lb-card-count"><?=$total_overall_players?> players</span>
                </div>
                <div class="overall-notice">
                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Overall scores are computed automatically from the three sub-boards. To change a player's overall rank, edit their scores in Story Prediction, Flash Words, or Story Scramble tabs.
                </div>
                <?php if(empty($overall_rows)):?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24"><polyline points="18 20 18 10"/><polyline points="12 20 12 4"/><polyline points="6 20 6 14"/></svg>
                        <p>No players have scores yet.</p>
                    </div>
                <?php else:?>
                <div style="overflow-x:auto">
                <table class="lb-table">
                    <thead><tr>
                        <th style="width:44px">Rank</th>
                        <th>Player</th>
                        <th>Pred Accuracy</th>
                        <th>Flash Score</th>
                        <th>Scramble Score</th>
                        <th>Composite</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($overall_rows as $i=>$r):
                        $rank = $i+1;
                        $rc = $rank===1?'gold':($rank===2?'silver':($rank===3?'bronze':'rank-n'));
                        $init = strtoupper(substr($r['first_name']??$r['user_name'],0,1).substr($r['last_name']??'',0,1));
                    ?>
                    <tr>
                        <td><div class="rank-cell <?=$rc?>">#<?=$rank?></div></td>
                        <td>
                            <div class="user-cell">
                                <div class="lb-avatar <?=$rc?>"><?=htmlspecialchars($init)?></div>
                                <div>
                                    <div class="lb-username">@<?=htmlspecialchars($r['user_name'])?></div>
                                    <div class="lb-fullname"><?=htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??'')))?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="mono-val"><?=$r['pred_acc']!==null?number_format((float)$r['pred_acc'],2).'%':'—'?></span></td>
                        <td><span class="mono-val"><?=$r['flash_score']!==null?number_format($r['flash_score']):'—'?></span></td>
                        <td><span class="mono-val"><?=$r['scram_score']!==null?number_format($r['scram_score']):'—'?></span></td>
                        <td><span class="score-val overall"><?=number_format((float)$r['composite'],2)?></span></td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
                </div>
                <?php endif;?>
            </div>
        </div>

        <!-- ═══ SECTION: STORY PREDICTION ═══ -->
        <div id="section-pred" class="hidden fade-up" style="animation-delay:.13s">
            <div class="main-grid">
                <!-- Edit form -->
                <div class="edit-card" id="pred-edit-card">
                    <div class="edit-card-header">
                        <span class="edit-card-title">Edit Player Scores</span>
                        <span class="edit-mode-badge idle" id="pred-edit-badge">SELECT A PLAYER</span>
                    </div>
                    <div class="edit-empty" id="pred-edit-empty">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                        <p>Click the Edit button next to any player to override their prediction scores.</p>
                    </div>
                    <div class="edit-body" id="pred-edit-body">
                        <div class="edit-user-strip">
                            <div class="edit-user-avatar" id="pred-edit-avatar">—</div>
                            <div>
                                <div class="edit-user-name" id="pred-edit-name">—</div>
                                <div class="edit-user-rank" id="pred-edit-rank">—</div>
                            </div>
                        </div>

                        <div class="form-section">Current Values</div>
                        <div class="current-val">
                            <span class="cv-label">Avg Accuracy</span>
                            <span class="cv-value" id="pred-cur-acc">—</span>
                        </div>
                        <div class="current-val">
                            <span class="cv-label">Total Bonus</span>
                            <span class="cv-value" id="pred-cur-bonus">—</span>
                        </div>

                        <div class="form-section">New Values</div>
                        <div class="field-row">
                            <div class="field-group">
                                <label class="field-label">Manual Accuracy (%)</label>
                                <input type="number" id="pred-new-acc" class="field-input" step="0.01" min="0" max="100" placeholder="e.g. 87.50">
                                <div class="field-hint">Sets ALL evaluated predictions to this value</div>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Total Bonus</label>
                                <input type="number" id="pred-new-bonus" class="field-input" step="0.01" min="0" placeholder="e.g. 250.00">
                                <div class="field-hint">Overrides all bonus rows for this user</div>
                            </div>
                        </div>
                    </div>
                    <div class="edit-footer" id="pred-edit-footer" style="display:none">
                        <button class="btn-cancel" onclick="clearEdit('pred')">Cancel</button>
                        <button class="btn-save" id="pred-save-btn" onclick="saveEdit('pred')">Save Changes</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="lb-card">
                    <div class="lb-card-header">
                        <span class="lb-card-title">Story Prediction Board</span>
                        <span class="lb-card-count" id="pred-table-count"><?=$total_pred_players?> players</span>
                    </div>
                    <?php if(empty($pred_rows)):?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                            <p>No evaluated predictions yet.</p>
                        </div>
                    <?php else:?>
                    <div style="overflow-x:auto">
                    <table class="lb-table" id="pred-table">
                        <thead><tr>
                            <th style="width:44px">Rank</th>
                            <th>Player</th>
                            <th>Predictions</th>
                            <th>Avg Accuracy</th>
                            <th>Total Bonus</th>
                            <th>Likes</th>
                            <th>Edit</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach($pred_rows as $i=>$r):
                            $rank=$i+1;
                            $rc=$rank===1?'gold':($rank===2?'silver':($rank===3?'bronze':'rank-n'));
                            $init=strtoupper(substr($r['first_name']??$r['user_name'],0,1).substr($r['last_name']??'',0,1));
                            $js=htmlspecialchars(json_encode([
                                'uid'       =>(int)$r['user_id'],
                                'uname'     =>$r['user_name'],
                                'fname'     =>trim(($r['first_name']??'').' '.($r['last_name']??'')),
                                'initials'  =>$init,
                                'rank'      =>$rank,
                                'avg_acc'   =>$r['avg_accuracy'],
                                'total_bonus'=>$r['total_bonus'],
                            ]),ENT_QUOTES);
                        ?>
                        <tr id="pred-row-<?=$r['user_id']?>">
                            <td><div class="rank-cell <?=$rc?>">#<?=$rank?></div></td>
                            <td>
                                <div class="user-cell">
                                    <div class="lb-avatar <?=$rc?>"><?=htmlspecialchars($init)?></div>
                                    <div>
                                        <div class="lb-username">@<?=htmlspecialchars($r['user_name'])?></div>
                                        <div class="lb-fullname"><?=htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??'')))?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="mono-val"><?=$r['total_predictions']?></td>
                            <td><span class="score-val primary"><?=number_format((float)$r['avg_accuracy'],2)?>%</span></td>
                            <td class="mono-val"><?=number_format((float)$r['total_bonus'],2)?></td>
                            <td class="mono-val"><?=$r['total_likes']?></td>
                            <td>
                                <button class="lb-edit-btn" id="pred-edit-btn-<?=$r['user_id']?>"
                                    onclick='openEdit("pred", <?=$js?>)'>
                                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif;?>
                </div>
            </div>
        </div>

        <!-- ═══ SECTION: FLASH WORDS ═══ -->
        <div id="section-flash" class="hidden fade-up" style="animation-delay:.13s">
            <div class="main-grid">
                <div class="edit-card">
                    <div class="edit-card-header">
                        <span class="edit-card-title">Edit Flash Words Scores</span>
                        <span class="edit-mode-badge idle" id="flash-edit-badge">SELECT A PLAYER</span>
                    </div>
                    <div class="edit-empty" id="flash-edit-empty">
                        <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <p>Click Edit next to a player to override their Flash Words scores.</p>
                    </div>
                    <div class="edit-body" id="flash-edit-body">
                        <div class="edit-user-strip">
                            <div class="edit-user-avatar" id="flash-edit-avatar">—</div>
                            <div>
                                <div class="edit-user-name" id="flash-edit-name">—</div>
                                <div class="edit-user-rank" id="flash-edit-rank">—</div>
                            </div>
                        </div>
                        <div class="form-section">Current Values</div>
                        <div class="current-val">
                            <span class="cv-label">Total Score</span>
                            <span class="cv-value" id="flash-cur-score">—</span>
                        </div>
                        <div class="current-val">
                            <span class="cv-label">Best WPM</span>
                            <span class="cv-value" id="flash-cur-wpm">—</span>
                        </div>
                        <div class="form-section">New Values</div>
                        <div class="field-row">
                            <div class="field-group">
                                <label class="field-label">Total Score</label>
                                <input type="number" id="flash-new-score" class="field-input" step="1" min="0" placeholder="e.g. 4500">
                                <div class="field-hint">Overrides SUM(score)</div>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Best WPM</label>
                                <input type="number" id="flash-new-wpm" class="field-input" step="1" min="0" placeholder="e.g. 180">
                                <div class="field-hint">Overrides MAX(best_wpm)</div>
                            </div>
                        </div>
                    </div>
                    <div class="edit-footer" id="flash-edit-footer" style="display:none">
                        <button class="btn-cancel" onclick="clearEdit('flash')">Cancel</button>
                        <button class="btn-save" id="flash-save-btn" onclick="saveEdit('flash')">Save Changes</button>
                    </div>
                </div>

                <div class="lb-card">
                    <div class="lb-card-header">
                        <span class="lb-card-title">Flash Words Board</span>
                        <span class="lb-card-count"><?=$total_flash_players?> players</span>
                    </div>
                    <?php if(empty($flash_rows)):?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <p>No Flash Words scores yet.</p>
                        </div>
                    <?php else:?>
                    <div style="overflow-x:auto">
                    <table class="lb-table">
                        <thead><tr>
                            <th style="width:44px">Rank</th>
                            <th>Player</th>
                            <th>Best WPM</th>
                            <th>Total Correct</th>
                            <th>Attempts</th>
                            <th>Total Score</th>
                            <th>Edit</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach($flash_rows as $i=>$r):
                            $rank=$i+1;
                            $rc=$rank===1?'gold':($rank===2?'silver':($rank===3?'bronze':'rank-n'));
                            $init=strtoupper(substr($r['first_name']??$r['user_name'],0,1).substr($r['last_name']??'',0,1));
                            $js=htmlspecialchars(json_encode([
                                'uid'        =>(int)$r['user_id'],
                                'uname'      =>$r['user_name'],
                                'fname'      =>trim(($r['first_name']??'').' '.($r['last_name']??'')),
                                'initials'   =>$init,
                                'rank'       =>$rank,
                                'total_score'=>$r['total_score'],
                                'best_wpm'   =>$r['best_wpm'],
                            ]),ENT_QUOTES);
                        ?>
                        <tr id="flash-row-<?=$r['user_id']?>">
                            <td><div class="rank-cell <?=$rc?>">#<?=$rank?></div></td>
                            <td>
                                <div class="user-cell">
                                    <div class="lb-avatar <?=$rc?>"><?=htmlspecialchars($init)?></div>
                                    <div>
                                        <div class="lb-username">@<?=htmlspecialchars($r['user_name'])?></div>
                                        <div class="lb-fullname"><?=htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??'')))?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="score-val secondary"><?=$r['best_wpm']?> WPM</span></td>
                            <td class="mono-val"><?=number_format($r['total_correct'])?></td>
                            <td class="mono-val"><?=number_format($r['total_attempts'])?></td>
                            <td><span class="score-val primary"><?=number_format($r['total_score'])?></span></td>
                            <td>
                                <button class="lb-edit-btn" onclick='openEdit("flash", <?=$js?>)'>
                                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif;?>
                </div>
            </div>
        </div>

        <!-- ═══ SECTION: STORY SCRAMBLE ═══ -->
        <div id="section-scramble" class="hidden fade-up" style="animation-delay:.13s">
            <div class="main-grid">
                <div class="edit-card">
                    <div class="edit-card-header">
                        <span class="edit-card-title">Edit Scramble Scores</span>
                        <span class="edit-mode-badge idle" id="scramble-edit-badge">SELECT A PLAYER</span>
                    </div>
                    <div class="edit-empty" id="scramble-edit-empty">
                        <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                        <p>Click Edit next to a player to override their Story Scramble scores.</p>
                    </div>
                    <div class="edit-body" id="scramble-edit-body">
                        <div class="edit-user-strip">
                            <div class="edit-user-avatar" id="scramble-edit-avatar">—</div>
                            <div>
                                <div class="edit-user-name" id="scramble-edit-name">—</div>
                                <div class="edit-user-rank" id="scramble-edit-rank">—</div>
                            </div>
                        </div>
                        <div class="form-section">Current Values</div>
                        <div class="current-val">
                            <span class="cv-label">Total Score</span>
                            <span class="cv-value" id="scramble-cur-score">—</span>
                        </div>
                        <div class="current-val">
                            <span class="cv-label">Correct Pairs</span>
                            <span class="cv-value" id="scramble-cur-pairs">—</span>
                        </div>
                        <div class="form-section">New Values</div>
                        <div class="field-row">
                            <div class="field-group">
                                <label class="field-label">Total Score</label>
                                <input type="number" id="scramble-new-score" class="field-input" step="1" min="0" placeholder="e.g. 2400">
                                <div class="field-hint">Overrides SUM(score)</div>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Correct Pairs</label>
                                <input type="number" id="scramble-new-pairs" class="field-input" step="1" min="0" placeholder="e.g. 120">
                                <div class="field-hint">Overrides SUM(correct_pairs)</div>
                            </div>
                        </div>
                    </div>
                    <div class="edit-footer" id="scramble-edit-footer" style="display:none">
                        <button class="btn-cancel" onclick="clearEdit('scramble')">Cancel</button>
                        <button class="btn-save" id="scramble-save-btn" onclick="saveEdit('scramble')">Save Changes</button>
                    </div>
                </div>

                <div class="lb-card">
                    <div class="lb-card-header">
                        <span class="lb-card-title">Story Scramble Board</span>
                        <span class="lb-card-count"><?=$total_scramble_players?> players</span>
                    </div>
                    <?php if(empty($scramble_rows)):?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg>
                            <p>No Story Scramble scores yet.</p>
                        </div>
                    <?php else:?>
                    <div style="overflow-x:auto">
                    <table class="lb-table">
                        <thead><tr>
                            <th style="width:44px">Rank</th>
                            <th>Player</th>
                            <th>Games Played</th>
                            <th>Correct Pairs</th>
                            <th>Total Score</th>
                            <th>Edit</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach($scramble_rows as $i=>$r):
                            $rank=$i+1;
                            $rc=$rank===1?'gold':($rank===2?'silver':($rank===3?'bronze':'rank-n'));
                            $init=strtoupper(substr($r['first_name']??$r['user_name'],0,1).substr($r['last_name']??'',0,1));
                            $js=htmlspecialchars(json_encode([
                                'uid'           =>(int)$r['user_id'],
                                'uname'         =>$r['user_name'],
                                'fname'         =>trim(($r['first_name']??'').' '.($r['last_name']??'')),
                                'initials'      =>$init,
                                'rank'          =>$rank,
                                'total_score'   =>$r['total_score'],
                                'correct_pairs' =>$r['total_correct_pairs'],
                            ]),ENT_QUOTES);
                        ?>
                        <tr id="scramble-row-<?=$r['user_id']?>">
                            <td><div class="rank-cell <?=$rc?>">#<?=$rank?></div></td>
                            <td>
                                <div class="user-cell">
                                    <div class="lb-avatar <?=$rc?>"><?=htmlspecialchars($init)?></div>
                                    <div>
                                        <div class="lb-username">@<?=htmlspecialchars($r['user_name'])?></div>
                                        <div class="lb-fullname"><?=htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??'')))?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="mono-val"><?=$r['games_played']?></td>
                            <td><span class="score-val secondary"><?=number_format($r['total_correct_pairs'])?></span></td>
                            <td><span class="score-val primary"><?=number_format($r['total_score'])?></span></td>
                            <td>
                                <button class="lb-edit-btn" onclick='openEdit("scramble", <?=$js?>)'>
                                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif;?>
                </div>
            </div>
        </div>

        <!-- ═══ RECENT ACTIVITY ═══ -->
        <div class="activity-card fade-up" style="animation-delay:.18s">
            <div class="activity-header">
                <span class="activity-title">Recent Leaderboard Changes</span>
            </div>
            <?php
            $activity = [];
            try {
                $activity = $pdo->query("
                    SELECT action_text, created_at
                    FROM admin_activity_log
                    WHERE action_text LIKE '%leaderboard%' OR action_text LIKE '%score%' OR action_text LIKE '%overridden%'
                    ORDER BY created_at DESC
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);
            } catch(Exception $e){}
            ?>
            <?php if(empty($activity)):?>
                <div class="activity-empty">No leaderboard changes recorded yet.</div>
            <?php else:?>
                <?php foreach($activity as $a):
                    $ago = time()-strtotime($a['created_at']);
                    $ts  = $ago<60?'Just now':($ago<3600?round($ago/60).'m ago':($ago<86400?round($ago/3600).'h ago':date('M j',strtotime($a['created_at']))));
                ?>
                <div class="activity-row">
                    <span class="activity-dot"></span>
                    <span class="activity-text"><?=htmlspecialchars($a['action_text'])?></span>
                    <span class="activity-time"><?=$ts?></span>
                </div>
                <?php endforeach;?>
            <?php endif;?>
        </div>

    </main>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// ── Sidebar ────────────────────────────────────────────────────
const sidebar=document.getElementById('sidebar'),mainWrapper=document.getElementById('mainWrapper'),hamburger=document.getElementById('hamburger');
hamburger.addEventListener('click',()=>{const c=sidebar.classList.toggle('collapsed');mainWrapper.classList.toggle('expanded',c);hamburger.classList.toggle('active',c);localStorage.setItem('sv_sidebar',c?'1':'0')});
if(localStorage.getItem('sv_sidebar')==='1'){sidebar.classList.add('collapsed');mainWrapper.classList.add('expanded');hamburger.classList.add('active')}

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg,type='default'){
    const tc=document.getElementById('toastContainer'),t=document.createElement('div');
    t.className='toast';t.style.borderLeftColor=type==='success'?'var(--success)':type==='error'?'var(--danger)':'var(--gold)';
    t.textContent=msg;tc.appendChild(t);
    setTimeout(()=>{t.classList.add('fade-out');setTimeout(()=>t.remove(),280)},3500);
}

// ── Tab switching ──────────────────────────────────────────────
const tabs=['overall','pred','flash','scramble'];
function switchTab(tab){
    tabs.forEach(t=>{
        document.getElementById('tab-'+t).classList.toggle('active',t===tab);
        document.getElementById('section-'+t).classList.toggle('hidden',t!==tab);
    });
    // Live search filter
    const q=document.getElementById('tableSearch').value.toLowerCase().trim();
    if(q) filterTable(tab,q);
}

// ── Live search ────────────────────────────────────────────────
document.getElementById('tableSearch').addEventListener('input',function(){
    const q=this.value.toLowerCase().trim();
    const activeTab=tabs.find(t=>!document.getElementById('section-'+t).classList.contains('hidden'))||'overall';
    filterTable(activeTab,q);
});

function filterTable(tab,q){
    const section=document.getElementById('section-'+tab);
    if(!section) return;
    section.querySelectorAll('tbody tr').forEach(row=>{
        const text=row.textContent.toLowerCase();
        row.style.display=!q||text.includes(q)?'':'none';
    });
}

// ── Active edit state ──────────────────────────────────────────
let activeEdit={board:null, uid:null};

function openEdit(board, u){
    // Reset previous
    if(activeEdit.board && activeEdit.uid){
        const prev=document.getElementById(activeEdit.board+'-edit-btn-'+activeEdit.uid);
        if(prev){ prev.classList.remove('active'); prev.textContent=''; prev.innerHTML='<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit'; }
        const prevRow=document.getElementById(activeEdit.board+'-row-'+activeEdit.uid);
        if(prevRow) prevRow.classList.remove('editing-row');
    }
    activeEdit={board, uid:u.uid};

    // Highlight row and button
    const row=document.getElementById(board+'-row-'+u.uid);
    if(row) row.classList.add('editing-row');
    const btn=document.getElementById(board+'-edit-btn-'+u.uid);
    if(btn){ btn.classList.add('active'); btn.innerHTML='<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Editing'; }

    // Populate form
    const prefix=board+'-';
    document.getElementById(prefix+'edit-badge').textContent=`#${u.rank} @${u.uname}`;
    document.getElementById(prefix+'edit-badge').className='edit-mode-badge active';
    document.getElementById(prefix+'edit-avatar').textContent=u.initials;
    document.getElementById(prefix+'edit-name').textContent=`@${u.uname}`;
    document.getElementById(prefix+'edit-rank').textContent=`Rank #${u.rank} — ${u.fname||''}`;

    // Hide empty, show body
    document.getElementById(prefix+'edit-empty').style.display='none';
    document.getElementById(prefix+'edit-body').classList.add('show');
    document.getElementById(prefix+'edit-footer').style.display='flex';

    // Populate current values + clear inputs
    if(board==='pred'){
        document.getElementById('pred-cur-acc').textContent   = u.avg_acc!==null ? u.avg_acc+'%' : '—';
        document.getElementById('pred-cur-bonus').textContent = u.total_bonus!==null ? u.total_bonus : '—';
        document.getElementById('pred-new-acc').value   = '';
        document.getElementById('pred-new-bonus').value = '';
    } else if(board==='flash'){
        document.getElementById('flash-cur-score').textContent = u.total_score!==null ? u.total_score : '—';
        document.getElementById('flash-cur-wpm').textContent   = u.best_wpm!==null ? u.best_wpm+' WPM' : '—';
        document.getElementById('flash-new-score').value = '';
        document.getElementById('flash-new-wpm').value   = '';
    } else if(board==='scramble'){
        document.getElementById('scramble-cur-score').textContent = u.total_score!==null ? u.total_score : '—';
        document.getElementById('scramble-cur-pairs').textContent = u.correct_pairs!==null ? u.correct_pairs : '—';
        document.getElementById('scramble-new-score').value = '';
        document.getElementById('scramble-new-pairs').value = '';
    }

    // Scroll form into view
    document.getElementById(board+'-edit-card').scrollIntoView({behavior:'smooth',block:'start'});
}

function clearEdit(board){
    if(activeEdit.uid){
        const row=document.getElementById(board+'-row-'+activeEdit.uid);
        if(row) row.classList.remove('editing-row');
        const btn=document.getElementById(board+'-edit-btn-'+activeEdit.uid);
        if(btn){ btn.classList.remove('active'); btn.innerHTML='<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit'; }
    }
    activeEdit={board:null,uid:null};
    const prefix=board+'-';
    document.getElementById(prefix+'edit-badge').textContent='SELECT A PLAYER';
    document.getElementById(prefix+'edit-badge').className='edit-mode-badge idle';
    document.getElementById(prefix+'edit-empty').style.display='';
    document.getElementById(prefix+'edit-body').classList.remove('show');
    document.getElementById(prefix+'edit-footer').style.display='none';
}

// ── Save edit via AJAX ─────────────────────────────────────────
function saveEdit(board){
    if(!activeEdit.uid){showToast('No player selected','error');return}
    const uid=activeEdit.uid;
    const saveBtn=document.getElementById(board+'-save-btn');
    saveBtn.textContent='Saving...';saveBtn.disabled=true;

    const fd=new FormData();
    fd.append('id',uid); // alias, backend uses user_id
    fd.append('user_id',uid);

    if(board==='pred'){
        const acc  = document.getElementById('pred-new-acc').value.trim();
        const bonus= document.getElementById('pred-new-bonus').value.trim();
        if(!acc && !bonus){showToast('Enter at least one value to update','error');saveBtn.textContent='Save Changes';saveBtn.disabled=false;return;}
        fd.append('action','edit_pred_score');
        fd.append('manual_accuracy', acc);
        fd.append('bonus_amount',    bonus);
    } else if(board==='flash'){
        const score= document.getElementById('flash-new-score').value.trim();
        const wpm  = document.getElementById('flash-new-wpm').value.trim();
        if(!score && !wpm){showToast('Enter at least one value to update','error');saveBtn.textContent='Save Changes';saveBtn.disabled=false;return;}
        fd.append('action','edit_flash_score');
        fd.append('total_score', score);
        fd.append('best_wpm',    wpm);
    } else if(board==='scramble'){
        const score = document.getElementById('scramble-new-score').value.trim();
        const pairs = document.getElementById('scramble-new-pairs').value.trim();
        if(!score && !pairs){showToast('Enter at least one value to update','error');saveBtn.textContent='Save Changes';saveBtn.disabled=false;return;}
        fd.append('action','edit_scramble_score');
        fd.append('total_score',    score);
        fd.append('correct_pairs',  pairs);
    }

    fetch('admin_ajax.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(d=>{
            if(d.success){
                showToast('Scores updated — reload to see new ranking','success');
                clearEdit(board);
            } else {
                showToast(d.msg||'Save failed','error');
            }
        })
        .catch(()=>showToast('Request failed — check admin_ajax.php','error'))
        .finally(()=>{saveBtn.textContent='Save Changes';saveBtn.disabled=false;});
}
</script>
</body>
</html>
