<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// POST /api/quiz-submit.php — grade the signed-in student's quiz attempt
// server-side. Supports single-choice, true/false and multiple-answer
// questions; writes one quiz_attempt_answers detail row per question and
// enforces the quiz's max_attempts limit (0 = unlimited).
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
verify_csrf();

$quizId = (int)($_POST['quiz_id'] ?? 0);
$answers = (array)($_POST['answers'] ?? []);
$sid = current_student_id($pdo);
$s = $pdo->prepare("SELECT qz.* FROM quizzes qz JOIN enrollments en ON en.course_id=qz.course_id AND en.student_id=?
    WHERE qz.id=? AND qz.tenant_id=? AND qz.status='published' AND en.status IN ('active','completed')");
$s->execute([$sid, $quizId, $tid]); $quiz = $s->fetch() ?: null;
if (!$sid || !$quiz) { flash('error', 'Quiz not available.'); redirect('/student/quizzes.php'); }

if ((int)$quiz['max_attempts'] > 0) {
    $s = $pdo->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND tenant_id=?');
    $s->execute([$quizId, $sid, $tid]);
    if ((int)$s->fetchColumn() >= (int)$quiz['max_attempts']) {
        flash('error', 'You have used all ' . (int)$quiz['max_attempts'] . ' attempts for this quiz.');
        redirect('/student/take-quiz.php?id=' . $quizId);
    }
}

$s = $pdo->prepare('SELECT id, type, points FROM questions WHERE quiz_id=? AND tenant_id=? ORDER BY sort_order, id');
$s->execute([$quizId, $tid]); $questions = $s->fetchAll();
if (!$questions) { flash('error', 'This quiz has no questions yet.'); redirect('/student/take-quiz.php?id=' . $quizId); }

$score = 0.0; $total = 0.0; $details = [];
foreach ($questions as $qq) {
    $qid = (int)$qq['id']; $pts = (float)$qq['points']; $total += $pts;
    // Correct answer ids for this question.
    $c = $pdo->prepare('SELECT id FROM answers WHERE question_id=? AND is_correct=1');
    $c->execute([$qid]); $correct = array_map('intval', $c->fetchAll(PDO::FETCH_COLUMN));
    // Student's selection: scalar (radio) or array (checkboxes).
    $raw = $answers[$qid] ?? [];
    $chosen = array_values(array_filter(array_map('intval', is_array($raw) ? $raw : [$raw])));
    $earned = 0.0; $isCorrect = 0;
    if ($chosen && $correct) {
        if ($qq['type'] === 'multiple') {
            $good = count(array_intersect($chosen, $correct));
            $bad = count(array_diff($chosen, $correct));
            $earned = $pts * max(0.0, ($good - $bad) / count($correct));
            $isCorrect = ($good === count($correct) && $bad === 0) ? 1 : 0;
        } else {
            if (count($chosen) === 1 && in_array($chosen[0], $correct, true)) { $earned = $pts; $isCorrect = 1; }
        }
    }
    $score += $earned;
    $details[] = ['question_id' => $qid, 'selected' => $chosen, 'is_correct' => $isCorrect, 'points' => round($earned, 2)];
}
$percent = $total > 0 ? round($score / $total * 100, 2) : 0.0;
$passed = $percent >= (float)$quiz['pass_percent'] ? 1 : 0;
$startedAt = isset($_POST['started_at']) && strtotime((string)$_POST['started_at']) ? date('Y-m-d H:i:s', strtotime((string)$_POST['started_at'])) : null;

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO quiz_attempts(tenant_id,quiz_id,student_id,score,total_points,percent,passed,answers,answers_json,started_at,submitted_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$tid, $quizId, $sid, $score, $total, $percent, $passed, json_encode($answers), json_encode($answers), $startedAt]);
    $attemptId = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO quiz_attempt_answers(tenant_id,attempt_id,question_id,selected_answer_ids,is_correct,points_awarded,created_at) VALUES(?,?,?,?,?,?,NOW())');
    foreach ($details as $d) {
        $ins->execute([$tid, $attemptId, $d['question_id'], $d['selected'] ? implode(',', $d['selected']) : null, $d['is_correct'], $d['points']]);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    flash('error', 'Could not save your attempt. Please try again.');
    redirect('/student/take-quiz.php?id=' . $quizId);
}
log_activity($pdo, 'Quiz "' . $quiz['title'] . '" scored ' . $percent . '%');
notify_user($pdo, $tid, (int)$me['id'], $passed ? 'Quiz passed' : 'Quiz result', 'You scored ' . number_format($percent, 0) . '% on "' . $quiz['title'] . '".', '/student/quizzes.php');
flash($passed ? 'success' : 'error', 'Quiz submitted: you scored ' . number_format($percent, 0) . '%. ' . ($passed ? 'Passed!' : 'The pass mark is ' . (int)$quiz['pass_percent'] . '%.'));
redirect('/student/take-quiz.php?id=' . $quizId . '&done=1');
