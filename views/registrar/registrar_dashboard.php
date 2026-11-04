<?php
require_once '../../includes/session_check.php';
checkAccess(['registrar'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/dashboard_greeting.php';
require_once '../../includes/announcement_access.php';

$registrarView = $_GET['view'] ?? ($registrarView ?? 'dashboard');
$allowedRegistrarViews = ['dashboard', 'courses', 'users', 'announcements'];
if (!in_array($registrarView, $allowedRegistrarViews, true)) {
    $registrarView = 'dashboard';
}
$registrarViewTitles = [
    'dashboard' => 'Dashboard',
    'courses' => 'Courses & Sections',
    'users' => 'User Accounts',
    'announcements' => 'Announcements',
];
$pageTitle = $registrarViewTitles[$registrarView] . ' | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611041910', '../../css/student.css?v=202611020900'];
$extraJs = ['../../js/admin.js?v=202611031901', '../../js/student.js?v=202610301835', '../../js/registrar-announcements.js?v=202611041910'];
$bodyClass = 'admin-dashboard student-dashboard registrar-portal';
$useVisualTheme = true;

$manilaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$greeting = dashboardGreeting($manilaNow);

$studentCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$profCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'professor'")->fetchColumn();
$deanCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'dean'")->fetchColumn();
$courseCountStmt = $pdo->prepare("SELECT COUNT(*) FROM courses c WHERE 1 = 1" . academicPeriodDateFilter('c.created_at'));
$courseCountStmt->execute(academicPeriodDateParams());
$courseCount = $courseCountStmt->fetchColumn();

$coursesStmt = $pdo->prepare("
    SELECT c.*,
           u.first_name AS prof_first, u.last_name AS prof_last, u.email AS prof_email,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'APPROVED') AS enrolled_students,
           (SELECT GROUP_CONCAT(cp.program_id ORDER BY cp.program_id)
            FROM course_programs cp WHERE cp.course_id = c.id) AS program_ids
    FROM courses c
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE 1 = 1" . academicPeriodDateFilter('c.created_at') . "
    ORDER BY c.course_code ASC
");
$coursesStmt->execute(academicPeriodDateParams());
$courses = $coursesStmt->fetchAll();

$userRoles = ['dean', 'professor', 'student'];
$selectedUserRole = $_GET['role'] ?? 'all';
if (!in_array($selectedUserRole, array_merge(['all'], $userRoles), true)) {
    $selectedUserRole = 'all';
}
$usersSql = "SELECT u.id, u.first_name, u.middle_name, u.last_name, u.suffix, u.id_number,
        COALESCE(p.name, d.name) AS program, u.email, u.role, u.created_at
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
    SELECT id, first_name, last_name, email
    FROM users
    WHERE role = 'professor' AND is_active = 1 AND archived_at IS NULL
    ORDER BY last_name ASC
");
$professors = $profListStmt->fetchAll();

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
    WHERE a.author_id = :registrar_id" . academicPeriodDateFilter('a.created_at') . "
    ORDER BY a.created_at DESC
");
$announcementStmt->execute(array_merge(
    ['registrar_id' => (int) $_SESSION['user_id']],
    academicPeriodDateParams()
));
$announcements = $announcementStmt->fetchAll();
$registrarAnnouncementAudienceFilter = announcementAudienceVisibilitySql('a', 'registrar');
$dashboardAnnouncementsStmt = $pdo->prepare("
    SELECT a.headline AS title, a.message, c.course_code, u.first_name, u.last_name, u.role
    FROM announcements a
    JOIN users u ON u.id = a.author_id
    LEFT JOIN courses c ON c.id = a.course_id
    WHERE {$registrarAnnouncementAudienceFilter}" . academicPeriodDateFilter('a.created_at') . "
    ORDER BY a.created_at DESC
    LIMIT 6
");
$dashboardAnnouncementsStmt->execute(academicPeriodDateParams());
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
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($registrarView === 'courses'): ?>
            <div class="tab-panel active" id="tabCourses">
                <div class="card">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <input type="text" placeholder="Search courses or codes..." data-table-search="coursesTable">
                        </div>
                        <div>
                            <button class="btn btn-primary btn-sm" data-open-modal="addCourseModal"><svg
                                    class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" />
                                </svg> Create Course</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table" id="coursesTable">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Course, Section &amp; Schedule</th>
                                    <th>Assigned Professor</th>
                                    <th>Enrollment / Capacity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($courses)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:2rem;">No courses created yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($courses as $c): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                                            <td>
                                                <strong><?= htmlspecialchars($c['course_name']) ?></strong>
                                                <div class="text-muted" style="font-size:0.75rem;">Section
                                                    <?= htmlspecialchars($c['section_name'] ?? 'General') ?></div>
                                                <div class="text-muted" style="font-size:0.75rem;">
                                                    <?= !empty($c['meeting_days'])
                                                        ? htmlspecialchars(str_replace(',', ' · ', $c['meeting_days'])) . ' &middot; ' . date('g:i A', strtotime($c['start_time'])) . '&ndash;' . date('g:i A', strtotime($c['end_time']))
                                                        : 'Schedule not set' ?>
                                                    <?= !empty($c['room']) ? ' &middot; ' . htmlspecialchars($c['room']) : '' ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($c['prof_first'])): ?>
                                                    <?= htmlspecialchars($c['prof_first'] . ' ' . $c['prof_last']) ?>
                                                    <div class="text-muted" style="font-size:0.75rem;">
                                                        <?= htmlspecialchars($c['prof_email']) ?></div>
                                                <?php else: ?>
                                                    <span class="badge" style="background:#eee; color:#666;">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-size:0.85rem; font-weight:600; margin-bottom:4px;">
                                                    <?= (int) $c['enrolled_students'] ?> / <?= (int) $c['max_capacity'] ?> Enrolled
                                                </div>
                                                <div class="capacity-meter">
                                                    <?php
                                                    $pct = $c['max_capacity'] > 0 ? min(100, round(($c['enrolled_students'] / $c['max_capacity']) * 100)) : 0;
                                                    ?>
                                                    <div class="capacity-fill <?= $pct >= 90 ? 'high' : '' ?>"
                                                        style="width: <?= $pct ?>%;"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-outline btn-sm course-schedule-edit"
                                                    data-open-modal="courseScheduleModal" data-course-id="<?= (int) $c['id'] ?>"
                                                    data-course-name="<?= htmlspecialchars($c['course_code'] . ' · ' . $c['course_name'], ENT_QUOTES) ?>"
                                                    data-days="<?= htmlspecialchars($c['meeting_days'] ?? '', ENT_QUOTES) ?>"
                                                    data-start="<?= htmlspecialchars(substr((string) ($c['start_time'] ?? ''), 0, 5), ENT_QUOTES) ?>"
                                                    data-end="<?= htmlspecialchars(substr((string) ($c['end_time'] ?? ''), 0, 5), ENT_QUOTES) ?>"
                                                    data-room="<?= htmlspecialchars($c['room'] ?? '', ENT_QUOTES) ?>"
                                                    data-program-ids="<?= htmlspecialchars($c['program_ids'] ?? '', ENT_QUOTES) ?>">
                                                    Set Schedule &amp; Programs
                                                </button>
                                                <form action="../../api/admin_process.php" method="POST" style="display:inline;"
                                                    onsubmit="return confirm('Delete this course? Students enrolled will be affected.');">
                                                    <input type="hidden" name="action" value="delete_course">
                                                    <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
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
        <?php endif; ?>

        <?php if ($registrarView === 'users'): ?>
            <div class="tab-panel active" id="tabUsers">
                <div class="card">
                    <div class="table-toolbar registrar-users-toolbar">
                        <form class="admin-account-role-filter" method="get" action="registrar_users.php">
                            <label for="registrarUserRole">User Role</label>
                            <select class="form-control" id="registrarUserRole" name="role" onchange="this.form.submit()">
                                <option value="all"<?= $selectedUserRole === 'all' ? ' selected' : '' ?>>All users</option>
                                <option value="dean"<?= $selectedUserRole === 'dean' ? ' selected' : '' ?>>Dean</option>
                                <option value="professor"<?= $selectedUserRole === 'professor' ? ' selected' : '' ?>>Professor</option>
                                <option value="student"<?= $selectedUserRole === 'student' ? ' selected' : '' ?>>Student</option>
                            </select>
                        </form>
                        <div>
                            <button type="button" class="btn btn-outline btn-sm" data-open-modal="manageProgramsModal">
                                Manage Programs
                            </button>
                            <button type="button" class="btn btn-teal btn-sm" data-open-modal="addUserModal"><svg
                                    class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" />
                                </svg> Add User Account</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table registrar-user-table" id="usersTable">
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
                                    <tr><td class="registrar-user-table-empty" colspan="9">No users found for this role.</td></tr>
                                <?php else: ?>
                                <?php foreach ($registrarUsers as $u): ?>
                                    <tr id="user-<?= (int) $u['id'] ?>">
                                        <td class="account-id-cell"><?= htmlspecialchars($u['id_number'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                        <td><?= htmlspecialchars($u['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($u['first_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($u['middle_name'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                        <td><?= htmlspecialchars($u['suffix'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                        <td><?= htmlspecialchars($u['program'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                        <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="role-pill role-<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>">
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
                </div>
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
                                    <button class="support-request-delete-button" type="submit"
                                        data-support-delete-button aria-label="Delete selected announcements"
                                        title="Permanently delete selected announcements" disabled>
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M4 7h16M9 7V4h6v3m3 0-.8 13H6.8L6 7m4 4v5m4-5v5" />
                                        </svg>
                                        <span class="support-request-delete-count" data-support-selected-count hidden></span>
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
                                    <th scope="col">Date Uploaded</th>
                                    <th scope="col">Attachment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($announcements)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding:2rem;">No announcements active.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($announcements as $a): ?>
                                        <tr>
                                            <td class="registrar-announcement-select-cell">
                                                <label class="support-request-select registrar-announcement-select">
                                                    <input type="checkbox" name="announcement_ids[]"
                                                        value="<?= (int) $a['id'] ?>"
                                                        form="deleteRegistrarAnnouncementsForm"
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
                                                <?= htmlspecialchars(date('M d, Y h:i A', strtotime($a['created_at'])), ENT_QUOTES, 'UTF-8') ?>
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
        <div class="modal-dialog">
            <form action="../../api/admin_process.php" method="POST">
                <input type="hidden" name="action" value="add_course">
                <div class="modal-header">
                    <h3 class="modal-title">Create New Academic Course</h3>
                    <button type="button" class="modal-close" data-close-modal>&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="course_code">Course Code *</label>
                        <input type="text" id="course_code" name="course_code" class="form-control"
                            placeholder="e.g. CS101" required>
                    </div>
                    <div class="form-group">
                        <label for="course_name">Course Name *</label>
                        <input type="text" id="course_name" name="course_name" class="form-control"
                            placeholder="e.g. Introduction to Computing" required>
                    </div>
                    <div class="form-group">
                        <label for="section_name">Section *</label>
                        <input type="text" id="section_name" name="section_name" class="form-control"
                            placeholder="e.g. A" maxlength="50" value="General" required>
                    </div>
                    <div class="form-group">
                        <label for="professor_id">Assign Faculty Instructor</label>
                        <select id="professor_id" name="professor_id" class="form-control">
                            <option value="">-- Select Professor (Optional) --</option>
                            <?php foreach ($professors as $p): ?>
                                <option value="<?= $p['id'] ?>">Prof.
                                    <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                                    (<?= htmlspecialchars($p['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <fieldset class="course-days-fieldset">
                        <legend>Degree Programs for This Course *</legend>
                        <div class="course-days-options registrar-course-program-options">
                            <?php foreach ($activePrograms as $program): ?>
                                <label>
                                    <input type="checkbox" name="program_ids[]" value="<?= (int) $program['id'] ?>">
                                    <?= htmlspecialchars($program['code'], ENT_QUOTES, 'UTF-8') ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Students outside these degree tracks cannot enroll in or open course materials.</small>
                    </fieldset>
                    <div class="form-group">
                        <label for="max_capacity">Maximum Student Capacity</label>
                        <input type="number" id="max_capacity" name="max_capacity" class="form-control" value="30"
                            min="1" max="100">
                    </div>
                    <fieldset class="course-days-fieldset">
                        <legend>Meeting Days (Optional)</legend>
                        <div class="course-days-options">
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                <label><input type="checkbox" name="meeting_days[]" value="<?= $day ?>">
                                    <?= substr($day, 0, 3) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div class="course-schedule-fields">
                        <div class="form-group">
                            <label for="new_course_start">Start Time</label>
                            <input type="time" id="new_course_start" name="start_time" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="new_course_end">End Time</label>
                            <input type="time" id="new_course_end" name="end_time" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_course_room">Room / Location</label>
                        <input type="text" id="new_course_room" name="room" class="form-control" maxlength="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Course</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="courseScheduleModal">
        <div class="modal-dialog">
            <form action="../../api/admin_process.php" method="POST">
                <input type="hidden" name="action" value="update_course_schedule">
                <input type="hidden" id="schedule_course_id" name="course_id">
                <div class="modal-header">
                    <h3 class="modal-title">Set Course Schedule</h3>
                    <button type="button" class="modal-close" data-close-modal>&times;</button>
                </div>
                <div class="modal-body">
                    <p id="schedule_course_name" class="text-muted"></p>
                    <fieldset class="course-days-fieldset">
                        <legend>Meeting Days</legend>
                        <div class="course-days-options">
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                <label><input type="checkbox" name="meeting_days[]" value="<?= $day ?>">
                                    <?= substr($day, 0, 3) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div class="course-schedule-fields">
                        <div class="form-group">
                            <label for="schedule_start_time">Start Time</label>
                            <input type="time" id="schedule_start_time" name="start_time" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="schedule_end_time">End Time</label>
                            <input type="time" id="schedule_end_time" name="end_time" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="schedule_room">Room / Location</label>
                        <input type="text" id="schedule_room" name="room" class="form-control" maxlength="100">
                    </div>
                    <fieldset class="course-days-fieldset">
                        <legend>Degree Programs for This Course *</legend>
                        <div class="course-days-options registrar-course-program-options">
                            <?php foreach ($activePrograms as $program): ?>
                                <label>
                                    <input type="checkbox" name="program_ids[]" value="<?= (int) $program['id'] ?>">
                                    <?= htmlspecialchars($program['code'], ENT_QUOTES, 'UTF-8') ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">At least one degree track is required. Students outside the selected tracks cannot access this course.</small>
                    </fieldset>
                    <p class="text-muted">Leave all schedule fields empty to mark the course schedule as TBA.</p>
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
                <input type="hidden" name="registrar_user_csrf" value="<?= htmlspecialchars($registrarUserCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-header">
                    <h3 class="modal-title">Create User Account</h3>
                    <button type="button" class="modal-close" data-close-modal>&times;</button>
                </div>
                <div class="modal-body">
                    <div class="registrar-user-form-grid">
                        <div class="form-group">
                            <label for="user_ln">Surname *</label>
                            <input type="text" id="user_ln" name="last_name" class="form-control"
                                placeholder="e.g. Dela Cruz" maxlength="50" autocomplete="family-name" required>
                        </div>
                        <div class="form-group">
                            <label for="user_fn">First Name *</label>
                            <input type="text" id="user_fn" name="first_name" class="form-control"
                                placeholder="e.g. Juan" maxlength="50" autocomplete="given-name" required>
                        </div>
                        <div class="form-group">
                            <label for="user_mn">Middle Name</label>
                            <input type="text" id="user_mn" name="middle_name" class="form-control"
                                maxlength="50" autocomplete="additional-name">
                        </div>
                        <div class="form-group">
                            <label for="user_suffix">Suffix</label>
                            <input type="text" id="user_suffix" name="suffix" class="form-control"
                                placeholder="e.g. Jr." maxlength="20">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="user_role">User Role *</label>
                        <select id="user_role" name="role" class="form-control" data-user-role-toggle required>
                            <option value="" selected>Select a user role</option>
                            <option value="dean">Dean</option>
                            <option value="professor">Professor</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="user_id_number">ID Number *</label>
                        <input type="text" id="user_id_number" name="id_number" class="form-control"
                            placeholder="Select a role first" maxlength="30" pattern="(?:D|EDU|STU)-[0-9]{4}-[0-9]{4}"
                            data-role-id-number required>
                        <small class="text-muted" data-role-id-hint>Select a role to see the required ID format.</small>
                    </div>
                    <div class="form-group" id="userDepartmentGroup" hidden>
                        <label for="user_department" data-department-label>Assigned Teaching Department *</label>
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
                        <label for="user_program">Enrolled Degree Program *</label>
                        <select id="user_program" name="program_id" class="form-control" disabled>
                            <option value="">Select a degree program</option>
                            <?php foreach ($departments as $department): ?>
                                <optgroup label="<?= htmlspecialchars($department['name'], ENT_QUOTES, 'UTF-8') ?>">
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
                        <label for="user_email">APEX Email *</label>
                        <input type="email" id="user_email" name="email" class="form-control"
                            placeholder="user@apex.edu.ph" maxlength="100" pattern="[A-Za-z0-9._%+\-]+@apex\.edu\.ph"
                            title="Use an APEX Email ending in @apex.edu.ph." required>
                    </div>
                    <div class="form-group">
                        <label for="user_pass">Initial Password *</label>
                        <input type="password" id="user_pass" name="password" class="form-control"
                            placeholder="8+ characters, upper/lowercase, number, symbol" minlength="8" maxlength="72"
                            pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}"
                            title="Use at least 8 characters with an uppercase letter, a lowercase letter, a number, and a special character."
                            autocomplete="new-password" required>
                        <small class="text-muted">Use at least 8 characters, including uppercase and lowercase letters,
                            a number, and a special character.</small>
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
                <p class="text-muted">Degree tracks are centrally defined and linked to their academic departments.</p>
                <h4 class="registrar-program-list-heading">Program options</h4>
                <div class="registrar-program-list">
                    <?php foreach ($programs as $program): ?>
                        <div class="registrar-program-row">
                            <span><?= htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <form action="../../api/registrar_programs_process.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($registrarUserCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
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
            <form action="../../api/registrar_announcements_process.php" method="POST"
                enctype="multipart/form-data" data-registrar-announcement-form>
                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($registrarAnnouncementCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="publish">
                <div class="modal-header">
                    <h3 class="modal-title">Publish Announcement</h3>
                    <button type="button" class="modal-close" data-close-modal>&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ann_headline">Headline *</label>
                        <input type="text" id="ann_headline" name="headline" class="form-control"
                            maxlength="150" placeholder="e.g. Midterm Schedule Advisory" required>
                    </div>
                    <div class="form-group registrar-announcement-audience" data-announcement-audience>
                        <span class="form-label">Target roles *</span>
                        <details class="registrar-audience-dropdown">
                            <summary data-audience-summary>All users</summary>
                            <div class="registrar-audience-options">
                                <label><input type="checkbox" name="audience_all" value="1" data-audience-all checked>
                                    <span>ALL</span></label>
                                <label><input type="checkbox" name="audience_roles[]" value="admin" data-audience-role>
                                    <span>Admin</span></label>
                                <label><input type="checkbox" name="audience_roles[]" value="dean" data-audience-role>
                                    <span>Dean</span></label>
                                <label><input type="checkbox" name="audience_roles[]" value="professor" data-audience-role>
                                    <span>Professor</span></label>
                                <label><input type="checkbox" name="audience_roles[]" value="student" data-audience-role>
                                    <span>Students</span></label>
                            </div>
                        </details>
                        <small>Select ALL or one or more individual roles. Registrar accounts receive ALL broadcasts.</small>
                    </div>
                    <fieldset class="form-group registrar-homepage-notice">
                        <legend class="form-label">Homepage Notice</legend>
                        <label class="registrar-homepage-notice-toggle">
                            <input type="checkbox" name="is_homepage_notice" value="1" data-homepage-notice>
                            <span>Also publish this announcement on the public homepage</span>
                        </label>
                        <small>Homepage notices appear on the public Notice page and in the latest-bulletin carousel. They are sent to all portal roles.</small>
                        <div class="registrar-homepage-category-options" data-homepage-category-fields hidden>
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
                        <small>JPG, PNG, or WebP up to 5 MB. Without an image, the announcement is shown as text only.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-coral">Broadcast Now</button>
                </div>
            </form>
        </div>
    </div>

</div>
</div>
<?php require_once '../../includes/footer.php'; ?>