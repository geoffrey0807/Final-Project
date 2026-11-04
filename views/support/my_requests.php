<?php
require_once '../../includes/session_check.php';
checkAccess(['registrar', 'dean', 'professor', 'student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/portal_notification_counts.php';
require_once '../../includes/portal_pagination.php';

$role = $_SESSION['role'];
$supportRequestCsrfToken = $_SESSION['support_request_csrf'] ??= bin2hex(random_bytes(32));
$pageTitle = 'My Support Requests | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611031950', '../../css/student.css?v=202610292224', '../../css/portal-layout.css?v=202611041304'];
$bodyClass = 'admin-dashboard student-dashboard support-requests-portal';
$useVisualTheme = true;
$hideSharedSearch = true;
$portalViewBase = '../' . $role . '/';
$extraJs = $role === 'student'
    ? '../../js/student.js?v=202610291930'
    : ['../../js/admin.js?v=202611031901', '../../js/student.js?v=202610301835'];
$studentPage = $role === 'student' ? 'support' : '';
$professorView = $role === 'professor' ? 'support' : '';
$deanPage = $role === 'dean' ? 'support' : '';
$registrarView = $role === 'registrar' ? 'support' : '';

$itemsPerPage = 10;
$totalRequestsStmt = $pdo->prepare('SELECT COUNT(*) FROM support_requests WHERE user_id = :user_id');
$totalRequestsStmt->execute(['user_id' => (int) $_SESSION['user_id']]);
$totalRequests = (int) $totalRequestsStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRequests / $itemsPerPage));
$requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = max(1, min($requestedPage === false || $requestedPage === null ? 1 : $requestedPage, $totalPages));
$requestOffset = ($currentPage - 1) * $itemsPerPage;

$requestsStmt = $pdo->prepare("
    SELECT id, ticket_code, subject, message, status, admin_response, created_at, updated_at, resolved_at
    FROM support_requests
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
$requestsStmt->bindValue(':user_id', (int) $_SESSION['user_id'], PDO::PARAM_INT);
$requestsStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$requestsStmt->bindValue(':offset', $requestOffset, PDO::PARAM_INT);
$requestsStmt->execute();
$requests = $requestsStmt->fetchAll();

foreach ($requests as $request) {
    if (is_string($request['admin_response']) && $request['admin_response'] !== '') {
        markSupportRequestMessageRead(
            $pdo,
            (int) $request['id'],
            (int) $_SESSION['user_id'],
            $request['admin_response']
        );
    }
}

require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php
    match ($role) {
        'student' => require '../../includes/student_sidebar.php',
        'professor' => require '../../includes/professor_sidebar.php',
        'dean' => require '../../includes/dean_sidebar.php',
        'registrar' => require '../../includes/registrar_sidebar.php',
    };
    ?>
    <div class="student-dashboard-content container">
        <div class="support-request-page-actions">
            <a class="btn btn-primary support-request-assistant-link" data-support-logout-link
                href="../../auth/logout.php?destination=homepage-chat">Open support assistant</a>
            <?php if ($requests): ?>
                <form class="support-request-delete-form" id="deleteSupportRequestsForm"
                    action="../../api/support_requests_delete.php" method="post" data-support-delete-form
                    data-confirm-message="Permanently delete the selected support requests? This cannot be undone.">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($supportRequestCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="support-request-delete-button" type="submit" data-support-delete-button
                        aria-label="Delete selected requests" title="Permanently delete selected requests" disabled>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7h16M9 7V4h6v3m3 0-.8 13H6.8L6 7m4 4v5m4-5v5" />
                        </svg>
                        <span class="support-request-delete-count" data-support-selected-count hidden></span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$requests): ?>
            <section class="student-page-list" aria-label="Support requests">
                <div class="student-overview-card student-empty-state">
                    <h2>No Support Requests Yet</h2>
                    <p>Your submitted concerns and their replies will appear here.</p>
                </div>
            </section>
        <?php else: ?>
            <div class="support-request-list">
                <?php foreach ($requests as $request): ?>
                    <article class="card support-request-card">
                        <div class="support-request-card-heading">
                            <div class="support-request-card-title">
                                <label class="support-request-select">
                                    <input type="checkbox" name="request_ids[]" value="<?= (int) $request['id'] ?>"
                                        form="deleteSupportRequestsForm" data-support-request-select>
                                    <span class="sr-only">Select request
                                        <?= htmlspecialchars($request['ticket_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                                <div>
                                    <span class="support-request-type">APEX support ticket</span>
                                    <h2><?= htmlspecialchars(ucwords(str_replace('_', ' ', $request['subject'])), ENT_QUOTES, 'UTF-8') ?>
                                    </h2>
                                </div>
                            </div>
                            <span
                                class="support-request-status status-<?= htmlspecialchars($request['status'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $request['status'])), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <div class="support-request-meta">
                            <span>Ticket
                                <strong><?= htmlspecialchars($request['ticket_code'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                            <time datetime="<?= htmlspecialchars($request['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                Submitted
                                <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($request['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                            </time>
                        </div>
                        <div class="support-request-message">
                            <?= nl2br(htmlspecialchars($request['message'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                        <?php if ($request['admin_response'] !== null && $request['admin_response'] !== ''): ?>
                            <section class="support-request-reply" aria-label="Admin response">
                                <div>
                                    <span>Admin response</span>
                                    <?php if ($request['status'] === 'resolved' && $request['resolved_at'] !== null): ?>
                                        <time datetime="<?= htmlspecialchars($request['resolved_at'], ENT_QUOTES, 'UTF-8') ?>">
                                            Resolved
                                            <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($request['resolved_at'])), ENT_QUOTES, 'UTF-8') ?>
                                        </time>
                                    <?php endif; ?>
                                </div>
                                <p><?= nl2br(htmlspecialchars($request['admin_response'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </section>
                        <?php else: ?>
                            <p class="support-request-pending">The Admin team has received your ticket and will post its response
                                here.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php renderPortalPagination($currentPage, $totalRequests, $itemsPerPage); ?>
        <?php endif; ?>
    </div>
</div>
<?php if (!in_array($role, ['student', 'registrar'], true)): ?>
    <button type="button" class="student-sidebar-backdrop" aria-label="Close portal navigation"></button>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>