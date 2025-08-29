// auth.js - Fungsi toggle password untuk login dan register

function togglePasswordVisibility(inputId, eyeIconId) {
    const passwordInput = document.getElementById(inputId);
    const eyeIcon = document.getElementById(eyeIconId);

    if (!passwordInput || !eyeIcon) return;

    const type = passwordInput.type === 'password' ? 'text' : 'password';
    passwordInput.type = type;

    if (type === 'text') {
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95m1.414-1.414A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.956 9.956 0 01-4.043 5.197M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 3l18 18" />
        `;
    } else {
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        `;
    }
}

// Auto-initialize based on page
document.addEventListener('DOMContentLoaded', function() {
    // Login page
    const loginToggle = document.getElementById('togglePassword');
    if (loginToggle) {
        loginToggle.addEventListener('click', function() {
            togglePasswordVisibility('password', 'eyeIcon');
        });
    }

    // Register page
    const regToggle = document.getElementById('togglePassword');
    const regConfirmToggle = document.getElementById('toggleConfirmPassword');

    if (regToggle) {
        regToggle.addEventListener('click', function() {
            togglePasswordVisibility('password', 'eyeIconPassword');
        });
    }

    if (regConfirmToggle) {
        regConfirmToggle.addEventListener('click', function() {
            togglePasswordVisibility('confirm_password', 'eyeIconConfirmPassword');
        });
    }
});