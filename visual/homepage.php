<?php
$pageTitle = 'APEX Learning Hub — Modern Digital Campus';
$activePage = 'home';
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
                <span data-lang-en>Learn<br>Advance<br>Achieve</span>
                <span data-lang-fil>Matuto<br>Umabante<br>Makatapos</span>
            </h1>

            <p class="hero-desc">
                <span data-lang-en>Welcome to APEX, institution that supports students, faculty, and
                    administrators with simple, effective digital learning site.</span>
                <span data-lang-fil>Maganda araw mula sa APEX, institusyon na naglalayong suportahan ang
                    mga mag-aaral, pasilidad, at administratura kasama ng simpleng website sa digital na
                    pag-aaral.</span>
            </p>

            <div class="hero-cta-group">
                <!-- Button 1: Explore Offerings -->
                <a href="#offerings" class="btn-hero-primary" id="btnExploreOfferings">
                    <span data-lang-en>Explore</span>
                    <span data-lang-fil>Tuklasin</span>
                </a>


                <!-- Button 3: Enter Portal -->
                <a href="../auth/login.php" class="btn-hero-secondary">
                    <span data-lang-en>Login Portal</span>
                    <span data-lang-fil>Login Portal</span>
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
                <h2 class="section-title centered" data-lang-en>What We Offer</h2>
                <h2 class="section-title centered" data-lang-fil style="display:none;">[FILIPINO: Ano ang Aming Inaalok]
                </h2>
                <p class="section-subtitle" data-lang-en>
                    Click or hover over any container below to explore key tools and learning infrastructure designed
                    for the modern scholar.
                </p>
                <p class="section-subtitle" data-lang-fil style="display:none;">
                    [FILIPINO: Mag-hover o mag-click sa alinmang container sa ibaba upang galugarin ang mga kagamitan sa
                    pag-aaral.]
                </p>
            </div>

            <div class="media-carousel" role="region" aria-label="What We Offer">
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
                                    <span data-lang-en>Comprehensive Digital Library</span>
                                    <span data-lang-fil>[FILIPINO: Komprehensibong Digital Library]</span>
                                </h3>
                                <p class="media-card-desc">
                                    <span data-lang-en>Instant access to official syllabi, lecture presentations,
                                        uploaded lab
                                        exercises, and reference literature anytime on your desktop or mobile
                                        device.</span>
                                    <span data-lang-fil>[FILIPINO: Agarang pag-access sa mga opisyal na silabus,
                                        presentasyon ng
                                        lektura, at mga pagsasanay sa laboratoryo.]</span>
                                </p>
                                <span class="media-card-cta">
                                    <span data-lang-en>Explore Overview</span>
                                    <span data-lang-fil>[FILIPINO: Tingnan ang Pangkalahatan]</span>
                                    <svg class="cta-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                </span>
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
                                    <span data-lang-en>Interactive Coursework Tracker</span>
                                    <span data-lang-fil>[FILIPINO: Tagasubaybay ng Kurso at Gawain]</span>
                                </h3>
                                <p class="media-card-desc">
                                    <span data-lang-en>Real-time progress bars, assignment dropboxes, syllabus
                                        milestones, and
                                        structured feedback from your subject instructors in one dashboard.</span>
                                    <span data-lang-fil>[FILIPINO: Real-time na progress bar, lagayan ng takdang-aralin,
                                        at
                                        komento mula sa mga propesor sa isang dashboard.]</span>
                                </p>
                                <span class="media-card-cta">
                                    <span data-lang-en>View Coursework Flow</span>
                                    <span data-lang-fil>[FILIPINO: Daloy ng mga Gawain]</span>
                                    <svg class="cta-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                </span>
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
                                    <span data-lang-en>Institutional Bulletins &amp; Alerts</span>
                                    <span data-lang-fil>[FILIPINO: Mga Anunsyo at Abiso ng Paaralan]</span>
                                </h3>
                                <p class="media-card-desc">
                                    <span data-lang-en>Never miss a midterm advisory, holiday schedule, maintenance
                                        window, or
                                        departmental deadline with synchronized notices across all devices.</span>
                                    <span data-lang-fil>[FILIPINO: Huwag palampasin ang mga abiso sa midterm, iskedyul
                                        ng pista
                                        opisyal, o mga deadline ng departamento.]</span>
                                </p>
                                <span class="media-card-cta">
                                    <span data-lang-en>Read Latest Bulletins</span>
                                    <span data-lang-fil>[FILIPINO: Basahin ang mga Anunsyo]</span>
                                    <svg class="cta-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                </span>
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
                                    <span data-lang-en>Faculty Mentorship &amp; Evaluation</span>
                                    <span data-lang-fil>[FILIPINO: Patnubay at Pagsusuri ng Guro]</span>
                                </h3>
                                <p class="media-card-desc">
                                    <span data-lang-en>Direct communication channels with faculty leaders, fast
                                        enrollment
                                        approval queue, attendance recording, and transparent academic grading.</span>
                                    <span data-lang-fil>[FILIPINO: Direktang pakikipag-ugnayan sa mga propesor, mabilis
                                        na
                                        pag-apruba sa enrollment, at patas na pagmamarka.]</span>
                                </p>
                                <span class="media-card-cta">
                                    <span data-lang-en>Learn About Mentorship</span>
                                    <span data-lang-fil>[FILIPINO: Alamin ang Patnubay]</span>
                                    <svg class="cta-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                </span>
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
                <h2 class="section-title centered" data-lang-en>Latest Announcements</h2>
                <h2 class="section-title centered" data-lang-fil style="display:none;">[FILIPINO: Pinakabagong mga
                    Anunsyo]</h2>
            </div>

            <div class="announcement-cards-grid">
                <!-- Ann 1 -->
                <article class="ann-card" onclick="window.location.href='announcement.php'">
                    <div>
                        <div class="ann-badge-row">
                            <span class="badge-tag badge-academic" data-lang-en>Academic Advisory</span>
                            <span class="badge-tag badge-academic" data-lang-fil style="display:none;">[FILIPINO:
                                Advisory sa Akademiko]</span>
                            <span class="ann-date">October 24, 2026</span>
                        </div>
                        <h3 class="ann-title">
                            <span data-lang-en>Midterm Examination Schedule Released</span>
                            <span data-lang-fil>[FILIPINO: Inilabas ang Iskedyul ng Midterm Exam]</span>
                        </h3>
                        <p class="ann-snippet">
                            <span data-lang-en>Room allocations, proctor schedules, and subject time slots are
                                finalized. Check your registered student portal for individual schedules.</span>
                            <span data-lang-fil>[FILIPINO: Nakatalaga na ang mga silid at oras ng pagsusulit. Tingnan
                                ang iyong portal para sa iskedyul.]</span>
                        </p>
                    </div>
                </article>

                <!-- Ann 2 -->
                <article class="ann-card" onclick="window.location.href='announcement.php'">
                    <div>
                        <div class="ann-badge-row">
                            <span class="badge-tag badge-system" data-lang-en>System Maintenance</span>
                            <span class="badge-tag badge-system" data-lang-fil style="display:none;">[FILIPINO:
                                Maintenance ng Sistema]</span>
                            <span class="ann-date">October 20, 2026</span>
                        </div>
                        <h3 class="ann-title">
                            <span data-lang-en>Scheduled LMS Infrastructure Upgrade</span>
                            <span data-lang-fil>[FILIPINO: Nakatakdang Upgrade sa LMS Server]</span>
                        </h3>
                        <p class="ann-snippet">
                            <span data-lang-en>Routine database optimization and security maintenance will be conducted
                                this Saturday from 2:00 AM to 4:00 AM UTC. Plan uploads accordingly.</span>
                            <span data-lang-fil>[FILIPINO: Magsasagawa ng regular na pag-upgrade sa database ngayong
                                Sabado mula 2:00 AM hanggang 4:00 AM UTC.]</span>
                        </p>
                    </div>
                </article>

                <!-- Ann 3 -->
                <article class="ann-card" onclick="window.location.href='announcement.php'">
                    <div>
                        <div class="ann-badge-row">
                            <span class="badge-tag badge-event" data-lang-en>Admissions Notice</span>
                            <span class="badge-tag badge-event" data-lang-fil style="display:none;">[FILIPINO: Abiso sa
                                Pagpapatala]</span>
                            <span class="ann-date">October 18, 2026</span>
                        </div>
                        <h3 class="ann-title">
                            <span data-lang-en>Second Semester Course Enrollment Deadline</span>
                            <span data-lang-fil>[FILIPINO: Huling Araw ng Pagpapatala para sa Ika-2 Semestre]</span>
                        </h3>
                        <p class="ann-snippet">
                            <span data-lang-en>Course application queues close on November 8, 2026. Ensure that all
                                course requests are submitted to instructors before the cut-off.</span>
                            <span data-lang-fil>[FILIPINO: Magtatapos ang pagpapatala sa Nobyembre 8, 2026. Siguraduhing
                                naisumite ang mga kurso bago ang deadline.]</span>
                        </p>
                    </div>
                </article>
            </div>

            <div class="section-action-center">
                <a href="announcement.php" class="btn-all-bulletins">
                    <span data-lang-en>View All Past Bulletins &amp; Archives &rarr;</span>
                    <span data-lang-fil>[FILIPINO: Tingnan ang Lahat ng Nakaraang Bulletin &rarr;]</span>
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
                <h2 class="section-title centered" data-lang-en>Campus Essentials &amp;<br> Student Guide</h2>
                <h2 class="section-title centered" data-lang-fil style="display:none;">[FILIPINO: Mahahalagang
                    Impormasyon sa Kampus]</h2>
                <p class="section-subtitle" data-lang-en>
                    Click any topic below to expand answers and review important academic guidelines.
                </p>
                <p class="section-subtitle" data-lang-fil style="display:none;">
                    [FILIPINO: I-click ang alinmang paksa sa ibaba upang buksan ang sagot at alituntunin.]
                </p>
            </div>

            <div class="accordion-wrapper">
                <!-- Item 1 -->
                <div class="accordion-item open">
                    <button type="button" class="accordion-header" aria-expanded="true">
                        <span>
                            <span data-lang-en>How do students enroll in courses for the new semester?</span>
                            <span data-lang-fil>[FILIPINO: Paano magpatala ang mga mag-aaral para sa bagong
                                semestre?]</span>
                        </span>
                        <svg class="accordion-chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="accordion-body">
                        <p data-lang-en>
                            Registered students can log in to their student workspace, navigate to the Course Catalog,
                            and submit enrollment applications with a single click. Your professor will review and
                            approve pending requests directly.
                        </p>
                        <p data-lang-fil style="display:none;">
                            [FILIPINO: Ang mga nakarehistrong mag-aaral ay maaaring mag-log in sa kanilang student
                            workspace, pumunta sa Course Catalog, at magsumite ng aplikasyon sa pagpapatala.]
                        </p>
                    </div>
                </div>

                <!-- Item 2 -->
                <div class="accordion-item">
                    <button type="button" class="accordion-header" aria-expanded="false">
                        <span>
                            <span data-lang-en>Where can I check exam schedules and faculty announcements?</span>
                            <span data-lang-fil>[FILIPINO: Saan ko makikita ang mga iskedyul ng pagsusulit at
                                anunsyo?]</span>
                        </span>
                        <svg class="accordion-chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="accordion-body">
                        <p data-lang-en>
                            All official updates, midterm room assignments, and maintenance alerts are posted on our
                            public Announcements page as well as inside the authenticated student and faculty feeds.
                        </p>
                        <p data-lang-fil style="display:none;">
                            [FILIPINO: Lahat ng opisyal na anunsyo, silid sa eksamen, at maintenance advisory ay
                            makikita sa pahina ng Mga Anunsyo.]
                        </p>
                        <a href="announcement.php" class="btn-all-bulletins"
                            style="margin-top:10px; display:inline-flex;">
                            <span data-lang-en>Go to Announcements Page &rarr;</span>
                            <span data-lang-fil>[FILIPINO: Pumunta sa Pahina ng mga Anunsyo &rarr;]</span>
                        </a>
                    </div>
                </div>

                <!-- Item 3 -->
                <div class="accordion-item">
                    <button type="button" class="accordion-header" aria-expanded="false">
                        <span>
                            <span data-lang-en>What are the institutional policies on attendance and grading?</span>
                            <span data-lang-fil>[FILIPINO: Ano ang mga patakaran sa pagdalo at sistema ng
                                pagmamarka?]</span>
                        </span>
                        <svg class="accordion-chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="accordion-body">
                        <p data-lang-en>
                            A minimum of 80% attendance is strictly enforced across all academic courses. The grading
                            scale follows standard percentage computation (Quizzes 30%, Midterm 30%, Final 30%,
                            Participation 10%).
                        </p>
                        <p data-lang-fil style="display:none;">
                            [FILIPINO: Kinakailangan ang minimum na 80% na pagdalo sa klase. Ang pagmamarka ay batay sa
                            pamantayang porsyento.]
                        </p>
                        <a href="policies.php" class="btn-all-bulletins" style="margin-top:10px; display:inline-flex;">
                            <span data-lang-en>Read Complete Policies &rarr;</span>
                            <span data-lang-fil>[FILIPINO: Basahin ang Buong Patakaran &rarr;]</span>
                        </a>
                    </div>
                </div>

                <!-- Item 4 -->
                <div class="accordion-item">
                    <button type="button" class="accordion-header" aria-expanded="false">
                        <span>
                            <span data-lang-en>How can I contact technical support or admissions officers?</span>
                            <span data-lang-fil>[FILIPINO: Paano makipag-ugnayan sa suportang teknikal o opisina ng
                                admission?]</span>
                        </span>
                        <svg class="accordion-chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="accordion-body">
                        <p data-lang-en>
                            Our support staff and department registrars are available from Monday to Friday, 8:00 AM to
                            5:00 PM. You can submit an inquiry through our Contact page or message support directly.
                        </p>
                        <p data-lang-fil style="display:none;">
                            [FILIPINO: Bukas ang aming opisina Lunes hanggang Biyernes, 8:00 AM hanggang 5:00 PM. Maaari
                            kayong magpadala ng mensahe sa pahina ng Contact.]
                        </p>
                        <a href="contact.php" class="btn-all-bulletins" style="margin-top:10px; display:inline-flex;">
                            <span data-lang-en>Open Contact Page &rarr;</span>
                            <span data-lang-fil>[FILIPINO: Buksan ang Pahina ng Pakikipag-ugnayan &rarr;]</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================================
         5. ABOUT OUR SCHOOL
         ============================================================= -->
    <section class="about-school-section" id="about">
        <div class="container">
            <div class="about-grid">
                <div class="about-text-col">
                    <h2 data-lang-en>About APEX</h2>
                    <h2 data-lang-fil style="display:none;">Kilalanin ang APEX</h2>

                    <p data-lang-en>
                        APEX is an integrated digital campus and learning management infrastructure built
                        to modernize education, promote transparent communication, and cultivate scholarly achievement.
                    </p>
                    <p data-lang-fil style="display:none;">
                        Ang APEX ay isang pinagsamang digital campus at learning management
                        infrastructure na dinisenyo upang gawing moderno ang edukasyon.]
                    </p>

                    <p data-lang-en>
                        Our mission is to empower both learners and educators with high-performance digital tools,
                        accessible learning modules, and a unified environment supporting academic integrity and
                        continuous professional growth.
                    </p>
                    <p data-lang-fil style="display:none;">
                        [FILIPINO: Ang aming misyon ay bigyang-lakas ang mga mag-aaral at guro gamit ang de-kalidad na
                        mga kagamitang digital at mga modyul sa pag-aaral.]
                    </p>

                </div>

            </div>
        </div>
    </section>

</main>

<?php include '_partials/foot.php'; ?>