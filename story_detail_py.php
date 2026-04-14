<?php
session_start();
require_once 'db_connect.php'; // Your database connection

// Check if user is logged in. If not, redirect to login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$current_user_id = $_SESSION['user_id']; // Get current logged-in user ID
$storyId = $_GET['story_id'] ?? null;
$partNumber = $_GET['part_number'] ?? null;
$message = null;

if (empty($storyId) || empty($partNumber)) {
    header('Location: index.php'); // Redirect to a safe page if no story/part is specified
    exit;
}

// --- Handle AJAX POST Requests (JSON) ---
// This is the most crucial part. We check if the request is a JSON request
// and handle it immediately, then exit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check if JSON decoding was successful
    if ($input === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
        exit;
    }

    $action = $input['action'] ?? null;
    $response = ['success' => false, 'message' => 'Invalid action.'];

    try {
        switch ($action) {
            case 'like':
                $prediction_id = $input['prediction_id'];
                $like_action = $input['like_action'];

                if ($like_action === 'like') {
                    $stmt = $pdo->prepare("INSERT INTO Likes (prediction_id, user_id) VALUES (:prediction_id, :userId)");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM Likes WHERE prediction_id = :prediction_id AND user_id = :userId");
                }
                $stmt->execute([':prediction_id' => $prediction_id, ':userId' => $current_user_id]);

                // Get updated like count
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Likes WHERE prediction_id = :prediction_id");
                $stmt->execute([':prediction_id' => $prediction_id]);
                $new_like_count = $stmt->fetchColumn();

                $response = ['success' => true, 'new_like_count' => $new_like_count];
                break;

            case 'edit_prediction':
                $prediction_id = $input['prediction_id'];
                $new_prediction_text = $input['new_prediction_text'];

                $stmt = $pdo->prepare("UPDATE Predictions SET prediction_text = :text WHERE prediction_id = :id AND user_id = :userId");
                $stmt->execute([
                    ':text' => $new_prediction_text,
                    ':id' => $prediction_id,
                    ':userId' => $current_user_id
                ]);

                if ($stmt->rowCount() > 0) {
                    $response = ['success' => true, 'message' => 'Prediction updated successfully!'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to update prediction. Check if you own this prediction.'];
                }
                break;

            case 'delete_prediction':
                $prediction_id = $input['prediction_id'];
                
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("DELETE FROM Likes WHERE prediction_id = :id");
                $stmt->execute([':id' => $prediction_id]);

                $stmt = $pdo->prepare("DELETE FROM Predictions WHERE prediction_id = :id AND user_id = :userId");
                $stmt->execute([
                    ':id' => $prediction_id,
                    ':userId' => $current_user_id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $pdo->commit();
                    $response = ['success' => true, 'message' => 'Prediction deleted successfully!'];
                } else {
                    $pdo->rollBack();
                    $response = ['success' => false, 'message' => 'Failed to delete prediction. You can only delete your own predictions.'];
                }
                break;
            
            default:
                $response = ['success' => false, 'message' => 'Invalid action.'];
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- Handle traditional Form Submissions (for new predictions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prediction'])) {
    $userPrediction = $_POST['prediction_text'] ?? '';
    if (empty($userPrediction)) {
        $message = ['status' => 'error', 'message' => 'Prediction text cannot be empty.'];
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO Predictions (story_id, prediction_part_no, user_id, prediction_text) VALUES (:storyId, :partNumber, :userId, :predictionText)");
            $stmt->execute([
                ':storyId' => $storyId,
                ':partNumber' => $partNumber,
                ':userId' => $current_user_id,
                ':predictionText' => $userPrediction
            ]);
            $message = ['status' => 'success', 'message' => 'Prediction submitted successfully!'];
        } catch (PDOException $e) {
            $message = ['status' => 'error', 'message' => 'Error submitting prediction: ' . $e->getMessage()];
        }
    }
}


// --- Fetch Story Part and Predictions Data ---
try {
    // Fetch story part details
    $stmt = $pdo->prepare("SELECT * FROM Story_Parts WHERE story_id = :storyId AND part_number = :partNumber");
    $stmt->execute([':storyId' => $storyId, ':partNumber' => $partNumber]);
    $storyPart = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch predictions for this story part
    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            u.user_name, 
            (SELECT COUNT(*) FROM Likes WHERE prediction_id = p.prediction_id) as like_count,
            (SELECT COUNT(*) FROM Likes WHERE prediction_id = p.prediction_id AND user_id = :currentUserId) as user_liked
        FROM Predictions p
        JOIN Users u ON p.user_id = u.user_id
        WHERE p.story_id = :storyId AND p.prediction_part_no = :partNumber
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([':storyId' => $storyId, ':partNumber' => $partNumber, ':currentUserId' => $current_user_id]);
    $predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

if (!$storyPart) {
    echo "Story part not found.";
    exit;
}

$pdo = null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Story Detail</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">

    <!-- Navbar -->
    <nav class="bg-white shadow-lg p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">Story Part <?= htmlspecialchars($storyPart['part_number']) ?></h1>
            <a href="manage_stories.php" class="text-blue-500 hover:underline">Back to Stories</a>
        </div>
    </nav>

    <main class="container mx-auto p-4 md:p-8">

        <!-- Message/Alert Area -->
        <div id="message-container" class="mb-4 hidden p-4 rounded-lg"></div>
        <?php if ($message): ?>
            <div id="php-message-container" class="mb-4 p-4 rounded-lg <?= $message['status'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= htmlspecialchars($message['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Story Part Content -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Content</h2>
            <div class="prose max-w-none text-gray-700 leading-relaxed">
                <p><?= nl2br(htmlspecialchars($storyPart['content'])) ?></p>
            </div>
            <p class="text-sm text-gray-500 mt-4">
                Upload Date: <?= htmlspecialchars($storyPart['upload_date']) ?> | Prediction Deadline: <?= htmlspecialchars($storyPart['prediction_deadline']) ?>
            </p>
        </div>

        <!-- Submit New Prediction Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Make Your Prediction</h2>
            <form action="story_detail.php?story_id=<?= htmlspecialchars($storyId) ?>&part_number=<?= htmlspecialchars($partNumber) ?>" method="POST">
                <input type="hidden" name="story_id" value="<?= htmlspecialchars($storyId) ?>">
                <input type="hidden" name="part_number" value="<?= htmlspecialchars($partNumber) ?>">
                <textarea name="prediction_text" rows="5" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Write your prediction here..." required></textarea>
                <button type="submit" name="submit_prediction" class="mt-4 w-full md:w-auto px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition-colors">Submit Prediction</button>
            </form>
        </div>

        <!-- Predictions Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Community Predictions</h2>
            <?php if (empty($predictions)): ?>
                <p class="text-gray-500 text-center">No predictions yet. Be the first to submit one!</p>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($predictions as $prediction): ?>
                        <div class="border rounded-lg p-4 shadow-sm relative">
                            <div class="flex items-center mb-2">
                                <span class="text-sm font-bold text-gray-700 mr-2">
                                    By: <?= htmlspecialchars($prediction['user_name']) ?>
                                </span>
                                <span class="text-xs text-gray-500">
                                    on <?= htmlspecialchars($prediction['created_at']) ?>
                                </span>
                            </div>
                            <div class="prose text-gray-700 mb-4">
                                <p><?= nl2br(htmlspecialchars($prediction['prediction_text'])) ?></p>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <!-- Like button -->
                                    <button class="flex items-center text-gray-500 hover:text-red-500 like-btn" data-prediction-id="<?= htmlspecialchars($prediction['prediction_id']) ?>" data-action="<?= $prediction['user_liked'] ? 'unlike' : 'like' ?>">
                                        <i class="fas fa-heart text-lg <?= $prediction['user_liked'] ? 'text-red-500' : 'text-gray-400' ?>"></i>
                                        <span class="ml-1 text-sm like-count" data-prediction-id="<?= htmlspecialchars($prediction['prediction_id']) ?>"><?= htmlspecialchars($prediction['like_count']) ?></span>
                                    </button>
                                </div>
                                
                                <!-- Edit & Delete Buttons (Only for the current user) -->
                                <?php if ($prediction['user_id'] == $current_user_id): ?>
                                    <div class="flex space-x-2">
                                        <button class="bg-yellow-500 text-white px-3 py-1 text-sm rounded-md hover:bg-yellow-600 transition-colors edit-btn" data-prediction-id="<?= htmlspecialchars($prediction['prediction_id']) ?>" data-prediction-text="<?= htmlspecialchars($prediction['prediction_text']) ?>">Edit</button>
                                        <button class="bg-red-500 text-white px-3 py-1 text-sm rounded-md hover:bg-red-600 transition-colors delete-btn" data-prediction-id="<?= htmlspecialchars($prediction['prediction_id']) ?>">Delete</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Edit Prediction Modal -->
    <div id="edit-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex justify-center items-center">
        <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
            <h2 class="text-xl font-bold mb-4">Edit Your Prediction</h2>
            <form id="edit-form">
                <input type="hidden" name="prediction_id" id="edit-prediction-id">
                <textarea name="new_prediction_text" id="edit-prediction-text" rows="5" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                <div class="mt-4 flex justify-end space-x-2">
                    <button type="button" id="cancel-edit-btn" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex justify-center items-center">
        <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-sm">
            <h2 class="text-xl font-bold mb-4">Confirm Deletion</h2>
            <p>Are you sure you want to delete this prediction? This action cannot be undone.</p>
            <div class="mt-4 flex justify-end space-x-2">
                <button type="button" id="cancel-delete-btn" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</button>
                <button type="button" id="confirm-delete-btn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const likeButtons = document.querySelectorAll('.like-btn');
            const editButtons = document.querySelectorAll('.edit-btn');
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const editModal = document.getElementById('edit-modal');
            const deleteModal = document.getElementById('delete-modal');
            const cancelEditBtn = document.getElementById('cancel-edit-btn');
            const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
            const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
            const editForm = document.getElementById('edit-form');
            const editPredictionIdInput = document.getElementById('edit-prediction-id');
            const editPredictionTextarea = document.getElementById('edit-prediction-text');
            
            // PHP variables for story details
            const storyId = <?= json_encode($storyId); ?>;
            const partNumber = <?= json_encode($partNumber); ?>;
            
            let predictionIdToDelete = null;

            // --- Function to show/hide messages ---
            function showMessage(message, status) {
                const messageContainer = document.getElementById('message-container');
                messageContainer.textContent = message;
                messageContainer.classList.remove('hidden', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
                if (status === 'success') {
                    messageContainer.classList.add('bg-green-100', 'text-green-700');
                } else {
                    messageContainer.classList.add('bg-red-100', 'text-red-700');
                }
                setTimeout(() => {
                    messageContainer.classList.add('hidden');
                }, 5000);
            }

            // --- Like/Unlike functionality ---
            likeButtons.forEach(button => {
                button.addEventListener('click', async () => {
                    const predictionId = button.dataset.predictionId;
                    const action = button.dataset.action;
                    const likeCountSpan = document.querySelector(`.like-count[data-prediction-id=\"${predictionId}\"]`);
                    const heartIcon = button.querySelector('i');

                    try {
                        const response = await fetch('story_detail.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'like',
                                prediction_id: predictionId,
                                like_action: action
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            likeCountSpan.textContent = data.new_like_count;
                            if (action === 'like') {
                                heartIcon.classList.remove('text-gray-400');
                                heartIcon.classList.add('text-red-500');
                                button.dataset.action = 'unlike';
                            } else {
                                heartIcon.classList.remove('text-red-500');
                                heartIcon.classList.add('text-gray-400');
                                button.dataset.action = 'like';
                            }
                        } else {
                            showMessage('Failed to update like: ' + data.message, 'error');
                        }
                    } catch (error) {
                        showMessage('Error sending like request: ' + error.message, 'error');
                    }
                });
            });

            // --- Edit button functionality (show modal) ---
            editButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const predictionId = button.dataset.predictionId;
                    const predictionText = button.dataset.predictionText;
                    editPredictionIdInput.value = predictionId;
                    editPredictionTextarea.value = predictionText;
                    editModal.classList.remove('hidden');
                });
            });

            cancelEditBtn.addEventListener('click', () => {
                editModal.classList.add('hidden');
            });

            // --- Edit form submission (via fetch) ---
            editForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const predictionId = editPredictionIdInput.value;
                const newPredictionText = editPredictionTextarea.value;
                
                try {
                    const response = await fetch(`story_detail.php?story_id=${storyId}&part_number=${partNumber}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'edit_prediction',
                            prediction_id: predictionId,
                            new_prediction_text: newPredictionText
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        editModal.classList.add('hidden');
                        showMessage(result.message, 'success');
                        setTimeout(() => window.location.reload(), 1000); // Reload to show the updated prediction
                    } else {
                        showMessage(result.message, 'error');
                    }
                } catch (error) {
                    showMessage('Error updating prediction: ' + error.message, 'error');
                }
            });

            // --- Delete button functionality (show modal) ---
            deleteButtons.forEach(button => {
                button.addEventListener('click', () => {
                    predictionIdToDelete = button.dataset.predictionId;
                    deleteModal.classList.remove('hidden');
                });
            });

            cancelDeleteBtn.addEventListener('click', () => {
                deleteModal.classList.add('hidden');
            });
            
            confirmDeleteBtn.addEventListener('click', async () => {
                deleteModal.classList.add('hidden');
                if (predictionIdToDelete) {
                    try {
                        const response = await fetch(`story_detail.php?story_id=${storyId}&part_number=${partNumber}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'delete_prediction',
                                prediction_id: predictionIdToDelete
                            })
                        });

                        const result = await response.json();
                        
                        if (result.success) {
                            showMessage(result.message, 'success');
                            setTimeout(() => window.location.reload(), 1000); // Reload to show the updated list
                        } else {
                            showMessage(result.message, 'error');
                        }
                    } catch (error) {
                        showMessage('Error deleting prediction: ' + error.message, 'error');
                    }
                }
            });

            // Auto-hide success/error message from PHP if it exists
            const phpMessageContainer = document.getElementById('php-message-container');
            if (phpMessageContainer) {
                setTimeout(() => {
                    phpMessageContainer.style.transition = 'opacity 0.5s ease-out';
                    phpMessageContainer.style.opacity = '0';
                    setTimeout(() => phpMessageContainer.remove(), 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
