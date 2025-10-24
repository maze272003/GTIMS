function showpassword() {
    var password = document.getElementById('password');
    var eye = document.getElementById('eye');
    if (password.type === 'password') {
        password.type = 'text';
        eye.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        password.type = 'password';
        eye.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const loginButton = document.getElementById('loginButton');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const errorContainer = document.getElementById('email-error-container') || document.createElement('div');
    let timerElement = null;
    const disableForm = () => {
        if (loginButton) {
            loginButton.disabled = true;
            loginButton.classList.remove('hover:bg-custom-blue-dark', 'hover:shadow-lg', 'hover:-translate-y-0.5', 'cursor-pointer');
            loginButton.classList.add('opacity-50', 'cursor-not-allowed');
        }
        if (emailInput) emailInput.disabled = true;
        if (passwordInput) passwordInput.disabled = true;
    };
    const enableForm = () => {
        if (loginButton) {
            loginButton.disabled = false;
            loginButton.classList.add('hover:bg-custom-blue-dark', 'hover:shadow-lg', 'hover:-translate-y-0.5', 'cursor-pointer');
            loginButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        if (emailInput) emailInput.disabled = false;
        if (passwordInput) passwordInput.disabled = false;
        if (errorContainer) errorContainer.innerHTML = '';
    };
    const handleLockout = () => {
        const lockoutEndTime = localStorage.getItem('lockoutEndTime');
        if (!lockoutEndTime) {
            enableForm();
            return;
        }
        const remainingSeconds = Math.round((lockoutEndTime - Date.now()) / 1000);
        if (remainingSeconds > 0) {
            disableForm();
            if (!timerElement) {
                timerElement = document.createElement('span');
                errorContainer.innerHTML = '';
                errorContainer.appendChild(timerElement);
            }
            timerElement.innerHTML = `Please try again in <strong>${remainingSeconds}</strong> second(s).`;
            setTimeout(handleLockout, 1000);
        } else {
            enableForm();
            localStorage.removeItem('lockoutEndTime');
            if (timerElement) {
                timerElement.remove();
                timerElement = null;
            }
        }
    };
    handleLockout();
});