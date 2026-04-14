<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$username   = htmlspecialchars($_SESSION['user_name'] ?? 'Player');
$first_name = htmlspecialchars($_SESSION['first_name'] ?? $username);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Flash Words — Game Arena</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet" />

<style>
  /* ── CSS Variables ── */
  :root {
    --void:       #0b0f14;
    --void-2:     #111820;
    --void-3:     #181f28;
    --cyan:       #3df2e0;
    --cyan-dim:   #1ab8a8;
    --cyan-glow:  rgba(61,242,224,0.18);
    --cyan-faint: rgba(61,242,224,0.06);
    --amber:      #f2a73d;
    --red:        #f2503d;
    --green:      #3df27a;
    --purple:     #9d6fff;
    --text:       #c8d8e8;
    --text-dim:   #5a7080;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background-color: var(--void);
    color: var(--text);
    font-family: 'Rajdhani', sans-serif;
    font-size: 16px;
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* ── Grid background ── */
  body::before {
    content: '';
    position: fixed; inset: 0;
    background-image:
      linear-gradient(rgba(61,242,224,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(61,242,224,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
  }

  /* Scanline overlay */
  body::after {
    content: '';
    position: fixed; inset: 0;
    background: repeating-linear-gradient(
      0deg,
      transparent,
      transparent 2px,
      rgba(0,0,0,0.08) 2px,
      rgba(0,0,0,0.08) 4px
    );
    pointer-events: none;
    z-index: 0;
  }

  .z-main { position: relative; z-index: 1; }

  /* ── Typography ── */
  .font-orbitron  { font-family: 'Orbitron', monospace; }
  .font-mono-tech { font-family: 'Share Tech Mono', monospace; }
  .font-rajdhani  { font-family: 'Rajdhani', sans-serif; }

  /* ── Neon glow effects ── */
  .glow-cyan  { text-shadow: 0 0 8px var(--cyan), 0 0 20px rgba(61,242,224,0.4); }
  .glow-amber { text-shadow: 0 0 8px var(--amber), 0 0 20px rgba(242,167,61,0.4); }
  .glow-red   { text-shadow: 0 0 8px var(--red),   0 0 20px rgba(242,80,61,0.4); }
  .glow-green { text-shadow: 0 0 8px var(--green),  0 0 20px rgba(61,242,122,0.4); }

  .border-cyan-glow {
    border: 1px solid var(--cyan-dim);
    box-shadow: 0 0 12px var(--cyan-glow), inset 0 0 12px rgba(61,242,224,0.03);
  }

  /* ── Back link ── */
  .back-link {
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--cyan-dim);
    font-family: 'Orbitron', monospace;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    text-decoration: none;
    padding: 8px 16px;
    border: 1px solid rgba(61,242,224,0.2);
    border-radius: 3px;
    transition: all 0.2s;
  }
  .back-link:hover {
    color: var(--cyan);
    border-color: var(--cyan-dim);
    box-shadow: 0 0 10px var(--cyan-glow);
    background: var(--cyan-faint);
  }

  /* ── Story cards ── */
  .story-card {
    background: var(--void-2);
    border: 1px solid rgba(61,242,224,0.12);
    border-radius: 6px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.25s;
    position: relative;
    overflow: hidden;
  }
  .story-card::before {
    content: '';
    position: absolute; top: 0; left: 0;
    width: 3px; height: 100%;
    background: var(--cyan-dim);
    opacity: 0;
    transition: opacity 0.25s;
  }
  .story-card:hover {
    border-color: var(--cyan-dim);
    transform: translateY(-2px);
    box-shadow: 0 4px 24px rgba(61,242,224,0.12);
  }
  .story-card:hover::before { opacity: 1; }

  /* ── WPM Badge ── */
  .wpm-badge {
    display: inline-flex; align-items: center;
    padding: 3px 10px;
    border-radius: 2px;
    font-family: 'Orbitron', monospace;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
  }
  .wpm-60  { background: rgba(61,242,122,0.12); color: var(--green); border: 1px solid rgba(61,242,122,0.3); }
  .wpm-100 { background: rgba(61,242,224,0.1);  color: var(--cyan);  border: 1px solid rgba(61,242,224,0.3); }
  .wpm-140 { background: rgba(157,111,255,0.1); color: var(--purple);border: 1px solid rgba(157,111,255,0.3); }
  .wpm-180 { background: rgba(242,167,61,0.1);  color: var(--amber); border: 1px solid rgba(242,167,61,0.3); }
  .wpm-220 { background: rgba(242,80,61,0.12);  color: var(--red);   border: 1px solid rgba(242,80,61,0.3); }

  /* ── Streak dots ── */
  .streak-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    border: 1px solid var(--text-dim);
    background: transparent;
    transition: all 0.3s;
  }
  .streak-dot.active {
    background: var(--cyan);
    border-color: var(--cyan);
    box-shadow: 0 0 6px var(--cyan);
  }

  /* ── Flash passage area ── */
  /* RSVP flash mode */
  #rsvp-word.flash-out { opacity: 0; }
  #passage-display {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(18px, 2.5vw, 26px);
    font-weight: 500;
    line-height: 1.85;
    color: var(--text);
    letter-spacing: 0.02em;
    transition: opacity 0.15s;
  }
  #passage-display.flash-out { opacity: 0; }

  /* ── Word highlight animation ── */
  .word-span {
    display: inline;
    transition: color 0.1s, background 0.1s;
  }
  .word-span.current-word {
    color: var(--void);
    background: var(--cyan);
    border-radius: 2px;
    padding: 0 2px;
  }
  .word-span.read-word { color: var(--text-dim); }

  /* ── Progress bar ── */
  .progress-bar-track {
    background: rgba(61,242,224,0.08);
    border-radius: 2px;
    overflow: hidden;
    height: 3px;
  }
  .progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--cyan-dim), var(--cyan));
    border-radius: 2px;
    transition: width 0.1s linear;
    box-shadow: 0 0 8px var(--cyan);
  }

  /* ── Timer ring ── */
  .timer-ring-svg { transform: rotate(-90deg); }
  .timer-ring-bg  { stroke: rgba(61,242,224,0.1); }
  .timer-ring-fill {
    stroke: var(--cyan);
    stroke-linecap: round;
    filter: drop-shadow(0 0 4px var(--cyan));
    transition: stroke-dashoffset 0.1s linear, stroke 0.3s;
  }

  /* ── Buttons ── */
  .btn-primary {
    font-family: 'Orbitron', monospace;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    padding: 12px 28px;
    background: transparent;
    border: 1px solid var(--cyan);
    color: var(--cyan);
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
  }
  .btn-primary::before {
    content: '';
    position: absolute; inset: 0;
    background: var(--cyan);
    opacity: 0;
    transition: opacity 0.2s;
  }
  .btn-primary:hover::before { opacity: 0.1; }
  .btn-primary:hover {
    box-shadow: 0 0 20px var(--cyan-glow);
    text-shadow: 0 0 8px var(--cyan);
  }
  .btn-primary:disabled {
    opacity: 0.3; cursor: not-allowed;
    box-shadow: none;
  }

  .btn-option {
    width: 100%;
    font-family: 'Rajdhani', sans-serif;
    font-size: 16px;
    font-weight: 500;
    padding: 16px 20px;
    background: var(--void-2);
    border: 1px solid rgba(61,242,224,0.15);
    color: var(--text);
    border-radius: 4px;
    cursor: pointer;
    text-align: left;
    transition: all 0.2s;
    letter-spacing: 0.3px;
  }
  .btn-option:hover:not(:disabled) {
    border-color: var(--cyan-dim);
    background: var(--cyan-faint);
    color: var(--cyan);
    transform: translateX(4px);
  }
  .btn-option.correct {
    border-color: var(--green);
    background: rgba(61,242,122,0.08);
    color: var(--green);
    box-shadow: 0 0 12px rgba(61,242,122,0.2);
  }
  .btn-option.wrong {
    border-color: var(--red);
    background: rgba(242,80,61,0.08);
    color: var(--red);
    box-shadow: 0 0 12px rgba(242,80,61,0.2);
  }

  /* ── Result overlay ── */
  .result-badge {
    font-family: 'Orbitron', monospace;
    font-size: 13px;
    letter-spacing: 3px;
    text-transform: uppercase;
    padding: 8px 20px;
    border-radius: 2px;
    display: inline-block;
  }
  .result-correct {
    background: rgba(61,242,122,0.1);
    color: var(--green);
    border: 1px solid var(--green);
    text-shadow: 0 0 8px var(--green);
    box-shadow: 0 0 16px rgba(61,242,122,0.2);
  }
  .result-wrong {
    background: rgba(242,80,61,0.1);
    color: var(--red);
    border: 1px solid var(--red);
    text-shadow: 0 0 8px var(--red);
    box-shadow: 0 0 16px rgba(242,80,61,0.2);
  }

  /* ── Level change toast ── */
  #level-toast {
    position: fixed;
    top: 20px; left: 50%;
    transform: translateX(-50%) translateY(-80px);
    z-index: 100;
    font-family: 'Orbitron', monospace;
    font-size: 12px;
    letter-spacing: 2px;
    padding: 10px 24px;
    border-radius: 3px;
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    pointer-events: none;
  }
  #level-toast.show { transform: translateX(-50%) translateY(0); }
  #level-toast.up   { background: rgba(61,242,122,0.15); color: var(--green); border: 1px solid var(--green); }
  #level-toast.down { background: rgba(242,80,61,0.15);  color: var(--red);   border: 1px solid var(--red); }

  /* ── Leaderboard ── */
  .lb-row {
    display: grid;
    grid-template-columns: 32px 1fr 80px 70px 60px;
    gap: 12px;
    align-items: center;
    padding: 10px 14px;
    border-bottom: 1px solid rgba(61,242,224,0.06);
    font-size: 14px;
  }
  .lb-row:last-child { border-bottom: none; }
  .lb-rank { font-family: 'Orbitron', monospace; font-size: 11px; color: var(--text-dim); text-align: center; }
  .lb-rank.top { color: var(--amber); }

  /* ── Phase transitions ── */
  .phase { display: none; animation: fadeIn 0.3s ease; }
  .phase.active { display: block; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

  /* ── Glitch effect on title ── */
  @keyframes glitch {
    0%, 90%, 100% { clip-path: none; transform: none; }
    91% { clip-path: inset(20% 0 50% 0); transform: translateX(-3px); }
    93% { clip-path: inset(50% 0 20% 0); transform: translateX(3px); }
    95% { clip-path: none; transform: none; }
  }
  .glitch-text {
    position: relative;
    display: inline-block;
    animation: glitch 6s infinite;
  }

  /* ── Scrollbar ── */
  ::-webkit-scrollbar { width: 4px; }
  ::-webkit-scrollbar-track { background: var(--void); }
  ::-webkit-scrollbar-thumb { background: var(--cyan-dim); border-radius: 2px; }

  /* ── Part selector pills ── */
  .part-pill {
    padding: 6px 14px;
    border: 1px solid rgba(61,242,224,0.2);
    border-radius: 3px;
    font-family: 'Orbitron', monospace;
    font-size: 10px;
    letter-spacing: 1.5px;
    color: var(--text-dim);
    background: transparent;
    cursor: pointer;
    transition: all 0.2s;
  }
  .part-pill:hover, .part-pill.active {
    border-color: var(--cyan);
    color: var(--cyan);
    background: var(--cyan-faint);
    box-shadow: 0 0 8px var(--cyan-glow);
  }

  /* ── Reading mode styles ── */
  #reading-panel {
    background: var(--void-2);
    border-radius: 6px;
    min-height: 200px;
    padding: 32px;
    position: relative;
    overflow: hidden;
  }
  #reading-panel::before {
    content: 'READING';
    position: absolute; top: 10px; right: 16px;
    font-family: 'Orbitron', monospace;
    font-size: 9px;
    letter-spacing: 3px;
    color: rgba(61,242,224,0.2);
  }

  /* Pulsing cursor */
  @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
  .cursor { display: inline-block; width: 2px; height: 1.1em; background: var(--cyan); vertical-align: middle; margin-left: 2px; animation: blink 1s infinite; }

  /* Confetti-like correct flash */
  @keyframes correctFlash {
    0% { box-shadow: 0 0 0 0 rgba(61,242,122,0.5); }
    70% { box-shadow: 0 0 0 20px rgba(61,242,122,0); }
    100% { box-shadow: 0 0 0 0 rgba(61,242,122,0); }
  }
  .flash-correct { animation: correctFlash 0.6s ease; }

  @keyframes wrongShake {
    0%,100%{transform:translateX(0)}
    20%{transform:translateX(-8px)}
    40%{transform:translateX(8px)}
    60%{transform:translateX(-5px)}
    80%{transform:translateX(5px)}
  }
  .flash-wrong { animation: wrongShake 0.4s ease; }
