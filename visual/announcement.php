<?php
require_once '../config/db.php';
$publicBulletinsStmt = $pdo->query("
    SELECT a.headline, a.message, a.homepage_category, a.created_at
    FROM announcements a
    JOIN users publisher ON publisher.id = a.author_id AND publisher.role = 'registrar'
    WHERE a.course_id IS NULL
      AND a.is_homepage_notice = 1
      AND a.homepage_category IS NOT NULL
    ORDER BY a.created_at DESC, a.id DESC
");
$publicBulletins = $publicBulletinsStmt->fetchAll(PDO::FETCH_ASSOC);
$publicCategoryLabels = [
    'academic' => 'Academic',
    'system' => 'System Update',
    'event' => 'Campus Event',
    'policy' => 'Policy Notice',
];
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

        <div class="filter-tabs" role="tablist">
            <button class="filter-tab active" data-filter="all" role="tab" aria-selected="true">
                All Bulletins

            </button>
            <button class="filter-tab" data-filter="academic" role="tab" aria-selected="false">
                Academic

            </button>
            <button class="filter-tab" data-filter="system" role="tab" aria-selected="false">
                System Updates

            </button>
            <button class="filter-tab" data-filter="event" role="tab" aria-selected="false">
                Campus Events

            </button>
            <button class="filter-tab" data-filter="policy" role="tab" aria-selected="false">
                Policy Notices

            </button>
        </div>

        <div class="announcement-grid" id="announcementList">

            <?php foreach ($publicBulletins as $bulletin): ?>
                <?php
                $category = $bulletin['homepage_category'];
                $categoryLabel = $publicCategoryLabels[$category] ?? 'Bulletin';
                $categoryClass = match ($category) {
                    'academic' => 'badge-coral',
                    'system' => 'badge-teal',
                    default => 'badge-navy',
                };
                ?>
                <article class="announcement-card" data-category="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="badge <?= $categoryClass ?>"><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <time datetime="<?= htmlspecialchars(date('Y-m-d', strtotime($bulletin['created_at'])), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(date('F j, Y', strtotime($bulletin['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </time>
                    <h3><?= htmlspecialchars($bulletin['headline'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= nl2br(htmlspecialchars($bulletin['message'], ENT_QUOTES, 'UTF-8')) ?></p>
                </article>
            <?php endforeach; ?>
            <?php if (!$publicBulletins): ?>
                <div class="announcement-empty-state">
                    <h2>No public bulletins yet</h2>
                    <p>Official homepage notices will appear here when published by the College Registrar.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>

</main>

<?php include '_partials/foot.php'; ?>
