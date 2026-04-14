<?php
session_start();
require_once 'db_connect.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? (int)$_SESSION['user_id'] : null;

/* ============================================================
   HELPER: avatar initials
============================================================ */
function getInitials($first, $last = '') {
    $i = strtoupper(substr($first, 0, 1));
    if ($last) $i .= strtoupper(substr($last, 0, 1));
    return htmlspecialchars($i);
}

/* ============================================================
   QUERY 1 — STORY PREDICTION LEADERBOARD
   Rank by avg manual_accuracy (evaluated predictions only),
   secondary: sum bonus, third: total likes received
============================================================ */
$pred_rows = [];
try {
    $sql = "
        SELECT
            u.user_id,
            u.user_name,
            u.first_name,
            u.last_name,
            u.profile_picture,
            COUNT(p.prediction_id)                          AS total_predictions,
            ROUND(AVG(p.manual_accuracy), 2)                AS avg_accuracy,
            COALESCE(SUM(b.bonus_amount), 0)                AS total_bonus,
            COALESCE(lk.total_likes, 0)                     AS total_likes
        FROM users u
        JOIN predictions p ON p.user_id = u.user_id
            AND p.manual_accuracy IS NOT NULL
        LEFT JOIN bonus b ON b.user_id = u.user_id
        LEFT JOIN (
            SELECT pr.user_id, COUNT(l.like_id) AS total_likes
            FROM likes l
            JOIN predictions pr ON pr.prediction_id = l.prediction_id
            GROUP BY pr.user_id
        ) lk ON lk.user_id = u.user_id
        GROUP BY u.user_id
        ORDER BY avg_accuracy DESC, total_bonus DESC, total_likes DESC
    ";
    $stmt = $pdo->query($sql);
    $pred_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

/* ============================================================
   QUERY 2 — FLASH WORDS LEADERBOARD
   arena_progress: sum score primary, max best_wpm secondary
============================================================ */
$flash_rows = [];
try {
    $sql = "
        SELECT
            u.user_id,
            u.user_name,
            u.first_name,
            u.last_name,
            u.profile_picture,
            SUM(ap.score)           AS total_score,
            MAX(ap.best_wpm)        AS best_wpm,
            SUM(ap.total_correct)   AS total_correct,
            SUM(ap.total_attempts)  AS total_attempts
        FROM users u
        JOIN arena_progress ap ON ap.user_id = u.user_id
        GROUP BY u.user_id
        ORDER BY total_score DESC, best_wpm DESC
    ";
    $stmt = $pdo->query($sql);
    $flash_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

/* ============================================================
   QUERY 3 — STORY SCRAMBLE LEADERBOARD
   game_scores: sum score primary, total correct_pairs secondary
============================================================ */
$scramble_rows = [];
try {
    $sql = "
        SELECT
            u.user_id,
            u.user_name,
            u.first_name,
            u.last_name,
            u.profile_picture,
            SUM(gs.score)           AS total_score,
            SUM(gs.correct_pairs)   AS total_correct_pairs,
            COUNT(gs.id)            AS games_played
        FROM users u
        JOIN game_scores gs ON gs.user_id = u.user_id
        GROUP BY u.user_id
        ORDER BY total_score DESC, total_correct_pairs DESC
    ";
    $stmt = $pdo->query($sql);
    $scramble_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

/* ============================================================
   QUERY 4 — OVERALL LEADERBOARD
   Weighted composite: Prediction 50%, Flash 30%, Scramble 20%
   Each normalized 0-100 relative to max in that category
============================================================ */
$overall_rows = [];
try {
    // Get max values for normalization
    $max_pred_acc  = 0; $max_flash_score = 0; $max_scram_score = 0;
    if (!empty($pred_rows))    $max_pred_acc    = max(array_column($pred_rows,    'avg_accuracy'));
    if (!empty($flash_rows))   $max_flash_score = max(array_column($flash_rows,   'total_score'));
    if (!empty($scramble_rows)) $max_scram_score = max(array_column($scramble_rows,'total_score'));

    // Map data by user_id
    $pred_map    = []; foreach ($pred_rows    as $r) $pred_map[$r['user_id']]    = $r;
    $flash_map   = []; foreach ($flash_rows   as $r) $flash_map[$r['user_id']]   = $r;
    $scramble_map= []; foreach ($scramble_rows as $r) $scramble_map[$r['user_id']]= $r;

    // Collect all unique user_ids
    $all_uids = array_unique(array_merge(
        array_column($pred_rows,    'user_id'),
        array_column($flash_rows,   'user_id'),
        array_column($scramble_rows,'user_id')
    ));

    // Fetch all relevant users
    if (!empty($all_uids)) {
        $in = implode(',', array_map('intval', $all_uids));
        $stmt = $pdo->query("SELECT user_id,user_name,first_name,last_name,profile_picture FROM users WHERE user_id IN ($in)");
        $user_info = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) $user_info[$u['user_id']] = $u;

        foreach ($all_uids as $uid) {
            if (!isset($user_info[$uid])) continue;
            $u = $user_info[$uid];

            $norm_pred  = ($max_pred_acc    > 0 && isset($pred_map[$uid]))    ? ($pred_map[$uid]['avg_accuracy']    / $max_pred_acc    * 100) : 0;
            $norm_flash = ($max_flash_score > 0 && isset($flash_map[$uid]))   ? ($flash_map[$uid]['total_score']    / $max_flash_score * 100) : 0;
            $norm_scram = ($max_scram_score > 0 && isset($scramble_map[$uid]))?($scramble_map[$uid]['total_score'] / $max_scram_score * 100) : 0;

            $composite = round($norm_pred * 0.50 + $norm_flash * 0.30 + $norm_scram * 0.20, 2);

            $overall_rows[] = [
                'user_id'        => $uid,
                'user_name'      => $u['user_name'],
                'first_name'     => $u['first_name'],
                'last_name'      => $u['last_name'],
                'profile_picture'=> $u['profile_picture'],
                'composite'      => $composite,
                'pred_acc'       => isset($pred_map[$uid])     ? round($pred_map[$uid]['avg_accuracy'], 2)      : null,
                'flash_score'    => isset($flash_map[$uid])    ? $flash_map[$uid]['total_score']                : null,
                'scram_score'    => isset($scramble_map[$uid]) ? $scramble_map[$uid]['total_score']             : null,
            ];
        }
        usort($overall_rows, fn($a,$b) => $b['composite'] <=> $a['composite']);
    }
} catch (PDOException $e) { error_log($e->getMessage()); }

/* ============================================================
   CURRENT USER STATS per tab (for "Your Statistics" card)
============================================================ */
$my_pred = $my_flash = $my_scramble = $my_overall = null;

if ($is_logged_in) {
    // Story Prediction
    foreach ($pred_rows as $i => $r) {
        if ($r['user_id'] == $user_id) {
            $my_pred = array_merge($r, ['rank' => $i + 1]);
            break;
        }
    }
    // Flash Words
    foreach ($flash_rows as $i => $r) {
        if ($r['user_id'] == $user_id) {
            $my_flash = array_merge($r, ['rank' => $i + 1]);
            break;
        }
    }
    // Story Scramble
    foreach ($scramble_rows as $i => $r) {
        if ($r['user_id'] == $user_id) {
            $my_scramble = array_merge($r, ['rank' => $i + 1]);
            break;
        }
    }
    // Overall
    foreach ($overall_rows as $i => $r) {
        if ($r['user_id'] == $user_id) {
            $my_overall = array_merge($r, ['rank' => $i + 1]);
            break;
        }
    }
}

/* ============================================================
   HELPER: render podium + table + stats for a given dataset
============================================================ */
function renderLeaderboard($rows, $tab, $user_id, $my_stats) {
    global $is_logged_in;
    $top3  = array_slice($rows, 0, 3);
    $rest  = array_slice($rows, 3);
    $podium_order = []; // 2nd, 1st, 3rd
    if (count($top3) >= 2) $podium_order = [
        isset($top3[1]) ? $top3[1] : null,
        $top3[0],
        isset($top3[2]) ? $top3[2] : null,
    ];
    elseif (count($top3) === 1) $podium_order = [null, $top3[0], null];

    // Sub-card labels per tab
    $label1 = $label2 = '';
    $val1_key = $val2_key = '';
    if ($tab === 'pred') {
        $label1='Avg Accuracy'; $label2='Bonus Earned';
        $val1_key='avg_accuracy'; $val2_key='total_bonus';
    } elseif ($tab === 'flash') {
        $label1='Best WPM'; $label2='Total Score';
        $val1_key='best_wpm'; $val2_key='total_score';
    } elseif ($tab === 'scramble') {
        $label1='Correct Pairs'; $label2='Total Score';
        $val1_key='total_correct_pairs'; $val2_key='total_score';
    } elseif ($tab === 'overall') {
        $label1='Composite Score'; $label2='Pred Accuracy';
        $val1_key='composite'; $val2_key='pred_acc';
    }

    if (empty($rows)) {
        echo '<div class="empty-lb"><div class="empty-lb-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <p class="empty-lb-msg">No scores recorded yet. Be the first to play!</p></div>';
        return;
    }

    // Podium positions: heights 2nd=medium, 1st=tallest, 3rd=shortest
    $podium_heights = ['second', 'first', 'third'];
    $podium_ranks   = [2, 1, 3];
    $crown_icons    = ['', '&#9812;', ''];
    ?>
    <!-- PODIUM -->
    <div class="podium-wrap">
      <?php foreach ($podium_order as $pi => $person):
        if (!$person) { echo '<div class="podium-slot podium-empty"></div>'; continue; }
        $rank      = $podium_ranks[$pi];
        $is_you    = $is_logged_in && ($person['user_id'] == $user_id);
        $fname     = $person['first_name'] ?? '';
        $lname     = $person['last_name']  ?? '';
        $initials  = getInitials($fname, $lname);
        $uname     = htmlspecialchars($person['user_name']);
        $has_pic   = !empty($person['profile_picture']);

        if ($tab === 'pred') {
            $sub1 = number_format((float)($person['avg_accuracy'] ?? 0), 2) . '%';
            $sub2 = number_format((float)($person['total_bonus']  ?? 0), 2);
            $detail = 'Predictions: ' . ($person['total_predictions'] ?? 0);
        } elseif ($tab === 'flash') {
            $sub1 = ($person['best_wpm']    ?? 0) . ' WPM';
            $sub2 = number_format($person['total_score'] ?? 0);
            $detail = 'Correct: ' . ($person['total_correct'] ?? 0);
        } elseif ($tab === 'scramble') {
            $sub1 = number_format($person['total_correct_pairs'] ?? 0);
            $sub2 = number_format($person['total_score'] ?? 0);
            $detail = 'Games: ' . ($person['games_played'] ?? 0);
        } elseif ($tab === 'overall') {
            $sub1 = number_format((float)($person['composite'] ?? 0), 2);
            $sub2 = ($person['pred_acc'] !== null ? number_format((float)$person['pred_acc'], 2) . '%' : 'N/A');
            $detail = '';
        }
      ?>
      <div class="podium-slot podium-<?php echo $podium_heights[$pi]; ?> <?php echo $is_you ? 'podium-you' : ''; ?>">
        <?php if ($rank === 1): ?>
          <div class="podium-crown">
            <svg viewBox="0 0 32 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M2 20L6 8L12 14L16 4L20 14L26 8L30 20H2Z" fill="url(#crownGrad)" stroke="rgba(232,184,75,0.6)" stroke-width="1"/>
              <defs>
                <linearGradient id="crownGrad" x1="0" y1="0" x2="32" y2="24" gradientUnits="userSpaceOnUse">
                  <stop offset="0%" stop-color="#f5d07a"/>
                  <stop offset="100%" stop-color="#c8860a"/>
                </linearGradient>
              </defs>
            </svg>
          </div>
        <?php endif; ?>

        <div class="podium-avatar-wrap rank-<?php echo $rank; ?>">
          <?php if ($has_pic): ?>
            <img src="<?php echo htmlspecialchars($person['profile_picture']); ?>"
                 alt="<?php echo $uname; ?>" class="podium-avatar-img"/>
          <?php else: ?>
            <div class="podium-avatar-initials rank-<?php echo $rank; ?>"><?php echo $initials; ?></div>
          <?php endif; ?>
          <div class="podium-rank-badge rank-<?php echo $rank; ?>"><?php echo $rank; ?></div>
        </div>

        <div class="podium-info">
          <span class="podium-username"><?php echo $uname; ?><?php if ($is_you): ?> <span class="you-badge">You</span><?php endif; ?></span>
          <?php if ($detail): ?><span class="podium-detail"><?php echo $detail; ?></span><?php endif; ?>

          <div class="podium-subcards">
            <div class="podium-subcard">
              <span class="subcard-label"><?php echo $label1; ?></span>
              <span class="subcard-value"><?php echo $sub1; ?></span>
            </div>
            <div class="podium-subcard">
              <span class="subcard-label"><?php echo $label2; ?></span>
              <span class="subcard-value"><?php echo $sub2; ?></span>
            </div>
          </div>
        </div>

        <div class="podium-base rank-<?php echo $rank; ?>"></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- REST TABLE -->
    <?php if (!empty($rest)): ?>
    <div class="lb-table-wrap">
      <table class="lb-table">
        <thead>
          <tr>
            <th>Rank</th>
            <th>User</th>
            <?php if ($tab === 'pred'): ?>
              <th>Predictions</th><th>Accuracy</th><th>Bonus</th>
            <?php elseif ($tab === 'flash'): ?>
              <th>Best WPM</th><th>Correct</th><th>Score</th>
            <?php elseif ($tab === 'scramble'): ?>
              <th>Games</th><th>Correct Pairs</th><th>Score</th>
            <?php elseif ($tab === 'overall'): ?>
              <th>Pred Accuracy</th><th>Flash Score</th><th>Composite</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rest as $ri => $row):
            $rank    = $ri + 4;
            $is_you  = $is_logged_in && ($row['user_id'] == $user_id);
            $fname   = $row['first_name'] ?? '';
            $lname   = $row['last_name']  ?? '';
            $initials= getInitials($fname, $lname);
            $uname   = htmlspecialchars($row['user_name']);
            $has_pic = !empty($row['profile_picture']);
          ?>
          <tr class="<?php echo $is_you ? 'row-you' : ''; ?>">
            <td><span class="rank-num">#<?php echo $rank; ?></span></td>
            <td>
              <div class="table-user">
                <?php if ($has_pic): ?>
                  <img src="<?php echo htmlspecialchars($row['profile_picture']); ?>" class="table-avatar-img" alt=""/>
                <?php else: ?>
                  <div class="table-avatar"><?php echo $initials; ?></div>
                <?php endif; ?>
                <span class="table-username"><?php echo $uname; ?><?php if ($is_you): ?> <span class="you-badge-sm">You</span><?php endif; ?></span>
              </div>
            </td>
            <?php if ($tab === 'pred'): ?>
              <td><?php echo $row['total_predictions']; ?></td>
              <td class="accent-val"><?php echo number_format((float)$row['avg_accuracy'], 2); ?>%</td>
              <td><?php echo number_format((float)$row['total_bonus'], 2); ?></td>
            <?php elseif ($tab === 'flash'): ?>
              <td class="accent-val"><?php echo $row['best_wpm']; ?> WPM</td>
              <td><?php echo number_format($row['total_correct']); ?></td>
              <td class="accent-val"><?php echo number_format($row['total_score']); ?></td>
            <?php elseif ($tab === 'scramble'): ?>
              <td><?php echo $row['games_played']; ?></td>
              <td><?php echo number_format($row['total_correct_pairs']); ?></td>
              <td class="accent-val"><?php echo number_format($row['total_score']); ?></td>
            <?php elseif ($tab === 'overall'): ?>
              <td><?php echo $row['pred_acc'] !== null ? number_format((float)$row['pred_acc'], 2) . '%' : 'N/A'; ?></td>
              <td><?php echo $row['flash_score'] !== null ? number_format($row['flash_score']) : 'N/A'; ?></td>
              <td class="accent-val"><?php echo number_format((float)$row['composite'], 2); ?></td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- YOUR STATISTICS CARD -->
    <?php if ($is_logged_in): ?>
    <div class="your-stats-card">
      <div class="your-stats-header">
        <svg class="your-stats-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <h3>Your Statistics</h3>
      </div>
      <?php if ($my_stats): ?>
        <div class="your-stats-grid">
          <div class="stat-chip">
            <span class="stat-chip-label">Rank</span>
            <span class="stat-chip-value gold">#<?php echo $my_stats['rank']; ?></span>
          </div>
          <?php if ($tab === 'pred'): ?>
            <div class="stat-chip">
              <span class="stat-chip-label">Predictions</span>
              <span class="stat-chip-value"><?php echo $my_stats['total_predictions']; ?></span>
            </div>
            <div class="stat-chip">
              <span class="stat-chip-label">Avg Accuracy</span>
              <span class="stat-chip-value azure"><?php echo number_format((float)$my_stats['avg_accuracy'], 2); ?>%</span>
            </div>
            <div class="stat-chip">
              <span class="stat-chip-label">Total Bonus</span>
              <span class="stat-chip-value gold"><?php echo number_format((float)$my_stats['total_bonus'], 2); ?></span>
            </div>
          <?php elseif ($tab === 'flash'): ?>
            <div class="stat-chip">
              <span class="stat-chip-label">Total Score</span>
              <span class="stat-chip-value"><?php echo number_format($my_stats['total_score']); ?></span>
            </div>
            <div class="stat-chip">
              <span class="stat-chip-label">Best WPM</span>
              <span class="stat-chip-value azure"><?php echo $my_stats['best_wpm']; ?></span>
            </div>
            <div class="stat-chip">
              <span class="stat-chip-label">Correct Answers</span>
              <span class="stat-chip-value gold"><?php echo number_format($my_stats['total_correct']); ?></span>
            </div>
          <?php elseif ($tab === 'scramble'): ?>
            <div class="stat-chip">
              <span class="stat-chip-label">Games Played</span>
              <span class="stat-chip-value"><?php echo $my_stats['games_played']; ?></span>
            </div>
            <div class="stat-chip">
              <span class="stat-chip-label">Correct Pairs</span>
              <span class="stat-chip-value azure"><?php echo number_format($my_stats['total_correct_pairs']); ?></span>
            </div>
            <div class="stat-chip">
              <span class="stat-chip-label">Total Score</span>
              <span class="stat-chip-value gold"><?php echo number_format($my_stats['total_score']); ?></span>
            </div>
          <?php elseif ($tab === 'overall'): ?>
            <div class="stat-chip">
              <span class="stat-chip-label">Composite</span>
              <span class="stat-chip-value azure"><?php echo number_format((float)$my_stats['composite'], 2); ?></span>
            </div>
            <div class="stat-chip">
              <span class="stat-chip-label">Pred Accuracy</span>
              <span class="stat-chip-value gold"><?php echo $my_stats['pred_acc'] !== null ? number_format((float)$my_stats['pred_acc'], 2) . '%' : 'N/A'; ?></span>
            </div>
            <div class="stat-chip">
              <span class="stat-chip-label">Flash Score</span>
              <span class="stat-chip-value"><?php echo $my_stats['flash_score'] !== null ? number_format($my_stats['flash_score']) : 'N/A'; ?></span>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p class="no-stats-msg">You have no recorded scores in this category yet. Start playing to appear on the leaderboard!</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Leaderboard — StoryVerse</title>

<link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Rajdhani:wght@300;400;500;600;700&family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet"/>

<style>
/* =====================================================
   DESIGN TOKENS — identical to stories.php
===================================================== */
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
  --glass-md: rgba(255,255,255,0.07);
  --border:   rgba(232,184,75,0.18);
  --border-az:rgba(74,158,255,0.18);

  --font-display: 'Cinzel Decorative', serif;
  --font-body:    'Cormorant Garamond', serif;
  --font-ui:      'Rajdhani', sans-serif;
  --font-mono:    'Space Mono', monospace;

  /* podium colours */
  --gold-1:  #e8b84b;
  --silver-2:#a8b8d0;
  --bronze-3:#c47a45;
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

/* subtle grid texture overlay */
body::before {
  content:'';
  position:fixed;inset:0;
  background-image:
    linear-gradient(rgba(232,184,75,.025) 1px,transparent 1px),
    linear-gradient(90deg,rgba(232,184,75,.025) 1px,transparent 1px);
  background-size:60px 60px;
  pointer-events:none;z-index:0;
}

::selection{background:rgba(232,184,75,.3);color:var(--gold-lt)}
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:var(--void)}
::-webkit-scrollbar-thumb{background:var(--ember);border-radius:2px}

/* =====================================================
   NAVBAR — exact from stories.php
===================================================== */
#navbar {
  position:fixed;top:0;left:0;right:0;z-index:1000;
  padding:0 5%;height:70px;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(6,8,16,.94);
  backdrop-filter:blur(20px);
  box-shadow:0 1px 0 var(--border),0 8px 32px rgba(0,0,0,.6);
}
.nav-logo {
  font-family:var(--font-display);font-size:1.1rem;font-weight:700;
  letter-spacing:.05em;
  background:linear-gradient(135deg,var(--gold),var(--azure-lt));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  text-decoration:none;white-space:nowrap;
}
.nav-links{display:flex;align-items:center;gap:.2rem;list-style:none}
.nav-links a {
  font-family:var(--font-ui);font-size:.83rem;font-weight:500;
  letter-spacing:.12em;text-transform:uppercase;
  color:var(--mist);text-decoration:none;
  padding:.4rem .85rem;border-radius:4px;transition:color .2s;position:relative;
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
  font-weight:600!important;letter-spacing:.1em!important;text-transform:uppercase!important;
  padding:.42rem 1.1rem!important;border-radius:30px!important;
  border:1px solid var(--border)!important;background:transparent!important;
  color:var(--ivory)!important;cursor:pointer;transition:all .3s!important;text-decoration:none;
}
.nav-btn:hover{background:rgba(232,184,75,.12)!important;border-color:var(--gold)!important;color:var(--gold-lt)!important}
.nav-btn-primary{background:linear-gradient(135deg,var(--ember),var(--gold))!important;border-color:transparent!important;color:var(--void)!important}
.nav-btn-primary:hover{background:linear-gradient(135deg,var(--gold),var(--gold-lt))!important;color:var(--void)!important;transform:translateY(-1px);box-shadow:0 4px 20px rgba(232,184,75,.35)!important}
.nav-toggle{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:4px}
.nav-toggle span{display:block;width:22px;height:2px;background:var(--ivory);transition:all .3s;border-radius:2px}

