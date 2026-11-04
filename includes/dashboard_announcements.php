<?php
$dashboardAnnouncements = is_array($dashboardAnnouncements ?? null) ? $dashboardAnnouncements : [];
$dashboardAnnouncementsHref = $dashboardAnnouncementsHref ?? '../../visual/announcement.php';
?>
<aside class="dashboard-announcements" aria-labelledby="dashboardAnnouncementsTitle">
    <h2 id="dashboardAnnouncementsTitle">Announcements</h2>
    <div class="dashboard-announcement-list">
        <?php foreach (array_slice($dashboardAnnouncements, 0, 6) as $announcement): ?>
            <a class="dashboard-announcement-item"
                href="<?= htmlspecialchars($dashboardAnnouncementsHref, ENT_QUOTES, 'UTF-8') ?>">
                <strong><?= htmlspecialchars($announcement['title'] ?? 'Announcement', ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (!empty($announcement['course_code']) || !empty($announcement['first_name'])): ?>
                    <small>
                        <?php if (!empty($announcement['role'])): ?>
                            <?= htmlspecialchars(announcementAuthorLabel($announcement['role']), ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                        <?= !empty($announcement['course_code']) ? ' &middot; ' . htmlspecialchars($announcement['course_code'], ENT_QUOTES, 'UTF-8') : '' ?>
                        <?= !empty($announcement['first_name']) ? ' &middot; ' . htmlspecialchars(trim(($announcement['first_name'] ?? '') . ' ' . ($announcement['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') : '' ?>
                    </small>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (empty($dashboardAnnouncements)): ?>
            <p class="dashboard-announcement-empty">No announcements yet.</p>
        <?php endif; ?>
    </div>
</aside>