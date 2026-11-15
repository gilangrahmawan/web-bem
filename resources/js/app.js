/*
| Interaksi kecil halaman publik tanpa library tambahan:
| - menu mobile (tombol [data-menu-toggle] → panel [data-menu-panel])
| - dropdown navigasi desktop ([data-dropdown])
| Tanpa JavaScript, menu tetap bisa dipakai karena dropdown memakai <details>.
*/

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const panel = document.querySelector('[data-menu-panel]');

    if (toggle && panel) {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!open));
            panel.classList.toggle('hidden', open);
            document.body.classList.toggle('overflow-hidden', !open);
        });
    }

    // Tutup dropdown <details> saat klik di luar atau menekan Escape.
    const dropdowns = document.querySelectorAll('details[data-dropdown]');

    document.addEventListener('click', (event) => {
        dropdowns.forEach((dropdown) => {
            if (!dropdown.contains(event.target)) {
                dropdown.removeAttribute('open');
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        dropdowns.forEach((dropdown) => dropdown.removeAttribute('open'));

        if (toggle && toggle.getAttribute('aria-expanded') === 'true') {
            toggle.click();
        }
    });
});