/* =====================================================
   PAGE HERO
===================================================== */
.page-hero {
  padding:8rem 5% 3.5rem;
  position:relative;overflow:hidden;
  background:linear-gradient(to bottom,var(--void) 0%,var(--deep) 100%);
}
.page-hero::before {
  content:'';position:absolute;top:-120px;left:-100px;
  width:600px;height:600px;
  background:radial-gradient(ellipse,rgba(232,184,75,.06) 0%,transparent 70%);
  pointer-events:none;
}
.page-hero::after {
  content:'';position:absolute;bottom:-80px;right:-60px;
  width:500px;height:400px;
  background:radial-gradient(ellipse,rgba(74,158,255,.05) 0%,transparent 70%);
  pointer-events:none;
}
.page-hero-inner{position:relative;z-index:1;max-width:1300px;margin:0 auto}
.page-eyebrow {
  font-family:var(--font-mono);font-size:.65rem;
  letter-spacing:.4em;text-transform:uppercase;color:var(--gold);
  display:block;margin-bottom:.75rem;
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

/* =====================================================
   MAIN
===================================================== */
main{flex:1;padding:2.5rem 5% 6rem;max-width:1300px;margin:0 auto;width:100%;position:relative;z-index:1}

/* =====================================================
   TAB NAVIGATION
===================================================== */
.lb-tabs-wrap {
  background:rgba(13,15,31,.85);
  border:1px solid var(--border);border-radius:16px;
  padding:1.25rem 1.75rem;margin-bottom:2.5rem;
  backdrop-filter:blur(14px);
}
.lb-tabs{display:flex;flex-wrap:wrap;gap:.65rem;align-items:center}

.lb-tab {
  display:inline-flex;align-items:center;gap:.45rem;
  font-family:var(--font-ui);font-size:.78rem;font-weight:600;
  letter-spacing:.12em;text-transform:uppercase;
  padding:.5rem 1.25rem;border-radius:50px;
  border:1px solid var(--border);background:var(--glass);
  color:var(--mist);cursor:pointer;transition:all .3s;white-space:nowrap;
  user-select:none;
}
.lb-tab:hover {
  border-color:rgba(232,184,75,.4);color:var(--gold-lt);
  background:rgba(232,184,75,.07);transform:translateY(-2px);
}
.lb-tab.active {
  background:linear-gradient(135deg,var(--ember),var(--gold));
  border-color:transparent;color:var(--void);
  box-shadow:0 4px 20px rgba(200,134,10,.35);font-weight:700;
}
.lb-tab-icon {
  width:14px;height:14px;
  display:inline-flex;align-items:center;justify-content:center;
}
.tab-star { color:var(--gold-lt); font-size:.85rem; }

/* Tab panels */
.lb-panel{display:none}
.lb-panel.active{display:block}

/* =====================================================
   SECTION LABEL
===================================================== */
.section-label {
  font-family:var(--font-mono);font-size:.6rem;
  letter-spacing:.35em;text-transform:uppercase;
  color:rgba(176,196,222,.35);margin-bottom:2rem;
  display:flex;align-items:center;gap:1rem;
}
.section-label::after {
  content:'';flex:1;height:1px;
  background:linear-gradient(to right,rgba(232,184,75,.15),transparent);
}

/* =====================================================
   PODIUM
===================================================== */
.podium-wrap {
  display:flex;
  align-items:flex-end;
  justify-content:center;
  gap:1.25rem;
  margin-bottom:3rem;
  padding:0 1rem;
}

.podium-slot {
  display:flex;flex-direction:column;align-items:center;
  position:relative;
  flex:0 0 auto;
  width:220px;
}
.podium-slot.podium-empty{width:220px;min-height:280px}
.podium-first  { order:2; }
.podium-second { order:1; }
.podium-third  { order:3; }

/* glow behind winning card */
.podium-slot.podium-first::before {
  content:'';position:absolute;
  top:20%;left:50%;transform:translateX(-50%);
  width:160px;height:160px;border-radius:50%;
  background:radial-gradient(ellipse,rgba(232,184,75,.18) 0%,transparent 70%);
  pointer-events:none;z-index:0;
}

.podium-crown {
  margin-bottom:.5rem;
  animation:floatCrown 3s ease-in-out infinite;
}
.podium-crown svg{width:42px;height:32px;filter:drop-shadow(0 0 8px rgba(232,184,75,.5))}
@keyframes floatCrown{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}

/* Avatar */
.podium-avatar-wrap {
  position:relative;margin-bottom:.75rem;z-index:1;
}
.podium-avatar-img,
.podium-avatar-initials {
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-weight:700;
}
/* sizes per rank */
.podium-avatar-wrap.rank-1 .podium-avatar-img,
.podium-avatar-wrap.rank-1 .podium-avatar-initials { width:90px;height:90px;font-size:1.4rem; }
.podium-avatar-wrap.rank-2 .podium-avatar-img,
.podium-avatar-wrap.rank-2 .podium-avatar-initials { width:76px;height:76px;font-size:1.1rem; }
.podium-avatar-wrap.rank-3 .podium-avatar-img,
.podium-avatar-wrap.rank-3 .podium-avatar-initials { width:68px;height:68px;font-size:1rem; }

.podium-avatar-initials.rank-1 {
  background:linear-gradient(135deg,rgba(232,184,75,.25),rgba(200,134,10,.15));
  border:2.5px solid var(--gold);color:var(--gold-lt);
  box-shadow:0 0 24px rgba(232,184,75,.3),inset 0 0 12px rgba(232,184,75,.08);
}
.podium-avatar-initials.rank-2 {
  background:linear-gradient(135deg,rgba(168,184,208,.15),rgba(100,120,160,.1));
  border:2px solid var(--silver-2);color:var(--silver-2);
  box-shadow:0 0 18px rgba(168,184,208,.2);
}
.podium-avatar-initials.rank-3 {
  background:linear-gradient(135deg,rgba(196,122,69,.2),rgba(140,80,40,.1));
  border:2px solid var(--bronze-3);color:var(--bronze-3);
  box-shadow:0 0 14px rgba(196,122,69,.2);
}
.podium-avatar-img { border:2.5px solid var(--border); object-fit:cover; }

.podium-rank-badge {
  position:absolute;bottom:-4px;right:-4px;
  width:22px;height:22px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-mono);font-size:.65rem;font-weight:700;
  border:2px solid var(--void);
}
.podium-rank-badge.rank-1{background:var(--gold);color:var(--void)}
.podium-rank-badge.rank-2{background:var(--silver-2);color:var(--void)}
.podium-rank-badge.rank-3{background:var(--bronze-3);color:var(--void)}

