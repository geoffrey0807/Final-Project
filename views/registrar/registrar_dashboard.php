<?php
require_once '../../includes/session_check.php';
checkAccess(['registrar'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/dashboard_greeting.php';
require_once '../../includes/announcement_access.php';
require_once '../../includes/student_progression.php';

$registrarView = $_GET['view'] ?? ($registrarView ?? 'dashboard');
$allowedRegistrarViews = ['dashboard', 'courses', 'users', 'announcements', 'placements'];
if (!in_array($registrarView, $allowedRegistrarViews, true)) {
    $registrarView = 'dashboard';
}
$registrarScriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/views/registrar/registrar_dashboard.php');
$registrarApplicationRoot = str_replace('\\', '/', dirname(dirname(dirname($registrarScriptPath))));
$registrarApplicationRoot = rtrim($registrarApplicationRoot, '/');
$registrarAdminProcessEndpoint = $registrarApplicationRoot . '/api/admin_process.php';
$registrarViewTitles = [
    'dashboard' => 'Dashboard',
    'courses' => 'Courses & Sections',
    'users' => 'User Accounts',
    'announcements' => 'Announcements',
    'placements' => 'Student Placements',
];
$pageTitle = $registrarViewTitles[$registrarView] . ' | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611081500', '../../css/student.css?v=202611020900'];
$extraJs = ['../../js/admin.js?v=202611072130', '../../js/student.js?v=202610301835', '../../js/registrar-announcements.js?v=202611082130'];
$bodyClass = 'admin-dashboard student-dashboard registrar-portal';
$useVisualTheme = true;

$manilaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$greeting = dashboardGreeting($manilaNow);

$studentCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$profCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'professor'")->fetchColumn();
$deanCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'dean'")->fetchColumn();
$courseCountStmt = $pdo->prepare("SELECT COUNT(*) FROM courses c WHERE c.archived_at IS NULL" . academicPeriodRecordFilter('c', 'c.created_at'));
$courseCountStmt->execute(academicPeriodRecordParams());
$courseCount = $courseCountStmt->fetchColumn();
$selectedCourseStatus = $_GET['course_status'] ?? 'active';
if (!in_array($selectedCourseStatus, ['all', 'active', 'archived'], true)) {
    $selectedCourseStatus = 'active';
}
$courseStatusFilter = match ($selectedCourseStatus) {
    'active' => ' AND c.archived_at IS NULL',
    'archived' => ' AND c.archived_at IS NOT NULL',
    default => '',
};

$coursesStmt = $pdo->prepare("
    SELECT c.*,
           u.first_name AS prof_first, u.last_name AS prof_last, u.email AS prof_email,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'APPROVED') AS enrolled_students
    FROM courses c
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE 1 = 1" . $courseStatusFilter . academicPeriodRecordFilter('c', 'c.created_at') . "
    ORDER BY c.course_code ASC
");
$coursesStmt->execute(academicPeriodRecordParams());
$courses = $coursesStmt->fetchAll();
$courseArchiveCsrfToken = $_SESSION['registrar_course_archive_csrf'] ??= bin2hex(random_bytes(32));

$userRoles = ['dean', 'professor', 'student'];
$selectedUserRole = $_GET['role'] ?? 'all';
if (!in_array($selectedUserRole, array_merge(['all'], $userRoles), true)) {
    $selectedUserRole = 'all';
}
$usersSql = "SELECT u.id, u.first_name, u.middle_name, u.last_name, u.suffix, u.id_number,
        COALESCE(p.name, d.name) AS program, p.code AS program_code,
        u.email, u.role, u.created_at
    FROM users u
    LEFT JOIN programs p ON p.id = u.program_id
    LEFT JOIN departments d ON d.id = u.department_id
    WHERE u.role IN ('dean', 'professor', 'student')";
if ($selectedUserRole !== 'all') {
    $usersSql .= ' AND u.role = :role';
}
$usersSql .= ' ORDER BY u.last_name ASC, u.first_name ASC, u.middle_name ASC';
$registrarUsersStmt = $pdo->prepare($usersSql);
if ($selectedUserRole !== 'all') {
    $registrarUsersStmt->execute(['role' => $selectedUserRole]);
} else {
    $registrarUsersStmt->execute();
}
$registrarUsers = $registrarUsersStmt->fetchAll();
$registrarUserCsrfToken = $_SESSION['registrar_user_csrf'] ??= bin2hex(random_bytes(32));
$registrarAnnouncementCsrfToken = $_SESSION['registrar_announcement_csrf'] ??= bin2hex(random_bytes(32));
$departmentsStmt = $pdo->query('SELECT id, code, name FROM departments WHERE is_active = 1 ORDER BY name ASC');
$departments = $departmentsStmt->fetchAll();
$programsStmt = $pdo->query("
    SELECT p.id, p.code, p.name, p.department_id, d.name AS department_name, p.is_active
    FROM programs p
    JOIN departments d ON d.id = p.department_id
    WHERE p.code IN ('BSSE', 'BSMAD', 'BSCS', 'GPMC', 'BSIT', 'ADFSWD')
    ORDER BY d.name ASC, p.name ASC
");
$programs = $programsStmt->fetchAll();
$activePrograms = array_values(array_filter($programs, static fn(array $program): bool => (bool) $program['is_active']));

$profListStmt = $pdo->query("
    SELECT id, first_name, last_name, id_number, email
    FROM users
    WHERE role = 'professor' AND is_active = 1 AND archived_at IS NULL
    ORDER BY last_name ASC
");
$professors = $profListStmt->fetchAll();
$courseStudentsStmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.id_number, u.email, u.program_id,
           u.year_level,
           p.code AS program_code,
                     (SELECT GROUP_CONCAT(e.course_id)
                        FROM enrollments e WHERE e.student_id = u.id
                            AND e.academic_year = :enrolled_year AND e.semester = :enrolled_semester) AS enrolled_course_ids,
           (SELECT GROUP_CONCAT(DISTINCT e.course_code)
                        FROM enrollments e WHERE e.student_id = u.id
                            AND e.academic_year = :codes_year AND e.semester = :codes_semester) AS enrolled_course_codes
    FROM users u
    JOIN programs p ON p.id = u.program_id
    JOIN departments d ON d.id = p.department_id
    WHERE u.role = 'student' AND u.is_active = 1 AND u.archived_at IS NULL
      AND p.is_active = 1 AND d.is_active = 1
      AND p.code IN ('BSSE', 'BSMAD', 'BSCS', 'GPMC', 'BSIT', 'ADFSWD')
    ORDER BY u.last_name ASC, u.first_name ASC
");
$courseStudentsStmt->execute([
    'enrolled_year' => $academicPeriod['academic_year'],
    'enrolled_semester' => $academicPeriod['semester'],
    'codes_year' => $academicPeriod['academic_year'],
    'codes_semester' => $academicPeriod['semester'],
]);
$courseStudents = $courseStudentsStmt->fetchAll();

$placementStudents = [];
$placementCsrfToken = '';
$placementIsEditable = !$academicReadOnly;
$placementEditableFlag = $placementIsEditable ? '1' : '0';
if ($registrarView === 'placements' && $academicPeriod !== null) {
    $placementCsrfToken = $_SESSION['registrar_placement_csrf'] ??= bin2hex(random_bytes(32));
    $placementStudentsStmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.id_number, u.created_at,
               p.id AS program_id, p.code AS program_code, p.name AS program_name,
               department.name AS department_name,
               placement.progression_status AS saved_progression_status,
               CASE
                   WHEN placement.student_id IS NOT NULL THEN placement.year_level
                   WHEN {$placementEditableFlag} = 1 THEN u.year_level
                   ELSE NULL
               END AS placement_year_level,
               CASE
                   WHEN placement.student_id IS NOT NULL THEN placement.progression_status
                   WHEN {$placementEditableFlag} = 0 THEN NULL
                   WHEN u.year_level IS NULL
                        OR (u.year_level = 1 AND u.created_at >= :new_student_period_start
                            AND u.created_at < :new_student_period_end) THEN 'new'
                   ELSE 'continued'
               END AS placement_progression_status
        FROM users u
        JOIN programs p ON p.id = u.program_id
        JOIN departments department ON department.id = p.department_id
        JOIN academic_periods period
          ON period.academic_year = :period_year AND period.semester = :period_semester
        LEFT JOIN student_academic_placements placement
          ON placement.student_id = u.id
         AND placement.academic_year = period.academic_year
         AND placement.semester = period.semester
                WHERE u.role = 'student'
                    AND (
                            ({$placementEditableFlag} = 1 AND u.is_active = 1 AND u.archived_at IS NULL)
                            OR placement.student_id IS NOT NULL
                            OR EXISTS (
                                    SELECT 1
                                    FROM enrollments historical_enrollment
                                    WHERE historical_enrollment.student_id = u.id
                                        AND historical_enrollment.academic_year = period.academic_year
                                        AND historical_enrollment.semester = period.semester
                                        AND historical_enrollment.status = 'APPROVED'
                            )
                    )
        ORDER BY department.name, p.code, u.year_level, u.last_name, u.first_name
    ");
    $placementStudentsStmt->execute([
        'period_year' => $academicPeriod['academic_year'],
        'period_semester' => $academicPeriod['semester'],
        'new_student_period_start' => $academicPeriod['start_date'],
        'new_student_period_end' => $academicPeriod['end_date'],
    ]);
    $placementStudents = $placementStudentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $previousPlacementsByStudent = [];
    if ($placementStudents !== []) {
        $previousStudentPlaceholders = [];
        $previousStudentParams = [];
        foreach ($placementStudents as $index => $placementStudent) {
            $parameter = 'placement_student_' . $index;
            $previousStudentPlaceholders[] = ':' . $parameter;
            $previousStudentParams[$parameter] = (int) $placementStudent['id'];
        }
        $previousPlacementsStmt = $pdo->prepare("
            SELECT student_id, academic_year, semester, year_level
            FROM student_academic_placements
            WHERE student_id IN (" . implode(',', $previousStudentPlaceholders) . ")
              AND (
                  academic_year < :placement_previous_year
                  OR (academic_year = :placement_previous_year_same AND semester < :placement_previous_semester)
              )
            ORDER BY student_id, academic_year DESC, FIELD(semester, 'first', 'second') DESC
        ");
        $previousPlacementsStmt->execute(array_merge($previousStudentParams, [
            'placement_previous_year' => $academicPeriod['academic_year'],
            'placement_previous_year_same' => $academicPeriod['academic_year'],
            'placement_previous_semester' => $academicPeriod['semester'],
        ]));
        foreach ($previousPlacementsStmt->fetchAll(PDO::FETCH_ASSOC) as $previousPlacement) {
            $previousPlacementsByStudent[(int) $previousPlacement['student_id']] ??= $previousPlacement;
        }
    }

    $placementCourseGradesByStudent = [];
    $gradePeriodConditions = [];
    $gradePeriodParams = [];
    foreach ($previousPlacementsByStudent as $index => $previousPlacement) {
        $studentParameter = 'grade_student_' . $index;
        $yearParameter = 'grade_year_' . $index;
        $semesterParameter = 'grade_semester_' . $index;
        $gradePeriodConditions[] = "(e.student_id = :{$studentParameter} AND e.academic_year = :{$yearParameter} AND e.semester = :{$semesterParameter})";
        $gradePeriodParams[$studentParameter] = (int) $index;
        $gradePeriodParams[$yearParameter] = $previousPlacement['academic_year'];
        $gradePeriodParams[$semesterParameter] = $previousPlacement['semester'];
    }
    if ($gradePeriodConditions !== []) {
        $placementGradesStmt = $pdo->prepare("
            SELECT e.student_id, e.academic_year, e.semester, c.course_code, c.course_name,
                   e.final_grade, e.grade_status, e.grade_released_at
            FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            WHERE e.status = 'APPROVED'
              AND (" . implode(' OR ', $gradePeriodConditions) . ")
            ORDER BY e.student_id, c.course_code, c.course_name
        ");
        $placementGradesStmt->execute($gradePeriodParams);
        foreach ($placementGradesStmt->fetchAll(PDO::FETCH_ASSOC) as $courseGrade) {
            $placementCourseGradesByStudent[(int) $courseGrade['student_id']][] = $courseGrade;
        }
    }
}

$announcementStmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.role, c.course_code,
           (
               SELECT GROUP_CONCAT(target.role ORDER BY FIELD(target.role, 'admin', 'registrar', 'dean', 'professor', 'student') SEPARATOR ',')
               FROM announcement_audience_roles target
               WHERE target.announcement_id = a.id
           ) AS audience_roles
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.author_id = :registrar_id" . academicPeriodRecordFilter('a', 'a.created_at') . "
    ORDER BY COALESCE(a.updated_at, a.created_at) DESC, a.id DESC
");
$announcementStmt->execute(array_merge(
    ['registrar_id' => (int) $_SESSION['user_id']],
    academicPeriodRecordParams()
));
$announcements = $announcementStmt->fetchAll();
$registrarAnnouncementAudienceFilter = announcementAudienceVisibilitySql('a', 'registrar');
$dashboardAnnouncementsStmt = $pdo->prepare("
    SELECT a.headline AS title, a.message, c.course_code, u.first_name, u.last_name, u.role
    FROM announcements a
    JOIN users u ON u.id = a.author_id
    LEFT JOIN courses c ON c.id = a.course_id
    WHERE {$registrarAnnouncementAudienceFilter}" . academicPeriodRecordFilter('a', 'a.created_at') . "
    ORDER BY a.created_at DESC
    LIMIT 6
");
$dashboardAnnouncementsStmt->execute(academicPeriodRecordParams());
$dashboardAnnouncements = $dashboardAnnouncementsStmt->fetchAll();
$dashboardAnnouncementsHref = '../announcements.php';
$showDashboardAnnouncements = $registrarView === 'dashboard';

require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell<?= $registrarView === 'dashboard' ? ' has-dashboard-announcements' : '' ?>"
    id="studentDashboardShell">
    <?php require '../../includes/registrar_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <div class="student-dashboard-heading">
            <div class="student-dashboard-heading-copy">
                <h1><?= htmlspecialchars($registrarViewTitles[$registrarView]) ?></h1>
            </div>
        </div>
        <?php if ($registrarView === 'dashboard'): ?>
            <section class="student-welcome-card registrar-welcome-card">
                <div class="student-welcome-copy">
                    <h1><?= $greeting ?>, <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
                    <?php require '../../includes/role_identifier_summary.php'; ?>
                    <?php require '../../includes/academic_period_summary.php'; ?>
                </div>
            </section>

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon navy" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="9" cy="8" r="3" />
                            <path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5.5a3 3 0 0 1 0 5.8M18 14a5 5 0 0 1 3 4.5V20" />
                        </svg>
                    </div>
                    <div class="metric-data">
                        <h3><?= number_format($studentCount) ?></h3>
                        <p>Enrolled Students</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon teal" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="9" cy="8" r="3" />
                            <path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5.5a3 3 0 0 1 0 5.8M18 14a5 5 0 0 1 3 4.5V20" />
                        </svg>
                    </div>
                    <div class="metric-data">
                        <h3><?= number_format($profCount) ?></h3>
                        <p>Faculty Professors</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon coral" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M3 9 12 4l9 5M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18M2 9h20" />
                        </svg>
                    </div>
                    <div class="metric-data">
                        <h3><?= number_format($deanCount) ?></h3>
                        <p>Academic Deans</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon coral" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5z" />
                            <path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8" />
                        </svg>
                    </div>
                    <div class="metric-data">
                        <h3><?= number_format($courseCount) ?></h3>
                        <p>Curriculum Courses</p>
                        </section>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($registrarView === 'courses'): ?>
                <div class="tab-panel active" id="tabCourses">
                    <div class="card">
                        <div class="table-toolbar">
                            <div class="search-box">
                                <input type="text" placeholder="Search courses or codes..."
                                    data-table-search="coursesTable">
                            </div>
                            <div class="registrar-course-toolbar-actions">
                                <form class="registrar-course-status-filter" method="get" action="registrar_dashboard.php">
                                    <input type="hidden" name="view" value="courses">
                                    <label for="registrarCourseStatus">Status</label>
                                    <select class="form-control" id="registrarCourseStatus" name="course_status"
                                        onchange="this.form.submit()">
                                        <option value="all" <?= $selectedCourseStatus === 'all' ? ' selected' : '' ?>>All
                                        </option>
                                        <option value="active" <?= $selectedCourseStatus === 'active' ? ' selected' : '' ?>>
                                            Active</option>
                                        <option value="archived" <?= $selectedCourseStatus === 'archived' ? ' selected' : '' ?>>Archived</option>
                                    </select>
                                </form>
                                <button class="btn btn-primary btn-sm" data-open-modal="addCourseModal"><svg
                                        class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 5v14M5 12h14" />
                                    </svg> Create Draft</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table admin-user-table registrar-user-table registrar-course-table"
                                id="coursesTable">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Assigned Section &amp; Professor</th>
                                        <th>Capacity</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($courses)): ?>
                                        <tr>
                                            <td class="admin-table-empty" colspan="6">No courses found for this status.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($courses as $c): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                                                <td>
                                                    <?= htmlspecialchars($c['course_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td>
                                                    Section
                                                    <?= htmlspecialchars($c['section_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?>
                                                    · <?= !empty($c['prof_first'])
                                                        ? htmlspecialchars($c['prof_first'] . ' ' . $c['prof_last'], ENT_QUOTES, 'UTF-8')
                                                        : 'Unassigned' ?>
                                                </td>
                                                <td>
                                                    <?= (int) $c['enrolled_students'] ?>/<?= (int) $c['max_capacity'] ?>
                                                </td>
                                                <td>
                                                    <span
                                                        class="admin-status-pill<?= $c['archived_at'] !== null ? ' archived' : ' active' ?>">
                                                        <?= $c['archived_at'] !== null ? 'Archived' : 'Active' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="admin-account-actions registrar-course-actions">
                                                        <?php if ($c['archived_at'] === null): ?>
                                                            <button type="button" class="btn btn-primary btn-sm course-schedule-edit"
                                                                data-open-modal="courseScheduleModal"
                                                                data-course-id="<?= (int) $c['id'] ?>"
                                                                data-course-name="<?= htmlspecialchars($c['course_code'] . ' · ' . $c['course_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-course-code="<?= htmlspecialchars($c['course_code'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-days="<?= htmlspecialchars($c['meeting_days'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-start="<?= htmlspecialchars(substr((string) ($c['start_time'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-end="<?= htmlspecialchars(substr((string) ($c['end_time'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-room="<?= htmlspecialchars($c['room'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-professor-id="<?= (int) ($c['professor_id'] ?? 0) ?>"
                                                                data-capacity="<?= (int) $c['max_capacity'] ?>"
                                                                data-enrolled-count="<?= (int) $c['enrolled_students'] ?>">
                                                                SET
                                                            </button>
                                                            <form action="../../api/admin_process.php" method="POST"
                                                                data-course-confirm-form
                                                                data-confirm-message="Archive course <?= htmlspecialchars($c['course_code'], ENT_QUOTES, 'UTF-8') ?>? Enrollment and course records will be retained.">
                                                                <input type="hidden" name="csrf_token"
                                                                    value="<?= htmlspecialchars($courseArchiveCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="action" value="archive_course">
                                                                <input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
                                                                <button type="submit"
                                                                    class="btn btn-outline btn-archive btn-sm">Archive</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form action="../../api/admin_process.php" method="POST"
                                                                data-course-confirm-form
                                                                data-confirm-message="Restore course <?= htmlspecialchars($c['course_code'], ENT_QUOTES, 'UTF-8') ?>?">
                                                                <input type="hidden" name="csrf_token"
                                                                    value="<?= htmlspecialchars($courseArchiveCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="action" value="restore_course">
                                                                <input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
                                                                <button type="submit" class="btn btn-primary btn-sm">Restore</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <dialog class="registrar-course-confirm" id="registrarCourseConfirm"
                            aria-labelledby="registrarCourseConfirmMessage">
                            <form method="dialog">
                                <p id="registrarCourseConfirmMessage">Confirm course change?</p>
                                <div class="professor-assessment-confirm-actions">
                                    <button type="submit" value="no">No</button>
                                    <button type="submit" value="yes">Yes</button>
                                </div>
                            </form>
                        </dialog>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($registrarView === 'placements'): ?>
                <section class="card registrar-placement-card">
                    <div class="table-toolbar">
                        <div>
                            <h2 class="card-title">Student placement</h2>
                            <p class="text-muted">
                                <?= htmlspecialchars($academicPeriod['academic_year'], ENT_QUOTES, 'UTF-8') ?> ·
                                <?= htmlspecialchars($academicPeriod['semester_label'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                        <div class="search-box">
                            <input type="search" placeholder="Search students or programs..."
                                data-table-search="placementsTable">
                        </div>
                    </div>
                    <?php if ($academicReadOnly): ?>
                        <p class="text-muted" role="status">This academic period is read-only. Placement changes are available
                            only in the open period.</p>
                    <?php endif; ?>
                    <form id="registrarPlacementForm" action="../../api/admin_process.php" method="post"
                        data-placement-form>
                        <input type="hidden" name="action" value="save_student_placements">
                        <input type="hidden" name="placement_csrf"
                            value="<?= htmlspecialchars($placementCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="table-responsive">
                            <table
                                class="data-table registrar-placement-table<?= $placementIsEditable ? ' is-editable' : '' ?>"
                                id="placementsTable">
                                <colgroup>
                                    <?php if ($placementIsEditable): ?>
                                        <col class="placement-select-column">
                                        <col class="placement-student-column">
                                        <col class="placement-program-column">
                                        <col class="placement-year-column">
                                        <col class="placement-progression-column">
                                        <col class="placement-gwa-column">
                                    <?php else: ?>
                                        <col class="placement-student-column">
                                        <col class="placement-program-column">
                                        <col class="placement-year-column">
                                        <col class="placement-progression-column">
                                        <col class="placement-gwa-column">
                                    <?php endif; ?>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <?php if ($placementIsEditable): ?>
                                            <th scope="col">Select</th><?php endif; ?>
                                        <th scope="col">Student</th>
                                        <th scope="col" class="registrar-placement-program">Program / department</th>
                                        <th scope="col">Year level</th>
                                        <th scope="col">Progression</th>
                                        <th scope="col">GWA / Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$placementStudents): ?>
                                        <tr>
                                            <td colspan="<?= $placementIsEditable ? 6 : 5 ?>" class="text-muted">No active
                                                students are available.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($placementStudents as $student): ?>
                                            <?php
                                            $yearLevelLimit = studentProgramYearLimit((string) $student['program_code']);
                                            $placementYear = $student['placement_year_level'] === null ? '' : (string) (int) $student['placement_year_level'];
                                            $placementProgression = (string) ($student['placement_progression_status'] ?? '');
                                            $studentPlacementField = 'placements[' . (int) $student['id'] . ']';
                                            $previousPlacement = $previousPlacementsByStudent[(int) $student['id']] ?? null;
                                            $studentCourseGrades = $placementCourseGradesByStudent[(int) $student['id']] ?? [];
                                            $releasedCourseGrades = array_values(array_filter(
                                                $studentCourseGrades,
                                                static fn(array $courseGrade): bool => $courseGrade['grade_released_at'] !== null
                                                    && $courseGrade['final_grade'] !== null
                                                    && $courseGrade['grade_status'] !== 'Drop'
                                            ));
                                            $gradePointSum = array_sum(array_map(
                                                static fn(array $courseGrade): float => (float) $courseGrade['final_grade'],
                                                $releasedCourseGrades
                                            ));
                                            $gradedCourseCount = count($releasedCourseGrades);
                                            $averageGradePoints = $gradedCourseCount > 0
                                                ? $gradePointSum / $gradedCourseCount
                                                : null;
                                            $studentGwa = $averageGradePoints;
                                            $requiredCourseGrades = array_values(array_filter(
                                                $studentCourseGrades,
                                                static fn(array $courseGrade): bool => $courseGrade['grade_status'] !== 'Drop'
                                            ));
                                            $studentGradesReady = $previousPlacement === null || $requiredCourseGrades !== [];
                                            $studentGradesPassed = $studentGradesReady;
                                            foreach ($requiredCourseGrades as $courseGrade) {
                                                $courseGradeReleased = $courseGrade['grade_released_at'] !== null
                                                    && $courseGrade['final_grade'] !== null;
                                                if (!$courseGradeReleased) {
                                                    $studentGradesReady = false;
                                                    $studentGradesPassed = false;
                                                } elseif (
                                                    $courseGrade['grade_status'] === 'Fail'
                                                    || !studentFinalGradePasses((float) $courseGrade['final_grade'])
                                                ) {
                                                    $studentGradesPassed = false;
                                                }
                                            }
                                            $previousYearLevel = $previousPlacement === null ? null : (int) $previousPlacement['year_level'];
                                            $previousAcademicYear = $previousPlacement['academic_year'] ?? null;
                                            $currentYearLevel = $student['placement_year_level'] === null
                                                ? 1
                                                : (int) $student['placement_year_level'];
                                            $placementChoices = [];
                                            if ($studentGradesReady && $studentGradesPassed) {
                                                if ($previousPlacement === null) {
                                                    if (
                                                        isValidStudentProgression(
                                                            'new',
                                                            1,
                                                            null,
                                                            null,
                                                            null,
                                                            $currentYearLevel,
                                                            $academicPeriod['academic_year'],
                                                            $academicPeriod['semester']
                                                        )
                                                    ) {
                                                        $placementChoices['new'] = 1;
                                                    }
                                                } else {
                                                    if (
                                                        isValidStudentProgression(
                                                            'continued',
                                                            $currentYearLevel,
                                                            $previousYearLevel,
                                                            $previousAcademicYear,
                                                            $previousPlacement['semester'],
                                                            $currentYearLevel,
                                                            $academicPeriod['academic_year'],
                                                            $academicPeriod['semester']
                                                        )
                                                    ) {
                                                        $placementChoices['continued'] = $currentYearLevel;
                                                    }
                                                    $promotedYearLevel = $previousYearLevel + 1;
                                                    if (
                                                        $promotedYearLevel <= $yearLevelLimit
                                                        && isValidStudentProgression(
                                                            'promoted',
                                                            $promotedYearLevel,
                                                            $previousYearLevel,
                                                            $previousAcademicYear,
                                                            $previousPlacement['semester'],
                                                            $currentYearLevel,
                                                            $academicPeriod['academic_year'],
                                                            $academicPeriod['semester']
                                                        )
                                                    ) {
                                                        $placementChoices['promoted'] = $promotedYearLevel;
                                                    }
                                                }
                                            } elseif ($studentGradesReady) {
                                                if (isValidStudentYearLevel((string) $student['program_code'], $currentYearLevel)) {
                                                    $placementChoices['retake'] = $currentYearLevel;
                                                }
                                            }
                                            $selectedPlacementStatus = array_key_exists($placementProgression, $placementChoices)
                                                ? $placementProgression
                                                : (string) (array_key_first($placementChoices) ?? '');
                                            $selectedPlacementYear = $placementChoices[$selectedPlacementStatus]
                                                ?? $currentYearLevel;
                                            $placementUnavailableLabel = !$studentGradesReady
                                                ? 'Awaiting released grades'
                                                : 'No valid placement available';
                                            $pendingGradeCount = count(array_filter(
                                                $studentCourseGrades,
                                                static fn(array $courseGrade): bool => $courseGrade['grade_status'] !== 'Drop'
                                                    && ($courseGrade['grade_released_at'] === null || $courseGrade['final_grade'] === null)
                                            ));
                                            $gradeModalId = 'placementGradeModal-' . (int) $student['id'];
                                            ?>
                                            <tr>
                                                <?php if ($placementIsEditable): ?>
                                                    <td><input type="checkbox" name="<?= $studentPlacementField ?>[selected]" value="1"
                                                            <?= !$studentGradesReady || $placementChoices === [] ? 'disabled' : '' ?>
                                                            aria-label="Select <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <strong><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <div class="text-muted">
                                                        <?= htmlspecialchars($student['id_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                </td>
                                                <td class="registrar-placement-program">
                                                    <strong><?= htmlspecialchars($student['program_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <span><?= htmlspecialchars($student['department_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                                <?php if ($placementIsEditable): ?>
                                                    <td>
                                                        <select class="form-control" name="<?= $studentPlacementField ?>[year_level]"
                                                            data-placement-year-select aria-label="Target year level"
                                                            <?= $placementChoices === [] ? 'disabled' : '' ?>
                                                            data-placement-choices="<?= htmlspecialchars(json_encode($placementChoices), ENT_QUOTES, 'UTF-8') ?>">
                                                            <?php if ($placementChoices === []): ?>
                                                                <option value="" selected disabled><?= htmlspecialchars($placementUnavailableLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endif; ?>
                                                            <?php for ($yearLevel = 1; $yearLevel <= $yearLevelLimit; $yearLevel++): ?>
                                                                <option value="<?= $yearLevel ?>" <?= !in_array($yearLevel, array_values($placementChoices), true) ? 'disabled' : '' ?>
                                                                    <?= $selectedPlacementYear === $yearLevel ? 'selected' : '' ?>>Year
                                                                    <?= $yearLevel ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select class="form-control"
                                                            name="<?= $studentPlacementField ?>[progression_status]"
                                                            data-placement-progression-select aria-label="Progression status"
                                                            <?= $placementChoices === [] ? 'disabled' : '' ?>
                                                            data-placement-choices="<?= htmlspecialchars(json_encode($placementChoices), ENT_QUOTES, 'UTF-8') ?>">
                                                            <?php if ($placementChoices === []): ?>
                                                                <option value="" selected disabled><?= htmlspecialchars($placementUnavailableLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endif; ?>
                                                            <?php foreach (['new' => 'New', 'continued' => 'Continued (same year)', 'promoted' => 'Promoted to next year', 'retake' => 'Retake'] as $statusValue => $statusLabel): ?>
                                                                <option value="<?= $statusValue ?>" <?= !array_key_exists($statusValue, $placementChoices) ? 'disabled' : '' ?>
                                                                    <?= $selectedPlacementStatus === $statusValue ? 'selected' : '' ?>>
                                                                    <?= $statusLabel ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php if (!$studentGradesReady): ?>
                                                            <small class="registrar-placement-grade-warning">Placement is locked until all
                                                                approved course grades are released.</small>
                                                        <?php elseif (!$studentGradesPassed): ?>
                                                            <small class="registrar-placement-grade-warning">A failing grade requires Retake
                                                                at the current year.</small>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php else: ?>
                                                    <td><?= $placementYear !== '' ? 'Year ' . htmlspecialchars($placementYear, ENT_QUOTES, 'UTF-8') : 'Not recorded' ?>
                                                    </td>
                                                    <td><?= $placementProgression !== '' ? htmlspecialchars(ucfirst($placementProgression), ENT_QUOTES, 'UTF-8') : 'Not recorded' ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <button type="button" class="btn btn-outline btn-sm"
                                                        data-open-modal="<?= $gradeModalId ?>">View grade</button>
                                                    <div class="modal-backdrop" id="<?= $gradeModalId ?>">
                                                        <div class="modal-dialog registrar-placement-grade-dialog" role="dialog"
                                                            aria-modal="true" aria-labelledby="<?= $gradeModalId ?>Title">
                                                            <div class="modal-header">
                                                                <h2 class="modal-title" id="<?= $gradeModalId ?>Title">
                                                                    Grades for
                                                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8') ?>
                                                                </h2>
                                                                <button type="button" class="modal-close" data-close-modal
                                                                    aria-label="Close grade details">&times;</button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="registrar-grade-summary">
                                                                    <p><strong>Average grade
                                                                            points</strong><span><?= $averageGradePoints === null ? '—' : number_format($averageGradePoints, 2) ?></span>
                                                                    </p>
                                                                    <p><strong>Courses
                                                                            counted</strong><span><?= $gradedCourseCount ?></span>
                                                                    </p>
                                                                    <p><strong>Semester
                                                                            GWA</strong><span><?= $studentGwa === null ? '—' : number_format($studentGwa, 2) ?></span>
                                                                    </p>
                                                                    <p><strong>Pending
                                                                            grades</strong><span><?= $pendingGradeCount ?></span>
                                                                    </p>
                                                                </div>
                                                                <?php if (!$studentCourseGrades): ?>
                                                                    <p class="text-muted">No approved course enrollments for this
                                                                        academic period.</p>
                                                                <?php else: ?>
                                                                    <div class="table-responsive">
                                                                        <table class="data-table registrar-placement-grade-table">
                                                                            <colgroup>
                                                                                <col class="grade-course-column">
                                                                                <col class="grade-result-column">
                                                                                <col class="grade-status-column">
                                                                            </colgroup>
                                                                            <thead>
                                                                                <tr>
                                                                                    <th scope="col">Course</th>
                                                                                    <th scope="col">Final grade</th>
                                                                                    <th scope="col">Status</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach ($studentCourseGrades as $courseGrade): ?>
                                                                                    <?php
                                                                                    $courseGradeReleased = $courseGrade['grade_released_at'] !== null
                                                                                        && $courseGrade['final_grade'] !== null;
                                                                                    $courseGradeStatus = $courseGrade['grade_status']
                                                                                        ?? ($courseGradeReleased ? 'Released' : 'Pending Grades');
                                                                                    ?>
                                                                                    <tr>
                                                                                        <td>
                                                                                            <strong><?= htmlspecialchars($courseGrade['course_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                                                            <div class="text-muted">
                                                                                                <?= htmlspecialchars($courseGrade['course_name'], ENT_QUOTES, 'UTF-8') ?>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td><?= $courseGradeReleased ? number_format((float) $courseGrade['final_grade'], 2) : '—' ?>
                                                                                        </td>
                                                                                        <td><?= htmlspecialchars($courseGradeStatus, ENT_QUOTES, 'UTF-8') ?>
                                                                                        </td>
                                                                                    </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($placementIsEditable && $placementStudents): ?>
                            <div class="table-toolbar registrar-placement-actions">
                                <button type="button" class="btn btn-primary" data-open-placement-confirm>Save
                                    placements</button>
                            </div>
                        <?php endif; ?>
                        <dialog class="registrar-placement-confirm" data-placement-confirm
                            aria-labelledby="registrarPlacementConfirmTitle"
                            aria-describedby="registrarPlacementConfirmDescription">
                            <div class="registrar-placement-confirm-content">
                                <h2 id="registrarPlacementConfirmTitle">Save placement changes?</h2>
                                <p id="registrarPlacementConfirmDescription">
                                    This will update the selected students for the current academic period.
                                </p>
                                <div class="registrar-placement-confirm-actions">
                                    <button type="button" class="btn btn-outline" data-close-placement-confirm>No</button>
                                    <button type="submit" class="btn btn-primary" form="registrarPlacementForm">Yes,
                                        save</button>
                                </div>
                            </div>
                        </dialog>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($registrarView === 'users'): ?>
                <div class="tab-panel active" id="tabUsers">
                    <section class="card admin-user-table-card admin-role-table-card">
                        <div class="table-toolbar registrar-users-toolbar">
                            <div>
                                <h1 class="card-title">User Accounts</h1>
                                <p class="text-muted"><?= number_format(count($registrarUsers)) ?> accounts</p>
                            </div>
                            <div class="admin-user-toolbar-actions registrar-user-toolbar-actions">
                                <form class="admin-account-role-filter" method="get" action="registrar_users.php">
                                    <label for="registrarUserRole">User Role</label>
                                    <select class="form-control" id="registrarUserRole" name="role"
                                        onchange="this.form.submit()">
                                        <option value="all" <?= $selectedUserRole === 'all' ? ' selected' : '' ?>>All users
                                        </option>
                                        <option value="dean" <?= $selectedUserRole === 'dean' ? ' selected' : '' ?>>Dean
                                        </option>
                                        <option value="professor" <?= $selectedUserRole === 'professor' ? ' selected' : '' ?>>
                                            Professor</option>
                                        <option value="student" <?= $selectedUserRole === 'student' ? ' selected' : '' ?>>
                                            Student</option>
                                    </select>
                                </form>
                                <button type="button" class="btn btn-outline btn-sm" data-open-modal="manageProgramsModal">
                                    Manage Programs
                                </button>
                                <button type="button" class="btn btn-primary admin-create-registrar-trigger"
                                    data-open-modal="addUserModal"><svg class="dashboard-action-icon" viewBox="0 0 24 24"
                                        aria-hidden="true">
                                        <path d="M12 5v14M5 12h14" />
                                    </svg> Add User Account</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table admin-user-table registrar-user-table" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>ID Number</th>
                                        <th>Surname</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Suffix</th>
                                        <th>Program / Department</th>
                                        <th>Email</th>
                                        <th>User Role</th>
                                        <th>Account Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$registrarUsers): ?>
                                        <tr>
                                            <td class="registrar-user-table-empty" colspan="9">No users found for this role.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($registrarUsers as $u): ?>
                                            <tr id="user-<?= (int) $u['id'] ?>">
                                                <td class="account-id-cell">
                                                    <?= htmlspecialchars($u['id_number'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?>
                                                </td>
                                                <td><?= htmlspecialchars($u['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($u['first_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($u['middle_name'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?>
                                                </td>
                                                <td><?= htmlspecialchars($u['suffix'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                                <td><?= htmlspecialchars($u['program'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                                <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <span
                                                        class="role-pill role-<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars(ucfirst($u['role']), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td class="registrar-user-created-date">
                                                    <?= htmlspecialchars(date('M d, Y', strtotime($u['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <?php if ($registrarView === 'announcements'): ?>
                <div class="tab-panel active" id="tabAnnouncements">
                    <div class="card">
                        <div class="table-toolbar">
                            <div class="card-title" style="margin:0;">Broadcasts &amp; Bulletins</div>
                            <div class="support-request-tools registrar-announcement-tools">
                                <button class="btn btn-coral btn-sm registrar-announcement-post-button"
                                    data-open-modal="addAnnouncementModal">
                                    <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 5v14M5 12h14" />
                                    </svg>
                                    Post Announcement
                                </button>
                                <?php if ($announcements): ?>
                                    <form class="support-request-delete-form" id="deleteRegistrarAnnouncementsForm"
                                        action="../../api/registrar_announcements_process.php" method="post"
                                        data-support-delete-form
                                        data-confirm-message="Permanently delete the selected announcements? This cannot be undone.">
                                        <input type="hidden" name="csrf_token"
                                            value="<?= htmlspecialchars($registrarAnnouncementCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button class="support-request-delete-button" type="submit" data-support-delete-button
                                            aria-label="Delete selected announcements"
                                            title="Permanently delete selected announcements" disabled>
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M4 7h16M9 7V4h6v3m3 0-.8 13H6.8L6 7m4 4v5m4-5v5" />
                                            </svg>
                                            <span class="support-request-delete-count" data-support-selected-count
                                                hidden></span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table registrar-announcement-table" data-no-sort>
                                <thead>
                                    <tr>
                                        <th class="registrar-announcement-select-heading">
                                            <span class="sr-only">Select</span>
                                        </th>
                                        <th scope="col">Headline</th>
                                        <th scope="col">Role</th>
                                        <th scope="col">Homepage Notice</th>
                                        <th scope="col">Last Updated</th>
                                        <th scope="col">Attachment</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($announcements)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center; padding:2rem;">No announcements active.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($announcements as $a): ?>
                                            <?php
                                            $editAudienceRoles = $a['audience_roles']
                                                ? explode(',', $a['audience_roles'])
                                                : ['admin', 'registrar', 'dean', 'professor', 'student'];
                                            if (!empty($a['is_homepage_notice'])) {
                                                $editAudienceRoles = [];
                                            }
                                            ?>
                                            <tr>
                                                <td class="registrar-announcement-select-cell">
                                                    <label class="support-request-select registrar-announcement-select">
                                                        <input type="checkbox" name="announcement_ids[]"
                                                            value="<?= (int) $a['id'] ?>" form="deleteRegistrarAnnouncementsForm"
                                                            data-support-request-select>
                                                        <span class="sr-only">Select announcement
                                                            <?= htmlspecialchars($a['headline'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    </label>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($a['headline'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    if (!empty($a['is_homepage_notice'])):
                                                        ?>
                                                        Homepage only
                                                    <?php else: ?>
                                                        <?php
                                                        $audienceRoles = $a['audience_roles']
                                                            ? explode(',', $a['audience_roles'])
                                                            : ['admin', 'registrar', 'dean', 'professor', 'student'];
                                                        $allAudienceRoles = ['admin', 'registrar', 'dean', 'professor', 'student'];
                                                        if (count(array_intersect($allAudienceRoles, $audienceRoles)) === count($allAudienceRoles)):
                                                            ?>
                                                            All
                                                        <?php else: ?>
                                                            <?= htmlspecialchars(implode(', ', array_map(
                                                                static fn(string $role): string => $role === 'student' ? 'Students' : ucfirst($role),
                                                                $audienceRoles
                                                            )), ENT_QUOTES, 'UTF-8') ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($a['is_homepage_notice'])): ?>
                                                        <?php
                                                        $homepageCategoryLabels = [
                                                            'academic' => 'Academic',
                                                            'system' => 'System Update',
                                                            'event' => 'Campus Event',
                                                            'policy' => 'Policy Notice',
                                                        ];
                                                        ?>
                                                        <?= htmlspecialchars($homepageCategoryLabels[$a['homepage_category'] ?? ''] ?? 'Bulletin', ENT_QUOTES, 'UTF-8') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Portal only</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="font-size:0.8rem;">
                                                    <?php $announcementDate = $a['updated_at'] ?? $a['created_at']; ?>
                                                    <?= !empty($a['updated_at']) ? 'Updated ' : '' ?><?= htmlspecialchars(date('M d, Y h:i A', strtotime($announcementDate)), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td>
                                                    <?php if (
                                                        is_string($a['image_path'])
                                                        && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $a['image_path'])
                                                    ): ?>
                                                        <button class="announcement-attachment-open" type="button"
                                                            data-announcement-attachment-open
                                                            data-image-src="../../<?= htmlspecialchars($a['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-image-alt="<?= htmlspecialchars($a['headline'], ENT_QUOTES, 'UTF-8') ?>">
                                                            View Attachment
                                                        </button>
                                                    <?php else: ?>
                                                        --
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-outline btn-sm"
                                                        data-open-modal="addAnnouncementModal"
                                                        data-edit-announcement
                                                        data-announcement-id="<?= (int) $a['id'] ?>"
                                                        data-announcement-headline="<?= htmlspecialchars($a['headline'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-announcement-message="<?= htmlspecialchars($a['message'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-homepage-notice="<?= (int) $a['is_homepage_notice'] ?>"
                                                        data-homepage-category="<?= htmlspecialchars($a['homepage_category'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-audience-roles="<?= htmlspecialchars(json_encode($editAudienceRoles), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-has-image="<?= !empty($a['image_path']) ? '1' : '0' ?>">Edit</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>


        <div class="modal-backdrop" id="addCourseModal">
            <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="addCourseModalTitle">
                <form action="<?= htmlspecialchars($registrarAdminProcessEndpoint, ENT_QUOTES, 'UTF-8') ?>"
                    method="POST" data-course-form>
                    <input type="hidden" name="action" value="add_course_group">
                    <div class="modal-header">
                        <h3 class="modal-title" id="addCourseModalTitle">Create Course Draft</h3>
                        <button type="button" class="modal-close" data-close-modal
                            aria-label="Close dialog">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="course-editor-grid">
                            <section class="course-days-fieldset course-editor-basics" aria-label="Course data">
                                <div class="form-group">
                                    <label for="course_code">Course Code</label>
                                    <input type="text" id="course_code" name="course_code" class="form-control"
                                        placeholder="e.g. CS101" required>
                                </div>
                                <div class="form-group">
                                    <label for="course_name">Course Name</label>
                                    <input type="text" id="course_name" name="course_name" class="form-control"
                                        placeholder="e.g. Introduction to Computing" required>
                                </div>
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Draft</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-backdrop" id="courseScheduleModal">
            <div class="modal-dialog">
                <form action="<?= htmlspecialchars($registrarAdminProcessEndpoint, ENT_QUOTES, 'UTF-8') ?>"
                    method="POST" data-course-form>
                    <input type="hidden" name="action" value="update_course_schedule">
                    <input type="hidden" id="schedule_course_id" name="course_id">
                    <div class="modal-header">
                        <h3 class="modal-title">Set Course Schedule</h3>
                        <button type="button" class="modal-close" data-close-modal>&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="course-editor-grid course-schedule-editor-grid">
                            <section class="course-editor-column course-assignment-column"
                                aria-label="Course assignments">
                                <p id="schedule_course_name" class="text-muted"></p>
                                <div class="form-group">
                                    <label for="schedule_professor_search">Assign Teacher</label>
                                    <div class="course-professor-picker" data-professor-picker>
                                        <input type="search" id="schedule_professor_search" class="form-control"
                                            data-professor-search role="combobox" aria-autocomplete="list"
                                            aria-expanded="false" aria-controls="schedule_professor_results"
                                            placeholder="Search professor by name or ID number" autocomplete="off">
                                        <div class="course-professor-picker-results" id="schedule_professor_results"
                                            data-professor-results role="listbox" aria-label="Matching professors"
                                            hidden></div>
                                        <select id="schedule_professor" name="professor_id"
                                            class="course-professor-select" tabindex="-1" aria-hidden="true">
                                            <option value="">Choose professor</option>
                                            <?php foreach ($professors as $p): ?>
                                                <?php $professorName = 'Prof. ' . $p['first_name'] . ' ' . $p['last_name']; ?>
                                                <option value="<?= (int) $p['id'] ?>"
                                                    data-professor-name="<?= htmlspecialchars($professorName, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-professor-id-number="<?= htmlspecialchars($p['id_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-professor-email="<?= htmlspecialchars($p['email'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($professorName . ' · ' . ($p['id_number'] ?: $p['email']), ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="course-professor-picker-selected" data-professor-selected
                                            aria-live="polite" hidden></p>
                                    </div>
                                </div>
                                <div class="course-student-assignment">
                                    <p class="course-student-assignment-title">Assign Students</p>
                                    <details class="admin-account-filter course-cohort-filter-dropdown"
                                        data-cohort-filter>
                                        <summary>
                                            <span>Filter</span>
                                            <span class="course-cohort-filter-count" data-filter-count hidden></span>
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M4 6h16M7 12h10m-7 6h4" />
                                            </svg>
                                        </summary>
                                        <div class="admin-account-filter-popover course-cohort-filter-popover">
                                            <div class="admin-account-filter-groups">
                                                <fieldset>
                                                    <legend>Year level</legend>
                                                    <?php foreach ([1, 2, 3, 4] as $year): ?>
                                                        <label>
                                                            <input type="checkbox" value="<?= $year ?>"
                                                                data-filter-year-choice>
                                                            Year <?= $year ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </fieldset>
                                                <fieldset>
                                                    <legend>Program</legend>
                                                    <?php foreach ($activePrograms as $program): ?>
                                                        <label>
                                                            <input type="checkbox" value="<?= (int) $program['id'] ?>"
                                                                data-filter-program-choice>
                                                            <?= htmlspecialchars($program['code'], ENT_QUOTES, 'UTF-8') ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </fieldset>
                                            </div>
                                            <div class="admin-account-filter-actions">
                                                <button type="button" class="course-cohort-filter-reset"
                                                    data-filter-reset>Reset</button>
                                                <button type="button" data-filter-apply>Apply</button>
                                            </div>
                                        </div>
                                    </details>
                                    <div data-cohort-applied-inputs></div>
                                    <div class="form-group course-student-picker" data-manual-students
                                        data-student-picker>
                                        <label for="schedule_course_students_search">Student</label>
                                        <input type="search" id="schedule_course_students_search" class="form-control"
                                            data-student-search role="combobox" aria-autocomplete="list"
                                            aria-expanded="false" aria-controls="schedule_student_results"
                                            placeholder="Search student by name or ID number" autocomplete="off">
                                        <div class="course-student-picker-results" id="schedule_student_results"
                                            data-student-results role="listbox" aria-label="Matching students" hidden>
                                        </div>
                                        <div class="course-student-picker-selected" data-student-selected
                                            aria-live="polite"></div>
                                        <select id="schedule_course_students" name="student_ids[]"
                                            class="course-student-select" multiple tabindex="-1" aria-hidden="true">
                                            <?php foreach ($courseStudents as $student): ?>
                                                <?php
                                                $studentName = $student['last_name'] . ', ' . $student['first_name'];
                                                $studentIdNumber = (string) $student['id_number'];
                                                $studentEmail = (string) $student['email'];
                                                ?>
                                                <option value="<?= (int) $student['id'] ?>"
                                                    data-student-name="<?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-student-id-number="<?= htmlspecialchars($studentIdNumber, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-student-email="<?= htmlspecialchars($studentEmail, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-program-id="<?= (int) $student['program_id'] ?>"
                                                    data-year-level="<?= $student['year_level'] !== null ? (int) $student['year_level'] : '' ?>"
                                                    data-enrolled-course-ids="<?= htmlspecialchars($student['enrolled_course_ids'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-enrolled-course-codes="<?= htmlspecialchars($student['enrolled_course_codes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($studentName . ' · ' . $studentIdNumber . ' · ' . $student['program_code'] . ' · Year ' . ($student['year_level'] ?? 'Not set'), ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <p class="text-muted" data-seat-summary>Choose a year and program to view eligible
                                        students.</p>
                                    </fieldset>
                            </section>
                            <section class="course-editor-column course-schedule-column" aria-label="Meeting schedule">
                                <div class="course-days-group">
                                    <p class="course-days-title" id="scheduleMeetingScheduleLabel">Meeting Schedule</p>
                                    <fieldset class="course-days-fieldset"
                                        aria-labelledby="scheduleMeetingScheduleLabel">
                                        <div class="course-days-options">
                                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                                <label><input type="checkbox" name="meeting_days[]" value="<?= $day ?>">
                                                    <?= substr($day, 0, 3) ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                </div>
                                <div class="course-schedule-fields">
                                    <div class="form-group">
                                        <label for="schedule_start_time">Start Time</label>
                                        <input type="time" id="schedule_start_time" name="start_time"
                                            class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="schedule_end_time">End Time</label>
                                        <input type="time" id="schedule_end_time" name="end_time" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="schedule_room">Room / Location</label>
                                    <input type="text" id="schedule_room" name="room" class="form-control"
                                        maxlength="100">
                                </div>
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Schedule</button>
                    </div>
                </form>
            </div>
        </div>


        <div class="modal-backdrop" id="addUserModal">
            <div class="modal-dialog">
                <form action="../../api/admin_process.php" method="POST">
                    <input type="hidden" name="registrar_user_csrf"
                        value="<?= htmlspecialchars($registrarUserCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-header">
                        <h3 class="modal-title">Create User Account</h3>
                        <button type="button" class="modal-close" data-close-modal>&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="account-editor-grid">
                            <section class="account-editor-column" aria-label="Personal details">
                                <div class="account-editor-name-fields">
                                    <div class="form-group">
                                        <label for="user_fn">First Name <span class="required-marker"
                                                aria-hidden="true">*</span></label>
                                        <input type="text" id="user_fn" name="first_name" class="form-control"
                                            placeholder="e.g. Juan" maxlength="50" autocomplete="given-name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="user_mn">Middle Name</label>
                                        <input type="text" id="user_mn" name="middle_name" class="form-control"
                                            placeholder="e.g. Maria" maxlength="50" autocomplete="additional-name">
                                    </div>
                                    <div class="form-group">
                                        <label for="user_ln">Surname <span class="required-marker"
                                                aria-hidden="true">*</span></label>
                                        <input type="text" id="user_ln" name="last_name" class="form-control"
                                            placeholder="e.g. Dela Cruz" maxlength="50" autocomplete="family-name"
                                            required>
                                    </div>
                                    <div class="form-group">
                                        <label for="user_suffix">Suffix</label>
                                        <input type="text" id="user_suffix" name="suffix" class="form-control"
                                            placeholder="e.g. Jr." maxlength="20">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="user_role">User Role <span class="required-marker"
                                            aria-hidden="true">*</span></label>
                                    <select id="user_role" name="role" class="form-control" data-user-role-toggle
                                        required>
                                        <option value="" selected>Select a user role</option>
                                        <option value="dean">Dean</option>
                                        <option value="professor">Professor</option>
                                        <option value="student">Student</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="user_id_number">ID Number <span class="required-marker"
                                            aria-hidden="true">*</span></label>
                                    <input type="text" id="user_id_number" name="id_number" class="form-control"
                                        placeholder="Select a role first" maxlength="30"
                                        pattern="(?:D|EDU|STU)-[0-9]{4}-[0-9]{4}" data-role-id-number required>
                                    <small class="text-muted" data-role-id-hint>Select a role to see the required ID
                                        format.</small>
                                </div>
                            </section>
                            <section class="account-editor-column" aria-label="Account access and assignment">
                                <div class="form-group" id="userDepartmentGroup" hidden>
                                    <label for="user_department" data-department-label>Assigned Teaching Department
                                        <span class="required-marker" aria-hidden="true">*</span></label>
                                    <select id="user_department" name="department_id" class="form-control" disabled>
                                        <option value="">Select a department</option>
                                        <?php foreach ($departments as $department): ?>
                                            <option value="<?= (int) $department['id'] ?>">
                                                <?= htmlspecialchars($department['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" id="userProgramGroup" hidden>
                                    <label for="user_program">Enrolled Degree Program <span class="required-marker"
                                            aria-hidden="true">*</span></label>
                                    <select id="user_program" name="program_id" class="form-control" disabled>
                                        <option value="">Select a degree program</option>
                                        <?php foreach ($departments as $department): ?>
                                            <optgroup
                                                label="<?= htmlspecialchars($department['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?php foreach ($activePrograms as $program): ?>
                                                    <?php if ((int) $program['department_id'] === (int) $department['id']): ?>
                                                        <option value="<?= (int) $program['id'] ?>">
                                                            <?= htmlspecialchars(
                                                                $program['name'] . (in_array($program['code'], ['GPMC', 'ADFSWD'], true) ? '' : ' (' . $program['code'] . ')'),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="user_email">APEX Email <span class="required-marker"
                                            aria-hidden="true">*</span></label>
                                    <input type="email" id="user_email" name="email" class="form-control"
                                        placeholder="user@apex.edu.ph" maxlength="100"
                                        pattern="[A-Za-z0-9._%+\-]+@apex\.edu\.ph"
                                        title="Use an APEX Email ending in @apex.edu.ph." required>
                                </div>
                                <div class="form-group">
                                    <label for="user_pass">Initial Password <span class="required-marker"
                                            aria-hidden="true">*</span></label>
                                    <input type="password" id="user_pass" name="password" class="form-control"
                                        placeholder="8+ characters, upper/lowercase, number, symbol" minlength="8"
                                        maxlength="72"
                                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}"
                                        title="Use at least 8 characters with an uppercase letter, a lowercase letter, a number, and a special character."
                                        autocomplete="new-password" required>
                                    <small class="text-muted">Use at least 8 characters, including uppercase and
                                        lowercase letters,
                                        a number, and a special character.</small>
                                </div>
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                        <button type="submit" class="btn btn-teal">Add User Account</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-backdrop" id="manageProgramsModal">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 class="modal-title">Manage Programs</h3>
                    <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Degree tracks are centrally defined and linked to their academic departments.
                    </p>
                    <h4 class="registrar-program-list-heading">Program options</h4>
                    <div class="registrar-program-list">
                        <?php foreach ($programs as $program): ?>
                            <div class="registrar-program-row">
                                <span><?= htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <form action="../../api/registrar_programs_process.php" method="POST">
                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars($registrarUserCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="set_program_status">
                                    <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $program['is_active'] ? '0' : '1' ?>">
                                    <button type="submit" class="btn btn-outline btn-sm">
                                        <?= $program['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$programs): ?>
                            <p class="text-muted">No programs have been added.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


        <div class="modal-backdrop" id="addAnnouncementModal">
            <div class="modal-dialog">
                <form action="../../api/registrar_announcements_process.php" method="POST" enctype="multipart/form-data"
                    data-registrar-announcement-form>
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($registrarAnnouncementCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="publish">
                    <input type="hidden" name="announcement_id" value="">
                    <div class="modal-header">
                        <h3 class="modal-title" data-announcement-form-title>Publish Announcement</h3>
                        <button type="button" class="modal-close" data-close-modal>&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="ann_headline">Headline *</label>
                            <input type="text" id="ann_headline" name="headline" class="form-control" maxlength="150"
                                placeholder="e.g. Midterm Schedule Advisory" required>
                        </div>
                        <div class="form-group registrar-announcement-audience" data-announcement-audience>
                            <span class="form-label">Target roles *</span>
                            <details class="registrar-audience-dropdown">
                                <summary data-audience-summary>All users</summary>
                                <div class="registrar-audience-options">
                                    <label><input type="checkbox" name="audience_all" value="1" data-audience-all
                                            checked>
                                        <span>ALL</span></label>
                                    <label><input type="checkbox" name="audience_roles[]" value="admin"
                                            data-audience-role>
                                        <span>Admin</span></label>
                                    <label><input type="checkbox" name="audience_roles[]" value="dean"
                                            data-audience-role>
                                        <span>Dean</span></label>
                                    <label><input type="checkbox" name="audience_roles[]" value="professor"
                                            data-audience-role>
                                        <span>Professor</span></label>
                                    <label><input type="checkbox" name="audience_roles[]" value="student"
                                            data-audience-role>
                                        <span>Students</span></label>
                                </div>
                            </details>
                            <small>Select ALL or one or more individual roles. Registrar accounts receive ALL
                                broadcasts.</small>
                        </div>
                        <fieldset class="form-group registrar-homepage-notice">
                            <legend>Homepage Notice</legend>
                            <div class="registrar-homepage-notice-content">
                                <div class="registrar-homepage-notice-heading">
                                    <div class="registrar-homepage-notice-title">
                                        <strong>Make this notice public</strong>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M3 11v2a2 2 0 0 0 2 2h2l9 5V4l-9 5H5a2 2 0 0 0-2 2Z" />
                                            <path d="M7 15l1.5 5h3L10 16M19 9a4 4 0 0 1 0 6M21 6a8 8 0 0 1 0 12" />
                                        </svg>
                                    </div>
                                    <label class="registrar-homepage-notice-toggle">
                                        <input type="checkbox" name="is_homepage_notice" value="1" data-homepage-notice
                                            aria-label="Make this notice public on the homepage">
                                        <span class="registrar-homepage-switch" aria-hidden="true"><span></span></span>
                                        <span class="registrar-homepage-switch-label"
                                            data-homepage-notice-label>Off</span>
                                    </label>
                                </div>
                                <p class="registrar-homepage-notice-description">
                                    When enabled, this notice appears on the public homepage.
                                </p>
                                <div class="registrar-homepage-notice-locations">
                                    <strong>WHERE IT APPEARS</strong>
                                    <ul>
                                        <li>Public homepage</li>
                                        <li>Latest Bulletins Carousel</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="registrar-homepage-category-options" data-homepage-category-fields hidden
                                role="radiogroup" aria-label="Homepage notice category">
                                <span class="form-label">Choose one category *</span>
                                <label><input type="radio" name="homepage_category" value="academic" disabled>
                                    <span>Academic</span></label>
                                <label><input type="radio" name="homepage_category" value="system" disabled>
                                    <span>System Update</span></label>
                                <label><input type="radio" name="homepage_category" value="event" disabled>
                                    <span>Campus Event</span></label>
                                <label><input type="radio" name="homepage_category" value="policy" disabled>
                                    <span>Policy Notice</span></label>
                            </div>
                        </fieldset>
                        <div class="form-group">
                            <label for="ann_message">Message *</label>
                            <textarea id="ann_message" name="message" class="form-control" rows="5"
                                placeholder="Write the announcement message..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="ann_image">Image (optional)</label>
                            <input type="file" id="ann_image" name="image" class="form-control"
                                accept="image/jpeg,image/png,image/webp">
                            <small data-announcement-image-note>JPG, PNG, or WebP up to 5 MB. Without an image, the notice is text only.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                        <button type="submit" class="btn btn-coral" data-announcement-submit>Publish Announcement</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>