// Small client-side helpers. Basic checks (required, email, min/max) use HTML5 attributes.
document.addEventListener('DOMContentLoaded', function () {

    // 1. Mobile menu button shows/hides the navigation
    var menuButton = document.getElementById('menuToggle');
    var nav = document.getElementById('mainNav');
    if (menuButton && nav) {
        menuButton.addEventListener('click', function () {
            nav.classList.toggle('open');
        });
    }

    // 2. Register page: blood type is only needed for patients and donors
    var role = document.getElementById('role');
    var bloodBox = document.getElementById('bloodTypeBox');
    var bloodSelect = document.getElementById('blood_type');
    if (role && bloodBox && bloodSelect) {
        var toggleBloodType = function () {
            var needed = (role.value === 'patient' || role.value === 'donor');
            bloodBox.classList.toggle('hidden', !needed);
            bloodSelect.required = needed;
        };
        role.addEventListener('change', toggleBloodType);
        toggleBloodType();
    }

    // 3. Register page: the two passwords must match
    var password = document.getElementById('password');
    var confirmBox = document.getElementById('confirm_password');
    if (password && confirmBox) {
        var checkMatch = function () {
            confirmBox.setCustomValidity(confirmBox.value !== password.value ? 'Passwords do not match.' : '');
        };
        password.addEventListener('input', checkMatch);
        confirmBox.addEventListener('input', checkMatch);
    }

    // 4. Forms with data-confirm ask "Are you sure?" before they are sent
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) { event.preventDefault(); }
        });
    });
});
