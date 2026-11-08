<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/dashboard_greeting.php';
require_once '../../includes/academic_access.php';
require_once '../../includes/announcement_access.php';

$pageTitle = 'Student Portal | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202610291355', '../../css/student.css?v=202611041145'];
$extraJs = '../../js/student.js?v=202610291930';
$bodyClass = 'admin-dashboard student-dashboard';
$useVisualTheme = true;

$studentId = (int) $_SESSION['user_id'];
$announcementAudienceFilter = announcementAudienceVisibilitySql('a', 'student');
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$userStmt->execute(['id' => $studentId]);
$currentUser = $userStmt->fetch();

$approvedStmt = $pdo->prepare("
    SELECT e.id AS enrollment_id, e.decided_at,
           c.id AS course_id, c.course_code, c.course_name, c.max_capacity,
           c.meeting_days, c.start_time, c.end_time, c.room,
           u.first_name AS prof_first, u.last_name AS prof_last, u.email AS prof_email
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE e.student_id = :sid AND e.status = 'APPROVED'
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
    " . academicPeriodRecordFilter('e', 'e.applied_at') . "
    ORDER BY c.course_code ASC
");
$approvedStmt->execute(array_merge(['sid' => $studentId], academicPeriodRecordParams()));
$enrolledCourses = $approvedStmt->fetchAll();

$myAppsStmt = $pdo->prepare("
    SELECT e.id AS enrollment_id, e.status, e.applied_at, e.decided_at,
           c.id AS course_id, c.course_code, c.course_name,
           u.first_name AS prof_first, u.last_name AS prof_last
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE e.student_id = :sid
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
    " . academicPeriodRecordFilter('e', 'e.applied_at') . "
    ORDER BY e.applied_at DESC
");
$myAppsStmt->execute(array_merge(['sid' => $studentId], academicPeriodRecordParams()));
$myApplications = $myAppsStmt->fetchAll();

$catalogStmt = $pdo->prepare("
    SELECT c.*,
           u.first_name AS prof_first, u.last_name AS prof_last, u.email AS prof_email,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'APPROVED') AS approved_students,
           (SELECT status FROM enrollments WHERE course_id = c.id AND student_id = :sid LIMIT 1) AS my_status
           ,
           EXISTS (
               SELECT 1
               FROM course_programs allowed_program
               JOIN programs allowed_track ON allowed_track.id = allowed_program.program_id AND allowed_track.is_active = 1
               JOIN users eligible_student ON eligible_student.program_id = allowed_track.id
                   AND eligible_student.role = 'student'
               WHERE allowed_program.course_id = c.id AND eligible_student.id = :program_student_id
           ) AS program_eligible
    FROM courses c
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE c.archived_at IS NULL" . academicPeriodRecordFilter('c', 'c.created_at') . "
    ORDER BY c.course_code ASC
");
$catalogStmt->execute(array_merge([
    'sid' => $studentId,
    'program_student_id' => $studentId,
], academicPeriodRecordParams()));
$catalogCourses = $catalogStmt->fetchAll();

$enrolledCourseIds = array_column($enrolledCourses, 'course_id');
$inClause = !empty($enrolledCourseIds) ? implode(',', array_map('intval', $enrolledCourseIds)) : '0';

$announcementsStmt = $pdo->prepare("
    SELECT a.*, a.headline AS title, c.course_code, c.course_name, u.first_name, u.last_name, u.role
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE (a.course_id IS NULL OR a.course_id IN ($inClause))
    AND {$announcementAudienceFilter}" . academicPeriodRecordFilter('a', 'a.created_at') . "
    ORDER BY a.created_at DESC
");
$announcementsStmt->execute(academicPeriodRecordParams());
$announcements = $announcementsStmt->fetchAll();
$dashboardAnnouncements = $announcements;
$dashboardAnnouncementsHref = '../announcements.php';
$showDashboardAnnouncements = true;

$enrolledCount = count($enrolledCourses);
$assignmentStmt = $pdo->prepare("
    SELECT a.id, a.title, a.due_at, c.course_code, c.course_name
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = :student_id AND e.status = 'APPROVED'
    LEFT JOIN assignment_completions ac ON ac.assignment_id = a.id AND ac.student_id = :completion_student_id
    WHERE ac.assignment_id IS NULL
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
    " . academicPeriodRecordFilter('a', 'a.created_at') . "
    ORDER BY a.due_at ASC
");
$assignmentStmt->execute(array_merge([
    'student_id' => $studentId,
    'completion_student_id' => $studentId,
], academicPeriodRecordParams()));
$pendingAssignments = $assignmentStmt->fetchAll();
$pendingEnrollmentTasks = array_values(array_filter(
    $myApplications,
    static fn($application) => $application['status'] === 'PENDING'
));
$studentPendingTaskCount = count($pendingAssignments) + count($pendingEnrollmentTasks);
$manilaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$tomorrowStart = $manilaNow->setTime(0, 0)->modify('+1 day')->format('Y-m-d H:i:s');
$assignmentMetricsStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT a.id) AS assigned_count,
           COUNT(DISTINCT CASE
               WHEN ac.assignment_id IS NULL
                    AND a.due_at >= :today_now
                    AND a.due_at < :tomorrow_start
               THEN a.id
           END) AS due_today_count,
           COUNT(DISTINCT CASE
               WHEN ac.assignment_id IS NULL AND a.due_at < :missed_before
               THEN a.id
           END) AS missed_count
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    JOIN enrollments e ON e.course_id = a.course_id
        AND e.student_id = :student_id
        AND e.status = 'APPROVED'
        AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
    LEFT JOIN assignment_completions ac
        ON ac.assignment_id = a.id AND ac.student_id = :completion_student_id
    WHERE 1 = 1" . academicPeriodRecordFilter('a', 'a.created_at', 'metrics') . "
");
$assignmentMetricsStmt->execute(array_merge([
    'today_now' => $manilaNow->format('Y-m-d H:i:s'),
    'tomorrow_start' => $tomorrowStart,
    'missed_before' => $manilaNow->format('Y-m-d H:i:s'),
    'student_id' => $studentId,
    'completion_student_id' => $studentId,
], academicPeriodRecordParams('metrics')));
$assignmentMetrics = $assignmentMetricsStmt->fetch();
$unreadAnnouncementsStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM announcements a
    WHERE (
        a.course_id IS NULL
        OR EXISTS (
            SELECT 1
            FROM enrollments e
            WHERE e.course_id = a.course_id
              AND e.student_id = :student_id
              AND e.status = 'APPROVED'
              AND EXISTS (
                  SELECT 1 FROM course_programs allowed_program
                  JOIN programs allowed_track ON allowed_track.id = allowed_program.program_id AND allowed_track.is_active = 1
                  JOIN users eligible_student ON eligible_student.program_id = allowed_track.id
                      AND eligible_student.role = 'student'
                  WHERE allowed_program.course_id = a.course_id AND eligible_student.id = e.student_id
              )
        )
    )
      AND NOT EXISTS (
          SELECT 1
          FROM student_announcement_reads sar
          WHERE sar.announcement_id = a.id AND sar.student_id = :read_student_id
    ) AND {$announcementAudienceFilter}" . academicPeriodRecordFilter('a', 'a.created_at', 'unread') . "
");
$unreadAnnouncementsStmt->execute(array_merge([
    'student_id' => $studentId,
    'read_student_id' => $studentId,
], academicPeriodRecordParams('unread')));
$unreadAnnouncementCount = (int) $unreadAnnouncementsStmt->fetchColumn();
$studentAnnouncementCount = $unreadAnnouncementCount;
$studentPage = 'dashboard';
$greeting = dashboardGreeting($manilaNow);

require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell has-dashboard-announcements" id="studentDashboardShell">
    <?php require '../../includes/student_sidebar.php'; ?>

    <div class="container student-dashboard-content">
        <div class="student-dashboard-heading">
            <div class="student-dashboard-heading-copy">
                <h1>Dashboard</h1>
            </div>
        </div>

        <div class="student-dashboard-layout">
            <div class="student-dashboard-main-column student-dashboard-intro">
                <section class="student-welcome-card">
                    <div class="student-welcome-copy">
                        <h1><?= htmlspecialchars($greeting) ?>,
                            <?= htmlspecialchars($currentUser['first_name'] ?? $_SESSION['user_name']) ?>
                        </h1>
                        <?php require '../../includes/role_identifier_summary.php'; ?>
                        <?php require '../../includes/academic_period_summary.php'; ?>
                    </div>
                </section>
            </div>

            <aside class="student-dashboard-side-column" aria-label="Student announcements">
                <section class="student-overview-card student-announcements-preview">
                    <div class="student-overview-card-heading">
                        <div>
                            <h2>Latest announcement</h2>
                            <p>The most recent campus or class update for you</p>
                        </div>
                        <a href="student_announcements.php">View all</a>
                    </div>
                    <div class="student-announcement-list">
                        <?php foreach (array_slice($announcements, 0, 1) as $announcement): ?>
                            <a class="student-announcement-preview student-announcement-bulletin"
                                href="student_announcements.php">
                                <span>
                                    <small>
                                        <?= htmlspecialchars(announcementAuthorLabel($announcement['role']), ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($announcement['role'] !== 'registrar'): ?>
                                            &middot;
                                            <?= htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </small>
                                    <strong><?= htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <p><?= nl2br(htmlspecialchars($announcement['message'], ENT_QUOTES, 'UTF-8')) ?></p>
                                </span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($announcements)): ?>
                            <p class="student-empty-note student-empty-inline">No announcements for your courses yet.</p>
                        <?php endif; ?>
                    </div>
                </section>

            </aside>

            <div class="student-dashboard-main-column student-dashboard-details">

                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon teal" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M5 3.5h14A2.5 2.5 0 0 1 21.5 6v14H7a3 3 0 0 1-3-3V5.5A2 2 0 0 1 6 3.5" />
                                <path d="M4 17a3 3 0 0 1 3-3h14M8 7h8M8 10h6" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= $enrolledCount ?></h3>
                            <p>My Courses</p>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon gold" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="17" rx="2" />
                                <path d="M16 2v4M8 2v4M3 9h18M8 13h3M13 13h3" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= (int) ($assignmentMetrics['due_today_count'] ?? 0) ?></h3>
                            <p>Due Today</p>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon navy" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M5 3.5h14A2.5 2.5 0 0 1 21.5 6v14H7a3 3 0 0 1-3-3V5.5A2 2 0 0 1 6 3.5" />
                                <path d="M8 7h8M8 10h6M8 14h8" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= (int) ($assignmentMetrics['assigned_count'] ?? 0) ?></h3>
                            <p>Assigned</p>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon coral" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3 2M5.6 5.6l-2 2" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= (int) ($assignmentMetrics['missed_count'] ?? 0) ?></h3>
                            <p>Missed</p>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon teal" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5z" />
                                <path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8M8 10h6" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= $unreadAnnouncementCount ?></h3>
                            <p>Unread Announcements</p>
                        </div>
                    </div>
                </div>
                <div class="tab-panel" id="tabCatalog">
                    <div class="card">
                        <div class="table-toolbar">
                            <div class="search-box" style="flex:1; max-width:400px;">
                                <input type="text" id="catalogSearch"
                                    placeholder="Search by course code, title, or professor...">
                            </div>
                            <div class="text-muted" style="font-size:0.85rem;">
                                Browse active courses and apply with 1 click
                            </div>
                        </div>

                        <div class="catalog-grid">
                            <?php foreach ($catalogCourses as $c): ?>
                                <?php
                                $isFull = $c['approved_students'] >= $c['max_capacity'];
                                $isProgramEligible = (int) $c['program_eligible'] === 1;
                                $pct = $c['max_capacity'] > 0 ? min(100, round(($c['approved_students'] / $c['max_capacity']) * 100)) : 0;
                                ?>
                                <div class="course-card<?= $isProgramEligible ? '' : ' course-card-unavailable' ?>">
                                    <div class="course-card-top">
                                        <div class="course-badge-row">
                                            <span class="course-code-tag"><?= htmlspecialchars($c['course_code']) ?></span>
                                            <?php if (!$isProgramEligible): ?>
                                                <span class="badge" style="background:#fff0f2; color:var(--coral-dark);">Outside
                                                    Degree Track</span>
                                            <?php elseif ($c['my_status'] === 'APPROVED'): ?>
                                                <span class="badge badge-approved">Enrolled</span>
                                            <?php elseif ($c['my_status'] === 'PENDING'): ?>
                                                <span class="badge badge-pending">Pending Approval</span>
                                            <?php elseif ($c['my_status'] === 'REJECTED'): ?>
                                                <span class="badge badge-rejected">Rejected</span>
                                            <?php elseif ($isFull): ?>
                                                <span class="badge" style="background:#fff0f2; color:var(--coral-dark);">Class
                                                    Full</span>
                                            <?php else: ?>
                                                <span class="badge"
                                                    style="background:var(--teal-light); color:var(--teal-dark);">Open
                                                    Seats</span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="course-title"><?= htmlspecialchars($c['course_name']) ?></h3>
                                        <div class="course-instructor">
                                            <div class="instructor-avatar">P</div>
                                            <span>Prof.
                                                <?= htmlspecialchars($c['prof_first'] . ' ' . $c['prof_last']) ?></span>
                                        </div>
                                    </div>

                                    <div class="course-card-bottom">
                                        <div class="course-meta-stats">
                                            <span>Seats: <?= (int) $c['approved_students'] ?> /
                                                <?= (int) $c['max_capacity'] ?></span>
                                            <span><?= 100 - $pct ?>% Available</span>
                                        </div>
                                        <div class="capacity-meter">
                                            <div class="capacity-fill <?= $pct >= 85 ? 'high' : '' ?>"
                                                style="width: <?= $pct ?>%;"></div>
                                        </div>

                                        <div style="margin-top:1rem;">
                                            <?php if (!$isProgramEligible): ?>
                                                <button class="btn btn-outline btn-sm" style="width:100%;" disabled
                                                    title="<?= htmlspecialchars(STUDENT_COURSE_ACCESS_DENIED_MESSAGE, ENT_QUOTES, 'UTF-8') ?>">
                                                    Not Available for Your Degree Track
                                                </button>
                                            <?php elseif ($c['my_status'] === 'APPROVED'): ?>
                                                <button class="btn btn-outline btn-sm" style="width:100%; cursor:default;"
                                                    disabled>
                                                    &#10003; Enrolled in Class
                                                </button>
                                            <?php elseif ($c['my_status'] === 'PENDING'): ?>
                                                <button class="btn btn-outline btn-sm"
                                                    style="width:100%; color:var(--status-pending);" disabled>
                                                    Application Pending
                                                </button>
                                            <?php elseif ($isFull): ?>
                                                <button class="btn btn-outline btn-sm" style="width:100%;" disabled>
                                                    Capacity Reached
                                                </button>
                                            <?php else: ?>
                                                <form action="../../api/student_process.php" method="POST">
                                                    <input type="hidden" name="action" value="apply_course">
                                                    <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="btn btn-teal btn-sm" style="width:100%;">
                                                        <?= $c['my_status'] === 'REJECTED' ? 'Re-Apply for Enrollment' : '+ Apply for Enrollment' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>


                <div class="tab-panel" id="tabApplications">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">My Application History &amp; Decisions</div>
                            <span class="text-muted" style="font-size:0.85rem;">All courses you have applied for</span>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Instructor</th>
                                        <th>Status</th>
                                        <th>Applied On</th>
                                        <th>Decision Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($myApplications)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:2rem;">No enrollment
                                                applications submitted yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($myApplications as $app): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($app['course_code']) ?></strong> &bull;
                                                    <?= htmlspecialchars($app['course_name']) ?>
                                                </td>
                                                <td>Prof. <?= htmlspecialchars($app['prof_first'] . ' ' . $app['prof_last']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= strtolower($app['status']) ?>">
                                                        <?= htmlspecialchars($app['status']) ?>
                                                    </span>
                                                </td>
                                                <td style="font-size:0.8rem;">
                                                    <?= date('M d, Y', strtotime($app['applied_at'])) ?>
                                                </td>
                                                <td style="font-size:0.8rem;">
                                                    <?= $app['decided_at'] ? date('M d, Y', strtotime($app['decided_at'])) : '<span class="text-muted">Awaiting decision</span>' ?>
                                                </td>
                                                <td>
                                                    <form action="../../api/student_process.php" method="POST"
                                                        onsubmit="return confirm('Withdraw or remove this course application?');">
                                                        <input type="hidden" name="action" value="drop_course">
                                                        <input type="hidden" name="enrollment_id"
                                                            value="<?= $app['enrollment_id'] ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm"
                                                            style="color:var(--coral-dark);">
                                                            <?= $app['status'] === 'APPROVED' ? 'Drop' : 'Cancel' ?>
                                                        </button>
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

                <div class="tab-panel" id="tabProfile">
                    <div class="profile-layout">
                        <div class="profile-card-sidebar">
                            <div class="profile-avatar-large">
                                <?= strtoupper(substr($currentUser['first_name'] ?? 'S', 0, 1)) ?>
                            </div>
                            <h2 style="font-size:1.3rem; color:var(--navy);">
                                <?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?>
                            </h2>
                            <p class="text-muted" style="font-size:0.875rem;">
                                <?= htmlspecialchars($currentUser['email']) ?>
                            </p>
                            <div style="margin-top:1rem;">
                                <span class="role-pill role-student">APEX Student</span>
                            </div>
                            <div
                                style="margin-top:1.5rem; text-align:left; font-size:0.85rem; border-top:1px solid var(--surface-border); padding-top:1rem;">
                                <p style="margin-bottom:0.4rem;"><strong>Student ID:</strong> #<?= $studentId ?></p>
                                <p style="margin-bottom:0.4rem;"><strong>Joined:</strong>
                                    <?= date('F j, Y', strtotime($currentUser['created_at'])) ?>
                                </p>
                                <p><strong>Status:</strong> Active Scholar</p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Edit Profile Information</div>
                            </div>

                            <form action="../../api/student_process.php" method="POST">
                                <input type="hidden" name="action" value="update_profile">

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                    <div class="form-group">
                                        <label for="prof_fn">First Name *</label>
                                        <input type="text" id="prof_fn" name="first_name" class="form-control"
                                            placeholder="e.g. Juan"
                                            value="<?= htmlspecialchars($currentUser['first_name']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="prof_ln">Last Name *</label>
                                        <input type="text" id="prof_ln" name="last_name" class="form-control"
                                            placeholder="e.g. Dela Cruz"
                                            value="<?= htmlspecialchars($currentUser['last_name']) ?>" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="prof_email">APEX Email</label>
                                    <input type="email" id="prof_email" class="form-control"
                                        value="<?= htmlspecialchars($currentUser['email']) ?>" disabled>
                                    <small class="text-muted">Email address is managed by campus administration.</small>
                                </div>

                                <button type="submit" class="btn btn-teal" style="margin-top:0.5rem;">
                                    Save Profile Updates
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>