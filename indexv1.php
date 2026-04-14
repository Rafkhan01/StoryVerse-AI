<?php
require_once 'db_connect.php';
session_start();
$isLoggedIn = isset($_SESSION['user_id']);

// ── Fetch active announcements (not expired) ───────────────────
$announcements = [];
try {
    $stmt = $pdo->query("
        SELECT id, heading, message, type
        FROM announcements
        WHERE is_active = 1
          AND (ends_at IS NULL OR ends_at >= CURDATE())
        ORDER BY created_at DESC
    ");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $announcements = [];
}

// Fetch stats from DB
$totalStories = 0;
$totalPredictions = 0;
$totalAuthors = 0;

try {
    
    //  require_once 'db_connect.php';
    $totalStories    = $pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn();
    $totalPredictions = $pdo->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
    //$totalAuthors    = $pdo->query("SELECT COUNT(DISTINCT author_id) FROM stories")->fetchColumn();

    // Placeholder until DB is connected:
    //$totalStories     = 128;
    //$totalPredictions = 4372;
    $totalAuthors     = 56;
} catch (Exception $e) {
    $totalStories     = 0;
    $totalPredictions = 0;
    $totalAuthors     = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>StoryVerse — Where Stories Come Alive</title>

<!-- AOS -->
<link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css"/>
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Rajdhani:wght@300;400;500;600;700&family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet"/>

<style>
/* ===== CSS VARIABLES ===== */
:root {
  --ink:       #0a0b14;
  --deep:      #0d0f1f;
  --void:      #060810;
  --ember:     #c8860a;
  --gold:      #e8b84b;
  --gold-lt:   #f5d07a;
  --azure:     #4a9eff;
  --azure-lt:  #7dbfff;
  --mist:      #b8c8e8;
  --ivory:     #f0ead8;
  --smoke:     rgba(255,255,255,0.06);
  --glass:     rgba(255,255,255,0.04);
  --border:    rgba(232,184,75,0.18);
  --shadow:    rgba(0,0,0,0.7);

  --font-display: 'Cinzel Decorative', serif;
  --font-body:    'Cormorant Garamond', serif;
  --font-ui:      'Rajdhani', sans-serif;
  --font-mono:    'Space Mono', monospace;
}

/* ===== RESET & BASE ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; overflow-x: hidden; }

body {
  background: var(--void);
  color: var(--ivory);
  font-family: var(--font-body);
  font-size: 18px;
  line-height: 1.7;
  overflow-x: hidden;
}

::selection { background: rgba(232,184,75,0.3); color: var(--gold-lt); }

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: var(--void); }
::-webkit-scrollbar-thumb { background: var(--ember); border-radius: 2px; }

/* ===== NAVBAR ===== */
#navbar {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 1000;
  padding: 0 5%;
  height: 70px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: background 0.4s, box-shadow 0.4s, backdrop-filter 0.4s;
}

#navbar.scrolled {
  background: rgba(6,8,16,0.92);
  backdrop-filter: blur(20px);
  box-shadow: 0 1px 0 var(--border), 0 8px 32px rgba(0,0,0,0.6);
}

.nav-logo {
  font-family: var(--font-display);
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  background: linear-gradient(135deg, var(--gold), var(--azure-lt));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-decoration: none;
  white-space: nowrap;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  list-style: none;
}

.nav-links a {
  font-family: var(--font-ui);
  font-size: 0.85rem;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--mist);
  text-decoration: none;
  padding: 0.4rem 0.85rem;
  border-radius: 4px;
  transition: color 0.2s, background 0.2s;
  position: relative;
}

.nav-links a::after {
  content: '';
  position: absolute;
  bottom: 0; left: 50%;
  width: 0; height: 1px;
  background: var(--gold);
  transform: translateX(-50%);
  transition: width 0.3s;
}

.nav-links a:hover { color: var(--gold-lt); }
.nav-links a:hover::after { width: 60%; }

.nav-btn {
  font-family: var(--font-ui) !important;
  font-size: 0.8rem !important;
  font-weight: 600 !important;
  letter-spacing: 0.1em !important;
  padding: 0.45rem 1.2rem !important;
  border-radius: 30px !important;
  border: 1px solid var(--border) !important;
  background: transparent !important;
  color: var(--ivory) !important;
  cursor: pointer;
  transition: all 0.3s !important;
}

.nav-btn:hover {
  background: rgba(232,184,75,0.12) !important;
  border-color: var(--gold) !important;
  color: var(--gold-lt) !important;
}

.nav-btn-primary {
  background: linear-gradient(135deg, var(--ember), var(--gold)) !important;
  border: none !important;
  color: var(--void) !important;
}

.nav-btn-primary:hover {
  background: linear-gradient(135deg, var(--gold), var(--gold-lt)) !important;
  color: var(--void) !important;
  transform: translateY(-1px);
  box-shadow: 0 4px 20px rgba(232,184,75,0.35) !important;
}

/* Hamburger */
.nav-toggle { display: none; flex-direction: column; gap: 5px; cursor: pointer; padding: 4px; }
.nav-toggle span { display: block; width: 22px; height: 2px; background: var(--ivory); transition: all 0.3s; border-radius: 2px; }

/* ===== HERO ===== */
#hero {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.hero-bg {
  position: absolute;
  inset: 0;
  background-image: url('opened_book.png');
  background-size: cover;
  background-position: center 60%;
  filter: brightness(0.35) saturate(0.8);
  transform: scale(1.05);
  animation: heroZoom 20s ease-in-out infinite alternate;
}

@keyframes heroZoom {
  from { transform: scale(1.05); }
  to   { transform: scale(1.12); }
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(10,50,120,0.55) 0%, transparent 70%),
    radial-gradient(ellipse 60% 40% at 50% 50%, rgba(6,8,16,0.3) 0%, transparent 80%),
    linear-gradient(to bottom, rgba(6,8,16,0.85) 0%, rgba(6,8,16,0.2) 40%, rgba(6,8,16,0.75) 100%);
}

/* Floating particles */
.particles {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
}

