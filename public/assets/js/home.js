// home.js — Scripts for the home page

// Auto-dismiss the logout success alert after 5 seconds
var logoutAlert = document.getElementById('logout-alert');
if (logoutAlert) {
    setTimeout(function () {
        logoutAlert.classList.remove('show');
        setTimeout(function () { logoutAlert.remove(); }, 300);
    }, 5000);
}
