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

$today      = date('l, F j, Y');
$flash      = ['type' => '', 'msg' => ''];

// ── Helper: log admin action ────────────────────────────────────────────
function log_admin(PDO $pdo, string $text, string $type = 'update'): void {
    try {
        $pdo->prepare("INSERT INTO admin_activity_log (action_text,action_type,created_at) VALUES(?,?,NOW())")
            ->execute([$text, $type]);
    } catch (Exception $e) {}
}

// ── Handle POST (Add / Edit) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id           = (int)($_POST['id']           ?? 0);
        $story_id     = (int)($_POST['story_id']     ?? 0);
        $part_id      = ($_POST['part_id'] !== '' && $_POST['part_id'] !== null)
                        ? (int)$_POST['part_id'] : null;
        $pred_text    = trim($_POST['prediction_text'] ?? '');
        $label_score  = (float)($_POST['label_score']  ?? 0);
        $label_type   = in_array($_POST['label_type'] ?? '', ['correct','partial','wrong'])
                        ? $_POST['label_type'] : 'correct';
        $ai_score     = $_POST['ai_score']    !== '' ? (float)$_POST['ai_score']    : null;
        $entailment   = $_POST['entailment']  !== '' ? (float)$_POST['entailment']  : null;
        $contradiction= $_POST['contradiction']!==''  ? (float)$_POST['contradiction']: null;
        $entity_score = $_POST['entity_score']!== '' ? (float)$_POST['entity_score']: null;
        $cosine_score = $_POST['cosine_score']!== '' ? (float)$_POST['cosine_score']: null;
        $notes        = trim($_POST['notes'] ?? '') ?: null;

        if (!$story_id || !$pred_text) {
            $flash = ['type' => 'error', 'msg' => 'Story and prediction text are required.'];
        } elseif ($label_score < 0 || $label_score > 100) {
            $flash = ['type' => 'error', 'msg' => 'Label score must be between 0 and 100.'];
        } else {
            try {
                if ($action === 'add') {
                    $pdo->prepare("
                        INSERT INTO admin_story_dataset
                            (story_id, part_id, prediction_text, label_score, label_type,
                             ai_score, entailment, contradiction, entity_score, cosine_score, notes)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)
                    ")->execute([$story_id,$part_id,$pred_text,$label_score,$label_type,
                                 $ai_score,$entailment,$contradiction,$entity_score,$cosine_score,$notes]);
                    $flash = ['type'=>'success','msg'=>'Dataset entry added successfully.'];
                    log_admin($pdo, "Dataset entry added for story #$story_id", 'add');
                } else {
                    if (!$id) { $flash = ['type'=>'error','msg'=>'Invalid entry ID.']; }
                    else {
                        $pdo->prepare("
                            UPDATE admin_story_dataset
                            SET story_id=?, part_id=?, prediction_text=?, label_score=?,
                                label_type=?, ai_score=?, entailment=?, contradiction=?,
                                entity_score=?, cosine_score=?, notes=?
                            WHERE id=?
                        ")->execute([$story_id,$part_id,$pred_text,$label_score,$label_type,
                                     $ai_score,$entailment,$contradiction,$entity_score,$cosine_score,$notes,$id]);
                        $flash = ['type'=>'success','msg'=>'Entry #'.$id.' updated.'];
                        log_admin($pdo, "Dataset entry #$id edited by admin", 'update');
                    }
                }
            } catch (PDOException $e) {
                error_log("Dataset error: ".$e->getMessage());
                $flash = ['type'=>'error','msg'=>'Database error: '.$e->getMessage()];
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            $flash = ['type'=>'error','msg'=>'Invalid ID.'];
        } else {
            $pdo->prepare("DELETE FROM admin_story_dataset WHERE id=?")->execute([$id]);
            $flash = ['type'=>'success','msg'=>'Entry #'.$id.' deleted.'];
            log_admin($pdo, "Dataset entry #$id deleted by admin", 'delete');
        }
    }
}