.particle {
  position: absolute;
  border-radius: 50%;
  opacity: 0;
  animation: floatUp linear infinite;
}

@keyframes floatUp {
  0%   { opacity: 0; transform: translateY(0) scale(0); }
  10%  { opacity: 1; }
  90%  { opacity: 0.6; }
  100% { opacity: 0; transform: translateY(-100vh) scale(1.5); }
}

.hero-content {
  position: relative;
  z-index: 2;
  text-align: center;
  padding: 0 5%;
  max-width: 900px;
}

.hero-eyebrow {
  font-family: var(--font-mono);
  font-size: 0.72rem;
  letter-spacing: 0.35em;
  text-transform: uppercase;
  color: var(--azure-lt);
  margin-bottom: 1.5rem;
  opacity: 0;
  animation: fadeInDown 0.8s 0.3s forwards;
}

.hero-title {
  font-family: var(--font-display);
  font-size: clamp(2.6rem, 7vw, 5.5rem);
  font-weight: 900;
  line-height: 1.1;
  margin-bottom: 1.5rem;
  opacity: 0;
  animation: fadeInUp 1s 0.6s forwards;
}

.hero-title .line1 {
  display: block;
  background: linear-gradient(135deg, var(--ivory) 30%, var(--gold-lt));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-title .line2 {
  display: block;
  background: linear-gradient(135deg, var(--azure) 0%, var(--azure-lt) 50%, var(--gold) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-style: italic;
}

.hero-sub {
  font-family: var(--font-body);
  font-size: clamp(1rem, 2.2vw, 1.35rem);
  font-weight: 300;
  color: var(--mist);
  max-width: 600px;
  margin: 0 auto 2.5rem;
  opacity: 0;
  animation: fadeInUp 1s 0.9s forwards;
}

.hero-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
  opacity: 0;
  animation: fadeInUp 1s 1.2s forwards;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-family: var(--font-ui);
  font-size: 0.9rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  text-decoration: none;
  padding: 0.85rem 2.2rem;
  border-radius: 50px;
  transition: all 0.35s;
  cursor: pointer;
  border: none;
}

.btn-primary {
  background: linear-gradient(135deg, var(--ember), var(--gold));
  color: var(--void);
  box-shadow: 0 4px 30px rgba(200,134,10,0.4);
}

.btn-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 40px rgba(232,184,75,0.5);
  background: linear-gradient(135deg, var(--gold), var(--gold-lt));
}

.btn-ghost {
  background: transparent;
  color: var(--ivory);
  border: 1px solid rgba(255,255,255,0.25);
  backdrop-filter: blur(10px);
}

.btn-ghost:hover {
  background: rgba(255,255,255,0.08);
  border-color: var(--azure-lt);
  color: var(--azure-lt);
  transform: translateY(-3px);
}

.hero-scroll {
  position: absolute;
  bottom: 2.5rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  opacity: 0;
  animation: fadeIn 1s 2s forwards;
}

.hero-scroll span {
  font-family: var(--font-mono);
  font-size: 0.6rem;
  letter-spacing: 0.3em;
  color: rgba(255,255,255,0.4);
  text-transform: uppercase;
}

.scroll-dot {
  width: 1px;
  height: 50px;
  background: linear-gradient(to bottom, var(--gold), transparent);
  animation: scrollPulse 2s ease-in-out infinite;
}

@keyframes scrollPulse {
  0%, 100% { opacity: 0.3; transform: scaleY(1); }
  50%       { opacity: 1; transform: scaleY(1.2); }
}

/* ===== WAVE FLOW SECTION (infinite scroll ticker) ===== */
#wave-flow {
  background: linear-gradient(to bottom, var(--void), var(--deep));
  padding: 4rem 0;
  overflow: hidden;
  position: relative;
}

#wave-flow::before,
#wave-flow::after {
  content: '';
  position: absolute;
  top: 0; bottom: 0;
  width: 15%;
  z-index: 2;
  pointer-events: none;
}

#wave-flow::before {
  left: 0;
  background: linear-gradient(to right, var(--void), transparent);
}

#wave-flow::after {
  right: 0;
  background: linear-gradient(to left, var(--void), transparent);
}

.wave-track {
  display: flex;
  width: max-content;
  animation: waveScroll 28s linear infinite;
}

.wave-track:nth-child(2) {
  animation: waveScrollReverse 22s linear infinite;
  margin-top: 1.2rem;
}

@keyframes waveScroll {
  from { transform: translateX(0); }
  to   { transform: translateX(-50%); }
}

@keyframes waveScrollReverse {
  from { transform: translateX(-50%); }
  to   { transform: translateX(0); }
}

.wave-item {
  display: inline-flex;
  align-items: center;
  gap: 1.2rem;
  padding: 0.7rem 2rem;
  margin-right: 1.5rem;
  border-radius: 50px;
  background: var(--glass);
  border: 1px solid var(--border);
  white-space: nowrap;
  font-family: var(--font-ui);
  font-size: 0.88rem;
  font-weight: 500;
  letter-spacing: 0.08em;
  color: var(--mist);
  animation: waveBob 3s ease-in-out infinite;
  animation-delay: var(--delay, 0s);
  transition: border-color 0.3s, color 0.3s;
}

.wave-item:hover {
  border-color: var(--gold);
  color: var(--gold-lt);
}

@keyframes waveBob {
  0%, 100% { transform: translateY(0); }
  50%       { transform: translateY(-5px); }
}

.wave-icon {
  font-size: 1.2rem;
  animation: waveSpin 6s linear infinite;
}

@keyframes waveSpin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

.wave-item.highlight {
  background: linear-gradient(135deg, rgba(232,184,75,0.1), rgba(74,158,255,0.1));
  border-color: rgba(232,184,75,0.35);
  color: var(--gold-lt);
}

/* ===== SECTION SHARED ===== */
.section-label {
  font-family: var(--font-mono);
  font-size: 0.65rem;
  letter-spacing: 0.4em;
  text-transform: uppercase;
  color: var(--azure);
  margin-bottom: 0.75rem;
  display: block;
}

