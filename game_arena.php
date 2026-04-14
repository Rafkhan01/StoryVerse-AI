<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Game Arena — StoryVerse</title>

<link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Rajdhani:wght@300;400;500;600;700&family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet"/>

<style>
/* ============================================================
   TOKENS — shared with index.php & stories.php
============================================================ */
:root {
  --void:      #060810;
  --deep:      #0d0f1f;
  --ink:       #0a0b14;
  --ember:     #c8860a;
  --gold:      #e8b84b;
  --gold-lt:   #f5d07a;
  --gold-dim:  rgba(232,184,75,0.12);
  --azure:     #4a9eff;
  --azure-lt:  #7dbfff;
  --mist:      #b8c8e8;
  --ivory:     #f0ead8;
  --glass:     rgba(255,255,255,0.035);
  --glass-md:  rgba(255,255,255,0.06);
  --border:    rgba(232,184,75,0.16);
  --border-az: rgba(74,158,255,0.2);
  --shadow-deep: 0 32px 80px rgba(0,0,0,0.7);

  --font-display: 'Cinzel Decorative', serif;
  --font-body:    'Cormorant Garamond', serif;
  --font-ui:      'Rajdhani', sans-serif;
  --font-mono:    'Space Mono', monospace;
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; overflow-x:hidden; }

body {
  background: var(--void);
  color: var(--ivory);
  font-family: var(--font-body);
  line-height: 1.7;
  overflow-x: hidden;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

::selection { background:rgba(232,184,75,0.28); color:var(--gold-lt); }
::-webkit-scrollbar { width:3px; }
::-webkit-scrollbar-track { background:var(--void); }
::-webkit-scrollbar-thumb { background:var(--ember); border-radius:2px; }

/* ============================================================
   NAVBAR — identical to index.php
============================================================ */
#navbar {
  position:fixed; top:0; left:0; right:0; z-index:1000;
  padding:0 5%; height:70px;
  display:flex; align-items:center; justify-content:space-between;
  background:rgba(6,8,16,0.94);
  backdrop-filter:blur(20px);
  box-shadow:0 1px 0 var(--border), 0 8px 32px rgba(0,0,0,0.6);
}

.nav-logo {
  font-family:var(--font-display);
  font-size:1.1rem; font-weight:700; letter-spacing:.05em;
  background:linear-gradient(135deg,var(--gold),var(--azure-lt));
  -webkit-background-clip:text; -webkit-text-fill-color:transparent;
  background-clip:text; text-decoration:none; white-space:nowrap;
}

.nav-links { display:flex; align-items:center; gap:.2rem; list-style:none; }

.nav-links a {
  font-family:var(--font-ui); font-size:.83rem; font-weight:500;
  letter-spacing:.12em; text-transform:uppercase;
  color:var(--mist); text-decoration:none;
  padding:.4rem .85rem; border-radius:4px;
  transition:color .2s; position:relative;
}
.nav-links a::after {
  content:''; position:absolute; bottom:-2px; left:50%;
  width:0; height:1px; background:var(--gold);
  transform:translateX(-50%); transition:width .3s;
}
.nav-links a:hover, .nav-links a.active-nav { color:var(--gold-lt); }
.nav-links a:hover::after, .nav-links a.active-nav::after { width:60%; }

.nav-btn {
  font-family:var(--font-ui)!important; font-size:.78rem!important;
  font-weight:600!important; letter-spacing:.1em!important;
  text-transform:uppercase!important; padding:.42rem 1.1rem!important;
  border-radius:30px!important; border:1px solid var(--border)!important;
  background:transparent!important; color:var(--ivory)!important;
  cursor:pointer; transition:all .3s!important; text-decoration:none;
}
.nav-btn:hover { background:rgba(232,184,75,.12)!important; border-color:var(--gold)!important; color:var(--gold-lt)!important; }
.nav-btn-primary {
  background:linear-gradient(135deg,var(--ember),var(--gold))!important;
  border-color:transparent!important; color:var(--void)!important;
}
.nav-btn-primary:hover {
  background:linear-gradient(135deg,var(--gold),var(--gold-lt))!important;
  color:var(--void)!important; transform:translateY(-1px);
  box-shadow:0 4px 20px rgba(232,184,75,.35)!important;
}

.nav-toggle { display:none; flex-direction:column; gap:5px; cursor:pointer; padding:4px; }
.nav-toggle span { display:block; width:22px; height:2px; background:var(--ivory); transition:all .3s; border-radius:2px; }

/* ============================================================
   ARENA HERO
============================================================ */
.arena-hero {
  position:relative; min-height:100vh;
  display:flex; align-items:center; justify-content:center;
  overflow:hidden;
}

