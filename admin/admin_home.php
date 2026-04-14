<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../admin_login.php');
    exit;
}
require_once '../db_connect.php';

// ── KPI Stats ─────────────────────────────────────────────────────────
$stats = [];

// Readers (user_type column)
$stats['users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'reader'")->fetchColumn();

// Authors
$stats['authors'] = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'author'")->fetchColumn();

// Total approved stories
$stats['stories'] = $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'approved'")->fetchColumn();

// Pending: stories OR parts awaiting approval (combined)
$pending_stories = $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'pending'")->fetchColumn();
$pending_parts   = $pdo->query("SELECT COUNT(*) FROM story_parts WHERE status = 'pending'")->fetchColumn();
$stats['pending'] = $pending_stories + $pending_parts;
$stats['pending_stories'] = $pending_stories;
$stats['pending_parts']   = $pending_parts;

// Manually flagged comments (spam that ML missed — never auto-stored)
$stats['flagged'] = $pdo->query("SELECT COUNT(*) FROM comments WHERE manually_flagged = 1")->fetchColumn();

// ── Recent Activity ───────────────────────────────────────────────────
$activity = [];
try {
    $activity = $pdo->query("
        SELECT action_text, action_type, created_at
        FROM admin_activity_log
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* table may not exist yet */ }

// ── Active Announcement ───────────────────────────────────────────────
$announcement = null;
try {
    $announcement = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── ML API Status ─────────────────────────────────────────────────────
$ml_status = 'offline';
$ch = curl_init('http://127.0.0.1:8000/');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
curl_exec($ch);
$ml_status = curl_errno($ch) === 0 ? 'online' : 'offline';
curl_close($ch);

// ── Chart: last 7-day user registrations ─────────────────────────────
$chart_rows   = $pdo->query("
    SELECT DATE(created_at) as day, COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = json_encode(array_column($chart_rows, 'day'));
$chart_values = json_encode(array_column($chart_rows, 'count'));

$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$today      = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — StoryVerse</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>

:root {
    --sidebar-bg: #0B1120;
    --sidebar-w: 240px;
    --sidebar-collapsed: 64px;
    --topbar-h: 56px;

    --bg: #F4F6FB;
    --card-bg: #ffffff;
    --card-shadow: 0 2px 16px rgba(15,20,50,0.07);
    --card-radius: 12px;
    --card-border: #EEF0F7;

    --text-primary: #1A1D2E;
    --text-secondary: #6B7280;
    --text-muted: #9CA3AF;

    --gold: #F5A623;
    --gold-dark: #E8920F;
    --gold-glow: rgba(245,166,35,0.18);

    --azure: #3B82F6;
    --success: #10B981;
    --danger: #EF4444;
    --warning: #F59E0B;
    --purple: #8B5CF6;

    --border: #E8EAF0;
    --divider: #F3F4F8;

    --tr: 0.26s cubic-bezier(0.4,0,0.2,1);
    --font-display: 'Cinzel Decorative', serif;
    --font-body: 'Rajdhani', sans-serif;
    --font-mono: 'Space Mono', monospace;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: var(--font-body);
    background: var(--bg);
    color: var(--text-primary);
    min-height: 100vh;
    display: flex;
    overflow-x: hidden;
}

/* ═══════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════ */
.sidebar {
    width: var(--sidebar-w);
    min-height: 100vh;
    background: var(--sidebar-bg);
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0; top: 0; bottom: 0;
    z-index: 100;
    transition: width var(--tr);
    overflow: hidden;
}

.sidebar.collapsed { width: var(--sidebar-collapsed); }

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 20px;
    height: var(--topbar-h);
    border-bottom: 1px solid rgba(255,255,255,0.05);
    flex-shrink: 0;
    overflow: hidden;
}

.brand-mark {
    width: 28px; height: 28px;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-family: var(--font-display);
    font-size: 13px; color: #fff; font-weight: 700;
}

.brand-name {
    font-family: var(--font-display);
    font-size: 12px; color: #fff;
    white-space: nowrap;
    transition: opacity var(--tr);
}

.sidebar.collapsed .brand-name { opacity: 0; pointer-events: none; }

.sidebar-nav {
    flex: 1;
    padding: 12px 0;
    overflow-y: auto; overflow-x: hidden;
}

.sidebar-nav::-webkit-scrollbar { width: 3px; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 99px; }

.nav-section-label {
    font-family: var(--font-mono);
    font-size: 9px; letter-spacing: 0.12em;
    color: rgba(255,255,255,0.28);
    padding: 14px 20px 6px;
    text-transform: uppercase;
    white-space: nowrap;
    transition: opacity var(--tr);
}

.sidebar.collapsed .nav-section-label { opacity: 0; }

.nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 20px;
    color: rgba(255,255,255,0.72);
    text-decoration: none;
    font-family: var(--font-body);
    font-size: 14px; font-weight: 500;
    white-space: nowrap;
    position: relative;
    transition: color 0.18s, background 0.18s;
    border-left: 3px solid transparent;
}

.nav-item:hover { color: #fff; background: rgba(255,255,255,0.05); }

.nav-item.active {
    color: var(--gold);
    background: linear-gradient(90deg, rgba(245,166,35,0.12) 0%, transparent 100%);
    border-left-color: var(--gold);
}

.nav-item svg {
    width: 18px; height: 18px; flex-shrink: 0;
    stroke: currentColor; fill: none;
    stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
}

.nav-label { transition: opacity var(--tr); }
.sidebar.collapsed .nav-label { opacity: 0; }

/* Badge on nav items (pending count) */
.nav-badge {
    margin-left: auto;
    background: var(--danger);
    color: #fff;
    font-family: var(--font-mono);
    font-size: 10px; font-weight: 700;
    padding: 1px 6px;
    border-radius: 99px;
    flex-shrink: 0;
    transition: opacity var(--tr);
}

.sidebar.collapsed .nav-badge { opacity: 0; }

.nav-item .tooltip {
    position: absolute;
    left: calc(var(--sidebar-collapsed) + 8px);
    background: #1E2A45; color: #fff;
    padding: 5px 10px; border-radius: 6px;
    font-size: 12px; font-family: var(--font-body);
    white-space: nowrap; pointer-events: none;
    opacity: 0; transform: translateX(-4px);
    transition: opacity 0.15s, transform 0.15s;
    z-index: 200;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.sidebar.collapsed .nav-item:hover .tooltip { opacity: 1; transform: translateX(0); }

.sidebar-bottom {
    padding: 12px 12px 16px;
    border-top: 1px solid rgba(255,255,255,0.05);
}

.logout-btn {
    display: flex; align-items: center; gap: 10px;
    width: 100%; padding: 9px 8px;
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.18);
    border-radius: 8px;
    color: #F87171; font-family: var(--font-body);
    font-size: 13px; font-weight: 600;
    cursor: pointer; white-space: nowrap; overflow: hidden;
    transition: background 0.18s;
    text-decoration: none;
}

.logout-btn:hover { background: rgba(239,68,68,0.16); color: #FCA5A5; }

.logout-btn svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; flex-shrink: 0; }

.logout-text { transition: opacity var(--tr); }
.sidebar.collapsed .logout-text { opacity: 0; }

/* ═══════════════════════════════════════════
   MAIN WRAPPER
═══════════════════════════════════════════ */
.main-wrapper {
    margin-left: var(--sidebar-w);
    width: calc(100% - var(--sidebar-w));
    min-height: 100vh;
    display: flex; flex-direction: column;
    transition: margin-left var(--tr), width var(--tr);
}

.main-wrapper.expanded {
    margin-left: var(--sidebar-collapsed);
    width: calc(100% - var(--sidebar-collapsed));
}

/* ═══════════════════════════════════════════
   TOP BAR
═══════════════════════════════════════════ */
.topbar {
    height: var(--topbar-h);
    background: var(--card-bg);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center;
    padding: 0 24px; gap: 16px;
    position: sticky; top: 0; z-index: 50;
}

.hamburger {
    width: 36px; height: 36px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 5px; background: none; border: none;
    cursor: pointer; border-radius: 8px;
    transition: background 0.15s; flex-shrink: 0;
}

.hamburger:hover { background: var(--divider); }

.hamburger span {
    display: block; width: 18px; height: 1.8px;
    background: var(--text-primary); border-radius: 2px;
    transition: transform 0.25s, opacity 0.25s, width 0.25s;
    transform-origin: center;
}

.hamburger.active span:nth-child(1) { transform: translateY(6.8px) rotate(45deg); }
.hamburger.active span:nth-child(2) { opacity: 0; width: 0; }
.hamburger.active span:nth-child(3) { transform: translateY(-6.8px) rotate(-45deg); }

.topbar-search {
    display: flex; align-items: center; gap: 8px;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: 8px; padding: 7px 12px;
    flex: 1; max-width: 320px;
    transition: border-color 0.18s, box-shadow 0.18s;
}

.topbar-search:focus-within {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--gold-glow);
}

.topbar-search svg { width: 15px; height: 15px; stroke: var(--text-muted); fill: none; stroke-width: 2; }
.topbar-search input { border: none; background: none; outline: none; font-family: var(--font-body); font-size: 14px; color: var(--text-primary); width: 100%; }
.topbar-search input::placeholder { color: var(--text-muted); }

.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 12px; }

.ml-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 99px;
    font-family: var(--font-mono); font-size: 11px; font-weight: 700;
    letter-spacing: 0.04em; border: 1px solid;
}

