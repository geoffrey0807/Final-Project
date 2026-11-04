<?php
require_once '../../includes/session_check.php';
checkAccess(['admin'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/admin_account_helpers.php';
require_once '../../includes/portal_notification_counts.php';
require_once '../../includes/portal_pagination.php';

$pageTitle = 'Message Requests | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611031950', '../../css/student.css?v=202610292224', '../../css/portal-layout.css?v=202611041318'];
$extraJs = '../../js/admin.js?v=202611031950';
$bodyClass = 'admin-dashboard student-dashboard admin-portal';
$useVisualTheme = true;
$hideSharedSearch = true;
$adminPage = 'messages';
$csrfToken = adminCsrfToken();
$supportRequestCsrfToken = $_SESSION['support_request_csrf'] ??= bin2hex(random_bytes(32));
$requestedStatus = $_GET['status'] ?? 'all';
$selectedStatus = is_string($requestedStatus) ? $requestedStatus : 'all';
if (!in_array($selectedStatus, ['all', 'new', 'in_progress', 'resolved'], true)) {
    $selectedStatus = 'all';
}

$itemsPerPage = 10;
$countSql = 'SELECT COUNT(*) FROM support_requests';
$countParams = [];
if ($selectedStatus !== 'all') {
    $countSql .= ' WHERE status = :status';
    $countParams['status'] = $selectedStatus;
}
$requestCountStmt = $pdo->prepare($countSql);
$requestCountStmt->execute($countParams);
$totalRequests = (int) $requestCountStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRequests / $itemsPerPage));
$requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = max(1, min($requestedPage === false || $requestedPage === null ? 1 : $requestedPage, $totalPages));
$requestOffset = ($currentPage - 1) * $itemsPerPage;

$requestsSql = "
    SELECT id, ticket_code, user_id, requester_name, requester_email, requester_role,
           subject, message, status, admin_response, created_at, updated_at, resolved_at
    FROM support_requests
";
$requestParams = [];
if ($selectedStatus !== 'all') {
    $requestsSql .= ' WHERE status = :status';
    $requestParams['status'] = $selectedStatus;
}
$requestsSql .= ' ORDER BY FIELD(status, "new", "in_progress", "resolved"), created_at DESC
    LIMIT :limit OFFSET :offset';
$requestsStmt = $pdo->prepare($requestsSql);
$requestsParams = $requestParams;
$requestsParams['limit'] = $itemsPerPage;
$requestsParams['offset'] = $requestOffset;
foreach ($requestsParams as $parameter => $value) {
    $requestsStmt->bindValue(':' . $parameter, $value, $parameter === 'limit' || $parameter === 'offset' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$requestsStmt->execute();
$requests = $requestsStmt->fetchAll();
foreach ($requests as $request) {
    markSupportRequestMessageRead(
        $pdo,
        (int) $request['id'],
        (int) $_SESSION['user_id'],
        $request['message']
    );
}
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/admin_sidebar.php'; ?>
    <div class="student-dashboard-content container">
        <section class="dashboard-header admin-portal-heading">
            <div class="dashboard-title">
                <h1>Message Requests</h1>
                <p>Homepage inquiries and verified APEX support tickets, all in one review queue.</p>
            </div>
        </section>

        <div class="support-request-toolbar">
            <div class="support-request-tools">
                <form class="support-request-filter" method="get" action="admin_message_requests.php">
                    <label for="requestStatusFilter">Show</label>
                    <select id="requestStatusFilter" name="status" class="form-control" onchange="this.form.submit()">
                        <?php foreach (['all' => 'All requests', 'new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $selectedStatus === $value ? ' selected' : '' ?>><?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($requests): ?>
                    <form class="support-request-delete-form" id="deleteSupportRequestsForm"
                        action="../../api/support_requests_delete.php" method="post" data-support-delete-form
                        data-confirm-message="Permanently delete the selected requests? This cannot be undone.">
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
        </div>

        <?php if (!$requests): ?>
            <section class="student-page-list" aria-label="Message requests">
                <div class="student-overview-card student-empty-state">
                    <h2>No New Message Requests</h2>
                    <p>Messages submitted through the local support assistant will appear here.</p>
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
                                        <?= htmlspecialchars($request['ticket_code'] ?? $request['requester_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                                <div>
                                    <span
                                        class="support-request-type"><?= $request['ticket_code'] !== null ? 'APEX support ticket' : 'Homepage message' ?></span>
                                    <h2><?= htmlspecialchars(ucwords(str_replace('_', ' ', $request['subject'])), ENT_QUOTES, 'UTF-8') ?>
                                    </h2>
                                    <p><?= htmlspecialchars($request['requester_name'], ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($request['requester_email'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($request['requester_role'] !== null): ?>
                                            · <?= htmlspecialchars(ucfirst($request['requester_role']), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <span
                                class="support-request-status status-<?= htmlspecialchars($request['status'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $request['status'])), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <div class="support-request-meta">
                            <?php if ($request['ticket_code'] !== null): ?>
                                <span>Ticket
                                    <strong><?= htmlspecialchars($request['ticket_code'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                            <?php else: ?>
                                <span>No ticket required</span>
                            <?php endif; ?>
                            <time datetime="<?= htmlspecialchars($request['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                Received
                                <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($request['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                            </time>
                        </div>
                        <div class="support-request-message">
                            <?= nl2br(htmlspecialchars($request['message'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                        <form class="support-request-response" action="../../api/admin_support_requests_process.php"
                            method="post">
                            <input type="hidden" name="csrf_token"
                                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                            <div class="support-request-form-row">
                                <label>
                                    Status
                                    <select class="form-control" name="status" required>
                                        <?php foreach (['new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $request['status'] === $value ? ' selected' : '' ?>>
                                                <?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="submit" class="btn btn-primary">Save &amp; Send Response</button>
                            </div>
                            <label for="response-<?= (int) $request['id'] ?>">Message to requester</label>
                            <textarea id="response-<?= (int) $request['id'] ?>" class="form-control" name="admin_response"
                                maxlength="5000"
                                placeholder="Write an update or explain how the concern was resolved. A response is required to mark this request resolved."><?= htmlspecialchars($request['admin_response'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <small>Account-linked requesters can read this response in My Support Requests. A homepage inquiry
                                has no linked portal inbox. Marking a concern resolved requires a response.</small>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php renderPortalPagination($currentPage, $totalRequests, $itemsPerPage, $selectedStatus === 'all' ? [] : ['status' => $selectedStatus]); ?>
        <?php endif; ?>
    </div>
</div>
<button type="button" class="student-sidebar-backdrop" aria-label="Close admin navigation"></button>
<button type="button" class="student-mobile-toggle" aria-label="Open admin navigation" aria-controls="studentSidebar"
    aria-expanded="false">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>

<?php require_once '../../includes/footer.php'; ?>