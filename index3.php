<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$user_id    = $_SESSION['user_id'];
$user_name  = $_SESSION['user_name'];
$first_name = $_SESSION['first_name'];
$last_name  = $_SESSION['last_name'];
$email      = $_SESSION['email'];
$total_score = $_SESSION['total_score'];

// --- Featured Story ---
$featured_story = null;
try {
    $sql = "
        SELECT s.*, sp.part_number, sp.content AS part_content,
               sp.prediction_deadline, sp.upload_date
        FROM stories s
        JOIN story_parts sp ON s.story_id = sp.story_id
        WHERE sp.upload_date <= NOW() AND sp.prediction_deadline >= NOW()
        ORDER BY sp.prediction_deadline ASC LIMIT 1;
    ";
    $stmt = $pdo->query($sql);
    $featured_story = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching featured story: " . $e->getMessage());
}

// --- Top 3 Leaderboard ---
$leaderboard = [];
try {
    $stmt = $pdo->query("SELECT user_name, first_name, last_name, total_score, profile_picture FROM users ORDER BY total_score DESC LIMIT 3");
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Leaderboard error: " . $e->getMessage());
}

function getTimeLeft($deadline) {
    $now = new DateTime();
    $deadline_dt = new DateTime($deadline);
    $interval = $now->diff($deadline_dt);
    $parts = [];
    if ($interval->d > 0) $parts[] = $interval->d . 'd';
    if ($interval->h > 0) $parts[] = $interval->h . 'h';
    if ($interval->i > 0) $parts[] = $interval->i . 'm';
    return empty($parts) ? 'Closing soon' : implode(' ', $parts) . ' left';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoryPulse — Read. Predict. Win.</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>

    <!-- Particles.js -->
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>

    <style>
        /* ===== RESET & ROOT ===== */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-deep:    #080c14;
            --bg-card:    rgba(255,255,255,0.04);
            --bg-glass:   rgba(255,255,255,0.06);
            --border:     rgba(255,255,255,0.08);
            --purple:     #7C5CFC;
            --purple-dim: rgba(124,92,252,0.15);
            --cyan:       #00D4FF;
            --cyan-dim:   rgba(0,212,255,0.12);
            --grad:       linear-gradient(135deg, #7C5CFC, #00D4FF);
            --grad-text:  linear-gradient(90deg, #7C5CFC, #00D4FF);
            --text-1:     #F0F4FF;
            --text-2:     #8892A4;
            --text-3:     #4A5568;
            --radius:     16px;
            --radius-lg:  24px;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--bg-deep);
            color: var(--text-1);
            overflow-x: hidden;
            min-height: 100vh;
        }

        a { text-decoration: none; color: inherit; }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: var(--bg-deep); }
        ::-webkit-scrollbar-thumb { background: var(--purple); border-radius: 4px; }

        /* ===== NAVBAR ===== */
        #navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 6%;
            transition: all 0.4s ease;
        }

        #navbar.scrolled {
            background: rgba(8,12,20,0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            padding: 14px 6%;
        }

        .nav-logo {
            font-family: 'Orbitron', sans-serif;
            font-size: 22px;
            font-weight: 700;
            background: var(--grad-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }

        .nav-links {
            display: flex; gap: 8px; align-items: center;
        }

        .nav-links a {
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-2);
            transition: all 0.3s;
        }

        .nav-links a:hover { color: var(--text-1); background: var(--bg-glass); }

        .nav-btn {
            padding: 9px 22px;
            background: var(--grad);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #fff !important;
            transition: all 0.3s;
            box-shadow: 0 0 0 0 rgba(124,92,252,0.4);
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 24px rgba(124,92,252,0.5) !important;
            background: var(--bg-deep) !important;
            border: 1px solid var(--purple);
        }

        /* ===== HERO ===== */
        #hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        #particles-js {
            position: absolute; inset: 0; z-index: 0;
        }

        /* Grid overlay */
        #hero::before {
            content: '';
            position: absolute; inset: 0; z-index: 1;
            background-image:
                linear-gradient(rgba(124,92,252,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(124,92,252,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
        }

        /* Bottom fade */
        #hero::after {
            content: '';
            position: absolute; bottom: 0; left: 0; right: 0;
            height: 200px; z-index: 2;
            background: linear-gradient(to bottom, transparent, var(--bg-deep));
            pointer-events: none;
        }

        .hero-inner {
            position: relative; z-index: 3;
            width: 100%;
            padding: 140px 6% 80px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
            max-width: 1300px;
            margin: 0 auto;
        }

        .hero-left .badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 16px;
            background: var(--purple-dim);
            border: 1px solid rgba(124,92,252,0.3);
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            color: var(--purple);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 28px;
        }

        .badge-dot {
            width: 6px; height: 6px;
            background: var(--cyan);
            border-radius: 50%;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.5; transform: scale(1.4); }
        }

        .hero-left h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(2.2rem, 4vw, 3.6rem);
            font-weight: 900;
            line-height: 1.15;
            margin-bottom: 22px;
            letter-spacing: -0.5px;
        }

        .hero-left h1 .grad-word {
            background: var(--grad-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-left p {
            font-size: 1.05rem;
            color: var(--text-2);
            line-height: 1.75;
            max-width: 480px;
            margin-bottom: 36px;
        }

        .hero-cta-row {
            display: flex; gap: 14px; flex-wrap: wrap; align-items: center;
        }

        .btn-primary {
            padding: 14px 32px;
            background: var(--grad);
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            border: none; cursor: pointer;
            transition: all 0.3s;
            position: relative; overflow: hidden;
        }

        .btn-primary::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
            opacity: 0; transition: opacity 0.3s;
        }

        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 12px 36px rgba(124,92,252,0.45); }
        .btn-primary:hover::after { opacity: 1; }

        .btn-secondary {
            padding: 14px 32px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-2);
            cursor: pointer; transition: all 0.3s;
        }

        .btn-secondary:hover {
            border-color: var(--cyan);
            color: var(--cyan);
            box-shadow: 0 0 20px var(--cyan-dim);
        }

        /* ===== STORY FRAGMENT CARD ===== */
        .hero-right {
            display: flex; justify-content: center; align-items: center;
        }

        .story-fragment {
            width: 100%;
            max-width: 420px;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 32px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            position: relative;
            box-shadow: 0 0 80px rgba(124,92,252,0.1), 0 0 0 1px rgba(255,255,255,0.04);
        }

        .fragment-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px;
        }

        .fragment-tag {
            font-size: 11px; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; color: var(--cyan);
            background: var(--cyan-dim);
            padding: 4px 12px; border-radius: 100px;
        }

        .fragment-dots { display: flex; gap: 6px; }
        .fragment-dots span {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--border);
        }
        .fragment-dots span:nth-child(1) { background: #ff5f57; }
        .fragment-dots span:nth-child(2) { background: #ffbd2e; }
        .fragment-dots span:nth-child(3) { background: #28c840; }

        .fragment-text {
            font-size: 1rem;
            line-height: 1.8;
            color: var(--text-1);
            margin-bottom: 28px;
        }

        .fragment-text .clue-word {
            position: relative;
            color: var(--text-1);
            cursor: pointer;
            transition: all 0.3s;
            padding: 1px 4px;
            border-radius: 4px;
        }

        .fragment-text .clue-word:hover {
            color: var(--cyan);
            background: var(--cyan-dim);
            text-shadow: 0 0 12px rgba(0,212,255,0.6);
        }

        .fragment-question {
            font-family: 'Orbitron', sans-serif;
            font-size: 13px;
            color: var(--text-2);
            margin-bottom: 16px;
            letter-spacing: 0.5px;
        }

        .prediction-hints {
            display: flex; flex-direction: column; gap: 10px;
            max-height: 0; overflow: hidden;
            transition: max-height 0.5s ease, opacity 0.4s ease;
            opacity: 0;
        }

        .prediction-hints.visible { max-height: 200px; opacity: 1; }

        .hint-chip {
            padding: 10px 16px;
            background: var(--purple-dim);
            border: 1px solid rgba(124,92,252,0.25);
            border-radius: 10px;
            font-size: 13px;
            color: var(--text-1);
            cursor: pointer;
            transition: all 0.3s;
        }

        .hint-chip:hover {
            border-color: var(--purple);
            background: rgba(124,92,252,0.25);
            transform: translateX(4px);
        }

        .fragment-action {
            margin-top: 20px;
            width: 100%;
            padding: 13px;
            background: var(--grad);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: all 0.3s;
        }

        .fragment-action:hover {
            box-shadow: 0 8px 28px rgba(124,92,252,0.5);
            transform: translateY(-2px);
        }

        /* Glow orbs behind card */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }

        .orb-purple {
            width: 300px; height: 300px;
            background: rgba(124,92,252,0.18);
            top: 10%; right: 5%;
        }

        .orb-cyan {
            width: 220px; height: 220px;
            background: rgba(0,212,255,0.12);
            bottom: 15%; right: 20%;
        }

        /* ===== WELCOME STRIP ===== */
        .welcome-strip {
            margin: 0 6% 80px;
            padding: 32px 40px;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            backdrop-filter: blur(12px);
        }

        .welcome-left h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            margin-bottom: 4px;
        }

        .welcome-left h2 span {
            background: var(--grad-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-left p {
            font-size: 14px;
            color: var(--text-2);
        }

        .score-pill {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 24px;
            background: var(--purple-dim);
            border: 1px solid rgba(124,92,252,0.3);
            border-radius: 100px;
        }

        .score-icon { font-size: 20px; }

        .score-val {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            background: var(--grad-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .score-label {
            font-size: 12px;
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ===== SECTION WRAPPER ===== */
        .section {
            padding: 80px 6%;
            max-width: 1300px;
            margin: 0 auto;
        }

        .section-label {
            display: inline-block;
            font-size: 11px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase;
            color: var(--cyan);
            margin-bottom: 14px;
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: 700;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .section-sub {
            font-size: 1rem;
            color: var(--text-2);
            max-width: 520px;
            line-height: 1.7;
        }

        /* ===== FEATURED STORY ===== */
        .featured-card {
            margin-top: 48px;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 40px;
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            align-items: flex-start;
            backdrop-filter: blur(12px);
            transition: all 0.4s;
            position: relative; overflow: hidden;
        }

        .story-meta { flex: 1; min-width: 240px; }
        .story-right { flex-shrink: 0; min-width: 160px; }

        .featured-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 2px;
            background: var(--grad);
        }

        .featured-card:hover {
            border-color: rgba(124,92,252,0.3);
            box-shadow: 0 0 60px rgba(124,92,252,0.08);
            transform: translateY(-4px);
        }

        .story-cover {
            width: 90px; height: 120px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 8px 28px rgba(0,0,0,0.4);
        }

        .story-meta h3 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .story-author {
            font-size: 13px;
            color: var(--text-2);
            margin-bottom: 16px;
        }

        .story-desc {
            font-size: 14px;
            color: var(--text-2);
            line-height: 1.7;
            max-width: 600px;
        }


        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px;
            background: rgba(0,200,80,0.12);
            border: 1px solid rgba(0,200,80,0.25);
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            color: #00C850;
            margin-bottom: 12px;
        }

        .status-ping {
            width: 6px; height: 6px;
            background: #00C850;
            border-radius: 50%;
            animation: pulse-dot 2s infinite;
        }

        .countdown {
            font-family: 'Orbitron', sans-serif;
            font-size: 13px;
            color: var(--text-2);
            margin-bottom: 20px;
        }

        .parts-bar {
            display: flex; gap: 4px; margin: 20px 0;
        }

        .part-seg {
            height: 4px; flex: 1;
            background: var(--border);
            border-radius: 4px;
        }

        .part-seg.filled {
            background: var(--grad);
        }

        .no-story {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-2);
        }

        /* ===== HOW IT WORKS (STACKED SCROLL) ===== */
        #how-it-works { padding: 80px 6%; }

        .hiw-header { text-align: center; margin-bottom: 80px; }
        .hiw-header .section-sub { margin: 0 auto; }

        .steps-stack {
            position: relative;
            max-width: 700px;
            margin: 0 auto;
        }

        .step-card {
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 36px 40px;
            margin-bottom: 20px;
            backdrop-filter: blur(16px);
            position: relative; overflow: hidden;
            opacity: 1; transform: none;
            transition: border-color 0.4s, box-shadow 0.4s, transform 0.4s;
        }

        .step-card.will-animate {
            opacity: 0;
            transform: translateY(36px);
            transition: opacity 0.65s ease, transform 0.65s ease, border-color 0.4s, box-shadow 0.4s;
        }

        .step-card.will-animate.revealed {
            opacity: 1;
            transform: translateY(0);
        }

        .step-card::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0;
            width: 3px;
        }

        .step-card:nth-child(1)::before { background: var(--purple); }
        .step-card:nth-child(2)::before { background: linear-gradient(to bottom, var(--purple), var(--cyan)); }
        .step-card:nth-child(3)::before { background: linear-gradient(to bottom, var(--cyan), #00ff88); }
        .step-card:nth-child(4)::before { background: #00ff88; }

        .step-card:hover {
            border-color: rgba(124,92,252,0.3);
            transform: translateY(-4px) !important;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        .step-top {
            display: flex; align-items: center; gap: 16px;
            margin-bottom: 14px;
        }

        .step-num {
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--text-3);
        }

        .step-icon {
            width: 44px; height: 44px;
            background: var(--purple-dim);
            border: 1px solid rgba(124,92,252,0.2);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }

        .step-icon img { width: 22px; height: 22px; filter: invert(1) brightness(2); }

        .step-card h3 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .step-card p {
            font-size: 14px;
            color: var(--text-2);
            line-height: 1.7;
        }

        /* ===== FEATURES / BOOK SECTION ===== */
        #features { padding: 80px 6%; }

        .features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 64px;
            align-items: center;
            margin-top: 60px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .book-visual {
            display: flex; justify-content: center; align-items: center;
            position: relative;
        }

        .book-visual img {
            width: 100%; max-width: 380px;
            filter: drop-shadow(0 0 40px rgba(124,92,252,0.3));
            transition: all 0.4s;
        }

        .book-visual:hover img {
            filter: drop-shadow(0 0 60px rgba(0,212,255,0.4));
            transform: scale(1.03) rotate(-1deg);
        }

        .features-list {
            display: flex; flex-direction: column; gap: 24px;
        }

        .feature-item {
            display: flex; gap: 18px; align-items: flex-start;
            padding: 22px 24px;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            transition: all 0.3s;
            cursor: default;
        }

        .feature-item:hover {
            border-color: rgba(124,92,252,0.3);
            background: rgba(124,92,252,0.06);
            transform: translateX(6px);
        }

        .feature-ico {
            width: 42px; height: 42px; flex-shrink: 0;
            background: var(--purple-dim);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }

        .feature-ico img { width: 20px; height: 20px; filter: invert(1) brightness(2); }

        .feature-item h4 {
            font-family: 'Orbitron', sans-serif;
            font-size: 13px;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .feature-item p {
            font-size: 13px;
            color: var(--text-2);
            line-height: 1.6;
        }

        /* ===== LEADERBOARD PREVIEW ===== */
        #leaderboard-preview { padding: 80px 6%; }

        .lb-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
            max-width: 1200px;
            margin: 60px auto 0;
        }

        .lb-table {
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            backdrop-filter: blur(12px);
        }

        .lb-table-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }

        .lb-table-header span {
            font-size: 12px; text-transform: uppercase;
            letter-spacing: 1.5px; color: var(--text-2);
        }

        .lb-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s;
        }

        .lb-row:last-child { border-bottom: none; }

        .lb-row:hover {
            background: var(--purple-dim);
        }

        .lb-rank {
            font-family: 'Orbitron', sans-serif;
            font-size: 13px;
            font-weight: 700;
            width: 28px; text-align: center;
            color: var(--text-2);
        }

        .lb-rank.gold   { color: #FFD700; }
        .lb-rank.silver { color: #C0C0C0; }
        .lb-rank.bronze { color: #CD7F32; }

        .lb-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--grad);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700;
            flex-shrink: 0; overflow: hidden;
        }

        .lb-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .lb-name {
            flex: 1;
            font-size: 14px; font-weight: 600;
        }

        .lb-score {
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 700;
            background: var(--grad-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .lb-right-panel {
            display: flex; flex-direction: column; gap: 20px;
        }

        .lb-stat-card {
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px;
            text-align: center;
            backdrop-filter: blur(12px);
        }

        .lb-stat-card .big-num {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.2rem;
            font-weight: 900;
            background: var(--grad-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
            margin-bottom: 6px;
        }

        .lb-stat-card p { font-size: 13px; color: var(--text-2); }

        /* ===== CTA SECTION ===== */
        #cta-section {
            padding: 100px 6%;
            text-align: center;
            position: relative; overflow: hidden;
        }

        .cta-bg {
            position: absolute; inset: 0; z-index: 0;
            display: flex; align-items: center; justify-content: center;
            pointer-events: none;
        }

        .cta-orb-1 {
            position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(124,92,252,0.15), transparent 70%);
            border-radius: 50%;
        }

        .cta-orb-2 {
            position: absolute;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(0,212,255,0.12), transparent 70%);
            border-radius: 50%;
            right: 20%; top: 20%;
        }

        .floating-words {
            position: absolute; inset: 0;
            pointer-events: none;
        }

        .float-word {
            position: absolute;
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.04);
            animation: float-anim 8s ease-in-out infinite;
        }

        .float-word:nth-child(1) { top: 15%; left: 8%;  animation-delay: 0s;   font-size: 14px; }
        .float-word:nth-child(2) { top: 70%; left: 5%;  animation-delay: 1.5s; }
        .float-word:nth-child(3) { top: 30%; right: 8%; animation-delay: 3s;   font-size: 16px; }
        .float-word:nth-child(4) { top: 75%; right: 10%;animation-delay: 0.8s; }
        .float-word:nth-child(5) { top: 50%; left: 50%; animation-delay: 2s;   font-size: 18px; opacity: 0.02; }

        @keyframes float-anim {
            0%, 100% { transform: translateY(0px); opacity: 0.04; }
            50%       { transform: translateY(-20px); opacity: 0.08; }
        }

        .cta-inner {
            position: relative; z-index: 2;
            max-width: 600px; margin: 0 auto;
        }

        .cta-inner h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1.8rem, 3.5vw, 3rem);
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 16px;
        }

        .cta-inner h2 .grad-line {
            background: var(--grad-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .cta-inner p {
            font-size: 1rem;
            color: var(--text-2);
            margin-bottom: 40px;
            line-height: 1.7;
        }

        .btn-cta {
            display: inline-block;
            padding: 16px 48px;
            background: var(--grad);
            border-radius: 14px;
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            border: none; cursor: pointer;
            position: relative; overflow: hidden;
        }

        .btn-cta:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(124,92,252,0.5);
        }

        .btn-cta .btn-text-default { display: inline; }
        .btn-cta .btn-text-hover   { display: none; }

        .btn-cta:hover .btn-text-default { display: none; }
        .btn-cta:hover .btn-text-hover   { display: inline; }

        /* ===== FOOTER ===== */
        footer {
            border-top: 1px solid var(--border);
            padding: 40px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        footer .logo {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 700;
            background: var(--grad-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        footer p {
            font-size: 13px;
            color: var(--text-3);
        }

        footer nav a {
            font-size: 13px;
            color: var(--text-2);
            margin-left: 24px;
            transition: color 0.2s;
        }

        footer nav a:hover { color: var(--text-1); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 900px) {
            .hero-inner { grid-template-columns: 1fr; gap: 48px; padding-top: 120px; }
            .features-grid { grid-template-columns: 1fr; }
            .lb-grid { grid-template-columns: 1fr; }
            .featured-card { grid-template-columns: 1fr; }
            .story-right { text-align: left; }
            .welcome-strip { flex-direction: column; text-align: center; }
        }

        @media (max-width: 600px) {
            #navbar { padding: 16px 5%; }
            .nav-links a:not(.nav-btn) { display: none; }
            .section { padding: 60px 5%; }
        }
    </style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav id="navbar">
    <div class="nav-logo">StoryPulse</div>
    <div class="nav-links">
        <a href="stories.php">Stories</a>
        <a href="#how-it-works">How It Works</a>
        <a href="#leaderboard-preview">Leaderboard</a>
        <a href="logout.php" class="nav-btn">Logout</a>
    </div>
</nav>

<!-- ===== HERO ===== -->
<section id="hero">
    <div id="particles-js"></div>
    <div class="orb orb-purple"></div>
    <div class="orb orb-cyan"></div>

    <div class="hero-inner">
        <!-- Left: Copy -->
        <div class="hero-left" id="heroLeft">
            <div class="badge">
                <span class="badge-dot"></span>
                Prediction Platform
            </div>
            <h1>
                Read the Story.<br>
                <span class="grad-word">Predict</span> What<br>
                Happens Next.
            </h1>
            <p>
                Dive into captivating short stories, submit your prediction before the deadline, and see how close you were to the author's vision. Top predictors earn points and fame.
            </p>
            <div class="hero-cta-row">
                <a href="stories.php" class="btn-primary">Start Predicting</a>
                <a href="#how-it-works" class="btn-secondary">How It Works</a>
            </div>
        </div>

        <!-- Right: Story Fragment Card -->
        <div class="hero-right" id="heroRight">
            <div class="story-fragment">
                <div class="fragment-header">
                    <span class="fragment-tag">Story Fragment</span>
                    <div class="fragment-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>

                <div class="fragment-text">
                    The <span class="clue-word">laboratory</span> door creaked open.<br>
                    Aris stepped inside, fingers trembling.<br>
                    The <span class="clue-word">machine</span> suddenly <span class="clue-word">started</span>...<br>
                    A low hum filled the room.
                </div>

                <p class="fragment-question">What happens next?</p>

                <div class="prediction-hints" id="predHints">
                    <div class="hint-chip">💥 The machine overloads and explodes</div>
                    <div class="hint-chip">🤖 A hidden AI awakens inside it</div>
                    <div class="hint-chip">🚪 A secret chamber slowly opens</div>
                </div>

                <button class="fragment-action" id="heroBtn" onclick="toggleHints()">
                    Predict Next Event
                </button>
            </div>
        </div>
    </div>
</section>

<!-- ===== WELCOME STRIP ===== -->
<div style="padding: 0 6%;" id="welcomeStrip">
    <div class="welcome-strip">
        <div class="welcome-left">
            <h2>Welcome back, <span><?php echo htmlspecialchars($first_name); ?></span> 👋</h2>
            <p>Ready to test your intuition today?</p>
        </div>
        <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
            <div class="score-pill">
                <span class="score-icon">⚡</span>
                <div>
                    <div class="score-val"><?php echo htmlspecialchars($total_score); ?></div>
                    <div class="score-label">Total Score</div>
                </div>
            </div>
            <a href="stories.php" class="btn-primary">Browse Stories</a>
        </div>
    </div>
</div>

<!-- ===== FEATURED STORY ===== -->
<div class="section" id="featuredSection">
    <span class="section-label">Live Now</span>
    <h2 class="section-title">Featured Story</h2>
    <p class="section-sub">The most anticipated story waiting for your prediction right now.</p>

    <?php if ($featured_story): ?>
    <div class="featured-card">
        <img src="<?php echo htmlspecialchars($featured_story['cover_image_url']); ?>"
             alt="Cover" class="story-cover">

        <div class="story-meta">
            <h3><?php echo htmlspecialchars($featured_story['title']); ?> — Part <?php echo htmlspecialchars($featured_story['part_number']); ?></h3>
            <div class="story-author">By <?php echo htmlspecialchars($featured_story['created_by']); ?></div>
            <p class="story-desc"><?php echo nl2br(htmlspecialchars(mb_substr($featured_story['description'], 0, 220))); ?>...</p>
            <div class="parts-bar">
                <?php for ($i = 1; $i <= $featured_story['total_parts']; $i++):
                    $cls = ($i <= $featured_story['part_number']) ? 'filled' : ''; ?>
                    <div class="part-seg <?php echo $cls; ?>"></div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="story-right">
            <div class="status-badge">
                <span class="status-ping"></span> Active
            </div>
            <div class="countdown"><?php echo getTimeLeft($featured_story['prediction_deadline']); ?></div>
            <a href="story_detail.php?story_id=<?php echo $featured_story['story_id']; ?>&part_number=<?php echo $featured_story['part_number']; ?>"
               class="btn-primary" style="display:inline-block;">Read &amp; Predict</a>
        </div>
    </div>
    <?php else: ?>
    <div class="featured-card">
        <div class="no-story">No active stories right now — check back soon!</div>
    </div>
    <?php endif; ?>
</div>

<!-- ===== HOW IT WORKS ===== -->
<section id="how-it-works">
    <div class="hiw-header">
        <span class="section-label">The Process</span>
        <h2 class="section-title">How StoryPulse Works</h2>
        <p class="section-sub">Four simple steps stand between you and becoming the top predictor on the platform.</p>
    </div>

    <div class="steps-stack" id="stepsStack">
        <div class="step-card" data-step="1">
            <div class="step-top">
                <span class="step-num">STEP 01</span>
                <div class="step-icon">
                    <img src="assets/icons/book-open.svg" alt="Read">
                </div>
                <h3>Read the Story</h3>
            </div>
            <p>Immerse yourself in carefully crafted short stories. Each story is split into parts, released on a schedule — so every reader starts on equal footing with the same information.</p>
        </div>

        <div class="step-card" data-step="2">
            <div class="step-top">
                <span class="step-num">STEP 02</span>
                <div class="step-icon">
                    <img src="assets/icons/brain.svg" alt="Predict">
                </div>
                <h3>Predict What Happens Next</h3>
            </div>
            <p>Before the deadline closes, submit your prediction. What do you think the author wrote? Use every clue in the text — foreshadowing, tone, and character choices are all hints.</p>
        </div>

        <div class="step-card" data-step="3">
            <div class="step-top">
                <span class="step-num">STEP 03</span>
                <div class="step-icon">
                    <img src="assets/icons/eye.svg" alt="Reveal">
                </div>
                <h3>Reveal the Truth</h3>
            </div>
            <p>When the deadline passes, the actual continuation is revealed. See how the author truly continued the story — sometimes shocking, sometimes exactly what you suspected.</p>
        </div>

        <div class="step-card" data-step="4">
            <div class="step-top">
                <span class="step-num">STEP 04</span>
                <div class="step-icon">
                    <img src="assets/icons/trophy.svg" alt="Win">
                </div>
                <h3>Compare &amp; Climb the Ranks</h3>
            </div>
            <p>Your prediction is scored against the actual continuation. Earn points for accuracy, climb the leaderboard, and prove you have the sharpest intuition on the platform.</p>
        </div>
    </div>
</section>

<!-- ===== FEATURES ===== -->
<section id="features">
    <div style="text-align:center; margin-bottom:60px;">
        <span class="section-label">Platform Features</span>
        <h2 class="section-title">Built for Story Enthusiasts</h2>
        <p class="section-sub" style="margin:0 auto;">Everything you need to read, predict, compete, and grow.</p>
    </div>

    <div class="features-grid">
        <div class="book-visual" id="bookVisual">
            <img src="assets/images/reading-illustration.svg" alt="Reading illustration">
        </div>

        <div class="features-list">
            <div class="feature-item">
                <div class="feature-ico">
                    <img src="assets/icons/book-open.svg" alt="">
                </div>
                <div>
                    <h4>Interactive Stories</h4>
                    <p>Engaging multi-part stories designed specifically for prediction challenges. New parts drop on a schedule to keep everyone on equal footing.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-ico">
                    <img src="assets/icons/swords.svg" alt="">
                </div>
                <div>
                    <h4>Prediction Duel</h4>
                    <p>Challenge other readers head-to-head. Submit your prediction, then watch as scores are compared when the truth is revealed.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-ico">
                    <img src="assets/icons/brain.svg" alt="">
                </div>
                <div>
                    <h4>Accuracy Tracking</h4>
                    <p>Your prediction accuracy is tracked across every story. Watch your intuition sharpen over time with detailed score history.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-ico">
                    <img src="assets/icons/trophy.svg" alt="">
                </div>
                <div>
                    <h4>Live Leaderboard</h4>
                    <p>The top predictors on the platform are ranked in real time. Every correct prediction pushes you closer to the #1 spot.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== LEADERBOARD PREVIEW ===== -->
<section id="leaderboard-preview">
    <div style="text-align:center; margin-bottom:0;">
        <span class="section-label">Top Minds</span>
        <h2 class="section-title">Leaderboard</h2>
        <p class="section-sub" style="margin:0 auto 0;">The sharpest predictors on StoryPulse right now.</p>
    </div>

    <div class="lb-grid">
        <div class="lb-table">
            <div class="lb-table-header">
                <span>Rank</span>
                <span>Predictor</span>
                <span>Score</span>
            </div>

            <?php
            $rank_classes = ['gold', 'silver', 'bronze'];
            $rank_emojis  = ['🥇', '🥈', '🥉'];
            foreach ($leaderboard as $i => $user):
                $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'] ?? '', 0, 1));
            ?>
            <div class="lb-row">
                <div class="lb-rank <?php echo $rank_classes[$i]; ?>"><?php echo $rank_emojis[$i]; ?></div>
                <div class="lb-avatar">
                    <?php if ($user['profile_picture']): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="lb-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                <div class="lb-score"><?php echo number_format($user['total_score']); ?> pts</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="lb-right-panel">
            <div class="lb-stat-card">
                <span class="big-num"><?php echo count($leaderboard); ?>+</span>
                <p>Active Predictors</p>
            </div>
            <div class="lb-stat-card" style="background: linear-gradient(135deg, rgba(124,92,252,0.08), rgba(0,212,255,0.06)); border-color: rgba(124,92,252,0.2);">
                <p style="color:var(--text-2); font-size:14px; margin-bottom:16px;">Think you can crack the top 5?</p>
                <a href="stories.php" class="btn-primary" style="display:inline-block;">Start Predicting</a>
            </div>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<section id="cta-section">
    <div class="cta-bg">
        <div class="cta-orb-1"></div>
        <div class="cta-orb-2"></div>
    </div>
    <div class="floating-words">
        <span class="float-word">PREDICT</span>
        <span class="float-word">DISCOVER</span>
        <span class="float-word">ANALYZE</span>
        <span class="float-word">STORY</span>
        <span class="float-word">GUESS</span>
    </div>
    <div class="cta-inner" id="ctaInner">
        <h2>Do You Trust<br><span class="grad-line">Your Intuition?</span></h2>
        <p>The next story chapter is waiting. Submit your prediction before time runs out and see how your mind compares to the author's.</p>
        <a href="stories.php" class="btn-cta">
            <span class="btn-text-default">Start Predicting</span>
            <span class="btn-text-hover">Enter Story Lab →</span>
        </a>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer>
    <div class="logo">StoryPulse</div>
    <p>© <?php echo date('Y'); ?> StoryPulse. All rights reserved.</p>
    <nav>
        <a href="stories.php">Stories</a>
        <a href="#how-it-works">How It Works</a>
        <a href="#leaderboard-preview">Leaderboard</a>
        <a href="logout.php">Logout</a>
    </nav>
</footer>

<!-- ===== SCRIPTS ===== -->
<script>
/* --- Particles.js --- */
particlesJS('particles-js', {
    particles: {
        number: { value: 60, density: { enable: true, value_area: 900 } },
        color: { value: ['#7C5CFC', '#00D4FF'] },
        shape: { type: 'circle' },
        opacity: { value: 0.35, random: true, anim: { enable: true, speed: 0.8, opacity_min: 0.05 } },
        size: { value: 2.5, random: true },
        line_linked: {
            enable: true, distance: 140, color: '#7C5CFC',
            opacity: 0.1, width: 1
        },
        move: { enable: true, speed: 0.8, direction: 'none', random: true, out_mode: 'out' }
    },
    interactivity: {
        detect_on: 'canvas',
        events: { onhover: { enable: true, mode: 'grab' }, onclick: { enable: false } },
        modes: { grab: { distance: 120, line_linked: { opacity: 0.3 } } }
    },
    retina_detect: true
});

/* --- Navbar scroll --- */
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 50);
});

/* --- Hero prediction hints toggle --- */
function toggleHints() {
    const hints = document.getElementById('predHints');
    const btn   = document.getElementById('heroBtn');
    hints.classList.toggle('visible');
    btn.textContent = hints.classList.contains('visible') ? 'Start Reading →' : 'Predict Next Event';
}

/* --- GSAP Animations --- */
gsap.registerPlugin(ScrollTrigger);

// Hero entrance
gsap.from('#heroLeft > *', {
    duration: 0.9,
    y: 40, opacity: 0,
    stagger: 0.15,
    ease: 'power3.out',
    delay: 0.2
});

gsap.from('#heroRight', {
    duration: 1,
    x: 60, opacity: 0,
    ease: 'power3.out',
    delay: 0.5
});

// Welcome strip
gsap.from('#welcomeStrip', {
    scrollTrigger: { trigger: '#welcomeStrip', start: 'top 88%' },
    duration: 0.7, y: 30, opacity: 0, ease: 'power3.out'
});

// Featured story
gsap.from('#featuredSection .featured-card', {
    scrollTrigger: { trigger: '#featuredSection', start: 'top 82%' },
    duration: 0.9, y: 50, opacity: 0, ease: 'power3.out'
});

// Step cards — use IntersectionObserver for reliable reveal
const stepCards = document.querySelectorAll('.step-card');
stepCards.forEach(card => card.classList.add('will-animate'));

const stepObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const card = entry.target;
            const delay = parseInt(card.dataset.step || 1) * 100;
            setTimeout(() => card.classList.add('revealed'), delay);
            stepObserver.unobserve(card);
        }
    });
}, { threshold: 0.15 });

stepCards.forEach(card => stepObserver.observe(card));

// Book illustration
gsap.from('#bookVisual', {
    scrollTrigger: { trigger: '#features', start: 'top 80%' },
    duration: 1, x: -60, opacity: 0, ease: 'power3.out'
});

// Feature items
gsap.from('.feature-item', {
    scrollTrigger: { trigger: '.features-list', start: 'top 82%' },
    duration: 0.6, x: 40, opacity: 0,
    stagger: 0.12, ease: 'power3.out'
});

// Leaderboard rows
gsap.from('.lb-row', {
    scrollTrigger: { trigger: '.lb-table', start: 'top 85%' },
    duration: 0.5, x: -30, opacity: 0,
    stagger: 0.08, ease: 'power3.out'
});

// CTA
gsap.from('#ctaInner > *', {
    scrollTrigger: { trigger: '#cta-section', start: 'top 80%' },
    duration: 0.8, y: 30, opacity: 0,
    stagger: 0.15, ease: 'power3.out'
});
</script>
</body>
</html>