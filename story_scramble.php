<?php
/* ══════════════════════════════════════════════════════════════
   StoryVerse — Story Scramble Game
   File : story_scramble.php
   Place: project root  (same folder as db_connect.php)
   Fix 1: Sentences are now randomly sampled every game session
   Fix 2: INSERT matches actual game_scores schema (no max_score)
══════════════════════════════════════════════════════════════ */

require_once 'db_connect.php';   // gives us $pdo (PDO instance)
session_start();

$current_user_id = $_SESSION['user_id'] ?? null;   // matches your users.user_id

/* ══════════════════════════════════════════════════════════════
   PHP HELPER — split_sentences_random()
   ─────────────────────────────────────────────────────────────
   FIX #1 — RANDOMNESS:
   Previously we always took the "middle slice" which gave the
   same sentences every single game. Now we:
     1. Split ALL valid sentences from the content
     2. If there are more than $max available, randomly pick
        $count of them using array_rand()
     3. Re-sort the picked sentences back into their original
        narrative order (so the "correct" order is preserved,
        but WHICH sentences appear is random each game)
══════════════════════════════════════════════════════════════ */
function split_sentences_random(string $text, int $min = 4, int $max = 6): array {
    // Normalise whitespace
    $text = trim(preg_replace('/\s+/', ' ', $text));

    // Split after  . ! ?  followed by whitespace or end of string.
    // Negative lookbehind avoids splitting common abbreviations.
    $pattern = '/(?<![A-Z][a-z])(?<![Mm]r|[Mm]rs|[Dd]r|[Pp]rof|[Ss]t|[Vv]s|[Ee]tc)(?<=[.!?])\s+/u';
    $all = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);

    // Keep only sentences with real content (> 20 chars)
    $valid = array_values(array_filter($all, fn($s) => mb_strlen(trim($s)) > 20));

    $count = count($valid);
    if ($count < $min) return [];   // not enough material

    if ($count <= $max) {
        // Use all sentences — shuffle will happen on the JS side
        return $valid;
    }

    // ── RANDOM PICK ──────────────────────────────────────────
    // Decide how many sentences to use this game (between $min and $max)
    $pick = mt_rand($min, $max);
    $pick = min($pick, $count);

    // Pick $pick random indices from the valid array
    $indices = (array) array_rand($valid, $pick);
    sort($indices);   // re-sort to preserve correct narrative order

    return array_map(fn($i) => $valid[$i], $indices);
}

