<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Story Activity Chart (last 7 days) ───────────────────────────
    case 'story_chart':
        $rows = $pdo->query("
            SELECT DATE(created_at) as day, COUNT(*) as count
            FROM stories
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY day ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'labels' => array_column($rows, 'day'),
            'values' => array_column($rows, 'count')
        ]);
        break;

    // ── ML API Ping ──────────────────────────────────────────────────
    case 'ping_ml':
        $ch = curl_init('http://127.0.0.1:8000/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
        curl_exec($ch);
        $status = curl_errno($ch) === 0 ? 'online' : 'offline';
        curl_close($ch);
        echo json_encode(['status' => $status]);
        break;

    // ── User: Edit ───────────────────────────────────────────────────
    // Real columns: first_name, last_name, user_name, email, user_type(reader|author),
    //               password, email_verified
    case 'edit_user':
        $id         = (int)($_POST['id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name']  ?? '');
        $user_name  = trim($_POST['user_name']  ?? '');
        $email      = trim($_POST['email']      ?? '');
        $utype      = in_array($_POST['user_type'] ?? '', ['reader','author']) ? $_POST['user_type'] : 'reader';
        $password   = trim($_POST['password']   ?? '');
        $verified   = (int)($_POST['email_verified'] ?? 0);

        if (!$id || !$first_name || !$email || !$user_name) {
            echo json_encode(['success' => false, 'msg' => 'First name, username and email are required']);
            break;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'msg' => 'Invalid email address']);
            break;
        }

        // Check username duplicate (exclude self)
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE user_name = ? AND user_id != ?");
        $chk->execute([$user_name, $id]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'msg' => 'Username already taken by another account']);
            break;
        }

        // Check email duplicate (exclude self)
        $chk2 = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $chk2->execute([$email, $id]);
        if ($chk2->fetch()) {
            echo json_encode(['success' => false, 'msg' => 'Email already used by another account']);
            break;
        }

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("
                UPDATE users
                SET first_name=?, last_name=?, user_name=?, email=?, user_type=?, password=?, email_verified=?
                WHERE user_id=?
            ")->execute([$first_name, $last_name, $user_name, $email, $utype, $hash, $verified, $id]);
        } else {
            $pdo->prepare("
                UPDATE users
                SET first_name=?, last_name=?, user_name=?, email=?, user_type=?, email_verified=?
                WHERE user_id=?
            ")->execute([$first_name, $last_name, $user_name, $email, $utype, $verified, $id]);
        }
        log_action($pdo, "User #$id (@$user_name) edited by admin", 'update');
        echo json_encode(['success' => true]);
        break;

    // ── User: Toggle email_verified ──────────────────────────────────
    case 'toggle_verify':
        $id    = (int)($_POST['id'] ?? 0);
        $state = (int)($_POST['state'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        $pdo->prepare("UPDATE users SET email_verified = ? WHERE user_id = ?")->execute([$state, $id]);
        $label = $state ? 'verified' : 'unverified';
        log_action($pdo, "User #$id email marked $label by admin", 'update');
        echo json_encode(['success' => true]);
        break;

    // ── User: Delete ─────────────────────────────────────────────────
    case 'delete_user':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        // user_type ENUM only has reader|author — no admin in users table
        $stmt = $pdo->prepare("SELECT user_type FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            echo json_encode(['success' => false, 'msg' => 'User not found']);
            break;
        }
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$id]);
        log_action($pdo, "User #$id deleted", 'delete');
        echo json_encode(['success' => true]);
        break;

    // ── Story: Edit metadata ─────────────────────────────────────────
    case 'edit_story':
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title']           ?? '');
        $category    = trim($_POST['category']        ?? '');
        $description = trim($_POST['description']     ?? '');
        $cover       = trim($_POST['cover_image_url'] ?? '');
        $total_parts = (int)($_POST['total_parts']    ?? 1);

        if (!$id || !$title) {
            echo json_encode(['success' => false, 'msg' => 'Story ID and title are required']);
            break;
        }
        $pdo->prepare("
            UPDATE stories
            SET title=?, category=?, description=?, cover_image_url=?, total_parts=?
            WHERE story_id=?
        ")->execute([$title, $category, $description, $cover, $total_parts, $id]);
        log_action($pdo, "Story #$id metadata edited by admin", 'update');
        echo json_encode(['success' => true]);
        break;

    // ── Part: Edit content ────────────────────────────────────────────
    case 'edit_part':
        $id          = (int)($_POST['id']                  ?? 0);
        $content     = trim($_POST['content']              ?? '');
        $upload_date = trim($_POST['upload_date']          ?? '');
        $deadline    = trim($_POST['prediction_deadline']  ?? '');

        if (!$id || !$content) {
            echo json_encode(['success' => false, 'msg' => 'Part ID and content are required']);
            break;
        }
        $pdo->prepare("
            UPDATE story_parts
            SET content=?, upload_date=?, prediction_deadline=?
            WHERE part_id=?
        ")->execute([$content, $upload_date ?: null, $deadline ?: null, $id]);
        log_action($pdo, "Story part #$id content edited by admin", 'update');
        echo json_encode(['success' => true]);
        break;

    // ── Story: Approve ───────────────────────────────────────────────
    case 'approve_story':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        $pdo->prepare("UPDATE stories SET status = 'approved' WHERE story_id = ?")->execute([$id]);
        // Also approve all pending parts of this story
        $pdo->prepare("UPDATE story_parts SET status = 'approved' WHERE story_id = ? AND status = 'pending'")->execute([$id]);
        log_action($pdo, "Story #$id approved (and its pending parts)", 'approve');
        echo json_encode(['success' => true]);
        break;

    // ── Story: Reject ────────────────────────────────────────────────
    case 'reject_story':
        $id     = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        $pdo->prepare("UPDATE stories SET status = 'rejected' WHERE story_id = ?")->execute([$id]);
        log_action($pdo, "Story #$id rejected" . ($reason ? ": $reason" : ''), 'delete');
        echo json_encode(['success' => true]);
        break;

    // ── Story: Delete ─────────────────────────────────────────────────
    case 'delete_story':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        // story_parts will cascade if FK set; otherwise delete manually
        $pdo->prepare("DELETE FROM story_parts WHERE story_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stories WHERE story_id = ?")->execute([$id]);
        log_action($pdo, "Story #$id and all its parts deleted", 'delete');
        echo json_encode(['success' => true]);
        break;

    // ── Part: Approve ─────────────────────────────────────────────────
    case 'approve_part':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        $pdo->prepare("UPDATE story_parts SET status = 'approved' WHERE part_id = ?")->execute([$id]);
        log_action($pdo, "Story part #$id approved", 'approve');
        echo json_encode(['success' => true]);
        break;

    // ── Part: Reject ──────────────────────────────────────────────────
    case 'reject_part':
        $id     = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        $pdo->prepare("UPDATE story_parts SET status = 'rejected' WHERE part_id = ?")->execute([$id]);
        log_action($pdo, "Story part #$id rejected" . ($reason ? ": $reason" : ''), 'delete');
        echo json_encode(['success' => true]);
        break;

    // ── Part: Delete ──────────────────────────────────────────────────
    case 'delete_part':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        $pdo->prepare("DELETE FROM story_parts WHERE part_id = ?")->execute([$id]);
        log_action($pdo, "Story part #$id deleted", 'delete');
        echo json_encode(['success' => true]);
        break;

    // ── Comment: Manually flag as spam ───────────────────────────────
    // (ML blocks spam before insert; this flags comments ML missed)
    case 'flag_comment':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        $pdo->prepare("UPDATE comments SET manually_flagged = 1 WHERE comment_id = ?")->execute([$id]);
        log_action($pdo, "Comment #$id manually flagged as spam", 'update');
        echo json_encode(['success' => true]);
        break;

    // ── Comment: Unflag (admin was wrong) ────────────────────────────
    case 'unflag_comment':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        $pdo->prepare("UPDATE comments SET manually_flagged = 0 WHERE comment_id = ?")->execute([$id]);
        log_action($pdo, "Comment #$id unflagged", 'update');
        echo json_encode(['success' => true]);
        break;

    // ── Comment: Delete ───────────────────────────────────────────────
    case 'delete_comment':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'msg' => 'Invalid ID']); break; }
        $pdo->prepare("DELETE FROM comments WHERE comment_id = ?")->execute([$id]);
        log_action($pdo, "Comment #$id permanently deleted", 'delete');
        echo json_encode(['success' => true]);
        break;

    // ── Announcement: Update (upsert) ────────────────────────────────
    case 'update_announcement':
        $msg    = trim($_POST['message'] ?? '');
        $type   = in_array($_POST['type'] ?? '', ['info','warning','success']) ? $_POST['type'] : 'info';
        $active = ($_POST['is_active'] ?? '0') === '1' ? 1 : 0;

        if (strlen($msg) > 500) {
            echo json_encode(['success' => false, 'msg' => 'Message too long (max 500 chars)']);
            break;
        }

        $existing = $pdo->query("SELECT id FROM announcements ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($existing) {
            $pdo->prepare("UPDATE announcements SET message=?, type=?, is_active=?, updated_at=NOW() WHERE id=?")
                ->execute([$msg, $type, $active, $existing]);
        } else {
            $pdo->prepare("INSERT INTO announcements (message, type, is_active, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())")
                ->execute([$msg, $type, $active]);
        }
        log_action($pdo, "Announcement " . ($active ? 'published' : 'updated/hidden'), 'update');
        echo json_encode(['success' => true]);
        break;

    // ── Leaderboard: Edit Story Prediction scores ────────────────────
    // Direct edit: updates manual_accuracy on a specific prediction row
    // and bonus_amount on the related bonus row.
    case 'edit_pred_score':
        $uid      = (int)($_POST['user_id']        ?? 0);
        $acc      = $_POST['manual_accuracy'] !== '' ? (float)$_POST['manual_accuracy'] : null;
        $bonus    = $_POST['bonus_amount']    !== '' ? (float)$_POST['bonus_amount']    : null;

        if (!$uid) { echo json_encode(['success'=>false,'msg'=>'Invalid user']); break; }

        // Update avg manual_accuracy on ALL evaluated predictions for this user
        // We set every non-null manual_accuracy to the new value so avg reflects it.
        // Admin is overriding the effective average by levelling all rows.
        if ($acc !== null) {
            $pdo->prepare("
                UPDATE predictions SET manual_accuracy = ?
                WHERE user_id = ? AND manual_accuracy IS NOT NULL
            ")->execute([$acc, $uid]);
        }

        // Update or insert bonus total
        if ($bonus !== null) {
            $existing = $pdo->prepare("SELECT id FROM bonus WHERE user_id = ? LIMIT 1");
            $existing->execute([$uid]);
            if ($existing->fetchColumn()) {
                $pdo->prepare("UPDATE bonus SET bonus_amount = ? WHERE user_id = ?")
                    ->execute([$bonus, $uid]);
            } else {
                // Find a prediction to attach to
                $pid = $pdo->prepare("SELECT prediction_id FROM predictions WHERE user_id = ? LIMIT 1");
                $pid->execute([$uid]);
                $pid_val = $pid->fetchColumn();
                if ($pid_val) {
                    $story = $pdo->prepare("SELECT story_id FROM predictions WHERE prediction_id = ?");
                    $story->execute([$pid_val]);
                    $story_id = $story->fetchColumn();
                    $pdo->prepare("INSERT INTO bonus (prediction_id, user_id, story_id, bonus_amount) VALUES(?,?,?,?)")
                        ->execute([$pid_val, $uid, $story_id, $bonus]);
                }
            }
        }
        log_action($pdo, "Prediction leaderboard scores manually overridden for user #$uid", 'update');
        echo json_encode(['success' => true]);
        break;

    // ── Leaderboard: Edit Flash Words scores ─────────────────────────
    // Direct edit: updates arena_progress rows for this user.
    // Sets all their score rows to distribute the total, and best_wpm directly.
    case 'edit_flash_score':
        $uid        = (int)($_POST['user_id']       ?? 0);
        $total_score= $_POST['total_score'] !== '' ? (int)$_POST['total_score']  : null;
        $best_wpm   = $_POST['best_wpm']    !== '' ? (int)$_POST['best_wpm']     : null;

        if (!$uid) { echo json_encode(['success'=>false,'msg'=>'Invalid user']); break; }

        // Check if user has any arena_progress rows
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM arena_progress WHERE user_id = ?");
        $count_stmt->execute([$uid]);
        $row_count = (int)$count_stmt->fetchColumn();

        if ($row_count === 0) {
            // Insert a single synthetic row
            $pdo->prepare("
                INSERT INTO arena_progress
                    (user_id, current_wpm, streak, loss_streak, total_correct, total_attempts, best_wpm, score)
                VALUES (?, ?, 0, 0, 0, 0, ?, ?)
            ")->execute([$uid, $best_wpm ?? 60, $best_wpm ?? 60, $total_score ?? 0]);
        } else {
            // Update the row with the highest score (primary row)
            if ($total_score !== null) {
                // Zero out all rows first, then set total on the first row
                $pdo->prepare("UPDATE arena_progress SET score = 0 WHERE user_id = ?")->execute([$uid]);
                $first = $pdo->prepare("SELECT progress_id FROM arena_progress WHERE user_id = ? ORDER BY progress_id ASC LIMIT 1");
                $first->execute([$uid]);
                $pid = $first->fetchColumn();
                $pdo->prepare("UPDATE arena_progress SET score = ? WHERE progress_id = ?")->execute([$total_score, $pid]);
            }
            if ($best_wpm !== null) {
                $pdo->prepare("UPDATE arena_progress SET best_wpm = ? WHERE user_id = ?")->execute([$best_wpm, $uid]);
            }
        }
        log_action($pdo, "Flash Words scores manually overridden for user #$uid", 'update');
        echo json_encode(['success' => true]);
        break;

    // ── Leaderboard: Edit Story Scramble scores ───────────────────────
    // Direct edit: zeros all existing rows then inserts a single override row
    // so SUM(score) and SUM(correct_pairs) match exactly what admin wants.
    case 'edit_scramble_score':
        $uid          = (int)($_POST['user_id']         ?? 0);
        $total_score  = $_POST['total_score']   !== '' ? (int)$_POST['total_score']   : null;
        $correct_pairs= $_POST['correct_pairs'] !== '' ? (int)$_POST['correct_pairs'] : null;

        if (!$uid) { echo json_encode(['success'=>false,'msg'=>'Invalid user']); break; }

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM game_scores WHERE user_id = ?");
        $count_stmt->execute([$uid]);
        $row_count = (int)$count_stmt->fetchColumn();

        if ($row_count === 0) {
            // Insert synthetic row
            $pdo->prepare("
                INSERT INTO game_scores (user_id, score, correct_pairs, total_pairs, time_left_seconds)
                VALUES (?, ?, ?, ?, 0)
            ")->execute([$uid, $total_score ?? 0, $correct_pairs ?? 0, $correct_pairs ?? 0]);
        } else {
            // Zero all rows then set values on the first row
            if ($total_score !== null) {
                $pdo->prepare("UPDATE game_scores SET score = 0 WHERE user_id = ?")->execute([$uid]);
                $first = $pdo->prepare("SELECT id FROM game_scores WHERE user_id = ? ORDER BY id ASC LIMIT 1");
                $first->execute([$uid]);
                $gid = $first->fetchColumn();
                $pdo->prepare("UPDATE game_scores SET score = ? WHERE id = ?")->execute([$total_score, $gid]);
            }
            if ($correct_pairs !== null) {
                $pdo->prepare("UPDATE game_scores SET correct_pairs = 0 WHERE user_id = ?")->execute([$uid]);
                $first = $pdo->prepare("SELECT id FROM game_scores WHERE user_id = ? ORDER BY id ASC LIMIT 1");
                $first->execute([$uid]);
                $gid = $first->fetchColumn();
                $pdo->prepare("UPDATE game_scores SET correct_pairs = ? WHERE id = ?")->execute([$correct_pairs, $gid]);
            }
        }
        log_action($pdo, "Story Scramble scores manually overridden for user #$uid", 'update');
        echo json_encode(['success' => true]);
        break;

    // ── Leaderboard: Reset by game type ──────────────────────────────
    case 'reset_leaderboard':
        $game = $_POST['game'] ?? '';
        if (!in_array($game, ['scramble','whosaidit','flashwords'])) {
            echo json_encode(['success' => false, 'msg' => 'Invalid game type']);
            break;
        }
        $pdo->prepare("DELETE FROM game_scores WHERE game_type = ?")->execute([$game]);
        log_action($pdo, "Leaderboard reset for game: $game", 'delete');
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}

// ── Activity log helper ───────────────────────────────────────────────
function log_action(PDO $pdo, string $text, string $type = 'update'): void {
    try {
        $pdo->prepare("
            INSERT INTO admin_activity_log (action_text, action_type, created_at)
            VALUES (?, ?, NOW())
        ")->execute([$text, $type]);
    } catch (Exception $e) {
        // Table may not exist yet on first run — silently skip
    }
}
?>
