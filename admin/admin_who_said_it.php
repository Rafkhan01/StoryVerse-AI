<?php
/* ══════════════════════════════════════════════════════════════
   StoryVerse — Who Said It? Admin Panel
   File : admin_who_said_it.php
   Place: project root (same folder as db_connect.php)

   AUTO-CREATES character_quiz table on first run if not exists.
══════════════════════════════════════════════════════════════ */
require_once 'db_connect.php';
session_start();

// ── Auth guard ──────────────────────────────────────────────
// Matches your signin.php session variables exactly:
//   Admin login  → $_SESSION['is_admin'] = true, $_SESSION['user_id'] = 'admin'
//   Author login → $_SESSION['user_id'] = int, $_SESSION['user_type'] = 'author'
$is_admin  = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$is_author = !empty($_SESSION['user_id'])
             && ($_SESSION['user_type'] ?? '') === 'author';

if (!$is_admin && !$is_author) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;background:#07080d;color:#f0eef8;min-height:100vh;">
         <h2 style="color:#ef4444;">Access Denied</h2>
         <p style="color:#8b8fa8;margin-top:8px;">Only admins and authors can access the question manager.</p>
         <p style="color:#8b8fa8;margin-top:4px;">
           Debug — is_admin: <strong style="color:#eab308;">'.(isset($_SESSION['is_admin'])?var_export($_SESSION['is_admin'],true):'not set').'</strong>
           | user_id: <strong style="color:#eab308;">'.(isset($_SESSION['user_id'])?$_SESSION['user_id']:'not set').'</strong>
           | user_type: <strong style="color:#eab308;">'.(isset($_SESSION['user_type'])?$_SESSION['user_type']:'not set').'</strong>
         </p>
         <a href="../signin.php" style="color:#eab308;margin-top:16px;display:inline-block;">← Sign in</a>
         </div>');
}
// created_by: NULL for admin (user_id='admin' string), int for authors
$admin_id = $is_admin ? null : (int)$_SESSION['user_id'];
// $admin_id already set above in auth guard