</style>
</head>
<body>
<div class="z-main min-h-screen flex flex-col">

  <!-- ── Top bar ── -->
  <div class="flex items-center justify-between px-6 py-4 border-b border-cyan-glow" style="border-color: rgba(61,242,224,0.1);">
    <a href="index.php" class="back-link">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
      Back to Home
    </a>
    <div class="flex items-center gap-4">
      <span class="font-mono-tech text-xs" style="color:var(--text-dim);">
        ARENA <span style="color:var(--cyan)">// FLASH WORDS</span>
      </span>
      <div class="wpm-badge wpm-60" id="header-wpm-badge">60 WPM</div>
    </div>
    <div class="flex items-center gap-3">
      <div class="w-2 h-2 rounded-full" style="background:var(--cyan); box-shadow:0 0 6px var(--cyan);"></div>
      <span class="font-rajdhani text-sm font-medium" style="color:var(--text-dim);"><?= $first_name ?></span>
    </div>
  </div>

  <!-- ── Main content ── -->
  <div class="flex-1 px-6 py-8 max-w-5xl mx-auto w-full">

    <!-- ══ PHASE: Story Select ══ -->
    <div id="phase-select" class="phase active">
      <div class="mb-10 text-center">
        <h1 class="font-orbitron text-3xl font-black mb-2 glow-cyan glitch-text" style="color:var(--cyan);">
          FLASH WORDS
        </h1>
        <p class="font-rajdhani text-base font-300" style="color:var(--text-dim); letter-spacing:3px; text-transform:uppercase;">
          Speed Reading Arena — Test Your Limits
        </p>
      </div>

      <!-- Stats strip -->
      <div class="grid grid-cols-3 gap-4 mb-10" id="global-stats" style="display:none!important;"></div>

      <!-- Loading -->
      <div id="stories-loading" class="text-center py-16">
        <div class="font-mono-tech text-sm" style="color:var(--cyan-dim);">
          <span id="load-dots">LOADING STORIES</span>
        </div>
      </div>

      <!-- Stories grid -->
      <div id="stories-grid" class="grid grid-cols-1 md:grid-cols-2 gap-5" style="display:none;"></div>

      <!-- Empty state -->
      <div id="stories-empty" class="text-center py-16" style="display:none;">
        <div class="font-orbitron text-xs mb-3" style="color:var(--text-dim); letter-spacing:3px;">NO ARENA CONTENT</div>
        <p class="font-rajdhani text-sm" style="color:var(--text-dim);">
          Story authors haven't added questions yet.<br/>Check back later or ask an author to add Flash Words questions.
        </p>
      </div>
    </div>

    <!-- ══ PHASE: Part Select ══ -->
    <div id="phase-parts" class="phase">
      <button class="back-link mb-8" onclick="showPhase('select')" style="border:none; background:none; cursor:pointer;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
        Back to Stories
      </button>

      <div id="part-story-title" class="font-orbitron text-xl font-bold mb-1" style="color:var(--cyan);"></div>
      <div class="font-rajdhani text-sm mb-8" style="color:var(--text-dim); letter-spacing:2px; text-transform:uppercase;">SELECT STORY PART</div>

      <div id="parts-grid" class="grid grid-cols-1 gap-4"></div>
    </div>

    <!-- ══ PHASE: Game ══ -->
    <div id="phase-game" class="phase">
      <div class="flex items-center justify-between mb-6">
        <button class="back-link" onclick="backToPartsFromGame()" style="border:none; background:none; cursor:pointer;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M19 12H5M12 5l-7 7 7 7"/>
          </svg>
          Exit Game
        </button>

        <!-- Live stats -->
        <div class="flex items-center gap-6">
          <div class="text-center">
            <div class="font-orbitron text-xs mb-1" style="color:var(--text-dim);">STREAK</div>
            <div id="streak-dots" class="flex gap-2"></div>
          </div>
          <div class="text-center">
            <div class="font-orbitron text-xs mb-1" style="color:var(--text-dim);">ACCURACY</div>
            <div id="live-accuracy" class="font-orbitron text-sm font-bold" style="color:var(--cyan);">—</div>
          </div>
          <div class="text-center">
            <div class="font-orbitron text-xs mb-1" style="color:var(--text-dim);">BEST WPM</div>
            <div id="live-best-wpm" class="font-orbitron text-sm font-bold" style="color:var(--amber);">60</div>
          </div>
        </div>
      </div>

      <!-- ── Sub-phase: Countdown ── -->
      <div id="sub-countdown" class="text-center py-16">
        <div class="font-orbitron text-xs mb-4" style="color:var(--text-dim); letter-spacing:3px;">PREPARE TO READ</div>
        <div id="countdown-num" class="font-orbitron font-black glow-cyan" style="font-size:80px; color:var(--cyan); line-height:1;">3</div>
        <div class="mt-4 font-mono-tech text-sm" style="color:var(--text-dim);">
          Target: <span id="countdown-wpm" class="font-bold" style="color:var(--cyan);"></span>
        </div>
      </div>

      <!-- ── Sub-phase: Reading ── -->
      <div id="sub-reading" style="display:none;">
        <!-- Header row -->
        <div class="flex items-center justify-between mb-4">
          <div>
            <div class="font-orbitron text-xs mb-1" style="color:var(--text-dim);">CURRENT SPEED</div>
            <div id="game-wpm-badge" class="wpm-badge wpm-60">60 WPM</div>
          </div>
          <!-- Timer -->
          <div class="flex flex-col items-center">
            <svg class="timer-ring-svg" width="60" height="60" viewBox="0 0 60 60">
              <circle class="timer-ring-bg" cx="30" cy="30" r="26" fill="none" stroke-width="3"/>
              <circle id="timer-ring" class="timer-ring-fill" cx="30" cy="30" r="26"
                fill="none" stroke-width="3"
                stroke-dasharray="163.4"
                stroke-dashoffset="0"/>
            </svg>
            <div id="timer-secs" class="font-orbitron text-xs mt-1" style="color:var(--cyan);">—</div>
          </div>
          <div class="text-right">
            <div class="font-orbitron text-xs mb-1" style="color:var(--text-dim);">WORDS</div>
            <div id="word-counter" class="font-orbitron text-sm font-bold" style="color:var(--text);">0 / 0</div>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="progress-bar-track mb-4">
          <div class="progress-bar-fill" id="reading-progress" style="width:0%"></div>
        </div>

        <!-- RSVP single-word display -->
        <div id="reading-panel" class="border-cyan-glow" style="
          display:flex; flex-direction:column; align-items:center;
          justify-content:center; min-height:140px; padding:24px 32px; text-align:center;">
          <div id="rsvp-word" style="
            font-family:'Orbitron',monospace;
            font-size:clamp(28px,5vw,52px);
            font-weight:900;
            color:var(--cyan);
            text-shadow:0 0 20px rgba(61,242,224,0.5);
            letter-spacing:2px;
            transition:opacity 0.08s ease;
            min-height:64px; display:flex; align-items:center;">
          </div>
          <div id="rsvp-chunk" style="
            font-family:'Rajdhani',sans-serif;
            font-size:13px; color:var(--text-dim);
            margin-top:12px; letter-spacing:1px;
            min-height:20px;">
          </div>
        </div>
      </div>

      <!-- ── Sub-phase: Question ── -->
      <div id="sub-question" style="display:none;">
        <div class="mb-6 text-center">
          <div class="font-orbitron text-xs mb-3" style="color:var(--text-dim); letter-spacing:3px;">COMPREHENSION CHECK</div>
          <!-- Answer timer -->
          <div class="flex items-center justify-center gap-3 mb-4">
            <div class="font-mono-tech text-sm" style="color:var(--text-dim);">Answer in:</div>
            <div id="ans-timer" class="font-orbitron text-xl font-bold" style="color:var(--amber);">10</div>
          </div>
          <!-- Answer progress bar -->
          <div class="progress-bar-track max-w-xs mx-auto mb-6" style="background:rgba(242,167,61,0.1);">
            <div id="ans-progress" class="progress-bar-fill" style="width:100%; background: linear-gradient(90deg, var(--amber), #f2c03d); box-shadow:0 0 8px var(--amber);"></div>
          </div>
        </div>

        <div class="border-cyan-glow rounded-lg p-6 mb-6" style="background:var(--void-3);">
          <p id="question-text" class="font-rajdhani text-lg font-medium mb-6" style="line-height:1.6; color:var(--text);"></p>
          <div class="flex flex-col gap-3">
            <button class="btn-option" id="opt-a" onclick="submitAnswer(this)"></button>
            <button class="btn-option" id="opt-b" onclick="submitAnswer(this)"></button>
          </div>
        </div>

        <!-- Result area -->
        <div id="result-area" style="display:none;" class="text-center py-4">
          <div id="result-badge" class="result-badge mb-3"></div>
          <div id="result-msg"  class="font-rajdhani text-base mb-2" style="color:var(--text-dim);"></div>
          <div id="level-change-msg" class="font-orbitron text-xs" style="letter-spacing:2px;"></div>

          <div class="mt-6 flex justify-center gap-4">
            <button class="btn-primary" onclick="startRound()">PLAY AGAIN</button>
            <button class="btn-primary" onclick="showLeaderboard()" style="border-color:var(--amber); color:var(--amber);">LEADERBOARD</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ PHASE: Leaderboard ══ -->
    <div id="phase-leaderboard" class="phase">
      <button class="back-link mb-8" onclick="showPhase('game')" style="border:none; background:none; cursor:pointer;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
        Back to Game
      </button>

      <div class="font-orbitron text-xl font-bold mb-1" style="color:var(--amber);">⚡ LEADERBOARD</div>
      <div id="lb-subtitle" class="font-rajdhani text-sm mb-6" style="color:var(--text-dim); letter-spacing:2px;"></div>

      <!-- Header -->
      <div class="lb-row font-orbitron text-xs border-b" style="color:var(--text-dim); border-color:rgba(61,242,224,0.15); padding-bottom:8px;">
        <div>#</div>
        <div>PLAYER</div>
        <div>BEST WPM</div>
        <div>CORRECT</div>
        <div>ACC%</div>
      </div>
      <div id="lb-body"></div>

      <div class="mt-8 flex justify-center">
        <button class="btn-primary" onclick="startRound()">PLAY AGAIN</button>
      </div>
    </div>

  </div><!-- /main content -->
