/*
 * Gallery lightbox. The gallery is a list of links to the large images; this
 * script opens them in a modal <dialog> instead.
 *
 * Keyboard: Left/Right arrows switch images, Escape closes, Tab stays inside
 * the dialog (native showModal), and focus returns to the thumbnail.
 */
(function () {
    'use strict';

    var grid = document.querySelector('[data-gallery]');

    if (!grid || typeof HTMLDialogElement !== 'function') {
        return;
    }

    var links = Array.prototype.slice.call(grid.querySelectorAll('[data-gallery-item]'));
    var current = 0;
    var opener = null;

    var dialog = document.createElement('dialog');
    dialog.className = 'lightbox';
    dialog.setAttribute('aria-label', 'Galerija');
    dialog.innerHTML =
        '<div class="lightbox__inner">' +
            '<div class="lightbox__bar">' +
                '<p class="lightbox__counter" aria-live="polite"></p>' +
                '<button type="button" data-action="close">Zatvori<span class="visually-hidden"> galeriju</span></button>' +
            '</div>' +
            '<div class="lightbox__stage">' +
                '<button type="button" data-action="prev" aria-label="Prethodna slika">‹</button>' +
                '<img class="lightbox__image" alt="">' +
                '<button type="button" data-action="next" aria-label="Sljedeća slika">›</button>' +
            '</div>' +
            '<p class="lightbox__caption"></p>' +
        '</div>';
    document.body.appendChild(dialog);

    var image = dialog.querySelector('.lightbox__image');
    var counter = dialog.querySelector('.lightbox__counter');
    var caption = dialog.querySelector('.lightbox__caption');
    var prev = dialog.querySelector('[data-action="prev"]');
    var next = dialog.querySelector('[data-action="next"]');

    if (links.length < 2) {
        prev.hidden = true;
        next.hidden = true;
    }

    function show(index) {
        current = (index + links.length) % links.length;
        var link = links[current];

        image.src = link.href;
        image.alt = link.getAttribute('data-alt') || '';
        caption.textContent = link.getAttribute('data-caption') || '';
        counter.textContent = 'Slika ' + (current + 1) + ' od ' + links.length;
    }

    function open(index, link) {
        opener = link;
        show(index);
        dialog.showModal();
        dialog.querySelector('[data-action="close"]').focus();
    }

    links.forEach(function (link, index) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            open(index, link);
        });
    });

    dialog.addEventListener('click', function (event) {
        var action = event.target.closest('[data-action]');

        if (action) {
            var name = action.getAttribute('data-action');

            if (name === 'close') {
                dialog.close();
            } else {
                show(current + (name === 'next' ? 1 : -1));
            }
        } else if (event.target === dialog || event.target.classList.contains('lightbox__stage')) {
            // A click on the dark area around the image closes the lightbox.
            dialog.close();
        }
    });

    dialog.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            show(current + 1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            show(current - 1);
        }
    });

    // Escape is handled by the dialog itself; either way focus goes back.
    dialog.addEventListener('close', function () {
        image.removeAttribute('src');

        if (opener) {
            opener.focus();
            opener = null;
        }
    });
})();