/* Podium info block */
.podium-info {
  display:flex;flex-direction:column;align-items:center;
  gap:.3rem;margin-bottom:.85rem;z-index:1;width:100%;
  background:rgba(13,15,31,.6);
  border:1px solid var(--border);
  border-radius:14px;
  padding:.9rem 1rem 1rem;
  backdrop-filter:blur(12px);
  transition:transform .3s,box-shadow .3s;
}
.podium-slot:hover .podium-info { transform:translateY(-3px);box-shadow:0 12px 40px rgba(0,0,0,.4) }
.podium-slot.podium-first .podium-info {
  border-color:rgba(232,184,75,.35);
  box-shadow:0 0 30px rgba(232,184,75,.1),inset 0 0 20px rgba(232,184,75,.03);
}
.podium-slot.podium-you .podium-info { border-color:rgba(74,158,255,.4) }

.podium-username {
  font-family:var(--font-ui);font-size:.88rem;font-weight:600;
  letter-spacing:.06em;color:var(--ivory);text-align:center;
}
.podium-detail {
  font-family:var(--font-mono);font-size:.6rem;
  letter-spacing:.1em;color:rgba(176,196,222,.4);text-transform:uppercase;
}
.you-badge {
  font-family:var(--font-mono);font-size:.55rem;letter-spacing:.1em;
  background:rgba(74,158,255,.2);border:1px solid rgba(74,158,255,.4);
  color:var(--azure-lt);padding:.1rem .4rem;border-radius:4px;
  text-transform:uppercase;vertical-align:middle;
}
.you-badge-sm{font-size:.6rem;font-family:var(--font-mono);
  background:rgba(74,158,255,.18);border:1px solid rgba(74,158,255,.35);
  color:var(--azure-lt);padding:.05rem .35rem;border-radius:3px;letter-spacing:.08em;vertical-align:middle}