.section-title {
  font-family: var(--font-display);
  font-size: clamp(1.8rem, 4vw, 3rem);
  font-weight: 700;
  line-height: 1.2;
  background: linear-gradient(135deg, var(--ivory) 40%, var(--gold-lt));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 1rem;
}

.section-desc {
  font-family: var(--font-body);
  font-size: 1.05rem;
  font-weight: 300;
  color: rgba(176,196,222,0.8);
  max-width: 550px;
  line-height: 1.8;
}

/* ===== FEATURES ===== */
#features {
  padding: 8rem 5%;
  position: relative;
  overflow: hidden;
}

.features-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 60% 50% at 80% 50%, rgba(74,158,255,0.06) 0%, transparent 70%),
    linear-gradient(to bottom, var(--deep), var(--void));
}

.features-inner {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5rem;
  align-items: center;
  max-width: 1300px;
  margin: 0 auto;
}

.features-left { order: 1; }
.features-right { order: 2; }

/* Book image with glow */
.book-showcase {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
}

.book-glow {
  position: absolute;
  width: 70%;
  height: 70%;
  background: radial-gradient(ellipse, rgba(74,158,255,0.2) 0%, transparent 70%);
  animation: bookGlow 4s ease-in-out infinite alternate;
}

@keyframes bookGlow {
  from { opacity: 0.5; transform: scale(0.9); }
  to   { opacity: 1; transform: scale(1.1); }
}

.book-img {
  width: 100%;
  max-width: 440px;
  border-radius: 12px;
  filter: drop-shadow(0 20px 60px rgba(74,158,255,0.25)) drop-shadow(0 0 40px rgba(0,0,0,0.8));
  animation: bookFloat 6s ease-in-out infinite;
  position: relative;
  z-index: 1;
}

@keyframes bookFloat {
  0%, 100% { transform: translateY(0) rotate(-1deg); }
  50%       { transform: translateY(-12px) rotate(1deg); }
}

/* Feature list */
.features-header {
  margin-bottom: 2.5rem;
}

.feature-list {
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}

.feature-card {
  display: flex;
  gap: 1.2rem;
  align-items: flex-start;
  padding: 1.2rem 1.4rem;
  border-radius: 12px;
  background: var(--glass);
  border: 1px solid var(--border);
  transition: all 0.35s;
  cursor: default;
}

.feature-card:hover {
  background: rgba(232,184,75,0.06);
  border-color: rgba(232,184,75,0.35);
  transform: translateX(6px);
  box-shadow: -3px 0 0 var(--gold), 0 8px 32px rgba(0,0,0,0.3);
}

.feature-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: linear-gradient(135deg, rgba(232,184,75,0.15), rgba(74,158,255,0.15));
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
  transition: transform 0.3s;
}

.feature-card:hover .feature-icon { transform: scale(1.15) rotate(5deg); }

.feature-text h4 {
  font-family: var(--font-ui);
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  color: var(--gold-lt);
  margin-bottom: 0.3rem;
  text-transform: uppercase;
}

.feature-text p {
  font-family: var(--font-body);
  font-size: 0.92rem;
  font-weight: 300;
  color: rgba(176,196,222,0.75);
  line-height: 1.6;
}

/* ===== HOW IT WORKS ===== */
#how-it-works {
  padding: 8rem 5%;
  position: relative;
  background: linear-gradient(to bottom, var(--void), var(--deep));
}

#how-it-works .section-header {
  text-align: center;
  margin-bottom: 5rem;
}

#how-it-works .section-desc {
  margin: 0 auto;
  text-align: center;
}

.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 2rem;
  max-width: 1200px;
  margin: 0 auto;
  position: relative;
}

/* Connecting line */
.steps-grid::before {
  content: '';
  position: absolute;
  top: 56px;
  left: 10%;
  right: 10%;
  height: 1px;
  background: linear-gradient(to right, transparent, var(--border), var(--gold), var(--border), transparent);
}

.step-card {
  background: var(--glass);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 2rem 1.6rem;
  text-align: center;
  position: relative;
  transition: all 0.4s;
}

.step-card:hover {
  background: rgba(232,184,75,0.05);
  border-color: rgba(232,184,75,0.3);
  transform: translateY(-8px);
  box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(232,184,75,0.15);
}

.step-num {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--ember), var(--gold));
  color: var(--void);
  font-family: var(--font-display);
  font-size: 1.1rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
  position: relative;
  z-index: 1;
  box-shadow: 0 0 0 6px rgba(232,184,75,0.12), 0 4px 20px rgba(200,134,10,0.4);
}

.step-card h3 {
  font-family: var(--font-ui);
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ivory);
  margin-bottom: 0.75rem;
}

.step-card p {
  font-family: var(--font-body);
  font-size: 0.95rem;
  font-weight: 300;
  color: rgba(176,196,222,0.7);
  line-height: 1.7;
}

/* ===== HOW IT DIFFERS ===== */
#differs {
  padding: 8rem 5%;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, var(--deep) 0%, var(--void) 100%);
}

.differs-inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
  max-width: 1200px;
  margin: 0 auto;
}

.differs-text { order: 1; }
.differs-image { order: 2; }

.differs-img {
  width: 100%;
  max-width: 520px;
  margin-top: 220px;
  filter: drop-shadow(0 10px 50px rgba(74,158,255,0.2));
  animation: diffFloat 7s ease-in-out infinite;
}

@keyframes diffFloat {
  0%, 100% { transform: translateY(0); }
  50%       { transform: translateY(-10px); }
}

.differs-qa {
  margin-top: 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.qa-item {
  padding: 1.25rem 1.5rem;
  border-left: 2px solid var(--gold);
  background: rgba(232,184,75,0.04);
  border-radius: 0 8px 8px 0;
  transition: border-color 0.3s, background 0.3s;
}

.qa-item:hover {
  border-color: var(--azure);
  background: rgba(74,158,255,0.06);
}

.qa-q {
  font-family: var(--font-ui);
  font-size: 0.85rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 0.4rem;
}

.qa-a {
  font-family: var(--font-body);
  font-size: 1rem;
  font-weight: 300;
  color: rgba(176,196,222,0.8);
  line-height: 1.7;
}

/* ===== WHY JOIN ===== */
#why-join {
  padding: 7rem 5%;
  text-align: center;
  background: var(--void);
  position: relative;
  overflow: hidden;
}