// ── Auto-create table if missing ───────────────────────────
$pdo->exec("
  CREATE TABLE IF NOT EXISTS character_quiz (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    story_id    INT NOT NULL,
    part_id     INT NOT NULL,
    dialogue    TEXT NOT NULL,
    `character` VARCHAR(100) NOT NULL,
    distractors VARCHAR(500) NOT NULL DEFAULT '[]',
    created_by  INT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (story_id) REFERENCES stories(story_id) ON DELETE CASCADE,
    FOREIGN KEY (part_id)  REFERENCES story_parts(part_id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ══════════════════════════════════════════════════════════════
   PHP HELPERS
══════════════════════════════════════════════════════════════ */

/**
 * extract_dialogues()
 * Scans a story-part's raw content and returns an array of
 * [ 'dialogue' => string, 'character' => string (best guess) ]
 *
 * Patterns matched (in order of confidence):
 *  1. "Dialogue," Character said/replied/asked/etc.
 *  2. Character said/replied/asked: "Dialogue"
 *  3. Bare quoted strings with no attribution (character = '')
 */
function extract_dialogues(string $text): array {
    $results = [];
    $seen    = [];

    // Normalise line endings and smart quotes to plain ASCII
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = str_replace(['\xe2\x80\x9c', '\xe2\x80\x9d',  // UTF-8 curly quotes
                         '\xc2\x93',      '\xc2\x94'],       // Windows-1252 curly quotes
                        '"', $text);

    $verbs = 'said|replied|asked|answered|whispered|shouted|muttered|called|'
           . 'cried|yelled|snapped|breathed|sighed|laughed|added|continued|'
           . 'began|declared|insisted|protested|warned|urged|pleaded|hissed|'
           . 'growled|exclaimed|remarked|noted|observed|stated|told|thought';

    // Pattern 1: "Dialogue[,!?.]" Character verb  (dialogue first, attribution after)
    // Handles: "Just a minor anomaly," he murmured  /  "Text." Dr. Lena said
    preg_match_all(
        '/\"([^"]{4,300})\"[,\.!?]?\s+([A-Z][a-zA-Z]*(?:\s+[A-Z][a-zA-Z]*)?)\s+(?:'.$verbs.')/',
        $text, $m1, PREG_SET_ORDER
    );
    foreach ($m1 as $m) {
        $key = md5($m[1]);
        if (!isset($seen[$key])) {
            $results[] = ['dialogue' => trim($m[1]), 'character' => trim($m[2])];
            $seen[$key] = 1;
        }
    }

    // Pattern 2: Character verb "Dialogue"  (attribution before quote)
    // Handles: Lena said, "Text"  /  Dr. Aris replied: "Text"
    preg_match_all(
        '/([A-Z][a-zA-Z]*(?:\s+[A-Z][a-zA-Z]*)?)\s+(?:'.$verbs.')[,:]?\s+\"([^"]{4,300})\"/',
        $text, $m2, PREG_SET_ORDER
    );
    foreach ($m2 as $m) {
        $key = md5($m[2]);
        if (!isset($seen[$key])) {
            $results[] = ['dialogue' => trim($m[2]), 'character' => trim($m[1])];
            $seen[$key] = 1;
        }
    }

    // Pattern 3: Any remaining quoted text (no clear attribution found)
    preg_match_all('/"([^"]{4,300})"/', $text, $m3, PREG_SET_ORDER);
    foreach ($m3 as $m) {
        $key = md5($m[1]);
        if (!isset($seen[$key])) {
            $results[] = ['dialogue' => trim($m[1]), 'character' => ''];
            $seen[$key] = 1;
        }
    }

    return array_slice($results, 0, 30);
}

/**
 * extract_characters()
 * Collects all unique proper-noun names found near attribution verbs
 * across all parts of a story — used to auto-fill distractor options.
 */
function extract_characters_from_story(PDO $pdo, int $story_id): array {
    $stmt = $pdo->prepare("SELECT content FROM story_parts WHERE story_id=:sid");
    $stmt->execute([':sid'=>$story_id]);
    $parts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $verbs = 'said|replied|asked|answered|whispered|shouted|muttered|called|'
           . 'cried|yelled|snapped|breathed|sighed|laughed|added|continued|'
           . 'began|declared|insisted|protested|warned|urged|pleaded|hissed|'
           . 'growled|exclaimed|remarked|noted|observed|stated|told|thought';

    $names = [];
    foreach ($parts as $content) {
        preg_match_all('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s+(?:'.$verbs.')/u', $content, $m);
        foreach ($m[1] as $name) $names[trim($name)] = 1;
        preg_match_all('/(?:'.$verbs.')\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/u', $content, $m2);
        foreach ($m2[1] as $name) $names[trim($name)] = 1;
    }
    $ignore = ['He','She','They','It','His','Her','The','This','That','There','I','We'];
    foreach ($ignore as $w) unset($names[$w]);
    return array_values(array_keys($names));
}

/* ══════════════════════════════════════════════════════════════
   AJAX ENDPOINTS
══════════════════════════════════════════════════════════════ */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    /* ── Stories list — show ALL stories (not just ones with questions) ── */
    if ($_GET['ajax'] === 'stories') {
        $stmt = $pdo->query(
            "SELECT s.story_id, s.title, s.category, s.total_parts,
                    COUNT(cq.id) AS question_count
             FROM   stories s
             LEFT JOIN character_quiz cq ON cq.story_id = s.story_id
             WHERE  s.total_parts > 0
             GROUP  BY s.story_id
             ORDER  BY s.last_updated DESC
             LIMIT  50"
        );
        echo json_encode(['stories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    /* ── Parts for a story ── */
    if ($_GET['ajax'] === 'parts' && isset($_GET['story_id'])) {
        $sid  = (int)$_GET['story_id'];
        $stmt = $pdo->prepare("SELECT part_id, part_number FROM story_parts WHERE story_id=:s ORDER BY part_number");
        $stmt->execute([':s'=>$sid]);
        echo json_encode(['parts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    /* ── Auto-extract dialogues from a part ── */
    if ($_GET['ajax'] === 'extract' && isset($_GET['part_id'])) {
        $pid  = (int)$_GET['part_id'];
        $stmt = $pdo->prepare("SELECT sp.content, sp.story_id FROM story_parts sp WHERE part_id=:p");
        $stmt->execute([':p'=>$pid]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['error'=>'Part not found']); exit; }

        $dialogues   = extract_dialogues($row['content']);
        $all_chars   = extract_characters_from_story($pdo, (int)$row['story_id']);

        echo json_encode(['dialogues'=>$dialogues, 'characters'=>$all_chars]);
        exit;
    }

    /* ── Existing saved questions for a part ── */
    if ($_GET['ajax'] === 'saved' && isset($_GET['part_id'])) {
        $pid  = (int)$_GET['part_id'];
        $stmt = $pdo->prepare("SELECT id, dialogue, `character`, distractors FROM character_quiz WHERE part_id=:p ORDER BY id");
        $stmt->execute([':p'=>$pid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) $r['distractors'] = json_decode($r['distractors'], true) ?? [];
        echo json_encode(['saved'=>$rows]);
        exit;
    }

    /* ── Save / upsert questions ── */
    if ($_GET['ajax'] === 'save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $body     = json_decode(file_get_contents('php://input'), true);
        $story_id = (int)($body['story_id']??0);
        $part_id  = (int)($body['part_id']??0);
        $items    = $body['items'] ?? [];

        if (!$story_id || !$part_id || !count($items)) {
            echo json_encode(['success'=>false,'message'=>'Missing data']); exit;
        }

        // Delete existing for this part (full replace strategy)
        $del = $pdo->prepare("DELETE FROM character_quiz WHERE part_id=:p");
        $del->execute([':p'=>$part_id]);

        $ins = $pdo->prepare(
            "INSERT INTO character_quiz (story_id, part_id, dialogue, `character`, distractors, created_by)
             VALUES (:sid,:pid,:dlg,:chr,:dis,:cby)"
        );
        $saved = 0;
        foreach ($items as $item) {
            $dlg = trim($item['dialogue']??'');
            $chr = trim($item['character']??'');
            $dis = array_values(array_filter(array_map('trim', $item['distractors']??[])));
            if (!$dlg || !$chr) continue;
            $ins->execute([':sid'=>$story_id,':pid'=>$part_id,':dlg'=>$dlg,':chr'=>$chr,':dis'=>json_encode($dis),':cby'=>$admin_id === null ? null : $admin_id]);
            $saved++;
        }
        echo json_encode(['success'=>true,'saved'=>$saved]);
        exit;
    }

    /* ── Delete single question ── */
    if ($_GET['ajax'] === 'delete' && $_SERVER['REQUEST_METHOD']==='POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id']??0);
        if (!$id) { echo json_encode(['success'=>false]); exit; }
        $stmt = $pdo->prepare("DELETE FROM character_quiz WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        echo json_encode(['success'=>true]);
        exit;
    }

    echo json_encode(['error'=>'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Who Said It? Admin — StoryVerse</title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root {
    --bg-void:#07080d; --bg-surface:#12151f; --bg-card:#171b28; --bg-raised:#1e2335;
    --border-dim:rgba(255,255,255,0.07); --border-glow:rgba(234,179,8,0.35);
    --text-primary:#f0eef8; --text-secondary:#8b8fa8; --text-muted:#4a4d60;
    --accent-gold:#eab308; --accent-violet:#8b5cf6; --accent-teal:#2dd4bf;
    --success:#22c55e; --danger:#ef4444;
  }
  html,body { min-height:100%; background:var(--bg-void); color:var(--text-primary); font-family:'DM Sans',sans-serif; font-size:14px; line-height:1.6; }
  .amb { position:fixed; pointer-events:none; z-index:0; border-radius:50%; filter:blur(120px); }
  .amb-1 { width:500px;height:500px;background:#eab308;opacity:.06;top:-100px;right:-100px; }
  .amb-2 { width:400px;height:400px;background:#8b5cf6;opacity:.05;bottom:0;left:-100px; }
  .wrapper { position:relative; z-index:1; min-height:100vh; display:flex; flex-direction:column; }

  /* HEADER */
  header { display:flex;align-items:center;justify-content:space-between;padding:16px 36px;border-bottom:1px solid var(--border-dim);background:rgba(13,15,24,.9);backdrop-filter:blur(16px);position:sticky;top:0;z-index:100; }
  .logo  { display:flex;align-items:center;gap:10px;text-decoration:none; }
  .logo-icon { width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#eab308,#f97316);display:flex;align-items:center;justify-content:center;font-size:16px; }
  .logo-name { font-family:'Cinzel',serif;font-size:15px;font-weight:700;color:var(--text-primary); }
  .logo-sub  { font-size:10px;color:var(--text-muted);letter-spacing:.12em;text-transform:uppercase; }
  .admin-pill { display:flex;align-items:center;gap:6px;padding:5px 12px;border:1px solid rgba(234,179,8,.3);border-radius:16px;font-size:11px;color:var(--accent-gold); }

  main { flex:1; max-width:1100px; width:100%; margin:0 auto; padding:36px 24px 80px; display:grid; grid-template-columns:280px 1fr; gap:28px; align-items:start; }

  /* SIDEBAR */
  .sidebar { position:sticky; top:80px; }
  .panel { background:var(--bg-card);border:1px solid var(--border-dim);border-radius:16px;padding:20px;margin-bottom:16px; }
  .panel-title { font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--text-muted);font-weight:500;margin-bottom:14px; }

  select, input[type=text], textarea {
    width:100%;background:var(--bg-surface);border:1px solid var(--border-dim);border-radius:10px;
    padding:9px 12px;color:var(--text-primary);font-family:'DM Sans',sans-serif;font-size:13px;
    outline:none;transition:border-color .18s;
  }
  select:focus, input:focus, textarea:focus { border-color:rgba(234,179,8,.4); }
  select { cursor:pointer; }
  select option { background:var(--bg-card); }

  .btn-gold {
    display:inline-flex;align-items:center;gap:8px;padding:10px 20px;
    background:linear-gradient(135deg,#eab308,#f97316);border:none;border-radius:10px;
    font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;color:#000;
    cursor:pointer;transition:all .2s;width:100%;justify-content:center;margin-top:10px;
  }
  .btn-gold:hover { transform:translateY(-1px);box-shadow:0 6px 20px rgba(234,179,8,.35); }
  .btn-gold:disabled { opacity:.4;cursor:not-allowed;transform:none;box-shadow:none; }
  .btn-outline {
    display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
    background:transparent;border:1px solid var(--border-dim);border-radius:8px;
    font-family:'DM Sans',sans-serif;font-size:13px;color:var(--text-secondary);
    cursor:pointer;transition:all .18s;
  }
  .btn-outline:hover { border-color:rgba(255,255,255,.2);color:var(--text-primary); }
  .btn-danger {
    display:inline-flex;align-items:center;gap:6px;padding:6px 12px;
    background:transparent;border:1px solid rgba(239,68,68,.3);border-radius:8px;
    font-size:12px;color:var(--danger);cursor:pointer;transition:all .18s;
  }
  .btn-danger:hover { background:rgba(239,68,68,.1); }

  /* STAT PILLS */
  .stat-pills { display:flex;gap:8px;flex-wrap:wrap;margin-top:12px; }
  .stat-pill  { padding:4px 10px;border-radius:12px;font-size:11px;font-weight:500; }
  .pill-gold   { background:rgba(234,179,8,.12);color:var(--accent-gold);border:1px solid rgba(234,179,8,.25); }
  .pill-violet { background:rgba(139,92,246,.12);color:#a78bfa;border:1px solid rgba(139,92,246,.25); }

  /* SKELETON */
  .skeleton { background:linear-gradient(90deg,var(--bg-card) 25%,var(--bg-raised) 50%,var(--bg-card) 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:10px; }
  @keyframes shimmer { from{background-position:200% 0} to{background-position:-200% 0} }

  /* QUESTION CARDS */
  .q-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px; }
  .q-header-title { font-family:'Cinzel',serif;font-size:18px;font-weight:600; }
  .q-actions { display:flex;gap:10px;align-items:center;flex-wrap:wrap; }

  .q-card {
    background:var(--bg-card);border:1px solid var(--border-dim);border-radius:14px;
    padding:18px;margin-bottom:14px;transition:border-color .2s;
    position:relative;
  }
  .q-card.unsaved { border-color:rgba(234,179,8,.3); }
  .q-card.saved-ok { border-color:rgba(34,197,94,.3); }

  .q-card-header { display:flex;align-items:center;gap:10px;margin-bottom:12px; }
  .q-num   { width:26px;height:26px;flex-shrink:0;border-radius:7px;background:var(--bg-raised);border:1px solid var(--border-dim);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--text-muted); }
  .q-badge { font-size:10px;letter-spacing:.1em;text-transform:uppercase;padding:2px 8px;border-radius:8px; }
  .q-badge.extracted { background:rgba(139,92,246,.15);color:#a78bfa;border:1px solid rgba(139,92,246,.25); }
  .q-badge.manual    { background:rgba(45,212,191,.12);color:var(--accent-teal);border:1px solid rgba(45,212,191,.25); }
  .q-badge.db        { background:rgba(34,197,94,.12);color:var(--success);border:1px solid rgba(34,197,94,.25); }

  .field-row { display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px; }
  .field-label { font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px; }
  .dist-row { display:flex;gap:8px;margin-bottom:6px; }
  .dist-row input { flex:1; }
  .dist-add { display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--accent-teal);background:transparent;border:none;cursor:pointer;padding:4px 0;margin-top:4px; }

  .empty-q { text-align:center;padding:60px 20px;color:var(--text-muted);border:1px dashed var(--border-dim);border-radius:14px; }
  .empty-q .ico { font-size:36px;margin-bottom:12px; }

  .toast { position:fixed;bottom:28px;right:28px;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:500;z-index:999;transform:translateY(80px);opacity:0;transition:all .35s ease; }
  .toast.show { transform:translateY(0);opacity:1; }
  .toast.success { background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.35);color:#22c55e; }
  .toast.error   { background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:var(--danger); }

  .loading-overlay { display:none;position:absolute;inset:0;background:rgba(7,8,13,.7);border-radius:14px;align-items:center;justify-content:center;z-index:10; }
  .loading-overlay.show { display:flex; }
  .spinner { width:30px;height:30px;border:3px solid var(--border-dim);border-top-color:var(--accent-gold);border-radius:50%;animation:spin .7s linear infinite; }
  @keyframes spin { to{transform:rotate(360deg)} }

  @media(max-width:768px){ main{grid-template-columns:1fr;} .sidebar{position:static;} .field-row{grid-template-columns:1fr;} }
</style>
</head>
<body>
<div class="amb amb-1"></div>
<div class="amb amb-2"></div>
<div class="wrapper">

<header>
  <a href="index.php" class="logo">
    <div class="logo-icon">🕵️</div>
    <div>
      <div class="logo-name">StoryVerse</div>
      <div class="logo-sub">Admin Panel</div>
    </div>
  </a>
  <div style="display:flex;align-items:center;gap:14px;">
    <a href="admin_games.php" style="display:inline-flex;align-items:center;gap:7px;padding:7px 16px;border-radius:8px;border:1px solid rgba(234,179,8,0.35);background:rgba(234,179,8,0.07);color:#eab308;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;text-decoration:none;transition:background 0.18s;" onmouseover="this.style.background='rgba(234,179,8,0.14)'" onmouseout="this.style.background='rgba(234,179,8,0.07)'">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Admin Dashboard
    </a>
    <div class="admin-pill">⚙ Who Said It? — Question Manager</div>
  </div>
</header>

<main>

  <!-- ══ SIDEBAR ══ -->
  <div class="sidebar">
    <div class="panel">
      <div class="panel-title">1. Select Story</div>
      <select id="sel-story" onchange="onStoryChange()">
        <option value="">Loading stories…</option>
      </select>
    </div>
    <div class="panel" id="panel-part" style="display:none;">
      <div class="panel-title">2. Select Part</div>
      <select id="sel-part" onchange="onPartChange()">
        <option value="">— choose part —</option>
      </select>
    </div>
    <div class="panel" id="panel-actions" style="display:none;">
      <div class="panel-title">3. Actions</div>
      <button class="btn-gold" id="btn-extract" onclick="extractDialogues()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        Auto-Extract Dialogues
      </button>
      <button class="btn-gold" style="margin-top:8px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;" id="btn-add-manual" onclick="addManualCard()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Manually
      </button>
      <button class="btn-gold" style="margin-top:8px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;" id="btn-save-all" onclick="saveAll()" disabled>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save All &amp; Verify
      </button>
      <div class="stat-pills" id="stat-pills" style="display:none;">
        <span class="stat-pill pill-gold" id="pill-total">0 total</span>
        <span class="stat-pill pill-violet" id="pill-unsaved">0 unsaved</span>
      </div>
    </div>
  </div>

  <!-- ══ MAIN AREA ══ -->
  <div>
    <div class="q-header">
      <div class="q-header-title" id="area-title">Select a story and part to begin</div>
      <div class="q-actions" id="area-actions" style="display:none;">
        <button class="btn-outline" onclick="clearAll()">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
          Clear All
        </button>
      </div>
    </div>
    <div id="q-area">
      <div class="empty-q">
        <div class="ico">🕵️</div>
        <p>Choose a story and part from the sidebar,<br>then click <strong>Auto-Extract</strong> to get started.</p>
      </div>
    </div>
  </div>

</main>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
/* ═══════════════════════════════════════════════
   Who Said It? — Admin Logic
═══════════════════════════════════════════════ */
const $ = id => document.getElementById(id);
const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

let currentStoryId  = null;
let currentPartId   = null;
let allCharacters   = [];    // auto-extracted from whole story
let cards           = [];    // array of { dialogue, character, distractors[], source, dbId }
let unsavedCount    = 0;

/* ── TOAST ── */
function toast(msg, type='success') {
  const t = $('toast');
  t.textContent = msg; t.className = `toast ${type} show`;
  setTimeout(() => t.classList.remove('show'), 3000);
}

/* ── LOAD STORIES ── */
async function loadStories() {
  const data = await fetch('?ajax=stories').then(r=>r.json()).catch(()=>({stories:[]}));
  const sel  = $('sel-story');
  sel.innerHTML = '<option value="">— choose story —</option>';
  (data.stories||[]).forEach(s => {
    const o = document.createElement('option');
    o.value = s.story_id;
    const qCount = parseInt(s.question_count) || 0;
    o.textContent = s.title + (qCount > 0 ? ` (${qCount} questions)` : ' — no questions yet');
    sel.appendChild(o);
  });
}

async function onStoryChange() {
  currentStoryId = $('sel-story').value || null;
  currentPartId  = null;
  cards          = [];
  renderCards();

  if (!currentStoryId) { $('panel-part').style.display='none'; $('panel-actions').style.display='none'; return; }

  $('panel-part').style.display   = 'block';
  $('panel-actions').style.display = 'block';
  $('btn-extract').disabled        = true;
  $('btn-add-manual').disabled     = true;
  $('btn-save-all').disabled       = true;

  const data = await fetch(`?ajax=parts&story_id=${currentStoryId}`).then(r=>r.json()).catch(()=>({parts:[]}));
  const sel  = $('sel-part');
  sel.innerHTML = '<option value="">— choose part —</option>';
  (data.parts||[]).forEach(p => {
    const o = document.createElement('option');
    o.value = p.part_id; o.textContent = `Part ${p.part_number}`;
    sel.appendChild(o);
  });
}

async function onPartChange() {
  currentPartId = $('sel-part').value || null;
  if (!currentPartId) return;
  $('btn-extract').disabled    = false;
  $('btn-add-manual').disabled = false;

  // Load already-saved questions for this part
  const data = await fetch(`?ajax=saved&part_id=${currentPartId}`).then(r=>r.json()).catch(()=>({saved:[]}));
  cards = (data.saved||[]).map(r => ({
    dbId: r.id, dialogue: r.dialogue, character: r.character,
    distractors: r.distractors, source: 'db'
  }));
  updateStats();
  renderCards();
  $('area-title').textContent = `Questions — Part ${$('sel-part').options[$('sel-part').selectedIndex].text}`;
  $('area-actions').style.display = 'flex';
}

/* ── AUTO-EXTRACT ── */
async function extractDialogues() {
  if (!currentPartId) return;
  $('btn-extract').disabled = true;
  $('btn-extract').textContent = 'Extracting…';

  const data = await fetch(`?ajax=extract&part_id=${currentPartId}`).then(r=>r.json()).catch(()=>({dialogues:[],characters:[]}));
  allCharacters = data.characters || [];

  // Merge extracted (skip if dialogue already in cards)
  const existing = new Set(cards.map(c => c.dialogue));
  let added = 0;
  (data.dialogues||[]).forEach(d => {
    if (!existing.has(d.dialogue)) {
      // Auto-fill distractors: pick 2 random characters that are NOT the correct one
      const pool = allCharacters.filter(c => c !== d.character);
      const dist = pool.sort(()=>Math.random()-.5).slice(0,2);
      cards.push({ dbId:null, dialogue:d.dialogue, character:d.character, distractors:dist, source:'extracted' });
      added++;
    }
  });

  $('btn-extract').disabled = false;
  $('btn-extract').innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg> Auto-Extract Dialogues`;

  updateStats();
  renderCards();
  toast(`Extracted ${added} dialogue${added!==1?'s':''} — review and save.`);
}

/* ── ADD MANUAL ── */
function addManualCard() {
  cards.unshift({ dbId:null, dialogue:'', character:'', distractors:['',''], source:'manual' });
  updateStats();
  renderCards();
  // Focus first input
  setTimeout(() => { const first = document.querySelector('.q-card textarea'); if(first) first.focus(); }, 50);
}

/* ── RENDER CARDS ── */
function renderCards() {
  const area = $('q-area');
  if (!cards.length) {
    area.innerHTML = '<div class="empty-q"><div class="ico">🕵️</div><p>No questions yet. Use <strong>Auto-Extract</strong> or <strong>Add Manually</strong>.</p></div>';
    return;
  }
  area.innerHTML = '';
  cards.forEach((card, idx) => {
    const div = document.createElement('div');
    div.className = `q-card ${card.source==='db'?'saved-ok':'unsaved'}`;
    div.dataset.idx = idx;

    const badgeClass = card.source==='db' ? 'db' : card.source==='extracted' ? 'extracted' : 'manual';
    const badgeText  = card.source==='db' ? '✓ Saved' : card.source==='extracted' ? 'Auto-extracted' : 'Manual';

    // Distractor inputs
    let distHtml = (card.distractors||[]).map((d,di) => `
      <div class="dist-row" data-di="${di}">
        <input type="text" placeholder="Wrong character name" value="${esc(d)}" onchange="updateDist(${idx},${di},this.value)">
        <button class="btn-danger" onclick="removeDist(${idx},${di})" title="Remove">✕</button>
      </div>`).join('');

    // Character datalist
    const dlId = `dl-${idx}`;
    const dlOpts = allCharacters.map(c=>`<option value="${esc(c)}">`).join('');

    div.innerHTML = `
      <datalist id="${dlId}">${dlOpts}</datalist>
      <div class="q-card-header">
        <div class="q-num">${idx+1}</div>
        <span class="q-badge ${badgeClass}">${badgeText}</span>
        <button class="btn-danger" style="margin-left:auto;" onclick="removeCard(${idx})">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
          Remove
        </button>
      </div>
      <div class="field-label">Dialogue / Action shown to player</div>
      <textarea rows="2" style="margin-bottom:12px;resize:vertical;" placeholder='e.g. "We need to talk," or He slammed the door shut.' onchange="updateCard(${idx},'dialogue',this.value)">${esc(card.dialogue)}</textarea>
      <div class="field-row">
        <div>
          <div class="field-label">✓ Correct character</div>
          <input type="text" list="${dlId}" placeholder="Character name" value="${esc(card.character)}" onchange="updateCard(${idx},'character',this.value)">
        </div>
        <div>
          <div class="field-label">✗ Wrong options (distractors)</div>
          ${distHtml}
          ${card.distractors.length < 3 ? `<button class="dist-add" onclick="addDist(${idx})">+ Add distractor</button>` : ''}
        </div>
      </div>`;
    area.appendChild(div);
  });
  $('btn-save-all').disabled = cards.length === 0;
}

/* ── CARD MUTATIONS ── */
function updateCard(idx, field, val) { cards[idx][field] = val; cards[idx].source = cards[idx].source==='db'?'db':'edited'; }
function updateDist(idx, di, val)    { cards[idx].distractors[di] = val; }
function removeDist(idx, di)         { cards[idx].distractors.splice(di,1); renderCards(); }
function addDist(idx)                { cards[idx].distractors.push(''); renderCards(); }
function removeCard(idx)             { cards.splice(idx,1); updateStats(); renderCards(); }
function clearAll()                  { if(confirm('Remove all questions from the list?')) { cards=[]; updateStats(); renderCards(); } }

function updateStats() {
  unsavedCount = cards.filter(c=>c.source!=='db').length;
  $('stat-pills').style.display = 'flex';
  $('pill-total').textContent   = cards.length + ' total';
  $('pill-unsaved').textContent = unsavedCount + ' unsaved';
}

/* ── SAVE ALL ── */
async function saveAll() {
  if (!currentStoryId || !currentPartId) { toast('Select a story and part first.','error'); return; }

  // Validate
  const invalid = cards.filter(c => !c.dialogue.trim() || !c.character.trim());
  if (invalid.length) { toast(`${invalid.length} card(s) are missing dialogue or character name.`,'error'); return; }

  $('btn-save-all').disabled  = true;
  $('btn-save-all').innerHTML = 'Saving…';

  try {
    const res  = await fetch('?ajax=save', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ story_id: currentStoryId, part_id: currentPartId, items: cards })
    });
    const data = await res.json();
    if (data.success) {
      toast(`✓ ${data.saved} question${data.saved!==1?'s':''} saved!`);
      cards.forEach(c => { c.source='db'; c.dbId=c.dbId||1; });
      updateStats();
      renderCards();
    } else {
      toast(data.message||'Save failed.','error');
    }
  } catch(e) { toast('Network error.','error'); }

  $('btn-save-all').disabled  = false;
  $('btn-save-all').innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save All &amp; Verify`;
}

loadStories();
</script>
</body>
</html>