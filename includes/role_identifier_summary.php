<?php
$roleIdentifierLabels = [
    'admin' => 'Administrator ID',
    'registrar' => 'Registrar ID',
    'dean' => 'Dean ID',
    'professor' => 'Professor ID',
    'student' => 'Student ID',
];
$roleIdentifierStatement = $pdo->prepare('SELECT id_number, role FROM users WHERE id = :id LIMIT 1');
$roleIdentifierStatement->execute(['id' => (int) ($_SESSION['user_id'] ?? 0)]);
$roleIdentifierAccount = $roleIdentifierStatement->fetch(PDO::FETCH_ASSOC);
$roleIdentifierLabel = $roleIdentifierLabels[$roleIdentifierAccount['role'] ?? ''] ?? 'Account ID';
$roleIdentifierValue = $roleIdentifierAccount['id_number'] ?? null;
?>
<p class="user-role-id-line" aria-label="<?= htmlspecialchars(
    $roleIdentifierLabel . ': ' . ($roleIdentifierValue ?: 'Not assigned'),
    ENT_QUOTES,
    'UTF-8'
) ?>">
    <?= htmlspecialchars($roleIdentifierValue ?: 'Not assigned', ENT_QUOTES, 'UTF-8') ?>
</p>
