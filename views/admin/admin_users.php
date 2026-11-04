<?php
require_once '../../includes/session_check.php';
checkAccess(['admin'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/admin_account_helpers.php';

$pageTitle = 'User Management | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202610301240', '../../css/student.css?v=202610292224'];
$extraJs = '../../js/admin.js?v=202611031940';
$bodyClass = 'admin-dashboard student-dashboard admin-portal';
$useVisualTheme = true;
$hideSharedSearch = true;
$adminPage = 'users';
$csrfToken = adminCsrfToken();
$requestedRole = $_GET['role'] ?? 'all';
$selectedRole = is_string($requestedRole) ? $requestedRole : 'all';
$allowedUserRoles = ['all', 'registrar', 'dean', 'professor', 'student'];
if (!in_array($selectedRole, $allowedUserRoles, true)) {
    $selectedRole = 'all';
}

$roleCondition = $selectedRole === 'all'
    ? '1 = 1'
    : 'u.role = :role';
$usersStmt = $pdo->prepare("
    SELECT u.id, u.id_number, u.last_name, u.first_name, u.middle_name, u.suffix,
           u.email, u.role, u.created_at
    FROM users u
    WHERE $roleCondition
      AND u.is_active = 1
      AND u.archived_at IS NULL
    ORDER BY u.role, u.last_name, u.first_name
");
$usersStmt->execute($selectedRole === 'all' ? [] : ['role' => $selectedRole]);
$users = $usersStmt->fetchAll();
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/admin_sidebar.php'; ?>
    <div class="student-dashboard-content container">
        <section class="dashboard-header admin-portal-heading">
            <div class="dashboard-title">
                <h1>User Management</h1>
                <p>Review active accounts across all roles.</p>
            </div>
        </section>

        <section class="card admin-user-table-card admin-role-table-card" aria-labelledby="activeAccountsTitle">
            <div class="table-toolbar">
                <div>
                    <h2 class="card-title" id="activeAccountsTitle">Active Accounts</h2>
                    <p class="text-muted"><?= number_format(count($users)) ?> active accounts</p>
                </div>
                <form class="admin-account-role-filter" method="get" action="admin_users.php">
                    <label for="activeAccountRole">Role</label>
                    <select class="form-control" id="activeAccountRole" name="role" onchange="this.form.submit()">
                        <option value="all"<?= $selectedRole === 'all' ? ' selected' : '' ?>>All</option>
                        <option value="registrar"<?= $selectedRole === 'registrar' ? ' selected' : '' ?>>Registrar</option>
                        <option value="dean"<?= $selectedRole === 'dean' ? ' selected' : '' ?>>Dean</option>
                        <option value="professor"<?= $selectedRole === 'professor' ? ' selected' : '' ?>>Professor</option>
                        <option value="student"<?= $selectedRole === 'student' ? ' selected' : '' ?>>Student</option>
                    </select>
                </form>
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
                        <?php if (!$users): ?>
                            <tr><td class="admin-table-empty" colspan="11">No active accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <?php $displayName = trim($user['first_name'] . ' ' . $user['last_name']); ?>
                                <tr id="user-<?= (int) $user['id'] ?>">
                                    <td class="account-id-cell"><?= htmlspecialchars($user['id_number'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                    <td><?= htmlspecialchars($user['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($user['middle_name'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                    <td><?= htmlspecialchars($user['suffix'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                    <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="role-pill role-<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= htmlspecialchars(date('M j, Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>Active</td>
                                    <td>
                                        <?php if (in_array($user['role'], ['registrar', 'dean', 'professor', 'student'], true)): ?>
                                            <button type="button" class="btn btn-outline btn-sm"
                                                data-open-modal="adminPasswordModal"
                                                data-user-id="<?= (int) $user['id'] ?>"
                                                data-user-name="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"
                                                data-return-to="active">Change</button>
                                        <?php else: ?>
                                            <span class="text-muted">Not available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (in_array($user['role'], ['registrar', 'dean', 'professor', 'student'], true)): ?>
                                            <div class="admin-account-actions">
                                                <form action="../../api/admin_accounts_process.php" method="POST"
                                                    onsubmit="return confirm('Disable access for <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="disable_user">
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">Disable</button>
                                                </form>
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
