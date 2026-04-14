<?php
session_start();
require_once 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is an author
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    header('Location: signin.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Python API endpoint
$python_api = 'http://127.0.0.1:8000/story-predict';

// ── AJAX: Save manual accuracy ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'save_accuracy') {
    header('Content-Type: application/json');
    $prediction_id = (int)$_POST['prediction_id'];
    $accuracy      = (float)$_POST['accuracy'];
    try {
        $is_bonus_eligible = $accuracy >= 40 ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE predictions SET manual_accuracy = ?, is_bonus_eligible = ? WHERE prediction_id = ?");
        $stmt->execute([$accuracy, $is_bonus_eligible, $prediction_id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── Get filter ──────────────────────────────────────────────────
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// ── Fetch author's stories with predictions ─────────────────────
$stories_with_predictions = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.story_id, s.title, s.total_parts, s.current_part_no
        FROM stories s
        INNER JOIN predictions p ON s.story_id = p.story_id
        WHERE s.created_by = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$user_name]);
    $stories_with_predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching stories: " . $e->getMessage());
}

function calculateAccuracy($upcoming_content, $prediction_text) {
    global $python_api;
    if (empty(trim($upcoming_content)) || empty(trim($prediction_text))) return 0;
    $data = ['story' => $upcoming_content, 'prediction' => $prediction_text];
    $ch = curl_init($python_api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    if ($response === false) { curl_close($ch); return 0; }
    curl_close($ch);
    $result = json_decode($response, true);
    return $result['confidence'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accuracy Review - Story Pulse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .story-section { border-left: 4px solid #667eea; }
        .score-input-sbert  { border-color: #93C5FD; background: #EFF6FF; }
        .score-input-ml     { border-color: #C4B5FD; background: #F5F3FF; }
        .score-input-acc    { border-color: #6EE7B7; background: #F0FDF9; font-weight: 600; }
        .use-btn {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 3px 8px; border-radius: 5px; font-size: 11px; font-weight: 600;
            border: 1px solid #6EE7B7; background: rgba(16,185,129,0.08);
            color: #059669; cursor: pointer; transition: all .15s; white-space: nowrap;
        }
        .use-btn:hover { background: rgba(16,185,129,0.18); border-color: #34D399; }
        .acc-saved   { color: #059669; font-size: 11px; }
        .acc-unsaved { color: #9CA3AF; font-size: 11px; }
        .expand-btn-wrap { background: #F9FAFB; border-top: 1px solid #E5E7EB; }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg sticky top-0 z-50" style="height:64px">
        <div class="max-w-full px-4 sm:px-6 lg:px-8 h-full">
            <div class="flex justify-between h-full">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-book-open text-3xl text-purple-600"></i>
                        <span class="ml-2 text-2xl font-bold gradient-bg bg-clip-text text-transparent">Story Pulse</span>
                    </div>
                    <div class="hidden md:ml-10 md:flex md:space-x-6">
                        <a href="index.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-home mr-2"></i> Home
                        </a>
                        <a href="add_story.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-plus-circle mr-2"></i> Add Story
                        </a>
                        <a href="manage_story.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-tasks mr-2"></i> Manage Stories
                        </a>
                        <a href="bonus_supply.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-clipboard-check mr-2"></i> Accuracy Review
                        </a>
                        <a href="dashboard.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-chart-line mr-2"></i> Dashboard
                        </a>
                        <a href="author_chat.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-comments mr-2"></i> Chat
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="logout.php" class="ml-4 px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Accuracy Review</h1>
            <p class="mt-2 text-gray-600">Review AI scores for reader predictions. Click <strong>Use</strong> on any SBERT or ML score to instantly save it as the manual accuracy — or enter it yourself. Bonus coins are managed by the admin.</p>
        </div>

        <!-- Filter tabs -->
        <div class="flex space-x-4 mb-6">
            <a href="bonus_supply.php?filter=all"
               class="px-4 py-2 rounded-lg font-medium <?= $filter === 'all' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
                All Predictions
            </a>
            <a href="bonus_supply.php?filter=verified"
               class="px-4 py-2 rounded-lg font-medium <?= $filter === 'verified' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
                Verified
            </a>
            <a href="bonus_supply.php?filter=unverified"
               class="px-4 py-2 rounded-lg font-medium <?= $filter === 'unverified' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
                Unverified
            </a>
        </div>

        <!-- Stories with Predictions -->
        <?php if (empty($stories_with_predictions)): ?>
            <div class="text-center py-16 bg-white rounded-xl shadow">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No predictions found for your stories yet</p>
                <p class="text-gray-400 text-sm mt-2">Please wait for readers to make predictions</p>
            </div>
        <?php else: ?>

        <?php foreach ($stories_with_predictions as $story):

            $parts_stmt = $pdo->prepare("SELECT DISTINCT prediction_part_no FROM predictions WHERE story_id = ? ORDER BY prediction_part_no DESC");
            $parts_stmt->execute([$story['story_id']]);
            $parts = $parts_stmt->fetchAll(PDO::FETCH_COLUMN);
        ?>

        <div class="mb-8 bg-white rounded-xl shadow overflow-hidden story-section">

            <!-- Story header -->
            <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-white border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-book text-purple-500 mr-2"></i>
                            <?= htmlspecialchars($story['title']) ?>
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5"><?= count($parts) ?> part<?= count($parts) !== 1 ? 's' : '' ?> with predictions</p>
                    </div>
                    <span class="text-xs font-mono text-purple-600 bg-purple-50 px-2 py-1 rounded-full border border-purple-100">
                        ID #<?= $story['story_id'] ?>
                    </span>
                </div>
            </div>

            <?php foreach ($parts as $part_no):
                $upcoming_part_no = $part_no + 1;

                $up_stmt = $pdo->prepare("SELECT content FROM story_parts WHERE story_id = ? AND part_number = ?");
                $up_stmt->execute([$story['story_id'], $upcoming_part_no]);
                $upcoming_content = $up_stmt->fetchColumn();

                $filter_sql = '';
                if ($filter === 'verified')   $filter_sql = "AND p.manual_accuracy IS NOT NULL";
                if ($filter === 'unverified') $filter_sql = "AND p.manual_accuracy IS NULL";

                $pred_stmt = $pdo->prepare("
                    SELECT
                        p.prediction_id, p.prediction_text, p.manual_accuracy, p.created_at,
                        u.user_id, u.user_name, u.first_name, u.last_name,
                        COALESCE(lk.lc, 0) AS like_count
                    FROM predictions p
                    INNER JOIN users u ON p.user_id = u.user_id
                    LEFT JOIN (SELECT prediction_id, COUNT(*) AS lc FROM likes GROUP BY prediction_id) lk ON lk.prediction_id = p.prediction_id
                    WHERE p.story_id = ? AND p.prediction_part_no = ?
                    $filter_sql
                    ORDER BY p.created_at DESC
                ");
                $pred_stmt->execute([$story['story_id'], $part_no]);
                $predictions = $pred_stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($predictions)) continue;

                $total_count = count($predictions);
                $section_key = "story_{$story['story_id']}_part_{$part_no}";
            ?>

            <!-- Part sub-header -->
            <div class="px-6 py-2 bg-gray-50 border-b border-gray-100 flex items-center gap-3">
                <span class="text-xs font-mono text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100">Part <?= $part_no ?></span>
                <span class="text-xs text-gray-500">
                    <?= $upcoming_content
                        ? 'Upcoming content (Part ' . $upcoming_part_no . ') is available — scores can be generated'
                        : 'Upcoming content not posted yet — AI scores unavailable' ?>
                </span>
            </div>

            <!-- Predictions table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reader</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prediction</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upcoming Content</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SBERT Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ML Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Manual Accuracy</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Likes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="tbody_<?= $section_key ?>">

                        <?php foreach ($predictions as $pidx => $pred):
                            $is_extra = $pidx >= 5;
                        ?>
                        <tr class="hover:bg-gray-50 pred-row"
                            style="<?= $is_extra ? 'display:none' : '' ?>"
                            data-auto-pred-id="<?= $pred['prediction_id'] ?>"
                            data-auto-story="<?= htmlspecialchars($upcoming_content ?? '', ENT_QUOTES) ?>"
                            data-auto-prediction="<?= htmlspecialchars($pred['prediction_text'], ENT_QUOTES) ?>">

                            <!-- Reader -->
                            <td class="px-4 py-3">
                                <div class="text-sm font-semibold text-gray-900">
                                    <?= htmlspecialchars($pred['first_name'] . ' ' . $pred['last_name']) ?>
                                </div>
                                <div class="text-xs text-gray-400">@<?= htmlspecialchars($pred['user_name']) ?></div>
                                <div class="text-xs text-gray-300 mt-0.5"><?= date('d M Y', strtotime($pred['created_at'])) ?></div>
                            </td>

                            <!-- Prediction -->
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-600 leading-relaxed max-w-xs">
                                    <?= htmlspecialchars($pred['prediction_text']) ?>
                                </div>
                            </td>

                            <!-- Upcoming Content -->
                            <td class="px-4 py-3">
                                <?php if ($upcoming_content): ?>
                                    <textarea readonly class="w-full p-2 border border-gray-200 rounded-lg text-xs text-gray-500 bg-gray-50 resize-none" rows="3"><?= htmlspecialchars($upcoming_content) ?></textarea>
                                <?php else: ?>
                                    <span class="text-red-400 text-xs font-medium">Not posted yet</span>
                                <?php endif; ?>
                            </td>

                            <!-- SBERT Score -->
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1.5">
                                    <input type="number"
                                           id="sbert_<?= $pred['prediction_id'] ?>"
                                           class="score-input-sbert w-24 p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                                           step="0.01" min="0" max="100"
                                           placeholder="—" readonly>
                                    <div class="flex items-center gap-1">
                                        <button type="button"
                                                id="sbert_btn_<?= $pred['prediction_id'] ?>"
                                                onclick="refreshSbert(<?= $pred['prediction_id'] ?>, <?= json_encode($upcoming_content ?? '') ?>, <?= json_encode($pred['prediction_text']) ?>)"
                                                class="flex items-center gap-1 px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded text-xs font-semibold transition-colors">
                                            <i class="fas fa-sync-alt text-xs" id="sbert_spinner_<?= $pred['prediction_id'] ?>"></i>
                                            SBERT
                                        </button>
                                        <button type="button" class="use-btn"
                                                title="Use SBERT score as manual accuracy"
                                                onclick="useScore('sbert', <?= $pred['prediction_id'] ?>)">
                                            <i class="fas fa-check" style="font-size:9px"></i> Use
                                        </button>
                                    </div>
                                </div>
                            </td>

                            <!-- ML Score -->
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1.5">
                                    <input type="number"
                                           id="ml_<?= $pred['prediction_id'] ?>"
                                           class="score-input-ml w-24 p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-300"
                                           step="0.01" min="0" max="100"
                                           placeholder="—" readonly>
                                    <div class="flex items-center gap-1">
                                        <button type="button"
                                                id="ml_btn_<?= $pred['prediction_id'] ?>"
                                                onclick="refreshMl(<?= $pred['prediction_id'] ?>, <?= json_encode($upcoming_content ?? '') ?>, <?= json_encode($pred['prediction_text']) ?>)"
                                                class="flex items-center gap-1 px-2 py-1 bg-purple-100 hover:bg-purple-200 text-purple-700 rounded text-xs font-semibold transition-colors">
                                            <i class="fas fa-sync-alt text-xs" id="ml_spinner_<?= $pred['prediction_id'] ?>"></i>
                                            ML
                                        </button>
                                        <button type="button" class="use-btn"
                                                title="Use ML score as manual accuracy"
                                                onclick="useScore('ml', <?= $pred['prediction_id'] ?>)">
                                            <i class="fas fa-check" style="font-size:9px"></i> Use
                                        </button>
                                    </div>
                                </div>
                            </td>

                            <!-- Manual Accuracy -->
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1">
                                    <input type="number"
                                           id="accuracy_<?= $pred['prediction_id'] ?>"
                                           class="score-input-acc w-24 p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-300"
                                           step="0.01" min="0" max="100"
                                           value="<?= $pred['manual_accuracy'] !== null ? htmlspecialchars($pred['manual_accuracy']) : '' ?>"
                                           placeholder="Enter manually"
                                           onchange="saveAccuracy(<?= $pred['prediction_id'] ?>)">
                                    <span id="acc_status_<?= $pred['prediction_id'] ?>"
                                          class="<?= $pred['manual_accuracy'] !== null ? 'acc-saved' : 'acc-unsaved' ?>">
                                        <?= $pred['manual_accuracy'] !== null ? 'Saved in DB' : 'Not set yet' ?>
                                    </span>
                                </div>
                            </td>

                            <!-- Likes -->
                            <td class="px-4 py-3">
                                <span class="flex items-center text-sm text-gray-500">
                                    <i class="fas fa-heart text-pink-500 mr-1.5"></i>
                                    <?= (int)$pred['like_count'] ?>
                                </span>
                            </td>

                        </tr>
                        <?php endforeach; // predictions ?>

                    </tbody>
                </table>
            </div>

            <?php if ($total_count > 5): ?>
            <div class="expand-btn-wrap text-center py-2">
                <button onclick="toggleExpand('<?= $section_key ?>', <?= $total_count ?>)"
                        id="expand_btn_<?= $section_key ?>"
                        data-open="0"
                        class="px-6 py-2 text-sm font-semibold text-gray-600 hover:text-purple-700 hover:bg-purple-50 rounded-lg transition-colors">
                    <i class="fas fa-chevron-down mr-1" id="expand_icon_<?= $section_key ?>"></i>
                    Show all <?= $total_count ?> predictions
                </button>
            </div>
            <?php endif; ?>

            <?php endforeach; // parts ?>
        </div>
        <?php endforeach; // stories ?>

        <?php endif; ?>
    </div>

    <script>
        // ─────────────────────────────────────────────
        // Core: call /evaluate-story and return scores
        // ─────────────────────────────────────────────
        async function callEvaluateStory(story, prediction) {
            const response = await fetch("http://127.0.0.1:8000/evaluate-story", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ story: story, prediction: prediction })
            });
            if (!response.ok) throw new Error("API returned HTTP " + response.status);
            return await response.json();
        }

        // ─────────────────────────────────────────────
        // Refresh SBERT score only
        // ─────────────────────────────────────────────
        async function refreshSbert(predId, story, prediction) {
            if (!story || !prediction) {
                alert("Upcoming content not available yet — cannot calculate score.");
                return;
            }
            const btn     = document.getElementById('sbert_btn_' + predId);
            const spinner = document.getElementById('sbert_spinner_' + predId);
            const input   = document.getElementById('sbert_' + predId);

            btn.disabled = true;
            spinner.classList.add('fa-spin');

            try {
                const data = await callEvaluateStory(story, prediction);
                if (data && data.scores && data.scores.sbert_score !== undefined) {
                    input.value = parseFloat(data.scores.sbert_score).toFixed(2);
                } else {
                    alert("Unexpected API response — SBERT score missing.");
                    console.error("API response:", data);
                }
            } catch (err) {
                alert("Could not reach AI server. Is it running on port 8000?");
                console.error("SBERT refresh error:", err);
            }

            spinner.classList.remove('fa-spin');
            btn.disabled = false;
        }

        // ─────────────────────────────────────────────
        // Refresh ML score only
        // ─────────────────────────────────────────────
        async function refreshMl(predId, story, prediction) {
            if (!story || !prediction) {
                alert("Upcoming content not available yet — cannot calculate score.");
                return;
            }
            const btn     = document.getElementById('ml_btn_' + predId);
            const spinner = document.getElementById('ml_spinner_' + predId);
            const input   = document.getElementById('ml_' + predId);

            btn.disabled = true;
            spinner.classList.add('fa-spin');

            try {
                const data = await callEvaluateStory(story, prediction);
                if (data && data.scores && data.scores.ml_dataset_score !== undefined) {
                    input.value = parseFloat(data.scores.ml_dataset_score).toFixed(2);
                } else {
                    alert("Unexpected API response — ML score missing.");
                    console.error("API response:", data);
                }
            } catch (err) {
                alert("Could not reach AI server. Is it running on port 8000?");
                console.error("ML refresh error:", err);
            }

            spinner.classList.remove('fa-spin');
            btn.disabled = false;
        }

        // ─────────────────────────────────────────────
        // "Use" button: copy score → accuracy + AJAX save
        // ─────────────────────────────────────────────
        async function useScore(type, predId) {
            const srcInp = document.getElementById(type + '_' + predId);
            const val    = srcInp ? srcInp.value.trim() : '';
            if (!val) {
                alert('Generate the ' + type.toUpperCase() + ' score first by clicking the refresh button.');
                return;
            }
            document.getElementById('accuracy_' + predId).value = parseFloat(val).toFixed(2);
            await saveAccuracy(predId);
        }

        // ─────────────────────────────────────────────
        // AJAX: save manual accuracy to DB
        // ─────────────────────────────────────────────
        async function saveAccuracy(predId) {
            const inp = document.getElementById('accuracy_' + predId);
            const acc = parseFloat(inp.value);
            if (isNaN(acc)) return;

            const fd = new FormData();
            fd.append('ajax_action',   'save_accuracy');
            fd.append('prediction_id', predId);
            fd.append('accuracy',      acc);

            try {
                const r = await fetch('bonus_supply.php', { method: 'POST', body: fd });
                const d = await r.json();
                const status = document.getElementById('acc_status_' + predId);
                if (d.success) {
                    status.textContent = 'Saved in DB';
                    status.className   = 'acc-saved';
                } else {
                    alert(d.msg || 'Failed to save accuracy');
                }
            } catch {
                alert('Request failed. Please check your connection.');
            }
        }

        // ─────────────────────────────────────────────
        // Auto-generate both scores on page load
        // ─────────────────────────────────────────────
        async function autoLoadScores(predId, story, prediction) {
            if (!story || !prediction) return;

            const sbertInput = document.getElementById('sbert_' + predId);
            const mlInput    = document.getElementById('ml_'    + predId);

            // Don't auto-fetch if both already have values
            if (sbertInput.value && mlInput.value) return;

            try {
                const data = await callEvaluateStory(story, prediction);
                if (data && data.scores) {
                    if (data.scores.sbert_score !== undefined)
                        sbertInput.value = parseFloat(data.scores.sbert_score).toFixed(2);
                    if (data.scores.ml_dataset_score !== undefined)
                        mlInput.value    = parseFloat(data.scores.ml_dataset_score).toFixed(2);
                }
            } catch (err) {
                console.warn("Auto-load failed for pred " + predId + ":", err.message);
            }
        }

        // ─────────────────────────────────────────────
        // Toggle expand / collapse extra rows
        // ─────────────────────────────────────────────
        function toggleExpand(key, total) {
            const tbody = document.getElementById('tbody_' + key);
            const rows  = tbody ? Array.from(tbody.querySelectorAll('tr')).filter((_, i) => i >= 5) : [];
            const btn   = document.getElementById('expand_btn_' + key);
            const icon  = document.getElementById('expand_icon_'  + key);
            const open  = btn.dataset.open === '1';

            rows.forEach(r => r.style.display = open ? 'none' : '');
            btn.dataset.open = open ? '0' : '1';

            if (open) {
                icon.className = 'fas fa-chevron-down mr-1';
                btn.querySelector('i').nextSibling.textContent = ' Show all ' + total + ' predictions';
            } else {
                icon.className = 'fas fa-chevron-up mr-1';
                btn.querySelector('i').nextSibling.textContent = ' Collapse';
            }
        }

        // ─────────────────────────────────────────────
        // On page load: auto-generate scores for all rows
        // ─────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-auto-pred-id]').forEach(function(el) {
                const predId     = el.dataset.autoPredId;
                const story      = el.dataset.autoStory;
                const prediction = el.dataset.autoPrediction;
                autoLoadScores(predId, story, prediction);
            });
        });
    </script>

</body>
</html>
