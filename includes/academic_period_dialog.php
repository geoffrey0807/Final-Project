<dialog class="student-fiscal-year-dialog" id="studentFiscalYearDialog" aria-labelledby="studentFiscalYearTitle">
    <form id="studentFiscalYearForm">
        <div class="student-fiscal-year-heading">
            <span class="student-eyebrow"><?= htmlspecialchars($activeUserRoleLabel ?? 'Portal', ENT_QUOTES, 'UTF-8') ?>
                portal</span>
            <button type="button" class="student-fiscal-year-close" data-fiscal-year-close
                aria-label="Close dialog">&times;</button>
        </div>
        <h2 id="studentFiscalYearTitle">Set an academic year</h2>
        <p><?= ($activeUserRole ?? '') === 'registrar'
            ? 'Set the active academic year for the whole portal. New records will use it until you select another period.'
            : 'Choose the academic year and semester you want to view.' ?></p>
        <label for="studentAcademicYear">Academic year</label>
        <select id="studentAcademicYear" name="academicYear" required>
            <option value="">Select an academic year</option>
            <?php foreach (academicYearStartOptions() as $year): ?>
                <option value="<?= $year ?>-<?= $year + 1 ?>">AY <?= $year ?>–<?= $year + 1 ?></option>
            <?php endforeach; ?>
        </select>
        <label for="studentSemester">Semester</label>
        <select id="studentSemester" name="semester" required>
            <option value="">Select a semester</option>
            <option value="first">First Semester</option>
            <option value="second">Second Semester</option>
        </select>
        <p class="student-fiscal-year-status" id="studentFiscalYearStatus" aria-live="polite"></p>
        <div class="student-fiscal-year-actions">
            <button type="button" class="btn btn-outline" data-academic-clear>Current period</button>
            <button type="button" class="btn btn-outline" data-fiscal-year-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Save selection</button>
        </div>
    </form>
</dialog>
<dialog class="student-fiscal-year-confirm" id="studentFiscalYearConfirm"
    aria-labelledby="studentFiscalYearConfirmTitle" aria-describedby="studentFiscalYearConfirmDescription">
    <div class="student-fiscal-year-confirm-content">
        <h2 id="studentFiscalYearConfirmTitle">Save this period selection?</h2>
        <p id="studentFiscalYearConfirmDescription">The selected academic year and semester will be applied.</p>
        <div class="student-fiscal-year-confirm-actions">
            <button type="button" class="btn btn-outline" data-fiscal-year-save-cancel>No</button>
            <button type="button" class="btn btn-primary" data-fiscal-year-save-confirm>Yes, save</button>
        </div>
    </div>
</dialog>