/* Sub-cards inside podium card */
.podium-subcards {
  display:grid;grid-template-columns:1fr 1fr;gap:.5rem;width:100%;margin-top:.4rem;
}
.podium-subcard {
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.07);
  border-radius:8px;padding:.55rem .5rem;
  display:flex;flex-direction:column;align-items:center;gap:.2rem;
  backdrop-filter:blur(6px);
}
.subcard-label {
  font-family:var(--font-mono);font-size:.52rem;
  letter-spacing:.12em;text-transform:uppercase;
  color:rgba(176,196,222,.4);
}
.subcard-value {
  font-family:var(--font-ui);font-size:.88rem;font-weight:700;
  color:var(--gold-lt);letter-spacing:.03em;
}

/* Podium base platform */
.podium-base {
  width:100%;border-radius:8px 8px 0 0;
  background:linear-gradient(to bottom,rgba(255,255,255,.06),rgba(255,255,255,.02));
  border:1px solid rgba(255,255,255,.08);border-bottom:none;
}
.podium-base.rank-1{height:56px;background:linear-gradient(to bottom,rgba(232,184,75,.18),rgba(232,184,75,.04));border-color:rgba(232,184,75,.25)}
.podium-base.rank-2{height:38px;background:linear-gradient(to bottom,rgba(168,184,208,.1),rgba(168,184,208,.03));border-color:rgba(168,184,208,.18)}
.podium-base.rank-3{height:24px;background:linear-gradient(to bottom,rgba(196,122,69,.1),rgba(196,122,69,.02));border-color:rgba(196,122,69,.15)}

