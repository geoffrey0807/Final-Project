<?php
require_once '../config/db.php';
$homepageBulletinsStmt = $pdo->query("
    SELECT a.headline, a.message, a.image_path, a.homepage_category, a.created_at, a.updated_at
    FROM announcements a
    JOIN users publisher ON publisher.id = a.author_id AND publisher.role = 'registrar'
    WHERE a.course_id IS NULL
      AND a.is_homepage_notice = 1
      AND a.homepage_category IS NOT NULL
    ORDER BY COALESCE(a.updated_at, a.created_at) DESC, a.id DESC
    LIMIT 3
");
$homepageBulletins = $homepageBulletinsStmt->fetchAll(PDO::FETCH_ASSOC);
$homepageCategoryLabels = [
    'academic' => 'Academic',
    'system' => 'System Update',
    'event' => 'Campus Event',
    'policy' => 'Policy Notice',
];
$publicAnnouncementPage = true;
$pageTitle = 'APEX Learning Hub — Home';
$activePage = 'home';
$bodyClass = 'home-page';
include '_partials/head.php';
?>

<main id="home">

    <!-- =============================================================
         1. HERO SECTION (Serif Font, Left-Aligned, No Chevron Icons)
         ============================================================= -->
    <section class="hero-section">
        <div class="hero-glow-blob" aria-hidden="true"></div>
        <div class="hero-glow-blob-2" aria-hidden="true"></div>

        <div class="hero-content">
            <h1 class="hero-title">
                Code Your Future,<br>Build Careers<br>

            </h1>

            <p class="hero-desc">
                Welcome to APEX, where students, faculty, and administrators connect on one platform built for programming education, from first line of code to final deployment.

            </p>

            <div class="hero-cta-group">
                <!-- Button 1: Explore Offerings -->
                <a href="#offerings" class="btn-hero-primary" id="btnExploreOfferings">
                    Explore

                </a>

                <!-- Button 3: Enter Portal -->
                <a href="../auth/login.php" class="btn-hero-secondary">
                    Login to Portal

                </a>
            </div>
        </div>
    </section>

    <!-- =============================================================
         2. APEX DEPARTMENTS
         ============================================================= -->
    <section class="section" id="offerings">
        <div class="container">
            <div class="section-header-block">
                <h2 class="section-title centered">What APEX Offers</h2>

            </div>

            <div class="media-carousel" role="region" aria-label="APEX Learning Hub departments">
                <button class="media-carousel-arrow" type="button" data-direction="previous"
                    aria-label="Previous department">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="m15 18-6-6 6-6"></path>
                    </svg>
                </button>
                <div class="media-carousel-viewport">
                    <div class="media-grid">
                        <article class="media-card" tabindex="0">
                            <div class="media-pic-wrap">
                                <img src="assets/departments/software-engineering.png"
                                    alt="Software engineer working with AI development tools" loading="lazy">
                                <div class="media-pic-overlay"></div>
                                <span class="media-tag-badge">DSEAD</span>
                            </div>
                            <div class="media-card-body">
                                <h3 class="media-card-title">Department of Software Engineering &amp; Application Development</h3>
                                <p class="media-card-desc">Build practical software solutions through application development, engineering methods, and mobile technologies.</p>
                                <div class="media-card-programs">
                                    <span>Programs</span>
                                    <ul>
                                        <li>BS in Software Engineering</li>
                                        <li>BS in Mobile Application Development</li>
                                    </ul>
                                </div>
                            </div>
                        </article>

                        <article class="media-card" tabindex="0">
                            <div class="media-pic-wrap">
                                <img src="assets/departments/computer-science.png"
                                    alt="Computer science workstation displaying code and artificial intelligence" loading="lazy">
                                <div class="media-pic-overlay"></div>
                                <span class="media-tag-badge">DSCS</span>
                            </div>
                            <div class="media-card-body">
                                <h3 class="media-card-title">Department of Computer Science</h3>
                                <p class="media-card-desc">Explore computing theory, intelligent systems, and creative digital technologies through hands-on learning.</p>
                                <div class="media-card-programs">
                                    <span>Programs</span>
                                    <ul>
                                        <li>BS in Computer Science</li>
                                        <li>BS in Game Programming / Multimedia Computing</li>
                                    </ul>
                                </div>
                            </div>
                        </article>

                        <article class="media-card" tabindex="0">
                            <div class="media-pic-wrap">
                                <img src="assets/departments/information-systems.png"
                                    alt="Information systems specialist working with AI-enabled computer systems" loading="lazy">
                                <div class="media-pic-overlay"></div>
                                <span class="media-tag-badge">DISWT</span>
                            </div>
                            <div class="media-card-body">
                                <h3 class="media-card-title">Department of Information Systems &amp; Web Technologies</h3>
                                <p class="media-card-desc">Connect business needs with digital solutions through information systems, web development, and full-stack practice.</p>
                                <div class="media-card-programs">
                                    <span>Programs</span>
                                    <ul>
                                        <li>BS in Information Systems</li>
                                        <li>Associate Degree in Full-Stack Web Development</li>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
                <button class="media-carousel-arrow" type="button" data-direction="next" aria-label="Next department">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="m9 18 6-6-6-6"></path>
                    </svg>
                </button>
            </div>
        </div>
    </section>

    <!-- =============================================================
         3. LATEST ANNOUNCEMENTS
         ============================================================= -->
    <section class="section" id="announcements">
        <div class="container">
            <div class="section-header-block announcement-header">
                <h2 class="section-title centered">Latest Announcements</h2>

            </div>

            <div class="home-card-carousel" role="region" aria-label="Latest announcements carousel">
                <button class="media-carousel-arrow" type="button" data-direction="previous"
                    aria-label="Previous announcement">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="m15 18-6-6 6-6"></path>
                    </svg>
                </button>
                <div class="home-card-carousel-viewport">
                    <div class="announcement-cards-grid home-card-carousel-track">
                        <?php foreach ($homepageBulletins as $bulletin): ?>
                            <?php
                            $imagePath = $bulletin['image_path'];
                            $category = $bulletin['homepage_category'];
                            $categoryLabel = $homepageCategoryLabels[$category] ?? 'Bulletin';
                            $noticeDate = $bulletin['updated_at'] ?? $bulletin['created_at'];
                            $hasImage = is_string($imagePath)
                                && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $imagePath);
                            ?>
                            <article class="ann-card">
                                <a class="ann-card-notice" href="announcement.php"
                                    aria-label="Read notice: <?= htmlspecialchars($bulletin['headline'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?>
                                    | <?= !empty($bulletin['updated_at']) ? 'Updated ' : '' ?><?= htmlspecialchars(date('F d, Y', strtotime($noticeDate)), ENT_QUOTES, 'UTF-8') ?>
                                    <hr class="ann-card-divider">
                                    <h3 class="ann-title"><?= htmlspecialchars($bulletin['headline'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p class="ann-snippet"><?= nl2br(htmlspecialchars($bulletin['message'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    <?php if ($hasImage): ?>
                                        <hr class="ann-card-divider">
                                    <?php endif; ?>
                                </a>
                                <?php if ($hasImage): ?>
                                    <button class="announcement-attachment-open" type="button"
                                        data-announcement-attachment-open
                                        data-image-src="../<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
                                        data-image-alt="<?= htmlspecialchars($bulletin['headline'], ENT_QUOTES, 'UTF-8') ?>">
                                        View Attachment
                                    </button>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php for ($slot = count($homepageBulletins); $slot < 3; $slot++): ?>
                            <article class="ann-card ann-card-placeholder">
                                <div>
                                    <div class="ann-badge-row">
                                        <span class="badge-tag">Next Bulletin</span>
                                    </div>
                                    <h3 class="ann-title">A new bulletin will appear here</h3>
                                    <p class="ann-snippet">The latest official homepage notice will be featured in this space.</p>
                                </div>
                            </article>
                        <?php endfor; ?>
                    </div>
                </div>
                <button class="media-carousel-arrow" type="button" data-direction="next" aria-label="Next announcement">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="m9 18 6-6-6-6"></path>
                    </svg>
                </button>
            </div>

        </div>
    </section>

    <!-- =============================================================
         4. INTERACTIVE CHEVRON ACCORDION (Campus Essentials & FAQ)
         ============================================================= -->
    <section class="section section-alt" id="essentials">
        <div class="container">
            <div class="section-header-block">
                <h2 class="section-title centered">Campus Essentials &amp;<br> Student Guide</h2>

            </div>

            <div class="home-card-carousel" role="region" aria-label="Student guide carousel">
                <button class="media-carousel-arrow" type="button" data-direction="previous"
                    aria-label="Previous student guide card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="m15 18-6-6 6-6"></path>
                    </svg>
                </button>
                <div class="home-card-carousel-viewport">
                    <div class="guide-cards-grid home-card-carousel-track">
                        <!-- Item 1 -->
                        <article class="guide-card">
                            <div class="ann-badge-row">
                                <span class="badge-tag badge-academic">Course Enrollment</span>

                            </div>
                            <h3 class="ann-title">
                                How do students enroll in courses for the new semester?

                            </h3>
                            <div class="guide-answer">
                                <p class="guide-answer-copy">
                                    Registered students can sign in to their student workspace, navigate to the Course
                                    Catalog,
                                    and submit enrollment applications with a single click. Your professor will review
                                    and
                                    approve pending requests directly.
                                </p>

                            </div>
                            <button type="button" class="guide-toggle" aria-expanded="false">
                                Read more
                            </button>
                        </article>

                        <!-- Item 2 -->
                        <article class="guide-card">
                            <div class="ann-badge-row">
                                <span class="badge-tag badge-system">Announcements</span>

                            </div>
                            <h3 class="ann-title">
                                Where can I check exam schedules and faculty announcements?

                            </h3>
                            <div class="guide-answer">
                                <p class="guide-answer-copy">
                                    All official updates, midterm room assignments, and maintenance alerts are posted on
                                    our
                                    public Announcements page as well as inside the authenticated student and faculty
                                    feeds.
                                </p>

                            </div>
                            <a href="announcement.php" class="guide-card-link">
                                Go to Announcements Page &rarr;

                            </a>
                            <button type="button" class="guide-toggle" aria-expanded="false">
                                Read more
                            </button>
                        </article>

                        <!-- Item 3 -->
                        <article class="guide-card">
                            <div class="ann-badge-row">
                                <span class="badge-tag badge-event">Academic Policies</span>

                            </div>
                            <h3 class="ann-title">
                                What are the institutional policies on attendance and grading?

                            </h3>
                            <div class="guide-answer">
                                <p class="guide-answer-copy">
                                    A minimum of 80% attendance is strictly enforced across all academic courses. The
                                    grading
                                    scale follows standard percentage computation (Quizzes 30%, Midterm 30%, Final 30%,
                                    Participation 10%).
                                </p>

                            </div>
                            <a href="policies.php" class="guide-card-link">
                                Read Complete Policies &rarr;

                            </a>
                            <button type="button" class="guide-toggle" aria-expanded="false">
                                Read more
                            </button>
                        </article>

                        <!-- Item 4 -->
                        <article class="guide-card">
                            <div class="ann-badge-row">
                                <span class="badge-tag badge-event">Support</span>

                            </div>
                            <h3 class="ann-title">
                                How can I contact technical support or admissions officers?

                            </h3>
                            <div class="guide-answer">
                                <p class="guide-answer-copy">
                                    Open the local assistant for guidance or send your concern to the Admin team.
                                </p>

                            </div>
                            <button type="button" class="guide-card-link" data-open-apex-assistant>
                                Open local assistant &rarr;

                            </button>
                            <button type="button" class="guide-toggle" aria-expanded="false">
                                Read more
                            </button>
                        </article>
                    </div>
                </div>
                <button class="media-carousel-arrow" type="button" data-direction="next"
                    aria-label="Next student guide card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="m9 18 6-6-6-6"></path>
                    </svg>
                </button>
            </div>
        </div>
    </section>

</main>

<?php include '_partials/foot.php'; ?>