</div>

<!-- Level toast -->
<div id="level-toast"></div>

<script>
// ═══════════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════════
const API = 'arena_api.php';
const WPM_TIERS    = [60, 100, 140, 180, 220];
const WINS_NEEDED  = 3;  // consecutive correct to advance
const LOSSES_NEEDED = 2; // consecutive wrong to drop
const ANS_TIME     = 10; // seconds to answer
const READ_TIME_MS = 60000; // always 60 seconds reading time

let state = {
  stories: [],
  selectedStory: null,
  selectedPart: null,
  currentSession: null,
  currentWpm: 60,
  winStreak: 0,
  lossStreak: 0,
  bestWpm: 60,
  isFirstRound: true,  // true until first processResult completes
  totalCorrect: 0,
  totalAttempts: 0,
  readTimer: null,
  ansTimer: null,
  wordTimer: null,
  answerLocked: false,
};

// ═══════════════════════════════════════════════════════════════
// UTILITY
// ═══════════════════════════════════════════════════════════════
const $ = id => document.getElementById(id);

function showPhase(name) {
  document.querySelectorAll('.phase').forEach(el => el.classList.remove('active'));
  $('phase-' + name).classList.add('active');
}

function wpmClass(wpm) {
  if (wpm <= 60)  return 'wpm-60';
  if (wpm <= 100) return 'wpm-100';
  if (wpm <= 140) return 'wpm-140';
  if (wpm <= 180) return 'wpm-180';
  return 'wpm-220';
}

