// login.js — Scripts for the login page

// Toggle password visibility when the eye icon is clicked
document.getElementById('togglePwd').addEventListener('click', function () {
    var pwd  = document.getElementById('password');
    var icon = document.getElementById('toggleIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});
