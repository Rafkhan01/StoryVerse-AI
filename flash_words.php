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
<title>Flash Words — StoryVerse Arena</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Orbitron:wght@400;600;700;900&family=DM+Sans:wght@300;400;500&family=Share+Tech+Mono&display=swap" rel="stylesheet" />

<style>
/* ══════════════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════════════ */
:root {
  --bg-void:    #07080d;
  --bg-deep:    #0d0f18;
  --bg-surface: #12151f;
  --bg-card:    #171b28;
  --bg-raised:  #1e2335;

  /* Cyan accent — Flash Words signature colour */
  --cyan:       #2dd4bf;
  --cyan-dim:   #0f9885;
  --cyan-glow:  rgba(45,212,191,0.20);
  --cyan-faint: rgba(45,212,191,0.06);

  --amber:  #eab308;
  --red:    #ef4444;
  --green:  #22c55e;
  --purple: #8b5cf6;

  --text:     #f0eef8;
  --text-dim: #8b8fa8;
  --text-muted: #4a4d60;

  --border-dim:  rgba(255,255,255,0.07);
  --border-cyan: rgba(45,212,191,0.25);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  min-height: 100%;
  background: var(--bg-void);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 15px;
  line-height: 1.6;
  overflow-x: hidden;
}

/* ── Noise texture overlay ── */
body::before {
  content: '';
  position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.025'/%3E%3C/svg%3E");
  background-size: 256px;
}

