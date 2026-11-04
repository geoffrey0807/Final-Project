<?php
require_once __DIR__ . '/announcement_access.php';
require_once __DIR__ . '/academic_access.php';
require_once __DIR__ . '/academic_year.php';

function portalNotificationCounts(PDO $pdo, int $userId, string $role): array
{
    $validRoles = ['admin', 'registrar', 'dean', 'professor', 'student'];
    if (!in_array($role, $validRoles, true)) {
        throw new InvalidArgumentException('Invalid role for portal notification counts.');
    }

    $audienceVisibilitySql = announcementAudienceVisibilitySql('a', $role);
    $announcementVisibilitySql = '1 = 1';
    $announcementParams = [];

    if ($role === 'student') {
        $announcementVisibilitySql = "(
            a.course_id IS NULL
            OR EXISTS (
                SELECT 1
                FROM enrollments e
                JOIN courses scoped_course ON scoped_course.id = e.course_id
                WHERE e.course_id = a.course_id
                  AND e.student_id = :student_id
                  AND e.status = 'APPROVED'
                  AND " . studentEnrollmentCourseScopeSql('scoped_course', 'e') . "
            )
        )";
        $announcementParams['student_id'] = $userId;
    } elseif ($role === 'professor') {
        $announcementVisibilitySql = "(
            a.course_id IS NULL
            OR EXISTS (
                SELECT 1
                FROM courses professor_course
                WHERE professor_course.id = a.course_id
                  AND professor_course.professor_id = :professor_id
            )
        )";
        $announcementParams['professor_id'] = $userId;
    } elseif ($role === 'dean') {
        $departmentId = requireDeanDepartmentId($pdo, $userId);
        $announcementVisibilitySql = "(a.course_id IS NULL OR (u.role = 'professor' AND u.department_id = :department_id))";
        $announcementParams['department_id'] = $departmentId;
    }

    $announcementDateFilter = academicPeriodDateFilter('a.created_at');
    $announcementStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.id)
        FROM announcements a
        JOIN users u ON u.id = a.author_id
        WHERE {$announcementVisibilitySql}
          AND {$audienceVisibilitySql}
          {$announcementDateFilter}
          AND NOT EXISTS (
              SELECT 1
              FROM user_announcement_reads announcement_read
              WHERE announcement_read.announcement_id = a.id
                AND announcement_read.user_id = :read_user_id
          )
    ");
    $announcementStmt->execute(array_merge(
        $announcementParams,
        ['read_user_id' => $userId],
        academicPeriodDateParams()
    ));

    if ($role === 'admin') {
        $messageStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM support_requests requests
            WHERE NOT EXISTS (
                SELECT 1
                FROM support_request_reads request_read
                WHERE request_read.request_id = requests.id
                  AND request_read.user_id = :user_id
                  AND request_read.message_hash = SHA2(requests.message, 256)
            )
        ");
    } else {
        $messageStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM support_requests requests
            WHERE requests.user_id = :user_id
              AND requests.admin_response IS NOT NULL
              AND requests.admin_response <> ''
              AND NOT EXISTS (
                  SELECT 1
                  FROM support_request_reads request_read
                  WHERE request_read.request_id = requests.id
                    AND request_read.user_id = :read_user_id
                    AND request_read.message_hash = SHA2(requests.admin_response, 256)
              )
        ");
    }
    $messageStmt->execute(
        $role === 'admin'
            ? ['user_id' => $userId]
            : ['user_id' => $userId, 'read_user_id' => $userId]
    );

    return [
        'announcements' => (int) $announcementStmt->fetchColumn(),
        'messages' => (int) $messageStmt->fetchColumn(),
    ];
}

function portalNotificationBadge(int $count, string $label): string
{
    if ($count < 1) {
        return '';
    }

    $displayCount = $count > 99 ? '99+' : (string) $count;
    $accessibleLabel = $count === 1 ? $label : $label . 's';
    return '<span class="portal-notification-badge" aria-label="'
        . htmlspecialchars($count . ' unread ' . $accessibleLabel, ENT_QUOTES, 'UTF-8')
        . '">' . htmlspecialchars($displayCount, ENT_QUOTES, 'UTF-8') . '</span>';
}

function markSupportRequestMessageRead(PDO $pdo, int $requestId, int $userId, string $message): void
{
    $statement = $pdo->prepare("
        INSERT INTO support_request_reads (request_id, user_id, message_hash)
        VALUES (:request_id, :user_id, :message_hash)
        ON DUPLICATE KEY UPDATE
            message_hash = VALUES(message_hash),
            read_at = CURRENT_TIMESTAMP
    ");
    $statement->execute([
        'request_id' => $requestId,
        'user_id' => $userId,
        'message_hash' => hash('sha256', $message),
    ]);
}
