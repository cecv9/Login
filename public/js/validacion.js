document.addEventListener('DOMContentLoaded', function () {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm) {
        return;
    }

    // Validación para el formulario de registro
    const passwordField = registerForm.querySelector('#password');
    const confirmField = registerForm.querySelector('#confirm_password');


    if (!passwordField || !confirmField) {
        return;
    }

    const clearCustomValidity = () => {
        confirmField.setCustomValidity('');
    };

    passwordField.addEventListener('input', clearCustomValidity);
    confirmField.addEventListener('input', clearCustomValidity);

    registerForm.addEventListener('submit', (event) => {
        if (passwordField.value !== confirmField.value) {
            event.preventDefault();
            confirmField.setCustomValidity('Las contraseñas no coinciden.');
            confirmField.reportValidity();
        }
    });
});