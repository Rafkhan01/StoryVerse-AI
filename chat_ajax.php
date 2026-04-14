<?php
session_start();
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

// ── Auth check ───────────────────────────────────────────────────
$is_admin  = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$is_author = isset($_SESSION['user_id']) && isset($_SESSION['user_type'])
             && $_SESSION['user_type'] === 'author';

if (!$is_admin && !$is_author) {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']); exit;
}

// ── Resolve current user id ───────────────────────────────────────
// For authors: always from session
// For admin:   caller passes my_id (the admin's user_id rendered from PHP into JS)
//              This avoids relying on user_type='admin' in the users table.
if ($is_author) {
    $me = (int)$_SESSION['user_id'];
} else {
    // Admin: accept my_id from POST or GET, must be > 0
    $me = (int)(($_POST['my_id'] ?? $_GET['my_id'] ?? 0));
    if (!$me) {
        // Last resort: look up by user_type (works if admin row exists)
        $me = (int)($pdo->query("SELECT user_id FROM users WHERE user_type='admin' LIMIT 1")->fetchColumn());
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Helper: 3-minute edit/delete window check ─────────────────────
function canModify(string $created_at): bool {
    return (time() - strtotime($created_at)) <= 180;
}

try {

// ════════════════════════════════════════════════════════════════
// PRIVATE CHAT
// ════════════════════════════════════════════════════════════════

if ($action === 'load_private') {
    $other_id = (int)($_GET['other_id'] ?? $_POST['other_id'] ?? 0);
    if (!$other_id) { echo json_encode(['success'=>false,'msg'=>'No user']); exit; }

    $stmt = $pdo->prepare("
        SELECT m.message_id, m.sender_id, m.message_text, m.is_edited, m.is_deleted,
               m.created_at, m.edited_at, u.first_name, u.last_name, u.user_type
        FROM chat_messages m
        INNER JOIN users u ON u.user_id = m.sender_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at DESC LIMIT 50
    ");
    $stmt->execute([$me, $other_id, $other_id, $me]);
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Mark received messages as read
    $pdo->prepare("UPDATE chat_messages SET is_read=1
                   WHERE receiver_id=? AND sender_id=? AND is_read=0")
        ->execute([$me, $other_id]);

    // Update read timestamp
    $pdo->prepare("INSERT INTO chat_read_status (user_id, other_user_id, last_read_at)
                   VALUES (?,?,NOW())
                   ON DUPLICATE KEY UPDATE last_read_at=NOW()")
        ->execute([$me, $other_id]);

    echo json_encode(['success'=>true, 'messages'=>$rows, 'my_id'=>$me]);
    exit;
}

if ($action === 'send_private') {
    $text     = trim($_POST['message_text'] ?? '');
    $other_id = (int)($_POST['other_id'] ?? 0);
    if (!$text || !$other_id) {
        echo json_encode(['success'=>false,'msg'=>'Missing text or recipient']); exit;
    }

    $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message_text) VALUES (?,?,?)")
        ->execute([$me, $other_id, $text]);
    $new_id = $pdo->lastInsertId();

    $row = $pdo->prepare("
        SELECT m.message_id, m.sender_id, m.message_text, m.is_edited, m.is_deleted,
               m.created_at, m.edited_at, u.first_name, u.last_name, u.user_type
        FROM chat_messages m INNER JOIN users u ON u.user_id=m.sender_id
        WHERE m.message_id=?");
    $row->execute([$new_id]);
    echo json_encode(['success'=>true, 'message'=>$row->fetch(PDO::FETCH_ASSOC), 'my_id'=>$me]);
    exit;
}

if ($action === 'poll_private') {
    $other_id = (int)($_GET['other_id'] ?? 0);
    $since_id = (int)($_GET['since_id'] ?? 0);
    if (!$other_id) { echo json_encode(['success'=>false,'msg'=>'No user']); exit; }

    $stmt = $pdo->prepare("
        SELECT m.message_id, m.sender_id, m.message_text, m.is_edited, m.is_deleted,
               m.created_at, m.edited_at, u.first_name, u.last_name, u.user_type
        FROM chat_messages m
        INNER JOIN users u ON u.user_id = m.sender_id
        WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
          AND m.message_id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$me, $other_id, $other_id, $me, $since_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        $pdo->prepare("UPDATE chat_messages SET is_read=1
                       WHERE receiver_id=? AND sender_id=? AND is_read=0")
            ->execute([$me, $other_id]);
    }

    echo json_encode(['success'=>true, 'messages'=>$rows, 'my_id'=>$me]);
    exit;
}

if ($action === 'edit_private') {
    $msg_id = (int)($_POST['message_id'] ?? 0);
    $text   = trim($_POST['message_text'] ?? '');
    if (!$msg_id || !$text) { echo json_encode(['success'=>false,'msg'=>'Empty']); exit; }

    $check = $pdo->prepare("SELECT sender_id, created_at FROM chat_messages WHERE message_id=?");
    $check->execute([$msg_id]);
    $orig  = $check->fetch(PDO::FETCH_ASSOC);

    if (!$orig) { echo json_encode(['success'=>false,'msg'=>'Not found']); exit; }
    if ((int)$orig['sender_id'] !== $me && !$is_admin) {
        echo json_encode(['success'=>false,'msg'=>'Not yours']); exit;
    }
    if (!$is_admin && !canModify($orig['created_at'])) {
        echo json_encode(['success'=>false,'msg'=>'Time expired (3 min limit)']); exit;
    }

    $pdo->prepare("UPDATE chat_messages SET message_text=?, is_edited=1, edited_at=NOW() WHERE message_id=?")
        ->execute([$text, $msg_id]);
    echo json_encode(['success'=>true]);
    exit;
}

if ($action === 'delete_private') {
    $msg_id = (int)($_POST['message_id'] ?? 0);
    if (!$msg_id) { echo json_encode(['success'=>false,'msg'=>'Empty']); exit; }

    $check = $pdo->prepare("SELECT sender_id, created_at FROM chat_messages WHERE message_id=?");
    $check->execute([$msg_id]);
    $orig  = $check->fetch(PDO::FETCH_ASSOC);

    if (!$orig) { echo json_encode(['success'=>false,'msg'=>'Not found']); exit; }
    if ((int)$orig['sender_id'] !== $me && !$is_admin) {
        echo json_encode(['success'=>false,'msg'=>'Not yours']); exit;
    }
    if (!$is_admin && !canModify($orig['created_at'])) {
        echo json_encode(['success'=>false,'msg'=>'Time expired (3 min limit)']); exit;
    }

    $pdo->prepare("UPDATE chat_messages SET is_deleted=1 WHERE message_id=?")->execute([$msg_id]);
    echo json_encode(['success'=>true]);
    exit;
}

// ════════════════════════════════════════════════════════════════
// GROUP CHAT
// ════════════════════════════════════════════════════════════════

if ($action === 'load_group') {
    $stmt = $pdo->prepare("
        SELECT g.message_id, g.sender_id, g.message_text, g.is_edited, g.is_deleted,
               g.created_at, g.edited_at, u.first_name, u.last_name, u.user_type
        FROM group_chat_messages g
        INNER JOIN users u ON u.user_id = g.sender_id
        ORDER BY g.created_at DESC LIMIT 50
    ");
    $stmt->execute();
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Update group read timestamp — marks this user as having read up to now
    $pdo->prepare("INSERT INTO chat_read_status (user_id, other_user_id, last_read_at)
                   VALUES (?, NULL, NOW())
                   ON DUPLICATE KEY UPDATE last_read_at=NOW()")
        ->execute([$me]);

    echo json_encode(['success'=>true, 'messages'=>$rows, 'my_id'=>$me]);
    exit;
}

if ($action === 'send_group') {
    $text = trim($_POST['message_text'] ?? '');
    if (!$text) { echo json_encode(['success'=>false,'msg'=>'Empty']); exit; }

    $pdo->prepare("INSERT INTO group_chat_messages (sender_id, message_text) VALUES (?,?)")
        ->execute([$me, $text]);
    $new_id = $pdo->lastInsertId();

    // Also update sender's own read status so their own message doesn't show as unread
    $pdo->prepare("INSERT INTO chat_read_status (user_id, other_user_id, last_read_at)
                   VALUES (?, NULL, NOW())
                   ON DUPLICATE KEY UPDATE last_read_at=NOW()")
        ->execute([$me]);

    $row = $pdo->prepare("
        SELECT g.message_id, g.sender_id, g.message_text, g.is_edited, g.is_deleted,
               g.created_at, g.edited_at, u.first_name, u.last_name, u.user_type
        FROM group_chat_messages g INNER JOIN users u ON u.user_id=g.sender_id
        WHERE g.message_id=?");
    $row->execute([$new_id]);
    echo json_encode(['success'=>true, 'message'=>$row->fetch(PDO::FETCH_ASSOC), 'my_id'=>$me]);
    exit;
}

if ($action === 'poll_group') {
    $since_id = (int)($_GET['since_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT g.message_id, g.sender_id, g.message_text, g.is_edited, g.is_deleted,
               g.created_at, g.edited_at, u.first_name, u.last_name, u.user_type
        FROM group_chat_messages g
        INNER JOIN users u ON u.user_id = g.sender_id
        WHERE g.message_id > ?
        ORDER BY g.created_at ASC
    ");
    $stmt->execute([$since_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // NOTE: Do NOT update chat_read_status here.
    // Read status is only updated when the user explicitly opens the chat (load_group).
    // Updating it on every poll would suppress unread badges for OTHER users.

    echo json_encode(['success'=>true, 'messages'=>$rows, 'my_id'=>$me]);
    exit;
}

if ($action === 'edit_group') {
    $msg_id = (int)($_POST['message_id'] ?? 0);
    $text   = trim($_POST['message_text'] ?? '');
    if (!$msg_id || !$text) { echo json_encode(['success'=>false,'msg'=>'Empty']); exit; }

    $check = $pdo->prepare("SELECT sender_id, created_at FROM group_chat_messages WHERE message_id=?");
    $check->execute([$msg_id]);
    $orig  = $check->fetch(PDO::FETCH_ASSOC);

    if (!$orig) { echo json_encode(['success'=>false,'msg'=>'Not found']); exit; }
    if ((int)$orig['sender_id'] !== $me && !$is_admin) {
        echo json_encode(['success'=>false,'msg'=>'Not yours']); exit;
    }
    if (!$is_admin && !canModify($orig['created_at'])) {
        echo json_encode(['success'=>false,'msg'=>'Time expired — cannot edit after 3 minutes']); exit;
    }

    $pdo->prepare("UPDATE group_chat_messages SET message_text=?, is_edited=1, edited_at=NOW() WHERE message_id=?")
        ->execute([$text, $msg_id]);
    echo json_encode(['success'=>true]);
    exit;
}

if ($action === 'delete_group') {
    $msg_id = (int)($_POST['message_id'] ?? 0);
    if (!$msg_id) { echo json_encode(['success'=>false,'msg'=>'Empty']); exit; }

    $check = $pdo->prepare("SELECT sender_id, created_at FROM group_chat_messages WHERE message_id=?");
    $check->execute([$msg_id]);
    $orig  = $check->fetch(PDO::FETCH_ASSOC);

    if (!$orig) { echo json_encode(['success'=>false,'msg'=>'Not found']); exit; }
    if ((int)$orig['sender_id'] !== $me && !$is_admin) {
        echo json_encode(['success'=>false,'msg'=>'Not yours']); exit;
    }
    if (!$is_admin && !canModify($orig['created_at'])) {
        echo json_encode(['success'=>false,'msg'=>'Time expired — cannot delete after 3 minutes']); exit;
    }

    $pdo->prepare("UPDATE group_chat_messages SET is_deleted=1 WHERE message_id=?")->execute([$msg_id]);
    echo json_encode(['success'=>true]);
    exit;
}

// ════════════════════════════════════════════════════════════════
// ADMIN EXTRAS
// ════════════════════════════════════════════════════════════════

if ($action === 'list_authors') {
    if (!$is_admin) { echo json_encode(['success'=>false,'msg'=>'Unauthorized']); exit; }

    $admin_id = $me;
    $stmt = $pdo->prepare("
        SELECT
            u.user_id, u.user_name, u.first_name, u.last_name,
            (
                SELECT message_text FROM chat_messages
                WHERE (sender_id=u.user_id AND receiver_id=:a1)
                   OR (sender_id=:a2 AND receiver_id=u.user_id)
                ORDER BY created_at DESC LIMIT 1
            ) AS last_message,
            (
                SELECT created_at FROM chat_messages
                WHERE (sender_id=u.user_id AND receiver_id=:a3)
                   OR (sender_id=:a4 AND receiver_id=u.user_id)
                ORDER BY created_at DESC LIMIT 1
            ) AS last_time,
            (
                SELECT COUNT(*) FROM chat_messages
                WHERE sender_id=u.user_id AND receiver_id=:a5 AND is_read=0
            ) AS unread_count
        FROM users u
        WHERE u.user_type = 'author'
        ORDER BY last_time DESC
    ");
    $stmt->execute([
        ':a1' => $admin_id, ':a2' => $admin_id,
        ':a3' => $admin_id, ':a4' => $admin_id,
        ':a5' => $admin_id
    ]);
    $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group unread: count messages after admin's last read timestamp
    $grp_stmt = $pdo->prepare(
        "SELECT last_read_at FROM chat_read_status
         WHERE user_id=? AND other_user_id IS NULL"
    );
    $grp_stmt->execute([$admin_id]);
    $last_grp_read = $grp_stmt->fetchColumn();

    if ($last_grp_read) {
        $gu_stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM group_chat_messages
             WHERE created_at > ? AND sender_id != ?"
        );
        $gu_stmt->execute([$last_grp_read, $admin_id]);
        $grp_unread = (int)$gu_stmt->fetchColumn();
    } else {
        $gu_stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM group_chat_messages WHERE sender_id != ?"
        );
        $gu_stmt->execute([$admin_id]);
        $grp_unread = (int)$gu_stmt->fetchColumn();
    }

    echo json_encode([
        'success'     => true,
        'authors'     => $authors,
        'group_unread'=> $grp_unread,
        'my_id'       => $me
    ]);
    exit;
}

echo json_encode(['success'=>false, 'msg'=>'Unknown action: '.$action]);

} catch (PDOException $e) {
    error_log("chat_ajax error: " . $e->getMessage());
    echo json_encode(['success'=>false, 'msg'=>'DB error: '.$e->getMessage()]);
}