/* =====================================================
   REST TABLE
===================================================== */
.lb-table-wrap {
  border-radius:14px;overflow:hidden;
  border:1px solid var(--border);
  backdrop-filter:blur(12px);
  background:rgba(13,15,31,.7);
  margin-bottom:2.5rem;
}
.lb-table {
  width:100%;border-collapse:collapse;
}
.lb-table thead tr {
  border-bottom:1px solid var(--border);
}
.lb-table thead th {
  font-family:var(--font-ui);font-size:.7rem;font-weight:600;
  letter-spacing:.2em;text-transform:uppercase;
  color:rgba(176,196,222,.45);
  padding:.9rem 1.25rem;text-align:left;
}
.lb-table thead th:first-child { padding-left:1.75rem; }
.lb-table thead th:last-child  { padding-right:1.75rem; }

.lb-table tbody tr {
  border-bottom:1px solid rgba(255,255,255,.04);
  transition:background .2s;
}
.lb-table tbody tr:last-child { border-bottom:none }
.lb-table tbody tr:hover { background:rgba(255,255,255,.025) }
.lb-table tbody tr.row-you {
  background:rgba(74,158,255,.06);
  border-left:2px solid var(--azure);
}
.lb-table td {
  font-family:var(--font-ui);font-size:.88rem;
  color:var(--mist);padding:.85rem 1.25rem;
}
.lb-table td:first-child { padding-left:1.75rem }
.lb-table td:last-child  { padding-right:1.75rem }

