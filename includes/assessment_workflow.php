<?php
require_once __DIR__ . '/academic_access.php';

function materializeExpiredAssessmentSubmissions(
    PDO $pdo,
    int $courseId,
    string $academicYear,
    string $semester
): void {
    $eligibleEnrollment = studentEnrollmentCourseScopeSql('c', 'e');
    $insertMissed = $pdo->prepare("
        INSERT IGNORE INTO student_submissions
            (assessment_id, student_id, score, status, submitted_at)
        SELECT a.id, e.student_id, 0, 'Missed', NULL
        FROM assessments a
        JOIN courses c ON c.id = a.course_id AND c.archived_at IS NULL
        JOIN enrollments e ON e.course_id = c.id AND e.status = 'APPROVED'
        WHERE a.course_id = :course_id
          AND a.academic_year = :assessment_year
          AND a.semester = :assessment_semester
          AND e.academic_year = :enrollment_year
          AND e.semester = :enrollment_semester
          AND a.deadline < NOW()
          AND {$eligibleEnrollment}
    ");
    $insertMissed->execute([
        'course_id' => $courseId,
        'assessment_year' => $academicYear,
        'assessment_semester' => $semester,
        'enrollment_year' => $academicYear,
        'enrollment_semester' => $semester,
    ]);

    $expireReopened = $pdo->prepare("
        UPDATE student_submissions submission
                JOIN assessments a ON a.id = submission.assessment_id
                JOIN courses c ON c.id = a.course_id AND c.archived_at IS NULL
                JOIN enrollments e ON e.course_id = c.id
                        AND e.student_id = submission.student_id
                        AND e.status = 'APPROVED'
                SET submission.score = 0,
                        submission.status = 'Missed',
                        submission.answers_json = NULL,
                        submission.submitted_at = NULL,
                        submission.reopened_until = NULL
                WHERE a.course_id = :course_id
                    AND a.academic_year = :assessment_year
                    AND a.semester = :assessment_semester
                    AND e.academic_year = :enrollment_year
                    AND e.semester = :enrollment_semester
                    AND submission.status = 'Pending'
                    AND submission.reopened_until IS NOT NULL
                    AND submission.reopened_until < NOW()
                    AND {$eligibleEnrollment}
    ");
    $expireReopened->execute([
        'course_id' => $courseId,
        'assessment_year' => $academicYear,
        'assessment_semester' => $semester,
        'enrollment_year' => $academicYear,
        'enrollment_semester' => $semester,
    ]);
}

function assessmentTermPercentage(array $assessments, array $submissionsByAssessment): ?float
{
    $examCount = 0;
    $resolvedExamCount = 0;
    $examEarnedPoints = 0.0;
    $examPossiblePoints = 0.0;
    $quizEarnedPoints = 0.0;
    $quizPossiblePoints = 0.0;

    foreach ($assessments as $assessment) {
        $assessmentId = (int) $assessment['id'];
        $submission = $submissionsByAssessment[$assessmentId] ?? null;
        $points = (float) $assessment['total_points'];
        $submissionStatus = $submission['status'] ?? null;
        $isMissed = $submissionStatus === 'Missed';
        $hasScore = $submission && $submission['score'] !== null;
        $earnedPoints = $isMissed ? 0.0 : ($hasScore ? (float) $submission['score'] : 0.0);

        if ($assessment['assessment_type'] === 'Exam') {
            $examCount++;
            if ($isMissed || $hasScore) {
                $resolvedExamCount++;
                $examPossiblePoints += $points;
                $examEarnedPoints += $earnedPoints;
            }
        } elseif ($isMissed || $hasScore) {
            $quizPossiblePoints += $points;
            $quizEarnedPoints += $earnedPoints;
        }
    }

    if ($examCount === 0 || $resolvedExamCount !== $examCount || $examPossiblePoints <= 0) {
        return null;
    }

    $examPercentage = ($examEarnedPoints / $examPossiblePoints) * 100;
    $quizPercentage = $quizPossiblePoints > 0
        ? ($quizEarnedPoints / $quizPossiblePoints) * 100
        : 0.0;

    return min(100.0, max(0.0, ($examPercentage * 0.5) + ($quizPercentage * 0.5)));
}

function assessmentTermWeight(string $term): float
{
    return match ($term) {
        'Prelim' => 0.30,
        'Midterm' => 0.30,
        'Final' => 0.40,
        default => 0.0,
    };
}

function gradeConversionScale(): array
{
    return [
        '1.00' => '96.0 - 100',
        '1.25' => '91.5 - 95.9',
        '1.50' => '87.0 - 91.4',
        '1.75' => '82.5 - 86.9',
        '2.00' => '78.0 - 82.4',
        '2.25' => '73.5 - 77.9',
        '2.50' => '69.0 - 73.4',
        '2.75' => '64.5 - 68.9',
        '3.00' => '60.0 - 64.4',
        '5.00' => '0.0 - 59.9',
    ];
}
