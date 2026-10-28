
document.addEventListener('DOMContentLoaded', () => {
    const demoChips = document.querySelectorAll('.demo-chip');
    const emailInput = document.getElementById('email');
    const passInput = document.getElementById('password');

    demoChips.forEach(chip => {
        chip.addEventListener('click', () => {
            const email = chip.getAttribute('data-email');
            const pass = chip.getAttribute('data-pass');
            if (emailInput && passInput) {
                emailInput.value = email;
                passInput.value = pass;
                
                emailInput.style.borderColor = 'var(--teal)';
                passInput.style.borderColor = 'var(--teal)';
                setTimeout(() => {
                    emailInput.style.borderColor = '';
                    passInput.style.borderColor = '';
                }, 1000);
            }
        });
    });

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', (e) => {
            const pass = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;

            if (pass !== confirm) {
                e.preventDefault();
                alert('Passwords do not match. Please verify both password fields.');
            }
        });
    }
});