/* ── Subtle grid ── */
body::after {
  content: '';
  position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background-image:
    linear-gradient(rgba(45,212,191,0.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(45,212,191,0.025) 1px, transparent 1px);
  background-size: 48px 48px;
}

/* ── Ambient glows ── */
.amb { position: fixed; pointer-events: none; z-index: 0; border-radius: 50%; filter: blur(130px); }
.amb-1 { width:600px;height:600px;background:#2dd4bf;opacity:.07;top:-180px;right:-120px; }
.amb-2 { width:450px;height:450px;background:#8b5cf6;opacity:.06;bottom:-100px;left:-150px; }
.amb-3 { width:300px;height:300px;background:#eab308;opacity:.04;top:45%;left:55%;transform:translate(-50%,-50%); }

.z1 { position: relative; z-index: 1; }

/* ══════════════════════════════════════════════
   TYPOGRAPHY HELPERS
══════════════════════════════════════════════ */
.font-orbitron  { font-family: 'Orbitron', monospace; }
.font-mono-tech { font-family: 'Share Tech Mono', monospace; }
.font-cinzel    { font-family: 'Cinzel', serif; }

/* ══════════════════════════════════════════════
   GLITCH EFFECT (title only — subtle & classy)
══════════════════════════════════════════════ */
@keyframes glitch-clip {
  0%,88%,100% { clip-path: none; transform: none; color: var(--cyan); }
  89% { clip-path: inset(15% 0 55% 0); transform: translateX(-4px); color: #fff; }
  91% { clip-path: inset(55% 0 10% 0); transform: translateX(4px);  color: var(--cyan); }
  93% { clip-path: inset(30% 0 40% 0); transform: translateX(-2px); color: #fff; }
  95% { clip-path: none; transform: none; color: var(--cyan); }
}
@keyframes glitch-shadow {
  0%,88%,100% { text-shadow: 0 0 16px rgba(45,212,191,0.45); }
  89% { text-shadow: 3px 0 0 rgba(239,68,68,0.7), -3px 0 0 rgba(45,212,191,0.7); }
  91% { text-shadow: -3px 0 0 rgba(139,92,246,0.7), 3px 0 0 rgba(45,212,191,0.7); }
  95% { text-shadow: 0 0 16px rgba(45,212,191,0.45); }
}
.glitch-title {
  font-family: 'Orbitron', monospace;
  font-weight: 900;
  color: var(--cyan);
  animation: glitch-clip 7s infinite, glitch-shadow 7s infinite;
  display: inline-block;
}

/* ══════════════════════════════════════════════
   HEADER
══════════════════════════════════════════════ */
.site-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 40px;
  border-bottom: 1px solid var(--border-dim);
  background: rgba(13,15,24,0.88);
  backdrop-filter: blur(18px);
  position: sticky; top: 0; z-index: 100;
}
.logo { display: flex; align-items: center; gap: 12px; text-decoration: none; }
.logo-icon {
  width: 34px; height: 34px; border-radius: 9px;
  background: linear-gradient(135deg, #2dd4bf, #8b5cf6);
  display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.logo-name { font-family: 'Cinzel', serif; font-size: 15px; font-weight: 700; color: var(--text); }
.logo-sub  { font-size: 9px; color: var(--text-muted); letter-spacing: .14em; text-transform: uppercase; }

.header-center {
  display: flex; align-items: center; gap: 10px;
  font-family: 'Share Tech Mono', monospace;
  font-size: 11px; color: var(--text-muted);
}
.header-sep { color: var(--cyan); }

.header-right { display: flex; align-items: center; gap: 12px; }
.user-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--cyan); box-shadow: 0 0 7px var(--cyan); }
.user-name { font-size: 13px; color: var(--text-dim); font-weight: 400; }

/* ══════════════════════════════════════════════
   WPM BADGES
══════════════════════════════════════════════ */
.wpm-badge {
  display: inline-flex; align-items: center;
  padding: 3px 10px; border-radius: 3px;
  font-family: 'Orbitron', monospace;
  font-size: 10px; font-weight: 700; letter-spacing: 1px;
}
.wpm-60  { background: rgba(34,197,94,0.12);   color: var(--green);  border: 1px solid rgba(34,197,94,0.3); }
.wpm-100 { background: rgba(45,212,191,0.10);  color: var(--cyan);   border: 1px solid rgba(45,212,191,0.3); }
.wpm-140 { background: rgba(139,92,246,0.10);  color: var(--purple); border: 1px solid rgba(139,92,246,0.3); }
.wpm-180 { background: rgba(234,179,8,0.10);   color: var(--amber);  border: 1px solid rgba(234,179,8,0.3); }
.wpm-220 { background: rgba(239,68,68,0.12);   color: var(--red);    border: 1px solid rgba(239,68,68,0.3); }

/* ══════════════════════════════════════════════
   BACK LINK
══════════════════════════════════════════════ */
.back-link {
  display: inline-flex; align-items: center; gap: 8px;
  color: var(--text-dim); font-size: 12px; letter-spacing: .06em;
  text-decoration: none; padding: 8px 16px;
  border: 1px solid var(--border-dim); border-radius: 8px;
  background: transparent; cursor: pointer;
  transition: all .2s; font-family: 'DM Sans', sans-serif;
}
.back-link:hover {
  color: var(--cyan); border-color: var(--border-cyan);
  background: var(--cyan-faint);
}

/* ══════════════════════════════════════════════
   PHASE TRANSITIONS
══════════════════════════════════════════════ */
.phase { display: none; animation: phaseIn .35s ease; }
.phase.active { display: block; }
@keyframes phaseIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

/* ══════════════════════════════════════════════
   STORY SELECT PHASE
══════════════════════════════════════════════ */
.select-hero { text-align: center; padding: 52px 0 40px; }
.select-eyebrow {
  font-size: 11px; letter-spacing: .22em; text-transform: uppercase;
  color: var(--cyan); font-family: 'Orbitron', monospace;
  margin-bottom: 14px; display: flex; align-items: center; justify-content: center; gap: 10px;
}
.select-eyebrow::before, .select-eyebrow::after {
  content: ''; width: 28px; height: 1px; background: var(--cyan); opacity: .5;
}
.select-title {
  font-size: clamp(32px, 6vw, 56px);
  letter-spacing: .06em;
  margin-bottom: 10px;
  line-height: 1.05;
}
.select-desc {
  font-size: 14px; color: var(--text-dim); font-weight: 300;
  letter-spacing: .04em; margin-bottom: 0;
}
.section-lbl {
  font-size: 10px; letter-spacing: .18em; text-transform: uppercase;
  color: var(--text-muted); font-family: 'Orbitron', monospace;
  margin-bottom: 16px; margin-top: 8px;
}

/* Story cards */
.stories-grid-wrap { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }

.story-card {
  background: var(--bg-card);
  border: 1px solid var(--border-dim);
  border-radius: 16px; padding: 22px;
  cursor: pointer; transition: all .22s ease;
  position: relative; overflow: hidden;
}
.story-card::before {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(45,212,191,.06) 0%, transparent 60%);
  opacity: 0; transition: opacity .22s;
}
.story-card::after {
  content: '';
  position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
  background: var(--cyan); opacity: 0; transition: opacity .22s;
  border-radius: 16px 0 0 16px;
}
.story-card:hover { border-color: var(--border-cyan); transform: translateY(-3px); box-shadow: 0 8px 32px rgba(45,212,191,.1); }
.story-card:hover::before, .story-card:hover::after { opacity: 1; }

.card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 10px; gap: 10px; }
.card-title { font-family: 'Orbitron', monospace; font-size: 13px; font-weight: 700; color: var(--cyan); line-height: 1.3; }
.card-cat   { font-family: 'Share Tech Mono', monospace; font-size: 10px; color: var(--text-muted); margin-top: 3px; }
.card-desc  { font-size: 12px; color: var(--text-dim); font-weight: 300; line-height: 1.55; margin-bottom: 14px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.card-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--border-dim); }
.card-parts { font-family: 'Share Tech Mono', monospace; font-size: 11px; color: var(--text-muted); }
.card-parts span { color: var(--cyan); }
.card-stats { display: flex; gap: 12px; }
.card-stat  { font-family: 'Share Tech Mono', monospace; font-size: 10px; color: var(--text-muted); }
.card-stat b { color: var(--amber); }

/* Loading / empty states */
.load-state { text-align: center; padding: 64px 20px; }
.load-text   { font-family: 'Share Tech Mono', monospace; font-size: 12px; color: var(--cyan-dim); letter-spacing: .1em; }
.empty-ico   { font-size: 36px; margin-bottom: 14px; }

/* Skeleton */
.skeleton {
  background: linear-gradient(90deg, var(--bg-card) 25%, var(--bg-raised) 50%, var(--bg-card) 75%);
  background-size: 200% 100%; animation: shimmer 1.4s infinite; border-radius: 16px;
}
@keyframes shimmer { from{background-position:200% 0} to{background-position:-200% 0} }

/* ══════════════════════════════════════════════
   PARTS SELECT PHASE
══════════════════════════════════════════════ */
.parts-story-title {
  font-family: 'Orbitron', monospace; font-size: 18px; font-weight: 700;
  color: var(--cyan); margin-bottom: 4px;
}
.parts-sub {
  font-family: 'Share Tech Mono', monospace; font-size: 10px;
  color: var(--text-muted); letter-spacing: .18em; text-transform: uppercase; margin-bottom: 28px;
}
.parts-grid { display: flex; flex-direction: column; gap: 10px; }

.part-row {
  background: var(--bg-card);
  border: 1px solid var(--border-dim); border-radius: 14px;
  padding: 18px 22px; cursor: pointer; transition: all .2s;
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
}
.part-row:hover { border-color: var(--border-cyan); background: var(--bg-raised); transform: translateX(4px); }
.part-num {
  font-family: 'Orbitron', monospace; font-size: 22px; font-weight: 900;
  color: rgba(45,212,191,0.18); min-width: 48px;
}
.part-info { flex: 1; }
.part-label    { font-family: 'Orbitron', monospace; font-size: 12px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
.part-meta     { font-family: 'Share Tech Mono', monospace; font-size: 10px; color: var(--text-muted); }
.part-right    { display: flex; align-items: center; gap: 16px; }
.part-stat     { text-align: right; }
.part-stat-lbl { font-family: 'Orbitron', monospace; font-size: 9px; color: var(--text-muted); letter-spacing: .1em; margin-bottom: 2px; }
.part-stat-val { font-family: 'Orbitron', monospace; font-size: 12px; font-weight: 700; }

/* Part pills (legacy — kept for JS compatibility) */
.part-pill {
  padding: 6px 14px;
  border: 1px solid rgba(45,212,191,0.2); border-radius: 4px;
  font-family: 'Orbitron', monospace; font-size: 10px; letter-spacing: 1.5px;
  color: var(--text-muted); background: transparent; cursor: pointer; transition: all .2s;
}
.part-pill:hover, .part-pill.active {
  border-color: var(--cyan); color: var(--cyan);
  background: var(--cyan-faint); box-shadow: 0 0 10px var(--cyan-glow);
}

/* ══════════════════════════════════════════════
   GAME PHASE
══════════════════════════════════════════════ */
.game-topbar {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 28px; gap: 16px;
}
.game-stats { display: flex; align-items: center; gap: 20px; }
.stat-block { text-align: center; }
.stat-lbl { font-family: 'Orbitron', monospace; font-size: 9px; color: var(--text-muted); letter-spacing: .12em; margin-bottom: 4px; }
.stat-val { font-family: 'Orbitron', monospace; font-size: 13px; font-weight: 700; }

/* Streak dots */
.streak-dot {
  width: 10px; height: 10px; border-radius: 50%;
  border: 1px solid var(--text-muted); background: transparent; transition: all .3s;
}
.streak-dot.active { background: var(--cyan); border-color: var(--cyan); box-shadow: 0 0 7px var(--cyan); }

/* ── Countdown ── */
.countdown-wrap { text-align: center; padding: 64px 0; }
.countdown-lbl  { font-family: 'Orbitron', monospace; font-size: 10px; color: var(--text-muted); letter-spacing: .3em; margin-bottom: 20px; }
.countdown-num  {
  font-family: 'Orbitron', monospace; font-weight: 900;
  font-size: clamp(72px, 12vw, 100px); color: var(--cyan); line-height: 1;
  text-shadow: 0 0 30px rgba(45,212,191,.5);
  animation: countdown-pulse 0.7s ease;
}
@keyframes countdown-pulse { from{transform:scale(1.2);opacity:.5} to{transform:scale(1);opacity:1} }
.countdown-wpm  { margin-top: 16px; font-family: 'Share Tech Mono', monospace; font-size: 13px; color: var(--text-dim); }

/* ── Reading panel ── */
.reading-header {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
}
.progress-track {
  background: rgba(45,212,191,.08); border-radius: 2px; overflow: hidden; height: 2px;
  margin-bottom: 20px;
}
.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--cyan-dim), var(--cyan));
  border-radius: 2px; transition: width .1s linear;
  box-shadow: 0 0 8px var(--cyan);
}

