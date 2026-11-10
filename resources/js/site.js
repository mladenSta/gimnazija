/*
 * Public site behaviour (Blade pages). Ported from resources/design/home.html.
 * Every block checks that its elements exist, so one file serves all pages.
 */

/* ---------- Hero slider ---------- */
(function () {
    const slider = document.getElementById('slider');
    const slides = slider ? [...slider.querySelectorAll('.slide')] : [];
    const dots = document.getElementById('dots');

    if (slides.length < 2 || !dots) {
        slider?.querySelector('.slider-ui')?.remove();

        return;
    }

    let i = 0;
    let timer;

    slides.forEach((_, k) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('aria-label', 'Slajd ' + (k + 1));
        b.onclick = () => go(k, true);
        dots.appendChild(b);
    });

    const go = (n, user) => {
        slides[i].classList.remove('active');
        dots.children[i].classList.remove('active');
        i = (n + slides.length) % slides.length;
        slides[i].classList.add('active');
        dots.children[i].classList.add('active');

        if (user) {
            restart();
        }
    };

    const restart = () => {
        clearInterval(timer);
        timer = setInterval(() => go(i + 1), 6500);
    };

    document.getElementById('prev').onclick = () => go(i - 1, true);
    document.getElementById('next').onclick = () => go(i + 1, true);
    dots.children[0].classList.add('active');
    restart();

    let x0 = null;
    slider.addEventListener('touchstart', (e) => (x0 = e.touches[0].clientX), {
        passive: true,
    });
    slider.addEventListener('touchend', (e) => {
        if (x0 === null) {
            return;
        }

        const d = e.changedTouches[0].clientX - x0;

        if (Math.abs(d) > 50) {
            go(i + (d < 0 ? 1 : -1), true);
        }

        x0 = null;
    });
})();

/* ---------- Gallery tag filters ---------- */
(function () {
    const filters = document.getElementById('filters');
    const masonry = document.getElementById('masonry');

    if (!filters || !masonry) {
        return;
    }

    filters.addEventListener('click', (e) => {
        const b = e.target.closest('button');

        if (!b) {
            return;
        }

        filters
            .querySelectorAll('button')
            .forEach((x) => x.classList.toggle('active', x === b));
        const f = b.dataset.f;
        masonry.querySelectorAll('.polaroid').forEach((p) => {
            const tags = (p.dataset.tags || '').split(' ');
            p.classList.toggle('hide', f !== 'all' && !tags.includes(f));
        });
    });
})();

/* ---------- Lightbox: <a data-lightbox="group" href="full.webp" data-caption="…"> ---------- */
(function () {
    const lb = document.getElementById('lightbox');

    if (!lb) {
        return;
    }

    const img = document.getElementById('lbImg');
    const cap = document.getElementById('lbCap');
    let group = [];
    let cur = 0;

    const visible = (name) =>
        [...document.querySelectorAll(`[data-lightbox="${name}"]`)].filter(
            (a) => !a.closest('.hide'),
        );

    const show = (n) => {
        cur = (n + group.length) % group.length;
        const a = group[cur];
        img.src = a.getAttribute('href');
        img.alt = a.dataset.caption || '';
        cap.textContent = a.dataset.caption || '';
        lb.querySelectorAll('.lb-prev, .lb-next').forEach(
            (b) => (b.hidden = group.length < 2),
        );
    };

    const close = () => {
        lb.classList.remove('open');
        document.body.style.overflow = '';
    };

    document.addEventListener('click', (e) => {
        const a = e.target.closest('[data-lightbox]');

        if (!a) {
            return;
        }

        e.preventDefault();
        group = visible(a.dataset.lightbox);
        show(group.indexOf(a));
        lb.classList.add('open');
        document.body.style.overflow = 'hidden';
    });

    document.getElementById('lbClose').onclick = close;
    document.getElementById('lbPrev').onclick = () => show(cur - 1);
    document.getElementById('lbNext').onclick = () => show(cur + 1);
    lb.addEventListener('click', (e) => e.target === lb && close());
    document.addEventListener('keydown', (e) => {
        if (!lb.classList.contains('open')) {
            return;
        }

        if (e.key === 'Escape') {
            close();
        }

        if (e.key === 'ArrowLeft') {
            show(cur - 1);
        }

        if (e.key === 'ArrowRight') {
            show(cur + 1);
        }
    });
})();

/* ---------- Mobile menu ---------- */
(function () {
    const burger = document.getElementById('burger');

    if (!burger) {
        return;
    }

    burger.onclick = () => {
        const open = document.body.classList.toggle('nav-open');
        burger.setAttribute('aria-expanded', String(open));
    };

    // Tap on the dimmed page (outside the panel) closes the menu.
    document.addEventListener('click', (e) => {
        if (
            document.body.classList.contains('nav-open') &&
            !e.target.closest('#menu, #burger')
        ) {
            document.body.classList.remove('nav-open');
            burger.setAttribute('aria-expanded', 'false');
        }
    });

    document.querySelectorAll('.has-dd > a').forEach((a) =>
        a.addEventListener('click', (e) => {
            if (window.innerWidth <= 1100) {
                e.preventDefault();
                a.parentElement.classList.toggle('open');
            }
        }),
    );
})();

/* ---------- Reveal on scroll, counters, back to top ---------- */
(function () {
    const reduce = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    const io = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                if (!e.isIntersecting) {
                    return;
                }

                e.target.classList.add('in');
                e.target.querySelectorAll('[data-count]').forEach((el) => {
                    const end = +el.dataset.count;

                    if (reduce) {
                        el.textContent = end + (end >= 10 ? '+' : '');

                        return;
                    }

                    let n = 0;
                    const step = Math.max(1, Math.ceil(end / 40));
                    const t = setInterval(() => {
                        n += step;

                        if (n >= end) {
                            n = end;
                            clearInterval(t);
                        }

                        el.textContent = n + (end >= 10 ? '+' : '');
                    }, 30);
                });
                io.unobserve(e.target);
            }),
        { threshold: 0.15 },
    );

    document.querySelectorAll('.reveal').forEach((el) => io.observe(el));

    const tt = document.getElementById('toTop');

    if (tt) {
        addEventListener(
            'scroll',
            () => tt.classList.toggle('show', scrollY > 700),
            { passive: true },
        );
    }
})();
