// App-wide JavaScript utilities
document.addEventListener('DOMContentLoaded', function () {
    // Auto-hide alerts after 5s
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });
});
