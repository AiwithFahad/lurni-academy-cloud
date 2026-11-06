<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// POST /api/quiz-submit.php — grade the signed-in student's quiz attempt
// server-side against the stored answers and record it in quiz_attempts.
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
verify_csrf();

$quizId = (int)($_POST['quiz_id'] ?? 0);
$answers = (array)($_POST['answers'] ?? []);
$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT qz.* FROM quizzes qz JOIN enrollments en ON en.course_id=qz.course_id AND en.student_id=?
    WHERE qz.id=? AND qz.tenant_id=? AND qz.status='published'");
$s->execute([$sid, $quizId, $tid]); $quiz = $s->fetch() ?: null;
if (!$sid || !$quiz) { flash('error', 'Quiz not available.'); redirect('/student/quizzes.php'); }

$s = $pdo->prepare('SELECT id, points FROM questions WHERE quiz_id=? AND tenant_id=?');
$s->execute([$quizId, $tid]); $questions = $s->fetchAll();
if (!$questions) { flash('error', 'This quiz has no questions yet.'); redirect('/student/take-quiz.php?id=' . $quizId); }

$score = 0.0; $total = 0.0;
foreach ($questions as $qq) {
    $total += (float)$qq['points'];
    $chosen = (int)($answers[$qq['id']] ?? 0);
    if ($chosen) {
        $c = $pdo->prepare('SELECT is_correct FROM answers WHERE id=? AND question_id=?');
        $c->execute([$chosen, (int)$qq['id']]);
        if ((int)$c->fetchColumn() === 1) $score += (float)$qq['points'];
    }
}
$percent = $total > 0 ? round($score / $total * 100, 2) : 0.0;
$passed = $percent >= (float)$quiz['pass_percent'] ? 1 : 0;
$pdo->prepare('INSERT INTO quiz_attempts(tenant_id,quiz_id,student_id,score,total_points,percent,passed,answers_json) VALUES(?,?,?,?,?,?,?,?)')
    ->execute([$tid, $quizId, $sid, $score, $total, $percent, $passed, json_encode($answers)]);
log_activity($pdo, 'quiz_attempt', 'Quiz "' . $quiz['title'] . '" scored ' . $percent . '%', $tid, $me['id']);
flash($passed ? 'success' : 'error', 'Quiz submitted: you scored ' . number_format($percent, 0) . '%. ' . ($passed ? 'Passed!' : 'The pass mark is ' . (int)$quiz['pass_percent'] . '%.'));
redirect('/student/take-quiz.php?id=' . $quizId . '&done=1');