.rank-num {
  font-family:var(--font-mono);font-size:.72rem;
  color:rgba(176,196,222,.4);letter-spacing:.08em;
}
.accent-val { color:var(--gold-lt);font-weight:600 }

.table-user { display:flex;align-items:center;gap:.75rem }
.table-avatar {
  width:34px;height:34px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-size:.65rem;font-weight:700;
  background:rgba(232,184,75,.12);border:1px solid rgba(232,184,75,.2);
  color:var(--gold);flex-shrink:0;
}
.table-avatar-img { width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1px solid var(--border) }
.table-username { font-family:var(--font-ui);font-size:.86rem;color:var(--ivory);font-weight:500 }

/* =====================================================
   YOUR STATISTICS CARD
===================================================== */
.your-stats-card {
  margin-top:1rem;
  border-radius:16px;
  border:1px solid var(--border-az);
  background:rgba(13,15,31,.75);
  backdrop-filter:blur(16px);
  padding:1.75rem 2rem;
  box-shadow:0 0 40px rgba(74,158,255,.06),inset 0 0 30px rgba(74,158,255,.03);
  position:relative;overflow:hidden;
}
.your-stats-card::before {
  content:'';position:absolute;top:-60px;right:-60px;
  width:200px;height:200px;
  background:radial-gradient(ellipse,rgba(74,158,255,.08) 0%,transparent 70%);
  pointer-events:none;
}
.your-stats-header {
  display:flex;align-items:center;gap:.75rem;
  margin-bottom:1.5rem;
}
.your-stats-icon {
  width:22px;height:22px;color:var(--azure);flex-shrink:0;
}
.your-stats-header h3 {
  font-family:var(--font-ui);font-size:.85rem;font-weight:700;
  letter-spacing:.2em;text-transform:uppercase;
  color:var(--mist);
}
.your-stats-grid {
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
  gap:1rem;
}
.stat-chip {
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.07);
  border-radius:12px;padding:1rem 1.1rem;
  display:flex;flex-direction:column;gap:.4rem;
  transition:border-color .2s,box-shadow .2s;
}
.stat-chip:hover { border-color:rgba(232,184,75,.2);box-shadow:0 4px 20px rgba(0,0,0,.3) }
.stat-chip-label {
  font-family:var(--font-mono);font-size:.58rem;
  letter-spacing:.18em;text-transform:uppercase;
  color:rgba(176,196,222,.4);
}
.stat-chip-value {
  font-family:var(--font-ui);font-size:1.55rem;font-weight:700;
  color:var(--ivory);line-height:1;letter-spacing:.02em;
}
.stat-chip-value.gold  { color:var(--gold-lt) }
.stat-chip-value.azure { color:var(--azure-lt) }

