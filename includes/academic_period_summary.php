<?php if (!empty($academicPeriod)): ?>
    <p class="academic-period-line">
        Academic Year <?= htmlspecialchars($academicPeriod['academic_year'], ENT_QUOTES, 'UTF-8') ?>
        | <?= htmlspecialchars($academicPeriod['semester_label'], ENT_QUOTES, 'UTF-8') ?>
        <?php if (!empty($academicReadOnly)): ?>
            | Historical — Read-only
        <?php endif; ?>
    </p>
<?php endif; ?>
