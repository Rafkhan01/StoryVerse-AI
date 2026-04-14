<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in (but allow guests to view)
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// Helper function to determine story status based on dates
function getStoryStatus($upload_date, $prediction_deadline) {
    $current_time = time();
    $upload_timestamp = strtotime($upload_date);
    $deadline_timestamp = strtotime($prediction_deadline);
    if ($current_time < $upload_timestamp) {
        return 'coming_soon';
    } elseif ($current_time >= $upload_timestamp && $current_time <= $deadline_timestamp) {
        return 'active';
    } else {
        return 'completed';
    }
}

// Get filter and sort parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by       = isset($_GET['sort'])   ? $_GET['sort']   : 'newest';
$author_filter = isset($_GET['author']) ? $_GET['author'] : '';

$results_to_display = [];
$all_authors = [];

try {
    $stmt = $pdo->query("SELECT DISTINCT created_by FROM stories ORDER BY created_by ASC");
    $all_authors = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($filter_status === 'my_predictions' && $is_logged_in) {
        $sql = "
            SELECT p.prediction_id, p.prediction_text, p.created_at AS prediction_date,
                   s.title, sp.part_number, sp.story_id, s.total_parts, s.cover_image_url,
                   (SELECT COUNT(*) FROM likes WHERE prediction_id = p.prediction_id) AS prediction_likes
            FROM predictions p
            JOIN story_parts sp ON p.story_id = sp.story_id AND p.prediction_part_no = sp.part_number
            JOIN stories s ON sp.story_id = s.story_id
            WHERE p.user_id = :user_id
        ";
        if ($sort_by === 'newest')       $sql .= " ORDER BY p.created_at DESC";
        elseif ($sort_by === 'oldest')   $sql .= " ORDER BY p.created_at ASC";
        elseif ($sort_by === 'popular')  $sql .= " ORDER BY prediction_likes DESC, p.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $results_to_display = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($filter_status === 'my_author_stories' && $is_logged_in) {
        $sql = "
            SELECT sp.*, s.title, s.created_by, s.category, s.description,
                   s.cover_image_url, s.total_parts, s.created_at AS story_created_at
            FROM story_parts sp
            JOIN stories s ON sp.story_id = s.story_id
            JOIN users u ON s.created_by = u.user_name
            JOIN follows f ON f.author_user_id = u.user_id
            WHERE f.follower_user_id = :user_id
        ";
        if (!empty($author_filter)) $sql .= " AND s.created_by = :author_name";
        if ($sort_by === 'popular')      $sql .= " ORDER BY s.view_count DESC, sp.upload_date DESC";
        elseif ($sort_by === 'oldest')   $sql .= " ORDER BY sp.upload_date ASC";
        else                             $sql .= " ORDER BY sp.upload_date DESC";
        $stmt   = $pdo->prepare($sql);
        $params = [':user_id' => $user_id];
        if (!empty($author_filter)) $params[':author_name'] = $author_filter;
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $part_data) {
            $part_data['dynamic_status'] = getStoryStatus($part_data['upload_date'], $part_data['prediction_deadline']);
            $results_to_display[] = $part_data;
        }

    } else {
        $sql = "
            SELECT sp.*, s.title, s.created_by, s.category, s.description,
                   s.cover_image_url, s.total_parts, s.view_count, s.created_at AS story_created_at
            FROM story_parts sp
            JOIN stories s ON sp.story_id = s.story_id
        ";
        $where_clauses = [];
        $params = [];
        if (!empty($author_filter)) {
            $where_clauses[] = "s.created_by = :author_name";
            $params[':author_name'] = $author_filter;
        }
        if (!empty($where_clauses)) $sql .= " WHERE " . implode(" AND ", $where_clauses);
        if ($sort_by === 'popular')     $sql .= " ORDER BY s.view_count DESC, sp.upload_date DESC";
        elseif ($sort_by === 'oldest')  $sql .= " ORDER BY sp.upload_date ASC";
        else                            $sql .= " ORDER BY sp.upload_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $part_data) {
            $part_data['dynamic_status'] = getStoryStatus($part_data['upload_date'], $part_data['prediction_deadline']);
            if ($filter_status === 'all' || $filter_status === $part_data['dynamic_status']) {
                $results_to_display[] = $part_data;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $results_to_display = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Browse Stories — StoryVerse</title>

<link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Rajdhani:wght@300;400;500;600;700&family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet"/>

<style>
/* ====================================================
   DESIGN TOKENS  — identical to index.php
==================================================== */
:root {
  --ink:      #0a0b14;
  --deep:     #0d0f1f;
  --void:     #060810;
  --ember:    #c8860a;
  --gold:     #e8b84b;
  --gold-lt:  #f5d07a;
  --azure:    #4a9eff;
  --azure-lt: #7dbfff;
  --mist:     #b8c8e8;
  --ivory:    #f0ead8;
  --glass:    rgba(255,255,255,0.04);
  --border:   rgba(232,184,75,0.18);

  --font-display: 'Cinzel Decorative', serif;
  --font-body:    'Cormorant Garamond', serif;
  --font-ui:      'Rajdhani', sans-serif;
  --font-mono:    'Space Mono', monospace;
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;overflow-x:hidden}

body {
  background: var(--void);
  color: var(--ivory);
  font-family: var(--font-body);
  font-size: 17px;
  line-height: 1.7;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  overflow-x: hidden;
}

::selection{background:rgba(232,184,75,.3);color:var(--gold-lt)}
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:var(--void)}
::-webkit-scrollbar-thumb{background:var(--ember);border-radius:2px}

/* ====================================================
   NAVBAR
==================================================== */
#navbar {
  position: fixed;
  top:0;left:0;right:0;
  z-index:1000;
  padding:0 5%;
  height:70px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  background:rgba(6,8,16,.94);
  backdrop-filter:blur(20px);
  box-shadow:0 1px 0 var(--border),0 8px 32px rgba(0,0,0,.6);
}