.reading-arena {
  background: var(--bg-surface);
  border: 1px solid var(--border-dim); border-radius: 20px;
  padding: 48px 32px; min-height: 160px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  text-align: center; position: relative; overflow: hidden;
}
.reading-arena::before {
  content: 'READING';
  position: absolute; top: 14px; right: 20px;
  font-family: 'Orbitron', monospace; font-size: 8px; letter-spacing: .25em;
  color: rgba(45,212,191,.15);
}
/* Subtle scan line on reading panel */
.reading-arena::after {
  content: '';
  position: absolute; inset: 0;
  background: repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(0,0,0,.04) 3px, rgba(0,0,0,.04) 4px);
  pointer-events: none; border-radius: 20px;
}

#rsvp-word {
  font-family: 'Orbitron', monospace;
  font-size: clamp(30px, 5.5vw, 56px);
  font-weight: 900; color: var(--cyan);
  text-shadow: 0 0 24px rgba(45,212,191,.5);
  letter-spacing: .06em;
  transition: opacity .08s ease; min-height: 68px;
  display: flex; align-items: center;
}
#rsvp-word.flash-out { opacity: 0; }

#rsvp-chunk {
  font-family: 'DM Sans', sans-serif; font-size: 13px;
  color: var(--text-muted); margin-top: 14px;
  letter-spacing: .03em; min-height: 22px;
}

