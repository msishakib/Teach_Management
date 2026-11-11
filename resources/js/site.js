import Alpine from 'alpinejs';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const lang = document.documentElement.lang || 'en';
const numberLocale = lang === 'bn' ? 'bn-BD' : 'en-US';

/* ---------------------------------------------------------------- Theme */
Alpine.data('themeToggle', () => ({
    dark: document.documentElement.classList.contains('dark'),

    init() {
        // Follow the OS only while the visitor has not picked a theme.
        window
            .matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', (event) => {
                if (!this.stored()) {
                    this.apply(event.matches);
                }
            });
    },

    stored() {
        try {
            return localStorage.getItem('theme');
        } catch {
            return null;
        }
    },

    apply(dark) {
        this.dark = dark;
        document.documentElement.classList.toggle('dark', dark);
    },

    toggle() {
        this.apply(!this.dark);

        try {
            localStorage.setItem('theme', this.dark ? 'dark' : 'light');
        } catch {
            /* storage unavailable (private mode) – ignore */
        }
    },
}));

/* --------------------------------------------------------------- Navbar */
Alpine.data('navbar', () => ({
    scrolled: false,
    open: false,
    loginOpen: false,

    init() {
        this.onScroll();

        this.$watch('open', (value) => {
            document.documentElement.classList.toggle('overflow-hidden', value);

            if (value) {
                this.$nextTick(() => this.$refs.close?.focus());
            } else {
                this.$refs.burger?.focus({ preventScroll: true });
            }
        });
    },

    onScroll() {
        this.scrolled = window.scrollY > 16;
    },
}));

/* ------------------------------------------------------ Animated counter */
Alpine.data('counter', (target = 0) => {
    const format = (value) =>
        new Intl.NumberFormat(numberLocale, { maximumFractionDigits: 0 }).format(
            Math.round(value),
        );

    return {
        display: format(target),

        init() {
            // Server-rendered text is the final value; animate only when motion is OK.
            if (reducedMotion.matches || !('IntersectionObserver' in window)) {
                return;
            }

            this.display = format(0);

            const observer = new IntersectionObserver(
                ([entry]) => {
                    if (entry.isIntersecting) {
                        observer.disconnect();
                        this.run();
                    }
                },
                { threshold: 0.4 },
            );

            observer.observe(this.$el);
        },

        run() {
            const duration = 1600;
            const start = performance.now();

            const tick = (now) => {
                const progress = Math.min(1, (now - start) / duration);
                this.display = format(target * (1 - Math.pow(1 - progress, 3)));

                if (progress < 1) {
                    requestAnimationFrame(tick);
                }
            };

            requestAnimationFrame(tick);
        },
    };
});

/* ---------------------------------------- Auto-sliding stacked slideshow */
Alpine.data('slider', (count = 1, interval = 6500) => ({
    i: 0,
    paused: false,
    timer: null,
    touchX: null,

    init() {
        if (count < 2 || reducedMotion.matches) {
            return;
        }

        this.timer = setInterval(() => {
            if (!this.paused && !document.hidden) {
                this.next();
            }
        }, interval);
    },

    destroy() {
        clearInterval(this.timer);
    },

    next() {
        this.i = (this.i + 1) % count;
    },

    prev() {
        this.i = (this.i - 1 + count) % count;
    },

    go(index) {
        this.i = index;
    },

    swipeStart(event) {
        this.touchX = event.changedTouches[0].clientX;
    },

    swipeEnd(event) {
        if (this.touchX === null) {
            return;
        }

        const delta = event.changedTouches[0].clientX - this.touchX;
        this.touchX = null;

        if (Math.abs(delta) > 40) {
            delta < 0 ? this.next() : this.prev();
        }
    },
}));

/* ------------------------------------------- Scroll-snap carousel arrows */
Alpine.data('scroller', () => ({
    canPrev: false,
    canNext: true,

    init() {
        this.$nextTick(() => this.update());
    },

    update() {
        const el = this.$refs.track;

        this.canPrev = el.scrollLeft > 4;
        this.canNext = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
    },

    scrollBy(direction) {
        const el = this.$refs.track;
        const first = el.firstElementChild;
        const step = first ? first.getBoundingClientRect().width + 16 : el.clientWidth * 0.8;

        el.scrollBy({
            left: direction * step,
            behavior: reducedMotion.matches ? 'auto' : 'smooth',
        });
    },
}));

/* ------------------------------------------------------------ Accordion */
Alpine.data('accordion', (first = null) => ({
    open: first,

    toggle(index) {
        this.open = this.open === index ? null : index;
    },
}));

/* ------------------------------------------------------------- Lightbox */
Alpine.data('lightbox', (items = []) => ({
    items,
    current: null,

    show(index) {
        this.current = index;
        document.documentElement.classList.add('overflow-hidden');
        this.$nextTick(() => this.$refs.close?.focus());
    },

    hide() {
        this.current = null;
        document.documentElement.classList.remove('overflow-hidden');
    },

    next() {
        this.current = (this.current + 1) % this.items.length;
    },

    prev() {
        this.current = (this.current - 1 + this.items.length) % this.items.length;
    },
}));

window.Alpine = Alpine;
Alpine.start();
document.documentElement.setAttribute('data-ready', '');

/* ---------------------------------------------------------- Scroll reveal */
(function initReveal() {
    const targets = document.querySelectorAll('[data-reveal]');

    if (reducedMotion.matches || !('IntersectionObserver' in window)) {
        targets.forEach((el) => el.classList.add('is-visible'));

        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.06 },
    );

    targets.forEach((el) => observer.observe(el));
})();