#why-join::before {
  content: '';
  position: absolute;
  top: -100px; left: 50%;
  transform: translateX(-50%);
  width: 800px; height: 400px;
  background: radial-gradient(ellipse, rgba(232,184,75,0.06) 0%, transparent 70%);
  pointer-events: none;
}

.why-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
  max-width: 1000px;
  margin: 3.5rem auto 0;
}

.why-card {
  padding: 2rem 1.5rem;
  background: var(--glass);
  border: 1px solid var(--border);
  border-radius: 14px;
  transition: all 0.3s;
}

.why-card:hover {
  border-color: rgba(232,184,75,0.3);
  transform: translateY(-5px);
  background: rgba(232,184,75,0.04);
}

.why-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
  display: block;
}

.why-card h4 {
  font-family: var(--font-ui);
  font-size: 0.9rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--gold-lt);
  margin-bottom: 0.6rem;
}

.why-card p {
  font-family: var(--font-body);
  font-size: 0.92rem;
  font-weight: 300;
  color: rgba(176,196,222,0.7);
  line-height: 1.65;
}

/* ===== LIVING UNIVERSE ===== */
#universe {
  padding: 7rem 5%;
  background: linear-gradient(to bottom, var(--void), var(--deep));
  text-align: center;
}

.universe-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
  max-width: 1000px;
  margin: 3.5rem auto 0;
}

.stat-card {
  padding: 2.2rem 1rem;
  border-radius: 16px;
  background: var(--glass);
  border: 1px solid var(--border);
  position: relative;
  overflow: hidden;
  transition: all 0.4s;
}

.stat-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(232,184,75,0.07), transparent);
  opacity: 0;
  transition: opacity 0.3s;
}

.stat-card:hover { transform: translateY(-6px); border-color: rgba(232,184,75,0.3); }
.stat-card:hover::before { opacity: 1; }

.stat-num {
  font-family: var(--font-display);
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 700;
  background: linear-gradient(135deg, var(--gold), var(--azure-lt));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  display: block;
  line-height: 1;
  margin-bottom: 0.5rem;
}

.stat-label {
  font-family: var(--font-ui);
  font-size: 0.78rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgba(176,196,222,0.6);
  font-weight: 500;
}

.stat-icon {
  font-size: 1.6rem;
  margin-bottom: 0.75rem;
  display: block;
}

/* ===== CTA ===== */
#cta {
  padding: 9rem 5%;
  text-align: center;
  position: relative;
  overflow: hidden;
  background: var(--deep);
}

#cta::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 50% 50%, rgba(74,158,255,0.07) 0%, transparent 70%),
    radial-gradient(ellipse 50% 40% at 30% 80%, rgba(232,184,75,0.06) 0%, transparent 60%);
}