/* Timer ring */
.timer-ring-svg { transform: rotate(-90deg); }
.timer-ring-bg   { stroke: rgba(45,212,191,.1); }
.timer-ring-fill {
  stroke: var(--cyan); stroke-linecap: round;
  filter: drop-shadow(0 0 5px var(--cyan));
  transition: stroke-dashoffset .1s linear, stroke .3s;
}

/* ── Question panel ── */
.question-header {
  text-align: center; margin-bottom: 24px;
}
.question-eyebrow {
  font-family: 'Orbitron', monospace; font-size: 9px;
  letter-spacing: .3em; color: var(--text-muted); margin-bottom: 14px;
}
.ans-timer-row {
  display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 14px;
}
.ans-timer-lbl { font-family: 'Share Tech Mono', monospace; font-size: 12px; color: var(--text-muted); }
#ans-timer {
  font-family: 'Orbitron', monospace; font-size: 22px; font-weight: 700; color: var(--amber);
}
.ans-progress-wrap {
  max-width: 280px; margin: 0 auto 24px;
  height: 3px; background: rgba(234,179,8,.1); border-radius: 2px; overflow: hidden;
}
#ans-progress {
  height: 100%;
  background: linear-gradient(90deg, var(--amber), #f2c03d);
  box-shadow: 0 0 8px var(--amber);
  transition: width 1s linear;
}

.question-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-dim); border-radius: 18px;
  padding: 28px; margin-bottom: 16px;
}
#question-text {
  font-size: 16px; font-weight: 400; line-height: 1.7;
  color: var(--text); margin-bottom: 20px;
}
.options-col { display: flex; flex-direction: column; gap: 10px; }

.btn-option {
  width: 100%; font-family: 'DM Sans', sans-serif;
  font-size: 15px; font-weight: 400; padding: 15px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border-dim); color: var(--text);
  border-radius: 12px; cursor: pointer; text-align: left;
  transition: all .18s; letter-spacing: .01em; display: flex; align-items: center; gap: 12px;
}
.btn-option::before {
  content: '▹';
  font-size: 12px; color: var(--cyan-dim); flex-shrink: 0; transition: color .18s;
}
.btn-option:hover:not(:disabled) {
  border-color: var(--border-cyan);
  background: var(--cyan-faint); color: var(--cyan);
  transform: translateX(4px);
}
.btn-option:hover:not(:disabled)::before { color: var(--cyan); }
.btn-option.correct {
  border-color: rgba(34,197,94,.5); background: rgba(34,197,94,.08);
  color: var(--green); box-shadow: 0 0 14px rgba(34,197,94,.18);
}
.btn-option.wrong {
  border-color: rgba(239,68,68,.4); background: rgba(239,68,68,.08);
  color: var(--red); box-shadow: 0 0 14px rgba(239,68,68,.15);
}

