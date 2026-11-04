<?php
$pageTitle = 'Settings | APEX Learning Hub';
$extraCss = [
    '../../css/admin.css?v=202611022300',
    '../../css/student.css?v=202611041145',
    '../../css/portal-settings.css?v=202611022305',
];
$extraJs = [
    '../../js/student.js?v=202611022300',
];
$bodyClass = 'admin-dashboard student-dashboard ' . $portalSettingsRole . '-portal';
$useVisualTheme = true;
$hideSharedSearch = true;
require __DIR__ . '/header.php';
?>
<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require __DIR__ . '/' . $portalSettingsRole . '_sidebar.php'; ?>
    <div class="student-dashboard-content container">
        <section class="dashboard-header admin-portal-heading portal-settings-header">
            <div class="dashboard-title">
                <h1>Settings</h1>
                <p>Appearance and language preferences are currently unavailable.</p>
            </div>
        </section>

        <section class="portal-settings-card" aria-labelledby="appearanceHeading">
            <div class="portal-settings-section">
                <h2 id="appearanceHeading">Appearance</h2>
                <p>Theme options are not available.</p>
                <label for="portalThemeSetting">Appearance</label>
                <select id="portalThemeSetting" class="form-control" disabled aria-disabled="true">
                    <option value="system">System</option>
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
            </div>
            <div class="portal-settings-section">
                <h2 id="languageHeading">Language</h2>
                <p>Language options are not available.</p>
                <label for="portalLanguageSetting">Language</label>
                <select id="portalLanguageSetting" class="form-control" disabled aria-disabled="true">
                    <option value="en">English</option>
                    <option value="fil">Filipino</option>
                </select>
            </div>
        </section>
    </div>
</div>
<button type="button" class="student-sidebar-backdrop" aria-label="Close navigation"></button>
<button type="button" class="student-mobile-toggle" aria-label="Open navigation" aria-controls="studentSidebar"
    aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 6h16M4 12h16M4 18h16"></path>
    </svg></button>
<?php require __DIR__ . '/footer.php'; ?>