function showToast(text, dir) {
  const t = $('level-toast');
  t.textContent = text;
  t.className = `show ${dir}`;
  setTimeout(() => { t.className = ''; }, 2800);
}

async function api(params, method = 'GET') {
  let res;
  if (method === 'GET') {
    const q = new URLSearchParams(params).toString();
    res = await fetch(`${API}?${q}`);
  } else {
    res = await fetch(API + '?action=' + params.action, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(params)
    });
  }
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch(e) {
    // PHP returned non-JSON (syntax error, warning, etc.) — surface it
    throw new Error('PHP Error: ' + text.replace(/<[^>]+>/g,'').substring(0, 300));
  }
}

function updateHeaderBadge(wpm) {
  const b = $('header-wpm-badge');
  b.textContent = wpm + ' WPM';
  b.className = 'wpm-badge ' + wpmClass(wpm);
}

function updateStreakDots(winStreak, lossStreak) {
  const container = $('streak-dots');
  container.innerHTML = '';
  // Show win dots (cyan) and loss dots (red) separately
  for (let i = 0; i < WINS_NEEDED; i++) {
    const dot = document.createElement('div');
    dot.className = 'streak-dot' + (i < winStreak ? ' active' : '');
    container.appendChild(dot);
  }
  if (lossStreak > 0) {
    const sep = document.createElement('div');
    sep.style.cssText = 'width:1px;height:10px;background:var(--text-dim);margin:0 4px;';
    container.appendChild(sep);
    for (let i = 0; i < LOSSES_NEEDED; i++) {
      const dot = document.createElement('div');
      dot.className = 'streak-dot' + (i < lossStreak ? ' active' : '');
      dot.style.background = i < lossStreak ? 'var(--red)' : '';
      dot.style.borderColor = i < lossStreak ? 'var(--red)' : '';
      dot.style.boxShadow   = i < lossStreak ? '0 0 6px var(--red)' : '';
      container.appendChild(dot);
    }
  }
}