/* Result area */
.result-area { text-align: center; padding: 24px 0 8px; }
.result-badge {
  font-family: 'Orbitron', monospace; font-size: 12px;
  letter-spacing: .25em; text-transform: uppercase;
  padding: 8px 22px; border-radius: 6px; display: inline-block; margin-bottom: 12px;
}
.result-correct {
  background: rgba(34,197,94,.1); color: var(--green);
  border: 1px solid rgba(34,197,94,.4);
  text-shadow: 0 0 8px var(--green); box-shadow: 0 0 18px rgba(34,197,94,.18);
}
.result-wrong {
  background: rgba(239,68,68,.1); color: var(--red);
  border: 1px solid rgba(239,68,68,.35);
  text-shadow: 0 0 8px var(--red); box-shadow: 0 0 18px rgba(239,68,68,.15);
}
#result-msg   { font-size: 14px; color: var(--text-dim); margin-bottom: 6px; }
#level-change-msg { font-family: 'Orbitron', monospace; font-size: 10px; letter-spacing: .18em; }

.result-actions { margin-top: 22px; display: flex; justify-content: center; gap: 12px; }

/* ── Flash correct/wrong panel animations ── */
@keyframes correctFlash {
  0% { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
  70% { box-shadow: 0 0 0 20px rgba(34,197,94,0); }
  100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
}
.flash-correct { animation: correctFlash .6s ease; }

@keyframes wrongShake {
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-8px)}
  40%{transform:translateX(8px)}
  60%{transform:translateX(-5px)}
  80%{transform:translateX(5px)}
}
.flash-wrong { animation: wrongShake .4s ease; }

/* ══════════════════════════════════════════════
   BUTTONS
══════════════════════════════════════════════ */
.btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  font-family: 'Orbitron', monospace; font-size: 10px; font-weight: 700;
  letter-spacing: .18em; text-transform: uppercase;
  padding: 12px 26px;
  background: transparent; border: 1px solid var(--cyan); color: var(--cyan);
  border-radius: 8px; cursor: pointer; transition: all .2s; position: relative; overflow: hidden;
}
.btn-primary::before {
  content: ''; position: absolute; inset: 0;
  background: var(--cyan); opacity: 0; transition: opacity .2s;
}
.btn-primary:hover::before { opacity: .1; }
.btn-primary:hover { box-shadow: 0 0 22px var(--cyan-glow); text-shadow: 0 0 8px var(--cyan); }
.btn-primary:disabled { opacity: .3; cursor: not-allowed; }

.btn-amber {
  border-color: var(--amber); color: var(--amber);
}
.btn-amber:hover { box-shadow: 0 0 22px rgba(234,179,8,.25); text-shadow: 0 0 8px var(--amber); }
.btn-amber::before { background: var(--amber); }

/* ══════════════════════════════════════════════
   LEADERBOARD
══════════════════════════════════════════════ */
.lb-header-title {
  font-family: 'Orbitron', monospace; font-size: 18px; font-weight: 700;
  color: var(--amber); margin-bottom: 4px;
}
.lb-subtitle {
  font-family: 'Share Tech Mono', monospace; font-size: 11px;
  color: var(--text-muted); letter-spacing: .14em; margin-bottom: 28px;
}
.lb-table {
  background: var(--bg-surface); border: 1px solid var(--border-dim);
  border-radius: 16px; overflow: hidden;
}
.lb-head {
  display: grid; grid-template-columns: 40px 1fr 90px 70px 64px;
  gap: 12px; padding: 12px 18px;
  border-bottom: 1px solid var(--border-dim);
  font-family: 'Orbitron', monospace; font-size: 9px;
  color: var(--text-muted); letter-spacing: .14em;
}
.lb-row {
  display: grid; grid-template-columns: 40px 1fr 90px 70px 64px;
  gap: 12px; align-items: center; padding: 12px 18px;
  border-bottom: 1px solid rgba(255,255,255,.04); font-size: 13px; transition: background .15s;
}
.lb-row:last-child { border-bottom: none; }
.lb-row:hover { background: var(--bg-raised); }
.lb-rank { font-family: 'Orbitron', monospace; font-size: 11px; color: var(--text-muted); text-align: center; }
.lb-rank.top { color: var(--amber); font-size: 14px; }