// ── Fetch all stories (approved) for dropdowns ──────────────────────────
$stories = $pdo->query("
    SELECT story_id, title, created_by FROM stories
    WHERE status='approved'
    ORDER BY title ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Dataset filter ──────────────────────────────────────────────────────
$filter_story = (int)($_GET['story_id'] ?? 0);
$filter_type  = $_GET['label_type'] ?? 'all';
$search_q     = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 20;

$where = ['1=1'];
$params = [];

if ($filter_story) {
    $where[] = 'd.story_id = ?';
    $params[] = $filter_story;
}
if (in_array($filter_type, ['correct','partial','wrong'])) {
    $where[] = 'd.label_type = ?';
    $params[] = $filter_type;
}
if ($search_q !== '') {
    $where[] = 'd.prediction_text LIKE ?';
    $params[] = "%$search_q%";
}

$where_sql = implode(' AND ', $where);

// Total count for pagination
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_story_dataset d WHERE $where_sql");
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// Fetch entries with story title and part number
$data_params = array_merge($params, [$per_page, $offset]);
$entries = $pdo->prepare("
    SELECT d.*,
           s.title AS story_title,
           sp.part_number
    FROM admin_story_dataset d
    LEFT JOIN stories s ON s.story_id = d.story_id
    LEFT JOIN story_parts sp ON sp.part_id = d.part_id
    WHERE $where_sql
    ORDER BY d.id DESC
    LIMIT ? OFFSET ?
");
$entries->execute($data_params);
$entries = $entries->fetchAll(PDO::FETCH_ASSOC);

// ── Summary stats ───────────────────────────────────────────────────────
$total_all     = (int)$pdo->query("SELECT COUNT(*) FROM admin_story_dataset")->fetchColumn();
$total_correct = (int)$pdo->query("SELECT COUNT(*) FROM admin_story_dataset WHERE label_type='correct'")->fetchColumn();
$total_partial = (int)$pdo->query("SELECT COUNT(*) FROM admin_story_dataset WHERE label_type='partial'")->fetchColumn();
$total_wrong   = (int)$pdo->query("SELECT COUNT(*) FROM admin_story_dataset WHERE label_type='wrong'")->fetchColumn();
$avg_score     = $pdo->query("SELECT ROUND(AVG(label_score),1) FROM admin_story_dataset")->fetchColumn() ?? 0;
$stories_covered = (int)$pdo->query("SELECT COUNT(DISTINCT story_id) FROM admin_story_dataset")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dataset Manager — StoryVerse Admin</title>
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
.topbar-search{display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:7px 12px;flex:1;max-width:340px;transition:border-color 0.18s,box-shadow 0.18s}
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
.page-header{margin-bottom:24px}
.page-header h1{font-family:var(--font-display);font-size:22px;color:var(--text-primary);margin-bottom:4px}
.page-header p{font-size:14px;color:var(--text-secondary)}

/* ── Flash ── */
.flash{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;margin-bottom:20px;font-size:14px;font-weight:600;border:1px solid}
.flash.success{background:#ECFDF5;border-color:rgba(16,185,129,0.3);color:#065F46}
.flash.error{background:#FEF2F2;border-color:rgba(239,68,68,0.3);color:#991B1B}
.flash svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}

/* ── Stat strip ── */
.stat-strip{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:24px}
.stat-chip{background:var(--card-bg);border:1px solid var(--card-border);border-radius:10px;box-shadow:var(--card-shadow);padding:14px 16px}
.stat-chip-label{font-family:var(--font-mono);font-size:9px;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px}
.stat-chip-value{font-family:var(--font-body);font-size:24px;font-weight:700;color:var(--text-primary);line-height:1}
.stat-chip-value.correct{color:var(--success)}
.stat-chip-value.partial{color:var(--warning)}
.stat-chip-value.wrong{color:var(--danger)}

/* ── Two-column layout ── */
.main-grid{display:grid;grid-template-columns:400px 1fr;gap:22px;align-items:start}

/* ── Add / Edit form card ── */
.form-card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);position:sticky;top:calc(var(--topbar-h) + 20px)}
.form-card-header{padding:18px 22px 14px;border-bottom:1px solid var(--divider);display:flex;align-items:center;justify-content:space-between}
.form-card-title{font-family:var(--font-body);font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-primary)}
.form-mode-badge{font-family:var(--font-mono);font-size:10px;font-weight:700;padding:3px 9px;border-radius:4px;letter-spacing:0.04em}
.form-mode-badge.add{background:rgba(245,166,35,0.12);color:var(--gold-dark);border:1px solid rgba(245,166,35,0.3)}
.form-mode-badge.edit{background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE}

.form-body{padding:18px 22px;max-height:calc(100vh - 200px);overflow-y:auto}
.form-body::-webkit-scrollbar{width:3px}
.form-body::-webkit-scrollbar-thumb{background:var(--border);border-radius:99px}

.field-group{margin-bottom:14px}
.field-label{display:block;font-family:var(--font-body);font-size:11px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:5px}
.field-label .req{color:var(--danger)}
.field-input{width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);transition:border-color 0.18s,box-shadow 0.18s}
.field-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-input::placeholder{color:var(--text-muted)}
.field-input:disabled{opacity:0.5;cursor:not-allowed;background:var(--divider)}
.field-textarea{width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);resize:vertical;min-height:80px;line-height:1.55;transition:border-color 0.18s,box-shadow 0.18s}
.field-textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-select{width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;outline:none;font-family:var(--font-body);font-size:14px;color:var(--text-primary);cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239CA3AF' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px;transition:border-color 0.18s}
.field-select:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow)}
.field-hint{font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-top:4px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.field-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}

