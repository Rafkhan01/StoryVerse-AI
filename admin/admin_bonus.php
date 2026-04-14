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

$today = date('l, F j, Y');

// ── Handle AJAX ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    $action = $_POST['ajax_action'];

    // Save accuracy (AJAX, no reload)
    if ($action === 'save_accuracy') {
        $prediction_id = (int)$_POST['prediction_id'];
        $accuracy      = (float)$_POST['accuracy'];
        try {
            $is_bonus_eligible = $accuracy >= 40 ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE predictions SET manual_accuracy = ?, is_bonus_eligible = ? WHERE prediction_id = ?");
            $stmt->execute([$accuracy, $is_bonus_eligible, $prediction_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // Save bonus amount (AJAX)
    if ($action === 'save_bonus') {
        $prediction_id = (int)$_POST['prediction_id'];
        $bonus_amount  = (float)$_POST['bonus_amount'];
        $user_id       = (int)$_POST['user_id'];
        $story_id      = (int)$_POST['story_id'];
        $part_no       = (int)$_POST['part_no'];
        try {
            $pdo->beginTransaction();
            // Upsert bonus row
            $check = $pdo->prepare("SELECT COUNT(*) FROM bonus WHERE prediction_id = ?");
            $check->execute([$prediction_id]);
            if ($check->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE bonus SET bonus_amount = ? WHERE prediction_id = ?");
                $stmt->execute([$bonus_amount, $prediction_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO bonus (prediction_id, user_id, story_id, part_no, bonus_amount, awarded_at) VALUES (?,?,?,?,?,NOW())");
                $stmt->execute([$prediction_id, $user_id, $story_id, $part_no, $bonus_amount]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // Delete prediction + bonus
    if ($action === 'delete_prediction') {
        $prediction_id = (int)$_POST['prediction_id'];
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM bonus WHERE prediction_id = ?")->execute([$prediction_id]);
            $pdo->prepare("DELETE FROM predictions WHERE prediction_id = ?")->execute([$prediction_id]);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'msg' => 'Unknown action']);
    exit;
}

// ── Fetch all stories that have predictions ───────────────────────
$all_stories = [];
try {
    $all_stories = $pdo->query("
        SELECT DISTINCT s.story_id, s.title
        FROM stories s
        INNER JOIN predictions p ON s.story_id = p.story_id
        ORDER BY s.title ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── Filter controls ───────────────────────────────────────────────
$story_filter = isset($_GET['story_id']) ? (int)$_GET['story_id'] : 0;
$sort_filter  = isset($_GET['sort']) ? $_GET['sort'] : 'accuracy';

// ── Fetch story sections with predictions ─────────────────────────
$sections = [];
try {
    $where_story = $story_filter ? "AND s.story_id = $story_filter" : '';

    $sort_sql = match($sort_filter) {
        'liked'    => 'ORDER BY p.created_at DESC',   // likes sub-sort applied per prediction
        'newest'   => 'ORDER BY p.created_at DESC',
        default    => 'ORDER BY p.manual_accuracy DESC NULLS LAST, p.created_at DESC',
    };

    // Build per-story sections
    $story_ids_sql = $story_filter
        ? [$story_filter]
        : array_column($pdo->query("SELECT DISTINCT story_id FROM predictions ORDER BY story_id")->fetchAll(), 'story_id');

    foreach ($story_ids_sql as $sid) {
        $sid = (int)$sid;

        // Story meta
        $story_row = $pdo->prepare("SELECT story_id, title FROM stories WHERE story_id = ?");
        $story_row->execute([$sid]);
        $story_meta = $story_row->fetch(PDO::FETCH_ASSOC);
        if (!$story_meta) continue;

        // Build predictions query with sort
        $like_join  = $sort_filter === 'liked' ? "LEFT JOIN (SELECT prediction_id, COUNT(*) AS lc FROM likes GROUP BY prediction_id) pl ON p.prediction_id = pl.prediction_id" : '';
        $sort_clause = match($sort_filter) {
            'liked'   => 'ORDER BY COALESCE(pl.lc, 0) DESC, p.created_at DESC',
            'newest'  => 'ORDER BY p.created_at DESC',
            default   => 'ORDER BY p.manual_accuracy DESC, p.created_at DESC',
        };

        $pred_sql = "
            SELECT
                p.prediction_id,
                p.prediction_text,
                p.manual_accuracy,
                p.prediction_part_no,
                p.created_at,
                u.user_id,
                u.user_name,
                u.first_name,
                u.last_name,
                b.bonus_amount,
                b.bonus_id,
                COALESCE(lk.lc, 0) AS like_count
            FROM predictions p
            INNER JOIN users u ON p.user_id = u.user_id
            LEFT JOIN bonus b ON b.prediction_id = p.prediction_id
            LEFT JOIN (SELECT prediction_id, COUNT(*) AS lc FROM likes GROUP BY prediction_id) lk ON lk.prediction_id = p.prediction_id
            $like_join
            WHERE p.story_id = ?
            $sort_clause
        ";
        $ps = $pdo->prepare($pred_sql);
        $ps->execute([$sid]);
        $predictions = $ps->fetchAll(PDO::FETCH_ASSOC);
        if (empty($predictions)) continue;

        $sections[] = [
            'story'       => $story_meta,
            'predictions' => $predictions,
        ];
    }
} catch (Exception $e) {
    error_log("admin_bonus fetch error: " . $e->getMessage());
}

// ── Summary stats ─────────────────────────────────────────────────
$total_preds = 0; $verified = 0; $bonused = 0;
try {
    $total_preds = (int)$pdo->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
    $verified    = (int)$pdo->query("SELECT COUNT(*) FROM predictions WHERE manual_accuracy IS NOT NULL")->fetchColumn();
    $bonused     = (int)$pdo->query("SELECT COUNT(*) FROM bonus")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bonus Supply — StoryVerse Admin</title>
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

/* ── Page ── */
.page-content{padding:28px 28px 60px;flex:1}
.page-header{margin-bottom:28px}
.page-header h1{font-family:var(--font-display);font-size:22px;color:var(--text-primary);margin-bottom:4px}
.page-header p{font-size:14px;color:var(--text-secondary)}

/* ── Stat strip ── */
.stat-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px}
.stat-chip{background:var(--card-bg);border:1px solid var(--card-border);border-radius:10px;box-shadow:var(--card-shadow);padding:16px 18px;display:flex;flex-direction:column;gap:4px}
.stat-chip-label{font-family:var(--font-mono);font-size:9px;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted)}
.stat-chip-value{font-family:var(--font-body);font-size:26px;font-weight:700;color:var(--text-primary);line-height:1}
.stat-chip-sub{font-size:11px;color:var(--text-muted);font-weight:500}

/* ── Controls bar ── */
.controls-bar{display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap}
.ctrl-select{background:var(--card-bg);border:1px solid var(--border);border-radius:8px;padding:8px 14px;font-family:var(--font-body);font-size:13px;color:var(--text-primary);outline:none;cursor:pointer;transition:border-color 0.18s}
.ctrl-select:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.sort-tabs{display:flex;gap:4px;background:var(--card-bg);border:1px solid var(--border);border-radius:9px;padding:3px}
.sort-tab{padding:6px 14px;border-radius:6px;font-family:var(--font-body);font-size:12px;font-weight:600;color:var(--text-secondary);cursor:pointer;text-decoration:none;transition:all 0.15s;white-space:nowrap}
.sort-tab.active{background:var(--gold);color:#fff}
.sort-tab:hover:not(.active){background:var(--divider);color:var(--text-primary)}
.ctrl-label{font-family:var(--font-mono);font-size:10px;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted)}

/* ── Section label ── */
.section-label{font-family:var(--font-mono);font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:16px;margin-top:10px;display:flex;align-items:center;gap:10px}
.section-label::after{content:'';flex:1;height:1px;background:var(--border)}

/* ── Story section card ── */
.story-section{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--card-radius);box-shadow:var(--card-shadow);margin-bottom:22px;overflow:hidden}
.story-section-header{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-bottom:1px solid var(--divider);background:linear-gradient(90deg,rgba(245,166,35,0.04) 0%,transparent 100%)}
.story-title{font-family:var(--font-display);font-size:13px;color:var(--text-primary);display:flex;align-items:center;gap:10px}
.story-badge{font-family:var(--font-mono);font-size:9px;letter-spacing:0.06em;padding:3px 8px;border-radius:4px;background:rgba(245,166,35,0.12);border:1px solid rgba(245,166,35,0.28);color:var(--gold)}
.section-header-right{display:flex;align-items:center;gap:10px}
.pred-count-tag{font-family:var(--font-mono);font-size:10px;color:var(--text-muted)}

/* ── Table ── */
.pred-table-wrap{overflow-x:auto}
.pred-table{width:100%;border-collapse:collapse;min-width:900px}
.pred-table thead{background:var(--divider)}
.pred-table th{padding:10px 14px;text-align:left;font-family:var(--font-mono);font-size:9px;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);white-space:nowrap;border-bottom:1px solid var(--border)}
.pred-table td{padding:12px 14px;border-bottom:1px solid var(--divider);vertical-align:top;font-size:13px}
.pred-table tbody tr:last-child td{border-bottom:none}
.pred-table tbody tr:hover{background:rgba(245,166,35,0.02)}

/* Cells */
.user-cell .uname{font-weight:700;color:var(--text-primary);font-size:13px}
.user-cell .handle{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:1px}
.author-cell{font-weight:600;font-size:13px;color:var(--text-secondary)}
.pred-text{font-size:12px;color:var(--text-secondary);line-height:1.55;max-width:220px;word-break:break-word}
.pred-date{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:4px;white-space:nowrap}
.upcoming-text{font-size:12px;color:var(--text-secondary);line-height:1.55;max-width:180px;word-break:break-word;font-style:italic}
.no-upcoming{font-family:var(--font-mono);font-size:10px;color:var(--text-muted)}

/* Editable fields */
.field-wrap{display:flex;flex-direction:column;gap:4px}
.edit-input{width:90px;padding:6px 10px;border:1px solid var(--border);border-radius:7px;font-family:var(--font-body);font-size:13px;color:var(--text-primary);background:var(--bg);outline:none;transition:border-color 0.18s,box-shadow 0.18s}
.edit-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.edit-input.accuracy-inp{border-color:rgba(16,185,129,0.4);background:#F0FDF9}
.edit-input.accuracy-inp:focus{border-color:var(--success);box-shadow:0 0 0 3px rgba(16,185,129,0.12)}
.edit-input.bonus-inp{border-color:rgba(245,166,35,0.4);background:#FFFBF0}
.edit-input.bonus-inp:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-status{font-family:var(--font-mono);font-size:9px;color:var(--text-muted)}
.field-status.saved{color:var(--success)}

/* Save / Delete buttons */
.btn-save{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:7px;border:none;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:#fff;font-family:var(--font-body);font-size:12px;font-weight:700;cursor:pointer;transition:opacity 0.18s,transform 0.18s;white-space:nowrap}
.btn-save:hover{opacity:0.88;transform:translateY(-1px)}
.btn-save:disabled{opacity:0.5;cursor:not-allowed;transform:none}
.btn-save svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.btn-delete{display:inline-flex;align-items:center;gap:5px;padding:7px 11px;border-radius:7px;border:1px solid rgba(239,68,68,0.3);background:rgba(239,68,68,0.06);color:var(--danger);font-family:var(--font-body);font-size:12px;font-weight:700;cursor:pointer;transition:all 0.15s;white-space:nowrap}
.btn-delete:hover{background:rgba(239,68,68,0.12);border-color:rgba(239,68,68,0.5)}
.btn-delete svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.actions-cell{display:flex;flex-direction:column;gap:6px;align-items:flex-start}

/* Expand row */
.expand-row td{padding:0;border-bottom:none}
.expand-btn{width:100%;padding:10px;border:none;background:var(--divider);color:var(--text-muted);font-family:var(--font-mono);font-size:10px;letter-spacing:0.06em;cursor:pointer;transition:background 0.15s,color 0.15s;display:flex;align-items:center;justify-content:center;gap:7px}
.expand-btn:hover{background:rgba(245,166,35,0.08);color:var(--gold)}
.expand-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:transform 0.2s}
.expand-btn.open svg{transform:rotate(180deg)}

/* Hidden rows */
.pred-row.hidden-row{display:none}

/* Like cell */
.like-cell{display:flex;align-items:center;gap:5px;font-family:var(--font-mono);font-size:11px;color:var(--text-muted)}
.like-cell svg{width:13px;height:13px;stroke:#EF4444;fill:rgba(239,68,68,0.15);stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}

/* Empty state */
.empty-state{text-align:center;padding:60px 24px;color:var(--text-muted)}
.empty-state svg{width:48px;height:48px;stroke:var(--border);fill:none;stroke-width:1.2;margin-bottom:16px}
.empty-state p{font-size:14px}

/* ── Toast ── */
.toast-container{position:fixed;bottom:28px;right:28px;z-index:999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;padding:12px 18px;background:#1A1D2E;color:#fff;border-radius:10px;font-family:var(--font-body);font-size:13.5px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,0.2);animation:toast-in 0.3s cubic-bezier(0.34,1.56,0.64,1) forwards;pointer-events:all;border-left:3px solid var(--gold)}
@keyframes toast-in{from{opacity:0;transform:translateY(16px) scale(0.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.toast.fade-out{animation:toast-out 0.25s ease forwards}
@keyframes toast-out{to{opacity:0;transform:translateY(8px) scale(0.97)}}

.fade-up{opacity:0;transform:translateY(14px);animation:fade-up-in 0.38s ease forwards}
@keyframes fade-up-in{to{opacity:1;transform:translateY(0)}}

/* Bonus warning badge */
.bonus-warning{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;background:rgba(245,166,35,0.1);border:1px solid rgba(245,166,35,0.3);color:var(--gold-dark);font-family:var(--font-mono);font-size:10px;font-weight:700;letter-spacing:0.04em}
.bonus-warning svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}

@media(max-width:900px){.stat-strip{grid-template-columns:1fr 1fr}.page-content{padding:16px 14px 40px}.topbar-date{display:none}}
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
        <a href="admin_bonus.php" class="nav-item active">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/><path d="M8 14h.01M12 14h.01M16 14h.01"/><path d="M9 9h6M9 12h3"/></svg>
            <span class="nav-label">Bonus Supply</span><span class="tooltip">Bonus Supply</span>
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
            <h1>Bonus Supply</h1>
            <p>Review all story predictions, verify manual accuracy, and award bonus coins to top predictors.</p>
        </div>

        <!-- Stat strip -->
        <div class="stat-strip fade-up" style="animation-delay:0.05s">
            <div class="stat-chip">
                <span class="stat-chip-label">Total Predictions</span>
                <span class="stat-chip-value"><?= number_format($total_preds) ?></span>
                <span class="stat-chip-sub">Across all stories</span>
            </div>
            <div class="stat-chip">
                <span class="stat-chip-label">Accuracy Verified</span>
                <span class="stat-chip-value"><?= number_format($verified) ?></span>
                <span class="stat-chip-sub"><?= $total_preds ? round(($verified/$total_preds)*100) : 0 ?>% coverage</span>
            </div>
            <div class="stat-chip">
                <span class="stat-chip-label">Bonuses Awarded</span>
                <span class="stat-chip-value"><?= number_format($bonused) ?></span>
                <span class="stat-chip-sub">Across all predictions</span>
            </div>
        </div>

        <!-- Controls bar -->
        <div class="controls-bar fade-up" style="animation-delay:0.08s">
            <span class="ctrl-label">Story</span>
            <select class="ctrl-select" id="storyFilter" onchange="applyFilters()">
                <option value="0">All Stories</option>
                <?php foreach ($all_stories as $s): ?>
                <option value="<?= $s['story_id'] ?>" <?= $story_filter == $s['story_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['title']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <span class="ctrl-label" style="margin-left:8px">Sort</span>
            <div class="sort-tabs">
                <a href="?story_id=<?= $story_filter ?>&sort=accuracy" class="sort-tab <?= $sort_filter === 'accuracy' ? 'active' : '' ?>">By Accuracy</a>
                <a href="?story_id=<?= $story_filter ?>&sort=liked"    class="sort-tab <?= $sort_filter === 'liked'    ? 'active' : '' ?>">Most Liked</a>
                <a href="?story_id=<?= $story_filter ?>&sort=newest"   class="sort-tab <?= $sort_filter === 'newest'   ? 'active' : '' ?>">Newest</a>
            </div>

            <div class="bonus-warning" id="bonusWarningBadge" style="display:none;margin-left:auto">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span id="bonusWarnText">0 bonuses set</span>
            </div>
        </div>

        <!-- Sections -->
        <?php if (empty($sections)): ?>
        <div class="story-section fade-up">
            <div class="empty-state">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5-2 4-2 4 2 4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                <p>No predictions found.</p>
            </div>
        </div>
        <?php else: ?>

        <?php foreach ($sections as $si => $sec):
            $preds       = $sec['predictions'];
            $story       = $sec['story'];
            $total_count = count($preds);
            $initial     = array_slice($preds, 0, 3);
            $extra       = array_slice($preds, 3);
            $section_key = 'story_' . $story['story_id'];
        ?>
        <div class="story-section fade-up" style="animation-delay:<?= 0.04 * $si ?>s" id="section_<?= $section_key ?>">
            <div class="story-section-header">
                <div class="story-title">
                    <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:var(--gold);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <?= htmlspecialchars($story['title']) ?>
                    <span class="story-badge">ID #<?= $story['story_id'] ?></span>
                </div>
                <div class="section-header-right">
                    <span class="pred-count-tag"><?= $total_count ?> prediction<?= $total_count !== 1 ? 's' : '' ?></span>
                </div>
            </div>

            <div class="pred-table-wrap">
                <table class="pred-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Author</th>
                            <th>Prediction</th>
                            <th>Upcoming Content</th>
                            <th>Likes</th>
                            <th>Manual Accuracy</th>
                            <th>Bonus Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_<?= $section_key ?>">
                        <?php foreach ($initial as $idx => $pred): ?>
                        <tr class="pred-row" data-pred-id="<?= $pred['prediction_id'] ?>">
                            <?php
                                // Fetch author name for this story
                                $auth_stmt = $pdo->prepare("SELECT created_by FROM stories WHERE story_id = ?");
                                $auth_stmt->execute([$story['story_id']]);
                                $author_name = $auth_stmt->fetchColumn() ?: '—';

                                // Fetch upcoming part content
                                $part_stmt = $pdo->prepare("SELECT content FROM story_parts WHERE story_id = ? AND part_number = (? + 1)");
                                $part_stmt->execute([$story['story_id'], $pred['prediction_part_no']]);
                                $upcoming = $part_stmt->fetchColumn();
                            ?>
                            <td>
                                <div class="user-cell">
                                    <div class="uname"><?= htmlspecialchars($pred['first_name'] . ' ' . $pred['last_name']) ?></div>
                                    <div class="handle">@<?= htmlspecialchars($pred['user_name']) ?></div>
                                </div>
                            </td>
                            <td><div class="author-cell"><?= htmlspecialchars($author_name) ?></div></td>
                            <td>
                                <div class="pred-text"><?= htmlspecialchars($pred['prediction_text']) ?></div>
                                <div class="pred-date"><?= date('d M Y, H:i', strtotime($pred['created_at'])) ?></div>
                            </td>
                            <td>
                                <?php if ($upcoming): ?>
                                    <div class="upcoming-text"><?= htmlspecialchars(mb_strimwidth($upcoming, 0, 140, '...')) ?></div>
                                <?php else: ?>
                                    <span class="no-upcoming">Not posted yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="like-cell">
                                    <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                    <?= (int)$pred['like_count'] ?>
                                </div>
                            </td>
                            <td>
                                <div class="field-wrap">
                                    <input type="number"
                                           class="edit-input accuracy-inp"
                                           id="acc_<?= $pred['prediction_id'] ?>"
                                           value="<?= $pred['manual_accuracy'] !== null ? htmlspecialchars($pred['manual_accuracy']) : '' ?>"
                                           step="0.01" min="0" max="100"
                                           placeholder="0 – 100"
                                           onchange="saveAccuracy(<?= $pred['prediction_id'] ?>)" oninput="calcBonus(<?= $pred['prediction_id'] ?>)">
                                    <span class="field-status <?= $pred['manual_accuracy'] !== null ? 'saved' : '' ?>" id="acc_status_<?= $pred['prediction_id'] ?>">
                                        <?= $pred['manual_accuracy'] !== null ? 'Saved' : 'Not set' ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="field-wrap">
                                    <input type="number"
                                           class="edit-input bonus-inp"
                                           id="bonus_<?= $pred['prediction_id'] ?>"
                                           value="<?= $pred['bonus_amount'] !== null ? htmlspecialchars($pred['bonus_amount']) : ($pred['manual_accuracy'] !== null ? round(($pred['manual_accuracy'] / 100) * 100) : '') ?>"
                                           step="1" min="0"
                                           placeholder="Auto-calculated"
                                           data-user-id="<?= $pred['user_id'] ?>"
                                           data-story-id="<?= $story['story_id'] ?>"
                                           data-part-no="<?= $pred['prediction_part_no'] ?>"
                                           oninput="bonusManualInput(<?= $pred['prediction_id'] ?>)">
                                    <span class="field-status <?= $pred['bonus_amount'] !== null ? 'saved' : '' ?>" id="bonus_status_<?= $pred['prediction_id'] ?>">
                                        <?= $pred['bonus_amount'] !== null ? 'Saved' : ($pred['manual_accuracy'] !== null ? 'Auto-calculated' : 'Set accuracy first') ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-save" onclick="saveRow(<?= $pred['prediction_id'] ?>)">
                                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                        Submit
                                    </button>
                                    <button class="btn-delete" onclick="deletePrediction(<?= $pred['prediction_id'] ?>, '<?= htmlspecialchars(addslashes($pred['user_name'])) ?>', this)">
                                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach ($extra as $pred): ?>
                        <tr class="pred-row hidden-row" data-pred-id="<?= $pred['prediction_id'] ?>">
                            <?php
                                $auth_stmt->execute([$story['story_id']]);
                                $author_name = $auth_stmt->fetchColumn() ?: '—';
                                $part_stmt->execute([$story['story_id'], $pred['prediction_part_no']]);
                                $upcoming = $part_stmt->fetchColumn();
                            ?>
                            <td>
                                <div class="user-cell">
                                    <div class="uname"><?= htmlspecialchars($pred['first_name'] . ' ' . $pred['last_name']) ?></div>
                                    <div class="handle">@<?= htmlspecialchars($pred['user_name']) ?></div>
                                </div>
                            </td>
                            <td><div class="author-cell"><?= htmlspecialchars($author_name) ?></div></td>
                            <td>
                                <div class="pred-text"><?= htmlspecialchars($pred['prediction_text']) ?></div>
                                <div class="pred-date"><?= date('d M Y, H:i', strtotime($pred['created_at'])) ?></div>
                            </td>
                            <td>
                                <?php if ($upcoming): ?>
                                    <div class="upcoming-text"><?= htmlspecialchars(mb_strimwidth($upcoming, 0, 140, '...')) ?></div>
                                <?php else: ?>
                                    <span class="no-upcoming">Not posted yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="like-cell">
                                    <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                    <?= (int)$pred['like_count'] ?>
                                </div>
                            </td>
                            <td>
                                <div class="field-wrap">
                                    <input type="number"
                                           class="edit-input accuracy-inp"
                                           id="acc_<?= $pred['prediction_id'] ?>"
                                           value="<?= $pred['manual_accuracy'] !== null ? htmlspecialchars($pred['manual_accuracy']) : '' ?>"
                                           step="0.01" min="0" max="100"
                                           placeholder="0 – 100"
                                           onchange="saveAccuracy(<?= $pred['prediction_id'] ?>)" oninput="calcBonus(<?= $pred['prediction_id'] ?>)">
                                    <span class="field-status <?= $pred['manual_accuracy'] !== null ? 'saved' : '' ?>" id="acc_status_<?= $pred['prediction_id'] ?>">
                                        <?= $pred['manual_accuracy'] !== null ? 'Saved' : 'Not set' ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="field-wrap">
                                    <input type="number"
                                           class="edit-input bonus-inp"
                                           id="bonus_<?= $pred['prediction_id'] ?>"
                                           value="<?= $pred['bonus_amount'] !== null ? htmlspecialchars($pred['bonus_amount']) : ($pred['manual_accuracy'] !== null ? round(($pred['manual_accuracy'] / 100) * 100) : '') ?>"
                                           step="1" min="0"
                                           placeholder="Auto-calculated"
                                           data-user-id="<?= $pred['user_id'] ?>"
                                           data-story-id="<?= $story['story_id'] ?>"
                                           data-part-no="<?= $pred['prediction_part_no'] ?>"
                                           oninput="bonusManualInput(<?= $pred['prediction_id'] ?>)">
                                    <span class="field-status <?= $pred['bonus_amount'] !== null ? 'saved' : '' ?>" id="bonus_status_<?= $pred['prediction_id'] ?>">
                                        <?= $pred['bonus_amount'] !== null ? 'Saved' : ($pred['manual_accuracy'] !== null ? 'Auto-calculated' : 'Set accuracy first') ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-save" onclick="saveRow(<?= $pred['prediction_id'] ?>)">
                                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                        Submit
                                    </button>
                                    <button class="btn-delete" onclick="deletePrediction(<?= $pred['prediction_id'] ?>, '<?= htmlspecialchars(addslashes($pred['user_name'])) ?>', this)">
                                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_count > 3): ?>
            <table class="pred-table" style="border-top:1px solid var(--divider)">
                <tbody>
                    <tr class="expand-row">
                        <td colspan="8">
                            <button class="expand-btn" id="expand_btn_<?= $section_key ?>"
                                    onclick="toggleExpand('<?= $section_key ?>', <?= $total_count ?>)">
                                <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                Show <?= $total_count - 3 ?> more prediction<?= ($total_count - 3) !== 1 ? 's' : '' ?>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>

    </main>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// ── Sidebar ────────────────────────────────────────────────────
const sidebar     = document.getElementById('sidebar');
const mainWrapper = document.getElementById('mainWrapper');
const hamburger   = document.getElementById('hamburger');
hamburger.addEventListener('click', () => {
    const c = sidebar.classList.toggle('collapsed');
    mainWrapper.classList.toggle('expanded', c);
    hamburger.classList.toggle('active', c);
    localStorage.setItem('sv_sidebar', c ? '1' : '0');
});
if (localStorage.getItem('sv_sidebar') === '1') {
    sidebar.classList.add('collapsed');
    mainWrapper.classList.add('expanded');
    hamburger.classList.add('active');
}
document.getElementById('globalSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && this.value.trim()) location.href = 'admin_users.php?q=' + encodeURIComponent(this.value.trim());
});

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg, type = 'default') {
    const tc = document.getElementById('toastContainer');
    const t  = document.createElement('div');
    t.className = 'toast';
    t.style.borderLeftColor = type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--gold)';
    t.textContent = msg;
    tc.appendChild(t);
    setTimeout(() => { t.classList.add('fade-out'); setTimeout(() => t.remove(), 280); }, 3500);
}

// ── Story filter dropdown ──────────────────────────────────────
function applyFilters() {
    const sid  = document.getElementById('storyFilter').value;
    const sort = new URLSearchParams(location.search).get('sort') || 'accuracy';
    location.href = `?story_id=${sid}&sort=${sort}`;
}

// ── Expand / collapse rows ─────────────────────────────────────
function toggleExpand(key, total) {
    const rows = document.querySelectorAll(`#tbody_${key} .hidden-row`);
    const btn  = document.getElementById(`expand_btn_${key}`);
    const isOpen = btn.classList.contains('open');
    rows.forEach(r => r.style.display = isOpen ? 'none' : '');
    btn.classList.toggle('open', !isOpen);
    btn.innerHTML = isOpen
        ? `<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><polyline points="6 9 12 15 18 9"/></svg> Show ${total - 3} more prediction${(total-3)!==1?'s':''}`
        : `<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><polyline points="6 9 12 15 18 9"/></svg> Collapse`;
}

// ── Auto-calculate bonus from accuracy ────────────────────────
// Formula: bonus = round(accuracy / 100 * 100)  (i.e. 1 coin per 1% accuracy)
// Admin can override by typing directly in the bonus field.
const MAX_BONUS_COINS = 100; // adjust this constant if needed

function calcBonus(predId) {
    const accVal = parseFloat(document.getElementById('acc_' + predId)?.value);
    const bonusInp = document.getElementById('bonus_' + predId);
    if (!bonusInp) return;
    // Only auto-fill if the bonus field hasn't been manually typed into
    if (!bonusInp.dataset.manualOverride) {
        if (!isNaN(accVal) && accVal >= 0) {
            bonusInp.value = Math.round((accVal / 100) * MAX_BONUS_COINS);
            const s = document.getElementById('bonus_status_' + predId);
            if (s && !s.classList.contains('saved')) s.textContent = 'Auto-calculated';
        }
    }
}

// ── Bonus count tracking ───────────────────────────────────────
// bonusChanged: set of prediction_ids with non-empty bonus value
const bonusChanged = new Set();

function bonusManualInput(predId) {
    // mark as manually overridden so calcBonus won't overwrite
    const inp = document.getElementById('bonus_' + predId);
    if (inp) inp.dataset.manualOverride = '1';
    trackBonusChange(predId);
}

function trackBonusChange(predId) {
    const val = document.getElementById('bonus_' + predId).value.trim();
    if (val !== '') bonusChanged.add(predId);
    else bonusChanged.delete(predId);
    updateBonusWarning();
}

function updateBonusWarning() {
    const badge    = document.getElementById('bonusWarningBadge');
    const warnText = document.getElementById('bonusWarnText');
    const count    = bonusChanged.size;
    if (count > 3) {
        badge.style.display = 'inline-flex';
        warnText.textContent = count + ' bonuses set — top 3 recommended';
    } else {
        badge.style.display = 'none';
    }
}

// Count already-saved bonuses on load
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.bonus-inp').forEach(inp => {
        if (inp.value.trim() !== '') {
            const predId = inp.id.replace('bonus_', '');
            bonusChanged.add(parseInt(predId));
        }
    });
    updateBonusWarning();
});

// ── AJAX: Save accuracy on change ─────────────────────────────
async function saveAccuracy(predId) {
    const inp = document.getElementById('acc_' + predId);
    const acc = parseFloat(inp.value);
    if (isNaN(acc)) return;

    const fd = new FormData();
    fd.append('ajax_action',  'save_accuracy');
    fd.append('prediction_id', predId);
    fd.append('accuracy',      acc);

    try {
        const r = await fetch('admin_bonus.php', { method: 'POST', body: fd });
        const d = await r.json();
        const status = document.getElementById('acc_status_' + predId);
        if (d.success) {
            status.textContent = 'Saved';
            status.className   = 'field-status saved';
            showToast('Accuracy saved', 'success');
        } else {
            showToast(d.msg || 'Failed to save accuracy', 'error');
        }
    } catch {
        showToast('Request failed', 'error');
    }
}

// ── AJAX: Save full row (accuracy + bonus) ─────────────────────
async function saveRow(predId) {
    const accInp   = document.getElementById('acc_' + predId);
    const bonusInp = document.getElementById('bonus_' + predId);
    const acc      = parseFloat(accInp.value);
    const bonus    = parseFloat(bonusInp.value);

    // Bonus count check
    const pendingCount = bonusChanged.size;
    if (pendingCount > 3 && bonusInp.value.trim() !== '') {
        const go = confirm(`You have set bonus for ${pendingCount} predictions.\nIt is recommended to limit bonuses to the top 3 predictors.\n\nDo you still want to save this bonus?`);
        if (!go) return;
    }

    // Save accuracy first (if filled)
    if (!isNaN(acc)) {
        const fdA = new FormData();
        fdA.append('ajax_action',  'save_accuracy');
        fdA.append('prediction_id', predId);
        fdA.append('accuracy',      acc);
        await fetch('admin_bonus.php', { method: 'POST', body: fdA });
        const s = document.getElementById('acc_status_' + predId);
        if (s) { s.textContent = 'Saved'; s.className = 'field-status saved'; }
    }

    // Save bonus (if filled)
    if (!isNaN(bonus) && bonusInp.value.trim() !== '') {
        const fdB = new FormData();
        fdB.append('ajax_action',  'save_bonus');
        fdB.append('prediction_id', predId);
        fdB.append('bonus_amount',  bonus);
        fdB.append('user_id',       bonusInp.dataset.userId);
        fdB.append('story_id',      bonusInp.dataset.storyId);
        fdB.append('part_no',       bonusInp.dataset.partNo);
        const r = await fetch('admin_bonus.php', { method: 'POST', body: fdB });
        const d = await r.json();
        const s = document.getElementById('bonus_status_' + predId);
        if (d.success) {
            if (s) { s.textContent = 'Saved'; s.className = 'field-status saved'; }
            showToast('Saved successfully', 'success');
        } else {
            showToast(d.msg || 'Failed to save bonus', 'error');
            return;
        }
    } else {
        showToast('Saved', 'success');
    }

    bonusChanged.add(predId);
    updateBonusWarning();
}

// ── AJAX: Delete prediction ────────────────────────────────────
async function deletePrediction(predId, username, btn) {
    if (!confirm(`Delete prediction by @${username}?\n\nThis will remove the prediction and any bonus record. This cannot be undone.`)) return;

    btn.disabled = true;
    const fd = new FormData();
    fd.append('ajax_action',  'delete_prediction');
    fd.append('prediction_id', predId);

    try {
        const r = await fetch('admin_bonus.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            const row = document.querySelector(`tr[data-pred-id="${predId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity    = '0';
                setTimeout(() => row.remove(), 300);
            }
            bonusChanged.delete(predId);
            updateBonusWarning();
            showToast('Prediction deleted', 'success');
        } else {
            showToast(d.msg || 'Delete failed', 'error');
            btn.disabled = false;
        }
    } catch {
        showToast('Request failed', 'error');
        btn.disabled = false;
    }
}
</script>
</body>
</html>
