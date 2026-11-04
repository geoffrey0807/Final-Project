<?php
function setPortalToast(string $message, string $type = 'success'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $message = trim($message);
    if ($message === '') {
        return;
    }

    $_SESSION['portal_toast'] = [
        'message' => $message,
        'type' => in_array($type, ['success', 'error', 'info'], true) ? $type : 'info',
    ];
}

function renderPortalToast(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $toast = $_SESSION['portal_toast'] ?? null;
    unset($_SESSION['portal_toast']);
    if (!is_array($toast)
        || !isset($toast['message'], $toast['type'])
        || !is_string($toast['message'])
        || !is_string($toast['type'])
        || !in_array($toast['type'], ['success', 'error', 'info'], true)) {
        $toast = null;
    }
    ?>
    <div class="portal-toast-region" data-portal-toast-region aria-live="polite" aria-relevant="additions text">
        <?php if ($toast !== null): ?>
            <div class="portal-toast portal-toast--<?= htmlspecialchars($toast['type'], ENT_QUOTES, 'UTF-8') ?>"
                data-portal-toast role="<?= $toast['type'] === 'error' ? 'alert' : 'status' ?>">
                <span class="portal-toast-message"><?= htmlspecialchars($toast['message'], ENT_QUOTES, 'UTF-8') ?></span>
                <button class="portal-toast-dismiss" type="button" data-portal-toast-dismiss
                    aria-label="Dismiss notification">&times;</button>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