/* ══════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════ */
#level-toast {
  position: fixed; top: 22px; left: 50%;
  transform: translateX(-50%) translateY(-100px);
  z-index: 200; font-family: 'Orbitron', monospace; font-size: 11px; letter-spacing: .18em;
  padding: 10px 26px; border-radius: 8px; pointer-events: none;
  transition: transform .4s cubic-bezier(.34,1.56,.64,1);
}
#level-toast.show { transform: translateX(-50%) translateY(0); }
#level-toast.up   { background: rgba(34,197,94,.15); color: var(--green); border: 1px solid rgba(34,197,94,.4); }
#level-toast.down { background: rgba(239,68,68,.15); color: var(--red);   border: 1px solid rgba(239,68,68,.4); }

/* border-cyan-glow utility — used on reading panel and question card */
.border-cyan-glow {
  border: 1px solid var(--border-cyan) !important;
  box-shadow: 0 0 18px var(--cyan-glow), inset 0 0 18px rgba(45,212,191,.03);
}

/* ══════════════════════════════════════════════
   SCROLLBAR
══════════════════════════════════════════════ */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: var(--bg-void); }
::-webkit-scrollbar-thumb { background: var(--cyan-dim); border-radius: 2px; }

/* ══════════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════════ */
@media(max-width:600px){
  .site-header { padding: 14px 18px; }
  .header-center { display: none; }
  .reading-arena { padding: 32px 18px; }
  .question-card { padding: 20px; }
  .lb-head, .lb-row { grid-template-columns: 32px 1fr 72px 54px; }
  .lb-head > *:last-child, .lb-row > *:last-child { display: none; }
}
</style>
</head>
<body>

<div class="amb amb-1"></div>
<div class="amb amb-2"></div>
<div class="amb amb-3"></div>

<!-- ══ HEADER ══ -->
<header class="site-header z1">
  <a href="index.php" class="logo">
    <div class="logo-icon">⚡</div>
    <div>
      <div class="logo-name">StoryVerse</div>
      <div class="logo-sub">Game Arena</div>
    </div>
  </a>

  <div class="header-center">
    ARENA <span class="header-sep">//</span> FLASH WORDS
    <div class="wpm-badge wpm-60" id="header-wpm-badge">60 WPM</div>
  </div>

  <div class="header-right">
    <div class="user-dot"></div>
    <span class="user-name"><?= $first_name ?></span>
  </div>
</header>

