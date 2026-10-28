<?php
?>
    </main>
    <footer class="app-footer">
        <div class="footer-content">
            <div class="footer-left">
                <strong>APEX Learning Hub</strong> &copy; <?= date('Y') ?> &bull; All Rights Reserved.
            </div>
            <div class="footer-right">
                <span class="text-muted">Connected to XAMPP &bull; PHP <?= phpversion() ?> &bull; MySQL</span>
            </div>
        </div>
    </footer>
    <?php if (isset($extraJs)): ?>
        <script src="<?= htmlspecialchars($extraJs) ?>"></script>
    <?php endif; ?>
</body>
</html>

