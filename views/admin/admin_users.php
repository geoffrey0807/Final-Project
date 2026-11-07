<?php
require_once '../../includes/session_check.php';
checkAccess(['admin'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/admin_account_helpers.php';
require_once '../../includes/academic_year.php';

$pageTitle = 'User Management | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611052430', '../../css/student.css?v=202610292224'];
$extraJs = '../../js/admin.js?v=202611051930';
$bodyClass = 'admin-dashboard student-dashboard admin-portal';
$useVisualTheme = true;
$hideSharedSearch = true;
$adminPage = 'users';
$csrfToken = adminCsrfToken();
$academicPeriod = academicPeriodFromValue($_SESSION['academic_period'] ?? null);
$roleOptions = [
    'admin' => 'Admin',
    'registrar' => 'Registrar',
    'dean' => 'Dean',
    'professor' => 'Professor',
    'student' => 'Student',
];
$requestedRoles = $_GET['roles'] ?? ['all'];
if (!is_array($requestedRoles)) {
    $requestedRoles = [];
}
$requestedRoles = array_filter($requestedRoles, 'is_string');
$selectedRoles = array_values(array_intersect($requestedRoles, array_merge(['all'], array_keys($roleOptions))));
if (in_array('all', $selectedRoles, true) || !$selectedRoles) {
    $selectedRoles = ['all'];
}

$statusOptions = [
    'active' => 'Active',
    'disabled' => 'Disabled',
    'archived' => 'Archived',
];
$requestedStatuses = $_GET['status'] ?? ['active'];
if (!is_array($requestedStatuses)) {
    $requestedStatuses = [];
}
$requestedStatuses = array_filter($requestedStatuses, 'is_string');
$selectedStatuses = array_values(array_intersect($requestedStatuses, array_keys($statusOptions)));
if (!$selectedStatuses) {
    $selectedStatuses = ['active'];
}

$roleCondition = '1 = 1';
$queryParams = [];
if ($selectedRoles !== ['all']) {
    $rolePlaceholders = [];
    foreach ($selectedRoles as $index => $role) {
        $parameter = 'role_' . $index;
        $rolePlaceholders[] = ':' . $parameter;
        $queryParams[$parameter] = $role;
    }
    $roleCondition = 'u.role IN (' . implode(', ', $rolePlaceholders) . ')';
}

$statusConditions = [];
$archivedDateFilter = academicPeriodDateFilter('u.archived_at', 'archived_accounts');
foreach ($selectedStatuses as $index => $accountStatus) {
    $statusCondition = match ($accountStatus) {
        'active' => '(u.is_active = 1 AND u.archived_at IS NULL)',
        'disabled' => '(u.is_active = 0 AND u.archived_at IS NULL)',
        'archived' => '(u.archived_at IS NOT NULL' . $archivedDateFilter . ')',
    };
    if ($accountStatus === 'archived') {
        $queryParams = array_merge($queryParams, academicPeriodDateParams('archived_accounts'));
    }
    $statusConditions[] = $statusCondition;
}

$accountsStmt = $pdo->prepare("
    SELECT u.id, u.id_number, u.last_name, u.first_name, u.middle_name, u.suffix,
           u.email, u.role, u.created_at, u.is_active, u.archived_at
    FROM users u
    WHERE $roleCondition
      AND (" . implode(' OR ', $statusConditions) . ")
    ORDER BY u.role, u.last_name, u.first_name
");
$accountsStmt->execute($queryParams);
$accounts = $accountsStmt->fetchAll();
$selectedStatusLabels = array_map(
    static fn(string $status): string => $statusOptions[$status],
    $selectedStatuses
);
$statusHeading = implode(', ', $selectedStatusLabels) . ' Accounts';
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/admin_sidebar.php'; ?>
    <div class="student-dashboard-content container">
        <section class="card admin-user-table-card admin-role-table-card" aria-labelledby="activeAccountsTitle">
            <div class="table-toolbar">
                <div>
                    <h1 class="card-title" id="activeAccountsTitle"><?= htmlspecialchars($statusHeading, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-muted"><?= number_format(count($accounts)) ?> accounts</p>
                </div>
                <div class="admin-user-toolbar-actions">
                    <button type="button" class="btn btn-primary admin-create-registrar-trigger"
                        data-open-modal="createRegistrarModal">
                        <span aria-hidden="true">+</span> Registrar
                    </button>
                    <details class="admin-account-filter" data-account-filter>
                        <summary>
                            <span>Filter</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 6h16M7 12h10m-7 6h4" />
                            </svg>
                        </summary>
                        <form method="get" action="admin_users.php">
                            <div class="admin-account-filter-groups">
                                <fieldset>
                                    <legend>Roles</legend>
                                    <label>
                                        <input type="checkbox" name="roles[]" value="all" data-role-all
                                            <?= $selectedRoles === ['all'] ? 'checked' : '' ?>>
                                        All
                                    </label>
                                    <?php foreach ($roleOptions as $roleValue => $roleLabel): ?>
                                        <label>
                                            <input type="checkbox" name="roles[]" value="<?= htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8') ?>"
                                                data-role-choice <?= in_array($roleValue, $selectedRoles, true) ? 'checked' : '' ?>
                                                <?= $selectedRoles === ['all'] ? 'disabled' : '' ?>>
                                            <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <fieldset>
                                    <legend>Status</legend>
                                    <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
                                        <label>
                                            <input type="checkbox" name="status[]" value="<?= htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= in_array($statusValue, $selectedStatuses, true) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </div>
                            <div class="admin-account-filter-actions">
                                <a href="admin_users.php">Reset</a>
                                <button type="submit">Apply</button>
                            </div>
                        </form>
                    </details>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table admin-user-table" id="adminUsersTable">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Last Name</th>
                            <th>First Name</th>
                            <th>Middle Name</th>
                            <th>Suffix</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Account Created</th>
                            <th>Status</th>
                            <th>Password</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$accounts): ?>
                            <tr><td class="admin-table-empty" colspan="11">No accounts match the selected filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($accounts as $account): ?>
                                <?php
                                $displayName = trim($account['first_name'] . ' ' . $account['last_name']);
                                $accountStatus = $account['archived_at'] !== null
                                    ? 'archived'
                                    : ((int) $account['is_active'] === 1 ? 'active' : 'disabled');
                                $editableRole = in_array($account['role'], ['registrar', 'dean', 'professor', 'student'], true);
                                ?>
                                <tr id="user-<?= (int) $account['id'] ?>">
                                    <td class="account-id-cell"><?= htmlspecialchars($account['id_number'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                    <td><?= htmlspecialchars($account['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($account['first_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($account['middle_name'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                    <td><?= htmlspecialchars($account['suffix'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                    <td><?= htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="role-pill role-<?= htmlspecialchars($account['role'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($account['role']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= htmlspecialchars(date('M j, Y', strtotime($account['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(ucfirst($accountStatus), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($editableRole): ?>
                                            <button type="button" class="btn btn-outline btn-sm"
                                                data-open-modal="adminPasswordModal"
                                                data-user-id="<?= (int) $account['id'] ?>"
                                                data-user-name="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"
                                                data-return-to="<?= htmlspecialchars($accountStatus, ENT_QUOTES, 'UTF-8') ?>">Change</button>
                                        <?php else: ?>
                                            <span class="text-muted">Not available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($editableRole): ?>
                                            <div class="admin-account-actions">
                                                <?php if ($accountStatus === 'active'): ?>
                                                    <form action="../../api/admin_accounts_process.php" method="POST"
                                                        onsubmit="return confirm('Disable access for <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="disable_user">
                                                        <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">Disable</button>
                                                    </form>
                                                <?php elseif ($accountStatus === 'disabled'): ?>
                                                    <form action="../../api/admin_accounts_process.php" method="POST"
                                                        onsubmit="return confirm('Restore access for <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="restore_user">
                                                        <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                                        <input type="hidden" name="return_to" value="disabled">
                                                        <button type="submit" class="btn btn-outline btn-restore btn-sm">Restore Access</button>
                                                    </form>
                                                    <form action="../../api/admin_accounts_process.php" method="POST"
                                                        onsubmit="return confirm('Archive <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> for records?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="archive_user">
                                                        <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                                        <input type="hidden" name="return_to" value="disabled">
                                                        <button type="submit" class="btn btn-outline btn-archive btn-sm">Archive</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form action="../../api/admin_accounts_process.php" method="POST"
                                                        onsubmit="return confirm('Unarchive <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> and move the account back to Disabled Accounts?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="unarchive_user">
                                                        <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                                        <input type="hidden" name="return_to" value="disabled">
                                                        <button type="submit" class="btn btn-outline btn-restore btn-sm">Unarchive</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">View only</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
<?php
$openCreateRegistrarModal = false;
require __DIR__ . '/_create_registrar_modal.php';
?>
<div class="modal-backdrop" id="adminPasswordModal">
    <div class="modal-dialog">
        <form action="../../api/admin_accounts_process.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="change_user_password">
            <input type="hidden" name="user_id" id="passwordUserId">
            <input type="hidden" name="return_to" id="passwordReturnTo" value="active">
            <div class="modal-header">
                <h3 class="modal-title">Change Account Password</h3>
                <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted">For <strong id="passwordUserName"></strong>. This sets a new password directly; the user's current password is not required.</p>
                <div class="form-group">
                    <label for="adminNewPassword">New password</label>
                    <input type="password" id="adminNewPassword" name="new_password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                    <small class="text-muted">8–72 characters, including uppercase and lowercase letters, a number, and a special character.</small>
                </div>
                <div class="form-group">
                    <label for="adminConfirmPassword">Confirm new password</label>
                    <input type="password" id="adminConfirmPassword" name="confirm_password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="passwordRequestChannel">Request received through</label>
                    <select id="passwordRequestChannel" name="request_channel" class="form-control" required>
                        <option value="contact">Contact request</option>
                        <option value="ticket">Support ticket</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="passwordRequestReference">Ticket reference <span class="text-muted">(required for support tickets)</span></label>
                    <input type="text" id="passwordRequestReference" name="request_reference" class="form-control" maxlength="100">
                </div>
                <p class="text-muted">The new password is never displayed after saving. Share it with the user through a verified, secure channel.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Change Password</button>
            </div>
        </form>
    </div>
</div>
<button type="button" class="student-sidebar-backdrop" aria-label="Close admin navigation"></button>
<button type="button" class="student-mobile-toggle" aria-label="Open admin navigation"
    aria-controls="studentSidebar" aria-expanded="false">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
</button>

<?php require_once '../../includes/footer.php'; ?>