// Animated loading dots
let loadDotInterval = setInterval(() => {
  const el = $('load-dots');
  if (!el) return;
  const dots = el.textContent.slice(14);
  el.textContent = 'LOADING STORIES' + (dots.length >= 3 ? '' : dots + '.');
}, 400);

// ═══════════════════════════════════════════════════════════════
// PHASE 1 — LOAD STORIES
// ═══════════════════════════════════════════════════════════════
async function loadStories() {
  try {
    const data = await api({action: 'get_stories'});
    clearInterval(loadDotInterval);
    $('stories-loading').style.display = 'none';

    if (!data.success || !data.stories.length) {
      $('stories-empty').style.display = 'block';
      return;
    }

    state.stories = data.stories;
    const grid = $('stories-grid');
    grid.style.display = 'grid';
    grid.innerHTML = '';

    data.stories.forEach(s => {
      const wpm = s.current_wpm || 60;
      const best = s.best_wpm || 60;
      const accuracy = s.total_attempts > 0
        ? Math.round((s.total_correct / s.total_attempts) * 100) : null;

      const card = document.createElement('div');
      card.className = 'story-card';
      card.innerHTML = `
        <div class="flex items-start justify-between mb-3">
          <div class="flex-1 pr-3">
            <div class="font-orbitron text-sm font-bold mb-1" style="color:var(--cyan);">${s.title}</div>
            <div class="font-mono-tech text-xs mb-2" style="color:var(--text-dim);">${s.category}</div>
          </div>
          <div class="wpm-badge ${wpmClass(wpm)}">${wpm} WPM</div>
        </div>
        <p class="font-rajdhani text-sm mb-4 line-clamp-2" style="color:var(--text-dim); line-height:1.5;">
          ${s.description ? s.description.substring(0, 100) + '...' : ''}
        </p>
        <div class="flex items-center justify-between pt-3" style="border-top:1px solid rgba(61,242,224,0.08);">
          <div class="font-mono-tech text-xs" style="color:var(--text-dim);">
            <span style="color:var(--cyan);">${s.parts_with_questions}</span> part${s.parts_with_questions != 1 ? 's' : ''} available
          </div>
          <div class="flex gap-3">
            ${best > 60 ? `<span class="font-mono-tech text-xs" style="color:var(--amber);">BEST: ${best} WPM</span>` : ''}
            ${accuracy !== null ? `<span class="font-mono-tech text-xs" style="color:var(--text-dim);">ACC: ${accuracy}%</span>` : ''}
          </div>
        </div>
      `;
      card.addEventListener('click', () => loadParts(s));
      grid.appendChild(card);
    });
  } catch(e) {
    $('stories-loading').innerHTML = `<div class="font-mono-tech text-xs" style="color:var(--red);white-space:pre-wrap;word-break:break-all;">ERROR: ${e.message}</div>`;
  }
}