.nav-logo {
  font-family:var(--font-display);
  font-size:1.1rem;font-weight:700;
  letter-spacing:.05em;
  background:linear-gradient(135deg,var(--gold),var(--azure-lt));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
  text-decoration:none;white-space:nowrap;
}

.nav-links{display:flex;align-items:center;gap:.2rem;list-style:none}

.nav-links a {
  font-family:var(--font-ui);font-size:.83rem;font-weight:500;
  letter-spacing:.12em;text-transform:uppercase;
  color:var(--mist);text-decoration:none;
  padding:.4rem .85rem;border-radius:4px;
  transition:color .2s;position:relative;
}
.nav-links a::after {
  content:'';position:absolute;bottom:-2px;left:50%;
  width:0;height:1px;background:var(--gold);
  transform:translateX(-50%);transition:width .3s;
}
.nav-links a:hover,.nav-links a.active-nav{color:var(--gold-lt)}
.nav-links a:hover::after,.nav-links a.active-nav::after{width:60%}

.nav-btn {
  font-family:var(--font-ui)!important;font-size:.78rem!important;
  font-weight:600!important;letter-spacing:.1em!important;
  text-transform:uppercase!important;
  padding:.42rem 1.1rem!important;border-radius:30px!important;
  border:1px solid var(--border)!important;background:transparent!important;
  color:var(--ivory)!important;cursor:pointer;transition:all .3s!important;
  text-decoration:none;
}
.nav-btn:hover{background:rgba(232,184,75,.12)!important;border-color:var(--gold)!important;color:var(--gold-lt)!important}
.nav-btn-primary{background:linear-gradient(135deg,var(--ember),var(--gold))!important;border-color:transparent!important;color:var(--void)!important}
.nav-btn-primary:hover{background:linear-gradient(135deg,var(--gold),var(--gold-lt))!important;color:var(--void)!important;transform:translateY(-1px);box-shadow:0 4px 20px rgba(232,184,75,.35)!important}

.nav-toggle{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:4px}
.nav-toggle span{display:block;width:22px;height:2px;background:var(--ivory);transition:all .3s;border-radius:2px}

/* ====================================================
   PAGE HERO BANNER
==================================================== */
.page-hero {
  padding:8rem 5% 3.5rem;
  position:relative;overflow:hidden;
  background:linear-gradient(to bottom,var(--void) 0%,var(--deep) 100%);
}
.page-hero::before {
  content:'';position:absolute;top:-120px;left:-100px;
  width:600px;height:600px;
  background:radial-gradient(ellipse,rgba(74,158,255,.07) 0%,transparent 70%);
  pointer-events:none;
}
.page-hero::after {
  content:'';position:absolute;bottom:-80px;right:-60px;
  width:500px;height:400px;
  background:radial-gradient(ellipse,rgba(232,184,75,.05) 0%,transparent 70%);
  pointer-events:none;
}
.page-hero-inner{position:relative;z-index:1;max-width:1300px;margin:0 auto}

