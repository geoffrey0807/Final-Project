<?php
$visualBase = $visualBase ?? '';
require_once __DIR__ . '/../../includes/portal_toast.php';
/**
 * visual/_partials/foot.php
 * Shared site footer and optional public support assistant.
 */
?>

<!-- ===== APEX Minimal Footer ===== -->
<footer class="site-footer">
    <div class="footer-container">
        <!-- APEX Learning Hub Logo -->
        <div class="footer-logo" role="img" aria-label="APEX Learning Hub">
            <svg class="footer-logo-mark" viewBox="0 0 504 428" role="img" aria-hidden="true">
                <title>APEX Mark</title>
                <path fill="#ffffff" d="M252 0 154 103a19 19 0 0 0-5 13v97L0 361v67h82l120-119v-62l50-49z" />
                <path fill="#ffffff" d="m262 13 87 92a15 15 0 0 1 5 12v97l150 147v67h-75L301 301v-53l-39-40z" />
                <path fill="#ffffff" d="M213 312h83l118 116h-99l-55-56h-57l-54 56H97z" />
            </svg>
            <div class="footer-logo-type">
                <span class="footer-wordmark">APEX</span>
                <span class="footer-tagline">Learning Hub</span>
            </div>
        </div>

        <!-- Copyright -->
        <div class="footer-copy">
            &copy; APEX Learning Hub. All rights reserved 2022

        </div>

    </div>
</footer>

<?php renderPortalToast(); ?>

<!-- Interactive Back-To-Top Button with Chevron -->
<button type="button" id="backToTop"
    class="back-to-top-btn<?= !empty($showPublicAssistant) ? ' back-to-top-with-assistant' : '' ?>"
    aria-label="Scroll back to top" title="Back to top">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
</button>

<?php if (!empty($showPublicAssistant)): ?>
    <aside class="apex-assistant" data-apex-assistant data-endpoint="../api/chatbot_request_process.php"
        data-csrf-token="<?= htmlspecialchars($publicAssistantCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button class="apex-assistant-launcher" type="button" data-assistant-open aria-expanded="false"
            aria-controls="apexAssistantPanel" aria-label="Open the APEX local support assistant">
            <span class="apex-assistant-launcher-icon" aria-hidden="true">
                <svg viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M451 108H103C57 108 20 145 20 191V306C20 350 54 386 97 389M103 108C57 108 20 145 20 191V278"
                        stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round" />
                    <path
                        d="M160 163H425C467 163 500 197 500 239V324C500 366 467 400 425 400H290L170 488V400H160C118 400 85 366 85 324V239C85 197 118 163 160 163Z"
                        stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M165 235H417M165 285H417M165 335H417" stroke="currentColor" stroke-width="24"
                        stroke-linecap="round" />
                </svg>
            </span>
            Contact us

        </button>
        <section class="apex-assistant-panel" id="apexAssistantPanel" data-assistant-panel hidden role="dialog"
            aria-modal="false" aria-labelledby="apexAssistantTitle">
            <header class="apex-assistant-header">
                <span class="apex-assistant-heading">
                    <strong id="apexAssistantTitle">APEX Support</strong>
                    <small>Local Assistant</small>
                </span>
                <button class="apex-assistant-close" type="button" data-assistant-close
                    aria-label="Close support assistant">&times;</button>
            </header>
            <div class="apex-assistant-messages" data-assistant-messages role="log" aria-live="polite"
                aria-relevant="additions text" aria-label="Chat messages"></div>
            <form class="apex-assistant-form" data-assistant-form novalidate>
                <label class="apex-assistant-sr-only" for="apexAssistantInput">Type your reply</label>
                <input type="text" id="apexAssistantInput" data-assistant-input maxlength="5000"
                    placeholder="Type a topic or reply…" autocomplete="off">
                <input type="text" name="fax_number" data-assistant-honeypot tabindex="-1" class="apex-assistant-honeypot"
                    autocomplete="new-password" aria-hidden="true">
                <button type="submit" data-assistant-send aria-label="Send message">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 12 20 4l-5 16-3-7-8-1Zm8 1 8-9"></path>
                    </svg>
                </button>
            </form>
            <p class="apex-assistant-footnote">Replies are handled locally. Submitted concerns go to the Admin team.</p>
        </section>
    </aside>
<?php endif; ?>

<script src="<?= htmlspecialchars($visualBase) ?>js/visual.js?v=202611051815"></script>
<?php if (!empty($publicAnnouncementPage)): ?>
    <script src="<?= htmlspecialchars($visualBase) ?>../js/announcement-attachments.js?v=202611062030"></script>
<?php endif; ?>
<script src="<?= htmlspecialchars($visualBase) ?>../js/portal-toast.js?v=202611041835"></script>
</body>

</html>