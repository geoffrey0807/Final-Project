<?php
require_once '../config/db.php';
$homepageBulletinsStmt = $pdo->query("
    SELECT a.headline, a.message, a.homepage_category, a.created_at
    FROM announcements a
    JOIN users publisher ON publisher.id = a.author_id AND publisher.role = 'registrar'
    WHERE a.course_id IS NULL
      AND a.is_homepage_notice = 1
      AND a.homepage_category IS NOT NULL
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT 2
");
$homepageBulletins = $homepageBulletinsStmt->fetchAll(PDO::FETCH_ASSOC);
$homepageCategoryLabels = [
    'academic' => 'Academic',
    'system' => 'System Update',
    'event' => 'Campus Event',
    'policy' => 'Policy Notice',
];
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
                Learn<br>Advance<br>Achieve

            </h1>

            <p class="hero-desc">
                Welcome to APEX, institution that supports students, faculty, and
                    administrators with simple, effective digital learning site.

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
         2. WHAT WE OFFER (Picture Containers with Hover-Up & Border Highlight)
         ============================================================= -->
    <section class="section" id="offerings">
        <div class="container">
            <div class="section-header-block">
                <h2 class="section-title centered">What APEX Offers</h2>

            </div>

            <div class="media-carousel" role="region" aria-label="What APEX Learning Hub Offers">
                <button class="media-carousel-arrow" type="button" data-direction="previous"
                    aria-label="Previous offering">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="m15 18-6-6 6-6"></path>
                    </svg>
                </button>
                <div class="media-carousel-viewport">
                    <div class="media-grid">
                        <!-- Card 1 -->
                        <article class="media-card" tabindex="0">
                            <div class="media-pic-wrap">
                                <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=700&q=80"
                                    alt="Students studying with digital resources" loading="lazy">
                                <div class="media-pic-overlay"></div>
                                <span class="media-tag-badge">Curriculum</span>
                            </div>
                            <div class="media-card-body">
                                <h3 class="media-card-title">
                                    Comprehensive Digital Library

                                </h3>
                                <p class="media-card-desc">
                                    Browse course offerings, review course details, and find syllabi,
                                        lecture presentations, lab exercises, and reference materials shared by faculty.
                                        Everything is organized for quick reference on desktop or mobile.

                                </p>
                            </div>
                        </article>

                        <!-- Card 2 -->
                        <article class="media-card" tabindex="0">
                            <div class="media-pic-wrap">
                                <img src="https://images.unsplash.com/photo-1531403009284-440f080d1e12?auto=format&fit=crop&w=700&q=80"
                                    alt="Interactive modern software interface" loading="lazy">
                                <div class="media-pic-overlay"></div>
                                <span class="media-tag-badge">Coursework</span>
                            </div>
                            <div class="media-card-body">
                                <h3 class="media-card-title">
                                    Interactive Coursework Tracker

                                </h3>
                                <p class="media-card-desc">
                                    Keep approved courses, pending applications, and the course
                                        catalog in one place. Check assigned instructors, application updates, and
                                        course capacity as you plan your semester.

                                </p>
                            </div>
                        </article>

                        <!-- Card 3 -->
                        <article class="media-card" tabindex="0">
                            <div class="media-pic-wrap">
                                <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=700&q=80"
                                    alt="Examinations and university notices" loading="lazy">
                                <div class="media-pic-overlay"></div>
                                <span class="media-tag-badge">Bulletins</span>
                            </div>
                            <div class="media-card-body">
                                <h3 class="media-card-title">
                                    Institutional Bulletins &amp; Alerts

                                </h3>
                                <p class="media-card-desc">
                                    Find campus-wide notices and course-specific updates, including
                                        schedules, deadlines, and maintenance advisories. Each post includes its
                                        publishing details and relevant course context.

                                </p>
                            </div>
                        </article>

                        <!-- Card 4 -->
                        <article class="media-card" tabindex="0">
                            <div class="media-pic-wrap">
                                <img src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=700&q=80"
                                    alt="Professor mentorship and classroom discussion" loading="lazy">
                                <div class="media-pic-overlay"></div>
                                <span class="media-tag-badge">Faculty</span>
                            </div>
                            <div class="media-card-body">
                                <h3 class="media-card-title">
                                    Faculty Mentorship &amp; Evaluation

                                </h3>
                                <p class="media-card-desc">
                                    See which faculty members are assigned to each course and follow
                                        enrollment requests through review. Course-level announcements help keep
                                        important learning updates connected to the right class.

                                </p>
                            </div>
                        </article>
                    </div>
                </div>
                <button class="media-carousel-arrow" type="button" data-direction="next" aria-label="Next offering">
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
                            $category = $bulletin['homepage_category'];
                            $categoryLabel = $homepageCategoryLabels[$category] ?? 'Bulletin';
                            ?>
                            <a class="ann-card" href="announcement.php">
                                <div>
                                    <div class="ann-badge-row">
                                        <span class="badge-tag badge-<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <time class="ann-date"
                                            datetime="<?= htmlspecialchars(date('Y-m-d', strtotime($bulletin['created_at'])), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(date('F j, Y', strtotime($bulletin['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                        </time>
                                    </div>
                                    <h3 class="ann-title"><?= htmlspecialchars($bulletin['headline'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p class="ann-snippet"><?= htmlspecialchars($bulletin['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php for ($slot = count($homepageBulletins); $slot < 2; $slot++): ?>
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

            <div class="section-action-center">
                <a href="announcement.php" class="btn-all-bulletins">
                    View All Past Bulletins &amp; Archives &rarr;

                </a>
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