/* ══════════════════════════════════════════════════════════════
   AJAX ENDPOINTS
══════════════════════════════════════════════════════════════ */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    /* ── 1. STORY LIST ─────────────────────────────────────── */
    if ($_GET['ajax'] === 'stories') {
        $stmt = $pdo->query(
            "SELECT story_id, title, category, description,
                    cover_image_url, total_parts, view_count, like_count
             FROM   stories
             WHERE  total_parts > 0
             ORDER  BY last_updated DESC
             LIMIT  20"
        );
        echo json_encode(['stories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    /* ── 2. PARTS FOR A STORY ──────────────────────────────── */
    if ($_GET['ajax'] === 'parts' && isset($_GET['story_id'])) {
        $story_id = (int) $_GET['story_id'];
        $stmt = $pdo->prepare(
            "SELECT part_id, part_number, content
             FROM   story_parts
             WHERE  story_id = :sid
             ORDER  BY part_number ASC"
        );
        $stmt->execute([':sid' => $story_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $parts = [];
        foreach ($rows as $row) {
            // Use the random splitter — different slice each call
            $sentences = split_sentences_random($row['content']);
            if (count($sentences) < 4) continue;
            $parts[] = [
                'part_id'     => (int) $row['part_id'],
                'part_number' => (int) $row['part_number'],
                'label'       => 'Part ' . $row['part_number'],
                'sentences'   => $sentences,
            ];
        }
        echo json_encode(['parts' => $parts]);
        exit;
    }

    /* ── 3. RE-ROLL SENTENCES for a specific part ──────────── */
    // Called by JS every time the user hits "Play Again" on the
    // same part — fetches a fresh random sentence slice.
    if ($_GET['ajax'] === 'reroll' && isset($_GET['part_id'])) {
        $part_id = (int) $_GET['part_id'];
        $stmt = $pdo->prepare(
            "SELECT part_id, part_number, content
             FROM   story_parts
             WHERE  part_id = :pid
             LIMIT  1"
        );
        $stmt->execute([':pid' => $part_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['error' => 'Part not found']);
            exit;
        }

        $sentences = split_sentences_random($row['content']);
        echo json_encode([
            'part_id'   => (int) $row['part_id'],
            'sentences' => $sentences,
        ]);
        exit;
    }

    /* ── 4. SAVE SCORE ─────────────────────────────────────── */
    // FIX #2 — INSERT now matches your actual game_scores schema:
    // id, user_id, story_part_id, score, correct_pairs,
    // total_pairs, time_left_seconds, played_at
    // (no max_score column — that was the crash cause)
    if ($_GET['ajax'] === 'save_score' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);

        $part_id       = isset($body['story_part_id']) ? (int) $body['story_part_id'] : 0;
        $score         = isset($body['score'])         ? (int) $body['score']         : 0;
        $correct_pairs = isset($body['correct_pairs']) ? (int) $body['correct_pairs'] : 0;
        $total_pairs   = isset($body['total_pairs'])   ? (int) $body['total_pairs']   : 0;
        $time_left     = isset($body['time_left'])     ? (int) $body['time_left']     : 0;

        if (!$part_id) {
            echo json_encode(['success' => false, 'message' => 'Missing part_id']);
            exit;
        }

        if (!$current_user_id) {
            echo json_encode(['success' => false, 'message' => 'Guest — score not saved']);
            exit;
        }

        // Exact columns from your game_scores table
        $stmt = $pdo->prepare(
            "INSERT INTO game_scores
                (user_id, story_part_id, score, correct_pairs, total_pairs, time_left_seconds, played_at)
             VALUES
                (:uid, :pid, :score, :cp, :tp, :tl, NOW())"
        );
        $ok = $stmt->execute([
            ':uid'   => $current_user_id,
            ':pid'   => $part_id,
            ':score' => $score,
            ':cp'    => $correct_pairs,
            ':tp'    => $total_pairs,
            ':tl'    => $time_left,
        ]);
        echo json_encode(['success' => $ok, 'insert_id' => $pdo->lastInsertId()]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}
/* ── End PHP — HTML/CSS/JS below ── */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Story Scramble — StoryVerse</title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg-void:    #07080d;
    --bg-surface: #12151f;
    --bg-card:    #171b28;
    --bg-raised:  #1e2335;
    --border-dim:  rgba(255,255,255,0.07);
    --border-glow: rgba(139,92,246,0.35);
    --text-primary:   #f0eef8;
    --text-secondary: #8b8fa8;
    --text-muted:     #4a4d60;
    --accent-violet:       #8b5cf6;
    --accent-violet-bright:#a78bfa;
    --accent-gold:  #eab308;
    --accent-teal:  #2dd4bf;
    --success: #22c55e;
    --danger:  #ef4444;
    --score-gradient: linear-gradient(135deg, #8b5cf6, #2dd4bf);
    --drag-over:   rgba(139,92,246,0.28);
  }

  html, body {
    min-height: 100%; background: var(--bg-void);
    color: var(--text-primary);
    font-family: 'DM Sans', sans-serif; font-size: 15px; line-height: 1.6;
    overflow-x: hidden;
  }
  body::before {
    content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
    background-size: 256px;
  }
  .amb { position: fixed; pointer-events: none; z-index: 0; border-radius: 50%; filter: blur(120px); }
  .amb-1 { width:600px;height:600px;background:#8b5cf6;opacity:.10;top:-200px;right:-100px; }
  .amb-2 { width:400px;height:400px;background:#2dd4bf;opacity:.07;bottom:100px;left:-150px; }
  .amb-3 { width:280px;height:280px;background:#eab308;opacity:.04;top:50%;left:50%;transform:translate(-50%,-50%); }

  .wrapper { position: relative; z-index: 1; min-height: 100vh; display: flex; flex-direction: column; }

  /* HEADER */
  header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 40px; border-bottom: 1px solid var(--border-dim);
    background: rgba(13,15,24,0.85); backdrop-filter: blur(16px);
    position: sticky; top: 0; z-index: 100;
  }
  .logo { display: flex; align-items: center; gap: 12px; text-decoration: none; }
  .logo-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: linear-gradient(135deg,#8b5cf6,#2dd4bf);
    display: flex; align-items: center; justify-content: center; font-size: 18px;
  }
  .logo-name { font-family: 'Cinzel', serif; font-size: 16px; font-weight: 700; color: var(--text-primary); }
  .logo-sub  { font-size: 10px; color: var(--text-muted); letter-spacing: .14em; text-transform: uppercase; }
  .live-badge {
    display: flex; align-items: center; gap: 8px; padding: 6px 14px;
    border: 1px solid var(--border-dim); border-radius: 20px;
    font-size: 12px; color: var(--text-secondary);
  }
  .live-dot { width:6px;height:6px;border-radius:50%;background:var(--accent-teal);animation:pulse 2s infinite; }
  @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.8)} }

  main { flex: 1; max-width: 920px; width: 100%; margin: 0 auto; padding: 48px 24px 80px; }
  .phase { animation: fadeUp .45s ease both; }
  @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }

  /* HERO */
  .eyebrow {
    font-size:11px;letter-spacing:.2em;text-transform:uppercase;
    color:var(--accent-violet);font-weight:500;margin-bottom:10px;
    display:flex;align-items:center;gap:8px;
  }
  .eyebrow::before { content:'';width:22px;height:1px;background:var(--accent-violet); }
  .page-title {
    font-family:'Cinzel',serif;font-size:clamp(26px,5vw,46px);font-weight:700;line-height:1.1;margin-bottom:10px;
    background:linear-gradient(135deg,#f0eef8 0%,#a78bfa 55%,#2dd4bf 100%);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  }
  .page-desc { font-size:15px;color:var(--text-secondary);font-weight:300;max-width:500px;margin-bottom:44px;line-height:1.75; }
  .section-lbl { font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--text-muted);font-weight:500;margin-bottom:14px; }

  /* STORY CARDS */
  .stories-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-bottom:36px; }
  .story-card {
    background:var(--bg-card);border:1px solid var(--border-dim);border-radius:16px;
    padding:20px;cursor:pointer;transition:all .22s ease;position:relative;overflow:hidden;
  }
  .story-card::before {
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,rgba(139,92,246,.07) 0%,transparent 60%);
    opacity:0;transition:opacity .22s;
  }
  .story-card:hover  { border-color:var(--border-glow);transform:translateY(-2px); }
  .story-card:hover::before { opacity:1; }
  .story-card.selected { border-color:var(--accent-violet);background:rgba(139,92,246,.10);box-shadow:0 0 0 1px var(--accent-violet); }
  .story-card.selected::before { opacity:1; }
  .card-cover {
    width:100%;height:90px;object-fit:cover;border-radius:10px;margin-bottom:12px;
    background:var(--bg-raised);display:block;
  }
  .card-no-cover {
    width:100%;height:90px;border-radius:10px;margin-bottom:12px;
    background:linear-gradient(135deg,var(--bg-raised),var(--bg-surface));
    display:flex;align-items:center;justify-content:center;font-size:28px;
  }
  .card-cat   { font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--accent-teal);font-weight:500;margin-bottom:6px; }
  .card-title { font-family:'Cinzel',serif;font-size:14px;font-weight:600;color:var(--text-primary);margin-bottom:5px;line-height:1.3; }
  .card-desc  {
    font-size:12px;color:var(--text-secondary);font-weight:300;line-height:1.5;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  }
  .card-meta { margin-top:12px;padding-top:10px;border-top:1px solid var(--border-dim);display:flex;gap:14px; }
  .card-meta span { font-size:11px;color:var(--text-muted); }
  .card-check {
    position:absolute;top:12px;right:12px;width:22px;height:22px;border-radius:50%;
    background:var(--accent-violet);display:none;align-items:center;justify-content:center;
    font-size:11px;color:#fff;
  }
  .story-card.selected .card-check { display:flex; }

  /* PART BUTTONS */
  .parts-row { display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;margin-bottom:32px; }
  .part-btn {
    padding:8px 18px;border:1px solid var(--border-dim);border-radius:24px;
    background:var(--bg-card);color:var(--text-secondary);
    font-family:'DM Sans',sans-serif;font-size:13px;cursor:pointer;transition:all .18s;
  }
  .part-btn:hover  { border-color:var(--border-glow);color:var(--text-primary); }
  .part-btn.active { background:rgba(139,92,246,.15);border-color:var(--accent-violet);color:var(--accent-violet-bright); }

  /* BUTTONS */
  .btn-play {
    display:inline-flex;align-items:center;gap:10px;padding:13px 30px;
    background:linear-gradient(135deg,#8b5cf6,#6d28d9);border:none;border-radius:12px;
    font-family:'DM Sans',sans-serif;font-size:15px;font-weight:500;color:#fff;
    cursor:pointer;transition:all .22s;
  }
  .btn-play:hover    { transform:translateY(-2px);box-shadow:0 8px 28px rgba(139,92,246,.4); }
  .btn-play:active   { transform:translateY(0); }
  .btn-play:disabled { opacity:.4;cursor:not-allowed;transform:none;box-shadow:none; }
  .btn-secondary {
    display:inline-flex;align-items:center;gap:8px;padding:11px 22px;
    background:transparent;border:1px solid var(--border-dim);border-radius:10px;
    font-family:'DM Sans',sans-serif;font-size:14px;color:var(--text-secondary);
    cursor:pointer;transition:all .18s;
  }
  .btn-secondary:hover { border-color:rgba(255,255,255,.18);color:var(--text-primary); }

  /* SKELETON */
  .skeleton {
    background:linear-gradient(90deg,var(--bg-card) 25%,var(--bg-raised) 50%,var(--bg-card) 75%);
    background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:12px;
  }
  @keyframes shimmer { from{background-position:200% 0} to{background-position:-200% 0} }

  .empty-state { text-align:center;padding:60px 20px;color:var(--text-muted); }
  .empty-state .ico { font-size:40px;margin-bottom:14px; }
  .empty-state p { font-size:14px;line-height:1.7; }

  /* GAME HEADER */
  .game-header {
    display:flex;align-items:flex-start;justify-content:space-between;
    margin-bottom:28px;gap:16px;
  }
  .game-story-title { font-family:'Cinzel',serif;font-size:20px;font-weight:600;margin-bottom:4px; }
  .game-part-lbl    { font-size:13px;color:var(--text-secondary); }
  .score-box {
    background:var(--bg-card);border:1px solid var(--border-dim);
    border-radius:14px;padding:12px 22px;text-align:center;min-width:110px;flex-shrink:0;
  }
  .score-num {
    font-family:'Cinzel',serif;font-size:28px;font-weight:700;line-height:1;
    background:var(--score-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  }
  .score-lbl { font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.1em;margin-top:3px; }

  .instruction {
    background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.18);
    border-radius:10px;padding:11px 15px;font-size:13px;color:var(--text-secondary);
    margin-bottom:22px;display:flex;align-items:center;gap:10px;
  }

  /* ARENA */
  .arena {
    background:var(--bg-surface);border:1px solid var(--border-dim);
    border-radius:20px;padding:22px;min-height:380px;
  }
  .arena-inner { display:flex;flex-direction:column;gap:10px; }

  .tile {
    background:var(--bg-card);border:1px solid var(--border-dim);border-radius:12px;
    padding:13px 15px;cursor:grab;
    display:flex;align-items:flex-start;gap:13px;
    transition:all .18s ease;user-select:none;
  }
  .tile:active    { cursor:grabbing; }
  .tile:hover     { border-color:rgba(139,92,246,.24);background:var(--bg-raised); }
  .tile.dragging  { opacity:.3;border:1px dashed rgba(139,92,246,.4);transform:scale(.99); }
  .tile.drag-over { border-color:var(--accent-violet);background:var(--drag-over);transform:scale(1.01); }
  .tile-handle { display:flex;flex-direction:column;gap:3px;padding-top:3px;opacity:.3;flex-shrink:0;transition:opacity .18s; }
  .tile:hover .tile-handle { opacity:.65; }
  .tile-handle span { display:block;width:15px;height:2px;background:var(--text-secondary);border-radius:2px; }
  .tile-num {
    width:26px;height:26px;flex-shrink:0;border:1px solid var(--border-dim);border-radius:8px;
    background:var(--bg-raised);font-size:12px;font-weight:500;color:var(--text-muted);
    display:flex;align-items:center;justify-content:center;margin-top:1px;
  }
  .tile-text { font-size:14px;line-height:1.65;color:var(--text-primary);font-weight:300;flex:1; }

  /* GAME ACTIONS */
  .game-actions { display:flex;align-items:center;gap:12px;margin-top:22px;flex-wrap:wrap; }
  .timer-pill {
    display:flex;align-items:center;gap:7px;padding:8px 16px;border-radius:20px;
    background:var(--bg-card);border:1px solid var(--border-dim);
    font-size:13px;color:var(--text-secondary);margin-left:auto;
  }
  .timer-num { font-family:'Cinzel',serif;font-size:15px;color:var(--text-primary);font-weight:600; }
  .timer-pill.urgent { border-color:rgba(239,68,68,.4); }
  .timer-pill.urgent .timer-num { color:var(--danger); }

  /* RESULT */
  .result-hero { text-align:center;padding:44px 20px 28px; }
  .result-icon {
    width:78px;height:78px;border-radius:50%;margin:0 auto 18px;
    display:flex;align-items:center;justify-content:center;font-size:34px;
  }
  .result-icon.win { background:rgba(34,197,94,.14);border:2px solid rgba(34,197,94,.38); }
  .result-icon.mid { background:rgba(234,179,8,.11); border:2px solid rgba(234,179,8,.32); }
  .result-icon.low { background:rgba(139,92,246,.11);border:2px solid rgba(139,92,246,.28); }
  .result-title { font-family:'Cinzel',serif;font-size:30px;font-weight:700;margin-bottom:7px; }
  .result-sub   { font-size:15px;color:var(--text-secondary);margin-bottom:30px; }

  .stats-row { display:flex;justify-content:center;gap:16px;flex-wrap:wrap;margin-bottom:36px; }
  .stat-card {
    background:var(--bg-card);border:1px solid var(--border-dim);
    border-radius:14px;padding:18px 24px;text-align:center;min-width:110px;
  }
  .stat-val { font-family:'Cinzel',serif;font-size:28px;font-weight:700;line-height:1;margin-bottom:5px; }
  .stat-lbl { font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.12em; }

  .result-seq {
    background:var(--bg-surface);border:1px solid var(--border-dim);
    border-radius:16px;padding:22px;margin-bottom:28px;text-align:left;
  }
  .seq-label { font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.15em;margin-bottom:14px; }
  .seq-item  { display:flex;align-items:flex-start;gap:13px;padding:9px 0;border-bottom:1px solid var(--border-dim); }
  .seq-item:last-child { border-bottom:none; }
  .seq-badge {
    flex-shrink:0;width:27px;height:27px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;margin-top:1px;
  }
  .seq-badge.ok  { background:rgba(34,197,94,.14);color:#22c55e;border:1px solid rgba(34,197,94,.28); }
  .seq-badge.bad { background:rgba(239,68,68,.10);color:var(--danger);border:1px solid rgba(239,68,68,.24); }
  .seq-text { font-size:13px;color:var(--text-secondary);line-height:1.55;flex:1; }
  .seq-pos  { font-size:11px;color:var(--text-muted);margin-left:6px; }

  .saved-badge {
    display:inline-flex;align-items:center;gap:6px;padding:6px 14px;
    background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.28);
    border-radius:20px;font-size:12px;color:#22c55e;
  }

  @media(max-width:600px){
    header { padding:14px 18px; }
    main   { padding:28px 14px 56px; }
    .game-header { flex-direction:column; }
    .stats-row   { gap:10px; }
  }
</style>
</head>
<body>
<div class="amb amb-1"></div>
<div class="amb amb-2"></div>
<div class="amb amb-3"></div>

<div class="wrapper">

  <header>
    <a href="index.php" class="logo">
      <div class="logo-icon">📖</div>
      <div>
        <div class="logo-name">StoryVerse</div>
        <div class="logo-sub">Game Arena</div>
      </div>
    </a>
    <div class="live-badge">
      <div class="live-dot"></div>
      Story Scramble
    </div>
  </header>

  <main>

    <!-- ══ PHASE 1 — SELECT ══ -->
    <div id="phase-select" class="phase">
      <div class="eyebrow">Game Arena</div>
      <h1 class="page-title">Story Scramble</h1>
      <p class="page-desc">
        Sentences from a story have been scattered. Drag and restore their correct order — prove your narrative instinct.
      </p>

      <div class="section-lbl">Choose a story</div>
      <div class="stories-grid" id="stories-grid">
        <div class="skeleton" style="height:220px;"></div>
        <div class="skeleton" style="height:220px;"></div>
        <div class="skeleton" style="height:220px;"></div>
      </div>

      <div id="part-wrap" style="display:none;">
        <div class="section-lbl">Select a part to play</div>
        <div class="parts-row" id="parts-row">
          <div class="skeleton" style="width:90px;height:36px;border-radius:24px;"></div>
          <div class="skeleton" style="width:90px;height:36px;border-radius:24px;"></div>
        </div>
      </div>

      <button class="btn-play" id="btn-start" disabled onclick="startGame()">
        <span>Begin Scramble</span>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </button>
    </div>

    <!-- ══ PHASE 2 — GAME ══ -->
    <div id="phase-game" class="phase" style="display:none;">
      <div class="game-header">
        <div>
          <div class="eyebrow" style="margin-bottom:5px;">Story Scramble</div>
          <div class="game-story-title" id="g-title">—</div>
          <div class="game-part-lbl"   id="g-part">Part —</div>
        </div>
        <div class="score-box">
          <div class="score-num" id="live-score">0</div>
          <div class="score-lbl">Points</div>
        </div>
      </div>

      <div class="instruction">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
        </svg>
        Drag tiles into the correct narrative sequence. Points are awarded for each correct adjacent pair.
      </div>

      <div class="arena">
        <div class="arena-inner" id="tile-container"></div>
      </div>

      <div class="game-actions">
        <button class="btn-secondary" onclick="reshuffleTiles()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 4v6h6M23 20v-6h-6M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/>
          </svg>
          Shuffle Tiles
        </button>
        <button class="btn-play" onclick="submitAnswer()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          <span>Submit Order</span>
        </button>
        <div class="timer-pill" id="timer-pill">
          ⏱ <span class="timer-num" id="timer-display">3:00</span>
        </div>
      </div>
    </div>

    <!-- ══ PHASE 3 — RESULT ══ -->
    <div id="phase-result" class="phase" style="display:none;">
      <div class="result-hero">
        <div class="result-icon" id="r-icon">🏆</div>
        <div class="result-title" id="r-title">Excellent!</div>
        <div class="result-sub"   id="r-sub">You have a strong sense of narrative flow.</div>
      </div>

      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-val" id="r-score"
            style="background:var(--score-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">0</div>
          <div class="stat-lbl">Score</div>
        </div>
        <div class="stat-card">
          <div class="stat-val" id="r-pairs" style="color:var(--success);">0/0</div>
          <div class="stat-lbl">Correct</div>
        </div>
        <div class="stat-card">
          <div class="stat-val" id="r-time"  style="color:var(--accent-teal);">—</div>
          <div class="stat-lbl">Time Left</div>
        </div>
        <div class="stat-card">
          <div class="stat-val" id="r-acc"   style="color:var(--accent-gold);">0%</div>
          <div class="stat-lbl">Accuracy</div>
        </div>
      </div>

      <div id="r-saved" style="text-align:center;margin-bottom:20px;min-height:28px;"></div>

      <div class="result-seq">
        <div class="seq-label">Your order vs. correct position</div>
        <div id="seq-list"></div>
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;">
        <button class="btn-play"      onclick="playAgainFresh()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 4v6h6M23 20v-6h-6M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/>
          </svg>
          Play Again (New Sentences)
        </button>
        <button class="btn-secondary" onclick="showPhase('select')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
          Choose Another
        </button>
      </div>
    </div>

  </main>
</div>

<script>
/* ═══════════════════════════════════════════════════════
   StoryVerse — Story Scramble  (client JS)
═══════════════════════════════════════════════════════ */

let selectedStory = null;
let selectedPart  = null;   // full part object {part_id, label, sentences}
let correctOrder  = [];     // true narrative order
let currentOrder  = [];     // user-dragged order
let dragSrcIndex  = null;
let timerInterval = null;
let timeLeft      = 180;

const $   = id => document.getElementById(id);
const esc = s  => String(s ?? '')
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
  .replace(/"/g,'&quot;').replace(/'/g,'&#39;');

function fmt(sec) {
  const m = Math.floor(sec / 60), s = sec % 60;
  return m + ':' + String(s).padStart(2,'0');
}

function showPhase(name) {
  ['select','game','result'].forEach(p => {
    const el = $('phase-' + p);
    const on = p === name;
    el.style.display = on ? 'block' : 'none';
    if (on) { el.style.animation='none'; el.offsetHeight; el.style.animation=''; }
  });
}

/* ── LOAD STORIES ──────────────────────────────────── */
async function loadStories() {
  try {
    const res  = await fetch('?ajax=stories');
    const data = await res.json();
    renderStories(data.stories || []);
  } catch(e) {
    $('stories-grid').innerHTML =
      '<div class="empty-state"><div class="ico">⚠️</div><p>Could not load stories.<br>Check your database connection.</p></div>';
  }
}

function renderStories(stories) {
  const grid = $('stories-grid');
  if (!stories.length) {
    grid.innerHTML = '<div class="empty-state"><div class="ico">📭</div><p>No stories found in the database.</p></div>';
    return;
  }
  grid.innerHTML = '';
  stories.forEach(s => {
    const card = document.createElement('div');
    card.className  = 'story-card';
    card.dataset.id = s.story_id;
    const coverHtml = s.cover_image_url
      ? `<img class="card-cover" src="${esc(s.cover_image_url)}" alt="" loading="lazy"
              onerror="this.outerHTML='<div class=card-no-cover>📖</div>'">`
      : `<div class="card-no-cover">📖</div>`;
    card.innerHTML = `
      ${coverHtml}
      <div class="card-cat">${esc(s.category || 'General')}</div>
      <div class="card-title">${esc(s.title)}</div>
      <div class="card-desc">${esc(s.description)}</div>
      <div class="card-meta">
        <span>📄 ${Number(s.total_parts)} part${s.total_parts != 1 ? 's' : ''}</span>
        <span>👁 ${Number(s.view_count).toLocaleString()}</span>
        <span>❤️ ${Number(s.like_count).toLocaleString()}</span>
      </div>
      <div class="card-check">✓</div>`;
    card.onclick = () => pickStory(s, card);
    grid.appendChild(card);
  });
}

/* ── PICK STORY → LOAD PARTS ───────────────────────── */
async function pickStory(story, cardEl) {
  selectedStory = story;
  selectedPart  = null;
  $('btn-start').disabled = true;

  document.querySelectorAll('.story-card').forEach(c => c.classList.remove('selected'));
  cardEl.classList.add('selected');

  $('part-wrap').style.display = 'block';
  $('parts-row').innerHTML = `
    <div class="skeleton" style="width:90px;height:36px;border-radius:24px;"></div>
    <div class="skeleton" style="width:100px;height:36px;border-radius:24px;"></div>`;

  try {
    const res  = await fetch('?ajax=parts&story_id=' + story.story_id);
    const data = await res.json();
    renderParts(data.parts || []);
  } catch(e) {
    $('parts-row').innerHTML = '<span style="color:var(--danger);font-size:13px;">Failed to load parts.</span>';
  }
}

function renderParts(parts) {
  const row = $('parts-row');
  if (!parts.length) {
    row.innerHTML = '<span style="color:var(--text-muted);font-size:13px;">No playable parts found — parts may be too short.</span>';
    return;
  }
  row.innerHTML = '';
  parts.forEach(part => {
    const btn = document.createElement('button');
    btn.className   = 'part-btn';
    btn.textContent = part.label;
    btn.onclick = () => {
      document.querySelectorAll('.part-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      selectedPart = part;
      $('btn-start').disabled = false;
    };
    row.appendChild(btn);
    if (parts.length === 1) btn.click();
  });
}

/* ── START GAME ─────────────────────────────────────── */
function startGame() {
  if (!selectedStory || !selectedPart) return;
  launchWithSentences(selectedPart.sentences);
}

function launchWithSentences(sentences) {
  // Store sentences as indexed objects: { text, origIdx }
  // origIdx = 0-based position in the correct narrative order.
  // This way scoring NEVER relies on string matching (indexOf),
  // which breaks when sentences share substrings or get rerolled.
  correctOrder = sentences.map((text, i) => ({ text, origIdx: i }));
  currentOrder = shuffle([...correctOrder]);
  timeLeft     = 180;

  $('g-title').textContent    = selectedStory.title;
  $('g-part').textContent     = selectedPart.label;
  $('live-score').textContent = '0';

  showPhase('game');
  renderTiles();
  startTimer();
}

/* ── PLAY AGAIN — fetch a FRESH random slice ─────────── */
// FIX #1: Instead of reusing the same sentences, we call
// ?ajax=reroll&part_id=X which runs split_sentences_random()
// again server-side, returning a different random slice.
async function playAgainFresh() {
  if (!selectedPart) { showPhase('select'); return; }

  // Show loading state briefly
  showPhase('game');
  $('tile-container').innerHTML = `
    <div class="skeleton" style="height:54px;"></div>
    <div class="skeleton" style="height:54px;"></div>
    <div class="skeleton" style="height:54px;"></div>
    <div class="skeleton" style="height:54px;"></div>`;
  clearInterval(timerInterval);

  try {
    const res  = await fetch('?ajax=reroll&part_id=' + selectedPart.part_id);
    const data = await res.json();
    if (data.sentences && data.sentences.length >= 4) {
      selectedPart = { ...selectedPart, sentences: data.sentences };
      launchWithSentences(data.sentences);
    } else {
      // Fallback: reuse existing sentences (re-extract text from objects)
      launchWithSentences(selectedPart.sentences.map ? selectedPart.sentences : selectedPart.sentences);
    }
  } catch(e) {
    launchWithSentences(selectedPart.sentences);
  }
}

function shuffle(arr) {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  // Guarantee it's actually scrambled (compare origIdx positions)
  if (arr.length > 1 && arr.every((v, i) => v.origIdx === i)) return shuffle(arr);
  return arr;
}

/* ── TILES ──────────────────────────────────────────── */
function renderTiles() {
  const c = $('tile-container');
  c.innerHTML = '';
  currentOrder.forEach((item, i) => {
    const tile = document.createElement('div');
    tile.className   = 'tile';
    tile.draggable   = true;
    tile.dataset.idx = i;
    tile.innerHTML   = `
      <div class="tile-handle"><span></span><span></span><span></span></div>
      <div class="tile-num">${i + 1}</div>
      <div class="tile-text">${esc(item.text)}</div>`;
    tile.addEventListener('dragstart', onDragStart);
    tile.addEventListener('dragover',  onDragOver);
    tile.addEventListener('dragleave', onDragLeave);
    tile.addEventListener('drop',      onDrop);
    tile.addEventListener('dragend',   onDragEnd);
    c.appendChild(tile);
  });
}

function onDragStart(e) {
  dragSrcIndex = +this.dataset.idx;
  this.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function onDragOver(e)  { e.preventDefault(); this.classList.add('drag-over'); }
function onDragLeave()  { this.classList.remove('drag-over'); }
function onDrop(e) {
  e.preventDefault();
  const tgt = +this.dataset.idx;
  if (dragSrcIndex !== null && dragSrcIndex !== tgt) {
    [currentOrder[dragSrcIndex], currentOrder[tgt]] =
    [currentOrder[tgt], currentOrder[dragSrcIndex]];
    renderTiles();
    updateLiveScore();
  }
}
function onDragEnd() {
  document.querySelectorAll('.tile').forEach(t => t.classList.remove('dragging','drag-over'));
  dragSrcIndex = null;
}
function reshuffleTiles() { currentOrder = shuffle([...correctOrder]); renderTiles(); updateLiveScore(); }

/* ── SCORING ─────────────────────────────────────────── */
// total   = n  (total sentences — what players naturally count)
// correct = number of tiles placed in their exact correct absolute position
// Example: 5 sentences, 2 in right spot → 2/5, score = 40%
// The adjacent-pair logic is kept only for badge colouring in the breakdown.
function computeScore() {
  const n     = correctOrder.length;     // e.g. 5
  const total = n;                       // display as X/5, X/4, X/6 etc.
  let correct = 0;
  for (let i = 0; i < n; i++) {
    if (currentOrder[i] && currentOrder[i].origIdx === i) correct++;
  }
  return { correct, total, score: total > 0 ? Math.round((correct / total) * 100) : 0 };
}
function updateLiveScore() { $('live-score').textContent = computeScore().score; }

/* ── TIMER ──────────────────────────────────────────── */
function startTimer() {
  clearInterval(timerInterval);
  updateTimerUI();
  timerInterval = setInterval(() => {
    timeLeft--;
    updateTimerUI();
    if (timeLeft <= 0) { clearInterval(timerInterval); submitAnswer(true); }
  }, 1000);
}
function updateTimerUI() {
  $('timer-display').textContent = fmt(timeLeft);
  $('timer-pill').classList.toggle('urgent', timeLeft <= 30);
}

/* ── SUBMIT ──────────────────────────────────────────── */
function submitAnswer(timedOut = false) {
  clearInterval(timerInterval);
  const { correct, total, score } = computeScore();
  const tl = Math.max(0, timeLeft);

  let iconCls, emoji, title, sub;
  if (score >= 80)      { iconCls='win';emoji='🏆';title='Masterful!';sub='Your narrative instincts are exceptional.'; }
  else if (score >= 50) { iconCls='mid';emoji='⭐';title='Well Done!';sub='You have a solid sense of story flow.'; }
  else                  { iconCls='low';emoji='📜';title=timedOut?"Time's Up!":'Keep Practicing!';sub='Read it once more and try again.'; }

  $('r-icon').className   = 'result-icon ' + iconCls;
  $('r-icon').textContent = emoji;
  $('r-title').textContent = title;
  $('r-sub').textContent   = sub;
  $('r-score').textContent = score;
  $('r-pairs').textContent = correct + '/' + total;
  $('r-time').textContent  = fmt(tl);
  $('r-acc').textContent   = score + '%';
  $('r-saved').innerHTML   = '';

  const list = $('seq-list');
  list.innerHTML = '';
  currentOrder.forEach((item, i) => {
    const cp   = item.origIdx + 1;           // correct position (1-based)
    const isOk = item.origIdx === i;         // green = tile is in its correct absolute slot
    const el   = document.createElement('div');
    el.className = 'seq-item';
    el.innerHTML = `
      <div class="seq-badge ${isOk ? 'ok' : 'bad'}">${i + 1}</div>
      <div class="seq-text">
        ${esc(item.text.length > 88 ? item.text.slice(0,88) + '…' : item.text)}
        <span class="seq-pos">(correct: #${cp})</span>
      </div>`;
    list.appendChild(el);
  });

  showPhase('result');
  saveScore(correct, total, score, tl);
}

/* ── SAVE SCORE ──────────────────────────────────────── */
// FIX #2: Payload matches exact game_scores columns:
// story_part_id, score, correct_pairs, total_pairs, time_left
// (max_score removed — not in your schema)
async function saveScore(correct, total, score, tlSec) {
  const box = $('r-saved');
  box.innerHTML = '<span style="color:var(--text-muted);font-size:13px;">Saving score…</span>';
  try {
    const res = await fetch('?ajax=save_score', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body   : JSON.stringify({
        story_part_id : selectedPart.part_id,
        score         : score,
        correct_pairs : correct,
        total_pairs   : total,
        time_left     : tlSec,
      })
    });
    const data = await res.json();
    if (data.success) {
      box.innerHTML = '<span class="saved-badge">✓ Score saved to your profile</span>';
    } else {
      box.innerHTML = `<span style="color:var(--text-muted);font-size:12px;">${esc(data.message ?? 'Score not saved.')}</span>`;
    }
  } catch(e) {
    box.innerHTML = '<span style="color:var(--danger);font-size:12px;">Could not reach the server.</span>';
  }
}

/* ── BOOT ────────────────────────────────────────────── */
loadStories();
</script>
</body>
</html>