.no-stats-msg {
  font-family:var(--font-body);font-size:.95rem;font-weight:300;
  color:rgba(176,196,222,.5);
  font-style:italic;
}

/* =====================================================
   EMPTY STATE
===================================================== */
.empty-lb {
  text-align:center;padding:4rem 2rem;
  display:flex;flex-direction:column;align-items:center;gap:1rem;
}
.empty-lb-icon svg { width:52px;height:52px;color:rgba(176,196,222,.2) }
.empty-lb-msg {
  font-family:var(--font-body);font-size:1rem;font-weight:300;
  color:rgba(176,196,222,.45);font-style:italic;
}

/* =====================================================
   FOOTER
===================================================== */
footer {
  position:relative;z-index:1;
  background:rgba(6,8,16,.95);
  border-top:1px solid var(--border);
  padding:4rem 5% 2rem;
}
.footer-top {
  display:grid;
  grid-template-columns:2fr 1fr 1fr 1fr;
  gap:3rem;margin-bottom:3rem;
}
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
.footer-col ul li a{font-family:var(--font-body);font-size:.9rem;font-weight:300;color:rgba(176,196,222,.48);text-decoration:none;transition:color .2s;display:inline-flex;align-items:center;gap:.5rem}
.footer-col ul li a:hover{color:var(--ivory)}
.footer-col ul li a svg { width:14px;height:14px;flex-shrink:0 }

.social-links{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.25rem}
.social-btn {
  width:38px;height:38px;border-radius:9px;
  background:var(--glass);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  text-decoration:none;color:var(--mist);transition:all .3s;
}
.social-btn svg { width:16px;height:16px }
.social-btn:hover{background:rgba(232,184,75,.1);border-color:var(--gold);color:var(--gold);transform:translateY(-3px)}

.footer-bottom{border-top:1px solid var(--border);padding-top:2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem}
.footer-bottom p{font-family:var(--font-mono);font-size:.66rem;letter-spacing:.1em;color:rgba(176,196,222,.28)}

