<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/dashboard_greeting.php';
require_once '../../includes/announcement_access.php';

$extraCss = ['../../css/admin.css?v=202611011335', '../../css/student.css?v=202611020900'];
$extraJs = ['../../js/admin.js?v=202611010115', '../../js/student.js?v=202610301825'];
$bodyClass = 'admin-dashboard student-dashboard professor-portal';
$useVisualTheme = true;

$profId = (int) $_SESSION['user_id'];
$professorView = $_GET['view'] ?? 'dashboard';
$allowedProfessorViews = ['dashboard', 'classes', 'announcements', 'assessments', 'lessons'];
if (!in_array($professorView, $allowedProfessorViews, true)) {
    $professorView = 'dashboard';
}
$professorViewTitles = [
    'dashboard' => 'Dashboard',
    'classes' => 'Assigned Classes',
    'announcements' => 'Class Announcements',
    'assessments' => 'Assessments',
    'lessons' => 'Lessons',
];
$pageTitle = $professorViewTitles[$professorView] . ' | APEX Learning Hub';
$bodyClass .= ' professor-view-' . $professorView;

$courseStmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'APPROVED') AS approved_students
    FROM courses c
    WHERE c.professor_id = :pid" . academicPeriodDateFilter('c.created_at') . "
    ORDER BY c.course_code ASC
");
$courseStmt->execute(array_merge(['pid' => $profId], academicPeriodDateParams()));
$myCourses = $courseStmt->fetchAll();

