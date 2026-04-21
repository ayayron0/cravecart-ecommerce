// admin.js — Scripts shared across all admin pages

// Auto-dismiss the profile update success alert after 5 seconds
var profileAlert = document.getElementById('profile-alert');
if (profileAlert) {
    setTimeout(function () {
        profileAlert.classList.remove('show');
        setTimeout(function () { profileAlert.remove(); }, 300);
    }, 5000);
}