.page-eyebrow {
  font-family:var(--font-mono);font-size:.65rem;
  letter-spacing:.4em;text-transform:uppercase;
  color:var(--azure);display:block;margin-bottom:.75rem;
}
.page-title {
  font-family:var(--font-display);
  font-size:clamp(2rem,4.5vw,3.4rem);font-weight:700;line-height:1.15;
  background:linear-gradient(135deg,var(--ivory) 40%,var(--gold-lt));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  margin-bottom:.75rem;
}
.page-sub {
  font-family:var(--font-body);font-size:1.05rem;font-weight:300;
  color:rgba(176,196,222,.72);max-width:520px;
}
.page-divider {
  width:60px;height:2px;
  background:linear-gradient(to right,var(--gold),var(--azure));
  border-radius:2px;margin:1.25rem 0;
}

/* ====================================================
   MAIN
==================================================== */
main{flex:1;padding:2rem 5% 6rem;max-width:1400px;margin:0 auto;width:100%}

/* ====================================================
   CONTROLS PANEL
==================================================== */
.controls-wrap {
  background:rgba(13,15,31,.85);
  border:1px solid var(--border);border-radius:16px;
  padding:1.5rem 1.75rem;margin-bottom:2.5rem;
  backdrop-filter:blur(14px);
  display:flex;flex-direction:column;gap:1.25rem;
}

/* Filter tabs */
.filter-tabs{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}

.filter-tab {
  display:inline-flex;align-items:center;gap:.4rem;
  font-family:var(--font-ui);font-size:.78rem;font-weight:600;
  letter-spacing:.1em;text-transform:uppercase;text-decoration:none;
  padding:.48rem 1.15rem;border-radius:50px;
  border:1px solid var(--border);background:var(--glass);
  color:var(--mist);transition:all .3s;white-space:nowrap;
  cursor:pointer;
}
.filter-tab:hover {
  border-color:rgba(232,184,75,.4);color:var(--gold-lt);
  background:rgba(232,184,75,.07);transform:translateY(-2px);
}
.filter-tab.tab-active {
  background:linear-gradient(135deg,var(--ember),var(--gold));
  border-color:transparent;color:var(--void);
  box-shadow:0 4px 20px rgba(200,134,10,.35);font-weight:700;
}
.filter-tab.tab-disabled {
  opacity:.32;cursor:not-allowed;pointer-events:none;
}

/* Active dot for 'Active' tab */
.live-dot {
  width:6px;height:6px;border-radius:50%;
  background:currentColor;
  animation:livePulse 1.6s infinite;
}
@keyframes livePulse {
  0%,100%{opacity:1;transform:scale(1)}
  50%{opacity:.35;transform:scale(.65)}
}

/* Controls bottom row */
.controls-row {
  display:flex;justify-content:space-between;
  align-items:center;flex-wrap:wrap;gap:.75rem;
}
.results-count {
  font-family:var(--font-mono);font-size:.67rem;
  letter-spacing:.18em;color:rgba(176,196,222,.4);text-transform:uppercase;
}
.results-count span{color:var(--gold);font-weight:700}

.selects-group{display:flex;gap:.75rem;flex-wrap:wrap}

.sv-select {
  appearance:none;
  background:var(--glass);
  border:1px solid var(--border);
  color:var(--mist);
  font-family:var(--font-ui);font-size:.8rem;font-weight:500;
  letter-spacing:.08em;
  padding:.5rem 2.2rem .5rem 1rem;
  border-radius:8px;cursor:pointer;transition:all .25s;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 20 20'%3E%3Cpath fill='%23b8c8e8' d='M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right .75rem center;
  min-width:165px;
}
.sv-select:hover,.sv-select:focus {
  border-color:rgba(232,184,75,.4);color:var(--ivory);outline:none;
  background-color:rgba(232,184,75,.05);
}
.sv-select option{background:var(--deep);color:var(--ivory)}