.cta-title {
  font-family: var(--font-display);
  font-size: clamp(2rem, 5vw, 4rem);
  font-weight: 900;
  line-height: 1.15;
  margin-bottom: 1.25rem;
  background: linear-gradient(135deg, var(--ivory), var(--gold-lt), var(--azure-lt));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.cta-sub {
  font-family: var(--font-body);
  font-size: 1.15rem;
  font-weight: 300;
  font-style: italic;
  color: rgba(176,196,222,0.75);
  max-width: 520px;
  margin: 0 auto 3rem;
}

.cta-actions {
  display: flex;
  gap: 1.2rem;
  justify-content: center;
  flex-wrap: wrap;
}

/* ===== FOOTER ===== */
footer {
  background: var(--void);
  border-top: 1px solid var(--border);
  padding: 5rem 5% 2.5rem;
}

.footer-top {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 3rem;
  margin-bottom: 4rem;
}

.footer-brand .footer-logo {
  font-family: var(--font-display);
  font-size: 1.3rem;
  font-weight: 700;
  background: linear-gradient(135deg, var(--gold), var(--azure-lt));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  display: block;
  margin-bottom: 1rem;
}

.footer-brand p {
  font-family: var(--font-body);
  font-size: 0.9rem;
  font-weight: 300;
  color: rgba(176,196,222,0.55);
  line-height: 1.7;
  max-width: 260px;
}

.footer-col h5 {
  font-family: var(--font-ui);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 1.25rem;
}

.footer-col ul { list-style: none; }

.footer-col ul li { margin-bottom: 0.6rem; }

.footer-col ul li a {
  font-family: var(--font-body);
  font-size: 0.9rem;
  font-weight: 300;
  color: rgba(176,196,222,0.55);
  text-decoration: none;
  transition: color 0.2s;
}

.footer-col ul li a:hover { color: var(--ivory); }

/* Social */
.social-links {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.social-btn {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--glass);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  text-decoration: none;
  color: var(--mist);
  transition: all 0.3s;
}

.social-btn:hover {
  background: rgba(232,184,75,0.1);
  border-color: var(--gold);
  color: var(--gold);
  transform: translateY(-3px);
}

.footer-bottom {
  border-top: 1px solid var(--border);
  padding-top: 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
}

.footer-bottom p {
  font-family: var(--font-mono);
  font-size: 0.68rem;
  letter-spacing: 0.1em;
  color: rgba(176,196,222,0.35);
}

/* ===== ANIMATIONS ===== */
@keyframes fadeIn      { from { opacity: 0; }                    to { opacity: 1; } }
@keyframes fadeInUp    { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeInDown  { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

/* ===== DIVIDER ===== */
.divider {
  width: 60px;
  height: 2px;
  background: linear-gradient(to right, var(--gold), var(--azure));
  margin: 1.5rem 0;
  border-radius: 2px;
}

.divider-center { margin: 1.5rem auto; }

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
  .features-inner,
  .differs-inner { grid-template-columns: 1fr; }

  .features-right { order: -1; }
  .differs-image { order: -1; }

  .universe-grid { grid-template-columns: repeat(2, 1fr); }

  .footer-top { grid-template-columns: 1fr 1fr; }

  .steps-grid::before { display: none; }
}

@media (max-width: 600px) {
  .universe-grid { grid-template-columns: repeat(2, 1fr); }
  .footer-top { grid-template-columns: 1fr; }
  .nav-links { display: none; }
  .nav-links.open {
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 70px; left: 0; right: 0;
    background: rgba(6,8,16,0.97);
    backdrop-filter: blur(20px);
    padding: 1.5rem 5%;
    border-bottom: 1px solid var(--border);
    gap: 0.25rem;
  }
  .nav-toggle { display: flex; }
}

/* ===== COUNTER ANIMATION ===== */
.count-up { display: inline; }

/* ===== ANNOUNCEMENTS ===== */
#announcements-section {
  padding: 2.5rem 5% 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 1100px;
  margin: 0 auto;
}

.ann-banner {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem 1.4rem;
  border-radius: 12px;
  border-left: 4px solid;
  animation: fadeInDown 0.5s ease both;
  position: relative;
}

.ann-banner.warning {
  background: rgba(239, 68, 68, 0.08);
  border-color: #EF4444;
}
.ann-banner.update {
  background: rgba(16, 185, 129, 0.08);
  border-color: #10B981;
}
.ann-banner.info {
  background: rgba(245, 158, 11, 0.08);
  border-color: #F59E0B;
}

.ann-banner-icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  margin-top: 2px;
}
.ann-banner-icon svg {
  width: 100%;
  height: 100%;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.ann-banner.warning .ann-banner-icon { color: #EF4444; }
.ann-banner.update  .ann-banner-icon { color: #10B981; }
.ann-banner.info    .ann-banner-icon { color: #F59E0B; }

.ann-banner-body { flex: 1; min-width: 0; }

.ann-banner-heading {
  font-family: var(--font-ui);
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 0.2rem;
}
.ann-banner.warning .ann-banner-heading { color: #FCA5A5; }
.ann-banner.update  .ann-banner-heading { color: #6EE7B7; }
.ann-banner.info    .ann-banner-heading { color: #FCD34D; }

.ann-banner-message {
  font-family: var(--font-body);
  font-size: 0.92rem;
  font-weight: 300;
  line-height: 1.6;
  color: rgba(240, 234, 216, 0.82);
}

.ann-banner-close {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  background: none;
  border: none;
  cursor: pointer;
  opacity: 0.45;
  color: var(--ivory);
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: opacity 0.2s;
  margin-top: 1px;
}
.ann-banner-close:hover { opacity: 0.9; }
.ann-banner-close svg {
  width: 14px;
  height: 14px;
  stroke: currentColor;
  fill: none;
  stroke-width: 2.5;
  stroke-linecap: round;
}
</style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav id="navbar">
  <a href="#" class="nav-logo">StoryVerse</a>

  <ul class="nav-links" id="navLinks">
    <li><a href="#">Home</a></li>
    <li><a href="stories.php">Stories</a></li>

    <?php if ($isLoggedIn): ?>
      <li><a href="game_arena.php">Game Arena</a></li>
      <li><a href="leaderboard.php">Leaderboard</a></li>
      <li><a href="#features">Features</a></li>
      <li><a href="logout.php" class="nav-btn">Log out</a></li>
    <?php else: ?>
      <li><a href="#features">Features</a></li>
      <li><a href="signin.php" class="nav-btn">Sign In</a></li>
      <li><a href="signup.php" class="nav-btn nav-btn-primary">Sign Up</a></li>
    <?php endif; ?>
  </ul>

  <div class="nav-toggle" id="navToggle" onclick="toggleNav()" aria-label="Menu">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- ===== HERO ===== -->
<section id="hero">
  <div class="hero-bg"></div>
  <div class="hero-overlay"></div>

  <!-- Particles -->
  <div class="particles" id="particles"></div>

  <div class="hero-content">
    <p class="hero-eyebrow">&#9670; &nbsp;An AI-Powered Narrative Universe&nbsp; &#9670;</p>
    <h1 class="hero-title">
      <span class="line1">Every Story</span>
      <span class="line2">Has a Prediction</span>
    </h1>
    <p class="hero-sub">Read living stories, cast your predictions, and watch the narrative unfold — powered by AI that understands the pulse of every tale.</p>
    <div class="hero-actions">
      <?php if ($isLoggedIn): ?>
        <a href="stories.php" class="btn btn-primary">&#9670; Read &amp; Predict</a>
        <a href="game_arena.php" class="btn btn-ghost">&#10095; Game Arena</a>
      <?php else: ?>
        <a href="signup.php" class="btn btn-primary">&#9670; Create Account</a>
        <a href="signin.php" class="btn btn-ghost">&#10095; Sign In</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="hero-scroll">
    <div class="scroll-dot"></div>
    <span>Scroll</span>
  </div>
</section>

<!-- ===== WAVE FLOW ===== -->
<?php if (!empty($announcements)): ?>
<div id="announcements-section">
  <?php foreach ($announcements as $ann):
      $type = htmlspecialchars($ann['type']);
      $icon_svg = match($ann['type']) {
          'warning' => '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
          'update'  => '<svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
          default   => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
      };
  ?>
  <div class="ann-banner <?=$type?>" id="ann-<?=(int)$ann['id']?>">
    <div class="ann-banner-icon"><?=$icon_svg?></div>
    <div class="ann-banner-body">
      <div class="ann-banner-heading"><?=htmlspecialchars($ann['heading'])?></div>
      <div class="ann-banner-message"><?=htmlspecialchars($ann['message'])?></div>
    </div>
    <button class="ann-banner-close" onclick="dismissAnn(<?=(int)$ann['id']?>)" title="Dismiss">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div id="wave-flow">
  <!-- Row 1 -->
  <div class="wave-track" id="waveRow1"></div>
  <!-- Row 2 -->
  <div class="wave-track" id="waveRow2"></div>
</div>

<!-- ===== FEATURES ===== -->
<section id="features">
  <div class="features-bg"></div>
  <div class="features-inner">

    <!-- Book image -->
    <div class="features-right">
      <div class="book-showcase" data-aos="zoom-in" data-aos-duration="900">
        <div class="book-glow"></div>
        <img src="open_side.png" alt="Ancient open book" class="book-img"/>
      </div>
    </div>

    <!-- Feature list -->
    <div class="features-left">
      <div class="features-header" data-aos="fade-right" data-aos-duration="700">
        <span class="section-label">✦ Core Features</span>
        <h2 class="section-title">Crafted for the<br/>Curious Mind</h2>
        <div class="divider"></div>
        <p class="section-desc">StoryVerse blends participatory storytelling with machine intelligence — giving readers a voice in every narrative arc.</p>
      </div>

      <div class="feature-list">
        <div class="feature-card" data-aos="fade-right" data-aos-delay="100">
          <div class="feature-icon">🧠</div>
          <div class="feature-text">
            <h4>AI Semantic Prediction Engine</h4>
            <p>Cross-Encoder Sentence-BERT evaluates your story predictions against actual outcomes with deep semantic understanding — not just keyword matching.</p>
          </div>
        </div>
        <div class="feature-card" data-aos="fade-right" data-aos-delay="200">
          <div class="feature-icon">🎭</div>
          <div class="feature-text">
            <h4>Live Sentiment Analysis</h4>
            <p>Every story chapter is scored in real-time — positive, negative, or neutral — giving authors actionable emotional intelligence on their narratives.</p>
          </div>
        </div>
        <div class="feature-card" data-aos="fade-right" data-aos-delay="300">
          <div class="feature-icon">📊</div>
          <div class="feature-text">
            <h4>Real-Time Author Dashboard</h4>
            <p>Authors see live reader engagement, prediction accuracy heatmaps, and sentiment flow visualized — all updating as readers interact.</p>
          </div>
        </div>
        <div class="feature-card" data-aos="fade-right" data-aos-delay="400">
          <div class="feature-icon">🔐</div>
          <div class="feature-text">
            <h4>Role-Based Access Control</h4>
            <p>Guests, registered readers, authors, and admins each inhabit a tailored experience — gated by email verification and secure session management.</p>
          </div>
        </div>
        <div class="feature-card" data-aos="fade-right" data-aos-delay="500">
          <div class="feature-icon">⚔️</div>
          <div class="feature-text">
            <h4>Competitive Game Arena</h4>
            <p>Turn story prediction into a competitive sport — earn points, climb leaderboards, and prove your narrative intuition against the community.</p>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section id="how-it-works">
  <div class="section-header" data-aos="fade-up">
    <span class="section-label" style="display:block;text-align:center;">✦ The Process</span>
    <h2 class="section-title" style="text-align:center;">How It Works</h2>
    <div class="divider divider-center"></div>
    <p class="section-desc">From first chapter to final prediction — a seamless journey through living narrative.</p>
  </div>

  <div class="steps-grid">
    <div class="step-card" data-aos="fade-up-right" data-aos-duration="700" data-aos-delay="0">
      <div class="step-num">01</div>
      <h3>Discover a Story</h3>
      <p>Browse the StoryVerse library of living narratives — curated by authors across genres, each story unfolding in real-time chapters with an open ending waiting.</p>
    </div>
    <div class="step-card" data-aos="zoom-out-up" data-aos-duration="700" data-aos-delay="100">
      <div class="step-num">02</div>
      <h3>Cast Your Prediction</h3>
      <p>Read the current chapter, absorb the narrative tension, then submit your prediction for what happens next. Our AI engine evaluates your insight semantically — not literally.</p>
    </div>
    <div class="step-card" data-aos="fade-up-left" data-aos-duration="700" data-aos-delay="200">
      <div class="step-num">03</div>
      <h3>See the Outcome</h3>
      <p>When the author publishes the next chapter, StoryVerse scores your prediction using Sentence-BERT — rewarding nuanced understanding over lucky guesses.</p>
    </div>
    <div class="step-card" data-aos="fade-up-right" data-aos-duration="700" data-aos-delay="300">
      <div class="step-num">04</div>
      <h3>Earn &amp; Compete</h3>
      <p>Accurate predictions earn you points and badges. Climb the leaderboard, challenge other readers, and prove your story intuition in the Game Arena.</p>
    </div>
    <div class="step-card" data-aos="zoom-out-up" data-aos-duration="700" data-aos-delay="400">
      <div class="step-num">05</div>
      <h3>Author Insights</h3>
      <p>If you're a storyteller, your dashboard shows reader predictions, sentiment shifts, and engagement patterns — real intelligence to shape your narrative arc.</p>
    </div>
    <div class="step-card" data-aos="fade-up-left" data-aos-duration="700" data-aos-delay="500">
      <div class="step-num">06</div>
      <h3>Grow Your Universe</h3>
      <p>Build a reading identity, follow authors, curate your prediction history, and become part of a community that shapes how stories are told and understood.</p>
    </div>
  </div>
</section>

<!-- ===== HOW IT DIFFERS ===== -->
<section id="differs">
  <div class="differs-inner">

    <div class="differs-text" data-aos="fade-right" data-aos-duration="800">
      <span class="section-label">✦ Our Edge</span>
      <h2 class="section-title">How We Differ<br/>From the Rest</h2>
      <div class="divider"></div>

      <div class="differs-qa">
        <div class="qa-item">
          <p class="qa-q">Isn't this just another reading app?</p>
          <p class="qa-a">Not at all. StoryVerse transforms passive reading into an active intellectual sport — your predictions are evaluated by AI that understands meaning, not keywords.</p>
        </div>
        <div class="qa-item">
          <p class="qa-q">How is the AI evaluation different?</p>
          <p class="qa-a">We use a Cross-Encoder Sentence-BERT model trained on narrative semantics — so a prediction about "the hero sacrificing himself" scores well against "he gave up everything for the cause," even without shared words.</p>
        </div>
        <div class="qa-item">
          <p class="qa-q">What makes authors choose StoryVerse?</p>
          <p class="qa-a">Authors gain real-time reader intelligence — emotional sentiment curves, prediction distribution maps, and engagement heatmaps — tools no other platform provides for storytellers.</p>
        </div>
      </div>
    </div>

    <div class="differs-image" data-aos="fade-left" data-aos-duration="800" data-aos-delay="150">
      <img src="assets/images/diff-reader-2.jpeg" alt="Reader surrounded by story fragments" class="differs-img"/>
    </div>

  </div>
</section>

<!-- ===== WHY JOIN ===== -->
<section id="why-join">
  <div data-aos="fade-up">
    <span class="section-label" style="display:block;text-align:center;">✦ The Community</span>
    <h2 class="section-title" style="text-align:center;">Why Join StoryVerse?</h2>
    <div class="divider divider-center"></div>
    <p class="section-desc" style="margin:0 auto;text-align:center;">Because stories shouldn't just be read — they should be felt, predicted, and lived.</p>
  </div>

  <div class="why-grid">
    <div class="why-card" data-aos="fade-up" data-aos-delay="0">
      <span class="why-icon"><svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></span>
      <h4>Infinite Narratives</h4>
      <p>A growing library of living stories that evolve with reader input — no story ever truly ends here.</p>
    </div>
    <div class="why-card" data-aos="fade-up" data-aos-delay="100">
      <span class="why-icon"><svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg></span>
      <h4>Genuine AI Intelligence</h4>
      <p>Not gimmick AI — real NLP models evaluating the depth of your narrative understanding with precision.</p>
    </div>
    <div class="why-card" data-aos="fade-up" data-aos-delay="200">
      <span class="why-icon"><svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><polyline points="18 20 18 10"/><polyline points="12 20 12 4"/><polyline points="6 20 6 14"/></svg></span>
      <h4>Rewarding Competition</h4>
      <p>Earn recognition for your literary intuition — rankings, badges, and community reputation built on real skill.</p>
    </div>
    <div class="why-card" data-aos="fade-up" data-aos-delay="300">
      <span class="why-icon"><svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></span>
      <h4>Empower Storytellers</h4>
      <p>Authors gain unprecedented insight into how readers experience their work — shaping better stories together.</p>
    </div>
  </div>
</section>

<!-- ===== LIVING UNIVERSE ===== -->
<section id="universe">
  <div data-aos="fade-up">
    <span class="section-label" style="display:block;text-align:center;">✦ Live Numbers</span>
    <h2 class="section-title" style="text-align:center;">Our Living Universe</h2>
    <div class="divider divider-center"></div>
  </div>

  <div class="universe-grid">
    <div class="stat-card" data-aos="zoom-in" data-aos-delay="0">
      <span class="stat-icon"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></span>
      <span class="stat-num" data-target="<?php echo (int)$totalStories; ?>">0</span>
      <span class="stat-label">Total Stories</span>
    </div>
    <div class="stat-card" data-aos="zoom-in" data-aos-delay="100">
      <span class="stat-icon"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg></span>
      <span class="stat-num" data-target="<?php echo (int)$totalPredictions; ?>">0</span>
      <span class="stat-label">Total Predictions</span>
    </div>
    <div class="stat-card" data-aos="zoom-in" data-aos-delay="200">
      <span class="stat-icon"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></span>
      <span class="stat-num" data-target="<?php echo (int)$totalAuthors; ?>">0</span>
      <span class="stat-label">Total Authors</span>
    </div>
    <div class="stat-card" data-aos="zoom-in" data-aos-delay="300">
      <span class="stat-icon"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="6" y1="12" x2="18" y2="12"/><line x1="12" y1="6" x2="12" y2="18"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg></span>
      <span class="stat-num" data-target="3">0</span>
      <span class="stat-label">Total Games</span>
    </div>
  </div>
</section>

<!-- ===== CTA ===== -->
<section id="cta">
  <div data-aos="fade-up">
    <h2 class="cta-title">Ready to Begin?</h2>
    <p class="cta-sub">"The story is not in the pages — it's in the mind of the one who dares to predict what comes next."</p>
    <div class="cta-actions">
      <?php if ($isLoggedIn): ?>
        <a href="stories.php" class="btn btn-primary">✦ Read &amp; Predict</a>
        <a href="game_arena.php" class="btn btn-ghost">⚔ Play &amp; Win</a>
      <?php else: ?>
        <a href="signup.php" class="btn btn-primary">✦ Create Account</a>
        <a href="signup.php" class="btn btn-ghost">→ Sign Up</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer>
  <div class="footer-top">
    <div class="footer-brand">
      <span class="footer-logo">StoryVerse</span>
      <p>An AI-driven interactive storytelling universe where every reader shapes the narrative. Where stories live, breathe, and evolve.</p>
      <br/>
      <div class="social-links" style="margin-top:1rem;">
        <a href="https://wa.me/yournumber" class="social-btn" title="WhatsApp" target="_blank" rel="noopener">💬</a>
        <a href="https://instagram.com/yourhandle" class="social-btn" title="Instagram" target="_blank" rel="noopener">📸</a>
        <a href="https://github.com/yourrepo" class="social-btn" title="GitHub" target="_blank" rel="noopener">🐙</a>
        <a href="https://linkedin.com/in/yourprofile" class="social-btn" title="LinkedIn" target="_blank" rel="noopener">💼</a>
      </div>
    </div>

    <div class="footer-col">
      <h5>Navigate</h5>
      <ul>
        <li><a href="#">Home</a></li>
        <li><a href="stories.php">Stories</a></li>
        <li><a href="#features">Features</a></li>
        <li><a href="#how-it-works">How It Works</a></li>
        <li><a href="leaderboard.php">Leaderboard</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h5>Account</h5>
      <ul>
        <?php if ($isLoggedIn): ?>
          <li><a href="profile.php">My Profile</a></li>
          <li><a href="my_predictions.php">My Predictions</a></li>
          <li><a href="game_arena.php">Game Arena</a></li>
          <li><a href="logout.php">Log Out</a></li>
        <?php else: ?>
          <li><a href="signin.php">Sign In</a></li>
          <li><a href="signup.php">Create Account</a></li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="footer-col">
      <h5>Contact</h5>
      <ul>
        <li><a href="https://wa.me/yournumber" target="_blank" rel="noopener">WhatsApp Us</a></li>
        <li><a href="https://instagram.com/yourhandle" target="_blank" rel="noopener">Instagram</a></li>
        <li><a href="https://github.com/yourrepo" target="_blank" rel="noopener">GitHub</a></li>
        <li><a href="https://linkedin.com/in/yourprofile" target="_blank" rel="noopener">LinkedIn</a></li>
        <li><a href="mailto:hello@storyverse.com">hello@storyverse.com</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <p>© <?php echo date('Y'); ?> StoryVerse. Crafted with narrative intelligence.</p>
    <p>Built with AI · Powered by Stories · Driven by Readers</p>
  </div>
</footer>

<!-- ===== AOS ===== -->
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({
  once: true,
  duration: 750,
  easing: 'ease-out-cubic',
  offset: 60,
});

/* ===== NAVBAR SCROLL ===== */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 30);
});

/* ===== MOBILE NAV ===== */
function toggleNav() {
  document.getElementById('navLinks').classList.toggle('open');
}

/* ===== PARTICLES ===== */
(function spawnParticles() {
  const container = document.getElementById('particles');
  const colors = ['rgba(74,158,255,', 'rgba(232,184,75,', 'rgba(245,208,122,', 'rgba(255,255,255,'];
  for (let i = 0; i < 40; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const size = Math.random() * 4 + 1;
    const color = colors[Math.floor(Math.random() * colors.length)];
    p.style.cssText = `
      width:${size}px; height:${size}px;
      left:${Math.random() * 100}%;
      top:${80 + Math.random() * 20}%;
      background:${color}${0.4 + Math.random() * 0.6});
      animation-duration:${6 + Math.random() * 10}s;
      animation-delay:${Math.random() * 8}s;
    `;
    container.appendChild(p);
  }
})();

/* ===== WAVE FLOW ITEMS ===== */
const waveItems1 = [
  { icon: '◆', text: 'Interactive Storytelling',  cls: '' },
  { icon: '◈', text: 'AI Prediction Engine',       cls: 'highlight' },
  { icon: '◇', text: 'Sentiment Analysis',          cls: '' },
  { icon: '◆', text: 'Author Analytics',            cls: '' },
  { icon: '◈', text: 'Game Arena',                  cls: 'highlight' },
  { icon: '◆', text: 'Live Leaderboard',            cls: '' },
  { icon: '◇', text: 'Living Narratives',           cls: '' },
  { icon: '◈', text: 'Sentence-BERT NLP',           cls: 'highlight' },
];

const waveItems2 = [
  { icon: '◆', text: 'Multi-Role Platform',   cls: '' },
  { icon: '◇', text: 'Secure Auth',            cls: '' },
  { icon: '◈', text: 'Real-Time Updates',      cls: 'highlight' },
  { icon: '◆', text: 'Reader Intelligence',    cls: '' },
  { icon: '◈', text: 'Story Library',          cls: 'highlight' },
  { icon: '◇', text: 'Precision Scoring',      cls: '' },
  { icon: '◈', text: 'Fast & Futuristic',      cls: 'highlight' },
  { icon: '◆', text: 'Community Driven',       cls: '' },
];

function buildWaveRow(rowId, items) {
  const row = document.getElementById(rowId);
  // Duplicate for seamless loop
  const all = [...items, ...items];
  all.forEach((item, i) => {
    const el = document.createElement('div');
    el.className = `wave-item ${item.cls}`;
    el.style.setProperty('--delay', `${(i % items.length) * 0.4}s`);
    el.innerHTML = `<span class="wave-icon">${item.icon}</span>${item.text}`;
    row.appendChild(el);
  });
}

buildWaveRow('waveRow1', waveItems1);
buildWaveRow('waveRow2', waveItems2);

/* ===== COUNT-UP ANIMATION ===== */
function animateCount(el) {
  const target = parseInt(el.getAttribute('data-target'), 10);
  const duration = 2000;
  const start = performance.now();
  function update(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 4);
    el.textContent = Math.floor(ease * target).toLocaleString();
    if (progress < 1) requestAnimationFrame(update);
    else el.textContent = target.toLocaleString();
  }
  requestAnimationFrame(update);
}

const countEls = document.querySelectorAll('.stat-num[data-target]');
const countObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      animateCount(e.target);
      countObs.unobserve(e.target);
    }
  });
}, { threshold: 0.4 });