// ═══════════════════════════════════════════════════════════════
// PHASE 2 — SELECT PART
// ═══════════════════════════════════════════════════════════════
async function loadParts(story) {
  state.selectedStory = story;
  $('part-story-title').textContent = story.title;
  showPhase('parts');

  const grid = $('parts-grid');
  grid.innerHTML = `<div class="font-mono-tech text-xs text-center py-8" style="color:var(--cyan-dim);">LOADING PARTS...</div>`;

  try {
    const data = await api({action: 'get_parts', story_id: story.story_id});
    grid.innerHTML = '';

    if (!data.success || !data.parts.length) {
      grid.innerHTML = `<div class="font-rajdhani text-sm text-center py-8" style="color:var(--text-dim);">No parts found.</div>`;
      return;
    }

    data.parts.forEach(part => {
      const wpm  = part.current_wpm || 60;
      const best = part.best_wpm || 60;
      const accuracy = part.total_attempts > 0
        ? Math.round((part.total_correct / part.total_attempts) * 100) : null;
      const attempts = part.total_attempts || 0;

      const row = document.createElement('div');
      row.className = 'story-card';
      row.innerHTML = `
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="font-orbitron text-2xl font-black" style="color:rgba(61,242,224,0.2);">
              ${part.part_number === 0 ? 'GK' : String(part.part_number).padStart(2, '0')}
            </div>
            <div>
              <div class="font-orbitron text-sm font-bold mb-1" style="color:var(--text);">
                ${part.label || ('Part ' + part.part_number)}
              </div>
              <div class="font-mono-tech text-xs" style="color:var(--text-dim);">
                ${part.question_count} question${part.question_count != 1 ? 's' : ''}
                ${attempts > 0 ? ` · ${attempts} attempt${attempts != 1 ? 's' : ''}` : ''}
              </div>
            </div>
          </div>
          <div class="flex items-center gap-4">
            ${accuracy !== null ? `<div class="text-right"><div class="font-mono-tech text-xs" style="color:var(--text-dim);">ACC</div><div class="font-orbitron text-sm font-bold" style="color:var(--cyan);">${accuracy}%</div></div>` : ''}
            ${best > 60 ? `<div class="text-right"><div class="font-mono-tech text-xs" style="color:var(--text-dim);">BEST</div><div class="font-orbitron text-sm font-bold" style="color:var(--amber);">${best} WPM</div></div>` : ''}
            <div class="wpm-badge ${wpmClass(wpm)}">${wpm} WPM</div>
          </div>
        </div>
      `;
      row.addEventListener('click', () => startGame(part));
      grid.appendChild(row);
    });
  } catch(e) {
    grid.innerHTML = `<div class="font-mono-tech text-xs text-center py-8" style="color:var(--red);">ERROR LOADING PARTS</div>`;
  }
}

// ═══════════════════════════════════════════════════════════════
// PHASE 3 — GAME
// ═══════════════════════════════════════════════════════════════
function startGame(part) {
  state.selectedPart  = part;
  state.currentWpm    = part.current_wpm || 60;
  state.winStreak     = part.streak || 0;
  state.lossStreak    = 0;
  state.bestWpm       = part.best_wpm || 60;
  state.totalCorrect  = part.total_correct || 0;
  state.totalAttempts = part.total_attempts || 0;

  state.isFirstRound = true; // reset for new game session
  showPhase('game');
  updateHeaderBadge(state.currentWpm);
  updateStreakDots(state.winStreak, state.lossStreak);
  updateLiveStats();

  $('sub-reading').style.display   = 'none';
  $('sub-question').style.display  = 'none';
  $('result-area').style.display   = 'none';
  startRound();
}

function backToPartsFromGame() {
  clearAllTimers();
  loadParts(state.selectedStory);
}

async function startRound() {
  clearAllTimers();
  $('result-area').style.display   = 'none';
  $('sub-question').style.display  = 'none';
  $('sub-reading').style.display   = 'none';

  // Countdown
  $('sub-countdown').style.display = 'block';
  $('countdown-wpm').textContent   = state.currentWpm + ' WPM';

  // Load session data during countdown
  const sessionPromise = api({
    action: 'get_session',
    story_id: state.selectedStory.story_id,
    part_number: state.selectedPart.part_number
  });

  await countdown(3);
  $('sub-countdown').style.display = 'none';

  const data = await sessionPromise;
  if (!data.success) {
    alert('Error loading session: ' + (data.error || 'Unknown'));
    return;
  }

  state.currentSession = data;
  // Only use server values on the first round of a new game session
  // After that, state is authoritative (updated by processResult after each answer)
  if (state.isFirstRound) {
    state.currentWpm  = data.current_wpm;
    state.winStreak   = data.streak || 0;
    state.lossStreak  = 0;
  }
  state.bestWpm = data.best_wpm;
  updateHeaderBadge(state.currentWpm);
  updateStreakDots(state.winStreak, state.lossStreak);

  // Setup game WPM badge — always from state (authoritative after first round)
  const badge = $('game-wpm-badge');
  badge.textContent = state.currentWpm + ' WPM';
  badge.className   = 'wpm-badge ' + wpmClass(state.currentWpm);

  showReadingPhase(data);
}

function countdown(from) {
  return new Promise(resolve => {
    const el = $('countdown-num');
    el.textContent = from;
    let n = from;
    const interval = setInterval(() => {
      n--;
      if (n <= 0) {
        clearInterval(interval);
        el.textContent = 'GO!';
        setTimeout(resolve, 400);
      } else {
        el.textContent = n;
      }
    }, 700);
  });
}