/* ====================================================
   STORIES GRID
==================================================== */
.stories-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(278px,1fr));
  gap:1.75rem;
}

/* ====================================================
   STORY CARD
==================================================== */
.story-card {
  background:rgba(13,15,31,.8);
  border:1px solid var(--border);border-radius:16px;
  overflow:hidden;cursor:pointer;
  transition:transform .35s,box-shadow .35s,border-color .35s;
  backdrop-filter:blur(8px);
  display:flex;flex-direction:column;
}
.story-card:hover {
  transform:translateY(-8px);
  border-color:rgba(232,184,75,.35);
  box-shadow:0 20px 50px rgba(0,0,0,.55),0 0 0 1px rgba(232,184,75,.12);
}

.card-img-wrap{position:relative;height:190px;overflow:hidden;flex-shrink:0}
.card-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.story-card:hover .card-img-wrap img{transform:scale(1.06)}
.card-img-overlay {
  position:absolute;inset:0;
  background:linear-gradient(to bottom,transparent 40%,rgba(6,8,16,.82) 100%);
}

/* Status badge */
.status-badge {
  position:absolute;top:10px;left:10px;
  display:inline-flex;align-items:center;gap:5px;
  font-family:var(--font-ui);font-size:.67rem;font-weight:700;
  letter-spacing:.12em;text-transform:uppercase;
  padding:.28rem .75rem;border-radius:50px;
  backdrop-filter:blur(8px);
}
.status-active     {background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.4);color:#4ade80}
.status-coming_soon{background:rgba(250,204,21,.2);border:1px solid rgba(250,204,21,.4);color:#fde047}
.status-completed  {background:rgba(74,158,255,.2);border:1px solid rgba(74,158,255,.4);color:var(--azure-lt)}

.ping-dot {
  width:7px;height:7px;border-radius:50%;background:#4ade80;
  animation:pingAnim 1.5s infinite;
}
@keyframes pingAnim {
  0%  {box-shadow:0 0 0 0 rgba(74,222,128,.7)}
  70% {box-shadow:0 0 0 8px rgba(74,222,128,0)}
  100%{box-shadow:0 0 0 0 rgba(74,222,128,0)}
}

/* Card body */
.card-body{padding:1.2rem 1.3rem 1.35rem;display:flex;flex-direction:column;flex:1}

.card-title {
  font-family:var(--font-ui);font-size:.96rem;font-weight:600;
  letter-spacing:.04em;color:var(--ivory);
  margin-bottom:.3rem;line-height:1.4;
}
.card-author {
  font-family:var(--font-body);font-size:.85rem;font-weight:300;
  color:rgba(176,196,222,.58);margin-bottom:.6rem;
}
.card-author a{color:var(--gold);text-decoration:none;font-weight:400;transition:color .2s}
.card-author a:hover{color:var(--gold-lt)}

.card-desc {
  font-family:var(--font-body);font-size:.87rem;font-weight:300;
  color:rgba(176,196,222,.52);line-height:1.6;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  margin-bottom:auto;padding-bottom:.9rem;
}
.card-meta {
  display:flex;justify-content:space-between;align-items:center;
  margin-top:.7rem;padding-top:.7rem;
  border-top:1px solid var(--border);
  font-family:var(--font-ui);font-size:.74rem;font-weight:500;
  letter-spacing:.08em;color:rgba(176,196,222,.42);
}
.card-meta-left,.card-meta-right{display:flex;align-items:center;gap:.35rem}
.card-meta svg{width:13px;height:13px;opacity:.7}

.progress-wrap {
  margin-top:.8rem;
  background:rgba(255,255,255,.07);border-radius:50px;height:4px;overflow:hidden;
}
.progress-fill {
  height:100%;
  background:linear-gradient(90deg,var(--ember),var(--gold));
  border-radius:50px;transition:width .6s ease;
}

/* ====================================================
   PREDICTION CARD
==================================================== */
.prediction-card {
  background:rgba(13,15,31,.8);
  border:1px solid var(--border);border-radius:16px;
  padding:1.5rem;display:flex;flex-direction:column;gap:.9rem;
  transition:transform .3s,border-color .3s,box-shadow .3s;
  backdrop-filter:blur(8px);
}
.prediction-card:hover {
  transform:translateY(-5px);
  border-color:rgba(232,184,75,.3);
  box-shadow:0 16px 40px rgba(0,0,0,.5);
}
.pred-story-title {
  font-family:var(--font-ui);font-size:.95rem;font-weight:600;
  letter-spacing:.04em;color:var(--gold-lt);
  text-decoration:none;line-height:1.4;transition:color .2s;
}
.pred-story-title:hover{color:var(--ivory)}

.pred-date {
  font-family:var(--font-mono);font-size:.64rem;
  letter-spacing:.15em;color:rgba(176,196,222,.38);text-transform:uppercase;
}
.pred-text-wrap {
  border-left:2px solid var(--gold);padding-left:.9rem;
  background:rgba(232,184,75,.04);border-radius:0 6px 6px 0;
  padding:.55rem .75rem .55rem .9rem;
}
.pred-text {
  font-family:var(--font-body);font-size:.92rem;font-weight:300;
  color:rgba(176,196,222,.72);line-height:1.65;
  display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;
}
.pred-footer {
  display:flex;align-items:center;justify-content:space-between;
  padding-top:.5rem;border-top:1px solid var(--border);
}
.pred-likes {
  display:flex;align-items:center;gap:.4rem;
  font-family:var(--font-ui);font-size:.76rem;font-weight:500;
  color:rgba(176,196,222,.48);
}
.pred-likes svg{width:14px;height:14px;color:#f87171}
.pred-btn {
  font-family:var(--font-ui);font-size:.72rem;font-weight:600;
  letter-spacing:.12em;text-transform:uppercase;
  padding:.36rem 1rem;border-radius:50px;
  background:var(--glass);border:1px solid var(--border);
  color:var(--mist);cursor:pointer;transition:all .25s;
}
.pred-btn:hover{background:rgba(232,184,75,.1);border-color:var(--gold);color:var(--gold-lt)}

/* ====================================================
   EMPTY STATE
==================================================== */
.empty-state {
  grid-column:1/-1;text-align:center;padding:5rem 2rem;
  display:flex;flex-direction:column;align-items:center;gap:1.25rem;
}
.empty-icon {
  width:80px;height:80px;border-radius:50%;
  background:var(--glass);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;font-size:2rem;
}
.empty-state h3 {
  font-family:var(--font-ui);font-size:1rem;font-weight:600;
  letter-spacing:.08em;text-transform:uppercase;color:var(--mist);
}
.empty-state p {
  font-family:var(--font-body);font-size:.95rem;font-weight:300;
  color:rgba(176,196,222,.48);max-width:380px;line-height:1.7;
}
.empty-btn {
  display:inline-flex;align-items:center;gap:.5rem;
  font-family:var(--font-ui);font-size:.82rem;font-weight:600;
  letter-spacing:.1em;text-transform:uppercase;text-decoration:none;
  padding:.7rem 1.8rem;border-radius:50px;
  background:linear-gradient(135deg,var(--ember),var(--gold));
  color:var(--void);transition:all .3s;
  box-shadow:0 4px 20px rgba(200,134,10,.3);
}
.empty-btn:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(232,184,75,.4)}

/* ====================================================
   FOOTER
==================================================== */
footer{background:var(--void);border-top:1px solid var(--border);padding:5rem 5% 2.5rem;margin-top:auto}

.footer-top{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:3rem;margin-bottom:4rem}

.footer-logo {
  font-family:var(--font-display);font-size:1.2rem;font-weight:700;
  background:linear-gradient(135deg,var(--gold),var(--azure-lt));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  display:block;margin-bottom:1rem;text-decoration:none;
}
.footer-brand p{font-family:var(--font-body);font-size:.9rem;font-weight:300;color:rgba(176,196,222,.48);line-height:1.7;max-width:260px}

.footer-col h5{font-family:var(--font-ui);font-size:.72rem;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--gold);margin-bottom:1.25rem}
.footer-col ul{list-style:none}
.footer-col ul li{margin-bottom:.6rem}
.footer-col ul li a{font-family:var(--font-body);font-size:.9rem;font-weight:300;color:rgba(176,196,222,.48);text-decoration:none;transition:color .2s}
.footer-col ul li a:hover{color:var(--ivory)}

