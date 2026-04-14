<?php
/**
 * arena_api.php
 * Backend API for Flash Words Game Arena
 * Requires: db_connect.php (PDO), session with user_id
 */

session_start();
require_once 'db_connect.php'; // Your existing PDO connection ($pdo)

header('Content-Type: application/json');

// ── Auth check ──────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

// ── WPM Tier Config ─────────────────────────────────────────
$WPM_TIERS      = [60, 100, 140, 180, 220];
$STREAK_TO_ADVANCE = 3; // Correct answers in a row to move up

// ── Route actions ────────────────────────────────────────────
switch ($action) {

    // ────────────────────────────────────────────────────────
    // GET: List all stories that have arena questions available
    // ────────────────────────────────────────────────────────
    case 'get_stories':
        try {
            // Stories with story_chunk questions
            $stmt = $pdo->prepare("
                SELECT DISTINCT s.story_id, s.title, s.category, s.cover_image_url,
                       s.description,
                       COUNT(DISTINCT aq.part_number) AS parts_with_questions,
                       ap.current_wpm, ap.best_wpm, ap.total_correct, ap.total_attempts
                FROM stories s
                INNER JOIN arena_questions aq ON aq.story_id = s.story_id
                    AND aq.question_type = 'story_chunk'
                LEFT JOIN arena_progress ap ON ap.story_id = s.story_id
                    AND ap.user_id = ? AND ap.part_number = (
                        SELECT MIN(part_number) FROM arena_questions
                        WHERE story_id = s.story_id AND question_type = 'story_chunk'
                    )
                GROUP BY s.story_id
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([$user_id]);
            $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count external questions
            $extStmt = $pdo->prepare("SELECT COUNT(*) FROM arena_questions WHERE question_type = 'external'");
            $extStmt->execute();
            $extCount = (int)$extStmt->fetchColumn();

            if ($extCount > 0) {
                // Get user progress for general knowledge (stored with story_id = 0 sentinel)
                $gpStmt = $pdo->prepare("
                    SELECT current_wpm, best_wpm, total_correct, total_attempts
                    FROM arena_progress WHERE user_id = ? AND story_id IS NULL AND part_number IS NULL
                ");
                $gpStmt->execute([$user_id]);
                $gp = $gpStmt->fetch(PDO::FETCH_ASSOC);

                $gkCard = [
                    'story_id'            => 0,
                    'title'               => 'General Knowledge',
                    'category'            => 'External',
                    'cover_image_url'     => null,
                    'description'         => 'Standalone knowledge questions added by admins. No story required — just read and answer.',
                    'parts_with_questions'=> 1,
                    'current_wpm'         => $gp ? $gp['current_wpm'] : 60,
                    'best_wpm'            => $gp ? $gp['best_wpm']    : 60,
                    'total_correct'       => $gp ? $gp['total_correct']   : 0,
                    'total_attempts'      => $gp ? $gp['total_attempts']  : 0,
                    'is_general_knowledge'=> true,
                    'external_count'      => $extCount,
                ];
                // Prepend so it appears first
                array_unshift($stories, $gkCard);
            }

            echo json_encode(['success' => true, 'stories' => $stories]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;

    // ────────────────────────────────────────────────────────
    // GET: Get story parts that have questions
    // ────────────────────────────────────────────────────────
    case 'get_parts':
        $story_id = (int)($_GET['story_id'] ?? -1);
        if ($story_id < 0) { echo json_encode(['success' => false, 'error' => 'Missing story_id']); exit; }

        try {
            // story_id = 0 means General Knowledge (external questions only)
            if ($story_id === 0) {
                $extStmt = $pdo->prepare("SELECT COUNT(*) FROM arena_questions WHERE question_type = 'external'");
                $extStmt->execute();
                $extCount = (int)$extStmt->fetchColumn();

                $gpStmt = $pdo->prepare("
                    SELECT current_wpm, best_wpm, streak, total_correct, total_attempts
                    FROM arena_progress WHERE user_id = ? AND story_id IS NULL AND part_number IS NULL
                ");
                $gpStmt->execute([$user_id]);
                $gp = $gpStmt->fetch(PDO::FETCH_ASSOC);

                $parts = [[
                    'part_number'    => 0,
                    'content'        => '',
                    'question_count' => $extCount,
                    'current_wpm'    => $gp ? $gp['current_wpm']    : 60,
                    'best_wpm'       => $gp ? $gp['best_wpm']       : 60,
                    'streak'         => $gp ? $gp['streak']         : 0,
                    'total_correct'  => $gp ? $gp['total_correct']  : 0,
                    'total_attempts' => $gp ? $gp['total_attempts'] : 0,
                    'label'          => 'General Knowledge',
                ]];
                echo json_encode(['success' => true, 'parts' => $parts]);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT sp.part_number, sp.content,
                       COUNT(aq.question_id) AS question_count,
                       ap.current_wpm, ap.best_wpm, ap.streak,
                       ap.total_correct, ap.total_attempts
                FROM story_parts sp
                INNER JOIN arena_questions aq ON aq.story_id = sp.story_id 
                    AND aq.part_number = sp.part_number
                    AND aq.question_type = 'story_chunk'
                LEFT JOIN arena_progress ap ON ap.story_id = sp.story_id 
                    AND ap.part_number = sp.part_number AND ap.user_id = ?
                WHERE sp.story_id = ?
                GROUP BY sp.part_number
                ORDER BY sp.part_number ASC
            ");
            $stmt->execute([$user_id, $story_id]);
            $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'parts' => $parts]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;

    // ────────────────────────────────────────────────────────
    // GET: Load a game session (passage + wpm + question)
    // ────────────────────────────────────────────────────────
    case 'get_session':
        $story_id    = isset($_GET['story_id'])    ? (int)$_GET['story_id']    : -1;
        $part_number = isset($_GET['part_number']) ? (int)$_GET['part_number'] : -1;
        if ($story_id < 0 || $part_number < 0) {
            echo json_encode(['success' => false, 'error' => 'Missing params']); exit;
        }

        try {
            $is_general_knowledge = ($story_id === 0 && $part_number === 0);

            // Get user's progress
            // GK pool stored with NULL/NULL; story parts stored with actual IDs
            if ($is_general_knowledge) {
                $stmt = $pdo->prepare("
                    SELECT * FROM arena_progress
                    WHERE user_id = ? AND story_id IS NULL AND part_number IS NULL
                ");
                $stmt->execute([$user_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM arena_progress
                    WHERE user_id = ? AND story_id = ? AND part_number = ?
                ");
                $stmt->execute([$user_id, $story_id, $part_number]);
            }
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);

            $current_wpm = $progress ? (int)$progress['current_wpm'] : 60;
            $best_wpm    = $progress ? (int)$progress['best_wpm'] : 60;
            // Streak is always returned as 0 at session start.
            // The frontend (state.isFirstRound) picks this up and resets its
            // local streak counter, ensuring "3 consecutive" is truly continuous.
            $streak = 0;

            // Also reset DB streak to 0 so it does not carry over across sessions.
            if ($progress) {
                if ($is_general_knowledge) {
                    $pdo->prepare("UPDATE arena_progress SET streak=0, loss_streak=0
                                   WHERE user_id=? AND story_id IS NULL AND part_number IS NULL")
                        ->execute([$user_id]);
                } else {
                    $pdo->prepare("UPDATE arena_progress SET streak=0, loss_streak=0
                                   WHERE user_id=? AND story_id=? AND part_number=?")
                        ->execute([$user_id, $story_id, $part_number]);
                }
            }

            // Pick question matching current_wpm word count (exact tier match)
            // Word count formula: LENGTH(TRIM(t)) - LENGTH(REPLACE(TRIM(t),' ','')) + 1
            if ($is_general_knowledge) {
                // GK: external questions matching word count
                $stmt = $pdo->prepare("
                    SELECT question_id, question_type, chunk_text, external_passage,
                           question_text, correct_option, misleading_option
                    FROM arena_questions
                    WHERE question_type = 'external'
                      AND (LENGTH(TRIM(external_passage)) - LENGTH(REPLACE(TRIM(external_passage),' ','')) + 1) = ?
                    ORDER BY RAND() LIMIT 1
                ");
                $stmt->execute([$current_wpm]);
                $question = $stmt->fetch(PDO::FETCH_ASSOC);
                // Fallback: any external question
                if (!$question) {
                    $stmt = $pdo->prepare("
                        SELECT question_id, question_type, chunk_text, external_passage,
                               question_text, correct_option, misleading_option
                        FROM arena_questions
                        WHERE question_type = 'external'
                        ORDER BY RAND() LIMIT 1
                    ");
                    $stmt->execute();
                    $question = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } else {
                // Story part: story_chunk + external both filtered by word count
                $stmt = $pdo->prepare("
                    (SELECT question_id, question_type, chunk_text, external_passage,
                            question_text, correct_option, misleading_option
                     FROM arena_questions
                     WHERE story_id = ? AND part_number = ? AND question_type = 'story_chunk'
                       AND (LENGTH(TRIM(chunk_text)) - LENGTH(REPLACE(TRIM(chunk_text),' ','')) + 1) = ?)
                    UNION ALL
                    (SELECT question_id, question_type, chunk_text, external_passage,
                            question_text, correct_option, misleading_option
                     FROM arena_questions
                     WHERE story_id IS NULL AND question_type = 'external'
                       AND (LENGTH(TRIM(external_passage)) - LENGTH(REPLACE(TRIM(external_passage),' ','')) + 1) = ?)
                    ORDER BY RAND() LIMIT 1
                ");
                $stmt->execute([$story_id, $part_number, $current_wpm, $current_wpm]);
                $question = $stmt->fetch(PDO::FETCH_ASSOC);
                // Fallback: any question for this part
                if (!$question) {
                    $stmt = $pdo->prepare("
                        SELECT question_id, question_type, chunk_text, external_passage,
                               question_text, correct_option, misleading_option
                        FROM arena_questions
                        WHERE story_id = ? AND part_number = ?
                        ORDER BY RAND() LIMIT 1
                    ");
                    $stmt->execute([$story_id, $part_number]);
                    $question = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            if (!$question) {
                echo json_encode(['success' => false, 'error' => 'No questions found']);
                exit;
            }

                        // Get passage: external uses external_passage, story_chunk uses chunk_text
            if ($question['question_type'] === 'external') {
                $passage = $question['external_passage'];
            } elseif (!empty($question['chunk_text'])) {
                $passage = $question['chunk_text'];
            } else {
                // Fallback for old questions without chunk_text
                $partRow = $pdo->prepare("SELECT content FROM story_parts WHERE story_id=? AND part_number=?");
                $partRow->execute([$story_id, $part_number]);
                $partData = $partRow->fetch(PDO::FETCH_ASSOC);
                $words    = preg_split('/\s+/', trim($partData['content'] ?? ''));
                $passage  = implode(' ', array_slice($words, 0, max(30, (int)($current_wpm * 1.5))));
            }

            $display_words = str_word_count($passage);

            // Shuffle options so correct isn't always first
            $options = [
                ['text' => $question['correct_option'],    'is_correct' => true],
                ['text' => $question['misleading_option'], 'is_correct' => false],
            ];
            shuffle($options);

            echo json_encode([
                'success'      => true,
                'passage'      => $passage,
                'word_count'   => $display_words,
                'current_wpm'  => $current_wpm,
                'best_wpm'     => $best_wpm,
                'streak'       => $streak,
                'streak_needed'=> $STREAK_TO_ADVANCE,
                'question_id'  => $question['question_id'],
                'question_text'=> $question['question_text'],
                'options'      => $options,
                'read_time_ms' => (int)(($display_words / $current_wpm) * 60 * 1000),
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;

    // ────────────────────────────────────────────────────────
    // POST: Submit answer and update progress
    // ────────────────────────────────────────────────────────
    case 'submit_answer':
        $data        = json_decode(file_get_contents('php://input'), true);
        $story_id    = isset($data['story_id'])    ? (int)$data['story_id']    : -1;
        $part_number = isset($data['part_number']) ? (int)$data['part_number'] : -1;
        $is_correct  = (bool)($data['is_correct'] ?? false);

        if ($story_id < 0 || $part_number < 0) {
            echo json_encode(['success' => false, 'error' => 'Missing params']); exit;
        }

        // Progression rules:
        //   Advance: 3 CONSECUTIVE correct answers (win streak must not be broken)
        //            A single wrong answer resets win_streak to 0 immediately.
        //   Drop:    2 consecutive wrong answers → go down one tier
        //   Score:   correct_answer_points = current_wpm × 10  (stored in arena_progress.score)
        $WINS_TO_ADVANCE  = 3;
        $LOSSES_TO_DROP   = 2;

        try {
            // story_id=0 = General Knowledge pool → stored as NULL
            $db_story_id    = ($story_id   === 0) ? null : $story_id;
            $db_part_number = ($part_number === 0) ? null : $part_number;

            // Fetch current progress
            if ($db_story_id === null) {
                $stmt = $pdo->prepare("
                    SELECT * FROM arena_progress
                    WHERE user_id = ? AND story_id IS NULL AND part_number IS NULL
                ");
                $stmt->execute([$user_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM arena_progress
                    WHERE user_id = ? AND story_id = ? AND part_number = ?
                ");
                $stmt->execute([$user_id, $db_story_id, $db_part_number]);
            }
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);

            $current_wpm    = $progress ? (int)$progress['current_wpm']    : 60;
            $win_streak     = $progress ? (int)$progress['streak']         : 0;
            $loss_streak    = $progress ? (int)($progress['loss_streak'] ?? 0) : 0;
            $best_wpm       = $progress ? (int)$progress['best_wpm']       : 60;
            $total_correct  = $progress ? (int)$progress['total_correct']  : 0;
            $total_attempts = $progress ? (int)$progress['total_attempts'] : 0;
            $score          = $progress ? (int)($progress['score'] ?? 0)   : 0;

            $tier_index = array_search($current_wpm, $WPM_TIERS);
            if ($tier_index === false) $tier_index = 0;

            $new_wpm         = $current_wpm;
            $new_win_streak  = $win_streak;
            $new_loss_streak = $loss_streak;
            $level_changed   = false;
            $direction       = null;

            if ($is_correct) {
                $total_correct++;
                $new_win_streak++;          // increment consecutive win counter
                $new_loss_streak = 0;       // any correct resets loss streak

                // Award points: current_wpm × 10 per correct answer
                $score += $current_wpm * 10;

                // Level UP: only when win_streak reaches WINS_TO_ADVANCE CONSECUTIVELY
                // (new_win_streak starts from the DB value which was reset on any previous loss)
                if ($new_win_streak >= $WINS_TO_ADVANCE) {
                    if ($tier_index < count($WPM_TIERS) - 1) {
                        $new_wpm        = $WPM_TIERS[$tier_index + 1];
                        $level_changed  = true;
                        $direction      = 'up';
                    }
                    // Always reset win streak after reaching the threshold
                    // (whether we levelled up or were already at max tier)
                    $new_win_streak = 0;
                    $new_loss_streak = 0;
                }
            } else {
                // WRONG / TIMED OUT — immediately break consecutive win streak
                $new_win_streak  = 0;   // ← hard reset: streak is broken, must start over
                $new_loss_streak++;

                // Level DOWN: only after LOSSES_TO_DROP consecutive losses
                if ($new_loss_streak >= $LOSSES_TO_DROP) {
                    if ($tier_index > 0) {
                        $new_wpm       = $WPM_TIERS[$tier_index - 1];
                        $level_changed = true;
                        $direction     = 'down';
                    }
                    $new_loss_streak = 0;
                    $new_win_streak  = 0;
                }
            }

            $total_attempts++;
            $new_best = max($best_wpm, $new_wpm);

            // Upsert progress — includes score column
            if ($progress) {
                if ($db_story_id === null) {
                    $stmt = $pdo->prepare("
                        UPDATE arena_progress
                        SET current_wpm=?, streak=?, loss_streak=?, best_wpm=?,
                            total_correct=?, total_attempts=?, score=?
                        WHERE user_id=? AND story_id IS NULL AND part_number IS NULL
                    ");
                    $stmt->execute([$new_wpm, $new_win_streak, $new_loss_streak, $new_best,
                                    $total_correct, $total_attempts, $score, $user_id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE arena_progress
                        SET current_wpm=?, streak=?, loss_streak=?, best_wpm=?,
                            total_correct=?, total_attempts=?, score=?
                        WHERE user_id=? AND story_id=? AND part_number=?
                    ");
                    $stmt->execute([$new_wpm, $new_win_streak, $new_loss_streak, $new_best,
                                    $total_correct, $total_attempts, $score,
                                    $user_id, $db_story_id, $db_part_number]);
                }
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO arena_progress
                    (user_id, story_id, part_number, current_wpm, streak, loss_streak,
                     best_wpm, total_correct, total_attempts, score)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([$user_id, $db_story_id, $db_part_number,
                                $new_wpm, $new_win_streak, $new_loss_streak,
                                $new_best, $total_correct, $total_attempts, $score]);
            }

            echo json_encode([
                'success'        => true,
                'is_correct'     => $is_correct,
                'new_wpm'        => $new_wpm,
                'new_win_streak' => $new_win_streak,
                'new_loss_streak'=> $new_loss_streak,
                'wins_needed'    => $WINS_TO_ADVANCE,
                'losses_to_drop' => $LOSSES_TO_DROP,
                'best_wpm'       => $new_best,
                'level_changed'  => $level_changed,
                'direction'      => $direction,
                'total_correct'  => $total_correct,
                'total_attempts' => $total_attempts,
                'score'          => $score,
                'accuracy'       => $total_attempts > 0 ? round(($total_correct/$total_attempts)*100) : 0,
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;

    // ────────────────────────────────────────────────────────
    // GET: Leaderboard for a story part
    // ────────────────────────────────────────────────────────
    case 'get_leaderboard':
        $story_id    = (int)($_GET['story_id'] ?? 0);
        $part_number = (int)($_GET['part_number'] ?? 1);
        if (!$story_id) { echo json_encode(['success' => false, 'error' => 'Missing story_id']); exit; }

        try {
            $stmt = $pdo->prepare("
                SELECT u.user_name, u.first_name, u.profile_picture,
                       ap.best_wpm, ap.total_correct, ap.total_attempts,
                       COALESCE(ap.score, 0) AS score,
                       ROUND((ap.total_correct / NULLIF(ap.total_attempts,0)) * 100) AS accuracy
                FROM arena_progress ap
                JOIN users u ON u.user_id = ap.user_id
                WHERE ap.story_id = ? AND ap.part_number = ?
                ORDER BY ap.best_wpm DESC, ap.score DESC, accuracy DESC
                LIMIT 10
            ");
            $stmt->execute([$story_id, $part_number]);
            $board = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'leaderboard' => $board]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}