/* Animated grid background */
.arena-grid-bg {
  position:absolute; inset:0;
  background-image:
    linear-gradient(rgba(74,158,255,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(74,158,255,0.04) 1px, transparent 1px);
  background-size:60px 60px;
  animation:gridShift 20s linear infinite;
}
@keyframes gridShift {
  from { transform:translateY(0); }
  to   { transform:translateY(60px); }
}

/* Radial spotlight */
.arena-hero::before {
  content:'';
  position:absolute; inset:0;
  background:
    radial-gradient(ellipse 70% 60% at 50% 60%, rgba(200,134,10,0.1) 0%, transparent 70%),
    radial-gradient(ellipse 50% 40% at 20% 20%, rgba(74,158,255,0.07) 0%, transparent 60%),
    linear-gradient(to bottom, rgba(6,8,16,0.4) 0%, rgba(6,8,16,0.0) 40%, rgba(6,8,16,0.9) 100%);
  pointer-events:none; z-index:1;
}

/* Floating orbs */
.orb {
  position:absolute;
  border-radius:50%;
  filter:blur(80px);
  pointer-events:none;
  z-index:0;
  animation:orbDrift ease-in-out infinite alternate;
}
.orb-1 { width:500px; height:500px; top:-100px; left:-150px; background:rgba(74,158,255,0.06); animation-duration:12s; }
.orb-2 { width:400px; height:400px; bottom:-80px; right:-100px; background:rgba(200,134,10,0.08); animation-duration:9s; }
.orb-3 { width:300px; height:300px; top:40%; left:60%; background:rgba(232,184,75,0.05); animation-duration:14s; }

@keyframes orbDrift {
  from { transform:translate(0,0) scale(1); }
  to   { transform:translate(30px,20px) scale(1.08); }
}

.arena-hero-content {
  position:relative; z-index:2;
  text-align:center; padding:0 5%;
  max-width:860px;
}

.hero-eyebrow {
  font-family:var(--font-mono);
  font-size:.65rem; letter-spacing:.42em; text-transform:uppercase;
  color:var(--azure); display:block; margin-bottom:1.5rem;
  opacity:0; animation:fadeSlideDown .8s .3s forwards;
}

.arena-title {
  font-family:var(--font-display);
  font-size:clamp(2.8rem, 7vw, 6rem);
  font-weight:900; line-height:1.05;
  margin-bottom:1.5rem;
  opacity:0; animation:fadeSlideUp 1s .5s forwards;
}
.arena-title .word-1 {
  display:block;
  background:linear-gradient(135deg, var(--ivory) 30%, var(--mist));
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.arena-title .word-2 {
  display:block;
  background:linear-gradient(135deg, var(--ember) 0%, var(--gold) 50%, var(--gold-lt) 100%);
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
  font-style:italic;
}

.arena-sub {
  font-family:var(--font-body);
  font-size:clamp(1rem,2.2vw,1.3rem);
  font-weight:300; font-style:italic;
  color:rgba(176,196,222,0.7);
  max-width:560px; margin:0 auto 3rem;
  opacity:0; animation:fadeSlideUp 1s .75s forwards;
}

.hero-cta-row {
  display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;
  opacity:0; animation:fadeSlideUp 1s 1s forwards;
}

.btn {
  display:inline-flex; align-items:center; gap:.5rem;
  font-family:var(--font-ui); font-size:.85rem; font-weight:600;
  letter-spacing:.14em; text-transform:uppercase; text-decoration:none;
  padding:.85rem 2.2rem; border-radius:50px; cursor:pointer;
  border:none; transition:all .35s;
}
.btn-primary {
  background:linear-gradient(135deg,var(--ember),var(--gold));
  color:var(--void); box-shadow:0 4px 30px rgba(200,134,10,0.4);
}
.btn-primary:hover { transform:translateY(-3px); box-shadow:0 8px 40px rgba(232,184,75,0.5); }
.btn-ghost {
  background:transparent; color:var(--ivory);
  border:1px solid rgba(255,255,255,0.2); backdrop-filter:blur(10px);
}
.btn-ghost:hover { background:rgba(255,255,255,0.06); border-color:var(--azure-lt); color:var(--azure-lt); transform:translateY(-3px); }

/* Scroll indicator */
.scroll-cue {
  position:absolute; bottom:2.5rem; left:50%; transform:translateX(-50%);
  z-index:2; display:flex; flex-direction:column; align-items:center; gap:.5rem;
  opacity:0; animation:fadeIn 1s 1.8s forwards;
}
.scroll-cue-line {
  width:1px; height:48px;
  background:linear-gradient(to bottom,var(--gold),transparent);
  animation:linePulse 2s ease-in-out infinite;
}
.scroll-cue-text {
  font-family:var(--font-mono); font-size:.56rem;
  letter-spacing:.35em; text-transform:uppercase;
  color:rgba(255,255,255,0.3);
}
@keyframes linePulse { 0%,100%{opacity:.3} 50%{opacity:1} }

/* Live stat strip */
.stat-strip {
  position:absolute; bottom:0; left:0; right:0; z-index:3;
  display:flex; justify-content:center; gap:0;
  border-top:1px solid var(--border);
  background:rgba(6,8,16,0.75); backdrop-filter:blur(12px);
}
.stat-item {
  padding:1rem 3rem; text-align:center;
  border-right:1px solid var(--border);
  transition:background .3s;
}
.stat-item:last-child { border-right:none; }
.stat-item:hover { background:rgba(232,184,75,0.04); }
.stat-num {
  font-family:var(--font-display); font-size:1.4rem; font-weight:700;
  background:linear-gradient(135deg,var(--gold),var(--azure-lt));
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
  display:block; line-height:1;
}
.stat-lbl {
  font-family:var(--font-mono); font-size:.58rem;
  letter-spacing:.22em; text-transform:uppercase;
  color:rgba(176,196,222,0.4); display:block; margin-top:.3rem;
}

/* ============================================================
   SECTION SHARED
============================================================ */
.section-wrap { padding:7rem 5%; max-width:1400px; margin:0 auto; width:100%; }

.section-header { margin-bottom:4rem; }
.section-header.centered { text-align:center; }

.section-eyebrow {
  font-family:var(--font-mono); font-size:.62rem;
  letter-spacing:.4em; text-transform:uppercase; color:var(--azure);
  display:block; margin-bottom:.7rem;
}
.section-title {
  font-family:var(--font-display);
  font-size:clamp(1.8rem,3.5vw,2.8rem); font-weight:700; line-height:1.2;
  background:linear-gradient(135deg,var(--ivory) 40%,var(--gold-lt));
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
  margin-bottom:.8rem;
}
.section-desc {
  font-family:var(--font-body); font-size:1rem; font-weight:300;
  color:rgba(176,196,222,0.65); line-height:1.8; max-width:540px;
}
.section-header.centered .section-desc { margin:0 auto; }

.s-divider {
  width:48px; height:2px;
  background:linear-gradient(to right,var(--gold),var(--azure));
  border-radius:2px; margin:1.2rem 0;
}
.s-divider.centered { margin:1.2rem auto; }

/* Separator between sections */
.section-sep {
  width:100%; height:1px;
  background:linear-gradient(to right, transparent, var(--border), var(--gold), var(--border), transparent);
  margin:0 5%;
  width:90%;
}

/* ============================================================
   AVAILABLE GAMES — 3 CARDS
============================================================ */
.games-available { background:linear-gradient(to bottom,var(--void),var(--deep)); }

.available-grid {
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:1.75rem;
}

/* Game card */
.game-card {
  position:relative; border-radius:20px; overflow:hidden;
  background:var(--glass); border:1px solid var(--border);
  transition:transform .4s, box-shadow .4s, border-color .4s;
  display:flex; flex-direction:column;
  cursor:pointer;
}
.game-card:hover {
  transform:translateY(-10px);
  box-shadow:0 30px 70px rgba(0,0,0,0.65), 0 0 0 1px rgba(232,184,75,0.2);
  border-color:rgba(232,184,75,0.32);
}

/* Card image area — SVG illustrated */
.game-card-art {
  position:relative; height:220px; overflow:hidden; flex-shrink:0;
}
.game-card-art canvas, .game-card-art .art-inner {
  width:100%; height:100%; display:block;
}
.game-card-art-overlay {
  position:absolute; inset:0;
  background:linear-gradient(to bottom, transparent 50%, rgba(6,8,16,0.9) 100%);
}

/* Card number badge */
.card-num {
  position:absolute; top:1rem; left:1rem;
  font-family:var(--font-mono); font-size:.6rem; font-weight:700;
  letter-spacing:.25em; text-transform:uppercase;
  padding:.25rem .7rem; border-radius:4px;
  background:rgba(6,8,16,0.7); border:1px solid var(--border);
  color:var(--gold); backdrop-filter:blur(8px);
}

/* Card play badge */
.card-play-badge {
  position:absolute; top:1rem; right:1rem;
  width:36px; height:36px; border-radius:50%;
  background:linear-gradient(135deg,var(--ember),var(--gold));
  display:flex; align-items:center; justify-content:center;
  opacity:0; transform:scale(0.7);
  transition:opacity .3s, transform .3s;
}
.card-play-badge svg { width:14px; height:14px; fill:var(--void); margin-left:2px; }
.game-card:hover .card-play-badge { opacity:1; transform:scale(1); }

/* Card body */
.game-card-body { padding:1.5rem 1.6rem 1.8rem; display:flex; flex-direction:column; flex:1; }

.game-tag {
  font-family:var(--font-mono); font-size:.58rem;
  letter-spacing:.22em; text-transform:uppercase;
  color:var(--azure); margin-bottom:.6rem; display:block;
}

.game-name {
  font-family:var(--font-display); font-size:1.1rem; font-weight:700;
  color:var(--ivory); margin-bottom:.75rem; line-height:1.3;
}

.game-desc {
  font-family:var(--font-body); font-size:.95rem; font-weight:300;
  color:rgba(176,196,222,0.62); line-height:1.7;
  margin-bottom:1.25rem; flex:1;
}

/* Skill pills */
.skill-pills {
  display:flex; flex-wrap:wrap; gap:.45rem; margin-bottom:1.4rem;
}
.skill-pill {
  font-family:var(--font-ui); font-size:.68rem; font-weight:500;
  letter-spacing:.1em; text-transform:uppercase;
  padding:.25rem .75rem; border-radius:50px;
  background:rgba(74,158,255,0.08); border:1px solid rgba(74,158,255,0.2);
  color:var(--azure-lt); transition:all .25s;
}
.game-card:hover .skill-pill {
  background:rgba(232,184,75,0.08); border-color:rgba(232,184,75,0.25); color:var(--gold-lt);
}

/* Card action row */
.game-card-action {
  display:flex; align-items:center; justify-content:space-between;
  padding-top:1rem; border-top:1px solid var(--border);
}
.game-difficulty {
  display:flex; align-items:center; gap:.5rem;
}
.diff-label { font-family:var(--font-ui); font-size:.68rem; font-weight:500; letter-spacing:.1em; text-transform:uppercase; color:rgba(176,196,222,0.4); }
.diff-dots { display:flex; gap:3px; }
.diff-dot { width:7px; height:7px; border-radius:50%; background:rgba(255,255,255,0.12); }
.diff-dot.lit { background:var(--gold); }

.game-play-btn {
  font-family:var(--font-ui); font-size:.72rem; font-weight:600;
  letter-spacing:.12em; text-transform:uppercase; text-decoration:none;
  padding:.42rem 1.2rem; border-radius:50px;
  background:linear-gradient(135deg,var(--ember),var(--gold));
  color:var(--void); border:none; cursor:pointer;
  transition:all .3s;
  box-shadow:0 3px 16px rgba(200,134,10,0.35);
}
.game-play-btn:hover { transform:translateY(-2px); box-shadow:0 6px 24px rgba(232,184,75,0.45); }

/* ============================================================
   UPCOMING GAMES — ACCORDION CARDS
============================================================ */
.games-upcoming { background:linear-gradient(to bottom,var(--deep),var(--void)); }

.upcoming-list {
  display:flex; flex-direction:column; gap:1.25rem;
}

/* Upcoming item — uses <details> */
.upcoming-item {
  border-radius:14px;
  border:1px solid var(--border);
  background:var(--glass);
  overflow:hidden;
  transition:border-color .35s, box-shadow .35s;
}

.upcoming-item[open] {
  border-color:rgba(232,184,75,0.28);
  box-shadow:0 12px 50px rgba(0,0,0,0.45), inset 0 1px 0 rgba(232,184,75,0.1);
}

/* Summary row */
.upcoming-summary {
  display:grid;
  grid-template-columns:80px 1fr auto;
  align-items:center;
  gap:1.75rem;
  padding:1.5rem 2rem;
  cursor:pointer;
  list-style:none;
  transition:background .25s;
  position:relative;
}
.upcoming-summary::-webkit-details-marker { display:none; }
.upcoming-summary::marker { display:none; }
.upcoming-summary:hover { background:rgba(232,184,75,0.04); }

/* Game index number */
.up-index {
  font-family:var(--font-display); font-size:2.2rem; font-weight:900;
  background:linear-gradient(135deg, rgba(232,184,75,0.25), rgba(74,158,255,0.2));
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
  text-align:center; line-height:1; flex-shrink:0;
}
.upcoming-item[open] .up-index {
  background:linear-gradient(135deg,var(--ember),var(--gold));
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}

.up-meta { min-width:0; }

.up-tag {
  font-family:var(--font-mono); font-size:.58rem;
  letter-spacing:.25em; text-transform:uppercase; color:var(--azure);
  display:block; margin-bottom:.35rem;
}

.up-title {
  font-family:var(--font-ui); font-size:1.05rem; font-weight:600;
  letter-spacing:.04em; color:var(--ivory); display:block;
  margin-bottom:.25rem;
}

.up-tagline {
  font-family:var(--font-body); font-size:.88rem; font-weight:300;
  color:rgba(176,196,222,0.5); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}

/* Toggle arrow */
.up-arrow {
  width:32px; height:32px; border-radius:50%;
  background:var(--glass); border:1px solid var(--border);
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0; transition:all .35s;
}
.up-arrow svg { width:14px; height:14px; stroke:var(--mist); transition:transform .35s; }
.upcoming-item[open] .up-arrow { background:var(--gold-dim); border-color:var(--gold); }
.upcoming-item[open] .up-arrow svg { transform:rotate(180deg); stroke:var(--gold); }

/* Expanded content */
.upcoming-body {
  padding:0 2rem 2rem;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:2.5rem;
  animation:expandIn .35s ease;
}
@keyframes expandIn {
  from { opacity:0; transform:translateY(-8px); }
  to   { opacity:1; transform:translateY(0); }
}

.up-description {
  font-family:var(--font-body); font-size:1rem; font-weight:300;
  color:rgba(176,196,222,0.68); line-height:1.8;
}

/* Why this game — benefits */
.up-benefits { }

.up-benefits-title {
  font-family:var(--font-ui); font-size:.72rem; font-weight:600;
  letter-spacing:.18em; text-transform:uppercase;
  color:var(--gold); margin-bottom:1rem; display:block;
}

.benefit-list { display:flex; flex-direction:column; gap:.65rem; }

.benefit-item {
  display:flex; align-items:flex-start; gap:.75rem;
}

.benefit-marker {
  width:1px; height:100%; min-height:20px;
  background:linear-gradient(to bottom,var(--gold),var(--azure));
  flex-shrink:0; margin-top:.35rem; align-self:stretch;
  border-radius:1px;
}

.benefit-text {
  font-family:var(--font-body); font-size:.94rem; font-weight:300;
  color:rgba(176,196,222,0.65); line-height:1.6;
}

.benefit-text strong {
  font-family:var(--font-ui); font-size:.78rem; font-weight:600;
  letter-spacing:.06em; text-transform:uppercase;
  color:var(--mist); display:block; margin-bottom:.15rem;
}

/* Coming soon tag */
.coming-soon-badge {
  display:inline-flex; align-items:center; gap:.5rem;
  font-family:var(--font-ui); font-size:.68rem; font-weight:600;
  letter-spacing:.14em; text-transform:uppercase;
  padding:.35rem 1rem; border-radius:50px;
  background:rgba(74,158,255,0.1); border:1px solid rgba(74,158,255,0.25);
  color:var(--azure-lt); margin-top:1.5rem; width:fit-content;
}
.coming-soon-badge .cs-dot {
  width:6px; height:6px; border-radius:50%;
  background:var(--azure); animation:csPulse 2s infinite;
}
@keyframes csPulse {
  0%,100%{ box-shadow:0 0 0 0 rgba(74,158,255,.5); }
  50%    { box-shadow:0 0 0 6px rgba(74,158,255,0); }
}

/* ============================================================
   ILLUSTRATED ART — CSS drawn backgrounds for game cards
============================================================ */

/* ── GAME 1 — Flash Words: speed-reading WPM meter ── */
.art-flash-words {
  background: linear-gradient(160deg, #07090f 0%, #0b0d1c 55%, #060810 100%);
  position:relative; overflow:hidden;
  display:flex; align-items:center; justify-content:center;
}
/* faint horizontal scan lines */
.art-flash-words::before {
  content:'';
  position:absolute; inset:0;
  background-image: repeating-linear-gradient(
    0deg,
    transparent, transparent 18px,
    rgba(74,158,255,0.025) 18px, rgba(74,158,255,0.025) 19px
  );
}
.art-flash-words .fw-center {
  position:relative; z-index:1; text-align:center;
}
/* the giant WPM number */
.art-flash-words .fw-wpm {
  font-family: var(--font-mono);
  font-size: 3.6rem; font-weight:700; line-height:1;
  letter-spacing:-.02em;
  background: linear-gradient(135deg, var(--azure) 0%, var(--azure-lt) 60%, #a8d8ff 100%);
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
  filter: drop-shadow(0 0 28px rgba(74,158,255,0.55));
  animation: wpmCount 3s ease-in-out infinite alternate;
}
@keyframes wpmCount {
  from { filter:drop-shadow(0 0 14px rgba(74,158,255,0.35)); letter-spacing:-.02em; }
  to   { filter:drop-shadow(0 0 48px rgba(74,158,255,0.75)); letter-spacing:.04em;  }
}
.art-flash-words .fw-unit {
  font-family:var(--font-mono); font-size:.6rem; letter-spacing:.3em;
  text-transform:uppercase; color:rgba(74,158,255,0.45);
  display:block; margin-top:.4rem;
}
/* three progress bars beneath */
.art-flash-words .fw-bars {
  display:flex; flex-direction:column; gap:6px;
  margin-top:1.1rem; width:120px;
}
.art-flash-words .fw-bar {
  height:3px; border-radius:2px;
  background:rgba(74,158,255,0.12);
  position:relative; overflow:hidden;
}
.art-flash-words .fw-bar::after {
  content:'';
  position:absolute; top:0; left:0; height:100%;
  background:linear-gradient(to right, var(--azure), var(--azure-lt));
  border-radius:2px;
  animation:barFill 2.4s ease-in-out infinite alternate;
}
.art-flash-words .fw-bar:nth-child(1)::after { width:82%; animation-delay:0s; }
.art-flash-words .fw-bar:nth-child(2)::after { width:61%; animation-delay:.3s; }
.art-flash-words .fw-bar:nth-child(3)::after { width:93%; animation-delay:.6s; }
@keyframes barFill {
  from { opacity:.5; transform:scaleX(.85); transform-origin:left; }
  to   { opacity:1;  transform:scaleX(1);   transform-origin:left; }
}
/* subtle right-edge glow */
.art-flash-words::after {
  content:'';
  position:absolute; right:0; top:20%; bottom:20%; width:2px;
  background:linear-gradient(to bottom, transparent, var(--azure), transparent);
  opacity:.3;
}

/* ── GAME 2 — Story Scramble: scattered blocks ── */
.art-scramble {
  background: linear-gradient(160deg, #080a14 0%, #0c0f20 55%, #070810 100%);
  position:relative; overflow:hidden;
  display:flex; align-items:center; justify-content:center;
}
.art-scramble .sc-blocks {
  position:relative; z-index:1;
  display:flex; flex-direction:column; gap:8px;
  width:170px;
}
.art-scramble .sc-row {
  display:flex; align-items:center; gap:7px;
}
.sc-block {
  height:16px; border-radius:4px;
  background:rgba(232,184,75,0.1); border:1px solid rgba(232,184,75,0.18);
  animation:blockFloat ease-in-out infinite alternate;
  flex-shrink:0;
}
/* widths give text-like rhythm */
.sc-block.w-lg  { width:72px; }
.sc-block.w-md  { width:48px; }
.sc-block.w-sm  { width:32px; }
.sc-block.w-xs  { width:22px; }
/* one row is "correct" — highlighted gold */
.sc-block.correct {
  background:rgba(232,184,75,0.22); border-color:rgba(232,184,75,0.5);
  box-shadow:0 0 12px rgba(232,184,75,0.25);
}
/* drag handle on the correct row */
.sc-handle {
  width:10px; display:flex; flex-direction:column; gap:2px; cursor:grab; flex-shrink:0;
}
.sc-handle span {
  display:block; height:1px; width:10px;
  background:rgba(232,184,75,0.4); border-radius:1px;
}
@keyframes blockFloat {
  from { transform:translateY(0)    rotate(0deg);   opacity:.7; }
  to   { transform:translateY(-3px) rotate(.4deg);  opacity:1;  }
}
.sc-block:nth-child(1){ animation-duration:2.1s; animation-delay:0s;   }
.sc-block:nth-child(2){ animation-duration:2.8s; animation-delay:.4s;  }
.sc-block:nth-child(3){ animation-duration:1.9s; animation-delay:.2s;  }
/* number label on left */
.sc-num {
  font-family:var(--font-mono); font-size:.58rem; font-weight:700;
  color:rgba(232,184,75,0.35); letter-spacing:.1em; width:14px;
  flex-shrink:0; text-align:right;
}
.sc-num.active { color:var(--gold); }
/* faint diagonal line */
.art-scramble::before {
  content:'';
  position:absolute; inset:0;
  background:linear-gradient(135deg,
    transparent 48%, rgba(232,184,75,0.04) 49%,
    rgba(232,184,75,0.04) 51%, transparent 52%);
}

/* ── GAME 3 — Who Said It: dialogue bubble ── */
.art-who-said {
  background: linear-gradient(160deg, #090810 0%, #0d0b1e 55%, #060710 100%);
  position:relative; overflow:hidden;
  display:flex; align-items:center; justify-content:center;
}
.art-who-said .ws-stage {
  position:relative; z-index:1; display:flex; flex-direction:column;
  align-items:center; gap:.9rem;
}
/* countdown ring */
.ws-ring {
  width:60px; height:60px; border-radius:50%;
  position:relative; display:flex; align-items:center; justify-content:center;
}
.ws-ring svg {
  position:absolute; inset:0; width:100%; height:100%;
  transform:rotate(-90deg);
}
.ws-ring .ring-track {
  fill:none; stroke:rgba(232,184,75,0.1); stroke-width:3;
}
.ws-ring .ring-fill {
  fill:none; stroke:url(#ringGrad); stroke-width:3;
  stroke-dasharray:163; stroke-dashoffset:0;
  stroke-linecap:round;
  animation:ringDrain 10s linear infinite;
}
@keyframes ringDrain {
  from { stroke-dashoffset:0;   }
  to   { stroke-dashoffset:163; }
}
.ws-countdown {
  font-family:var(--font-mono); font-size:1.3rem; font-weight:700;
  color:var(--gold-lt); line-height:1;
  animation:cdTick 1s steps(1) infinite;
}
@keyframes cdTick {
  0%  { opacity:1; }
  50% { opacity:.6; }
}
/* quote box */
.ws-quote {
  background:rgba(255,255,255,0.04); border:1px solid rgba(232,184,75,0.16);
  border-radius:10px; padding:.65rem .9rem;
  font-family:var(--font-body); font-size:.82rem; font-style:italic;
  font-weight:300; color:rgba(240,234,216,0.7);
  max-width:170px; text-align:center; line-height:1.5;
  position:relative;
}
/* speech-bubble tail */
.ws-quote::after {
  content:'';
  position:absolute; top:100%; left:50%; transform:translateX(-50%);
  border:7px solid transparent;
  border-top-color:rgba(232,184,75,0.16);
}
/* two attribution options */
.ws-options {
  display:flex; gap:.5rem;
}
.ws-opt {
  font-family:var(--font-ui); font-size:.68rem; font-weight:600;
  letter-spacing:.08em; text-transform:uppercase;
  padding:.28rem .75rem; border-radius:50px;
  border:1px solid rgba(255,255,255,0.1);
  color:rgba(176,196,222,0.5);
  background:rgba(255,255,255,0.04);
  animation:optPulse 3s ease-in-out infinite alternate;
}
.ws-opt.ws-opt-b { animation-delay:1.5s; border-color:rgba(232,184,75,0.2); color:rgba(232,184,75,0.55); }
@keyframes optPulse {
  from { transform:scale(1);    opacity:.6; }
  to   { transform:scale(1.04); opacity:1;  }
}
/* faint radial burst */
.art-who-said::before {
  content:'';
  position:absolute; inset:0;
  background:radial-gradient(ellipse 70% 60% at 50% 50%, rgba(130,80,255,0.06) 0%, transparent 70%);
}

/* ============================================================
   FOOTER (same as index.php)
============================================================ */
footer {
  background:var(--void);
  border-top:1px solid var(--border);
  padding:5rem 5% 2.5rem;
  margin-top:auto;
}
.footer-top {
  display:grid; grid-template-columns:2fr 1fr 1fr 1fr;
  gap:3rem; margin-bottom:4rem;
  max-width:1400px; margin-left:auto; margin-right:auto;
}
.footer-logo {
  font-family:var(--font-display); font-size:1.2rem; font-weight:700;
  background:linear-gradient(135deg,var(--gold),var(--azure-lt));
  -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
  display:block; margin-bottom:1rem; text-decoration:none;
}
.footer-brand p {
  font-family:var(--font-body); font-size:.9rem; font-weight:300;
  color:rgba(176,196,222,0.45); line-height:1.7; max-width:260px;
}
.footer-col h5 {
  font-family:var(--font-ui); font-size:.72rem; font-weight:600;
  letter-spacing:.2em; text-transform:uppercase; color:var(--gold); margin-bottom:1.25rem;
}
.footer-col ul { list-style:none; }
.footer-col ul li { margin-bottom:.6rem; }
.footer-col ul li a {
  font-family:var(--font-body); font-size:.9rem; font-weight:300;
  color:rgba(176,196,222,0.45); text-decoration:none; transition:color .2s;
}
.footer-col ul li a:hover { color:var(--ivory); }
.social-links { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1.25rem; }
.social-btn {
  width:38px; height:38px; border-radius:9px;
  background:var(--glass); border:1px solid var(--border);
  display:flex; align-items:center; justify-content:center;
  font-size:.85rem; text-decoration:none; color:var(--mist); transition:all .3s;
  font-family:var(--font-ui); font-weight:600; letter-spacing:.05em;
}
.social-btn:hover { background:rgba(232,184,75,0.1); border-color:var(--gold); color:var(--gold); transform:translateY(-3px); }
.footer-bottom {
  border-top:1px solid var(--border); padding-top:2rem;
  display:flex; align-items:center; justify-content:space-between;
  flex-wrap:wrap; gap:1rem;
  max-width:1400px; margin:0 auto;
}
.footer-bottom p { font-family:var(--font-mono); font-size:.66rem; letter-spacing:.1em; color:rgba(176,196,222,.28); }

/* ============================================================
   KEYFRAMES
============================================================ */
@keyframes fadeIn      { from{opacity:0}           to{opacity:1} }
@keyframes fadeSlideUp { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeSlideDown{from{opacity:0;transform:translateY(-18px)} to{opacity:1;transform:translateY(0)}}

/* ============================================================
   RESPONSIVE
============================================================ */
@media(max-width:1024px){
  .available-grid{ grid-template-columns:repeat(2,1fr); }
  .footer-top{ grid-template-columns:1fr 1fr; }
}
@media(max-width:700px){
  .available-grid{ grid-template-columns:1fr; }
  .upcoming-body{ grid-template-columns:1fr; gap:1.5rem; }
  .upcoming-summary{ grid-template-columns:48px 1fr auto; gap:1rem; padding:1.2rem 1.25rem; }
  .up-index{ font-size:1.6rem; }
  .stat-strip{ flex-wrap:wrap; }
  .stat-item{ flex:1; min-width:80px; padding:.75rem 1rem; }
  .footer-top{ grid-template-columns:1fr; }
  .nav-links{ display:none; }
  .nav-links.open{
    display:flex; flex-direction:column; position:fixed;
    top:70px; left:0; right:0;
    background:rgba(6,8,16,.97); backdrop-filter:blur(20px);
    padding:1.5rem 5%; border-bottom:1px solid var(--border);
    gap:.25rem; z-index:999;
  }
  .nav-toggle{ display:flex; }
}
</style>
</head>
<body>

<!-- ============================================================
     NAVBAR
============================================================ -->
<nav id="navbar">
  <a href="index.php" class="nav-logo">StoryVerse</a>

  <ul class="nav-links" id="navLinks">
    <li><a href="index.php">Home</a></li>
    <li><a href="stories.php">Stories</a></li>
    <?php if ($isLoggedIn): ?>
      <li><a href="game_arena.php" class="active-nav">Game Arena</a></li>
      <li><a href="leaderboard.php">Leaderboard</a></li>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="logout.php" class="nav-btn">Log out</a></li>
    <?php else: ?>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="signin.php" class="nav-btn">Sign In</a></li>
      <li><a href="signup.php" class="nav-btn nav-btn-primary">Sign Up</a></li>
    <?php endif; ?>
  </ul>

  <div class="nav-toggle" id="navToggle" onclick="toggleNav()" aria-label="Menu">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- ============================================================
     HERO
============================================================ -->
<section class="arena-hero">
  <div class="arena-grid-bg"></div>
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>

  <div class="arena-hero-content">
    <span class="hero-eyebrow">StoryVerse &nbsp;&mdash;&nbsp; Game Arena</span>
    <h1 class="arena-title">
      <span class="word-1">Test Your</span>
      <span class="word-2">Narrative Instinct</span>
    </h1>
    <p class="arena-sub">
      Three games. One universe. Compete, predict, and outthink
      every reader in the StoryVerse.
    </p>
    <div class="hero-cta-row">
      <a href="#available" class="btn btn-primary">Enter the Arena</a>
      <a href="leaderboard.php" class="btn btn-ghost">View Leaderboard</a>
    </div>
  </div>

  <div class="scroll-cue">
    <div class="scroll-cue-line"></div>
    <span class="scroll-cue-text">Explore</span>
  </div>

  <!-- Live stats strip -->
  <div class="stat-strip">
    <div class="stat-item">
      <span class="stat-num" id="heroPlayers">—</span>
      <span class="stat-lbl">Active Players</span>
    </div>
    <div class="stat-item">
      <span class="stat-num">3</span>
      <span class="stat-lbl">Live Games</span>
    </div>
    <div class="stat-item">
      <span class="stat-num">7</span>
      <span class="stat-lbl">Upcoming Games</span>
    </div>
    <div class="stat-item">
      <span class="stat-num">Daily</span>
      <span class="stat-lbl">Leaderboard Reset</span>
    </div>
  </div>
</section>

<!-- ============================================================
     SECTION 1 — AVAILABLE GAMES
============================================================ -->
<section class="games-available" id="available">
  <div class="section-wrap">

    <div class="section-header" data-aos="fade-right" data-aos-duration="700">
      <span class="section-eyebrow">Now Live</span>
      <h2 class="section-title">Available Games</h2>
      <div class="s-divider"></div>
      <p class="section-desc">
        Three distinct challenges drawn from the world of narrative prediction.
        Each game tests a different dimension of your literary intelligence.
      </p>
    </div>

    <div class="available-grid">

      <!-- ── GAME 1 : Flash Words ── -->
      <div class="game-card" data-aos="fade-up" data-aos-delay="0" data-aos-duration="700">
        <div class="game-card-art art-flash-words">
          <div class="fw-center">
            <span class="fw-wpm">348</span>
            <span class="fw-unit">words per minute</span>
            <div class="fw-bars">
              <div class="fw-bar"></div>
              <div class="fw-bar"></div>
              <div class="fw-bar"></div>
            </div>
          </div>
          <div class="card-img-overlay"></div>
          <span class="card-num">01 / 03</span>
          <div class="card-play-badge">
            <svg viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z"/></svg>
          </div>
        </div>

        <div class="game-card-body">
          <span class="game-tag">Speed Reading</span>
          <h3 class="game-name">Flash Words</h3>
          <p class="game-desc">
            Words appear one at a time at your chosen pace — train your reading
            speed from 100 to 1000 WPM. After the passage ends, a comprehension
            question confirms you read with intent, not just velocity.
          </p>

          <div class="skill-pills">
            <span class="skill-pill">Reading Velocity</span>
            <span class="skill-pill">Focus &amp; Retention</span>
            <span class="skill-pill">Comprehension</span>
            <span class="skill-pill">Cognitive Stamina</span>
          </div>

          <div class="game-card-action">
            <div class="game-difficulty">
              <span class="diff-label">Difficulty</span>
              <div class="diff-dots">
                <div class="diff-dot lit"></div>
                <div class="diff-dot lit"></div>
                <div class="diff-dot"></div>
                <div class="diff-dot"></div>
                <div class="diff-dot"></div>
              </div>
            </div>
            <a href="flash_words.php" class="game-play-btn">Play Now</a>
          </div>
        </div>
      </div>

      <!-- ── GAME 2 : Story Scramble ── -->
      <div class="game-card" data-aos="fade-up" data-aos-delay="120" data-aos-duration="700">
        <div class="game-card-art art-scramble">
          <div class="sc-blocks">
            <div class="sc-row">
              <span class="sc-num">3</span>
              <div class="sc-handle"><span></span><span></span><span></span></div>
              <div class="sc-block w-lg"></div>
              <div class="sc-block w-sm"></div>
            </div>
            <div class="sc-row">
              <span class="sc-num active">1</span>
              <div class="sc-handle"><span></span><span></span><span></span></div>
              <div class="sc-block w-md correct"></div>
              <div class="sc-block w-lg correct"></div>
              <div class="sc-block w-xs correct"></div>
            </div>
            <div class="sc-row">
              <span class="sc-num">4</span>
              <div class="sc-handle"><span></span><span></span><span></span></div>
              <div class="sc-block w-sm"></div>
              <div class="sc-block w-md"></div>
            </div>
            <div class="sc-row">
              <span class="sc-num">2</span>
              <div class="sc-handle"><span></span><span></span><span></span></div>
              <div class="sc-block w-lg"></div>
              <div class="sc-block w-xs"></div>
              <div class="sc-block w-sm"></div>
            </div>
          </div>
          <div class="card-img-overlay"></div>
          <span class="card-num">02 / 03</span>
          <div class="card-play-badge">
            <svg viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z"/></svg>
          </div>
        </div>

        <div class="game-card-body">
          <span class="game-tag">Narrative Order</span>
          <h3 class="game-name">Story Scramble</h3>
          <p class="game-desc">
            Sentences from a story have been scattered. Drag and restore their
            correct order — prove your narrative instinct by reconstructing the
            sequence exactly as the author intended.
          </p>

          <div class="skill-pills">
            <span class="skill-pill">Narrative Logic</span>
            <span class="skill-pill">Structural Thinking</span>
            <span class="skill-pill">Story Sequencing</span>
            <span class="skill-pill">Spatial Reasoning</span>
          </div>

          <div class="game-card-action">
            <div class="game-difficulty">
              <span class="diff-label">Difficulty</span>
              <div class="diff-dots">
                <div class="diff-dot lit"></div>
                <div class="diff-dot lit"></div>
                <div class="diff-dot lit"></div>
                <div class="diff-dot"></div>
                <div class="diff-dot"></div>
              </div>
            </div>
            <a href="story_scramble.php" class="game-play-btn">Play Now</a>
          </div>
        </div>
      </div>

      <!-- ── GAME 3 : Who Said It ── -->
      <div class="game-card" data-aos="fade-up" data-aos-delay="240" data-aos-duration="700">
        <div class="game-card-art art-who-said">
          <svg width="0" height="0" style="position:absolute">
            <defs>
              <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%"   stop-color="#c8860a"/>
                <stop offset="100%" stop-color="#e8b84b"/>
              </linearGradient>
            </defs>
          </svg>
          <div class="ws-stage">
            <div class="ws-ring">
              <svg viewBox="0 0 56 56">
                <circle class="ring-track" cx="28" cy="28" r="26"/>
                <circle class="ring-fill"  cx="28" cy="28" r="26"/>
              </svg>
              <span class="ws-countdown">7</span>
            </div>
            <div class="ws-quote">
              "The night was longer than I had expected."
            </div>
            <div class="ws-options">
              <span class="ws-opt">Elara</span>
              <span class="ws-opt ws-opt-b">Marcus</span>
            </div>
          </div>
          <div class="card-img-overlay"></div>
          <span class="card-num">03 / 03</span>
          <div class="card-play-badge">
            <svg viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z"/></svg>
          </div>
        </div>

        <div class="game-card-body">
          <span class="game-tag">Character Intelligence</span>
          <h3 class="game-name">Who Said It</h3>
          <p class="game-desc">
            A dialogue appears — you decide who spoke it. Ten seconds per
            question; faster correct answers earn more points. Know your
            characters as well as you know the story.
          </p>

          <div class="skill-pills">
            <span class="skill-pill">Character Empathy</span>
            <span class="skill-pill">Dialogue Intuition</span>
            <span class="skill-pill">Voice Recognition</span>
            <span class="skill-pill">Timed Precision</span>
          </div>

          <div class="game-card-action">
            <div class="game-difficulty">
              <span class="diff-label">Difficulty</span>
              <div class="diff-dots">
                <div class="diff-dot lit"></div>
                <div class="diff-dot lit"></div>
                <div class="diff-dot lit"></div>
                <div class="diff-dot lit"></div>
                <div class="diff-dot"></div>
              </div>
            </div>
            <a href="who_said_it.php" class="game-play-btn">Play Now</a>
          </div>
        </div>
      </div>

    </div><!-- /available-grid -->
  </div><!-- /section-wrap -->
</section>

<!-- Separator -->
<div style="padding:0 5%"><div class="section-sep"></div></div>

<!-- ============================================================
     SECTION 2 — UPCOMING GAMES
============================================================ -->
<section class="games-upcoming" id="upcoming">
  <div class="section-wrap">

    <div class="section-header" data-aos="fade-right" data-aos-duration="700">
      <span class="section-eyebrow">Coming Soon</span>
      <h2 class="section-title">Upcoming Games</h2>
      <div class="s-divider"></div>
      <p class="section-desc">
        Four new game modes in development — with three more on the horizon.
        Each one pushes the boundaries of how readers engage with narrative
        intelligence. Expand any card to learn what it trains and why it matters.
      </p>
    </div>

    <div class="upcoming-list">

      <!-- ── UPCOMING 1 : Author's Mask ── -->
      <details class="upcoming-item" data-aos="fade-up" data-aos-delay="0" data-aos-duration="650">
        <summary class="upcoming-summary">
          <span class="up-index">04</span>
          <div class="up-meta">
            <span class="up-tag">Deception &amp; Deduction</span>
            <span class="up-title">Author's Mask</span>
            <span class="up-tagline">Identify the hidden author from style alone — no names, no hints.</span>
          </div>
          <div class="up-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </div>
        </summary>

        <div class="upcoming-body">
          <div>
            <p class="up-description">
              Three anonymous passages. Three distinct authors. Your task is to attribute each
              passage to its writer using nothing but stylistic cues — sentence rhythm, vocabulary
              density, narrative voice, and tonal fingerprint. The AI evaluates your reasoning,
              not just your answer. A game designed for readers who truly listen to the way a
              story breathes.
            </p>
            <div class="coming-soon-badge">
              <span class="cs-dot"></span>
              In Development
            </div>
          </div>

          <div class="up-benefits">
            <span class="up-benefits-title">Why this sharpens your mind</span>
            <div class="benefit-list">
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Stylistic Perception</strong>
                  Training yourself to detect authorial voice heightens your sensitivity
                  to how writing choices create meaning.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Analytical Reasoning</strong>
                  Attributing authorship demands structured, evidence-based thinking
                  rather than surface-level impression.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Literary Vocabulary</strong>
                  Repeated exposure to diverse styles expands your understanding
                  of literary devices and rhetorical technique.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Deductive Discipline</strong>
                  Learning to hold multiple hypotheses simultaneously before
                  committing — a transferable cognitive skill.
                </div>
              </div>
            </div>
          </div>
        </div>
      </details>

      <!-- ── UPCOMING 2 : Plot Architect ── -->
      <details class="upcoming-item" data-aos="fade-up" data-aos-delay="80" data-aos-duration="650">
        <summary class="upcoming-summary">
          <span class="up-index">05</span>
          <div class="up-meta">
            <span class="up-tag">Collaborative Strategy</span>
            <span class="up-title">Plot Architect</span>
            <span class="up-tagline">Co-write the next chapter with another reader — then let the community judge.</span>
          </div>
          <div class="up-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </div>
        </summary>

        <div class="upcoming-body">
          <div>
            <p class="up-description">
              Paired with a random partner, you alternate writing sentences to construct
              the next story chapter within a strict word budget. Once submitted, the
              community votes on which pair's chapter best continues the narrative.
              The winning team earns points; the losing team receives the author's
              own continuation as a benchmark for reflection.
            </p>
            <div class="coming-soon-badge">
              <span class="cs-dot"></span>
              In Development
            </div>
          </div>

          <div class="up-benefits">
            <span class="up-benefits-title">Why this sharpens your mind</span>
            <div class="benefit-list">
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Collaborative Creativity</strong>
                  Writing with a stranger demands adaptability — you must build on
                  ideas that aren't your own without losing narrative coherence.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Structural Thinking</strong>
                  Keeping a plot coherent under word constraints forces you to
                  prioritise what the story truly needs at each moment.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Persuasive Writing</strong>
                  Community judging means your chapter must compel readers —
                  clarity and voice become competitive tools.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Narrative Empathy</strong>
                  Understanding what a community of readers finds satisfying
                  builds a rare, author-grade sensitivity to audience.
                </div>
              </div>
            </div>
          </div>
        </div>
      </details>

      <!-- ── UPCOMING 3 : Tension Index ── -->
      <details class="upcoming-item" data-aos="fade-up" data-aos-delay="160" data-aos-duration="650">
        <summary class="upcoming-summary">
          <span class="up-index">06</span>
          <div class="up-meta">
            <span class="up-tag">Precision Scoring</span>
            <span class="up-title">Tension Index</span>
            <span class="up-tagline">Map the exact moment a story's tension peaks — to the sentence.</span>
          </div>
          <div class="up-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </div>
        </summary>

        <div class="upcoming-body">
          <div>
            <p class="up-description">
              A full chapter is displayed. Your goal is to slide a marker along
              a timeline to indicate exactly where narrative tension reaches its
              zenith — the turning point, the breath-hold moment. Our sentiment
              engine has already mapped the chapter's emotional curve. The closer
              your marker is to the AI's calculated peak, the higher you score.
              Precision is everything.
            </p>
            <div class="coming-soon-badge">
              <span class="cs-dot"></span>
              In Development
            </div>
          </div>

          <div class="up-benefits">
            <span class="up-benefits-title">Why this sharpens your mind</span>
            <div class="benefit-list">
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Close Reading</strong>
                  Sentence-level attention to pacing, syntax, and word choice
                  develops one of literature's most demanding skills.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Emotional Calibration</strong>
                  Distinguishing between moments of high tension and mere excitement
                  builds nuanced emotional discernment.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Story Structure Mastery</strong>
                  Repeated play internalises classical narrative arcs — rising
                  action, climax, release — as intuitive knowledge.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Metacognitive Precision</strong>
                  Committing to a specific moment rather than a range trains
                  confident, precise judgment under uncertainty.
                </div>
              </div>
            </div>
          </div>
        </div>
      </details>

      <!-- ── UPCOMING 4 : The Unreliable Narrator ── -->
      <details class="upcoming-item" data-aos="fade-up" data-aos-delay="240" data-aos-duration="650">
        <summary class="upcoming-summary">
          <span class="up-index">07</span>
          <div class="up-meta">
            <span class="up-tag">Critical Interpretation</span>
            <span class="up-title">The Unreliable Narrator</span>
            <span class="up-tagline">Separate fact from fiction within a story told by a voice that cannot be trusted.</span>
          </div>
          <div class="up-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </div>
        </summary>

        <div class="upcoming-body">
          <div>
            <p class="up-description">
              Every chapter is narrated by a character with a demonstrable bias, blind spot,
              or deliberate deception. Your task is to annotate which statements you believe
              are factually true within the story's world, and which are distorted by the
              narrator's unreliability. The author's own notes, revealed after submission,
              are used to score your critical interpretation.
            </p>
            <div class="coming-soon-badge">
              <span class="cs-dot"></span>
              In Development
            </div>
          </div>

          <div class="up-benefits">
            <span class="up-benefits-title">Why this sharpens your mind</span>
            <div class="benefit-list">
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Source Evaluation</strong>
                  Questioning the reliability of a narrator translates directly
                  to evaluating the credibility of real-world information sources.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Critical Thinking</strong>
                  Separating what is stated from what is true demands sustained
                  scepticism — the foundation of rigorous reasoning.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Bias Recognition</strong>
                  Identifying how a narrator's perspective distorts their account
                  builds awareness of cognitive and ideological bias.
                </div>
              </div>
              <div class="benefit-item">
                <div class="benefit-marker"></div>
                <div class="benefit-text">
                  <strong>Interpretive Confidence</strong>
                  Making and defending judgments in ambiguous situations
                  develops the intellectual courage to hold a position.
                </div>
              </div>
            </div>
          </div>
        </div>
      </details>

    </div><!-- /upcoming-list -->
  </div><!-- /section-wrap -->
</section>

<!-- ============================================================
     FOOTER
============================================================ -->
<footer>
  <div class="footer-top">
    <div class="footer-brand">
      <a href="index.php" class="footer-logo">StoryVerse</a>
      <p>An AI-driven interactive storytelling universe where every reader shapes the narrative. Where stories live, breathe, and evolve.</p>
      <div class="social-links">
        <a href="https://wa.me/yournumber"            class="social-btn" title="WhatsApp"  target="_blank" rel="noopener">WA</a>
        <a href="https://instagram.com/yourhandle"    class="social-btn" title="Instagram" target="_blank" rel="noopener">IG</a>
        <a href="https://github.com/yourrepo"         class="social-btn" title="GitHub"    target="_blank" rel="noopener">GH</a>
        <a href="https://linkedin.com/in/yourprofile" class="social-btn" title="LinkedIn"  target="_blank" rel="noopener">LI</a>
      </div>
    </div>

    <div class="footer-col">
      <h5>Navigate</h5>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="stories.php">Stories</a></li>
        <li><a href="index.php#features">Features</a></li>
        <li><a href="index.php#how-it-works">How It Works</a></li>
        <li><a href="leaderboard.php">Leaderboard</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h5>Arena</h5>
      <ul>
        <li><a href="story_duel.php">Story Duel</a></li>
        <li><a href="sentiment_clash.php">Sentiment Clash</a></li>
        <li><a href="narrative_race.php">Narrative Race</a></li>
        <li><a href="leaderboard.php">Leaderboard</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h5>Contact</h5>
      <ul>
        <li><a href="https://wa.me/yournumber"            target="_blank" rel="noopener">WhatsApp Us</a></li>
        <li><a href="https://instagram.com/yourhandle"    target="_blank" rel="noopener">Instagram</a></li>
        <li><a href="https://github.com/yourrepo"         target="_blank" rel="noopener">GitHub</a></li>
        <li><a href="https://linkedin.com/in/yourprofile" target="_blank" rel="noopener">LinkedIn</a></li>
        <li><a href="mailto:hello@storyverse.com">hello@storyverse.com</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <p>&copy; <?php echo date('Y'); ?> StoryVerse. Crafted with narrative intelligence.</p>
    <p>Built with AI &nbsp;&middot;&nbsp; Powered by Stories &nbsp;&middot;&nbsp; Driven by Readers</p>
  </div>
</footer>

<!-- ============================================================
     SCRIPTS
============================================================ -->
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({ once: true, duration: 700, easing: 'ease-out-cubic', offset: 60 });

/* Mobile nav */
function toggleNav() {
  document.getElementById('navLinks').classList.toggle('open');
}
document.querySelectorAll('#navLinks a').forEach(a =>
  a.addEventListener('click', () => document.getElementById('navLinks').classList.remove('open'))
);

/* Animate stat strip numbers */
function countUp(el, target, duration) {
  const start = performance.now();
  function step(now) {
    const p = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.floor(ease * target).toLocaleString();
    if (p < 1) requestAnimationFrame(step);
    else el.textContent = target.toLocaleString();
  }
  requestAnimationFrame(step);
}

window.addEventListener('load', () => {
  /* Replace with real DB value if available */
  countUp(document.getElementById('heroPlayers'), 247, 1800);
});

/* Smooth open/close animation for <details> */
document.querySelectorAll('.upcoming-item').forEach(details => {
  details.addEventListener('toggle', () => {
    if (details.open) {
      const body = details.querySelector('.upcoming-body');
      body.style.animation = 'none';
      body.offsetHeight; /* reflow */
      body.style.animation = '';
    }
  });
});
</script>
</body>
</html>