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
        LEFT JOIN registrar_announcement_acknowledgements acknowledged
          ON acknowledged.announcement_id = a.id
         AND acknowledged.user_id = :user_id
        WHERE a.course_id IS NULL
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
                    AND audience.role = :role
              )
          )
        ORDER BY a.created_at ASC, a.id ASC
    ");
    $statement->execute([
        'user_id' => $userId,
        'role' => $role,
    ]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}
