<?php
require_once '../includes/session_check.php';
checkAccess(['professor'], '../auth/login.php');
require_once '../config/db.php';

$pageTitle = 'Faculty Dashboard | APEX Learning Hub';
$extraCss  = '../css/admin.css';
$extraJs   = '../js/admin.js';

$profId = (int)$_SESSION['user_id'];
$msg    = $_GET['msg'] ?? '';
$error  = $_GET['error'] ?? '';

$courseStmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'APPROVED') AS approved_students,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'PENDING') AS pending_students
    FROM courses c
    WHERE c.professor_id = :pid
    ORDER BY c.course_code ASC
");
$courseStmt->execute(['pid' => $profId]);
$myCourses = $courseStmt->fetchAll();

$pendingStmt = $pdo->prepare("
    SELECT e.id AS enrollment_id, e.applied_at,
           u.first_name, u.last_name, u.email,
           c.id AS course_id, c.course_code, c.course_name
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE c.professor_id = :pid AND e.status = 'PENDING'
    ORDER BY e.applied_at ASC
");
$pendingStmt->execute(['pid' => $profId]);
$pendingRequests = $pendingStmt->fetchAll();

$rosterStmt = $pdo->prepare("
    SELECT e.id AS enrollment_id, e.decided_at,
           u.id AS student_id, u.first_name, u.last_name, u.email,
           c.course_code, c.course_name
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE c.professor_id = :pid AND e.status = 'APPROVED'
    ORDER BY c.course_code ASC, u.last_name ASC
");
$rosterStmt->execute(['pid' => $profId]);
$approvedRoster = $rosterStmt->fetchAll();

$annStmt = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_name
    FROM announcements a
    JOIN courses c ON a.course_id = c.id
    WHERE a.author_id = :pid
    ORDER BY a.created_at DESC
");
$annStmt->execute(['pid' => $profId]);
$myAnnouncements = $annStmt->fetchAll();

$totalAssignedCourses = count($myCourses);
$totalPendingCount    = count($pendingRequests);
$totalEnrolledStudents = count($approvedRoster);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <div class="dashboard-title">
            <h1>Faculty Workspace</h1>
            <p>Prof. <?= htmlspecialchars($_SESSION['user_name']) ?> &bull; Course Leadership &amp; Approvals</p>
        </div>
        <div>
            <button type="button" class="btn btn-coral" data-open-modal="courseAnnModal">
                + Post Class Announcement
            </button>
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
            <div class="metric-icon navy">📚</div>
            <div class="metric-data">
                <h3><?= $totalAssignedCourses ?></h3>
                <p>Assigned Courses</p>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon teal">🎓</div>
            <div class="metric-data">
                <h3><?= $totalEnrolledStudents ?></h3>
                <p>Enrolled Students</p>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon coral">⏳</div>
            <div class="metric-data">
                <h3><?= $totalPendingCount ?></h3>
                <p>Awaiting Approval</p>
            </div>
        </div>
    </div>

    
    <div class="tab-navigation">
        <button type="button" class="tab-btn active" data-target="tabRequests">
            Pending Applications <span class="tab-badge"><?= $totalPendingCount ?></span>
        </button>
        <button type="button" class="tab-btn" data-target="tabCourses">
            My Courses &amp; Capacity <span class="tab-badge"><?= $totalAssignedCourses ?></span>
        </button>
        <button type="button" class="tab-btn" data-target="tabRoster">
            Class Student Rosters <span class="tab-badge"><?= $totalEnrolledStudents ?></span>
        </button>
        <button type="button" class="tab-btn" data-target="tabAnnouncements">
            Course Bulletins <span class="tab-badge"><?= count($myAnnouncements) ?></span>
        </button>
    </div>

    
    <div class="tab-panel active" id="tabRequests">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Pending Course Admission Requests</div>
                <span class="text-muted" style="font-size:0.85rem;">Review and decide on student applications</span>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Target Course</th>
                            <th>Contact Email</th>
                            <th>Applied At</th>
                            <th>Approval Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingRequests)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:2.5rem; color:var(--text-muted);">
                                    🎉 Great! There are no pending student applications requiring your approval at this time.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingRequests as $req): ?>
                                <tr>
                                    <td>
                                        <div class="user-info-cell">
                                            <div class="avatar-mini" style="background:var(--teal-light); color:var(--teal-dark);">
                                                <?= strtoupper(substr($req['first_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($req['course_code']) ?></strong> &bull; <?= htmlspecialchars($req['course_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($req['email']) ?></td>
                                    <td style="font-size:0.8rem;"><?= date('M d, Y h:i A', strtotime($req['applied_at'])) ?></td>
                                    <td>
                                        <form action="../api/professor_process.php" method="POST" style="display:inline-flex; gap:0.4rem;">
                                            <input type="hidden" name="enrollment_id" value="<?= $req['enrollment_id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                ✓ Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                                ✕ Reject
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

    
    <div class="tab-panel" id="tabCourses">
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
                    <div class="course-roster-card">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                                <span class="role-pill role-admin" style="font-size:0.8rem;"><?= htmlspecialchars($c['course_code']) ?></span>
                                <span class="badge <?= $c['pending_students'] > 0 ? 'badge-pending' : 'badge-approved' ?>">
                                    <?= $c['pending_students'] ?> Pending
                                </span>
                            </div>
                            <h3 style="font-size:1.15rem; color:var(--navy); margin-bottom:0.5rem;">
                                <?= htmlspecialchars($c['course_name']) ?>
                            </h3>
                            <div class="capacity-meter">
                                <div class="capacity-fill <?= $pct >= 85 ? 'high' : '' ?>" style="width: <?= $pct ?>%;"></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:0.85rem; color:var(--text-muted);">
                                <span><?= (int)$c['approved_students'] ?> Enrolled</span>
                                <span>Max <?= (int)$c['max_capacity'] ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="tab-panel" id="tabRoster">
        <div class="card">
            <div class="table-toolbar">
                <div class="search-box">
                    <input type="text" placeholder="Search student roster..." data-table-search="rosterTable">
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table" id="rosterTable">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Enrolled Course</th>
                            <th>Email Address</th>
                            <th>Enrolled Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($approvedRoster)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:2rem;">No students enrolled yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($approvedRoster as $r): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="role-pill role-professor"><?= htmlspecialchars($r['course_code']) ?></span>
                                        <?= htmlspecialchars($r['course_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($r['email']) ?></td>
                                    <td style="font-size:0.8rem;">
                                        <?= $r['decided_at'] ? date('M d, Y', strtotime($r['decided_at'])) : '—' ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-approved">Enrolled</span>
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
            <div class="table-toolbar">
                <div class="card-title" style="margin:0;">My Class Announcements</div>
                <button class="btn btn-coral btn-sm" data-open-modal="courseAnnModal">+ New Announcement</button>
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
                            <tr><td colspan="4" style="text-align:center; padding:2rem;">You haven't posted any announcements yet.</td></tr>
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
                                        <span class="role-pill role-professor"><?= htmlspecialchars($ann['course_code']) ?></span>
                                    </td>
                                    <td style="font-size:0.8rem;"><?= date('M d, Y h:i A', strtotime($ann['created_at'])) ?></td>
                                    <td>
                                        <form action="../api/professor_process.php" method="POST" onsubmit="return confirm('Delete this announcement?');">
                                            <input type="hidden" name="action" value="delete_announcement">
                                            <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--coral-dark);">Delete</button>
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
</div>


<div class="modal-backdrop" id="courseAnnModal">
    <div class="modal-dialog">
        <form action="../api/professor_process.php" method="POST">
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
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code']) ?>: <?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ann_title">Announcement Title *</label>
                    <input type="text" id="ann_title" name="title" class="form-control" placeholder="e.g. Reading Assignment &amp; Quiz Notice" required>
                </div>
                <div class="form-group">
                    <label for="ann_msg">Announcement Details *</label>
                    <textarea id="ann_msg" name="message" class="form-control" rows="4" placeholder="Enter message for your enrolled students..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-coral">Post to Class</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
