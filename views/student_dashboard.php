<?php
require_once '../includes/session_check.php';
checkAccess(['student'], '../auth/login.php');
require_once '../config/db.php';

$pageTitle = 'Student Portal | APEX Learning Hub';
$extraCss  = '../css/student.css';
$extraJs   = '../js/student.js';

$studentId = (int)$_SESSION['user_id'];
$msg       = $_GET['msg'] ?? '';
$error     = $_GET['error'] ?? '';

$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$userStmt->execute(['id' => $studentId]);
$currentUser = $userStmt->fetch();

$approvedStmt = $pdo->prepare("
    SELECT e.id AS enrollment_id, e.decided_at,
           c.id AS course_id, c.course_code, c.course_name, c.max_capacity,
           u.first_name AS prof_first, u.last_name AS prof_last, u.email AS prof_email
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE e.student_id = :sid AND e.status = 'APPROVED'
    ORDER BY c.course_code ASC
");
$approvedStmt->execute(['sid' => $studentId]);
$enrolledCourses = $approvedStmt->fetchAll();

$myAppsStmt = $pdo->prepare("
    SELECT e.id AS enrollment_id, e.status, e.applied_at, e.decided_at,
           c.id AS course_id, c.course_code, c.course_name,
           u.first_name AS prof_first, u.last_name AS prof_last
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE e.student_id = :sid
    ORDER BY e.applied_at DESC
");
$myAppsStmt->execute(['sid' => $studentId]);
$myApplications = $myAppsStmt->fetchAll();

$catalogStmt = $pdo->prepare("
    SELECT c.*, 
           u.first_name AS prof_first, u.last_name AS prof_last, u.email AS prof_email,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'APPROVED') AS approved_students,
           (SELECT status FROM enrollments WHERE course_id = c.id AND student_id = :sid LIMIT 1) AS my_status
    FROM courses c
    LEFT JOIN users u ON c.professor_id = u.id
    ORDER BY c.course_code ASC
");
$catalogStmt->execute(['sid' => $studentId]);
$catalogCourses = $catalogStmt->fetchAll();

$enrolledCourseIds = array_column($enrolledCourses, 'course_id');
$inClause = !empty($enrolledCourseIds) ? implode(',', array_map('intval', $enrolledCourseIds)) : '0';

$announcementsStmt = $pdo->query("
    SELECT a.*, c.course_code, c.course_name, u.first_name, u.last_name, u.role
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.course_id IS NULL OR a.course_id IN ($inClause)
    ORDER BY a.created_at DESC
");
$announcements = $announcementsStmt->fetchAll();

$enrolledCount = count($enrolledCourses);
$pendingCount  = count(array_filter($myApplications, fn($a) => $a['status'] === 'PENDING'));
$catalogCount  = count($catalogCourses);

require_once '../includes/header.php';
?>

<div class="container">
    
    <div class="student-hero-banner">
        <div class="student-hero-text">
            <h1>APEX Student Portal</h1>
            <p>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?> &bull; Track your coursework, enrollments, and announcements</p>
        </div>
        <div>
            <span class="role-pill role-student" style="background:#ffffff; color:var(--coral-dark); font-size:0.85rem; padding:0.4rem 0.9rem;">
                Student ID #<?= $studentId ?>
            </span>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-icon teal">🎓</div>
            <div class="metric-data">
                <h3><?= $enrolledCount ?></h3>
                <p>Enrolled Courses</p>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon coral">⏳</div>
            <div class="metric-data">
                <h3><?= $pendingCount ?></h3>
                <p>Pending Applications</p>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon navy">📚</div>
            <div class="metric-data">
                <h3><?= $catalogCount ?></h3>
                <p>Available Courses</p>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon gold">📢</div>
            <div class="metric-data">
                <h3><?= count($announcements) ?></h3>
                <p>Campus Notices</p>
            </div>
        </div>
    </div>

    
    <div class="tab-navigation">
        <button type="button" class="tab-btn active" data-target="tabMyCourses">
            My Enrolled Courses <span class="tab-badge"><?= $enrolledCount ?></span>
        </button>
        <button type="button" class="tab-btn" data-target="tabCatalog">
            Course Catalog &amp; Apply <span class="tab-badge"><?= $catalogCount ?></span>
        </button>
        <button type="button" class="tab-btn" data-target="tabApplications">
            Application Status <span class="tab-badge"><?= count($myApplications) ?></span>
        </button>
        <button type="button" class="tab-btn" data-target="tabAnnouncements">
            Announcements <span class="tab-badge"><?= count($announcements) ?></span>
        </button>
        <button type="button" class="tab-btn" data-target="tabProfile">
            My Profile
        </button>
    </div>

    
    <div class="tab-panel active" id="tabMyCourses">
        <?php if (empty($enrolledCourses)): ?>
            <div class="card" style="text-align:center; padding:3rem 1.5rem;">
                <h3 style="color:var(--navy); margin-bottom:0.5rem;">No Active Course Enrollments</h3>
                <p class="text-muted" style="max-width:500px; margin:0 auto 1.5rem auto;">
                    You are not currently enrolled in any courses. Browse the course catalog to submit enrollment applications for faculty review.
                </p>
                <button type="button" class="btn btn-teal" onclick="document.querySelector('[data-target=\'tabCatalog\']').click();">
                    Explore Course Catalog &rarr;
                </button>
            </div>
        <?php else: ?>
            <div class="catalog-grid">
                <?php foreach ($enrolledCourses as $course): ?>
                    <div class="course-card">
                        <div class="course-card-top">
                            <div class="course-badge-row">
                                <span class="course-code-tag"><?= htmlspecialchars($course['course_code']) ?></span>
                                <span class="badge badge-approved">Active Enrollment</span>
                            </div>
                            <h3 class="course-title"><?= htmlspecialchars($course['course_name']) ?></h3>
                            <div class="course-instructor">
                                <div class="instructor-avatar">P</div>
                                <span>Prof. <?= htmlspecialchars($course['prof_first'] . ' ' . $course['prof_last']) ?></span>
                            </div>
                            <div class="text-muted" style="font-size:0.8rem; margin-top:0.4rem;">
                                <?= htmlspecialchars($course['prof_email']) ?>
                            </div>
                        </div>

                        <div class="course-card-bottom">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:0.8rem; color:var(--text-muted);">
                                    Enrolled: <?= date('M d, Y', strtotime($course['decided_at'] ?? 'now')) ?>
                                </span>
                                <form action="../api/student_process.php" method="POST" onsubmit="return confirm('Are you sure you want to drop this enrolled course?');">
                                    <input type="hidden" name="action" value="drop_course">
                                    <input type="hidden" name="enrollment_id" value="<?= $course['enrollment_id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:var(--coral-dark);">Drop Course</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    
    <div class="tab-panel" id="tabCatalog">
        <div class="card">
            <div class="table-toolbar">
                <div class="search-box" style="flex:1; max-width:400px;">
                    <input type="text" id="catalogSearch" placeholder="Search by course code, title, or professor...">
                </div>
                <div class="text-muted" style="font-size:0.85rem;">
                    Browse active courses and apply with 1 click
                </div>
            </div>

            <div class="catalog-grid">
                <?php foreach ($catalogCourses as $c): ?>
                    <?php 
                        $isFull = $c['approved_students'] >= $c['max_capacity'];
                        $pct = $c['max_capacity'] > 0 ? min(100, round(($c['approved_students'] / $c['max_capacity']) * 100)) : 0;
                    ?>
                    <div class="course-card">
                        <div class="course-card-top">
                            <div class="course-badge-row">
                                <span class="course-code-tag"><?= htmlspecialchars($c['course_code']) ?></span>
                                <?php if ($c['my_status'] === 'APPROVED'): ?>
                                    <span class="badge badge-approved">Enrolled</span>
                                <?php elseif ($c['my_status'] === 'PENDING'): ?>
                                    <span class="badge badge-pending">Pending Approval</span>
                                <?php elseif ($c['my_status'] === 'REJECTED'): ?>
                                    <span class="badge badge-rejected">Rejected</span>
                                <?php elseif ($isFull): ?>
                                    <span class="badge" style="background:#feece9; color:var(--coral-dark);">Class Full</span>
                                <?php else: ?>
                                    <span class="badge" style="background:var(--teal-light); color:var(--teal-dark);">Open Seats</span>
                                <?php endif; ?>
                            </div>
                            <h3 class="course-title"><?= htmlspecialchars($c['course_name']) ?></h3>
                            <div class="course-instructor">
                                <div class="instructor-avatar">P</div>
                                <span>Prof. <?= htmlspecialchars($c['prof_first'] . ' ' . $c['prof_last']) ?></span>
                            </div>
                        </div>

                        <div class="course-card-bottom">
                            <div class="course-meta-stats">
                                <span>Seats: <?= (int)$c['approved_students'] ?> / <?= (int)$c['max_capacity'] ?></span>
                                <span><?= 100 - $pct ?>% Available</span>
                            </div>
                            <div class="capacity-meter">
                                <div class="capacity-fill <?= $pct >= 85 ? 'high' : '' ?>" style="width: <?= $pct ?>%;"></div>
                            </div>

                            <div style="margin-top:1rem;">
                                <?php if ($c['my_status'] === 'APPROVED'): ?>
                                    <button class="btn btn-outline btn-sm" style="width:100%; cursor:default;" disabled>
                                        ✓ Enrolled in Class
                                    </button>
                                <?php elseif ($c['my_status'] === 'PENDING'): ?>
                                    <button class="btn btn-outline btn-sm" style="width:100%; color:var(--status-pending);" disabled>
                                        ⏳ Application Pending
                                    </button>
                                <?php elseif ($isFull): ?>
                                    <button class="btn btn-outline btn-sm" style="width:100%;" disabled>
                                        Capacity Reached
                                    </button>
                                <?php else: ?>
                                    <form action="../api/student_process.php" method="POST">
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
                            <tr><td colspan="6" style="text-align:center; padding:2rem;">No enrollment applications submitted yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($myApplications as $app): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($app['course_code']) ?></strong> &bull; <?= htmlspecialchars($app['course_name']) ?>
                                    </td>
                                    <td>Prof. <?= htmlspecialchars($app['prof_first'] . ' ' . $app['prof_last']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($app['status']) ?>">
                                            <?= htmlspecialchars($app['status']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.8rem;"><?= date('M d, Y', strtotime($app['applied_at'])) ?></td>
                                    <td style="font-size:0.8rem;">
                                        <?= $app['decided_at'] ? date('M d, Y', strtotime($app['decided_at'])) : '<span class="text-muted">Awaiting decision</span>' ?>
                                    </td>
                                    <td>
                                        <form action="../api/student_process.php" method="POST" onsubmit="return confirm('Withdraw or remove this course application?');">
                                            <input type="hidden" name="action" value="drop_course">
                                            <input type="hidden" name="enrollment_id" value="<?= $app['enrollment_id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--coral-dark);">
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

    
    <div class="tab-panel" id="tabAnnouncements">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Campus &amp; Course Announcements Feed</div>
            </div>

            <?php if (empty($announcements)): ?>
                <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                    No announcements available right now. Check back later!
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                    <div class="bulletin-card <?= $ann['course_code'] ? '' : 'global' ?>">
                        <div class="bulletin-header">
                            <div>
                                <span class="bulletin-title"><?= htmlspecialchars($ann['title']) ?></span>
                                <div class="bulletin-meta" style="margin-top:2px;">
                                    Posted by <strong><?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?></strong> 
                                    (<?= htmlspecialchars(ucfirst($ann['role'])) ?>) &bull; <?= date('M d, Y h:i A', strtotime($ann['created_at'])) ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($ann['course_code']): ?>
                                    <span class="role-pill role-professor"><?= htmlspecialchars($ann['course_code']) ?></span>
                                <?php else: ?>
                                    <span class="role-pill role-admin">Campus Bulletin</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bulletin-body">
                            <?= nl2br(htmlspecialchars($ann['message'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                <p class="text-muted" style="font-size:0.875rem;"><?= htmlspecialchars($currentUser['email']) ?></p>
                <div style="margin-top:1rem;">
                    <span class="role-pill role-student">APEX Student</span>
                </div>
                <div style="margin-top:1.5rem; text-align:left; font-size:0.85rem; border-top:1px solid var(--surface-border); padding-top:1rem;">
                    <p style="margin-bottom:0.4rem;"><strong>Student ID:</strong> #<?= $studentId ?></p>
                    <p style="margin-bottom:0.4rem;"><strong>Joined:</strong> <?= date('F j, Y', strtotime($currentUser['created_at'])) ?></p>
                    <p><strong>Status:</strong> Active Scholar</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Edit Profile Information</div>
                </div>

                <form action="../api/student_process.php" method="POST">
                    <input type="hidden" name="action" value="update_profile">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label for="prof_fn">First Name *</label>
                            <input type="text" id="prof_fn" name="first_name" class="form-control" value="<?= htmlspecialchars($currentUser['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="prof_ln">Last Name *</label>
                            <input type="text" id="prof_ln" name="last_name" class="form-control" value="<?= htmlspecialchars($currentUser['last_name']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="prof_email">Institutional Email</label>
                        <input type="email" id="prof_email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled>
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

<?php require_once '../includes/footer.php'; ?>
