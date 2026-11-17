<?php if (!empty($useVisualTheme)): ?>
    </main>
    <?php if (isset($_SESSION['user_id']) && in_array($activeUserRole, ['admin', 'registrar', 'dean', 'professor', 'student'], true)): ?>
        <?php require __DIR__ . '/academic_period_dialog.php'; ?>
    <?php endif; ?>
    <?php
    $visualBase = $siteRootPath ?? '../';
    $visualBase .= 'visual/';
    if (isset($extraJs)):
        ?>
        <?php foreach ((array) $extraJs as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php
    endif;
    ?>
    <script src="<?= htmlspecialchars(($siteRootPath ?? '../') . 'js/portal-layout.js?v=202611092126') ?>"></script>
    <script src="<?= htmlspecialchars(($siteRootPath ?? '../') . 'js/academic-year.js?v=202611101515') ?>"></script>
    <?php if (!empty($mobileNavigationEnabled)): ?>
        <script src="<?= htmlspecialchars(($siteRootPath ?? '../') . 'js/mobile-nav.js?v=202611101725') ?>"></script>
    <?php endif; ?>
    <script
        src="<?= htmlspecialchars(($siteRootPath ?? '../') . 'js/announcement-attachments.js?v=202611062030') ?>"></script>
    <?php if ($registrarAnnouncementGate !== null): ?>
        <script
            src="<?= htmlspecialchars(($siteRootPath ?? '../') . 'js/registrar-announcement-gate.js?v=202611051955') ?>"></script>
    <?php endif; ?>
    <?php
    require __DIR__ . '/../visual/_partials/foot.php';
?>
<?php else: ?>
    </main>
    <footer class="app-footer">
        <div class="footer-content">
            <div class="footer-left">
                <strong>APEX Learning Hub</strong> &copy; 2022 &bull; All Rights Reserved.
            </div>
            <div class="footer-right">
                <span class="text-muted">Connected to XAMPP &bull; PHP <?= phpversion() ?> &bull; MySQL</span>
            </div>
        </div>
    </footer>
    <?php if (isset($extraJs)): ?>
        <?php foreach ((array) $extraJs as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php require_once __DIR__ . '/portal_toast.php';
    renderPortalToast(); ?>
    <script src="<?= htmlspecialchars(($siteRootPath ?? '../') . 'js/portal-toast.js?v=202611041835') ?>"></script>
    <script src="<?= htmlspecialchars(($siteRootPath ?? '../') . 'visual/js/visual.js?v=202611041602') ?>"></script>
    </body>

    </html>
<?php endif; ?>