<!-- ══ MAIN ══ -->
<div class="z1" style="max-width:900px;margin:0 auto;padding:0 24px 80px;width:100%;">

  <!-- ════════════════════════════
       PHASE: Story Select
  ════════════════════════════ -->
  <div id="phase-select" class="phase active">

    <div class="select-hero">
      <div class="select-eyebrow">Speed Reading Arena</div>
      <h1 class="select-title glitch-title">FLASH WORDS</h1>
      <p class="select-desc">Read at speed. Prove your comprehension. Climb the tiers.</p>
    </div>

    <div class="section-lbl" style="margin-top:36px;">Choose a story</div>

    <!-- Loading -->
    <div id="stories-loading" class="load-state">
      <div class="load-text"><span id="load-dots">LOADING STORIES</span></div>
    </div>

    <!-- Stories grid -->
    <div id="stories-grid" class="stories-grid-wrap" style="display:none;"></div>

    <!-- Empty state -->
    <div id="stories-empty" class="load-state" style="display:none;">
      <div class="empty-ico">📭</div>
      <div class="load-text" style="color:var(--text-muted);">No arena content yet.<br>Authors haven't added Flash Words questions.</div>
    </div>

  </div>

  <!-- ════════════════════════════
       PHASE: Part Select
  ════════════════════════════ -->
  <div id="phase-parts" class="phase">

    <div style="display:flex;align-items:center;justify-content:space-between;margin:32px 0 28px;flex-wrap:wrap;gap:12px;">
      <button class="back-link" onclick="showPhase('select')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Back to Stories
      </button>
    </div>

    <div id="part-story-title" class="parts-story-title"></div>
    <div class="parts-sub">Select Story Part</div>
    <div id="parts-grid" class="parts-grid"></div>

  </div>

  <!-- ════════════════════════════
       PHASE: Game
  ════════════════════════════ -->
  <div id="phase-game" class="phase">

    <div class="game-topbar" style="margin-top:24px;">
      <button class="back-link" onclick="backToPartsFromGame()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Exit
      </button>

      <div class="game-stats">
        <div class="stat-block">
          <div class="stat-lbl">Streak</div>
          <div id="streak-dots" style="display:flex;gap:6px;justify-content:center;margin-top:2px;"></div>
        </div>
        <div class="stat-block">
          <div class="stat-lbl">Accuracy</div>
          <div id="live-accuracy" class="stat-val" style="color:var(--cyan);">—</div>
        </div>
        <div class="stat-block">
          <div class="stat-lbl">Best WPM</div>
          <div id="live-best-wpm" class="stat-val" style="color:var(--amber);">60</div>
        </div>
      </div>
    </div>

    <!-- Sub-phase: Countdown -->
    <div id="sub-countdown" class="countdown-wrap">
      <div class="countdown-lbl">Prepare to Read</div>
      <div id="countdown-num" class="countdown-num">3</div>
      <div class="countdown-wpm">
        Target speed: <span id="countdown-wpm" style="color:var(--cyan);font-weight:700;"></span>
      </div>
    </div>

    <!-- Sub-phase: Reading -->
    <div id="sub-reading" style="display:none;">
      <div class="reading-header">
        <div>
          <div class="stat-lbl">Current Speed</div>
          <div id="game-wpm-badge" class="wpm-badge wpm-60" style="margin-top:4px;">60 WPM</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
          <svg class="timer-ring-svg" width="58" height="58" viewBox="0 0 60 60">
            <circle class="timer-ring-bg" cx="30" cy="30" r="26" fill="none" stroke-width="3"/>
            <circle id="timer-ring" class="timer-ring-fill" cx="30" cy="30" r="26"
              fill="none" stroke-width="3" stroke-dasharray="163.4" stroke-dashoffset="0"/>
          </svg>
          <div id="timer-secs" class="font-mono-tech" style="font-size:11px;color:var(--cyan);">—</div>
        </div>
        <div style="text-align:right;">
          <div class="stat-lbl">Words</div>
          <div id="word-counter" class="font-orbitron" style="font-size:13px;font-weight:700;color:var(--text);margin-top:4px;">0 / 0</div>
        </div>
      </div>

      <div class="progress-track">
        <div class="progress-fill" id="reading-progress" style="width:0%"></div>
      </div>

      <div id="reading-panel" class="reading-arena border-cyan-glow">
        <div id="rsvp-word"></div>
        <div id="rsvp-chunk"></div>
      </div>
    </div>

    <!-- Sub-phase: Question -->
    <div id="sub-question" style="display:none;">
      <div class="question-header">
        <div class="question-eyebrow">Comprehension Check</div>
        <div class="ans-timer-row">
          <span class="ans-timer-lbl">Answer in:</span>
          <span id="ans-timer">10</span>
        </div>
        <div class="ans-progress-wrap">
          <div id="ans-progress" style="width:100%;height:100%;background:linear-gradient(90deg,var(--amber),#f2c03d);box-shadow:0 0 8px var(--amber);transition:width 1s linear;border-radius:2px;"></div>
        </div>
      </div>

      <div class="question-card border-cyan-glow">
        <p id="question-text"></p>
        <div class="options-col">
          <button class="btn-option" id="opt-a" onclick="submitAnswer(this)"></button>
          <button class="btn-option" id="opt-b" onclick="submitAnswer(this)"></button>
        </div>
      </div>

      <!-- Result area -->
      <div id="result-area" style="display:none;" class="result-area">
        <div id="result-badge" class="result-badge"></div>
        <div id="result-msg"></div>
        <div id="level-change-msg" class="font-orbitron" style="font-size:10px;letter-spacing:.18em;margin-top:4px;"></div>
        <div class="result-actions">
          <button class="btn-primary" onclick="startRound()">Play Again</button>
          <button class="btn-primary btn-amber" onclick="showLeaderboard()">Leaderboard</button>
        </div>
      </div>
    </div>

  </div><!-- /phase-game -->

  <!-- ════════════════════════════
       PHASE: Leaderboard
  ════════════════════════════ -->
  <div id="phase-leaderboard" class="phase">
    <div style="display:flex;align-items:center;justify-content:space-between;margin:32px 0 24px;flex-wrap:wrap;gap:12px;">
      <button class="back-link" onclick="showPhase('game')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Back to Game
      </button>
    </div>

    <div class="lb-header-title">⚡ Leaderboard</div>
    <div id="lb-subtitle" class="lb-subtitle"></div>

    <div class="lb-table">
      <div class="lb-head">
        <div>#</div><div>Player</div><div>Best WPM</div><div>Correct</div><div>Acc%</div>
      </div>
      <div id="lb-body"></div>
    </div>

    <div style="display:flex;justify-content:center;margin-top:28px;">
      <button class="btn-primary" onclick="startRound()">Play Again</button>
    </div>
  </div>