/* Score color feedback */
.score-hint{font-family:var(--font-mono);font-size:10px;margin-top:4px;font-weight:700}

/* Section divider inside form */
.form-section{font-family:var(--font-mono);font-size:9px;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-muted);margin:16px 0 12px;display:flex;align-items:center;gap:8px}
.form-section::after{content:'';flex:1;height:1px;background:var(--divider)}

.form-footer{padding:14px 22px;border-top:1px solid var(--divider);display:flex;gap:10px}
.btn-save{flex:1;padding:10px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border:none;border-radius:8px;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:opacity 0.15s,transform 0.15s}
.btn-save:hover{opacity:0.9;transform:translateY(-1px)}
.btn-cancel-edit{padding:10px 16px;background:var(--divider);border:1px solid var(--border);border-radius:8px;color:var(--text-secondary);font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;transition:background 0.15s;display:none}
.btn-cancel-edit:hover{background:var(--border)}
.btn-cancel-edit.show{display:block}

/* ── Dataset table card ── */
.table-card{background:var(--card-bg);border-radius:var(--card-radius);border:1px solid var(--card-border);box-shadow:var(--card-shadow);overflow:hidden}
.table-card-header{padding:16px 20px 12px;border-bottom:1px solid var(--divider);display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.table-card-title{font-family:var(--font-body);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-primary)}
.table-count{font-family:var(--font-mono);font-size:11px;color:var(--text-muted);margin-left:auto}