.ml-pill.online  { background: #ECFDF5; color: var(--success); border-color: rgba(16,185,129,0.2); }
.ml-pill.offline { background: #FEF2F2; color: var(--danger);  border-color: rgba(239,68,68,0.2); }

.ml-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:0.4} }

.topbar-date { font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); white-space: nowrap; }

/* ═══════════════════════════════════════════
   PAGE CONTENT
═══════════════════════════════════════════ */
.page-content { padding: 28px 28px 48px; flex: 1; }

.page-header { margin-bottom: 24px; }

.page-header h1 {
    font-family: var(--font-display);
    font-size: 22px; color: var(--text-primary);
    margin-bottom: 4px;
}

.page-header p { font-size: 14px; color: var(--text-secondary); }

/* Announcement preview */
.ann-preview {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 18px; border-radius: 10px;
    margin-bottom: 22px;
    font-family: var(--font-body); font-size: 14px; font-weight: 600;
    border: 1px solid;
}

.ann-preview.info    { background: linear-gradient(135deg,#EFF6FF,#DBEAFE); border-color: rgba(59,130,246,0.25); color: #1D4ED8; }
.ann-preview.warning { background: linear-gradient(135deg,#FFFBEB,#FEF3C7); border-color: rgba(245,158,11,0.3);   color: #92400E; }
.ann-preview.success { background: linear-gradient(135deg,#ECFDF5,#D1FAE5); border-color: rgba(16,185,129,0.25); color: #065F46; }

.ann-badge {
    font-family: var(--font-mono); font-size: 9px;
    padding: 2px 7px; border-radius: 4px;
    background: rgba(0,0,0,0.12);
    letter-spacing: 0.08em; flex-shrink: 0;
}

.ann-manage {
    margin-left: auto; font-size: 12px; font-weight: 600;
    color: currentColor; opacity: 0.7;
    text-decoration: none; padding: 3px 10px;
    border: 1px solid currentColor; border-radius: 5px;
    white-space: nowrap; transition: opacity 0.15s;
}
.ann-manage:hover { opacity: 1; }

/* ── Pending sub-info banner ── */
.pending-split {
    display: flex; align-items: center; gap: 16px;
    background: #FFFBEB; border: 1px solid #FCD34D;
    border-radius: 10px; padding: 10px 16px;
    margin-bottom: 22px;
    font-size: 13px; font-weight: 600; color: #92400E;
}

.pending-split a {
    color: #92400E; text-decoration: underline;
    text-underline-offset: 2px;
}

/* ═══════════════════════════════════════════
   KPI CARDS
═══════════════════════════════════════════ */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px; margin-bottom: 22px;
}

.kpi-card {
    background: var(--card-bg);
    border-radius: var(--card-radius);
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    padding: 20px 20px 18px;
    position: relative; overflow: hidden;
    transition: transform 0.18s, box-shadow 0.18s;
    cursor: default;
}

.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(15,20,50,0.11); }

.kpi-card::before {
    content: ''; position: absolute;
    top: 0; left: 0; right: 0; height: 3px;
    border-radius: var(--card-radius) var(--card-radius) 0 0;
}

.kpi-card.c-blue::before   { background: linear-gradient(90deg,#3B82F6,#60A5FA); }
.kpi-card.c-purple::before { background: linear-gradient(90deg,#8B5CF6,#A78BFA); }
.kpi-card.c-gold::before   { background: linear-gradient(90deg,#F5A623,#FBD380); }
.kpi-card.c-orange::before { background: linear-gradient(90deg,#F59E0B,#FCD34D); }
.kpi-card.c-red::before    { background: linear-gradient(90deg,#EF4444,#FCA5A5); }

.kpi-icon {
    width: 38px; height: 38px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 14px;
}

.kpi-card.c-blue   .kpi-icon { background: #EFF6FF; }
.kpi-card.c-purple .kpi-icon { background: #F5F3FF; }
.kpi-card.c-gold   .kpi-icon { background: rgba(245,166,35,0.1); }
.kpi-card.c-orange .kpi-icon { background: #FFFBEB; }
.kpi-card.c-red    .kpi-icon { background: #FEF2F2; }

.kpi-icon svg { width: 18px; height: 18px; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; fill: none; }

.kpi-card.c-blue   .kpi-icon svg { stroke: #3B82F6; }
.kpi-card.c-purple .kpi-icon svg { stroke: #8B5CF6; }
.kpi-card.c-gold   .kpi-icon svg { stroke: var(--gold); }
.kpi-card.c-orange .kpi-icon svg { stroke: var(--warning); }
.kpi-card.c-red    .kpi-icon svg { stroke: var(--danger); }

.kpi-label {
    font-family: var(--font-mono); font-size: 10px;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--text-muted); margin-bottom: 6px;
}

.kpi-value {
    font-family: var(--font-body); font-size: 32px;
    font-weight: 700; color: var(--text-primary);
    line-height: 1; margin-bottom: 8px;
}

.kpi-sub { font-size: 12px; font-weight: 600; color: var(--text-muted); }
.kpi-sub.warn { color: var(--warning); }
.kpi-sub.danger { color: var(--danger); }

/* ═══════════════════════════════════════════
   CARDS
═══════════════════════════════════════════ */
.card {
    background: var(--card-bg);
    border-radius: var(--card-radius);
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.card-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px 14px;
    border-bottom: 1px solid var(--divider);
}

.card-title {
    font-family: var(--font-body); font-size: 14px;
    font-weight: 700; color: var(--text-primary);
    text-transform: uppercase; letter-spacing: 0.06em;
}

.card-body { padding: 20px 22px; }

/* Chart toggle */
.chart-toggle { display: flex; background: var(--divider); border-radius: 8px; padding: 3px; gap: 2px; }
.chart-toggle button {
    padding: 4px 12px; border-radius: 6px; border: none;
    background: none; font-family: var(--font-body);
    font-size: 12px; font-weight: 600;
    color: var(--text-muted); cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.chart-toggle button.active { background: var(--card-bg); color: var(--text-primary); box-shadow: 0 1px 4px rgba(0,0,0,0.08); }

.chart-wrap {
    background: linear-gradient(135deg,#F0F4FF 0%,#F7F8FC 100%);
    border-radius: 8px; padding: 16px;
}

/* Mid + Bottom grids */
.mid-grid    { display: grid; grid-template-columns: 1fr 320px; gap: 20px; margin-bottom: 20px; }
.bottom-grid { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }

/* Quick actions */
.quick-actions { display: flex; flex-direction: column; gap: 10px; padding: 16px 18px; }

.qa-btn {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 14px; border-radius: 9px;
    border: 1px solid var(--border); background: var(--card-bg);
    cursor: pointer; text-decoration: none;
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
    font-family: var(--font-body); color: var(--text-primary);
}

.qa-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(15,20,50,0.09); border-color: var(--gold); }
.qa-btn:hover .qa-icon { background: var(--gold); }
.qa-btn:hover .qa-icon svg { stroke: #fff; }

.qa-icon {
    width: 34px; height: 34px; border-radius: 8px;
    background: var(--divider);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: background 0.15s;
}

.qa-icon svg { width: 15px; height: 15px; stroke: var(--text-secondary); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; transition: stroke 0.15s; }

.qa-text  { font-size: 13px; font-weight: 600; color: var(--text-primary); line-height: 1.2; }
.qa-sub   { font-size: 11px; color: var(--text-muted); font-weight: 400; }

/* Pending badge on quick action */
.qa-pending-badge {
    margin-left: auto;
    background: #FEF3C7; color: #92400E;
    border: 1px solid #FCD34D;
    font-family: var(--font-mono); font-size: 10px; font-weight: 700;
    padding: 2px 8px; border-radius: 99px; flex-shrink: 0;
}

/* Activity feed */
.activity-list { list-style: none; padding: 4px 22px 18px; }

.activity-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 0; border-bottom: 1px solid var(--divider);
}

.activity-item:last-child { border-bottom: none; }

.activity-dot {
    width: 8px; height: 8px; border-radius: 50%;
    flex-shrink: 0; margin-top: 5px;
}

.activity-dot.green { background: var(--success); }
.activity-dot.red   { background: var(--danger); }
.activity-dot.blue  { background: var(--azure); }
.activity-dot.gold  { background: var(--gold); }

.activity-text { font-size: 13.5px; font-weight: 500; color: var(--text-primary); flex: 1; line-height: 1.4; }
.activity-time { font-family: var(--font-mono); font-size: 10px; color: var(--text-muted); white-space: nowrap; margin-top: 2px; }

.activity-empty { padding: 24px 22px; font-size: 13px; color: var(--text-muted); font-style: italic; }

.card-footer { padding: 12px 22px; border-top: 1px solid var(--divider); }
.view-all-link { font-size: 12px; font-weight: 600; color: var(--gold); text-decoration: none; letter-spacing: 0.04em; }
.view-all-link:hover { opacity: 0.75; }

/* ML card */
.ml-status-card { padding: 22px; }
.ml-badge { font-family: var(--font-mono); font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 99px; letter-spacing: 0.06em; }
.ml-badge.online  { background: #ECFDF5; color: var(--success); }
.ml-badge.offline { background: #FEF2F2; color: var(--danger); }

.ml-endpoint { font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); background: var(--divider); padding: 8px 12px; border-radius: 7px; margin-bottom: 16px; word-break: break-all; }

.ml-stat-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid var(--divider); }
.ml-stat-row:last-child { border-bottom: none; }
.ml-stat-label { font-size: 12.5px; font-weight: 600; color: var(--text-secondary); }
.ml-stat-val   { font-family: var(--font-mono); font-size: 12px; color: var(--text-primary); font-weight: 700; }

.ml-ping-btn {
    margin-top: 16px; width: 100%; padding: 9px;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    border: none; border-radius: 8px; color: #fff;
    font-family: var(--font-body); font-size: 13px; font-weight: 700;
    cursor: pointer; transition: opacity 0.15s, transform 0.15s;
}

.ml-ping-btn:hover { opacity: 0.9; transform: translateY(-1px); }

/* Toast */
.toast-container { position: fixed; bottom: 28px; right: 28px; z-index: 999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }

.toast {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px; background: #1A1D2E; color: #fff;
    border-radius: 10px; font-family: var(--font-body);
    font-size: 13.5px; font-weight: 600;
    box-shadow: 0 8px 28px rgba(0,0,0,0.2);
    animation: toast-in 0.3s cubic-bezier(0.34,1.56,0.64,1) forwards;
    pointer-events: all; border-left: 3px solid var(--gold);
}

@keyframes toast-in { from{opacity:0;transform:translateY(16px) scale(0.96)} to{opacity:1;transform:translateY(0) scale(1)} }

.toast.fade-out { animation: toast-out 0.25s ease forwards; }
@keyframes toast-out { to{opacity:0;transform:translateY(8px) scale(0.97)} }

/* Animations */
.fade-up { opacity:0; transform:translateY(14px); animation: fade-up-in 0.4s ease forwards; }
@keyframes fade-up-in { to{opacity:1;transform:translateY(0)} }

.kpi-card:nth-child(1){animation-delay:0.05s}
.kpi-card:nth-child(2){animation-delay:0.10s}
.kpi-card:nth-child(3){animation-delay:0.15s}
.kpi-card:nth-child(4){animation-delay:0.20s}
.kpi-card:nth-child(5){animation-delay:0.25s}

/* Responsive */
@media(max-width:1200px){ .kpi-grid{grid-template-columns:repeat(3,1fr)} }
@media(max-width:900px){ .mid-grid,.bottom-grid{grid-template-columns:1fr} .kpi-grid{grid-template-columns:repeat(2,1fr)} }
@media(max-width:600px){ .page-content{padding:16px 14px 40px} .kpi-grid{grid-template-columns:1fr 1fr} .topbar-date{display:none} .topbar-search{max-width:160px} }
</style>
</head>
<body>

<!-- ═══ SIDEBAR ══════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">S</div>
        <span class="brand-name">StoryVerse</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>

        <a href="admin_home.php" class="nav-item active">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <span class="nav-label">Dashboard</span>
            <span class="tooltip">Dashboard</span>
        </a>

        <div class="nav-section-label">Manage</div>

        <a href="admin_users.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="nav-label">Users</span>
            <span class="tooltip">Users</span>
        </a>

        <a href="admin_stories.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <span class="nav-label">Stories</span>
            <?php if ($stats['pending'] > 0): ?>
                <span class="nav-badge"><?= $stats['pending'] ?></span>
            <?php endif; ?>
            <span class="tooltip">Stories<?= $stats['pending'] > 0 ? ' ('.$stats['pending'].' pending)' : '' ?></span>
        </a>

        <a href="admin_games.php" class="nav-item">
            <svg viewBox="0 0 24 24"><line x1="6" y1="12" x2="18" y2="12"/><line x1="12" y1="6" x2="12" y2="18"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>
            <span class="nav-label">Games</span>
            <span class="tooltip">Games</span>
        </a>

        <a href="admin_dataset.php" class="nav-item">
            <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            <span class="nav-label">Dataset & ML</span>
            <span class="tooltip">Dataset & ML</span>
        </a>

        <a href="admin_comments.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="nav-label">Comments</span>
            <?php if ($stats['flagged'] > 0): ?>
                <span class="nav-badge"><?= $stats['flagged'] ?></span>
            <?php endif; ?>
            <span class="tooltip">Comments<?= $stats['flagged'] > 0 ? ' ('.$stats['flagged'].' flagged)' : '' ?></span>
        </a>

        <a href="admin_leaderboard.php" class="nav-item">
            <svg viewBox="0 0 24 24"><polyline points="18 20 18 10"/><polyline points="12 20 12 4"/><polyline points="6 20 6 14"/></svg>
            <span class="nav-label">Leaderboard</span>
            <span class="tooltip">Leaderboard</span>
        </a>

        <a href="admin_announcement.php" class="nav-item">
            <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            <span class="nav-label">Announcement</span>
            <span class="tooltip">Announcement</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a href="admin_logout.php" class="logout-btn">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="logout-text">Log Out</span>
        </a>
    </div>
</aside>

<!-- ═══ MAIN WRAPPER ═════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">

    <!-- TOP BAR -->
    <header class="topbar">
        <button class="hamburger" id="hamburger" aria-label="Toggle sidebar">
            <span></span><span></span><span></span>
        </button>

        <div class="topbar-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search users, stories..." id="globalSearch">
        </div>

        <div class="topbar-right">
            <div class="ml-pill <?= $ml_status ?>">
                <span class="ml-dot"></span>
                ML API &nbsp;<?= strtoupper($ml_status) ?>
            </div>
            <span class="topbar-date"><?= $today ?></span>
        </div>
    </header>

    <!-- PAGE CONTENT -->
    <main class="page-content">

        <!-- Page header -->
        <div class="page-header fade-up">
            <h1>Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($admin_name) ?>. Here is what is happening on StoryVerse.</p>
        </div>

        <!-- Active announcement preview -->
        <?php if ($announcement): ?>
        <div class="ann-preview <?= htmlspecialchars($announcement['type']) ?> fade-up">
            <span class="ann-badge">LIVE BANNER</span>
            <?= htmlspecialchars($announcement['message']) ?>
            <a href="admin_announcement.php" class="ann-manage">Manage</a>
        </div>
        <?php endif; ?>

        <!-- Pending breakdown notice (only shown when there are pending items) -->
        <?php if ($stats['pending'] > 0): ?>
        <div class="pending-split fade-up">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?php if ($stats['pending_stories'] > 0 && $stats['pending_parts'] > 0): ?>
                <?= $stats['pending_stories'] ?> pending <?= $stats['pending_stories'] === 1 ? 'story' : 'stories' ?>
                and <?= $stats['pending_parts'] ?> pending <?= $stats['pending_parts'] === 1 ? 'part' : 'parts' ?> need review.
            <?php elseif ($stats['pending_stories'] > 0): ?>
                <?= $stats['pending_stories'] ?> pending <?= $stats['pending_stories'] === 1 ? 'story' : 'stories' ?> need review.
            <?php else: ?>
                <?= $stats['pending_parts'] ?> pending <?= $stats['pending_parts'] === 1 ? 'part' : 'parts' ?> need review.
            <?php endif; ?>
            &nbsp;<a href="admin_stories.php?filter=pending">Review now</a>
        </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="kpi-grid">

            <div class="kpi-card c-blue fade-up">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div class="kpi-label">Readers</div>
                <div class="kpi-value"><?= number_format($stats['users']) ?></div>
                <div class="kpi-sub">Registered users</div>
            </div>

            <div class="kpi-card c-purple fade-up">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </div>
                <div class="kpi-label">Authors</div>
                <div class="kpi-value"><?= number_format($stats['authors']) ?></div>
                <div class="kpi-sub">Active storytellers</div>
            </div>

            <div class="kpi-card c-gold fade-up">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </div>
                <div class="kpi-label">Stories</div>
                <div class="kpi-value"><?= number_format($stats['stories']) ?></div>
                <div class="kpi-sub">Live &amp; approved</div>
            </div>

            <div class="kpi-card c-orange fade-up">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="kpi-label">Pending Review</div>
                <div class="kpi-value"><?= number_format($stats['pending']) ?></div>
                <div class="kpi-sub <?= $stats['pending'] > 0 ? 'warn' : '' ?>">
                    <?php if ($stats['pending'] > 0): ?>
                        <?= $stats['pending_stories'] ?> stories &middot; <?= $stats['pending_parts'] ?> parts
                    <?php else: ?>
                        All clear
                    <?php endif; ?>
                </div>
            </div>

            <div class="kpi-card c-red fade-up">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div class="kpi-label">Flagged Comments</div>
                <div class="kpi-value"><?= number_format($stats['flagged']) ?></div>
                <div class="kpi-sub <?= $stats['flagged'] > 0 ? 'danger' : '' ?>">
                    <?= $stats['flagged'] > 0 ? 'Manually flagged' : 'None flagged' ?>
                </div>
            </div>

        </div><!-- /kpi-grid -->

        <!-- Mid grid: Chart + Quick Actions -->
        <div class="mid-grid fade-up" style="animation-delay:0.3s">

            <div class="card">
                <div class="card-header">
                    <span class="card-title">Platform Activity</span>
                    <div class="chart-toggle">
                        <button class="active" id="btnUsers" onclick="switchChart('users')">User Growth</button>
                        <button id="btnStories" onclick="switchChart('stories')">Story Activity</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-wrap">
                        <canvas id="mainChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">Quick Actions</span>
                </div>
                <div class="quick-actions">

                    <a href="admin_users.php?action=add" class="qa-btn">
                        <div class="qa-icon">
                            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        </div>
                        <div>
                            <div class="qa-text">Add User</div>
                            <div class="qa-sub">Create reader or author</div>
                        </div>
                    </a>

                    <a href="admin_stories.php?filter=pending" class="qa-btn">
                        <div class="qa-icon">
                            <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        </div>
                        <div>
                            <div class="qa-text">Review Submissions</div>
                            <div class="qa-sub">Stories &amp; parts</div>
                        </div>
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="qa-pending-badge"><?= $stats['pending'] ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="admin_games.php" class="qa-btn">
                        <div class="qa-icon">
                            <svg viewBox="0 0 24 24"><line x1="6" y1="12" x2="18" y2="12"/><line x1="12" y1="6" x2="12" y2="18"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>
                        </div>
                        <div>
                            <div class="qa-text">Add Game Data</div>
                            <div class="qa-sub">Who Said It / Flash Words</div>
                        </div>
                    </a>

                    <a href="admin_comments.php?filter=flagged" class="qa-btn">
                        <div class="qa-icon">
                            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div>
                            <div class="qa-text">Flagged Comments</div>
                            <div class="qa-sub">Admin-marked as spam</div>
                        </div>
                        <?php if ($stats['flagged'] > 0): ?>
                            <span class="qa-pending-badge" style="background:#FEE2E2;color:#991B1B;border-color:#FCA5A5"><?= $stats['flagged'] ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="admin_announcement.php" class="qa-btn">
                        <div class="qa-icon">
                            <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        </div>
                        <div>
                            <div class="qa-text">Post Announcement</div>
                            <div class="qa-sub">Broadcast to all users</div>
                        </div>
                    </a>

                </div>
            </div>
        </div>

        <!-- Bottom grid: Activity + ML Status -->
        <div class="bottom-grid fade-up" style="animation-delay:0.4s">

            <div class="card">
                <div class="card-header">
                    <span class="card-title">Recent Activity</span>
                </div>

                <?php if (empty($activity)): ?>
                    <div class="activity-empty">No recent activity recorded yet.</div>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($activity as $log):
                            $dot = match($log['action_type']) {
                                'approve' => 'green',
                                'delete'  => 'red',
                                'update'  => 'blue',
                                default   => 'gold'
                            };
                            $ago = time() - strtotime($log['created_at']);
                            $ts  = $ago < 60 ? 'Just now'
                                 : ($ago < 3600  ? round($ago/60).'m ago'
                                 : ($ago < 86400 ? round($ago/3600).'h ago'
                                 : date('M j', strtotime($log['created_at']))));
                        ?>
                        <li class="activity-item">
                            <span class="activity-dot <?= $dot ?>"></span>
                            <span class="activity-text"><?= htmlspecialchars($log['action_text']) ?></span>
                            <span class="activity-time"><?= $ts ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="card-footer">
                        <a href="admin_activity.php" class="view-all-link">View all activity</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">ML API Status</span>
                    <span class="ml-badge <?= $ml_status ?>"><?= strtoupper($ml_status) ?></span>
                </div>
                <div class="ml-status-card">
                    <div class="ml-endpoint">http://127.0.0.1:8000</div>
                    <div class="ml-stat-row">
                        <span class="ml-stat-label">Evaluate Endpoint</span>
                        <span class="ml-stat-val">/evaluate-story</span>
                    </div>
                    <div class="ml-stat-row">
                        <span class="ml-stat-label">Spam Endpoint</span>
                        <span class="ml-stat-val">/detect-spam</span>
                    </div>
                    <div class="ml-stat-row">
                        <span class="ml-stat-label">Response</span>
                        <span class="ml-stat-val" style="color:<?= $ml_status === 'online' ? 'var(--success)' : 'var(--danger)' ?>">
                            <?= $ml_status === 'online' ? '200 OK' : 'Unreachable' ?>
                        </span>
                    </div>
                    <div class="ml-stat-row">
                        <span class="ml-stat-label">Spam Note</span>
                        <span class="ml-stat-val" style="color:var(--text-muted);font-size:10px">Blocked before DB insert</span>
                    </div>
                    <div class="ml-stat-row">
                        <span class="ml-stat-label">Checked At</span>
                        <span class="ml-stat-val"><?= date('H:i:s') ?></span>
                    </div>
                    <button class="ml-ping-btn" onclick="pingML()">Re-check API</button>
                </div>
            </div>

        </div>

    </main>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// Sidebar toggle
const sidebar     = document.getElementById('sidebar');
const mainWrapper = document.getElementById('mainWrapper');
const hamburger   = document.getElementById('hamburger');

hamburger.addEventListener('click', () => {
    const collapsed = sidebar.classList.toggle('collapsed');
    mainWrapper.classList.toggle('expanded', collapsed);
    hamburger.classList.toggle('active', collapsed);
    localStorage.setItem('sv_sidebar', collapsed ? '1' : '0');
});

if (localStorage.getItem('sv_sidebar') === '1') {
    sidebar.classList.add('collapsed');
    mainWrapper.classList.add('expanded');
    hamburger.classList.add('active');
}

// Toast
function showToast(msg, type = 'default') {
    const tc = document.getElementById('toastContainer');
    const t  = document.createElement('div');
    t.className = 'toast';
    t.style.borderLeftColor = type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--gold)';
    t.textContent = msg;
    tc.appendChild(t);
    setTimeout(() => { t.classList.add('fade-out'); setTimeout(() => t.remove(), 280); }, 3200);
}

// Chart
const userLabels = <?= $chart_labels ?>;
const userValues = <?= $chart_values ?>;

const ctx = document.getElementById('mainChart').getContext('2d');
const mainChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: userLabels.length ? userLabels : ['No data'],
        datasets: [{
            label: 'New Users',
            data: userValues.length ? userValues : [0],
            borderColor: '#F5A623',
            backgroundColor: 'rgba(245,166,35,0.08)',
            borderWidth: 2.5,
            pointBackgroundColor: '#F5A623',
            pointRadius: 4, pointHoverRadius: 6,
            tension: 0.42, fill: true
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1A1D2E',
                titleFont: { family: 'Space Mono', size: 11 },
                bodyFont: { family: 'Rajdhani', size: 13, weight: '600' },
                padding: 10, cornerRadius: 8, displayColors: false
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { family: 'Space Mono', size: 10 }, color: '#9CA3AF' } },
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { family: 'Space Mono', size: 10 }, color: '#9CA3AF', stepSize: 1 } }
        }
    }
});

function switchChart(type) {
    document.getElementById('btnUsers').classList.toggle('active', type === 'users');
    document.getElementById('btnStories').classList.toggle('active', type === 'stories');

    if (type === 'users') {
        mainChart.data.labels = userLabels.length ? userLabels : ['No data'];
        mainChart.data.datasets[0].data = userValues.length ? userValues : [0];
        mainChart.data.datasets[0].label = 'New Users';
        mainChart.data.datasets[0].borderColor = '#F5A623';
        mainChart.data.datasets[0].backgroundColor = 'rgba(245,166,35,0.08)';
        mainChart.data.datasets[0].pointBackgroundColor = '#F5A623';
        mainChart.update('active');
        return;
    }

    fetch('admin_ajax.php?action=story_chart')
        .then(r => r.json())
        .then(data => {
            mainChart.data.labels = data.labels.length ? data.labels : ['No data'];
            mainChart.data.datasets[0].data = data.values.length ? data.values : [0];
            mainChart.data.datasets[0].label = 'New Stories';
            mainChart.data.datasets[0].borderColor = '#3B82F6';
            mainChart.data.datasets[0].backgroundColor = 'rgba(59,130,246,0.08)';
            mainChart.data.datasets[0].pointBackgroundColor = '#3B82F6';
            mainChart.update('active');
        })
        .catch(() => showToast('Could not load story data', 'error'));
}

// ML Ping
function pingML() {
    const btn = document.querySelector('.ml-ping-btn');
    btn.textContent = 'Checking...';
    btn.disabled = true;
    fetch('admin_ajax.php?action=ping_ml')
        .then(r => r.json())
        .then(data => {
            const badge  = document.querySelector('.ml-badge');
            const pill   = document.querySelector('.ml-pill');
            const status = data.status;
            badge.className  = 'ml-badge ' + status;
            badge.textContent = status.toUpperCase();
            pill.className = 'ml-pill ' + status;
            pill.innerHTML = `<span class="ml-dot"></span> ML API &nbsp;${status.toUpperCase()}`;
            showToast(status === 'online' ? 'ML API is reachable' : 'ML API is unreachable', status === 'online' ? 'success' : 'error');
        })
        .catch(() => showToast('Ping failed', 'error'))
        .finally(() => { btn.textContent = 'Re-check API'; btn.disabled = false; });
}

// Global search
document.getElementById('globalSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && this.value.trim())
        window.location.href = `admin_search.php?q=${encodeURIComponent(this.value.trim())}`;
});
</script>
</body>
</html>
