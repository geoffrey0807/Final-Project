<?php
function announcementAuthorLabel(string $role): string
{
    return $role === 'registrar'
        ? 'Office of the College Registrar'
        : ucfirst($role);
}

function announcementAudienceVisibilitySql(string $announcementAlias, string $role): string
{
    if (!preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/D', $announcementAlias)) {
        throw new InvalidArgumentException('Invalid SQL alias for announcement audience.');
    }

    $validRoles = ['admin', 'registrar', 'dean', 'professor', 'student'];
    if (!in_array($role, $validRoles, true)) {
        throw new InvalidArgumentException('Invalid role for announcement audience.');
    }

    return "(
        NOT EXISTS (
            SELECT 1
            FROM announcement_audience_roles audience_rule
            WHERE audience_rule.announcement_id = {$announcementAlias}.id
        )
        OR EXISTS (
            SELECT 1
            FROM announcement_audience_roles audience_rule
            WHERE audience_rule.announcement_id = {$announcementAlias}.id
              AND audience_rule.role = '{$role}'
        )
    )";
}
