(() => {
    'use strict';

    const STYLE_CLASSES = new Set([
        'fa-solid',
        'fa-regular',
        'fa-light',
        'fa-thin',
        'fa-duotone',
        'fa-brands',
        'fas',
        'far',
        'fal',
        'fat',
        'fab',
    ]);

    const SOLID_STYLE_CLASSES = new Set(['fa-solid', 'fas']);
    const UTILITY_CLASSES = new Set([
        'fa-spin',
        'fa-pulse',
        'fa-fw',
        'fa-xs',
        'fa-sm',
        'fa-lg',
        'fa-xl',
        'fa-2x',
        'fa-3x',
        'fa-4x',
        'fa-5x',
        'fa-6x',
        'fa-7x',
        'fa-8x',
        'fa-9x',
        'fa-10x',
    ]);
    const NON_ICON_CLASSES = new Set([...STYLE_CLASSES, ...UTILITY_CLASSES]);

    const SELECTOR = '.fa-solid, .fa-regular, .fa-light, .fa-thin, .fa-duotone, .fa-brands, .fas, .far, .fal, .fat, .fab, [class*="fa-"]';

    const ICON_ALIASES = {
        'fa-home': 'fa-house-chimney',
        'fa-save': 'fa-floppy-disk',
        'fa-search': 'fa-magnifying-glass',
        'fa-times': 'fa-xmark',
    };

    const variant = (regular, solid = regular) => ({ regular, solid });

    const ICON_MAP = {
        'fa-arrow-down': variant('bx-down-arrow-alt', 'bxs-down-arrow-alt'),
        'fa-arrow-down-to-bracket': 'bx-import',
        'fa-arrow-left': variant('bx-left-arrow-alt', 'bxs-left-arrow-alt'),
        'fa-arrow-right': variant('bx-right-arrow-alt', 'bxs-right-arrow-alt'),
        'fa-arrow-right-from-bracket': variant('bx-log-out', 'bxs-log-out'),
        'fa-arrow-trend-up': 'bx-trending-up',
        'fa-arrow-up': variant('bx-up-arrow-alt', 'bxs-up-arrow-alt'),
        'fa-arrow-up-from-bracket': 'bx-export',
        'fa-arrow-up-right-from-square': 'bx-link-external',
        'fa-ban': variant('bx-no-entry', 'bxs-no-entry'),
        'fa-bars': 'bx-menu',
        'fa-bell': variant('bx-bell', 'bxs-bell'),
        'fa-bell-slash': variant('bx-bell-off', 'bxs-bell-off'),
        'fa-bolt': variant('bx-bolt-circle', 'bxs-bolt'),
        'fa-book-user': variant('bx-book-reader', 'bxs-book-reader'),
        'fa-box-archive': variant('bx-archive', 'bxs-archive'),
        'fa-box-check': variant('bx-package', 'bxs-package'),
        'fa-boxes-stacked': variant('bx-package', 'bxs-package'),
        'fa-building': variant('bx-building', 'bxs-building'),
        'fa-building-columns': 'bxs-bank',
        'fa-calendar-xmark': variant('bx-calendar-x', 'bxs-calendar-x'),
        'fa-cart-shopping': variant('bx-cart-alt', 'bxs-cart-alt'),
        'fa-chart-bar': variant('bx-bar-chart-alt-2', 'bxs-bar-chart-alt-2'),
        'fa-chart-line': 'bx-line-chart',
        'fa-chart-line-down': 'bx-line-chart-down',
        'fa-chart-pie': variant('bx-pie-chart-alt-2', 'bxs-pie-chart-alt-2'),
        'fa-check': 'bx-check',
        'fa-check-circle': variant('bx-check-circle', 'bxs-check-circle'),
        'fa-check-double': 'bx-check-double',
        'fa-chevron-down': variant('bx-chevron-down', 'bxs-chevron-down'),
        'fa-chevron-left': variant('bx-chevron-left', 'bxs-chevron-left'),
        'fa-chevron-right': variant('bx-chevron-right', 'bxs-chevron-right'),
        'fa-circle': 'bx-circle',
        'fa-circle-check': variant('bx-check-circle', 'bxs-check-circle'),
        'fa-circle-exclamation': variant('bx-error-circle', 'bxs-error-circle'),
        'fa-circle-info': variant('bx-info-circle', 'bxs-info-circle'),
        'fa-circle-notch': 'bx-loader-circle',
        'fa-circle-question': variant('bx-help-circle', 'bxs-help-circle'),
        'fa-circle-xmark': variant('bx-x-circle', 'bxs-x-circle'),
        'fa-clipboard-check': 'bx-task',
        'fa-clock': variant('bx-time', 'bxs-time'),
        'fa-clock-rotate-left': 'bx-history',
        'fa-cubes-stacked': variant('bx-package', 'bxs-package'),
        'fa-diagram-project': variant('bx-network-chart', 'bxs-network-chart'),
        'fa-diamond': variant('bx-diamond', 'bxs-diamond'),
        'fa-envelope': variant('bx-envelope', 'bxs-envelope'),
        'fa-envelope-open': variant('bx-envelope-open', 'bxs-envelope-open'),
        'fa-exclamation': variant('bx-error', 'bxs-error'),
        'fa-expand-alt': 'bx-expand-alt',
        'fa-eye': variant('bx-show', 'bxs-show'),
        'fa-eye-slash': variant('bx-hide', 'bxs-hide'),
        'fa-file-excel': variant('bx-spreadsheet', 'bxs-spreadsheet'),
        'fa-file-export': 'bxs-file-export',
        'fa-file-invoice-dollar': variant('bx-receipt', 'bxs-receipt'),
        'fa-file-pdf': 'bxs-file-pdf',
        'fa-file-spreadsheet': variant('bx-spreadsheet', 'bxs-spreadsheet'),
        'fa-filter': variant('bx-filter-alt', 'bxs-filter-alt'),
        'fa-filter-list': variant('bx-filter-alt', 'bxs-filter-alt'),
        'fa-flask': 'bxs-flask',
        'fa-floppy-disk': variant('bx-save', 'bxs-save'),
        'fa-folder-open': variant('bx-folder-open', 'bxs-folder-open'),
        'fa-gauge': variant('bx-tachometer', 'bxs-tachometer'),
        'fa-gear': variant('bx-cog', 'bxs-cog'),
        'fa-grid-2': variant('bx-grid-alt', 'bxs-grid-alt'),
        'fa-hand': 'bxs-hand',
        'fa-hand-pointer': variant('bx-pointer', 'bxs-pointer'),
        'fa-history': 'bx-history',
        'fa-house-chimney': variant('bx-home-alt', 'bxs-home'),
        'fa-id-badge': variant('bx-id-card', 'bxs-id-card'),
        'fa-inbox': 'bxs-inbox',
        'fa-link': 'bx-link',
        'fa-lock': variant('bx-lock-alt', 'bxs-lock-alt'),
        'fa-magnifying-glass': variant('bx-search', 'bxs-search'),
        'fa-magnifying-glass-minus': variant('bx-zoom-out', 'bxs-zoom-out'),
        'fa-magnifying-glass-plus': variant('bx-zoom-in', 'bxs-zoom-in'),
        'fa-minus': 'bx-minus',
        'fa-minus-circle': variant('bx-minus-circle', 'bxs-minus-circle'),
        'fa-moon': variant('bx-moon', 'bxs-moon'),
        'fa-notes-medical': variant('bx-notepad', 'bxs-notepad'),
        'fa-paper-plane': variant('bx-paper-plane', 'bxs-paper-plane'),
        'fa-paperclip': 'bx-paperclip',
        'fa-pen-to-square': variant('bx-edit-alt', 'bxs-edit-alt'),
        'fa-pencil': variant('bx-pencil', 'bxs-pencil'),
        'fa-play': 'bx-play',
        'fa-plus': 'bx-plus',
        'fa-plus-circle': variant('bx-plus-circle', 'bxs-plus-circle'),
        'fa-rocket': variant('bx-rocket', 'bxs-rocket'),
        'fa-rotate': 'bx-refresh',
        'fa-rotate-left': 'bx-rotate-left',
        'fa-rotate-right': 'bx-rotate-right',
        'fa-shapes': 'bxs-shapes',
        'fa-share-from-square': variant('bx-share', 'bxs-share'),
        'fa-shield-check': variant('bx-check-shield', 'bxs-check-shield'),
        'fa-shield-halved': variant('bx-shield-quarter', 'bxs-shield-alt-2'),
        'fa-skull-crossbones': 'bxs-skull',
        'fa-sliders': 'bx-slider-alt',
        'fa-sliders-up': 'bx-slider-alt',
        'fa-spinner': 'bx-loader-alt',
        'fa-spinner-third': 'bx-loader-circle',
        'fa-stars': 'bxs-star',
        'fa-sun': variant('bx-sun', 'bxs-sun'),
        'fa-trash': variant('bx-trash', 'bxs-trash'),
        'fa-trash-can': variant('bx-trash-alt', 'bxs-trash-alt'),
        'fa-triangle-exclamation': variant('bx-error', 'bxs-error'),
        'fa-truck': 'bxs-truck',
        'fa-unlink': 'bx-unlink',
        'fa-unlock': variant('bx-lock-open', 'bxs-lock-open'),
        'fa-upload': 'bx-upload',
        'fa-user': variant('bx-user', 'bxs-user'),
        'fa-user-check': variant('bx-user-check', 'bxs-user-check'),
        'fa-user-group': variant('bx-group', 'bxs-group'),
        'fa-user-shield': variant('bx-user-check', 'bxs-user-check'),
        'fa-user-slash': variant('bx-user-x', 'bxs-user-x'),
        'fa-users': variant('bx-group', 'bxs-group'),
        'fa-users-gear': variant('bx-group', 'bxs-group'),
        'fa-users-slash': variant('bx-user-x', 'bxs-user-x'),
        'fa-wand-magic-sparkles': 'bxs-magic-wand',
        'fa-wave-pulse': 'bx-pulse',
        'fa-wifi-slash': 'bx-wifi-off',
        'fa-xmark': 'bx-x',
    };

    const pending = new Set();
    let frameId = null;

    function scheduleFlush() {
        if (frameId !== null) {
            return;
        }

        frameId = window.requestAnimationFrame(() => {
            frameId = null;

            pending.forEach((element) => syncElement(element));
            pending.clear();
        });
    }

    function queueElement(element) {
        if (!(element instanceof Element)) {
            return;
        }

        pending.add(element);
        scheduleFlush();
    }

    function queueTree(root) {
        if (!(root instanceof Element)) {
            return;
        }

        if (root.matches(SELECTOR)) {
            queueElement(root);
        }

        root.querySelectorAll(SELECTOR).forEach((element) => queueElement(element));
    }

    function cleanupElement(element) {
        const previousIcon = element.dataset.boxiconCompatIcon;

        if (previousIcon) {
            element.classList.remove(previousIcon);
            delete element.dataset.boxiconCompatIcon;
        }

        if (element.dataset.boxiconCompatSpin) {
            element.classList.remove('bx-spin');
            delete element.dataset.boxiconCompatSpin;
        }

        if (element.dataset.boxiconCompatBase) {
            const stillHasBoxiconGlyph = Array.from(element.classList).some((className) => (
                className.startsWith('bx-')
                || className.startsWith('bxs-')
                || className.startsWith('bxl-')
            ));

            if (!stillHasBoxiconGlyph) {
                element.classList.remove('bx');
            }

            delete element.dataset.boxiconCompatBase;
        }
    }

    function pickFaIcon(classes) {
        const icons = classes.filter((className) => (
            className.startsWith('fa-')
            && !NON_ICON_CLASSES.has(className)
            && className !== 'fa-arrow-'
        ));

        if (!icons.length) {
            return null;
        }

        const icon = icons[icons.length - 1];
        return ICON_ALIASES[icon] || icon;
    }

    function resolveBoxIcon(faIcon, solid) {
        const mapping = ICON_MAP[faIcon];

        if (!mapping) {
            return solid ? 'bxs-circle' : 'bx-circle';
        }

        if (typeof mapping === 'string') {
            return mapping;
        }

        return solid
            ? (mapping.solid || mapping.regular)
            : (mapping.regular || mapping.solid);
    }

    function syncElement(element) {
        const classes = Array.from(element.classList);
        const hasFaClasses = classes.some((className) => (
            STYLE_CLASSES.has(className) || className.startsWith('fa-')
        ));

        if (!hasFaClasses) {
            cleanupElement(element);
            return;
        }

        const faIcon = pickFaIcon(classes);

        if (!faIcon) {
            cleanupElement(element);
            return;
        }

        const boxIcon = resolveBoxIcon(
            faIcon,
            classes.some((className) => SOLID_STYLE_CLASSES.has(className)),
        );

        const previousIcon = element.dataset.boxiconCompatIcon;

        if (previousIcon && previousIcon !== boxIcon) {
            element.classList.remove(previousIcon);
        }

        if (!element.classList.contains('bx')) {
            element.classList.add('bx');
        }

        if (!element.classList.contains(boxIcon)) {
            element.classList.add(boxIcon);
        }

        element.dataset.boxiconCompatBase = '1';
        element.dataset.boxiconCompatIcon = boxIcon;

        if (classes.includes('fa-spin')) {
            element.classList.add('bx-spin');
            element.dataset.boxiconCompatSpin = '1';
        } else if (element.dataset.boxiconCompatSpin) {
            element.classList.remove('bx-spin');
            delete element.dataset.boxiconCompatSpin;
        }
    }

    function startObserver() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes') {
                    queueElement(mutation.target);
                    return;
                }

                mutation.addedNodes.forEach((node) => queueTree(node));
            });
        });

        observer.observe(document.documentElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class'],
        });
    }

    function initialize() {
        queueTree(document.body || document.documentElement);
        startObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }

    window.gtimsIconCompat = {
        refresh(root = document.body) {
            queueTree(root);
        },
    };
})();
