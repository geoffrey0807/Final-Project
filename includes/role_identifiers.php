<?php
function nextRoleUserIdentifier(PDO $pdo, string $role): string
{
    $roleFormats = [
        'admin' => ['prefix' => 'ADM', 'starting_number' => 0],
        'registrar' => ['prefix' => 'REG', 'starting_number' => 0],
        'dean' => ['prefix' => 'D', 'starting_number' => 1000],
        'professor' => ['prefix' => 'EDU', 'starting_number' => 2000],
        'student' => ['prefix' => 'STU', 'starting_number' => 3000],
    ];

    if (!isset($roleFormats[$role])) {
        throw new InvalidArgumentException('Cannot generate an identifier for this role.');
    }

    $year = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
    $prefix = $roleFormats[$role]['prefix'];
    $pattern = $prefix . '-' . $year . '-%';
    $statement = $pdo->prepare('SELECT id_number FROM users WHERE role = :role AND id_number LIKE :pattern');
    $statement->execute(['role' => $role, 'pattern' => $pattern]);

    $lastNumber = $roleFormats[$role]['starting_number'];
    $identifierPattern = '/^' . preg_quote($prefix . '-' . $year . '-', '/') . '([0-9]{4})$/D';
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $existingIdentifier) {
        if (preg_match($identifierPattern, (string) $existingIdentifier, $matches)) {
            $lastNumber = max($lastNumber, (int) $matches[1]);
        }
    }

    if ($lastNumber >= 9999) {
        throw new RuntimeException('The role-specific account ID sequence is full for this year.');
    }

    return sprintf('%s-%d-%04d', $prefix, $year, $lastNumber + 1);
}

function insertUserWithGeneratedRoleIdentifier(PDO $pdo, string $role, callable $insertUser): void
{
    $lockName = 'apex_role_user_identifier';
    $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
    $lock->execute(['lock_name' => $lockName]);

    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Unable to reserve a unique account ID. Please try again.');
    }

    try {
        $insertUser(nextRoleUserIdentifier($pdo, $role));
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $release->execute(['lock_name' => $lockName]);
    }
}
