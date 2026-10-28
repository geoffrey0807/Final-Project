<?php
/**
 * visual/_partials/head.php
 * Reusable header & sub-bar for all visual pages in APEX Learning Hub
 * @var string $pageTitle
 * @var string $activePage (home | announcement | policies | contact | about)
 */
$pageTitle = $pageTitle ?? 'APEX Learning Hub';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="css/visual.css?v=202610281809">
</head>

<body>

    <!-- ===== Primary Navigation Header ===== -->
    <header class="main-header">
        <div class="header-container">
            <!-- Logo -->
            <a href="homepage.php" class="header-logo" aria-label="APEX Learning Hub Home">
                <svg class="header-logo-mark" viewBox="0 0 504 428" role="img" aria-hidden="true">
                    <title>APEX Brand Mark</title>
                    <path fill="var(--navy)" d="M252 0 154 103a19 19 0 0 0-5 13v97L0 361v67h82l120-119v-62l50-49z" />
                    <path fill="var(--teal)" d="m262 13 87 92a15 15 0 0 1 5 12v97l150 147v67h-75L301 301v-53l-39-40z" />
                    <path fill="var(--coral)" d="M213 312h83l118 116h-99l-55-56h-57l-54 56H97z" />
                </svg>
                <div class="header-logo-type">
                    <span class="header-wordmark">APEX</span>
                    <span class="header-tagline">Learning Hub</span>
                </div>
            </a>

            <!-- Desktop Navigation -->
            <nav class="main-nav" id="mainNav" aria-label="Main Site Navigation">
                <a href="homepage.php" class="nav-link <?= $activePage === 'home' ? 'active' : '' ?>">
                    <span data-lang-en>Home</span>
                    <span data-lang-fil>[FILIPINO: Tahanan]</span>
                </a>
                <a href="announcement.php" class="nav-link <?= $activePage === 'announcement' ? 'active' : '' ?>">
                    <span data-lang-en>Announcements</span>
                    <span data-lang-fil>[FILIPINO: Mga Anunsyo]</span>
                </a>
                <a href="policies.php" class="nav-link <?= $activePage === 'policies' ? 'active' : '' ?>">
                    <span data-lang-en>Policies</span>
                    <span data-lang-fil>[FILIPINO: Mga Patakaran]</span>
                </a>
                <a href="contact.php" class="nav-link <?= $activePage === 'contact' ? 'active' : '' ?>">
                    <span data-lang-en>Contact Us</span>
                    <span data-lang-fil>[FILIPINO: Makipag-ugnayan]</span>
                </a>
                <a href="homepage.php#about" class="nav-link <?= $activePage === 'about' ? 'active' : '' ?>">
                    <span data-lang-en>About</span>
                    <span data-lang-fil>[FILIPINO: Tungkol sa Amin]</span>
                </a>
            </nav>

            <div class="header-actions">
                <button class="burger-btn" id="menuToggle" aria-label="Toggle Navigation Menu" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </header>

    <!-- ===== Sub-Utility Bar ===== -->
    <div class="sub-bar">
        <div class="sub-bar-container">
            <div class="sub-bar-right">
                <div class="sub-search" role="search">
                    <svg class="search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="globalSearch" placeholder="Search courses, announcements, policies…"
                        autocomplete="off" aria-label="Search site content">
                    <button type="button" id="searchClear" class="search-clear-btn" aria-label="Clear search query"
                        style="display:none;">&times;</button>
                    <div id="searchResults" class="search-results-dropdown" role="listbox" aria-live="polite"></div>
                </div>

                <div class="lang-wrapper">
                    <button id="langBtn" class="lang-btn" aria-haspopup="listbox" aria-expanded="false"
                        title="Change Language">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        <span id="langLabel">English</span>
                        <svg class="chevron-arrow" width="10" height="6" viewBox="0 0 10 6" fill="none"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                            aria-hidden="true">
                            <path d="M1 1l4 4 4-4" />
                        </svg>
                    </button>
                    <div id="langDropdown" class="lang-dropdown" role="listbox">
                        <button type="button" class="lang-option selected" data-lang="en" role="option">
                            <span>English</span>
                            <span class="lang-tag">EN</span>
                        </button>
                        <button type="button" class="lang-option" data-lang="fil" role="option">
                            <span>Filipino</span>
                            <span class="lang-tag">FIL</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>