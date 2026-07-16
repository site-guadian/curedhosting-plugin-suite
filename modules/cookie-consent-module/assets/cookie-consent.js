document.addEventListener('DOMContentLoaded', function () {
    const banner = document.getElementById('chcc-banner');
    if (!banner) {
        return;
    }

    const buttons = banner.querySelectorAll('a');
    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            banner.style.display = 'none';
        });
    });
});
