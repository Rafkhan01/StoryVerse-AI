<?php
/* ══════════════════════════════════════════════════════════════
   StoryVerse — Who Said It? (Character Attribution Quiz)
   File : who_said_it.php
   Place: project root (same folder as db_connect.php)
══════════════════════════════════════════════════════════════ */
require_once 'db_connect.php';
session_start();
$current_user_id = $_SESSION['user_id'] ?? null;

/* ══════════════════════════════════════════════════════════════
   AJAX ENDPOINTS
══════════════════════════════════════════════════════════════ */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    /* ── 1. STORY LIST ─────────────────────────────────────── */
    if ($_GET['ajax'] === 'stories') {
        $stmt = $pdo->query(
            "SELECT s.story_id, s.title, s.category, s.description,
                    s.cover_image_url, s.total_parts, s.view_count, s.like_count,
                    COUNT(cq.id) AS question_count
             FROM   stories s
             JOIN   character_quiz cq ON cq.story_id = s.story_id
             GROUP  BY s.story_id
             HAVING question_count > 0
             ORDER  BY s.last_updated DESC
             LIMIT  20"
        );
        echo json_encode(['stories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    /* ── 2. QUESTIONS FOR A STORY (shuffled, max 10) ───────── */
    if ($_GET['ajax'] === 'questions' && isset($_GET['story_id'])) {
        $story_id = (int) $_GET['story_id'];
        $stmt = $pdo->prepare(
            "SELECT id, dialogue, `character`, distractors
             FROM   character_quiz
             WHERE  story_id = :sid
             ORDER  BY RAND()
             LIMIT  10"
        );
        $stmt->execute([':sid' => $story_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $questions = [];
        foreach ($rows as $row) {
            $distractors = json_decode($row['distractors'], true) ?? [];
            $correct_char = $row['character'];
            // Need at least 1 distractor to make a valid question — skip if none
            if (empty($correct_char) || count($distractors) < 1) continue;
            // Build options: correct + up to 2 distractors, then shuffle
            $options = array_merge([$correct_char], array_slice($distractors, 0, 2));
            shuffle($options);
            $questions[] = [
                'id'        => (int) $row['id'],
                'dialogue'  => $row['dialogue'],
                'correct'   => $correct_char,
                'options'   => $options,
            ];
        }
        echo json_encode(['questions' => $questions]);
        exit;
    }

    /* ── 3. SAVE SCORE ─────────────────────────────────────── */
    if ($_GET['ajax'] === 'save_score' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body          = json_decode(file_get_contents('php://input'), true);
        $story_id      = isset($body['story_id'])      ? (int) $body['story_id']      : 0;
        $score         = isset($body['score'])         ? (int) $body['score']         : 0;
        $correct_count = isset($body['correct_count']) ? (int) $body['correct_count'] : 0;
        $total_count   = isset($body['total_count'])   ? (int) $body['total_count']   : 0;

        if (!$story_id) { echo json_encode(['success'=>false,'message'=>'Missing story_id']); exit; }
        if (!$current_user_id) { echo json_encode(['success'=>false,'message'=>'Guest — score not saved']); exit; }

        // Reuse game_scores: story_part_id = 0 for whole-story games, score = points
        $stmt = $pdo->prepare(
            "INSERT INTO game_scores (user_id, story_part_id, score, correct_pairs, total_pairs, time_left_seconds, played_at)
             VALUES (:uid, 0, :score, :cp, :tp, 0, NOW())"
        );
        $ok = $stmt->execute([
            ':uid'   => $current_user_id,
            ':score' => $score,
            ':cp'    => $correct_count,
            ':tp'    => $total_count,
        ]);
        echo json_encode(['success' => $ok]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Who Said It? — StoryVerse</title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg-void:    #07080d;
    --bg-surface: #12151f;
    --bg-card:    #171b28;
    --bg-raised:  #1e2335;
    --border-dim:  rgba(255,255,255,0.07);
    --border-glow: rgba(234,179,8,0.35);
    --text-primary:   #f0eef8;
    --text-secondary: #8b8fa8;
    --text-muted:     #4a4d60;
    --accent-gold:         #eab308;
    --accent-gold-bright:  #fde047;
    --accent-gold-dim:     rgba(234,179,8,0.15);
    --accent-violet: #8b5cf6;
    --accent-teal:   #2dd4bf;
    --success: #22c55e;
    --danger:  #ef4444;
    --score-gradient: linear-gradient(135deg, #eab308, #f97316);
  }
  html, body { min-height:100%; background:var(--bg-void); color:var(--text-primary); font-family:'DM Sans',sans-serif; font-size:15px; line-height:1.6; overflow-x:hidden; }
  body::before { content:''; position:fixed; inset:0; pointer-events:none; z-index:0; background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E"); background-size:256px; }
  .amb { position:fixed; pointer-events:none; z-index:0; border-radius:50%; filter:blur(120px); }
  .amb-1 { width:600px;height:600px;background:#eab308;opacity:.07;top:-200px;right:-100px; }
  .amb-2 { width:400px;height:400px;background:#8b5cf6;opacity:.07;bottom:100px;left:-150px; }
  .amb-3 { width:280px;height:280px;background:#2dd4bf;opacity:.04;top:50%;left:50%;transform:translate(-50%,-50%); }
  .wrapper { position:relative; z-index:1; min-height:100vh; display:flex; flex-direction:column; }

  /* HEADER */
  header { display:flex; align-items:center; justify-content:space-between; padding:18px 40px; border-bottom:1px solid var(--border-dim); background:rgba(13,15,24,0.85); backdrop-filter:blur(16px); position:sticky; top:0; z-index:100; }
  .logo { display:flex; align-items:center; gap:12px; text-decoration:none; }
  .logo-icon { width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#eab308,#f97316);display:flex;align-items:center;justify-content:center;font-size:18px; }
  .logo-name { font-family:'Cinzel',serif;font-size:16px;font-weight:700;color:var(--text-primary); }
  .logo-sub  { font-size:10px;color:var(--text-muted);letter-spacing:.14em;text-transform:uppercase; }
  .live-badge { display:flex;align-items:center;gap:8px;padding:6px 14px;border:1px solid var(--border-dim);border-radius:20px;font-size:12px;color:var(--text-secondary); }
  .live-dot { width:6px;height:6px;border-radius:50%;background:var(--accent-gold);animation:pulse 2s infinite; }
  @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.8)} }

  main { flex:1; max-width:760px; width:100%; margin:0 auto; padding:48px 24px 80px; }
  .phase { animation:fadeUp .45s ease both; }
  @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }

  /* HERO */
  .eyebrow { font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--accent-gold);font-weight:500;margin-bottom:10px;display:flex;align-items:center;gap:8px; }
  .eyebrow::before { content:'';width:22px;height:1px;background:var(--accent-gold); }
  .page-title { font-family:'Cinzel',serif;font-size:clamp(26px,5vw,46px);font-weight:700;line-height:1.1;margin-bottom:10px;background:linear-gradient(135deg,#f0eef8 0%,#fde047 55%,#f97316 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text; }
  .page-desc { font-size:15px;color:var(--text-secondary);font-weight:300;max-width:500px;margin-bottom:44px;line-height:1.75; }
  .section-lbl { font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--text-muted);font-weight:500;margin-bottom:14px; }

  /* STORY CARDS */
  .stories-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-bottom:36px; }
  .story-card { background:var(--bg-card);border:1px solid var(--border-dim);border-radius:16px;padding:20px;cursor:pointer;transition:all .22s ease;position:relative;overflow:hidden; }
  .story-card::before { content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(234,179,8,.07) 0%,transparent 60%);opacity:0;transition:opacity .22s; }
  .story-card:hover { border-color:var(--border-glow);transform:translateY(-2px); }
  .story-card:hover::before { opacity:1; }
  .story-card.selected { border-color:var(--accent-gold);background:rgba(234,179,8,.08);box-shadow:0 0 0 1px var(--accent-gold); }
  .story-card.selected::before { opacity:1; }
  .card-cover { width:100%;height:90px;object-fit:cover;border-radius:10px;margin-bottom:12px;background:var(--bg-raised);display:block; }
  .card-no-cover { width:100%;height:90px;border-radius:10px;margin-bottom:12px;background:linear-gradient(135deg,var(--bg-raised),var(--bg-surface));display:flex;align-items:center;justify-content:center;font-size:28px; }
  .card-cat   { font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--accent-gold);font-weight:500;margin-bottom:6px; }
  .card-title { font-family:'Cinzel',serif;font-size:14px;font-weight:600;color:var(--text-primary);margin-bottom:5px;line-height:1.3; }
  .card-desc  { font-size:12px;color:var(--text-secondary);font-weight:300;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
  .card-meta  { margin-top:12px;padding-top:10px;border-top:1px solid var(--border-dim);display:flex;gap:14px; }
  .card-meta span { font-size:11px;color:var(--text-muted); }
  .card-check { position:absolute;top:12px;right:12px;width:22px;height:22px;border-radius:50%;background:var(--accent-gold);display:none;align-items:center;justify-content:center;font-size:11px;color:#000; }
  .story-card.selected .card-check { display:flex; }

  /* BUTTONS */
  .btn-play { display:inline-flex;align-items:center;gap:10px;padding:13px 30px;background:linear-gradient(135deg,#eab308,#f97316);border:none;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:500;color:#000;cursor:pointer;transition:all .22s; }
  .btn-play:hover    { transform:translateY(-2px);box-shadow:0 8px 28px rgba(234,179,8,.4); }
  .btn-play:active   { transform:translateY(0); }
  .btn-play:disabled { opacity:.4;cursor:not-allowed;transform:none;box-shadow:none; }
  .btn-secondary { display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:transparent;border:1px solid var(--border-dim);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;color:var(--text-secondary);cursor:pointer;transition:all .18s; }
  .btn-secondary:hover { border-color:rgba(255,255,255,.18);color:var(--text-primary); }

  /* SKELETON */
  .skeleton { background:linear-gradient(90deg,var(--bg-card) 25%,var(--bg-raised) 50%,var(--bg-card) 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:12px; }
  @keyframes shimmer { from{background-position:200% 0} to{background-position:-200% 0} }
  .empty-state { text-align:center;padding:60px 20px;color:var(--text-muted); }
  .empty-state .ico { font-size:40px;margin-bottom:14px; }

  /* ── GAME PHASE ── */
  /* Progress bar */
  .progress-bar-wrap { display:flex;align-items:center;gap:12px;margin-bottom:28px; }
  .progress-track { flex:1;height:4px;background:var(--bg-raised);border-radius:4px;overflow:hidden; }
  .progress-fill  { height:100%;background:linear-gradient(90deg,#eab308,#f97316);border-radius:4px;transition:width .4s ease; }
  .progress-label { font-size:13px;color:var(--text-muted);white-space:nowrap; }

  /* Per-question timer ring */
  .timer-ring-wrap {
    display:flex;flex-direction:column;align-items:center;margin-bottom:28px;
  }
  .timer-ring {
    position:relative;width:80px;height:80px;
  }
  .timer-ring svg { transform:rotate(-90deg); }
  .timer-ring-bg  { fill:none;stroke:var(--bg-raised);stroke-width:5; }
  .timer-ring-fg  { fill:none;stroke:#eab308;stroke-width:5;stroke-linecap:round;
                    stroke-dasharray:220;stroke-dashoffset:0;transition:stroke-dashoffset .9s linear, stroke .3s; }
  .timer-ring-num { position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:'Cinzel',serif;font-size:22px;font-weight:700;color:var(--text-primary); }
  .timer-ring.urgent .timer-ring-fg  { stroke:var(--danger); }
  .timer-ring.urgent .timer-ring-num { color:var(--danger); }

  /* Points flash */
  .pts-flash { font-size:13px;color:var(--accent-gold);font-weight:500;margin-top:6px;min-height:20px;text-align:center;transition:opacity .3s; }

  /* Dialogue card */
  .dialogue-card {
    background:var(--bg-card);border:1px solid var(--border-dim);border-radius:20px;
    padding:32px 28px;margin-bottom:24px;position:relative;text-align:center;
  }
  .dialogue-card::before {
    content:'"';position:absolute;top:-16px;left:24px;
    font-family:'Cinzel',serif;font-size:72px;line-height:1;
    color:var(--accent-gold);opacity:.18;pointer-events:none;
  }
  .dialogue-label { font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px; }
  .dialogue-text  { font-size:18px;line-height:1.7;color:var(--text-primary);font-weight:300;font-style:italic; }

  /* Answer options */
  .options-grid { display:flex;flex-direction:column;gap:12px;margin-bottom:24px; }
  .opt-btn {
    background:var(--bg-card);border:1px solid var(--border-dim);border-radius:14px;
    padding:16px 20px;text-align:left;cursor:pointer;
    display:flex;align-items:center;gap:14px;
    font-family:'DM Sans',sans-serif;font-size:15px;color:var(--text-primary);
    transition:all .18s ease;position:relative;overflow:hidden;
  }
  .opt-btn::before { content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(234,179,8,.07),transparent);opacity:0;transition:opacity .18s; }
  .opt-btn:hover:not(:disabled) { border-color:var(--border-glow);transform:translateX(3px); }
  .opt-btn:hover:not(:disabled)::before { opacity:1; }
  .opt-btn:disabled { cursor:default; }
  .opt-btn.correct  { border-color:rgba(34,197,94,.5);background:rgba(34,197,94,.1);color:#22c55e; }
  .opt-btn.wrong    { border-color:rgba(239,68,68,.4);background:rgba(239,68,68,.08);color:var(--danger); }
  .opt-btn.reveal   { border-color:rgba(234,179,8,.4);background:rgba(234,179,8,.08); }
  .opt-letter {
    width:30px;height:30px;flex-shrink:0;border-radius:8px;
    background:var(--bg-raised);border:1px solid var(--border-dim);
    display:flex;align-items:center;justify-content:center;
    font-size:12px;font-weight:600;color:var(--text-muted);
    transition:all .18s;
  }
  .opt-btn.correct .opt-letter { background:rgba(34,197,94,.2);border-color:rgba(34,197,94,.4);color:#22c55e; }
  .opt-btn.wrong   .opt-letter { background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.3);color:var(--danger); }
  .opt-btn.reveal  .opt-letter { background:rgba(234,179,8,.15);border-color:rgba(234,179,8,.35);color:var(--accent-gold); }
  .opt-name { font-weight:400; }
  .opt-icon { margin-left:auto;font-size:16px; }

  /* Score HUD */
  .score-hud { display:flex;align-items:center;justify-content:space-between;margin-bottom:32px; }
  .hud-score { font-family:'Cinzel',serif;font-size:28px;font-weight:700;background:var(--score-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text; }
  .hud-label { font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.1em; }
  .streak-badge { display:flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;background:rgba(234,179,8,.1);border:1px solid rgba(234,179,8,.25);font-size:13px;color:var(--accent-gold); }

  /* RESULT */
  .result-hero { text-align:center;padding:44px 20px 28px; }
  .result-icon { width:78px;height:78px;border-radius:50%;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;font-size:34px; }
  .result-icon.win { background:rgba(34,197,94,.14);border:2px solid rgba(34,197,94,.38); }
  .result-icon.mid { background:rgba(234,179,8,.11); border:2px solid rgba(234,179,8,.32); }
  .result-icon.low { background:rgba(139,92,246,.11);border:2px solid rgba(139,92,246,.28); }
  .result-title { font-family:'Cinzel',serif;font-size:30px;font-weight:700;margin-bottom:7px; }
  .result-sub   { font-size:15px;color:var(--text-secondary);margin-bottom:30px; }
  .stats-row    { display:flex;justify-content:center;gap:16px;flex-wrap:wrap;margin-bottom:36px; }
  .stat-card    { background:var(--bg-card);border:1px solid var(--border-dim);border-radius:14px;padding:18px 24px;text-align:center;min-width:110px; }
  .stat-val     { font-family:'Cinzel',serif;font-size:28px;font-weight:700;line-height:1;margin-bottom:5px; }
  .stat-lbl     { font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.12em; }

  /* Review list */
  .review-list { background:var(--bg-surface);border:1px solid var(--border-dim);border-radius:16px;padding:22px;margin-bottom:28px;text-align:left; }
  .review-lbl  { font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.15em;margin-bottom:14px; }
  .review-item { display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-dim); }
  .review-item:last-child { border-bottom:none; }
  .review-badge { flex-shrink:0;width:27px;height:27px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;margin-top:1px; }
  .review-badge.ok  { background:rgba(34,197,94,.14);border:1px solid rgba(34,197,94,.28); }
  .review-badge.bad { background:rgba(239,68,68,.10);border:1px solid rgba(239,68,68,.24); }
  .review-body { flex:1; }
  .review-dialogue { font-size:13px;color:var(--text-secondary);line-height:1.5;margin-bottom:3px;font-style:italic; }
  .review-answer   { font-size:12px; }
  .review-answer .correct-ans { color:var(--success); }
  .review-answer .your-ans    { color:var(--danger); }

  .saved-badge { display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.28);border-radius:20px;font-size:12px;color:#22c55e; }

  @media(max-width:600px){ header{padding:14px 18px;} main{padding:28px 14px 56px;} .stats-row{gap:10px;} }
</style>
</head>
<body>
<div class="amb amb-1"></div>
<div class="amb amb-2"></div>
<div class="amb amb-3"></div>
<div class="wrapper">

  <header>
    <a href="index.php" class="logo">
      <div class="logo-icon">🕵️</div>
      <div>
        <div class="logo-name">StoryVerse</div>
        <div class="logo-sub">Game Arena</div>
      </div>
    </a>
    <div class="live-badge"><div class="live-dot"></div>Who Said It?</div>
  </header>

  <main>

    <!-- ══ PHASE 1 — SELECT STORY ══ -->
    <div id="phase-select" class="phase">
      <div class="eyebrow">Game Arena</div>
      <h1 class="page-title">Who Said It?</h1>
      <p class="page-desc">A dialogue appears — you decide who spoke it. 10 seconds per question, faster answers earn more points.</p>
      <div class="section-lbl">Choose a story</div>
      <div class="stories-grid" id="stories-grid">
        <div class="skeleton" style="height:220px;"></div>
        <div class="skeleton" style="height:220px;"></div>
        <div class="skeleton" style="height:220px;"></div>
      </div>
      <button class="btn-play" id="btn-start" disabled onclick="startGame()">
        <span>Start Quiz</span>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </div>

    <!-- ══ PHASE 2 — GAME ══ -->
    <div id="phase-game" class="phase" style="display:none;">
      <!-- Score HUD -->
      <div class="score-hud">
        <div>
          <div class="hud-score" id="hud-score">0</div>
          <div class="hud-label">Points</div>
        </div>
        <div class="streak-badge" id="streak-badge" style="display:none;">
          🔥 <span id="streak-num">0</span> streak
        </div>
        <div style="text-align:right;">
          <div class="hud-score" style="font-size:18px;" id="hud-qnum">1/10</div>
          <div class="hud-label">Question</div>
        </div>
      </div>

      <!-- Progress -->
      <div class="progress-bar-wrap">
        <div class="progress-track"><div class="progress-fill" id="progress-fill" style="width:0%"></div></div>
      </div>

      <!-- Timer ring -->
      <div class="timer-ring-wrap">
        <div class="timer-ring" id="timer-ring">
          <svg width="80" height="80" viewBox="0 0 80 80">
            <circle class="timer-ring-bg" cx="40" cy="40" r="35"/>
            <circle class="timer-ring-fg" id="ring-fg" cx="40" cy="40" r="35"/>
          </svg>
          <div class="timer-ring-num" id="ring-num">10</div>
        </div>
        <div class="pts-flash" id="pts-flash"></div>
      </div>

      <!-- Dialogue -->
      <div class="dialogue-card">
        <div class="dialogue-label">Who said or did this?</div>
        <div class="dialogue-text" id="dialogue-text">…</div>
      </div>

      <!-- Options -->
      <div class="options-grid" id="options-grid"></div>
    </div>

    <!-- ══ PHASE 2b — STREAK RESULT ══ -->
    <div id="phase-streak" class="phase" style="display:none;">
      <div class="result-hero">
        <div class="result-icon" id="sk-icon">⭐</div>
        <div style="font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:var(--accent-gold);margin-bottom:6px;" id="sk-eyebrow">Streak 1 Complete</div>
        <div class="result-title" id="sk-title">Nice Work!</div>
        <div class="result-sub"   id="sk-sub"></div>
      </div>
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-val" id="sk-score" style="background:var(--score-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">0</div>
          <div class="stat-lbl">Points</div>
        </div>
        <div class="stat-card">
          <div class="stat-val" id="sk-correct" style="color:var(--success);">0/5</div>
          <div class="stat-lbl">Correct</div>
        </div>
        <div class="stat-card">
          <div class="stat-val" id="sk-acc" style="color:var(--accent-gold);">0%</div>
          <div class="stat-lbl">Accuracy</div>
        </div>
        <div class="stat-card" id="sk-remaining-card">
          <div class="stat-val" id="sk-remaining" style="color:var(--accent-teal);">0</div>
          <div class="stat-lbl">Left</div>
        </div>
      </div>
      <!-- Mini review for this streak -->
      <div class="review-list" style="margin-bottom:24px;">
        <div class="review-lbl">This Streak</div>
        <div id="sk-review-list"></div>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;" id="sk-buttons">
        <!-- filled by JS -->
      </div>
    </div>

    <!-- ══ PHASE 3 — FINAL RESULT ══ -->
    <div id="phase-result" class="phase" style="display:none;">
      <div class="result-hero">
        <div class="result-icon" id="r-icon">🏆</div>
        <div class="result-title" id="r-title">Excellent!</div>
        <div class="result-sub"   id="r-sub"></div>
      </div>
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-val" id="r-score" style="background:var(--score-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">0</div>
          <div class="stat-lbl">Points</div>
        </div>
        <div class="stat-card">
          <div class="stat-val" id="r-correct" style="color:var(--success);">0/0</div>
          <div class="stat-lbl">Correct</div>
        </div>
        <div class="stat-card">
          <div class="stat-val" id="r-acc" style="color:var(--accent-gold);">0%</div>
          <div class="stat-lbl">Accuracy</div>
        </div>
        <div class="stat-card">
          <div class="stat-val" id="r-streak" style="color:#f97316;">0</div>
          <div class="stat-lbl">Best Streak</div>
        </div>
      </div>
      <div id="r-saved" style="text-align:center;margin-bottom:20px;min-height:28px;"></div>
      <div class="review-list">
        <div class="review-lbl">Question Review</div>
        <div id="review-list"></div>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;">
        <button class="btn-play"      onclick="restartGame()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6M23 20v-6h-6M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/></svg>
          Play Again
        </button>
        <button class="btn-secondary" onclick="showPhase('select')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Choose Another
        </button>
      </div>
    </div>

  </main>
</div>

<script>
/* ═══════════════════════════════════════════════
   Who Said It? — Game Logic
═══════════════════════════════════════════════ */
const $ = id => document.getElementById(id);
const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

// ── State ──────────────────────────────────────
let selectedStory  = null;
let questions      = [];     // all questions for this game (all streaks)
let streaks        = [];     // questions split into chunks of STREAK_SIZE
let streakIndex    = 0;      // which streak we are currently on (0-based)
let qIndex         = 0;      // question index within the current streak
let streakScore    = 0;      // score for the current streak only (resets each streak)
let streakCorrect  = 0;      // correct count for current streak
let streakHistory  = [];     // answer history for current streak
let consecStreak   = 0;      // consecutive correct answers (for 🔥 badge)
let bestConsec     = 0;
let answered       = false;
let timerInterval  = null;
let timeLeft       = 10;

const TIMER_MAX     = 10;
const STREAK_SIZE   = 5;                   // questions per streak
const CIRCUMFERENCE = 2 * Math.PI * 35;   // r=35

function showPhase(name) {
  ['select','game','streak','result'].forEach(p => {
    const el = $('phase-'+p);
    const on = p === name;
    el.style.display = on ? 'block' : 'none';
    if (on) { el.style.animation='none'; el.offsetHeight; el.style.animation=''; }
  });
}

// ── Load Stories ───────────────────────────────
async function loadStories() {
  try {
    const data = await fetch('?ajax=stories').then(r=>r.json());
    renderStories(data.stories || []);
  } catch(e) {
    $('stories-grid').innerHTML = '<div class="empty-state"><div class="ico">⚠️</div><p>Could not load stories.</p></div>';
  }
}

function renderStories(stories) {
  const grid = $('stories-grid');
  if (!stories.length) {
    grid.innerHTML = '<div class="empty-state"><div class="ico">📭</div><p>No stories with quiz questions yet.<br>Ask an admin to add some!</p></div>';
    return;
  }
  grid.innerHTML = '';
  stories.forEach(s => {
    const card = document.createElement('div');
    card.className = 'story-card';
    const cover = s.cover_image_url
      ? `<img class="card-cover" src="${esc(s.cover_image_url)}" alt="" loading="lazy" onerror="this.outerHTML='<div class=card-no-cover>📖</div>'">`
      : `<div class="card-no-cover">📖</div>`;
    card.innerHTML = `${cover}
      <div class="card-cat">${esc(s.category||'General')}</div>
      <div class="card-title">${esc(s.title)}</div>
      <div class="card-desc">${esc(s.description)}</div>
      <div class="card-meta">
        <span>❓ ${Number(s.question_count)} question${s.question_count!=1?'s':''}</span>
        <span>👁 ${Number(s.view_count).toLocaleString()}</span>
      </div>
      <div class="card-check">✓</div>`;
    card.onclick = () => {
      document.querySelectorAll('.story-card').forEach(c=>c.classList.remove('selected'));
      card.classList.add('selected');
      selectedStory = s;
      $('btn-start').disabled = false;
    };
    grid.appendChild(card);
  });
}

// ── Start Game ─────────────────────────────────
async function startGame() {
  if (!selectedStory) return;
  $('btn-start').disabled = true;
  try {
    const data = await fetch(`?ajax=questions&story_id=${selectedStory.story_id}`).then(r=>r.json());
    questions = data.questions || [];
    if (!questions.length) { alert('No questions available for this story yet.'); $('btn-start').disabled=false; return; }
  } catch(e) { alert('Failed to load questions.'); $('btn-start').disabled=false; return; }

  // Slice all questions into chunks of STREAK_SIZE
  streaks = [];
  for (let i = 0; i < questions.length; i += STREAK_SIZE) {
    streaks.push(questions.slice(i, i + STREAK_SIZE));
  }

  streakIndex = 0;
  beginStreak();
}

// ── Begin a streak ──────────────────────────────
function beginStreak() {
  qIndex        = 0;
  streakScore   = 0;
  streakCorrect = 0;
  streakHistory = [];
  consecStreak  = 0;
  bestConsec    = 0;
  answered      = false;

  $('hud-score').textContent = '0';
  $('streak-badge').style.display = 'none';

  showPhase('game');
  loadQuestion();
}

// ── Load Question ──────────────────────────────
function loadQuestion() {
  const currentStreak = streaks[streakIndex];
  if (qIndex >= currentStreak.length) { endStreak(); return; }
  answered = false;
  const q = currentStreak[qIndex];

  // Update HUD — show position within current streak
  const totalStreaks = streaks.length;
  $('hud-qnum').textContent = `${qIndex+1}/${currentStreak.length}`;
  // Small streak indicator in score label area
  const pct = (qIndex / currentStreak.length) * 100;
  $('progress-fill').style.width = pct + '%';

  // Dialogue
  $('dialogue-text').textContent = q.dialogue;

  // Options
  const grid = $('options-grid');
  grid.innerHTML = '';
  const letters = ['A','B','C'];
  q.options.forEach((opt, i) => {
    const btn = document.createElement('button');
    btn.className = 'opt-btn';
    btn.innerHTML = `<div class="opt-letter">${letters[i]}</div><span class="opt-name">${esc(opt)}</span><span class="opt-icon"></span>`;
    btn.onclick = () => handleAnswer(opt, q.correct);
    grid.appendChild(btn);
  });

  // Clear flash
  $('pts-flash').textContent = '';

  // Start timer
  timeLeft = TIMER_MAX;
  updateRing();
  clearInterval(timerInterval);
  timerInterval = setInterval(() => {
    timeLeft--;
    updateRing();
    if (timeLeft <= 0) {
      clearInterval(timerInterval);
      timeExpired();
    }
  }, 1000);
}

// ── Timer Ring ─────────────────────────────────
function updateRing() {
  const pct    = timeLeft / TIMER_MAX;
  const offset = CIRCUMFERENCE * (1 - pct);
  $('ring-fg').style.strokeDashoffset = offset;
  $('ring-num').textContent = timeLeft;
  $('timer-ring').classList.toggle('urgent', timeLeft <= 3);
}

// ── Handle Answer ──────────────────────────────
function handleAnswer(chosen, correct) {
  if (answered) return;
  answered = true;
  clearInterval(timerInterval);

  const isCorrect = chosen === correct;
  const pts       = isCorrect ? Math.max(10, timeLeft * 10) : 0;   // 10–100 pts based on speed

  // Update streak-local state (scores reset each streak)
  if (isCorrect) {
    streakScore   += pts;
    streakCorrect++;
    consecStreak++;
    bestConsec = Math.max(bestConsec, consecStreak);
  } else {
    consecStreak = 0;
  }

  // Update HUD (shows current streak score)
  $('hud-score').textContent = streakScore;
  if (consecStreak >= 2) {
    $('streak-badge').style.display = 'flex';
    $('streak-num').textContent = consecStreak;
  } else {
    $('streak-badge').style.display = 'none';
  }

  // Points flash
  if (isCorrect) {
    $('pts-flash').textContent = `+${pts} pts${consecStreak>=2?' 🔥 '+consecStreak+'x':''}`;
  } else {
    $('pts-flash').textContent = `Correct: ${correct}`;
    $('pts-flash').style.color = 'var(--danger)';
  }

  // Colour the buttons
  document.querySelectorAll('.opt-btn').forEach(btn => {
    btn.disabled = true;
    const name = btn.querySelector('.opt-name').textContent;
    const icon = btn.querySelector('.opt-icon');
    if (name === correct) {
      btn.classList.add('correct');
      icon.textContent = '✓';
    } else if (name === chosen && !isCorrect) {
      btn.classList.add('wrong');
      icon.textContent = '✗';
    }
  });

  // Save to streak history
  streakHistory.push({ dialogue: streaks[streakIndex][qIndex].dialogue, correct, userAns: chosen, wasCorrect: isCorrect });

  // Advance after short delay
  setTimeout(() => {
    qIndex++;
    $('pts-flash').style.color = 'var(--accent-gold)';
    loadQuestion();
  }, 1400);
}

// ── Timer Expired ──────────────────────────────
function timeExpired() {
  if (answered) return;
  answered = true;
  streak = 0;
  $('streak-badge').style.display = 'none';
  $('pts-flash').textContent = `⏱ Time's up! Correct: ${streaks[streakIndex][qIndex].correct}`;
  $('pts-flash').style.color = 'var(--danger)';

  // Reveal correct answer
  document.querySelectorAll('.opt-btn').forEach(btn => {
    btn.disabled = true;
    const name = btn.querySelector('.opt-name').textContent;
    if (name === streaks[streakIndex][qIndex].correct) btn.classList.add('reveal');
  });

  streakHistory.push({ dialogue: streaks[streakIndex][qIndex].dialogue, correct: streaks[streakIndex][qIndex].correct, userAns: null, wasCorrect: false });

  setTimeout(() => {
    qIndex++;
    $('pts-flash').style.color = 'var(--accent-gold)';
    loadQuestion();
  }, 1600);
}

// ── End of one streak ──────────────────────────
function endStreak() {
  clearInterval(timerInterval);
  const streakSize = streaks[streakIndex].length;
  const acc        = Math.round((streakCorrect / streakSize) * 100);
  const isLast     = streakIndex >= streaks.length - 1;
  const remaining  = questions.length - (streakIndex + 1) * STREAK_SIZE;

  // Pick result copy
  let iconCls, emoji, title, sub;
  if (acc >= 80)      { iconCls='win'; emoji='🏆'; title='Brilliant!';      sub='You nailed this streak!'; }
  else if (acc >= 60) { iconCls='mid'; emoji='⭐'; title='Well Done!';       sub='Solid reading of the characters.'; }
  else                { iconCls='low'; emoji='📖'; title='Keep Trying!';     sub='Review the story to get more right.'; }

  // Fill streak result panel
  $('sk-eyebrow').textContent  = `Streak ${streakIndex + 1} of ${streaks.length} Complete`;
  $('sk-icon').className       = 'result-icon ' + iconCls;
  $('sk-icon').textContent     = emoji;
  $('sk-title').textContent    = title;
  $('sk-sub').textContent      = sub;
  $('sk-score').textContent    = streakScore;
  $('sk-correct').textContent  = `${streakCorrect}/${streakSize}`;
  $('sk-acc').textContent      = acc + '%';

  // Remaining questions badge
  const remCard = $('sk-remaining-card');
  if (isLast) {
    remCard.style.display = 'none';
  } else {
    remCard.style.display = 'block';
    $('sk-remaining').textContent = Math.min(remaining, STREAK_SIZE) + ' next';
  }

  // Mini review for this streak
  const list = $('sk-review-list');
  list.innerHTML = '';
  streakHistory.forEach(h => {
    const el = document.createElement('div');
    el.className = 'review-item';
    const ansHtml = h.wasCorrect
      ? `<span class="correct-ans">✓ ${esc(h.correct)}</span>`
      : `<span class="your-ans">✗ ${h.userAns ? esc(h.userAns) : 'No answer'}</span> → <span class="correct-ans">${esc(h.correct)}</span>`;
    el.innerHTML = `
      <div class="review-badge ${h.wasCorrect?'ok':'bad'}">${h.wasCorrect?'✓':'✗'}</div>
      <div class="review-body">
        <div class="review-dialogue">"${esc(h.dialogue.length>80?h.dialogue.slice(0,80)+'…':h.dialogue)}"</div>
        <div class="review-answer">${ansHtml}</div>
      </div>`;
    list.appendChild(el);
  });

  // Buttons
  const btns = $('sk-buttons');
  if (isLast) {
    btns.innerHTML = `
      <button class="btn-play" onclick="window.location.href='leaderboard.php'">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 3 21 3 21 7"/><polyline points="10 17 21 6"/><path d="M21 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h6"/></svg>
        View Leaderboard
      </button>
      <button class="btn-secondary" onclick="showPhase('select')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Choose Another
      </button>`;
  } else {
    btns.innerHTML = `
      <button class="btn-play" onclick="nextStreak()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        Next Streak →
      </button>
      <button class="btn-secondary" onclick="showPhase('select')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Stop Here
      </button>`;
  }

  // Save this streak's score
  saveScore(streakScore, streakCorrect, streakSize);
  showPhase('streak');
}

// ── Advance to next streak ──────────────────────
function nextStreak() {
  streakIndex++;
  beginStreak();
}

// ── End Game (legacy alias — not used in streak mode) ───
function endGame() {
  endStreak();
}

async function saveScore(score, correctCnt, totalCnt) {
  // Saves each streak independently — no UI feedback needed mid-game,
  // only show it on the streak result panel if it's the last streak.
  try {
    await fetch('?ajax=save_score', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ story_id: selectedStory.story_id, score, correct_count: correctCnt, total_count: totalCnt })
    });
  } catch(e) { /* silent — non-critical */ }
}

function restartGame() {
  if (selectedStory) startGame();
  else showPhase('select');
}

// Alias used by streak result "Play Again" button
function playAgainFromStreak() { startGame(); }

loadStories();
</script>
</body>
</html>