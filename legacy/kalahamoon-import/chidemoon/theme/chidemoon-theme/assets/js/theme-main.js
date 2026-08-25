/**
 * Chidemoon public shell interactions.
 */
document.addEventListener('DOMContentLoaded', function () {
    function updateCompareBadge() {
        const badges = document.querySelectorAll('.chidemoon-compare-badge');
        if (badges.length === 0) return;

        let count = 0;
        try {
            const stored = localStorage.getItem('kalahamoon_compare');
            const items = stored ? JSON.parse(stored) : [];
            count = Array.isArray(items) ? Math.min(items.length, 4) : 0;
        } catch (error) {
            count = 0;
        }

        badges.forEach((badge) => {
            badge.hidden = count === 0;
            if (count > 0) {
                badge.textContent = String(count).replace(/[0-9]/g, (digit) => String.fromCharCode(0x06F0 + Number(digit)));
            }
        });
    }

    updateCompareBadge();
    window.addEventListener('storage', (event) => {
        if (event.key === 'kalahamoon_compare') updateCompareBadge();
    });
    window.addEventListener('kalahamoon_compare_updated', () => {
        updateCompareBadge();
    });

    const mobileNavItems = document.querySelectorAll('.chidemoon-mobile-nav-item');
    function updateMobileNavActiveState() {
        const path = window.location.pathname.replace(/\/+$/, '') || '/';
        mobileNavItems.forEach((item) => item.classList.remove('active'));

        const routeMap = [
            ['/magazine', 'chidemoon-mob-nav-magazine'],
            ['/compare', 'chidemoon-mob-nav-compare'],
            ['/shop', 'chidemoon-mob-nav-shop'],
        ];
        const matched = routeMap.find(([route]) => path === route || path.startsWith(route + '/'));
        const activeId = matched ? matched[1] : (path === '/' ? 'chidemoon-mob-nav-home' : '');
        if (activeId) document.getElementById(activeId)?.classList.add('active');
    }
    updateMobileNavActiveState();

    const menuShell = document.querySelector('.chidemoon-mobile-menu-shell');
    let closeMobileMenu = () => {};
    if (menuShell) {
        const toggle = menuShell.querySelector('.chidemoon-mobile-menu-toggle');
        const drawer = menuShell.querySelector('.chidemoon-mobile-drawer');
        const backdrop = menuShell.querySelector('.chidemoon-mobile-menu-backdrop');
        const closeButton = menuShell.querySelector('.chidemoon-mobile-menu-close');
        let lastFocus = null;

        closeMobileMenu = () => {
            if (!drawer || drawer.hidden) return;
            drawer.hidden = true;
            if (backdrop) backdrop.hidden = true;
            toggle?.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('chidemoon-menu-open');
            if (lastFocus instanceof HTMLElement) lastFocus.focus();
        };

        const openMobileMenu = () => {
            if (!drawer) return;
            lastFocus = document.activeElement;
            drawer.hidden = false;
            if (backdrop) backdrop.hidden = false;
            toggle?.setAttribute('aria-expanded', 'true');
            document.body.classList.add('chidemoon-menu-open');
            drawer.focus();
        };

        toggle?.addEventListener('click', openMobileMenu);
        closeButton?.addEventListener('click', closeMobileMenu);
        backdrop?.addEventListener('click', closeMobileMenu);
        drawer?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMobileMenu));

        document.addEventListener('keydown', (event) => {
            if (!drawer || drawer.hidden) return;
            if (event.key === 'Escape') {
                closeMobileMenu();
                return;
            }
            if (event.key !== 'Tab') return;
            const focusable = Array.from(drawer.querySelectorAll('a, button, input, [tabindex]:not([tabindex="-1"])'))
                .filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

});
