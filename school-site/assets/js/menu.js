/*
 * Mobile menu: collapses the navigation behind a button on small screens.
 * Without JavaScript the navigation simply stays visible.
 */
(function () {
    'use strict';

    var header = document.querySelector('.site-header');
    var button = header && header.querySelector('.menu-toggle');

    if (!button) {
        return;
    }

    function setOpen(open) {
        header.setAttribute('data-menu', open ? 'open' : 'closed');
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    button.hidden = false;
    setOpen(false);

    button.addEventListener('click', function () {
        setOpen(button.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            button.focus();
        }
    });
})();