/* Filter bar inside table card */
.filter-row{display:flex;align-items:center;gap:10px;padding:12px 20px;border-bottom:1px solid var(--divider);flex-wrap:wrap}
.filter-select{padding:6px 28px 6px 10px;background:var(--bg);border:1px solid var(--border);border-radius:7px;font-family:var(--font-body);font-size:13px;color:var(--text-primary);outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%239CA3AF' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;transition:border-color 0.15s}
.filter-select:focus{border-color:var(--gold)}
.filter-search{display:flex;align-items:center;gap:7px;background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:6px 10px;flex:1;max-width:260px;transition:border-color 0.18s}
.filter-search:focus-within{border-color:var(--gold)}
.filter-search svg{width:13px;height:13px;stroke:var(--text-muted);fill:none;stroke-width:2;flex-shrink:0}
.filter-search input{border:none;background:none;outline:none;font-family:var(--font-body);font-size:13px;color:var(--text-primary);width:100%}
.filter-search input::placeholder{color:var(--text-muted)}
.filter-btn{padding:6px 14px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border:none;border-radius:7px;color:#fff;font-family:var(--font-body);font-size:12px;font-weight:700;cursor:pointer;transition:opacity 0.15s}
.filter-btn:hover{opacity:0.9}
.filter-clear{font-family:var(--font-mono);font-size:11px;color:var(--text-muted);text-decoration:none;padding:6px 8px}
.filter-clear:hover{color:var(--text-secondary)}

/* Dataset entries */
.dataset-table{width:100%;border-collapse:collapse}
.dataset-table thead tr{background:var(--divider);border-bottom:1px solid var(--border)}
.dataset-table thead th{padding:10px 14px;text-align:left;font-family:var(--font-mono);font-size:9px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);white-space:nowrap}
.dataset-table tbody tr{border-bottom:1px solid var(--divider);transition:background 0.1s}
.dataset-table tbody tr:last-child{border-bottom:none}
.dataset-table tbody tr:hover{background:#F8F9FF}
.dataset-table td{padding:12px 14px;vertical-align:top}

/* Prediction text cell */
.pred-text{font-size:13px;color:var(--text-primary);line-height:1.5;max-width:320px}
.pred-text-full{display:none}
.pred-text-short{display:block}
.pred-meta{margin-top:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.meta-tag{font-family:var(--font-mono);font-size:9px;color:var(--text-muted);background:var(--divider);padding:2px 6px;border-radius:3px}
.notes-preview{font-size:11px;color:var(--text-muted);font-style:italic;margin-top:4px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Label type badge */
.label-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:5px;font-family:var(--font-body);font-size:11px;font-weight:700;border:1px solid;white-space:nowrap}
.label-badge.correct{background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.label-badge.partial{background:#FFFBEB;color:#92400E;border-color:#FCD34D}
.label-badge.wrong{background:#FEF2F2;color:#991B1B;border-color:#FCA5A5}

/* Score cells */
.score-cell{font-family:var(--font-mono);font-size:12px;font-weight:700}
.score-bar-wrap{height:4px;background:var(--divider);border-radius:99px;width:60px;margin-top:4px;overflow:hidden}
.score-bar{height:100%;border-radius:99px}

.mono-cell{font-family:var(--font-mono);font-size:11px;color:var(--text-muted)}

/* Entry actions */
.entry-actions{display:flex;align-items:center;gap:5px;white-space:nowrap}
.eaction-btn{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border);background:var(--card-bg);cursor:pointer;transition:all 0.15s}
.eaction-btn svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.eaction-btn.edit-btn{color:var(--azure)}
.eaction-btn.edit-btn:hover{background:#EFF6FF;border-color:#BFDBFE}
.eaction-btn.del-btn{color:var(--danger)}
.eaction-btn.del-btn:hover{background:#FEF2F2;border-color:#FCA5A5}

/* Empty state */
.empty-state{text-align:center;padding:48px 24px;color:var(--text-muted)}
.empty-state svg{width:44px;height:44px;stroke:var(--border);fill:none;stroke-width:1.4;margin:0 auto 14px;display:block}

/* Pagination */
.pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--divider);flex-wrap:wrap;gap:10px}
.page-info{font-family:var(--font-mono);font-size:11px;color:var(--text-muted)}
.page-btns{display:flex;align-items:center;gap:6px}
.page-btn{padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:var(--card-bg);font-family:var(--font-mono);font-size:11px;color:var(--text-secondary);cursor:pointer;text-decoration:none;transition:all 0.15s}
.page-btn:hover{border-color:var(--gold);color:var(--text-primary)}
.page-btn.active{background:var(--text-primary);color:#fff;border-color:var(--text-primary)}
.page-btn.disabled{opacity:0.35;cursor:not-allowed;pointer-events:none}

/* Delete confirm row */
.del-confirm-row{display:none;background:#FEF2F2;padding:8px 14px;align-items:center;gap:10px;font-size:12px;font-weight:600;color:var(--danger)}
.del-confirm-row.show{display:flex}
.del-confirm-row button{padding:4px 12px;border-radius:5px;border:1px solid;font-family:var(--font-body);font-size:11px;font-weight:700;cursor:pointer}
.del-yes{background:var(--danger);color:#fff;border-color:var(--danger)}
.del-yes:hover{background:#DC2626}
.del-no{background:var(--card-bg);color:var(--text-secondary);border-color:var(--border)}

/* Toast */
.toast-container{position:fixed;bottom:28px;right:28px;z-index:999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;padding:12px 18px;background:#1A1D2E;color:#fff;border-radius:10px;font-family:var(--font-body);font-size:13.5px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,0.2);animation:toast-in 0.3s cubic-bezier(0.34,1.56,0.64,1) forwards;pointer-events:all;border-left:3px solid var(--gold)}
@keyframes toast-in{from{opacity:0;transform:translateY(16px) scale(0.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.toast.fade-out{animation:toast-out 0.25s ease forwards}
@keyframes toast-out{to{opacity:0;transform:translateY(8px) scale(0.97)}}

.fade-up{opacity:0;transform:translateY(14px);animation:fade-up-in 0.38s ease forwards}
@keyframes fade-up-in{to{opacity:1;transform:translateY(0)}}

@media(max-width:1200px){.main-grid{grid-template-columns:1fr}.form-card{position:static}.stat-strip{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.stat-strip{grid-template-columns:repeat(2,1fr)}.field-row,.field-row-3{grid-template-columns:1fr}.page-content{padding:16px 14px 40px}.topbar-date{display:none}}
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
        <a href="admin_dataset.php" class="nav-item active">
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
            <input type="text" placeholder="Search dataset entries..." id="topbarSearch"
                   value="<?= htmlspecialchars($search_q) ?>"
                   onkeydown="if(event.key==='Enter'){applyFilter()}">
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
            <h1>Dataset & ML</h1>
            <p>Manage training data for the Cross-Encoder SBERT prediction evaluation model.</p>
        </div>

        <!-- Flash message -->
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

        <!-- Summary stat strip -->
        <div class="stat-strip fade-up" style="animation-delay:0.04s">
            <div class="stat-chip">
                <div class="stat-chip-label">Total Entries</div>
                <div class="stat-chip-value"><?= number_format($total_all) ?></div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-label">Correct</div>
                <div class="stat-chip-value correct"><?= number_format($total_correct) ?></div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-label">Partial</div>
                <div class="stat-chip-value partial"><?= number_format($total_partial) ?></div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-label">Wrong</div>
                <div class="stat-chip-value wrong"><?= number_format($total_wrong) ?></div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-label">Avg Label Score</div>
                <div class="stat-chip-value"><?= $avg_score ?></div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-label">Stories Covered</div>
                <div class="stat-chip-value"><?= $stories_covered ?></div>
            </div>
        </div>

        <!-- Main two-column grid -->
        <div class="main-grid fade-up" style="animation-delay:0.08s">

            <!-- ══ LEFT: Add/Edit Form ══ -->
            <div class="form-card" id="formCard">
                <div class="form-card-header">
                    <span class="form-card-title" id="formTitle">Add Entry</span>
                    <span class="form-mode-badge add" id="formModeBadge">NEW</span>
                </div>

                <form method="POST" action="" id="dataForm">
                    <input type="hidden" name="form_action" id="formAction" value="add">
                    <input type="hidden" name="id" id="editId" value="">

                    <div class="form-body">

                        <!-- Story selector -->
                        <div class="field-group">
                            <label class="field-label">Story <span class="req">*</span></label>
                            <select name="story_id" id="storySelect" class="field-select" onchange="loadParts()" required>
                                <option value="">— Choose a story —</option>
                                <?php foreach ($stories as $st): ?>
                                    <option value="<?= $st['story_id'] ?>">
                                        <?= htmlspecialchars($st['title']) ?> (by @<?= htmlspecialchars($st['created_by']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-hint">Only approved stories are shown</div>
                        </div>

                        <!-- Part selector (populated by JS) -->
                        <div class="field-group">
                            <label class="field-label">Story Part</label>
                            <select name="part_id" id="partSelect" class="field-select" disabled>
                                <option value="">— Select a story first —</option>
                            </select>
                            <div class="field-hint">The prediction was made against this part</div>
                        </div>

                        <!-- Prediction text -->
                        <div class="field-group">
                            <label class="field-label">Prediction Text <span class="req">*</span></label>
                            <textarea name="prediction_text" id="predText" class="field-textarea" rows="4"
                                placeholder="Enter the prediction text exactly as it was submitted..." required></textarea>
                        </div>

                        <div class="form-section">Scoring</div>

                        <div class="field-row">
                            <div class="field-group">
                                <label class="field-label">Label Score (0–100) <span class="req">*</span></label>
                                <input type="number" name="label_score" id="labelScore" class="field-input"
                                       step="0.01" min="0" max="100" placeholder="e.g. 92.5"
                                       oninput="updateScoreHint(this)" required>
                                <div class="score-hint" id="scoreHint"></div>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Label Type <span class="req">*</span></label>
                                <select name="label_type" id="labelType" class="field-select" onchange="syncLabelType()">
                                    <option value="correct">Correct</option>
                                    <option value="partial">Partial</option>
                                    <option value="wrong">Wrong</option>
                                </select>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field-group">
                                <label class="field-label">AI Score</label>
                                <input type="number" name="ai_score" id="aiScore" class="field-input"
                                       step="0.01" min="0" max="100" placeholder="e.g. 89.5">
                            </div>
                            <div class="field-group">
                                <label class="field-label">Entailment (0–1)</label>
                                <input type="number" name="entailment" id="entailment" class="field-input"
                                       step="0.0001" min="0" max="1" placeholder="e.g. 0.88">
                            </div>
                        </div>

                        <div class="field-row-3">
                            <div class="field-group">
                                <label class="field-label">Contradiction</label>
                                <input type="number" name="contradiction" id="contradiction" class="field-input"
                                       step="0.0001" min="0" max="1" placeholder="0.05">
                            </div>
                            <div class="field-group">
                                <label class="field-label">Entity Score</label>
                                <input type="number" name="entity_score" id="entityScore" class="field-input"
                                       step="0.0001" min="0" max="1" placeholder="0.80">
                            </div>
                            <div class="field-group">
                                <label class="field-label">Cosine Score</label>
                                <input type="number" name="cosine_score" id="cosineScore" class="field-input"
                                       step="0.0001" min="0" max="1" placeholder="0.76">
                            </div>
                        </div>

                        <div class="form-section">Notes</div>

                        <div class="field-group">
                            <label class="field-label">Admin Notes</label>
                            <textarea name="notes" id="notesField" class="field-textarea" rows="2"
                                placeholder="Optional — explain why this score was assigned..."></textarea>
                        </div>

                    </div><!-- /form-body -->

                    <div class="form-footer">
                        <a href="admin_dataset.php" class="btn-cancel-edit" id="cancelEditBtn">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">Add Entry</button>
                    </div>
                </form>
            </div>

            <!-- ══ RIGHT: Dataset Table ══ -->
            <div>
                <div class="table-card">
                    <div class="table-card-header">
                        <span class="table-card-title">Dataset Entries</span>
                        <span class="table-count"><?= number_format($total) ?> entries
                            <?= ($filter_story || $filter_type !== 'all' || $search_q) ? '(filtered)' : '' ?></span>
                    </div>

                    <!-- Filter row -->
                    <div class="filter-row">
                        <select class="filter-select" id="filterStory" onchange="applyFilter()">
                            <option value="">All Stories</option>
                            <?php foreach ($stories as $st): ?>
                                <option value="<?= $st['story_id'] ?>"
                                    <?= $filter_story === (int)$st['story_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(mb_substr($st['title'], 0, 30)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select class="filter-select" id="filterType" onchange="applyFilter()">
                            <option value="all" <?= $filter_type==='all'?'selected':'' ?>>All Types</option>
                            <option value="correct" <?= $filter_type==='correct'?'selected':'' ?>>Correct</option>
                            <option value="partial" <?= $filter_type==='partial'?'selected':'' ?>>Partial</option>
                            <option value="wrong" <?= $filter_type==='wrong'?'selected':'' ?>>Wrong</option>
                        </select>

                        <div class="filter-search">
                            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="filterSearch" placeholder="Search predictions..."
                                   value="<?= htmlspecialchars($search_q) ?>"
                                   onkeydown="if(event.key==='Enter') applyFilter()">
                        </div>

                        <button class="filter-btn" onclick="applyFilter()">Filter</button>

                        <?php if ($filter_story || $filter_type !== 'all' || $search_q): ?>
                            <a href="admin_dataset.php" class="filter-clear">Clear</a>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($entries)): ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            <p>No dataset entries found<?= $search_q ? ' matching "'.htmlspecialchars($search_q).'"' : '' ?>.</p>
                        </div>
                    <?php else: ?>
                    <div style="overflow-x:auto">
                    <table class="dataset-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Prediction</th>
                                <th>Type</th>
                                <th>Score</th>
                                <th>AI / NLI</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entries as $e):
                            $score_pct = min(100, max(0, (float)$e['label_score']));
                            $score_color = $score_pct >= 70 ? 'var(--success)'
                                         : ($score_pct >= 35 ? 'var(--warning)' : 'var(--danger)');
                            $short_pred = mb_substr($e['prediction_text'], 0, 90);
                            $has_more   = mb_strlen($e['prediction_text']) > 90;
                            $created    = date('M j, Y', strtotime($e['created_at']));

                            // Build JS data object for edit pre-fill
                            $js_data = htmlspecialchars(json_encode([
                                'id'              => (int)$e['id'],
                                'story_id'        => (int)$e['story_id'],
                                'part_id'         => $e['part_id'] ? (int)$e['part_id'] : '',
                                'prediction_text' => $e['prediction_text'],
                                'label_score'     => $e['label_score'],
                                'label_type'      => $e['label_type'],
                                'ai_score'        => $e['ai_score'] ?? '',
                                'entailment'      => $e['entailment'] ?? '',
                                'contradiction'   => $e['contradiction'] ?? '',
                                'entity_score'    => $e['entity_score'] ?? '',
                                'cosine_score'    => $e['cosine_score'] ?? '',
                                'notes'           => $e['notes'] ?? '',
                            ]), ENT_QUOTES);
                        ?>
                        <tr id="entry-row-<?= $e['id'] ?>">
                            <td class="mono-cell" style="font-size:10px;color:var(--text-muted)"><?= $e['id'] ?></td>

                            <td>
                                <div class="pred-text">
                                    <span class="pred-text-short" id="short-<?= $e['id'] ?>">
                                        <?= htmlspecialchars($short_pred) ?><?= $has_more ? '...' : '' ?>
                                        <?php if ($has_more): ?>
                                            <span style="color:var(--gold);cursor:pointer;font-size:11px;font-weight:700"
                                                  onclick="toggleText(<?= $e['id'] ?>)"> more</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($has_more): ?>
                                    <span class="pred-text-full" id="full-<?= $e['id'] ?>">
                                        <?= htmlspecialchars($e['prediction_text']) ?>
                                        <span style="color:var(--gold);cursor:pointer;font-size:11px;font-weight:700"
                                              onclick="toggleText(<?= $e['id'] ?>)"> less</span>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="pred-meta">
                                    <span class="meta-tag"><?= htmlspecialchars($e['story_title'] ?? 'Unknown story') ?></span>
                                    <?php if ($e['part_number']): ?>
                                        <span class="meta-tag">Part <?= $e['part_number'] ?></span>
                                    <?php else: ?>
                                        <span class="meta-tag" style="color:var(--text-muted);opacity:0.7">No part</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($e['notes'])): ?>
                                    <div class="notes-preview" title="<?= htmlspecialchars($e['notes']) ?>">
                                        <?= htmlspecialchars($e['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="label-badge <?= $e['label_type'] ?>">
                                    <?= ucfirst($e['label_type'] ?? '—') ?>
                                </span>
                            </td>

                            <td>
                                <div class="score-cell" style="color:<?= $score_color ?>"><?= $e['label_score'] ?></div>
                                <div class="score-bar-wrap">
                                    <div class="score-bar" style="width:<?= $score_pct ?>%;background:<?= $score_color ?>"></div>
                                </div>
                            </td>

                            <td>
                                <div class="mono-cell">
                                    <?php if ($e['ai_score'] !== null): ?>
                                        <div>AI: <strong><?= $e['ai_score'] ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($e['entailment'] !== null): ?>
                                        <div style="margin-top:2px">E: <?= number_format((float)$e['entailment'],3) ?>
                                        / C: <?= number_format((float)$e['contradiction'],3) ?></div>
                                    <?php endif; ?>
                                    <?php if ($e['cosine_score'] !== null): ?>
                                        <div style="margin-top:2px">Cos: <?= number_format((float)$e['cosine_score'],3) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="mono-cell"><?= $created ?></td>

                            <td>
                                <div class="entry-actions">
                                    <button class="eaction-btn edit-btn" title="Edit entry"
                                        onclick='prefillEdit(<?= $js_data ?>)'>
                                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button class="eaction-btn del-btn" title="Delete entry"
                                        onclick="confirmDelete(<?= $e['id'] ?>)">
                                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                    </button>
                                </div>
                                <!-- Inline delete confirm -->
                                <div class="del-confirm-row" id="del-confirm-<?= $e['id'] ?>">
                                    <span>Delete #<?= $e['id'] ?>?</span>
                                    <form method="POST" action="" style="display:contents">
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <button type="submit" class="del-yes">Yes</button>
                                        <button type="button" class="del-no" onclick="cancelDelete(<?= $e['id'] ?>)">No</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <span class="page-info">
                            Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?> of <?= $total ?>
                        </span>
                        <div class="page-btns">
                            <?php
                            // Build URL helper for pagination
                            $purl = function(int $p) use ($filter_story, $filter_type, $search_q): string {
                                $params = ['page' => $p];
                                if ($filter_story) $params['story_id'] = $filter_story;
                                if ($filter_type !== 'all') $params['label_type'] = $filter_type;
                                if ($search_q) $params['q'] = $search_q;
                                return 'admin_dataset.php?' . http_build_query($params);
                            };
                            ?>
                            <a href="<?= $purl(1) ?>" class="page-btn <?= $page === 1 ? 'disabled' : '' ?>">&laquo;</a>
                            <a href="<?= $purl(max(1,$page-1)) ?>" class="page-btn <?= $page === 1 ? 'disabled' : '' ?>">Prev</a>

                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($total_pages, $page + 2);
                            for ($p = $start; $p <= $end; $p++):
                            ?>
                                <a href="<?= $purl($p) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>

                            <a href="<?= $purl(min($total_pages,$page+1)) ?>" class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>">Next</a>
                            <a href="<?= $purl($total_pages) ?>" class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>">&raquo;</a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endif; // end if entries not empty ?>
                </div><!-- /table-card -->
            </div><!-- /right column -->

        </div><!-- /main-grid -->
    </main>
</div>

<div class="toast-container" id="toastContainer"></div>

<!-- ═══ JAVASCRIPT ══════════════════════════════════════════════ -->
<script>
// ── Sidebar ────────────────────────────────────────────────────
const sidebar=document.getElementById('sidebar'),mainWrapper=document.getElementById('mainWrapper'),hamburger=document.getElementById('hamburger');
hamburger.addEventListener('click',()=>{const c=sidebar.classList.toggle('collapsed');mainWrapper.classList.toggle('expanded',c);hamburger.classList.toggle('active',c);localStorage.setItem('sv_sidebar',c?'1':'0')});
if(localStorage.getItem('sv_sidebar')==='1'){sidebar.classList.add('collapsed');mainWrapper.classList.add('expanded');hamburger.classList.add('active')}

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg,type='default'){
    const tc=document.getElementById('toastContainer'),t=document.createElement('div');
    t.className='toast';
    t.style.borderLeftColor=type==='success'?'var(--success)':type==='error'?'var(--danger)':'var(--gold)';
    t.textContent=msg;tc.appendChild(t);
    setTimeout(()=>{t.classList.add('fade-out');setTimeout(()=>t.remove(),280)},3500);
}

// ── Parts loader (AJAX) ────────────────────────────────────────
// Pre-built parts map from PHP for instant loading
const partsMap = {};

async function loadParts(preSelectPartId) {
    const storyId = document.getElementById('storySelect').value;
    const sel     = document.getElementById('partSelect');
    sel.innerHTML = '<option value="">Loading...</option>';
    sel.disabled  = true;

    if (!storyId) {
        sel.innerHTML = '<option value="">— Select a story first —</option>';
        return;
    }

    // Check cache
    if (partsMap[storyId]) {
        renderPartOptions(sel, partsMap[storyId], preSelectPartId);
        return;
    }

    try {
        const res  = await fetch(`admin_dataset_parts.php?story_id=${storyId}`);
        const data = await res.json();
        partsMap[storyId] = data.parts || [];
        renderPartOptions(sel, partsMap[storyId], preSelectPartId);
    } catch(e) {
        sel.innerHTML = '<option value="">Error loading parts</option>';
        sel.disabled = false;
    }
}

function renderPartOptions(sel, parts, preSelect) {
    sel.innerHTML = '<option value="">— No specific part —</option>';
    parts.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.part_id;
        opt.textContent = `Part ${p.part_number}${p.status !== 'approved' ? ' ['+p.status+']' : ''}`;
        if (preSelect && parseInt(preSelect) === parseInt(p.part_id)) opt.selected = true;
        sel.appendChild(opt);
    });
    sel.disabled = parts.length === 0;
    if (parts.length === 0) {
        sel.innerHTML = '<option value="">No parts found for this story</option>';
    }
}

// ── Score hint ─────────────────────────────────────────────────
function updateScoreHint(input) {
    const v   = parseFloat(input.value);
    const el  = document.getElementById('scoreHint');
    if (isNaN(v)) { el.textContent = ''; return; }

    // Auto-suggest label type
    const typeEl = document.getElementById('labelType');
    if (v >= 70)      { el.textContent='Looks like Correct'; el.style.color='var(--success)'; if(!typeEl.dataset.manual) typeEl.value='correct'; }
    else if (v >= 30) { el.textContent='Looks like Partial';  el.style.color='var(--warning)'; if(!typeEl.dataset.manual) typeEl.value='partial'; }
    else              { el.textContent='Looks like Wrong';     el.style.color='var(--danger)';  if(!typeEl.dataset.manual) typeEl.value='wrong'; }
}

function syncLabelType() {
    document.getElementById('labelType').dataset.manual = '1';
}

// ── Edit prefill ───────────────────────────────────────────────
async function prefillEdit(d) {
    // Scroll form into view
    document.getElementById('formCard').scrollIntoView({behavior:'smooth', block:'start'});

    document.getElementById('formAction').value     = 'edit';
    document.getElementById('editId').value          = d.id;
    document.getElementById('formTitle').textContent = 'Edit Entry #' + d.id;
    document.getElementById('formModeBadge').textContent  = 'EDITING';
    document.getElementById('formModeBadge').className    = 'form-mode-badge edit';
    document.getElementById('saveBtn').textContent   = 'Save Changes';
    document.getElementById('cancelEditBtn').classList.add('show');

    document.getElementById('storySelect').value   = d.story_id;
    document.getElementById('predText').value       = d.prediction_text;
    document.getElementById('labelScore').value     = d.label_score;
    document.getElementById('labelType').value      = d.label_type;
    document.getElementById('labelType').dataset.manual = '1'; // don't auto-change
    document.getElementById('aiScore').value        = d.ai_score;
    document.getElementById('entailment').value     = d.entailment;
    document.getElementById('contradiction').value  = d.contradiction;
    document.getElementById('entityScore').value    = d.entity_score;
    document.getElementById('cosineScore').value    = d.cosine_score;
    document.getElementById('notesField').value     = d.notes;

    // Update score hint
    updateScoreHint(document.getElementById('labelScore'));

    // Load parts for selected story then pre-select
    await loadParts(d.part_id);
}

// ── Toggle expanded prediction text ───────────────────────────
function toggleText(id) {
    const s = document.getElementById('short-'+id);
    const f = document.getElementById('full-'+id);
    if (!f) return;
    if (f.style.display === 'block') {
        s.style.display = 'block'; f.style.display = 'none';
    } else {
        s.style.display = 'none'; f.style.display = 'block';
    }
}

// ── Inline delete confirm ──────────────────────────────────────
function confirmDelete(id) {
    // Hide any other open confirms first
    document.querySelectorAll('.del-confirm-row.show').forEach(el => el.classList.remove('show'));
    document.getElementById('del-confirm-'+id).classList.add('show');
}

function cancelDelete(id) {
    document.getElementById('del-confirm-'+id).classList.remove('show');
}

// ── Filter apply ───────────────────────────────────────────────
function applyFilter() {
    const story  = document.getElementById('filterStory')?.value  || '';
    const type   = document.getElementById('filterType')?.value   || 'all';
    const search = document.getElementById('filterSearch')?.value.trim()
                || document.getElementById('topbarSearch')?.value.trim()
                || '';
    const params = new URLSearchParams();
    if (story)         params.set('story_id',   story);
    if (type !== 'all') params.set('label_type', type);
    if (search)        params.set('q',           search);
    location.href = 'admin_dataset.php?' + params.toString();
}

// ── On load: restore edit state from URL hash if any ──────────
// (nothing needed — form resets naturally on page load)
</script>
</body>
</html>