/* =====================================================
   RESPONSIVE
===================================================== */
@media(max-width:1000px) {
  .podium-wrap { gap:.85rem }
  .podium-slot { width:190px }
  .footer-top { grid-template-columns:1fr 1fr }
}
@media(max-width:720px) {
  .podium-wrap { flex-direction:column;align-items:center }
  .podium-slot { width:100%;max-width:280px }
  .podium-first,.podium-second,.podium-third { order:unset }
  .podium-base { height:16px!important }
  .lb-tabs { gap:.45rem }
  .lb-tab { font-size:.72rem;padding:.42rem .95rem }
  .footer-top { grid-template-columns:1fr }
}
@media(max-width:600px) {
  .nav-links { display:none }
  .nav-links.open {
    display:flex;flex-direction:column;position:fixed;
    top:70px;left:0;right:0;
    background:rgba(6,8,16,.97);backdrop-filter:blur(20px);
    padding:1.5rem 5%;border-bottom:1px solid var(--border);
    gap:.25rem;z-index:999;
  }
  .nav-toggle { display:flex }
  .lb-table thead th:nth-child(3),
  .lb-table td:nth-child(3) { display:none }
  .your-stats-grid { grid-template-columns:1fr 1fr }
}
</style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav id="navbar">
  <a href="index.php" class="nav-logo">StoryVerse</a>
  <ul class="nav-links" id="navLinks">
    <li><a href="index.php">Home</a></li>
    <li><a href="stories.php">Stories</a></li>
    <?php if ($is_logged_in): ?>
      <li><a href="game_arena.php">Game Arena</a></li>
      <li><a href="leaderboard.php" class="active-nav">Leaderboard</a></li>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="logout.php" class="nav-btn">Log out</a></li>
    <?php else: ?>
      <li><a href="leaderboard.php" class="active-nav">Leaderboard</a></li>
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
    <span class="page-eyebrow">&#10022; &nbsp;Hall of Fame</span>
    <h1 class="page-title">Leaderboard</h1>
    <div class="page-divider"></div>
    <p class="page-sub">The finest minds of StoryVerse — ranked by prediction mastery, reading speed, and story intuition.</p>
  </div>
</div>

<!-- ===== MAIN ===== -->
<main>

  <!-- TAB NAVIGATION -->
  <div class="lb-tabs-wrap" data-aos="fade-up" data-aos-duration="600">
    <div class="lb-tabs">

      <button class="lb-tab active" data-tab="overall" onclick="switchTab('overall')">
        <span class="lb-tab-icon">
          <svg viewBox="0 0 20 20" fill="currentColor">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
          </svg>
        </span>
        Overall
      </button>

      <button class="lb-tab" data-tab="pred" onclick="switchTab('pred')">
        <span class="lb-tab-icon">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
          </svg>
        </span>
        Story Prediction
      </button>

      <button class="lb-tab" data-tab="flash" onclick="switchTab('flash')">
        <span class="lb-tab-icon">
          <svg viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
          </svg>
        </span>
        Flash Words
      </button>

      <button class="lb-tab" data-tab="scramble" onclick="switchTab('scramble')">
        <span class="lb-tab-icon">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h7"/>
          </svg>
        </span>
        Story Scramble
      </button>

    </div>
  </div>

  <!-- ===== PANEL: OVERALL ===== -->
  <div class="lb-panel active" id="panel-overall" data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
    <p class="section-label">Overall Rankings — Weighted Composite (Prediction 50% · Flash Words 30% · Story Scramble 20%)</p>
    <?php renderLeaderboard($overall_rows, 'overall', $user_id, $my_overall); ?>
  </div>

  <!-- ===== PANEL: STORY PREDICTION ===== -->
  <div class="lb-panel" id="panel-pred">
    <p class="section-label">Story Prediction — Ranked by Average Accuracy</p>
    <?php renderLeaderboard($pred_rows, 'pred', $user_id, $my_pred); ?>
  </div>

  <!-- ===== PANEL: FLASH WORDS ===== -->
  <div class="lb-panel" id="panel-flash">
    <p class="section-label">Flash Words — Ranked by Total Score &amp; Best WPM</p>
    <?php renderLeaderboard($flash_rows, 'flash', $user_id, $my_flash); ?>
  </div>

  <!-- ===== PANEL: STORY SCRAMBLE ===== -->
  <div class="lb-panel" id="panel-scramble">
    <p class="section-label">Story Scramble — Ranked by Total Score &amp; Correct Pairs</p>
    <?php renderLeaderboard($scramble_rows, 'scramble', $user_id, $my_scramble); ?>
  </div>

</main>

<!-- ===== FOOTER ===== -->
<footer>
  <div class="footer-top">
    <div class="footer-brand">
      <a href="index.php" class="footer-logo">StoryVerse</a>
      <p>An AI-driven interactive storytelling universe where every reader shapes the narrative. Where stories live, breathe, and evolve.</p>
      <div class="social-links">
        <!-- WhatsApp -->
        <a href="https://wa.me/yournumber" class="social-btn" title="WhatsApp" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
        </a>
        <!-- Instagram -->
        <a href="https://instagram.com/yourhandle" class="social-btn" title="Instagram" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor" stroke="none"/></svg>
        </a>
        <!-- GitHub -->
        <a href="https://github.com/yourrepo" class="social-btn" title="GitHub" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
        </a>
        <!-- LinkedIn -->
        <a href="https://linkedin.com/in/yourprofile" class="social-btn" title="LinkedIn" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
        </a>
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
        <li>
          <a href="https://wa.me/yournumber" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
            WhatsApp Us
          </a>
        </li>
        <li>
          <a href="https://instagram.com/yourhandle" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor" stroke="none"/></svg>
            Instagram
          </a>
        </li>
        <li>
          <a href="https://github.com/yourrepo" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
            GitHub
          </a>
        </li>
        <li>
          <a href="https://linkedin.com/in/yourprofile" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            LinkedIn
          </a>
        </li>
        <li><a href="mailto:hello@storyverse.com">hello@storyverse.com</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <p>&copy; <?php echo date('Y'); ?> StoryVerse. Crafted with narrative intelligence.</p>
    <p>Built with AI &middot; Powered by Stories &middot; Driven by Readers</p>
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

/* Tab switching */
function switchTab(tab) {
  // Update buttons
  document.querySelectorAll('.lb-tab').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tab);
  });
  // Update panels
  document.querySelectorAll('.lb-panel').forEach(panel => {
    panel.classList.toggle('active', panel.id === 'panel-' + tab);
  });
  // Scroll to top of main smoothly
  document.querySelector('main').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
</body>
</html>
