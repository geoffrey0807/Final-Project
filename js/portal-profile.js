/**
 * APEX Learning Hub - Portal Profile Scripts
 */

document.addEventListener('DOMContentLoaded', () => {
    // -------------------------------------------------------------
    // 1. Profile Photo Selection & Live Image Preview
    // -------------------------------------------------------------
    const photoInput = document.querySelector('[data-profile-photo-input]');
    const avatarPreviewWrap = document.querySelector('[data-avatar-preview-wrap]');
    const previewImg = document.querySelector('[data-profile-photo-preview]');
    const photoFallbackSvg = document.querySelector('[data-profile-photo-fallback]');
    const revertBtn = document.querySelector('[data-profile-photo-revert]');
    const photoStatus = document.querySelector('[data-photo-status]');
    const removePhotoBtn = document.querySelector('[data-profile-photo-remove]');

    removePhotoBtn?.addEventListener('click', (event) => {
        if (!window.confirm('Remove your current profile photo?')) {
            event.preventDefault();
        }
    });

    // Remember initial state
    const originalPhotoUrl = previewImg ? previewImg.getAttribute('src') || '' : '';
    const hadOriginalPhoto = originalPhotoUrl !== '';

    if (photoInput) {
        // Trigger file input when clicking avatar directly
        if (avatarPreviewWrap) {
            avatarPreviewWrap.addEventListener('click', () => {
                photoInput.click();
            });
            avatarPreviewWrap.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    photoInput.click();
                }
            });
        }

        // When user selects a file from their folders
        photoInput.addEventListener('change', () => {
            const file = photoInput.files && photoInput.files[0];
            if (!file) {
                return;
            }

            // Client-side validation: Type
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('Please choose a valid JPG, PNG, or WebP image.');
                resetPhotoSelection();
                return;
            }

            // Client-side validation: Size (max 5 MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('Please choose an image smaller than 5 MB.');
                resetPhotoSelection();
                return;
            }

            // Put image into the system image preview (only the selected image will be shown)
            const reader = new FileReader();
            reader.onload = (e) => {
                const dataUrl = e.target.result;
                if (previewImg) {
                    previewImg.src = dataUrl;
                    previewImg.hidden = false;
                    previewImg.removeAttribute('hidden');
                }
                if (photoFallbackSvg) {
                    photoFallbackSvg.hidden = true;
                    photoFallbackSvg.setAttribute('hidden', 'hidden');
                }
                if (photoStatus) {
                    const sizeKb = Math.round(file.size / 1024);
                    photoStatus.textContent = `Selected: ${file.name} (${sizeKb} KB)`;
                    photoStatus.hidden = false;
                    photoStatus.removeAttribute('hidden');
                }
                if (revertBtn) {
                    revertBtn.hidden = false;
                    revertBtn.removeAttribute('hidden');
                }
            };
            reader.readAsDataURL(file);
        });

        // Revert photo selection
        function resetPhotoSelection() {
            photoInput.value = '';
            if (hadOriginalPhoto) {
                if (previewImg) {
                    previewImg.src = originalPhotoUrl;
                    previewImg.hidden = false;
                    previewImg.removeAttribute('hidden');
                }
                if (photoFallbackSvg) {
                    photoFallbackSvg.hidden = true;
                    photoFallbackSvg.setAttribute('hidden', 'hidden');
                }
            } else {
                if (previewImg) {
                    previewImg.src = '';
                    previewImg.hidden = true;
                    previewImg.setAttribute('hidden', 'hidden');
                }
                if (photoFallbackSvg) {
                    photoFallbackSvg.hidden = false;
                    photoFallbackSvg.removeAttribute('hidden');
                }
            }
            if (photoStatus) {
                photoStatus.textContent = '';
                photoStatus.hidden = true;
                photoStatus.setAttribute('hidden', 'hidden');
            }
            if (revertBtn) {
                revertBtn.hidden = true;
                revertBtn.setAttribute('hidden', 'hidden');
            }
        }

        if (revertBtn) {
            revertBtn.addEventListener('click', (e) => {
                e.preventDefault();
                resetPhotoSelection();
            });
        }
    }

    // -------------------------------------------------------------
    // 2. Password Visibility Toggles
    // -------------------------------------------------------------
    const eyeOpenSvg = `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
    const eyeClosedSvg = `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;

    document.querySelectorAll('[data-password-toggle]').forEach((toggleBtn) => {
        toggleBtn.addEventListener('click', () => {
            const targetId = toggleBtn.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            if (!targetInput) return;

            const isPassword = targetInput.type === 'password';
            targetInput.type = isPassword ? 'text' : 'password';
            toggleBtn.innerHTML = isPassword ? eyeClosedSvg : eyeOpenSvg;
            toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            toggleBtn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
        });
    });

    // -------------------------------------------------------------
    // 3. Password Validation Checklist & Match Verification
    // -------------------------------------------------------------
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmNewPassword');
    const matchIndicator = document.getElementById('passwordMatchIndicator');
    const matchText = document.getElementById('passwordMatchText');

    const reqLength = document.getElementById('reqLength');
    const reqUppercase = document.getElementById('reqUppercase');
    const reqLowercase = document.getElementById('reqLowercase');
    const reqNumber = document.getElementById('reqNumber');
    const reqSpecial = document.getElementById('reqSpecial');

    function checkPasswordRequirements(password) {
        const hasLength = password.length >= 8 && password.length <= 72;
        const hasUpper = /[A-Z]/.test(password); // Title case / uppercase letter
        const hasLower = /[a-z]/.test(password); // Lowercase letter
        const hasNum = /[0-9]/.test(password);   // Alphanumeric number
        const hasSpecial = /[^A-Za-z0-9\s]/.test(password); // Special character

        if (reqLength) reqLength.classList.toggle('valid', hasLength);
        if (reqUppercase) reqUppercase.classList.toggle('valid', hasUpper);
        if (reqLowercase) reqLowercase.classList.toggle('valid', hasLower);
        if (reqNumber) reqNumber.classList.toggle('valid', hasNum);
        if (reqSpecial) reqSpecial.classList.toggle('valid', hasSpecial);

        return hasLength && hasUpper && hasLower && hasNum && hasSpecial;
    }

    function checkPasswordMatch() {
        if (!confirmPasswordInput || !newPasswordInput || !matchIndicator || !matchText) return;

        const p1 = newPasswordInput.value;
        const p2 = confirmPasswordInput.value;

        if (p2.length === 0) {
            matchIndicator.className = 'portal-match-indicator';
            matchText.textContent = '';
            return;
        }

        if (p1 === p2) {
            matchIndicator.className = 'portal-match-indicator match';
            matchText.textContent = '✓ Passwords match';
        } else {
            matchIndicator.className = 'portal-match-indicator mismatch';
            matchText.textContent = '✕ Passwords do not match';
        }
    }

    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', () => {
            checkPasswordRequirements(newPasswordInput.value);
            if (confirmPasswordInput && confirmPasswordInput.value.length > 0) {
                checkPasswordMatch();
            }
        });
    }

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }

    // -------------------------------------------------------------
    // 4. Form Validation on Submit
    // -------------------------------------------------------------
    const passwordForm = document.getElementById('portalProfilePasswordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', (e) => {
            const currentPass = document.getElementById('currentPassword')?.value || '';
            const newPass = newPasswordInput?.value || '';
            const confirmPass = confirmPasswordInput?.value || '';

            if (!currentPass) {
                e.preventDefault();
                alert('Please enter your current password.');
                document.getElementById('currentPassword')?.focus();
                return;
            }

            if (!checkPasswordRequirements(newPass)) {
                e.preventDefault();
                alert('Your new password must be at least 8 characters long and include an uppercase letter (title case), a lowercase letter, a number, and a special character.');
                newPasswordInput?.focus();
                return;
            }

            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Your new password and confirmation password do not match.');
                confirmPasswordInput?.focus();
                return;
            }
        });
    }

    const infoForm = document.getElementById('portalProfileInfoForm');
    if (infoForm) {
        infoForm.addEventListener('submit', (e) => {
            const firstName = document.getElementById('profileFirstName')?.value.trim() || '';
            const lastName = document.getElementById('profileLastName')?.value.trim() || '';
            const email = document.getElementById('profileEmail')?.value.trim() || '';

            if (!firstName || !lastName) {
                e.preventDefault();
                alert('Please enter both your first and last name.');
                return;
            }

            if (!email || !email.endsWith('@apex.edu.ph')) {
                e.preventDefault();
                alert('Please enter a valid official APEX email ending in @apex.edu.ph.');
                return;
            }
        });
    }
});
