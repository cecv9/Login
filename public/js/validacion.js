document.addEventListener('DOMContentLoaded', function () {
    // Validación para el formulario de login
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            let valid = true;
            let errorMsg = '';
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            if (username.length < 4) {
                valid = false;
                errorMsg += 'El usuario debe tener al menos 4 caracteres.<br>';
            }
            if (password.length < 8) {
                valid = false;
                errorMsg += 'La contraseña debe tener al menos 8 caracteres.<br>';
            }
            if (!valid) {
                e.preventDefault();
                document.getElementById('loginErrorMsg').innerHTML = errorMsg;
            }
        });
    }

    // Validación para el formulario de registro
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            let valid = true;
            let errorMsg = '';
            const username = document.getElementById('reg_username').value.trim();
            const email = document.getElementById('reg_email').value.trim();
            const password = document.getElementById('reg_password').value;
            const password2 = document.getElementById('reg_password2').value;

            if (username.length < 4) {
                valid = false;
                errorMsg += 'El usuario debe tener al menos 4 caracteres.<br>';
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                valid = false;
                errorMsg += 'El email no es válido.<br>';
            }
            if (password.length < 8) {
                valid = false;
                errorMsg += 'La contraseña debe tener al menos 8 caracteres.<br>';
            }
            if (password !== password2) {
                valid = false;
                errorMsg += 'Las contraseñas no coinciden.<br>';
            }
            if (!valid) {
                e.preventDefault();
                document.getElementById('registerErrorMsg').innerHTML = errorMsg;
            }
        });
    }
});