.social-links{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.25rem}
.social-btn {
  width:38px;height:38px;border-radius:9px;
  background:var(--glass);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;text-decoration:none;color:var(--mist);transition:all .3s;
}
.social-btn:hover{background:rgba(232,184,75,.1);border-color:var(--gold);color:var(--gold);transform:translateY(-3px)}

.footer-bottom{border-top:1px solid var(--border);padding-top:2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem}
.footer-bottom p{font-family:var(--font-mono);font-size:.66rem;letter-spacing:.1em;color:rgba(176,196,222,.28)}

/* ====================================================
   RESPONSIVE
==================================================== */
@media(max-width:900px){
  .footer-top{grid-template-columns:1fr 1fr}
  .stories-grid{grid-template-columns:repeat(auto-fill,minmax(240px,1fr))}
}
@media(max-width:600px){
  .nav-links{display:none}
  .nav-links.open{
    display:flex;flex-direction:column;position:fixed;
    top:70px;left:0;right:0;
    background:rgba(6,8,16,.97);backdrop-filter:blur(20px);
    padding:1.5rem 5%;border-bottom:1px solid var(--border);
    gap:.25rem;z-index:999;
  }
  .nav-toggle{display:flex}
  .footer-top{grid-template-columns:1fr}
  .stories-grid{grid-template-columns:1fr}
  .controls-row{flex-direction:column;align-items:flex-start}
  .filter-tabs{gap:.45rem}
  .filter-tab{font-size:.72rem;padding:.42rem .95rem}
}
</style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav id="navbar">
  <a href="index.php" class="nav-logo">StoryVerse</a>

  <ul class="nav-links" id="navLinks">
    <li><a href="index.php">Home</a></li>
    <li><a href="stories.php" class="active-nav">Stories</a></li>
    <?php if ($is_logged_in): ?>
      <li><a href="game_arena.php">Game Arena</a></li>
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