$annStmt = $pdo->prepare("
    SELECT a.*, a.headline AS title, c.course_code, c.course_name
    FROM announcements a
    JOIN courses c ON a.course_id = c.id
    WHERE a.author_id = :pid" . academicPeriodDateFilter('a.created_at') . "
    ORDER BY a.created_at DESC
");
$annStmt->execute(array_merge(['pid' => $profId], academicPeriodDateParams()));
$myAnnouncements = $annStmt->fetchAll();

$assignedCourseIds = array_values(array_filter(array_map(
    static fn(array $course): int => (int) $course['id'],
    $myCourses
)));
$assignedCourseClause = !empty($assignedCourseIds)
    ? ' OR a.course_id IN (' . implode(',', $assignedCourseIds) . ')'
    : '';
$announcementAudienceFilter = announcementAudienceVisibilitySql('a', 'professor');
$latestAnnouncementStmt = $pdo->prepare("
    SELECT a.headline AS title, a.message, a.created_at, c.course_code,
           u.first_name, u.last_name, u.role
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE u.role IN ('registrar', 'dean')
      AND (a.course_id IS NULL$assignedCourseClause)
      AND {$announcementAudienceFilter}
      " . academicPeriodDateFilter('a.created_at') . "
    ORDER BY a.created_at DESC
        LIMIT 6
");
$latestAnnouncementStmt->execute(academicPeriodDateParams());
$latestAnnouncements = $latestAnnouncementStmt->fetchAll();
$dashboardAnnouncements = $latestAnnouncements;
$dashboardAnnouncementsHref = '../announcements.php';
$showDashboardAnnouncements = $professorView === 'dashboard';
$assignmentsStmt = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_name
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    WHERE a.professor_id = :professor_id" . academicPeriodDateFilter('a.due_at') . "
    ORDER BY a.due_at ASC
");
$assignmentsStmt->execute(array_merge(['professor_id' => $profId], academicPeriodDateParams()));
$myAssignments = $assignmentsStmt->fetchAll();

$lessonsStmt = $pdo->prepare("
    SELECT l.*, c.course_code, c.course_name
    FROM lessons l
    JOIN courses c ON c.id = l.course_id
    WHERE l.professor_id = :professor_id" . academicPeriodDateFilter('l.created_at') . "
    ORDER BY l.created_at DESC
");
$lessonsStmt->execute(array_merge(['professor_id' => $profId], academicPeriodDateParams()));
$myLessons = $lessonsStmt->fetchAll();

$totalAssignedCourses = count($myCourses);
$totalAssignedSections = count(array_unique(array_map(
    static fn(array $course): string => trim((string) ($course['section_name'] ?? 'General')),
    $myCourses
)));
$assignedCourseIdsForStudents = array_values(array_filter(array_map(
    static fn(array $course): int => (int) $course['id'],
    $myCourses
)));
$totalAssignedStudents = 0;
if (!empty($assignedCourseIdsForStudents)) {
    $studentCountStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.student_id)
        FROM enrollments e
        WHERE e.status = 'APPROVED'
          AND e.course_id IN (" . implode(',', $assignedCourseIdsForStudents) . ")
          " . academicPeriodDateFilter('COALESCE(e.decided_at, e.applied_at)') . "
    ");
    $studentCountStmt->execute(academicPeriodDateParams());
    $totalAssignedStudents = (int) $studentCountStmt->fetchColumn();
}
$manilaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$greeting = dashboardGreeting($manilaNow);

require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell<?= $professorView === 'dashboard' ? ' has-dashboard-announcements' : '' ?>"
    id="studentDashboardShell">
    <?php require '../../includes/professor_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <?php if ($professorView === 'dashboard'): ?>
            <div class="student-dashboard-heading">
                <div class="student-dashboard-heading-copy">
                    <h1><?= htmlspecialchars($professorViewTitles[$professorView]) ?></h1>
                </div>
            </div>
        <?php else: ?>
            <header class="student-page-header">
                <h1><?= htmlspecialchars($professorViewTitles[$professorView]) ?></h1>
            </header>
        <?php endif; ?>

        <div class="professor-dashboard-layout">
            <div class="professor-dashboard-main-column">
                <section class="student-welcome-card professor-welcome-card">
                    <div class="student-welcome-copy">
                        <h1><?= $greeting ?>, Prof. <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
                        <?php require '../../includes/role_identifier_summary.php'; ?>
                        <?php require '../../includes/academic_period_summary.php'; ?>
                    </div>
                </section>

                <div class="metrics-grid professor-summary-grid">
                    <div class="metric-card">
                        <div class="metric-icon navy" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5z" />
                                <path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= $totalAssignedCourses ?></h3>
                            <p>Assigned Courses</p>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon gold" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="17" rx="2" />
                                <path d="M16 2v4M8 2v4M3 9h18M8 13h3M13 13h3M8 17h3" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= $totalAssignedSections ?></h3>
                            <p>Sections</p>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon teal" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <circle cx="9" cy="8" r="3" />
                                <path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5.5a3 3 0 0 0 0 5.8M18 14a5 5 0 0 1 3 4.5V20" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= $totalAssignedStudents ?></h3>
                            <p>Students</p>
                        </div>
                    </div>
                </div>

            </div>
            <aside class="professor-dashboard-side-column">
                <?php if ($professorView === 'dashboard' && !empty($myCourses)): ?>
                    <a class="course-roster-card professor-course-link professor-dashboard-course-card"
                        href="professor_classes.php">
                        <h3>View your assigned classes</h3>
                        <div class="text-muted">Open a class to manage its lessons, assessments, and announcements.</div>
                        <div class="professor-course-card-footer">
                            <span><?= $totalAssignedCourses ?>     <?= $totalAssignedCourses === 1 ? 'class' : 'classes' ?>
                                assigned</span>
                            <span>View classes &rarr;</span>
                        </div>
                    </a>
                <?php endif; ?>
                <section class="professor-actions-panel">
                    <div class="student-overview-card admin-dashboard-action-card professor-quick-action">
                        <button type="button" class="btn btn-coral professor-action-btn professor-quick-action-button"
                            data-open-modal="courseAnnModal">
                            <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            <span>New Announcement</span>
                        </button>
                        <div class="professor-quick-action-copy">
                            <strong>Announcements</strong>
                            <small>Post an announcement to an assigned class</small>
                        </div>
                        <a href="professor_announcements.php">View all announcements</a>
                    </div>
                    <div class="student-overview-card admin-dashboard-action-card professor-quick-action">
                        <button type="button" class="btn btn-primary professor-action-btn professor-quick-action-button"
                            data-open-modal="courseAssignmentModal">
                            <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            <span>New Activity</span>
                        </button>
                        <div class="professor-quick-action-copy">
                            <strong>Activities, Quizzes &amp; Tests</strong>
                            <small>Create an activity, quiz, or test</small>
                        </div>
                        <a href="professor_assessments.php">View all activities, quizzes &amp; tests</a>
                    </div>
                </section>

                <section class="student-overview-card student-announcements-preview professor-latest-announcements">
                    <div class="student-overview-card-heading">
                        <h2>Latest announcement</h2>
                    </div>
                    <div class="student-announcement-list">
                        <?php foreach ($latestAnnouncements as $announcement): ?>
                            <div class="student-announcement-preview">
                                <span>
                                    <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                                    <small>
                                        <?= htmlspecialchars(announcementAuthorLabel($announcement['role']), ENT_QUOTES, 'UTF-8') ?>
                                        &middot; <?= htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($latestAnnouncements)): ?>
                            <p class="student-empty-note student-empty-inline">No registrar or dean announcements yet.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </div>


        <div class="tab-navigation professor-workspace-tabs">
            <button type="button" class="tab-btn active" data-target="tabCourses">
                Assigned Classes <span class="tab-badge"><?= $totalAssignedCourses ?></span>
            </button>
            <button type="button" class="tab-btn" data-target="tabAnnouncements">
                Class Announcements <span class="tab-badge"><?= count($myAnnouncements) ?></span>
            </button>
            <button type="button" class="tab-btn" data-target="tabAssignments">
                Assessments <span class="tab-badge"><?= count($myAssignments) ?></span>
            </button>
        </div>

        <div class="tab-panel active" id="tabCourses">
            <div class="roster-grid">
                <?php if (empty($myCourses)): ?>
                    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 2rem;">
                        No courses have been assigned to your faculty profile yet. Contact the Administrator.
                    </div>
                <?php else: ?>
                    <?php foreach ($myCourses as $c): ?>
                        <?php
                        $pct = $c['max_capacity'] > 0 ? min(100, round(($c['approved_students'] / $c['max_capacity']) * 100)) : 0;
                        ?>
                        <a class="course-roster-card professor-course-link"
                            href="professor_course.php?id=<?= (int) $c['id'] ?>">
                            <div>
                                <div
                                    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                                    <span class="context-pill context-pill-admin"
                                        style="font-size:0.8rem;"><?= htmlspecialchars($c['course_code']) ?></span>
                                </div>
                                <h3 style="font-size:1.15rem; color:var(--navy); margin-bottom:0.5rem;">
                                    <?= htmlspecialchars($c['course_name']) ?>
                                </h3>
                                <div class="text-muted" style="font-size:0.8rem;">Section
                                    <?= htmlspecialchars($c['section_name'] ?? 'General') ?></div>
                                <div class="professor-class-schedule">
                                    <?= htmlspecialchars($c['meeting_days'] ?: 'Schedule not set') ?>
                                    <?php if ($c['start_time'] && $c['end_time']): ?>
                                        &middot; <?= htmlspecialchars(date('g:i A', strtotime($c['start_time']))) ?>
                                        &ndash; <?= htmlspecialchars(date('g:i A', strtotime($c['end_time']))) ?>
                                    <?php endif; ?>
                                    <?php if ($c['room']): ?>
                                        &middot; <?= htmlspecialchars($c['room']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="capacity-meter">
                                    <div class="capacity-fill <?= $pct >= 85 ? 'high' : '' ?>" style="width: <?= $pct ?>%;">
                                    </div>
                                </div>
                                <div
                                    style="display:flex; justify-content:space-between; font-size:0.85rem; color:var(--text-muted);">
                                    <span><?= (int) $c['approved_students'] ?> Enrolled</span>
                                    <span>Max <?= (int) $c['max_capacity'] ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>


        <div class="tab-panel" id="tabAnnouncements">
            <div class="card">
                <div class="table-toolbar">
                    <div class="card-title" style="margin:0;">Class Announcements</div>
                    <button class="btn btn-coral btn-sm" data-open-modal="courseAnnModal">
                        <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        <span>New Announcement</span>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Subject &amp; Message</th>
                                <th>Target Course</th>
                                <th>Date Published</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myAnnouncements)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding:2rem;">You haven't posted any
                                        announcements yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($myAnnouncements as $ann): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($ann['title']) ?></strong>
                                            <p style="font-size:0.85rem; color:var(--text-muted); margin-top:3px;">
                                                <?= nl2br(htmlspecialchars($ann['message'])) ?>
                                            </p>
                                        </td>
                                        <td>
                                            <span
                                                class="context-pill context-pill-professor"><?= htmlspecialchars($ann['course_code']) ?></span>
                                        </td>
                                        <td style="font-size:0.8rem;"><?= date('M d, Y h:i A', strtotime($ann['created_at'])) ?>
                                        </td>
                                        <td>
                                            <form action="../../api/professor_process.php" method="POST"
                                                onsubmit="return confirm('Delete this announcement?');">
                                                <input type="hidden" name="action" value="delete_announcement">
                                                <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                                                <button type="submit" class="btn btn-outline btn-sm"
                                                    style="color:var(--coral-dark);">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-panel" id="tabAssignments">
            <div class="card">
                <div class="table-toolbar">
                    <div class="card-title" style="margin:0;">Activities, Quizzes &amp; Tests</div>
                    <button class="btn btn-primary btn-sm" data-open-modal="courseAssignmentModal">
                        <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        <span>New Activity</span>
                    </button>
                </div>
                <?php
                $assessmentGroups = [
                    'Assessment' => 'Activities',
                    'Quiz' => 'Quizzes',
                    'Exam' => 'Tests',
                ];
                ?>
                <div class="professor-assessment-sections">
                    <?php foreach ($assessmentGroups as $type => $label): ?>
                        <?php $groupedAssignments = array_values(array_filter(
                            $myAssignments,
                            static fn(array $assignment): bool => ($assignment['assessment_type'] ?? 'Assessment') === $type
                        )); ?>
                        <section class="professor-assessment-section">
                            <div class="professor-assessment-section-heading">
                                <h3><?= $label ?></h3>
                                <span class="tab-badge"><?= count($groupedAssignments) ?></span>
                            </div>
                            <?php if (empty($groupedAssignments)): ?>
                                <p class="professor-assessment-empty">No <?= strtolower($label) ?> created yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th><?= $label === 'Activities' ? 'Activity' : rtrim($label, 's') ?></th>
                                                <th>Course</th>
                                                <th>Due</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($groupedAssignments as $assignment): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($assignment['title']) ?></strong>
                                                        <?php if ($assignment['description'] !== ''): ?>
                                                            <div class="text-muted">
                                                                <?= nl2br(htmlspecialchars($assignment['description'])) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($assignment['course_code']) ?> &middot;
                                                        <?= htmlspecialchars($assignment['course_name']) ?></td>
                                                    <td><?= date('M j, Y', strtotime($assignment['due_at'])) ?> &middot;
                                                        <?= date('g:i A', strtotime($assignment['due_at'])) ?></td>
                                                    <td>
                                                        <form action="../../api/professor_process.php" method="POST"
                                                            onsubmit="return confirm('Delete this item for all enrolled students?');">
                                                            <input type="hidden" name="action" value="delete_assignment">
                                                            <input type="hidden" name="assignment_id"
                                                                value="<?= (int) $assignment['id'] ?>">
                                                            <button type="submit" class="btn btn-outline btn-sm">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($professorView === 'lessons'): ?>
            <div class="tab-panel active" id="tabLessons">
                <div class="card">
                    <div class="table-toolbar">
                        <div class="card-title" style="margin:0;">Course Lessons</div>
                        <button class="btn btn-primary btn-sm" data-open-modal="courseLessonModal">
                            <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            <span>New Lesson</span>
                        </button>
                    </div>
                    <div class="professor-assessment-sections">
                        <?php if (empty($myLessons)): ?>
                            <p class="professor-assessment-empty">No lessons created yet.</p>
                        <?php else: ?>
                            <?php foreach ($myLessons as $lesson): ?>
                                <article class="professor-assessment-section">
                                    <div class="professor-assessment-section-heading">
                                        <h3><?= htmlspecialchars($lesson['title']) ?></h3>
                                        <span
                                            class="context-pill context-pill-professor"><?= htmlspecialchars($lesson['course_code']) ?></span>
                                    </div>
                                    <p><?= nl2br(htmlspecialchars($lesson['content'])) ?></p>
                                    <?php if (!empty($lesson['file_name'])): ?>
                                        <a class="btn btn-outline btn-sm"
                                            href="../../api/download_lesson.php?id=<?= (int) $lesson['id'] ?>">
                                            Download <?= htmlspecialchars($lesson['file_name']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <div class="table-toolbar" style="margin-top:0.75rem;">
                                        <small class="text-muted"><?= htmlspecialchars($lesson['course_name']) ?> &middot;
                                            <?= htmlspecialchars(date('M d, Y', strtotime($lesson['created_at']))) ?></small>
                                        <form action="../../api/professor_process.php" method="POST"
                                            onsubmit="return confirm('Delete this lesson?');">
                                            <input type="hidden" name="action" value="delete_lesson">
                                            <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm"
                                                style="color:var(--coral-dark);">Delete</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

</div>
</div>
<button type="button" class="student-sidebar-backdrop" aria-label="Close professor navigation"></button>
<button type="button" class="student-mobile-toggle" aria-label="Open professor navigation"
    aria-controls="studentSidebar" aria-expanded="false">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>

<div class="modal-backdrop" id="courseAnnModal">
    <div class="modal-dialog">
        <form action="../../api/professor_process.php" method="POST">
            <input type="hidden" name="action" value="post_announcement">
            <div class="modal-header">
                <h3 class="modal-title">Post Announcement to Class</h3>
                <button type="button" class="modal-close" data-close-modal>&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="ann_course_id">Select Course *</label>
                    <select id="ann_course_id" name="course_id" class="form-control" required>
                        <?php foreach ($myCourses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code']) ?>:
                                <?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ann_title">Announcement Title *</label>
                    <input type="text" id="ann_title" name="title" class="form-control"
                        placeholder="e.g. Reading Assignment &amp; Quiz Notice" required>
                </div>
                <div class="form-group">
                    <label for="ann_msg">Announcement Details *</label>
                    <textarea id="ann_msg" name="message" class="form-control" rows="4"
                        placeholder="Enter message for your enrolled students..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-coral">Post to Class</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="courseAssignmentModal">
    <div class="modal-dialog">
        <form action="../../api/professor_process.php" method="POST">
            <input type="hidden" name="action" value="create_assignment">
            <div class="modal-header">
                <h3 class="modal-title">Create Activity, Quiz, or Test</h3>
                <button type="button" class="modal-close" data-close-modal>&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="assignment_course_id">Course *</label>
                    <select id="assignment_course_id" name="course_id" class="form-control" required>
                        <?php foreach ($myCourses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>">
                                <?= htmlspecialchars($course['course_code'] . ': ' . $course['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="assignment_title">Title *</label>
                    <input type="text" id="assignment_title" name="title" class="form-control" maxlength="150" required>
                </div>
                <div class="form-group">
                    <label for="assessment_type">Type *</label>
                    <select id="assessment_type" name="assessment_type" class="form-control" required>
                        <option value="Assessment" selected>Activity</option>
                        <option value="Quiz">Quiz</option>
                        <option value="Exam">Test</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="assignment_description">Instructions</label>
                    <textarea id="assignment_description" name="description" class="form-control" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="assignment_due_at">Due date and time *</label>
                    <input type="datetime-local" id="assignment_due_at" name="due_at" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Publish</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop lesson-modal-backdrop" id="courseLessonModal">
    <div class="modal-dialog lesson-modal-dialog">
        <form action="../../api/professor_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_lesson">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Create Course Lesson</h3>
                </div>
                <button type="button" class="modal-close" data-close-modal>&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="lesson_course_id">Course / Class *</label>
                    <select id="lesson_course_id" name="course_id" class="form-control" required>
                        <?php foreach ($myCourses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>">
                                <?= htmlspecialchars($course['course_code'] . ': ' . $course['course_name'] . ' - Section ' . ($course['section_name'] ?? 'General')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="lesson_title">Lesson Title *</label>
                    <input type="text" id="lesson_title" name="title" class="form-control" maxlength="150" required>
                </div>
                <div class="form-group">
                    <label for="lesson_content">Description *</label>
                    <textarea id="lesson_content" name="content" class="form-control" rows="6" maxlength="10000"
                        required></textarea>
                </div>
                <div class="form-group">
                    <label for="lesson_file">Lesson File *</label>
                    <label class="lesson-upload-box" for="lesson_file">
                        <span class="lesson-upload-main">
                            <span class="lesson-upload-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 16V4M7 9l5-5 5 5M5 20h14" />
                                </svg>
                            </span>
                            <strong class="lesson-upload-title">Upload your file</strong>
                        </span>
                        <small class="lesson-upload-types" id="lessonFileName">PDF, Word, PowerPoint, Excel, text, or
                            ZIP up to 10 MB</small>
                        <input type="file" id="lesson_file" name="lesson_file"
                            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip" required>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary lesson-submit-btn">
                    <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m5 12 4 4L19 6" />
                    </svg>
                    Publish Lesson
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>