<?php
/**
 * arena_admin_api.php
 * Admin-only backend for Flash Words Question Manager
 * Location: admin/arena_admin_api.php
 * Requires: ../db_connect.php (PDO), session with is_admin
 */

session_start();
header('Content-Type: application/json');

// ── Auth ────────────────────────────────────────────────────────
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../db_connect.php';

$admin_user_id = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$action = $_GET['action'] ?? '';

// For POST, read JSON body
$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
    $action = $body['action'] ?? $action;
}

// ── Config ──────────────────────────────────────────────────────
// Max sentence count per chunk before we split a paragraph further
define('MAX_SENTENCES_PER_CHUNK', 6);
// Min words a chunk must have to be worth showing
define('MIN_WORDS_PER_CHUNK', 20);
// Max words before we force-split a paragraph (even without sentence boundary)
define('MAX_WORDS_PER_CHUNK', 120);


// ════════════════════════════════════════════════════════════════
// ROUTING
// ════════════════════════════════════════════════════════════════
switch ($action) {

    // ────────────────────────────────────────────────────────────
    // GET: Generate chunks from a story part (no DB storage)
    // Also returns list of chunk_text values already in DB
    // ────────────────────────────────────────────────────────────
    case 'get_chunks':
        $story_id    = (int)($_GET['story_id'] ?? 0);
        $part_number = (int)($_GET['part_number'] ?? 0);

        if (!$story_id || !$part_number) {
            echo json_encode(['success' => false, 'error' => 'Missing story_id or part_number']);
            exit;
        }

        try {
            // Fetch raw story part content
            $stmt = $pdo->prepare(
                "SELECT content FROM story_parts WHERE story_id = ? AND part_number = ?"
            );
            $stmt->execute([$story_id, $part_number]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['success' => false, 'error' => 'Story part not found']);
                exit;
            }

            $chunks = splitIntoChunks($row['content']);

            // Fetch which chunk_texts are already stored in DB for this story+part
            $stmt = $pdo->prepare(
                "SELECT chunk_text FROM arena_questions
                 WHERE story_id = ? AND part_number = ?
                   AND question_type = 'story_chunk'
                   AND chunk_text IS NOT NULL"
            );
            $stmt->execute([$story_id, $part_number]);
            $usedTexts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'success'          => true,
                'chunks'           => $chunks,
                'used_chunk_texts' => $usedTexts,
            ]);

        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;


    // ────────────────────────────────────────────────────────────
    // POST: Save a chunk-based question
    // ────────────────────────────────────────────────────────────
    case 'save_chunk_question':
        $story_id    = (int)($body['story_id'] ?? 0);
        $part_number = (int)($body['part_number'] ?? 0);
        $chunk_text  = trim($body['chunk_text'] ?? '');
        $question    = trim($body['question_text'] ?? '');
        $correct     = trim($body['correct_option'] ?? '');
        $misleading  = trim($body['misleading_option'] ?? '');

        if (!$story_id || !$part_number || !$chunk_text || !$question || !$correct || !$misleading) {
            echo json_encode(['success' => false, 'error' => 'All fields are required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO arena_questions
                  (story_id, part_number, chunk_text, question_type,
                   external_passage, question_text, correct_option, misleading_option)
                VALUES (?, ?, ?, 'story_chunk', NULL, ?, ?, ?)
            ");
            $stmt->execute([
                $story_id, $part_number, $chunk_text,
                $question, $correct, $misleading
            ]);

            echo json_encode(['success' => true, 'question_id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;


    // ────────────────────────────────────────────────────────────
    // POST: Save an external knowledge question
    // ────────────────────────────────────────────────────────────
    // ────────────────────────────────────────────────────────────
    // POST: Save an external knowledge question
    // Fully standalone — NOT linked to any story or part
    // ────────────────────────────────────────────────────────────
    case 'save_external_question':
        $passage  = trim($body['external_passage'] ?? '');
        $question = trim($body['question_text'] ?? '');
        $correct  = trim($body['correct_option'] ?? '');
        $mislead  = trim($body['misleading_option'] ?? '');

        if (!$passage || !$question || !$correct || !$mislead) {
            echo json_encode(['success' => false, 'error' => 'All fields are required']);
            exit;
        }

        try {
            // story_id = NULL, part_number = NULL — no story link whatsoever
            $stmt = $pdo->prepare("
                INSERT INTO arena_questions
                  (story_id, part_number, chunk_text, question_type,
                   external_passage, question_text, correct_option, misleading_option)
                VALUES (NULL, NULL, NULL, 'external', ?, ?, ?, ?)
            ");
            $stmt->execute([$passage, $question, $correct, $mislead]);

            echo json_encode(['success' => true, 'question_id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;



    // ────────────────────────────────────────────────────────────
    // GET: Fetch questions list (with optional filters)
    // ────────────────────────────────────────────────────────────
    case 'get_questions':
        // story_id filter: when set, show story_chunk questions for that story
        //                  PLUS all external questions (they belong to no story)
        // No type filter — both types shown together
        $story_id = isset($_GET['story_id']) ? (int)$_GET['story_id'] : null;
        $limit    = min((int)($_GET['limit'] ?? 50), 200);

        if ($story_id) {
            // Story-specific: story_chunk for this story OR external (story_id IS NULL)
            $params = [$story_id, $limit];
            $sql = "
                SELECT aq.question_id, aq.story_id, aq.part_number,
                       aq.question_type, aq.chunk_text, aq.external_passage,
                       aq.question_text, aq.correct_option, aq.misleading_option,
                       aq.created_at,
                       s.title AS story_title
                FROM arena_questions aq
                LEFT JOIN stories s ON s.story_id = aq.story_id
                WHERE (aq.story_id = ? OR aq.story_id IS NULL)
                ORDER BY aq.question_type ASC, aq.created_at DESC
                LIMIT ?
            ";
        } else {
            // All questions — no filter
            $params = [$limit];
            $sql = "
                SELECT aq.question_id, aq.story_id, aq.part_number,
                       aq.question_type, aq.chunk_text, aq.external_passage,
                       aq.question_text, aq.correct_option, aq.misleading_option,
                       aq.created_at,
                       s.title AS story_title
                FROM arena_questions aq
                LEFT JOIN stories s ON s.story_id = aq.story_id
                ORDER BY aq.created_at DESC
                LIMIT ?
            ";
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'questions' => $questions]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;


    // ────────────────────────────────────────────────────────────
    // POST: Delete a question
    // ────────────────────────────────────────────────────────────
    case 'delete_question':
        $qid = (int)($body['question_id'] ?? 0);
        if (!$qid) {
            echo json_encode(['success' => false, 'error' => 'Missing question_id']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM arena_questions WHERE question_id = ?");
            $stmt->execute([$qid]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;


    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}


// ════════════════════════════════════════════════════════════════
// CHUNK SPLITTING LOGIC
// ════════════════════════════════════════════════════════════════
/**
 * Split story content into meaningful chunks.
 *
 * Strategy:
 * 1. Split by paragraphs (double newlines)
 * 2. If a paragraph is too long (> MAX_SENTENCES_PER_CHUNK sentences
 *    OR > MAX_WORDS_PER_CHUNK words), split it by sentences
 * 3. Group short sentences together until we hit the limits
 * 4. Filter out chunks under MIN_WORDS_PER_CHUNK words
 *
 * @param  string $content  Raw story part content
 * @return array            Array of ['index'=>int, 'text'=>string]
 */
function splitIntoChunks(string $content): array
{
    // Normalise line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    // Split by blank lines (paragraph boundaries)
    $paragraphs = preg_split('/\n{2,}/', $content);
    $paragraphs = array_map('trim', $paragraphs);
    $paragraphs = array_filter($paragraphs, fn($p) => strlen($p) > 0);

    $rawChunks = [];

    foreach ($paragraphs as $para) {
        $wordCount = str_word_count($para);
        $sentences = splitBySentences($para);
        $sentCount = count($sentences);

        if ($wordCount <= MAX_WORDS_PER_CHUNK && $sentCount <= MAX_SENTENCES_PER_CHUNK) {
            // Paragraph is fine as-is
            $rawChunks[] = $para;
        } else {
            // Too long → group sentences into sub-chunks
            $rawChunks = array_merge($rawChunks, groupSentences($sentences));
        }
    }

    // Filter too-short chunks and build result
    $result = [];
    $idx    = 0;
    foreach ($rawChunks as $chunk) {
        $chunk = trim($chunk);
        if (!$chunk) continue;
        if (str_word_count($chunk) < MIN_WORDS_PER_CHUNK) continue;

        $result[] = ['index' => $idx++, 'text' => $chunk];
    }

    return $result;
}

/**
 * Split a paragraph into individual sentences.
 * Handles common abbreviations to avoid false splits.
 */
function splitBySentences(string $para): array
{
    // Protect common abbreviations (Mr., Dr., etc.)
    $protected = preg_replace('/\b(Mr|Mrs|Ms|Dr|Prof|Sr|Jr|vs|etc|e\.g|i\.e)\./i', '$1⟨DOT⟩', $para);

    // Split on sentence-ending punctuation followed by space/end
    $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z"\'])/u', $protected);

    // Restore abbreviation dots and trim
    return array_map(function ($s) {
        return trim(str_replace('⟨DOT⟩', '.', $s));
    }, $parts ?: [$para]);
}

/**
 * Group sentences into chunks respecting word and sentence limits.
 */
function groupSentences(array $sentences): array
{
    $chunks   = [];
    $current  = [];
    $curWords = 0;
    $curSents = 0;

    foreach ($sentences as $sent) {
        $sentWords = str_word_count($sent);

        $wouldExceedWords = ($curWords + $sentWords) > MAX_WORDS_PER_CHUNK;
        $wouldExceedSents = ($curSents + 1) > MAX_SENTENCES_PER_CHUNK;

        if ($current && ($wouldExceedWords || $wouldExceedSents)) {
            $chunks[]  = implode(' ', $current);
            $current   = [];
            $curWords  = 0;
            $curSents  = 0;
        }

        $current[]  = $sent;
        $curWords  += $sentWords;
        $curSents++;
    }

    if ($current) {
        $chunks[] = implode(' ', $current);
    }

    return $chunks;
}