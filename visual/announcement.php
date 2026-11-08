<?php
require_once '../config/db.php';
$itemsPerPage = 10;
$validCategories = ['academic', 'system', 'event', 'policy'];
$requestedCategory = $_GET['category'] ?? 'all';
$categoryFilter = is_string($requestedCategory) && in_array($requestedCategory, $validCategories, true)
    ? $requestedCategory
    : 'all';
$whereCategory = $categoryFilter === 'all' ? '' : ' AND a.homepage_category = :category';
$countBulletinsStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM announcements a
    JOIN users publisher ON publisher.id = a.author_id AND publisher.role = 'registrar'
    WHERE a.course_id IS NULL
      AND a.is_homepage_notice = 1
      AND a.homepage_category IS NOT NULL{$whereCategory}
");
if ($categoryFilter === 'all') {
    $countBulletinsStmt->execute();
} else {
    $countBulletinsStmt->execute(['category' => $categoryFilter]);
}
$totalBulletins = (int) $countBulletinsStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalBulletins / $itemsPerPage));
$requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = max(1, min(
    $requestedPage === false || $requestedPage === null ? 1 : $requestedPage,
    $totalPages
));
$offset = ($currentPage - 1) * $itemsPerPage;
$publicBulletinsStmt = $pdo->prepare("
    SELECT a.headline, a.message, a.image_path, a.homepage_category, a.created_at, a.updated_at
    FROM announcements a
    JOIN users publisher ON publisher.id = a.author_id AND publisher.role = 'registrar'
    WHERE a.course_id IS NULL
      AND a.is_homepage_notice = 1
      AND a.homepage_category IS NOT NULL{$whereCategory}
    ORDER BY COALESCE(a.updated_at, a.created_at) DESC, a.id DESC
    LIMIT :limit OFFSET :offset
");
$bulletinParams = [];
if ($categoryFilter !== 'all') {
    $bulletinParams['category'] = $categoryFilter;
}
$publicBulletinsStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$publicBulletinsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($bulletinParams as $parameter => $value) {
    $publicBulletinsStmt->bindValue(':' . $parameter, $value, PDO::PARAM_STR);
}
$publicBulletinsStmt->execute();
$publicBulletins = $publicBulletinsStmt->fetchAll(PDO::FETCH_ASSOC);
require_once '../includes/portal_pagination.php';
$publicCategoryLabels = [
    'academic' => 'Academic',
    'system' => 'System Update',
    'event' => 'Campus Event',
    'policy' => 'Policy Notice',
];
$publicAnnouncementPage = true;
$pageTitle  = 'Announcements — APEX Learning Hub';
$activePage = 'announcement';
include '_partials/head.php';
?>

<main>

    <div class="page-band">
        <h1>Announcements</h1>
        <p>Stay updated with the latest news, advisories, and academic bulletins from APEX Learning Hub.</p>
    </div>

    <section class="section">
        <div class="container">
            <div class="filter-tabs" role="tablist" aria-label="Filter announcements by category">
                <a class="filter-tab<?= $categoryFilter === 'all' ? ' active' : '' ?>" href="announcement.php"
                    data-filter="all" role="tab" aria-selected="<?= $categoryFilter === 'all' ? 'true' : 'false' ?>">
                    All Bulletins
                </a>
                <a class="filter-tab<?= $categoryFilter === 'academic' ? ' active' : '' ?>"
                    href="announcement.php?category=academic" data-filter="academic" role="tab"
                    aria-selected="<?= $categoryFilter === 'academic' ? 'true' : 'false' ?>">
                    Academic
                </a>
                <a class="filter-tab<?= $categoryFilter === 'system' ? ' active' : '' ?>"
                    href="announcement.php?category=system" data-filter="system" role="tab"
                    aria-selected="<?= $categoryFilter === 'system' ? 'true' : 'false' ?>">
                    System Updates
                </a>
                <a class="filter-tab<?= $categoryFilter === 'event' ? ' active' : '' ?>"
                    href="announcement.php?category=event" data-filter="event" role="tab"
                    aria-selected="<?= $categoryFilter === 'event' ? 'true' : 'false' ?>">
                    Campus Events
                </a>
                <a class="filter-tab<?= $categoryFilter === 'policy' ? ' active' : '' ?>"
                    href="announcement.php?category=policy" data-filter="policy" role="tab"
                    aria-selected="<?= $categoryFilter === 'policy' ? 'true' : 'false' ?>">
                    Policy Notices
                </a>
            </div>

            <div class="announcement-grid announcement-notice-list" id="announcementList">
                <?php foreach ($publicBulletins as $bulletin): ?>
                    <?php
                    $category = $bulletin['homepage_category'];
                    $categoryLabel = $publicCategoryLabels[$category] ?? 'Bulletin';
                    $imagePath = $bulletin['image_path'];
                    $noticeDate = $bulletin['updated_at'] ?? $bulletin['created_at'];
                    $hasImage = is_string($imagePath)
                        && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $imagePath);
                    ?>
                    <article class="announcement-card announcement-notice-card" data-category="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="announcement-notice-meta">
                            <span><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <time datetime="<?= htmlspecialchars(date('c', strtotime($noticeDate)), ENT_QUOTES, 'UTF-8') ?>">
                                <?= !empty($bulletin['updated_at']) ? 'Updated ' : 'Published ' ?><?= htmlspecialchars(date('M j, Y · g:i A', strtotime($noticeDate)), ENT_QUOTES, 'UTF-8') ?>
                            </time>
                        </div>
                        <hr class="announcement-card-divider">
                        <h3><?= htmlspecialchars($bulletin['headline'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= nl2br(htmlspecialchars($bulletin['message'], ENT_QUOTES, 'UTF-8')) ?></p>
                        <?php if ($hasImage): ?>
                            <hr class="announcement-card-divider">
                            <button class="announcement-attachment-open" type="button"
                                data-announcement-attachment-open
                                data-image-src="../<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
                                data-image-alt="<?= htmlspecialchars($bulletin['headline'], ENT_QUOTES, 'UTF-8') ?>">
                                View Attachment
                            </button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$publicBulletins): ?>
                    <div class="announcement-empty-state">
                        <h2>No public bulletins yet</h2>
                        <p>Official homepage notices will appear here when published by the College Registrar.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            renderPortalPagination(
                $currentPage,
                $totalBulletins,
                $itemsPerPage,
                $categoryFilter === 'all' ? [] : ['category' => $categoryFilter]
            );
            ?>
        </div>
    </section>
</main>

<?php include '_partials/foot.php'; ?>