function showReadingPhase(data) {
  $('sub-reading').style.display = 'block';
  state.answerLocked = false;

  // Always sync both badges and streak dots at reading start
  const badge = $('game-wpm-badge');
  badge.textContent = state.currentWpm + ' WPM';
  badge.className   = 'wpm-badge ' + wpmClass(state.currentWpm);
  updateHeaderBadge(state.currentWpm);
  updateStreakDots(state.winStreak, state.lossStreak);

  const words  = data.passage.split(/\s+/).filter(w => w.length > 0);
  const total  = words.length;
  const totalMs = READ_TIME_MS; // always 60 seconds regardless of WPM level
  const msPerWord = totalMs / total;
  const circumference = 163.4;

  $('word-counter').textContent = `1 / ${total}`;
  $('reading-progress').style.width = '0%';
  $('timer-secs').textContent = '60s';
  $('timer-ring').style.stroke = 'var(--cyan)';
  $('timer-ring').style.strokeDashoffset = '0';

  let wordIndex = 0;
  let elapsed   = 0;
  const TICK    = 50; // ms — smooth timer

  // Show a word in RSVP style
  function flashWord(idx) {
    const wordEl  = $('rsvp-word');
    const chunkEl = $('rsvp-chunk');
    if (!wordEl) return;

    // Flash out then in
    wordEl.style.opacity = '0';
    setTimeout(() => {
      wordEl.textContent  = words[idx] || '';
      wordEl.style.opacity = '1';
    }, 60);

    // Show surrounding context: 2 words before and after (dimmed)
    const start = Math.max(0, idx - 2);
    const end   = Math.min(total - 1, idx + 2);
    const parts = [];
    for (let i = start; i <= end; i++) {
      if (i === idx) parts.push(`<strong style="color:var(--text);">${words[i]}</strong>`);
      else           parts.push(`<span style="opacity:0.3;">${words[i]}</span>`);
    }
    chunkEl.innerHTML = parts.join(' ');

    $('word-counter').textContent = `${idx + 1} / ${total}`;
    $('reading-progress').style.width = ((idx + 1) / total * 100) + '%';
  }

  // Show first word immediately
  flashWord(0);
  wordIndex = 1;

  // Word timer: advance word every msPerWord ms
  state.wordTimer = setInterval(() => {
    if (wordIndex < total) {
      flashWord(wordIndex);
      wordIndex++;
    }
  }, msPerWord);

  // Ring countdown timer
  state.readTimer = setInterval(() => {
    elapsed += TICK;
    const remaining = Math.max(0, totalMs - elapsed);
    const secs = Math.ceil(remaining / 1000);
    $('timer-secs').textContent = secs + 's';

    const pct = remaining / totalMs;
    $('timer-ring').style.strokeDashoffset = circumference * (1 - pct);

    if (pct < 0.25)      $('timer-ring').style.stroke = 'var(--red)';
    else if (pct < 0.5)  $('timer-ring').style.stroke = 'var(--amber)';
    else                 $('timer-ring').style.stroke = 'var(--cyan)';

    if (elapsed >= totalMs) {
      clearInterval(state.readTimer);
      clearInterval(state.wordTimer);
      $('reading-progress').style.width = '100%';
      // Show last word fully before transition
      flashWord(total - 1);
      setTimeout(() => transitionToQuestion(data), 400);
    }
  }, TICK);
}

function transitionToQuestion(data) {
  // Brief flash then show question
  const panel = $('reading-panel');
  panel.style.transition = 'opacity 0.3s';
  panel.style.opacity = '0';

  setTimeout(() => {
    $('sub-reading').style.display  = 'none';
    panel.style.opacity = '1';
    showQuestion(data);
  }, 350);
}

function showQuestion(data) {
  $('sub-question').style.display = 'block';
  $('result-area').style.display  = 'none';
  state.answerLocked = false;

  $('question-text').textContent = data.question_text;

  const optA = $('opt-a');
  const optB = $('opt-b');

  optA.textContent     = '▹ ' + data.options[0].text;
  optA.dataset.correct = data.options[0].is_correct;
  optA.className       = 'btn-option';
  optA.disabled        = false;

  optB.textContent     = '▹ ' + data.options[1].text;
  optB.dataset.correct = data.options[1].is_correct;
  optB.className       = 'btn-option';
  optB.disabled        = false;

  // Answer timer
  startAnswerTimer(optA, optB);
}

function startAnswerTimer(optA, optB) {
  let remaining = ANS_TIME;
  $('ans-timer').textContent      = remaining;
  $('ans-progress').style.width   = '100%';
  $('ans-progress').style.transition = 'width 1s linear';

  state.ansTimer = setInterval(() => {
    remaining--;
    $('ans-timer').textContent = remaining;
    $('ans-progress').style.width = (remaining / ANS_TIME * 100) + '%';

    if (remaining <= 3) {
      $('ans-timer').style.color = 'var(--red)';
    }

    if (remaining <= 0) {
      clearInterval(state.ansTimer);
      if (!state.answerLocked) {
        // Time's up = wrong
        autoWrong(optA, optB);
      }
    }
  }, 1000);
}

function autoWrong(optA, optB) {
  state.answerLocked = true;
  optA.disabled = true;
  optB.disabled = true;
  processResult(false, null, optA, optB, true);
}

function submitAnswer(btn) {
  if (state.answerLocked) return;
  state.answerLocked = true;
  clearInterval(state.ansTimer);

  const isCorrect = btn.dataset.correct === 'true';
  const optA = $('opt-a');
  const optB = $('opt-b');
  optA.disabled = true;
  optB.disabled = true;

  // Visual feedback
  if (isCorrect) {
    btn.className = 'btn-option correct';
  } else {
    btn.className = 'btn-option wrong';
    // Show correct answer
    [optA, optB].forEach(o => {
      if (o.dataset.correct === 'true') o.className = 'btn-option correct';
    });
  }

  processResult(isCorrect, btn, optA, optB, false);
}

