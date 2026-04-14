<?php
session_start();
require_once 'db_connect.php';

// Ensure only authors can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    header('Location: ../signin.php');
    exit();
}

$message = '';
$message_type = '';
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch only APPROVED stories for this author (can't add parts to pending/rejected)
$existing_stories = [];
try {
    $stmt = $pdo->prepare("
        SELECT story_id, title, current_part_no, total_parts, status
        FROM stories
        WHERE created_by = ? AND status = 'approved'
        ORDER BY title ASC
    ");
    $stmt->execute([$user_name]);
    $existing_stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching existing stories: " . $e->getMessage());
    $message      = 'Error loading existing stories.';
    $message_type = 'error';
}

// Fetch ALL stories for status overview panel
$all_stories = [];
try {
    $stmt = $pdo->prepare("
        SELECT story_id, title, status, current_part_no, total_parts
        FROM stories
        WHERE created_by = ?
        ORDER BY story_id DESC
    ");
    $stmt->execute([$user_name]);
    $all_stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching all stories: " . $e->getMessage());
}

// ── Handle NEW STORY submission ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_story'])) {
    $title               = trim($_POST['title']);
    $created_by          = $user_name;
    $category            = trim($_POST['category']);
    $description         = trim($_POST['description']);
    $cover_image_url     = trim($_POST['cover_image_url']);
    $total_parts         = (int)$_POST['total_parts'];
    $part_content        = trim($_POST['part_content']);
    $upload_date         = $_POST['upload_date'];
    $prediction_deadline = $_POST['prediction_deadline'];

    if (empty($title) || empty($description) || empty($part_content) || empty($upload_date) || empty($prediction_deadline)) {
        $message      = 'All required fields must be filled.';
        $message_type = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert story with status = pending
            $stmt = $pdo->prepare("
                INSERT INTO stories (title, created_by, category, description, cover_image_url, total_parts, current_part_no, status)
                VALUES (?, ?, ?, ?, ?, ?, 1, 'pending')
            ");
            $stmt->execute([$title, $created_by, $category, $description, $cover_image_url, $total_parts]);
            $story_id = $pdo->lastInsertId();

            // Insert Part 1 with status = pending
            $stmt = $pdo->prepare("
                INSERT INTO story_parts (story_id, part_number, content, upload_date, prediction_deadline, status)
                VALUES (?, 1, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$story_id, $part_content, $upload_date, $prediction_deadline]);

            // Update author story count
            $stmt = $pdo->prepare("UPDATE users SET total_stories = total_stories + 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $pdo->commit();
            $message      = 'Story "' . htmlspecialchars($title) . '" submitted for admin review. It will go live once approved.';
            $message_type = 'success';

            // Refresh story lists
            $stmt = $pdo->prepare("SELECT story_id, title, current_part_no, total_parts, status FROM stories WHERE created_by = ? AND status = 'approved' ORDER BY title ASC");
            $stmt->execute([$user_name]);
            $existing_stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT story_id, title, status, current_part_no, total_parts FROM stories WHERE created_by = ? ORDER BY story_id DESC");
            $stmt->execute([$user_name]);
            $all_stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Add New Story Error: " . $e->getMessage());
            $message      = 'An error occurred: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ── Handle NEW PART submission ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_part'])) {
    $story_id            = (int)$_POST['existing_story_id'];
    $part_number         = (int)$_POST['new_part_number'];
    $part_content        = trim($_POST['new_part_content']);
    $upload_date         = $_POST['new_upload_date'];
    $prediction_deadline = $_POST['new_prediction_deadline'];

    if (empty($story_id) || empty($part_number) || empty($part_content) || empty($upload_date) || empty($prediction_deadline)) {
        $message      = 'All fields for new part are required.';
        $message_type = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            // Verify story belongs to this author and is approved
            $stmt = $pdo->prepare("SELECT story_id FROM stories WHERE story_id = ? AND created_by = ? AND status = 'approved'");
            $stmt->execute([$story_id, $user_name]);
            if (!$stmt->fetch()) {
                $message      = 'Invalid story selected or story is not approved yet.';
                $message_type = 'error';
                $pdo->rollBack();
            } else {
                // Check part number conflict
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM story_parts WHERE story_id = ? AND part_number = ?");
                $stmt->execute([$story_id, $part_number]);

                if ($stmt->fetchColumn() > 0) {
                    $message      = 'Part ' . $part_number . ' already exists for this story.';
                    $message_type = 'error';
                    $pdo->rollBack();
                } else {
                    // Insert new part with status = pending
                    $stmt = $pdo->prepare("
                        INSERT INTO story_parts (story_id, part_number, content, upload_date, prediction_deadline, status)
                        VALUES (?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([$story_id, $part_number, $part_content, $upload_date, $prediction_deadline]);

                    // Update current_part_no tracker
                    $stmt = $pdo->prepare("UPDATE stories SET current_part_no = GREATEST(current_part_no, ?) WHERE story_id = ?");
                    $stmt->execute([$part_number, $story_id]);

                    $pdo->commit();
                    $message      = 'Part ' . $part_number . ' submitted for admin review. It will appear once approved.';
                    $message_type = 'success';
                }
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Add New Part Error: " . $e->getMessage());
            $message      = 'An error occurred: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Story — StoryVerse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

        /* Status badge colours */
        .badge-pending  { background:#FEF3C7; color:#92400E; border:1px solid #FCD34D; }
        .badge-approved { background:#D1FAE5; color:#065F46; border:1px solid #6EE7B7; }
        .badge-rejected { background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5; }

        /* Review notice banner */
        .review-notice {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            border: 1px solid #93C5FD;
            border-radius: 12px;
            padding: 14px 18px;
        }
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
                    <a href="add_story.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-plus-circle mr-2"></i> Add Story
                    </a>
                    <a href="manage_story.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-tasks mr-2"></i> Manage Stories
                    </a>
                    <a href="bonus_supply.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-clipboard-check mr-2"></i> Accuracy Review
                    </a>
                    <a href="dashboard.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        <i class="fas fa-chart-line mr-2"></i> Dashboard
                    </a>
                    <a href="author_chat.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
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
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Add Story & Parts</h1>
        <p class="mt-1 text-gray-500">All submissions go through admin review before going live.</p>
    </div>

    <!-- Admin review notice -->
    <div class="review-notice mb-6 flex items-start gap-3">
        <i class="fas fa-shield-alt text-blue-500 mt-0.5 flex-shrink-0"></i>
        <div>
            <p class="text-sm font-semibold text-blue-800">Admin Review Required</p>
            <p class="text-sm text-blue-700 mt-0.5">
                New stories and new parts are reviewed by an admin before they appear to readers.
                You will see the status of each submission in the panel below.
            </p>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg flex items-start gap-3
            <?= $message_type === 'success'
                ? 'bg-green-50 border border-green-300 text-green-800'
                : 'bg-red-50 border border-red-300 text-red-800' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> mt-0.5 flex-shrink-0"></i>
            <p class="font-medium"><?= htmlspecialchars($message) ?></p>
        </div>
    <?php endif; ?>

    <!-- Forms Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">

        <!-- ── Add New Story ─────────────────────────────── -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center mb-6">
                <div class="bg-purple-100 rounded-full p-3 mr-4">
                    <i class="fas fa-book text-purple-600 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Add New Story</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Will be submitted as pending</p>
                </div>
            </div>

            <form action="add_story.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Story Title *</label>
                    <input type="text" name="title" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Author Name</label>
                    <input type="text" value="<?= htmlspecialchars($user_name) ?>" readonly
                        class="w-full px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed text-gray-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select name="category" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="Fantasy">Fantasy</option>
                        <option value="Science Fiction">Science Fiction</option>
                        <option value="Mystery">Mystery</option>
                        <option value="Thriller">Thriller</option>
                        <option value="Romance">Romance</option>
                        <option value="Horror">Horror</option>
                        <option value="Adventure">Adventure</option>
                        <option value="Drama">Drama</option>
                        <option value="Comedy">Comedy</option>
                        <option value="General">General</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                    <textarea name="description" rows="3" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cover Image URL</label>
                    <input type="url" name="cover_image_url" placeholder="https://example.com/image.jpg"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <p class="text-xs text-gray-400 mt-1">Optional — leave blank for default cover</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Parts (Estimated) *</label>
                    <input type="number" name="total_parts" min="1" value="10" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <hr class="my-2">
                <h3 class="text-base font-semibold text-gray-800">Part 1 Content</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                    <textarea name="part_content" rows="6" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="Write the first part of your story here..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload Date *</label>
                        <input type="date" name="upload_date" value="<?= date('Y-m-d') ?>" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prediction Deadline *</label>
                        <input type="datetime-local" name="prediction_deadline"
                            value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>

                <!-- Pending badge preview -->
                <div class="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                    <i class="fas fa-clock text-amber-500"></i>
                    After submission, your story will show as <strong>Pending</strong> until an admin approves it.
                </div>

                <button type="submit" name="add_new_story"
                    class="w-full gradient-bg text-white py-3 rounded-lg font-semibold hover:opacity-90 transition flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i> Submit Story for Review
                </button>
            </form>
        </div>

        <!-- ── Add New Part ──────────────────────────────── -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center mb-6">
                <div class="bg-blue-100 rounded-full p-3 mr-4">
                    <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Add New Part</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Only approved stories shown below</p>
                </div>
            </div>

            <?php if (empty($existing_stories)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-hourglass-half text-5xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 font-medium mb-2">No approved stories yet</p>
                    <p class="text-sm text-gray-400">
                        You can add parts once an admin approves your story.
                        Check the status panel below for updates.
                    </p>
                </div>
            <?php else: ?>
                <form action="add_story.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Story *</label>
                        <select name="existing_story_id" id="story_selector" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">-- Choose an Approved Story --</option>
                            <?php foreach ($existing_stories as $story): ?>
                                <option value="<?= $story['story_id'] ?>"
                                    data-current-part="<?= $story['current_part_no'] ?>"
                                    data-total-parts="<?= $story['total_parts'] ?>">
                                    <?= htmlspecialchars($story['title']) ?>
                                    (Part <?= $story['current_part_no'] ?>/<?= $story['total_parts'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Part Number *</label>
                        <input type="number" name="new_part_number" id="part_number" min="1" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-400 mt-1" id="part_hint">Select a story to see suggested part number</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Part Content *</label>
                        <textarea name="new_part_content" rows="8" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Write the next part of your story..."></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Date *</label>
                            <input type="date" name="new_upload_date" value="<?= date('Y-m-d') ?>" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prediction Deadline *</label>
                            <input type="datetime-local" name="new_prediction_deadline"
                                value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                        <i class="fas fa-clock text-amber-500"></i>
                        This part will be <strong>pending</strong> until an admin approves it.
                    </div>

                    <button type="submit" name="add_new_part"
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i> Submit Part for Review
                    </button>
                </form>
            <?php endif; ?>
        </div>

    </div><!-- /forms grid -->

    <!-- ── My Submissions Status Panel ──────────────────── -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="bg-gray-100 rounded-full p-2.5">
                    <i class="fas fa-list-check text-gray-600 text-lg"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">My Submissions</h2>
                    <p class="text-xs text-gray-400">Track the status of all your stories</p>
                </div>
            </div>
            <!-- Legend -->
            <div class="hidden sm:flex items-center gap-3 text-xs font-medium">
                <span class="badge-pending px-2.5 py-1 rounded-full">Pending</span>
                <span class="badge-approved px-2.5 py-1 rounded-full">Approved</span>
                <span class="badge-rejected px-2.5 py-1 rounded-full">Rejected</span>
            </div>
        </div>

        <?php if (empty($all_stories)): ?>
            <div class="text-center py-10 text-gray-400">
                <i class="fas fa-book-open text-4xl mb-3"></i>
                <p>No stories submitted yet.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
                            <th class="pb-3 pr-4">Title</th>
                            <th class="pb-3 pr-4">Parts</th>
                            <th class="pb-3 pr-4">Story Status</th>
                            <th class="pb-3">Parts Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($all_stories as $s):
                            // Count parts by status for this story
                            $pstmt = $pdo->prepare("
                                SELECT status, COUNT(*) as cnt
                                FROM story_parts WHERE story_id = ?
                                GROUP BY status
                            ");
                            $pstmt->execute([$s['story_id']]);
                            $part_counts = [];
                            foreach ($pstmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                                $part_counts[$row['status']] = $row['cnt'];
                            }
                            $badge_class = match($s['status']) {
                                'approved' => 'badge-approved',
                                'rejected' => 'badge-rejected',
                                default    => 'badge-pending',
                            };
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-3 pr-4 font-medium text-gray-800">
                                <?= htmlspecialchars($s['title']) ?>
                            </td>
                            <td class="py-3 pr-4 text-gray-500">
                                <?= $s['current_part_no'] ?>/<?= $s['total_parts'] ?>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="<?= $badge_class ?> px-2.5 py-1 rounded-full text-xs font-semibold capitalize">
                                    <?= $s['status'] ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    <?php if (!empty($part_counts['approved'])): ?>
                                        <span class="badge-approved px-2 py-0.5 rounded-full text-xs"><?= $part_counts['approved'] ?> approved</span>
                                    <?php endif; ?>
                                    <?php if (!empty($part_counts['pending'])): ?>
                                        <span class="badge-pending px-2 py-0.5 rounded-full text-xs"><?= $part_counts['pending'] ?> pending</span>
                                    <?php endif; ?>
                                    <?php if (!empty($part_counts['rejected'])): ?>
                                        <span class="badge-rejected px-2 py-0.5 rounded-full text-xs"><?= $part_counts['rejected'] ?> rejected</span>
                                    <?php endif; ?>
                                    <?php if (empty($part_counts)): ?>
                                        <span class="text-gray-400 text-xs">No parts yet</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /max-w -->

<script>
document.getElementById('story_selector')?.addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    if (selected.value) {
        const currentPart = parseInt(selected.getAttribute('data-current-part'));
        const totalParts  = parseInt(selected.getAttribute('data-total-parts'));
        const nextPart    = currentPart + 1;
        document.getElementById('part_number').value = nextPart;
        document.getElementById('part_hint').textContent =
            `Suggested: Part ${nextPart} (${totalParts} total planned)`;
    } else {
        document.getElementById('part_number').value = '';
        document.getElementById('part_hint').textContent = 'Select a story to see suggested part number';
    }
});
</script>
</body>
</html>