<!-- ===== PAGE HERO ===== -->
<div class="page-hero">
  <div class="page-hero-inner" data-aos="fade-right" data-aos-duration="700">
    <span class="page-eyebrow">✦ &nbsp;The Library</span>
    <h1 class="page-title">Browse Stories</h1>
    <div class="page-divider"></div>
    <p class="page-sub">Discover living narratives, cast your predictions, and follow the authors shaping the StoryVerse.</p>
  </div>
</div>

<!-- ===== MAIN ===== -->
<main>

  <!-- Controls panel -->
  <div class="controls-wrap" data-aos="fade-up" data-aos-duration="600">

    <!-- Filter tabs row -->
    <div class="filter-tabs">
      <a href="stories.php?status=all&sort=<?php echo htmlspecialchars($sort_by); ?>"
         class="filter-tab <?php echo ($filter_status==='all'?'tab-active':''); ?>">
        All Stories
      </a>
      <a href="stories.php?status=active&sort=<?php echo htmlspecialchars($sort_by); ?>"
         class="filter-tab <?php echo ($filter_status==='active'?'tab-active':''); ?>">
        <span class="live-dot"></span> Active
      </a>
      <a href="stories.php?status=coming_soon&sort=<?php echo htmlspecialchars($sort_by); ?>"
         class="filter-tab <?php echo ($filter_status==='coming_soon'?'tab-active':''); ?>">
        Coming Soon
      </a>
      <a href="stories.php?status=completed&sort=<?php echo htmlspecialchars($sort_by); ?>"
         class="filter-tab <?php echo ($filter_status==='completed'?'tab-active':''); ?>">
        Completed
      </a>

      <?php if ($is_logged_in): ?>
        <a href="stories.php?status=my_predictions&sort=<?php echo htmlspecialchars($sort_by); ?>"
           class="filter-tab <?php echo ($filter_status==='my_predictions'?'tab-active':''); ?>">
          🔮 My Predictions
        </a>
        <a href="stories.php?status=my_author_stories&sort=<?php echo htmlspecialchars($sort_by); ?>"
           class="filter-tab <?php echo ($filter_status==='my_author_stories'?'tab-active':''); ?>">
          ✍️ My Author Stories
        </a>
      <?php else: ?>
        <span class="filter-tab tab-disabled" title="Sign in to view your predictions">
          🔮 My Predictions
        </span>
        <span class="filter-tab tab-disabled" title="Sign in to view followed authors">
          ✍️ My Author Stories
        </span>
      <?php endif; ?>
    </div>

    <!-- Results count + dropdowns -->
    <div class="controls-row">
      <p class="results-count">
        Showing <span><?php echo count($results_to_display); ?></span>
        result<?php echo count($results_to_display)!==1?'s':''; ?>
      </p>

      <div class="selects-group">
        <!-- Author filter — only shown on my_author_stories -->
        <?php if ($filter_status==='my_author_stories' && !empty($all_authors)): ?>
        <select class="sv-select"
                onchange="window.location.href='stories.php?status=my_author_stories&sort=<?php echo htmlspecialchars($sort_by); ?>&author='+encodeURIComponent(this.value)">
          <option value="">All Followed Authors</option>
          <?php foreach ($all_authors as $author): ?>
            <option value="<?php echo htmlspecialchars($author); ?>"
                    <?php echo ($author_filter===$author?'selected':''); ?>>
              <?php echo htmlspecialchars($author); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <!-- Sort -->
        <select class="sv-select"
                onchange="window.location.href='stories.php?status=<?php echo htmlspecialchars($filter_status); ?>&sort='+this.value+'<?php echo !empty($author_filter)?'&author='.urlencode($author_filter):''; ?>'">
          <option value="newest"  <?php echo ($sort_by==='newest' ?'selected':''); ?>>↓ Newest First</option>
          <option value="oldest"  <?php echo ($sort_by==='oldest' ?'selected':''); ?>>↑ Oldest First</option>
          <option value="popular" <?php echo ($sort_by==='popular'?'selected':''); ?>>★ Most Viewed</option>
        </select>
      </div>
    </div>

  </div><!-- /controls-wrap -->

  <!-- Stories / Predictions Grid -->
  <div class="stories-grid">

    <?php if (empty($results_to_display)): ?>
      <div class="empty-state" data-aos="fade-up">
        <div class="empty-icon">📭</div>
        <h3>Nothing Here Yet</h3>
        <p>
          <?php if ($filter_status==='my_predictions'): ?>
            You haven't made any predictions yet. Start reading and cast your first one!
          <?php elseif ($filter_status==='my_author_stories'): ?>
            <?php if (!empty($author_filter)): ?>
              No stories found from <strong><?php echo htmlspecialchars($author_filter); ?></strong>.
            <?php else: ?>
              You're not following any authors yet. Explore stories and follow your favourites!
            <?php endif; ?>
          <?php else: ?>
            No stories found in this category. Check back soon!
          <?php endif; ?>
        </p>
        <?php if (!$is_logged_in): ?>
          <a href="signin.php" class="empty-btn">→ Sign In to Continue</a>
        <?php else: ?>
          <a href="stories.php?status=all" class="empty-btn">✦ View All Stories</a>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <?php foreach ($results_to_display as $index => $item): ?>

        <?php if ($filter_status==='my_predictions'): ?>
        <!-- ===== PREDICTION CARD ===== -->
        <div class="prediction-card"
             data-aos="fade-up"
             data-aos-delay="<?php echo min($index*60,400); ?>"
             data-aos-duration="600">

          <a href="story_detail.php?story_id=<?php echo htmlspecialchars($item['story_id']); ?>&part_number=<?php echo htmlspecialchars($item['part_number']); ?>"
             class="pred-story-title">
            <?php echo htmlspecialchars($item['title']); ?> — Part <?php echo htmlspecialchars($item['part_number']); ?>
          </a>

          <span class="pred-date">Predicted on <?php echo date('M d, Y', strtotime($item['prediction_date'])); ?></span>

          <div class="pred-text-wrap">
            <p class="pred-text"><?php echo htmlspecialchars($item['prediction_text']); ?></p>
          </div>

          <div class="pred-footer">
            <span class="pred-likes">
              <svg fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
              </svg>
              <?php echo htmlspecialchars($item['prediction_likes']); ?> Likes
            </span>
            <button class="pred-btn"
                    onclick="location.href='story_detail.php?story_id=<?php echo htmlspecialchars($item['story_id']); ?>&part_number=<?php echo htmlspecialchars($item['part_number']); ?>'">
              View / Edit
            </button>
          </div>
        </div>

        <?php else: ?>
        <?php
          $dyn_status      = getStoryStatus($item['upload_date'], $item['prediction_deadline']);
          $progress_pct    = ($item['total_parts']>0) ? ($item['part_number']/$item['total_parts'])*100 : 0;
          $status_label    = ucfirst(str_replace('_',' ',$dyn_status));
          $story_url       = 'track_view.php?story_id='.htmlspecialchars($item['story_id']).'&part_number='.htmlspecialchars($item['part_number']);
          $cover_src       = !empty($item['cover_image_url'])
                               ? htmlspecialchars($item['cover_image_url'])
                               : 'https://placehold.co/400x220/0d0f1f/4a9eff?text=StoryVerse';
        ?>
        <!-- ===== STORY CARD ===== -->
        <div class="story-card"
             onclick="window.location.href='<?php echo $story_url; ?>'"
             data-aos="fade-up"
             data-aos-delay="<?php echo min($index*60,400); ?>"
             data-aos-duration="600">

          <div class="card-img-wrap">
            <img src="<?php echo $cover_src; ?>"
                 onerror="this.onerror=null;this.src='https://placehold.co/400x220/0d0f1f/4a9eff?text=StoryVerse';"
                 alt="<?php echo htmlspecialchars($item['title']); ?>"/>
            <div class="card-img-overlay"></div>
            <span class="status-badge status-<?php echo $dyn_status; ?>">
              <?php if ($dyn_status==='active'): ?>
                <span class="ping-dot"></span>
              <?php endif; ?>
              <?php echo htmlspecialchars($status_label); ?>
            </span>
          </div>

          <div class="card-body">
            <h3 class="card-title">
              <?php echo htmlspecialchars($item['title']); ?> &mdash; Part <?php echo htmlspecialchars($item['part_number']); ?>
            </h3>
            <p class="card-author">
              By
              <a href="author_profile.php?author=<?php echo urlencode($item['created_by']); ?>"
                 onclick="event.stopPropagation()">
                <?php echo htmlspecialchars($item['created_by']); ?>
              </a>
            </p>
            <p class="card-desc"><?php echo htmlspecialchars($item['description']); ?></p>

            <div class="card-meta">
              <span class="card-meta-left">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Part <?php echo htmlspecialchars($item['part_number']); ?> of <?php echo htmlspecialchars($item['total_parts']); ?>
              </span>
              <span class="card-meta-right">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <?php echo isset($item['view_count']) ? number_format($item['view_count']) : '0'; ?>
              </span>
            </div>

            <div class="progress-wrap">
              <div class="progress-fill" style="width:<?php echo round($progress_pct); ?>%"></div>
            </div>
          </div>
        </div>

        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>

  </div><!-- /stories-grid -->
