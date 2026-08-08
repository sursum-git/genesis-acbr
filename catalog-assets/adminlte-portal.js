(function () {
    'use strict';

    function clearSidebarOverlay() {
        document.querySelectorAll('.sidebar-overlay').forEach(function (overlay) {
            overlay.remove();
        });

        document.body.classList.remove('sidebar-open');
    }

    document.addEventListener('click', function (event) {
        const sidebarLink = event.target.closest('.app-sidebar .nav-link[href]');
        if (!sidebarLink) {
            return;
        }

        const href = sidebarLink.getAttribute('href') || '';
        if (href === '' || href === '#') {
            return;
        }

        clearSidebarOverlay();

        if (
            !event.defaultPrevented
            && sidebarLink.target === ''
            && !event.metaKey
            && !event.ctrlKey
            && !event.shiftKey
            && !event.altKey
        ) {
            event.preventDefault();
            window.location.assign(sidebarLink.href);
        }
    }, true);

    document.addEventListener('click', function (event) {
        const sidebarToggle = event.target.closest('[data-lte-toggle="sidebar"]');
        if (!sidebarToggle) {
            return;
        }

        window.setTimeout(function () {
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.addEventListener('click', clearSidebarOverlay, { once: true });
            }
        }, 0);
    });
}());
