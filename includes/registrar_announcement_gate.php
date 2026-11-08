<?php
function pendingRegistrarAnnouncements(PDO $pdo, string $role, int $userId): array
{
    $validRoles = ['admin', 'registrar', 'dean', 'professor', 'student'];
    if (!in_array($role, $validRoles, true) || $userId < 1) {
        return [];
    }

    $statement = $pdo->prepare("
        SELECT a.id, a.headline, a.message, a.image_path, a.created_at
        FROM announcements a
        JOIN users author ON author.id = a.author_id AND author.role = 'registrar'
            JOIN users recipient ON recipient.id = ?
        LEFT JOIN registrar_announcement_acknowledgements acknowledged
          ON acknowledged.announcement_id = a.id
         AND acknowledged.user_id = ?
        WHERE a.course_id IS NULL
          AND COALESCE(a.is_homepage_notice, 0) = 0
          AND a.created_at >= recipient.created_at
          AND acknowledged.announcement_id IS NULL
          AND (
              NOT EXISTS (
                  SELECT 1
                  FROM announcement_audience_roles audience
                  WHERE audience.announcement_id = a.id
              )
              OR EXISTS (
                  SELECT 1
                  FROM announcement_audience_roles audience
                  WHERE audience.announcement_id = a.id
                    AND audience.role = ?
              )
          )
        ORDER BY a.created_at ASC, a.id ASC
    ");
    $statement->execute([$userId, $userId, $role]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}
