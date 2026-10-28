<?php
/**
 * visual/_partials/foot.php
 * Minimal site footer showing strictly:
 * 1. APEX learning hub logo
 * 2. (c)APEX Learning Hub. All rights reserved 2026
 * 3. terms of service | privacy policy | etc
 */
?>

<!-- ===== APEX Minimal Footer ===== -->
<footer class="site-footer">
    <div class="footer-container">
        <!-- APEX Learning Hub Logo -->
        <a href="homepage.php" class="footer-logo" aria-label="APEX Learning Hub">
            <svg class="footer-logo-mark" viewBox="0 0 504 428" role="img" aria-hidden="true">
                <title>APEX Mark</title>
                <path fill="#ffffff" d="M252 0 154 103a19 19 0 0 0-5 13v97L0 361v67h82l120-119v-62l50-49z"/>
                <path fill="var(--teal)" d="m262 13 87 92a15 15 0 0 1 5 12v97l150 147v67h-75L301 301v-53l-39-40z"/>
                <path fill="var(--coral)" d="M213 312h83l118 116h-99l-55-56h-57l-54 56H97z"/>
            </svg>
            <div class="footer-logo-type">
                <span class="footer-wordmark">APEX</span>
                <span class="footer-tagline">Learning Hub</span>
            </div>
        </a>

        <!-- Copyright -->
        <div class="footer-copy">
            &copy; APEX Learning Hub. All rights reserved 2026
        </div>

        <!-- Links: terms of service | privacy policy | etc -->
        <div class="footer-legal-links">
            <a href="policies.php#terms" class="footer-link">
                <span data-lang-en>Terms of Service</span>
                <span data-lang-fil>[FILIPINO: Mga Tuntunin ng Serbisyo]</span>
            </a>
            <span class="footer-sep">|</span>
            <a href="policies.php#privacy" class="footer-link">
                <span data-lang-en>Privacy Policy</span>
                <span data-lang-fil>[FILIPINO: Patakaran sa Privacy]</span>
            </a>
            <span class="footer-sep">|</span>
            <a href="contact.php" class="footer-link">
                <span data-lang-en>Contact Support</span>
                <span data-lang-fil>[FILIPINO: Suporta sa Pakikipag-ugnayan]</span>
            </a>
        </div>
    </div>
</footer>

<!-- Interactive Back-To-Top Button with Chevron -->
<button type="button" id="backToTop" class="back-to-top-btn" aria-label="Scroll back to top" title="Back to top">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
</button>

<script src="js/visual.js"></script>
</body>
</html>