/* ===== ANNOUNCEMENT DISMISS ===== */
function dismissAnn(id) {
  const el = document.getElementById('ann-' + id);
  if (!el) return;
  el.style.transition = 'opacity 0.3s, transform 0.3s, max-height 0.4s, padding 0.3s, margin 0.3s';
  el.style.opacity    = '0';
  el.style.transform  = 'translateY(-6px)';
  el.style.maxHeight  = el.offsetHeight + 'px';
  requestAnimationFrame(() => {
    el.style.maxHeight  = '0';
    el.style.padding    = '0';
    el.style.marginBottom = '0';
  });
  setTimeout(() => {
    el.remove();
    // Hide container if no banners left
    const section = document.getElementById('announcements-section');
    if (section && !section.querySelector('.ann-banner')) section.remove();
  }, 420);
  // Remember dismissed in sessionStorage so it doesn't reappear on scroll
  try { sessionStorage.setItem('ann_dismissed_' + id, '1'); } catch(e){}
}

// On page load, hide any already-dismissed banners
(function() {
  document.querySelectorAll('.ann-banner[id^="ann-"]').forEach(el => {
    const id = el.id.replace('ann-','');
    try { if (sessionStorage.getItem('ann_dismissed_' + id)) el.remove(); } catch(e){}
  });
  const section = document.getElementById('announcements-section');
  if (section && !section.querySelector('.ann-banner')) section.remove();
})();
</script>
</body>
</html>