async function processResult(isCorrect, clickedBtn, optA, optB, timedOut) {
  const session = state.currentSession;
  const panel   = document.querySelector('#sub-question .border-cyan-glow');
  if (panel) panel.classList.add(isCorrect ? 'flash-correct' : 'flash-wrong');

  try {
    const data = await api({
      action:       'submit_answer',
      story_id:     state.selectedStory.story_id,
      part_number:  state.selectedPart.part_number,
      is_correct:   isCorrect,
    }, 'POST');

    if (!data.success) return;

    state.isFirstRound  = false; // state is now authoritative for this session
    state.currentWpm    = data.new_wpm;
    state.winStreak     = data.new_win_streak;
    state.lossStreak    = data.new_loss_streak;
    state.bestWpm       = data.best_wpm;
    state.totalCorrect  = data.total_correct;
    state.totalAttempts = data.total_attempts;

    updateHeaderBadge(state.currentWpm);
    updateStreakDots(state.winStreak, state.lossStreak);
    updateLiveStats();

    // Show result
    const badge  = $('result-badge');
    const msg    = $('result-msg');
    const lvlMsg = $('level-change-msg');

    if (timedOut) {
      badge.textContent = '⏱ TIME UP';
      badge.className   = 'result-badge result-wrong';
      const lossLeft = data.losses_to_drop - data.new_loss_streak;
      msg.textContent = `Time's up! ${lossLeft} more loss${lossLeft!==1?'es':''} will drop your level.`;
    } else if (isCorrect) {
      badge.textContent = '✓ CORRECT';
      badge.className   = 'result-badge result-correct';
      if (data.level_changed) {
        msg.textContent = `Streak complete! Advancing to ${data.new_wpm} WPM.`;
      } else {
        const left = data.wins_needed - data.new_win_streak;
        msg.textContent = `${data.new_win_streak} / ${data.wins_needed} — ${left} more win${left!==1?'s':''} to reach next level`;
      }
    } else {
      badge.textContent = '✗ WRONG';
      badge.className   = 'result-badge result-wrong';
      if (data.level_changed) {
        msg.textContent = `Dropped to ${data.new_wpm} WPM.`;
      } else {
        const lossLeft = data.losses_to_drop - data.new_loss_streak;
        msg.textContent = `${data.new_loss_streak} / ${data.losses_to_drop} losses — ${lossLeft} more will drop your level`;
      }
    }

    // Level toast
    if (data.level_changed) {
      if (data.direction === 'up') {
        showToast(`▲ LEVEL UP → ${data.new_wpm} WPM`, 'up');
        lvlMsg.textContent = `▲ ADVANCED TO ${data.new_wpm} WPM`;
        lvlMsg.style.color = 'var(--green)';
      } else {
        showToast(`▼ SPEED DOWN → ${data.new_wpm} WPM`, 'down');
        lvlMsg.textContent = `▼ DROPPED TO ${data.new_wpm} WPM`;
        lvlMsg.style.color = 'var(--red)';
      }
    } else {
      lvlMsg.textContent = `Accuracy: ${data.accuracy}%`;
      lvlMsg.style.color = 'var(--text-dim)';
    }

    $('result-area').style.display = 'block';

  } catch(e) {
    $('result-area').style.display = 'block';
  }
}

function updateLiveStats() {
  $('live-best-wpm').textContent = state.bestWpm + ' WPM';
  if (state.totalAttempts > 0) {
    const acc = Math.round((state.totalCorrect / state.totalAttempts) * 100);
    $('live-accuracy').textContent = acc + '%';
  } else {
    $('live-accuracy').textContent = '—';
  }
}

function clearAllTimers() {
  clearInterval(state.readTimer);
  clearInterval(state.ansTimer);
  clearInterval(state.wordTimer);
  state.readTimer = null;
  state.ansTimer  = null;
  state.wordTimer = null;
}

// ═══════════════════════════════════════════════════════════════
// PHASE 4 — LEADERBOARD
// ═══════════════════════════════════════════════════════════════
async function showLeaderboard() {
  showPhase('leaderboard');
  $('lb-subtitle').textContent = state.selectedStory.title + ' — Part ' + state.selectedPart.part_number;
  $('lb-body').innerHTML = `<div class="font-mono-tech text-xs text-center py-6" style="color:var(--cyan-dim);">LOADING...</div>`;

  try {
    const data = await api({
      action: 'get_leaderboard',
      story_id: state.selectedStory.story_id,
      part_number: state.selectedPart.part_number
    });

    if (!data.success || !data.leaderboard.length) {
      $('lb-body').innerHTML = `<div class="font-rajdhani text-sm text-center py-6" style="color:var(--text-dim);">No data yet. Be the first!</div>`;
      return;
    }

    const rankColors = ['var(--amber)', '#a0c4ff', '#c9a87c'];
    $('lb-body').innerHTML = data.leaderboard.map((row, i) => `
      <div class="lb-row">
        <div class="lb-rank ${i < 3 ? 'top' : ''}">${i < 3 ? ['①','②','③'][i] : (i+1)}</div>
        <div class="font-rajdhani font-medium" style="color:${i === 0 ? 'var(--amber)' : 'var(--text)'};">
          ${row.first_name || row.user_name}
          <span class="font-mono-tech text-xs ml-1" style="color:var(--text-dim);">@${row.user_name}</span>
        </div>
        <div class="wpm-badge ${wpmClass(row.best_wpm)}" style="width:fit-content;">${row.best_wpm} WPM</div>
        <div class="font-mono-tech text-xs text-center" style="color:var(--text-dim);">${row.total_correct}</div>
        <div class="font-orbitron text-xs text-center" style="color:var(--cyan);">${row.accuracy || 0}%</div>
      </div>
    `).join('');
  } catch(e) {
    $('lb-body').innerHTML = `<div style="color:var(--red);" class="text-center p-4 font-mono-tech text-xs">ERROR LOADING LEADERBOARD</div>`;
  }
}

// ═══════════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════════
loadStories();
</script>
</body>
</html>