</main>

<!-- ===== FOOTER ===== -->
<footer>
  <div class="footer-top">
    <div class="footer-brand">
      <a href="index.php" class="footer-logo">StoryVerse</a>
      <p>An AI-driven interactive storytelling universe where every reader shapes the narrative. Where stories live, breathe, and evolve.</p>
      <div class="social-links">
        <a href="https://wa.me/yournumber"           class="social-btn" title="WhatsApp"  target="_blank" rel="noopener">💬</a>
        <a href="https://instagram.com/yourhandle"   class="social-btn" title="Instagram" target="_blank" rel="noopener">📸</a>
        <a href="https://github.com/yourrepo"        class="social-btn" title="GitHub"    target="_blank" rel="noopener">🐙</a>
        <a href="https://linkedin.com/in/yourprofile" class="social-btn" title="LinkedIn" target="_blank" rel="noopener">💼</a>
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
      <h5>Account</h5>
      <ul>
        <?php if ($is_logged_in): ?>
          <li><a href="profile.php">My Profile</a></li>
          <li><a href="stories.php?status=my_predictions">My Predictions</a></li>
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
        <li><a href="https://wa.me/yournumber"           target="_blank" rel="noopener">WhatsApp Us</a></li>
        <li><a href="https://instagram.com/yourhandle"   target="_blank" rel="noopener">Instagram</a></li>
        <li><a href="https://github.com/yourrepo"        target="_blank" rel="noopener">GitHub</a></li>
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

<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({ once: true, duration: 680, easing: 'ease-out-cubic', offset: 50 });

function toggleNav() {
  document.getElementById('navLinks').classList.toggle('open');
}

document.querySelectorAll('#navLinks a').forEach(a => {
  a.addEventListener('click', () => document.getElementById('navLinks').classList.remove('open'));
});
</script>
</body>
</html>
