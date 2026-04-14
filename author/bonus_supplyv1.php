<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is an author
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    header('Location: signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$message = '';
$message_type = '';

// Python API endpoint
$python_api = "http://127.0.0.1:8000/story-predict";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_accuracy'])) {
    $prediction_id = (int)$_POST['prediction_id'];
    $accuracy = (float)$_POST['accuracy'];
    $bonus_amount = (float)$_POST['bonus_amount'];
    $updated_content = trim($_POST['upcoming_content']);
    $story_id = (int)$_POST['story_id'];
    $part_number = (int)$_POST['part_number'];
    
    try {
        $pdo->beginTransaction();
        
        // Update prediction accuracy and bonus eligibility
        $is_bonus_eligible = $accuracy >= 40 ? 1 : 0;
        $stmt = $pdo->prepare("
            UPDATE predictions 
            SET manual_accuracy = ?, is_bonus_eligible = ?
            WHERE prediction_id = ?
        ");
        $stmt->execute([$accuracy, $is_bonus_eligible, $prediction_id]);
        
        // Update upcoming part content if changed
        $stmt = $pdo->prepare("UPDATE story_parts SET content = ? WHERE story_id = ? AND part_number = ?");
        $stmt->execute([$updated_content, $story_id, $part_number]);
        
        // Insert bonus record if eligible
        if ($is_bonus_eligible && $bonus_amount > 0) {
            $stmt = $pdo->prepare("
                SELECT user_id FROM predictions WHERE prediction_id = ?
            ");
            $stmt->execute([$prediction_id]);
            $pred_user_id = $stmt->fetchColumn();
            
            // Check if bonus already awarded
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bonus WHERE prediction_id = ?");
            $stmt->execute([$prediction_id]);
            
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO bonus (prediction_id, user_id, story_id, part_no, bonus_amount, awarded_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$prediction_id, $pred_user_id, $story_id, $part_number, $bonus_amount]);
            } else {
                // Update existing bonus
                $stmt = $pdo->prepare("
                    UPDATE bonus SET bonus_amount = ? WHERE prediction_id = ?
                ");
                $stmt->execute([$bonus_amount, $prediction_id]);
            }
        }
        
        $pdo->commit();
        $message = 'Accuracy and bonus saved successfully!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Bonus supply error: " . $e->getMessage());
        $message = 'Error saving data: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Fetch author's stories with predictions
$stories_with_predictions = [];
try {
    $sql = "
        SELECT DISTINCT
            s.story_id,
            s.title,
            s.total_parts,
            s.current_part_no
        FROM stories s
        INNER JOIN predictions p ON s.story_id = p.story_id
        WHERE s.created_by = ?
        ORDER BY s.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_name]);
    $stories_with_predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching stories: " . $e->getMessage());
}

/*
// Function to call Python API
function calculateAccuracy($upcoming_content, $prediction_text) {
    global $python_api;
    
    $data = [
        'story' => $upcoming_content,
        'prediction' => $prediction_text
    ];
    
    $ch = curl_init($python_api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        return $result['confidence'] ?? 0;
    }

    $result = json_decode($response, true);
    return $result['confidence'] ?? 0;

    
    return null;
}
*/
// Python API endpoint
$python_api = 'http://127.0.0.1:8000/story-predict';

function calculateAccuracy($upcoming_content, $prediction_text) {
    global $python_api;

    if (empty(trim($upcoming_content)) || empty(trim($prediction_text))) {
        error_log("Empty input sent to AI");
        return 0;
    }

    $data = [
        'story' => $upcoming_content,
        'prediction' => $prediction_text
    ];

    $ch = curl_init($python_api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log("Curl error: " . curl_error($ch));
        curl_close($ch);
        return 0;
    }

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
    <title>Bonus Supply - Story Pulse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .story-section { border-left: 4px solid #667eea; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-book-open text-3xl text-purple-600"></i>
                        <span class="ml-2 text-2xl font-bold gradient-bg bg-clip-text text-transparent">Story Pulse</span>
                    </div>
                    <div class="hidden md:ml-10 md:flex md:space-x-8">
                        <a href="index.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-home mr-2"></i> Home
                        </a>
                        <a href="add_story.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-plus-circle mr-2"></i> Add Story
                        </a>
                        <a href="manage_story.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-tasks mr-2"></i> Manage Stories
                        </a>
                        <a href="bonus_supply.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-gift mr-2"></i> Bonus Supply
                        </a>
                        <a href="follows_likes.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-heart mr-2"></i> Follows & Likes
                        </a>
                        <a href="scoreboard.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-trophy mr-2"></i> Scoreboard
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
            <h1 class="text-3xl font-bold text-gray-900">Bonus Supply & Accuracy Verification</h1>
            <p class="mt-2 text-gray-600">Verify prediction accuracy and assign bonus coins to readers</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
            <div class="flex items-center">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-3 text-xl"></i>
                <p class="font-medium"><?php echo htmlspecialchars($message); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="flex space-x-4 mb-6">
            <a href="bonus_supply.php?filter=all" 
               class="px-4 py-2 rounded-lg font-medium <?php echo $filter === 'all' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                All Predictions
            </a>
            <a href="bonus_supply.php?filter=verified" 
               class="px-4 py-2 rounded-lg font-medium <?php echo $filter === 'verified' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                Verified
            </a>
            <a href="bonus_supply.php?filter=unverified" 
               class="px-4 py-2 rounded-lg font-medium <?php echo $filter === 'unverified' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
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
                // Get predictions for each part
                $parts_sql = "
                    SELECT DISTINCT prediction_part_no
                    FROM predictions
                    WHERE story_id = ?
                    ORDER BY prediction_part_no DESC
                ";
                $stmt = $pdo->prepare($parts_sql);
                $stmt->execute([$story['story_id']]);
                $parts = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($parts as $part_no):
                    // Get upcoming part content
                    $upcoming_part_no = $part_no + 1;
                    $stmt = $pdo->prepare("SELECT content FROM story_parts WHERE story_id = ? AND part_number = ?");
                    $stmt->execute([$story['story_id'], $upcoming_part_no]);
                    $upcoming_content = $stmt->fetchColumn();
                    
                    // Get predictions for this part
                    $pred_sql = "
                        SELECT 
                            p.prediction_id,
                            p.prediction_text,
                            p.manual_accuracy,
                            p.is_bonus_eligible,
                            p.created_at,
                            u.user_id,
                            u.user_name,
                            u.first_name,
                            u.last_name,
                            (SELECT COUNT(*) FROM likes WHERE prediction_id = p.prediction_id) as like_count,
                            (SELECT bonus_amount FROM bonus WHERE prediction_id = p.prediction_id) as bonus_given
                        FROM predictions p
                        JOIN users u ON p.user_id = u.user_id
                        WHERE p.story_id = ? AND p.prediction_part_no = ?
                    ";
                    
                    // Apply filter
                    if ($filter === 'verified') {
                        $pred_sql .= " AND p.manual_accuracy IS NOT NULL";
                    } elseif ($filter === 'unverified') {
                        $pred_sql .= " AND p.manual_accuracy IS NULL";
                    }
                    
                    $pred_sql .= " ORDER BY p.created_at DESC";
                    
                    $stmt = $pdo->prepare($pred_sql);
                    $stmt->execute([$story['story_id'], $part_no]);
                    $predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($predictions)) continue;
            ?>
            
            <!-- Story Part Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 story-section">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($story['title']); ?> - Part <?php echo $part_no; ?>
                        </h2>
                        <p class="text-sm text-gray-500">
                            <?php echo count($predictions); ?> prediction(s) | 
                            Upcoming Part: <?php echo $upcoming_part_no; ?>
                            <?php if (!$upcoming_content): ?>
                                <span class="text-red-600 font-semibold ml-2">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Please post Part <?php echo $upcoming_part_no; ?> immediately!
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <button onclick="togglePredictions('story_<?php echo $story['story_id']; ?>_part_<?php echo $part_no; ?>')" 
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <span id="btn_story_<?php echo $story['story_id']; ?>_part_<?php echo $part_no; ?>">Show All (<?php echo count($predictions); ?>)</span>
                    </button>
                </div>

                <!-- Predictions Table -->
                <div id="predictions_story_<?php echo $story['story_id']; ?>_part_<?php echo $part_no; ?>">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prediction</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Upcoming Content</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Accuracy %</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Max Bonus</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bonus Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Likes</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $display_predictions = array_slice($predictions, 0, 5);
                                $pred_index = 0;
                                foreach ($display_predictions as $pred): 
                                    $pred_index++;
                                ?>
                                <tr class="hover:bg-gray-50" id="pred_row_<?php echo $pred['prediction_id']; ?>">
                                    <form method="POST" onsubmit="return confirmSubmit()">
                                        <input type="hidden" name="prediction_id" value="<?php echo $pred['prediction_id']; ?>">
                                        <input type="hidden" name="story_id" value="<?php echo $story['story_id']; ?>">
                                        <input type="hidden" name="part_number" value="<?php echo $upcoming_part_no; ?>">
                                        
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($pred['first_name'] . ' ' . $pred['last_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($pred['user_name']); ?></div>
                                            <div class="text-xs text-gray-400">ID: <?php echo $pred['user_id']; ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <textarea readonly class="w-full p-2 border rounded text-sm" rows="3"><?php echo htmlspecialchars($pred['prediction_text']); ?></textarea>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($upcoming_content): ?>
                                                <textarea name="upcoming_content" class="w-full p-2 border rounded text-sm" rows="3"><?php echo htmlspecialchars($upcoming_content); ?></textarea>
                                            <?php else: ?>
                                                <span class="text-red-600 text-sm">Not posted yet</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="px-4 py-3">
                                            <input type="number"
                                                name="accuracy"
                                                id="accuracy_<?php echo $pred['prediction_id']; ?>"
                                                value="<?php echo $pred['manual_accuracy'] ?? ''; ?>"
                                                step="0.01" min="0" max="100"
                                                class="w-20 p-2 border rounded text-sm">
                                            <button type="button"
                                                class="ml-2 px-2 py-1 bg-blue-500 text-white rounded text-xs refresh-btn"
                                                data-pred-id="<?php echo $pred['prediction_id']; ?>"
                                                data-story="<?php echo htmlspecialchars($upcoming_content ?? '', ENT_QUOTES); ?>"
                                                data-prediction="<?php echo htmlspecialchars($pred['prediction_text'], ENT_QUOTES); ?>">
                                                🔄
                                            </button>

                                            <span id="loader_<?php echo $pred['prediction_id']; ?>"
                                                class="text-xs text-gray-500 ml-2 hidden">
                                                Calculating…
                                            </span>

                                            <button type="button"
                                                onclick="refreshAccuracy(
                                                    this,
                                                    <?php echo $pred['prediction_id']; ?>,
                                                    `<?php echo addslashes($upcoming_content ?? ''); ?>`,
                                                    `<?php echo addslashes($pred['prediction_text']); ?>`
                                                )"
                                                class="ml-2 px-2 py-1 bg-blue-500 text-white rounded text-xs">
                                                🔄
                                            </button>



                                        </td>

                                        <td class="px-4 py-3">
                                            <input type="number" 
                                                   id="max_bonus_<?php echo $pred['prediction_id']; ?>"
                                                   value="100" 
                                                   step="1" min="0"
                                                   class="w-20 p-2 border rounded text-sm"
                                                   onchange="calculateBonus(<?php echo $pred['prediction_id']; ?>)">
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="bonus_amount" 
                                                   id="bonus_<?php echo $pred['prediction_id']; ?>"
                                                   value="<?php echo $pred['bonus_given'] ?? '0'; ?>" 
                                                   step="0.01" min="0"
                                                   class="w-20 p-2 border rounded text-sm" readonly>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="flex items-center text-sm">
                                                <i class="fas fa-heart text-pink-500 mr-1"></i>
                                                <?php echo $pred['like_count']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <button type="submit" name="submit_accuracy" 
                                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                                <i class="fas fa-save mr-1"></i> Submit
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($predictions) > 5): ?>
                    <div class="mt-4 text-center">
                        <button onclick="showAllPredictions('story_<?php echo $story['story_id']; ?>_part_<?php echo $part_no; ?>')"
                                id="show_all_btn_story_<?php echo $story['story_id']; ?>_part_<?php echo $part_no; ?>"
                                class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            Show All <?php echo count($predictions); ?> Predictions
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php 
                endforeach;
            endforeach; 
            ?>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('click', function (e) {
            if (!e.target.classList.contains('refresh-btn')) return;

            const button = e.target;
            const predId = button.dataset.predId;
            const story = button.dataset.story;
            const prediction = button.dataset.prediction;

            refreshAccuracy(button, predId, story, prediction);
        });
        document.querySelectorAll('.refresh-btn').forEach(btn => {
            const predId = btn.dataset.predId;
            const accuracyInput = document.getElementById('accuracy_' + predId);

            if (!accuracyInput.value) {
                btn.click(); // auto-fetch AI accuracy
            }
        });
    </script>

    <script>
        function testPredict() {
            fetch('http://localhost:5000/predict', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    story: "Finally, he chose ambition over friendship and left the village to become a ruler.",
                    prediction: "He will betray his friend to gain power."
                })
            })
            .then(res => res.json())
            .then(data => {
                alert("AI RESPONSE:\n" + JSON.stringify(data, null, 2));
                console.log("AI response:", data);
            })
            .catch(err => {
                alert("Error calling /predict");
                console.error(err);
            });
        }
    </script>
    <!--  
    <script>
        async function refreshAccuracy(button, predId, upcomingContent, predictionText) {
            button.disabled = true;

            try {
                const response = await fetch('http://localhost:5000/predict', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        story: upcomingContent,
                        prediction: predictionText
                    })
                });

                const data = await response.json();
                console.log("AI response:", data);

                // ✅ USE CORRECT KEY
                if (data.confidence !== undefined) {
                    document.getElementById('accuracy_' + predId).value =
                        parseFloat(data.confidence).toFixed(2);
                } else {
                    alert("AI response missing confidence value");
                }

            } catch (err) {
                alert("Error connecting to AI server");
                console.error(err);
            }

            button.disabled = false;
        }
    </script>
    -->

    <script>
        function togglePredictions(id) {
            const element = document.getElementById('predictions_' + id);
            const btn = document.getElementById('btn_' + id);

            if (element.style.display === 'none') {
                element.style.display = 'block';
                btn.textContent = 'Hide Predictions';
            } else {
                element.style.display = 'none';
                btn.textContent = 'Show Predictions';
            }
        }
        function testAPI() {
            fetch('http://localhost:5000/')
                .then(res => res.text())
                .then(data => {
                    alert('Flask says: ' + data);
                })
                .catch(err => {
                    alert('Cannot reach Flask API');
                    console.error(err);
                });
        }
        
        function calculateBonus(predId) {
            const accuracy = parseFloat(
                document.getElementById('accuracy_' + predId).value
            );

            const maxBonus = parseFloat(
                document.getElementById('max_bonus_' + predId).value
            );

            if (isNaN(accuracy) || isNaN(maxBonus)) {
                document.getElementById('bonus_' + predId).value = 0;
                return;
            }

            const bonus = (accuracy / 100) * maxBonus;
            document.getElementById('bonus_' + predId).value = bonus.toFixed(2);
        }

        
        async function refreshAccuracy(button, predId, upcomingContent, predictionText) {
            const loader = document.getElementById('loader_' + predId);

            button.disabled = true;
            loader.classList.remove('hidden'); // show loader

            try {
                const response = await fetch("http://127.0.0.1:5000/predict", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        story: upcomingContent,
                        prediction: predictionText
                    })
                });

                const data = await response.json();

                if (data.confidence !== undefined) {
                    document.getElementById('accuracy_' + predId).value =
                        parseFloat(data.confidence).toFixed(2);
                }

            } catch (err) {
                alert("AI server error");
                console.error(err);
            }

            loader.classList.add('hidden'); // hide loader
            button.disabled = false;
        }



        function confirmSubmit() {
            return confirm('Are you sure you want to save this accuracy and bonus? This action will update the database.');
        }

        function showAllPredictions(id) {
            // This would load all predictions via AJAX or reload page with parameter
            alert('Feature to load all predictions - implement AJAX load or page reload with ?show_all=' + id);
        }
    </script>
</body>
</html>