</div><!-- /main -->

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
    sep.style.cssText = 'width:1px;height:10px;background:var(--text-muted);margin:0 4px;';
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
        <div class="card-top">
          <div>
            <div class="card-title">${s.title}</div>
            <div class="card-cat">${s.category || 'General'}</div>
          </div>
          <div class="wpm-badge ${wpmClass(wpm)}">${wpm} WPM</div>
        </div>
        <p class="card-desc">${s.description ? s.description.substring(0, 100) + '…' : ''}</p>
        <div class="card-footer">
          <div class="card-parts"><span>${s.parts_with_questions}</span> part${s.parts_with_questions != 1 ? 's' : ''}</div>
          <div class="card-stats">
            ${best > 60 ? `<span class="card-stat">BEST <b>${best} WPM</b></span>` : ''}
            ${accuracy !== null ? `<span class="card-stat">${accuracy}% acc</span>` : ''}
          </div>
        </div>`;
      card.addEventListener('click', () => loadParts(s));
      grid.appendChild(card);
    });
  } catch(e) {
    $('stories-loading').innerHTML = `<div class="load-text" style="color:var(--red);white-space:pre-wrap;word-break:break-all;">ERROR: ${e.message}</div>`;
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
  grid.innerHTML = `<div class="load-state"><div class="load-text">LOADING PARTS...</div></div>`;

  try {
    const data = await api({action: 'get_parts', story_id: story.story_id});
    grid.innerHTML = '';

    if (!data.success || !data.parts.length) {
      grid.innerHTML = `<div class="load-state"><div class="load-text" style="color:var(--text-muted);">No parts found.</div></div>`;
      return;
    }

    data.parts.forEach(part => {
      const wpm  = part.current_wpm || 60;
      const best = part.best_wpm || 60;
      const accuracy = part.total_attempts > 0
        ? Math.round((part.total_correct / part.total_attempts) * 100) : null;
      const attempts = part.total_attempts || 0;

      const row = document.createElement('div');
      row.className = 'part-row';
      row.innerHTML = `
        <div class="part-num">${part.part_number === 0 ? 'GK' : String(part.part_number).padStart(2, '0')}</div>
        <div class="part-info">
          <div class="part-label">${part.label || ('Part ' + part.part_number)}</div>
          <div class="part-meta">${part.question_count} question${part.question_count != 1 ? 's' : ''}${attempts > 0 ? ` · ${attempts} attempt${attempts != 1 ? 's' : ''}` : ''}</div>
        </div>
        <div class="part-right">
          ${accuracy !== null ? `<div class="part-stat"><div class="part-stat-lbl">Acc</div><div class="part-stat-val" style="color:var(--cyan);">${accuracy}%</div></div>` : ''}
          ${best > 60 ? `<div class="part-stat"><div class="part-stat-lbl">Best</div><div class="part-stat-val" style="color:var(--amber);">${best}</div></div>` : ''}
          <div class="wpm-badge ${wpmClass(wpm)}">${wpm} WPM</div>
        </div>`;
      row.addEventListener('click', () => startGame(part));
      grid.appendChild(row);
    });
  } catch(e) {
    grid.innerHTML = `<div class="load-state"><div class="load-text" style="color:var(--red);">Error loading parts.</div></div>`;
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
  const panel   = document.querySelector('#sub-question .question-card');
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
  $('lb-body').innerHTML = `<div class="load-state"><div class="load-text">LOADING...</div></div>`;

  try {
    const data = await api({
      action: 'get_leaderboard',
      story_id: state.selectedStory.story_id,
      part_number: state.selectedPart.part_number
    });

    if (!data.success || !data.leaderboard.length) {
      $('lb-body').innerHTML = `<div class="load-state"><div class="load-text" style="color:var(--text-muted);">No data yet. Be the first!</div></div>`;
      return;
    }

    const rankColors = ['var(--amber)', '#a0c4ff', '#c9a87c'];
    $('lb-body').innerHTML = data.leaderboard.map((row, i) => `
      <div class="lb-row">
        <div class="lb-rank ${i < 3 ? 'top' : ''}">${i < 3 ? ['①','②','③'][i] : (i+1)}</div>
        <div style="font-size:14px;color:${i === 0 ? 'var(--amber)' : 'var(--text)'};">
          ${row.first_name || row.user_name}
          <span class="font-mono-tech" style="font-size:11px;color:var(--text-muted);margin-left:6px;">@${row.user_name}</span>
        </div>
        <div class="wpm-badge ${wpmClass(row.best_wpm)}">${row.best_wpm} WPM</div>
        <div class="font-mono-tech" style="font-size:12px;text-align:center;color:var(--text-dim);">${row.total_correct}</div>
        <div class="font-orbitron" style="font-size:12px;text-align:center;color:var(--cyan);">${row.accuracy || 0}%</div>
      </div>
    `).join('');
  } catch(e) {
    $('lb-body').innerHTML = `<div class="load-state"><div class="load-text" style="color:var(--red);">Error loading leaderboard.</div></div>`;
  }
}

// ═══════════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════════
loadStories();
</script>
</body>
</html>
