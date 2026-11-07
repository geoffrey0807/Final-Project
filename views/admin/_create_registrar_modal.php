<?php
$registrarModalYear = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
?>
<div class="modal-backdrop<?= !empty($openCreateRegistrarModal) ? ' active' : '' ?>" id="createRegistrarModal">
    <div class="modal-dialog">
        <form action="../../api/admin_accounts_process.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create_registrar">
            <div class="modal-header">
                <h2 class="modal-title">Create Registrar Account</h2>
                <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
            </div>
            <div class="modal-body account-editor-grid">
                <section class="account-editor-column" aria-label="Registrar personal details">
                    <p class="text-muted">A maximum of two registrar accounts can be active at once. Disabled and archived registrars do not count toward this limit.</p>
                    <div class="account-editor-name-fields">
                        <div class="form-group">
                            <label for="registrarFirstName">First Name <span class="required-marker" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="registrarFirstName" name="first_name"
                                placeholder="e.g. Juan" maxlength="50" autocomplete="given-name" required>
                        </div>
                        <div class="form-group">
                            <label for="registrarMiddleName">Middle Name</label>
                            <input type="text" class="form-control" id="registrarMiddleName" name="middle_name"
                                placeholder="Optional" maxlength="50" autocomplete="additional-name">
                        </div>
                        <div class="form-group">
                            <label for="registrarLastName">Last Name <span class="required-marker" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="registrarLastName" name="last_name"
                                placeholder="e.g. Dela Cruz" maxlength="50" autocomplete="family-name" required>
                        </div>
                        <div class="form-group">
                            <label for="registrarSuffix">Suffix</label>
                            <input type="text" id="registrarSuffix" name="suffix" class="form-control"
                                placeholder="Optional (e.g. Jr.)" maxlength="20" autocomplete="honorific-suffix">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registrarIdNumber">ID Number <span class="required-marker" aria-hidden="true">*</span></label>
                        <input type="text" id="registrarIdNumber" name="id_number" class="form-control"
                            placeholder="REG-<?= htmlspecialchars($registrarModalYear, ENT_QUOTES, 'UTF-8') ?>-0001"
                            maxlength="13" pattern="[Rr][Ee][Gg]-<?= htmlspecialchars($registrarModalYear, ENT_QUOTES, 'UTF-8') ?>-[0-9]{4}"
                            title="Enter REG-<?= htmlspecialchars($registrarModalYear, ENT_QUOTES, 'UTF-8') ?>- followed by four digits."
                            autocomplete="off" required>
                        <small class="text-muted">Format: REG-<?= htmlspecialchars($registrarModalYear, ENT_QUOTES, 'UTF-8') ?>-####</small>
                    </div>
                </section>
                <section class="account-editor-column" aria-label="Registrar account access">
                    <div class="form-group">
                        <label for="registrarEmail">APEX Email <span class="required-marker" aria-hidden="true">*</span></label>
                        <input type="email" id="registrarEmail" name="email" class="form-control"
                            placeholder="user@apex.edu.ph" maxlength="100" autocomplete="email"
                            pattern="[A-Za-z0-9._%+\-]+@apex\.edu\.ph" title="Use an APEX Email ending in @apex.edu.ph."
                            required>
                    </div>
                    <div class="form-group">
                        <label for="registrarPassword">Initial Password <span class="required-marker" aria-hidden="true">*</span></label>
                        <input type="password" id="registrarPassword" name="password" class="form-control"
                            placeholder="8+ characters, upper/lowercase, number, symbol" minlength="8" maxlength="72"
                            pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}"
                            title="Use at least 8 characters with an uppercase letter, a lowercase letter, a number, and a special character."
                            autocomplete="new-password" required>
                        <small class="text-muted">Use at least 8 characters, including uppercase and lowercase letters, a
                            number, and a special character.</small>
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Add User Account</button>
            </div>
        </form>
    </div>
</div>
