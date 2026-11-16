import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import flatpickr from 'flatpickr';
import { French } from 'flatpickr/dist/l10n/fr.js';

/**
 * Carillon's web interface is server-rendered (one page per URL).
 * Alpine.js only adds small interactions: menus, dialogs, tabs, toasts,
 * copy buttons and live updates.
 */

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.plugin(focus);

/* ---------------------------------------------------------------- Theme */

Alpine.store('theme', {
    value: localStorage.getItem('theme') || 'system',
    set(value) {
        this.value = value;
        localStorage.setItem('theme', value);
        applyTheme();
    },
});

function applyTheme() {
    const value = localStorage.getItem('theme') || 'system';
    const dark = value === 'dark' || (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme);

/* --------------------------------------------------------------- Toasts */

Alpine.store('toasts', {
    items: [],
    push(message, type = 'success') {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type });
        setTimeout(() => this.dismiss(id), 5000);
    },
    dismiss(id) {
        this.items = this.items.filter((toast) => toast.id !== id);
    },
});

/* ----------------------------------------------------- Confirm dialogs */

Alpine.store('confirm', {
    open: false,
    title: '',
    message: '',
    action: '',
    form: null,
    ask(form) {
        this.form = form;
        this.title = form.dataset.confirmTitle || form.dataset.confirm;
        this.message = form.dataset.confirmTitle ? form.dataset.confirm : '';
        this.action = form.dataset.confirmAction || '';
        this.open = true;
    },
    accept() {
        this.open = false;
        this.form?.removeAttribute('data-confirm');
        this.form?.submit();
    },
});

// Forms with data-confirm="…" ask for confirmation in a dialog first.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (form instanceof HTMLFormElement && form.dataset.confirm) {
        event.preventDefault();
        Alpine.store('confirm').ask(form);
    }
});

/* ------------------------------------------------------------ Clipboard */

Alpine.data('copy', (text = null) => ({
    copied: false,
    async copy(value = null) {
        const content = value ?? text ?? this.$refs.source?.textContent.trim() ?? '';

        try {
            await navigator.clipboard.writeText(content);
        } catch {
            const area = Object.assign(document.createElement('textarea'), { value: content });
            document.body.append(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }

        this.copied = true;
        setTimeout(() => (this.copied = false), 1600);
    },
}));

// mode: false (date), true (date and time) or 'time' (time of day only).
Alpine.data('datePicker', (withTime = false, min = null) => ({
    init() {
        const french = document.documentElement.lang.startsWith('fr');
        const timeOnly = withTime === 'time';
        this.picker = flatpickr(this.$el, {
            locale: french ? French : 'default',
            enableTime: Boolean(withTime),
            noCalendar: timeOnly,
            time_24hr: true,
            minuteIncrement: 5,
            dateFormat: timeOnly ? 'H:i' : withTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
            altInput: true,
            altFormat: timeOnly ? 'H:i' : withTime ? (french ? 'j F Y à H:i' : 'F j, Y at H:i') : (french ? 'j F Y' : 'F j, Y'),
            minDate: min || null,
            disableMobile: true,
            monthSelectorType: 'static',
        });
    },
    destroy() {
        this.picker?.destroy();
    },
}));

/* --------------------------------------------------------- Custom select */

// Drives components/ui/select.blade.php: a styled listbox in front of a
// hidden native <select> (which still holds the value for forms, x-model
// and onchange handlers).
/* ---------------------------------------------------- Journey editor */

// Steps of a journey: added, reordered and removed in place; the inputs are
// named steps[index][...] so the form posts them in their order.
Alpine.data('journeyEditor', (config) => ({
    trigger: config.trigger,
    steps: config.steps,
    uid: config.steps.length,
    labels: config.labels,
    add(type) {
        this.steps.push({ uid: ++this.uid, type, amount: 1, unit: 'days', template_id: '', title: '', subject: '', body: '', url: '', key: '', operator: 'is', value: '' });
    },
    move(index, offset) {
        const target = index + offset;
        if (target < 0 || target >= this.steps.length) return;
        const [step] = this.steps.splice(index, 1);
        this.steps.splice(target, 0, step);
    },
}));

/* ---------------------------------------------------- Delight */

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Figures of the stat tiles count up the first time they come into view
// ("1 040", "14,5 %", "—" is left alone). The text ends exactly as written.
const formatFigure = (value, decimals) =>
    value.toFixed(decimals).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

function countUp(element) {
    const original = element.textContent.trim();
    const match = original.match(/^(\D*?)(\d[\d\s  ]*?(?:,\d+)?)(\s*\D*)$/);
    if (!match) return;
    const [, prefix, figure, suffix] = match;
    const decimals = (figure.split(',')[1] || '').length;
    const target = parseFloat(figure.replace(/[\s  ]/g, '').replace(',', '.'));
    if (!Number.isFinite(target) || target === 0) return;

    const duration = Math.min(1200, 500 + Math.log10(target + 1) * 180);
    const start = performance.now();
    const step = (now) => {
        const progress = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - progress, 3);
        element.textContent = progress < 1 ? prefix + formatFigure(target * eased, decimals) + suffix : original;
        if (progress < 1) requestAnimationFrame(step);
        else element.classList.add('count-done');
    };
    requestAnimationFrame(step);
}

function watchFigures(root = document) {
    if (reduceMotion || !('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            countUp(entry.target);
        });
    });
    root.querySelectorAll('[data-count]').forEach((element) => observer.observe(element));
}
document.addEventListener('DOMContentLoaded', () => watchFigures());

// A burst of confetti in the colors of Carillon (a message went out).
window.celebrate = () => {
    if (reduceMotion) return;
    const canvas = document.createElement('canvas');
    Object.assign(canvas.style, { position: 'fixed', inset: '0', width: '100%', height: '100%', pointerEvents: 'none', zIndex: '70' });
    document.body.appendChild(canvas);
    const ratio = window.devicePixelRatio || 1;
    canvas.width = innerWidth * ratio;
    canvas.height = innerHeight * ratio;
    const context = canvas.getContext('2d');
    context.scale(ratio, ratio);

    const colors = ['#E4531E', '#F97316', '#FBBF24', '#10B981', '#3B82F6', '#A855F7'];
    const pieces = Array.from({ length: 140 }, (_, index) => {
        const fromLeft = index % 2 === 0;
        const angle = (fromLeft ? -55 : -125) * (Math.PI / 180) + (Math.random() - 0.5) * 0.9;
        const speed = 9 + Math.random() * 9;
        return {
            x: fromLeft ? -10 : innerWidth + 10,
            y: innerHeight * (0.55 + Math.random() * 0.25),
            vx: Math.cos(angle) * speed,
            vy: Math.sin(angle) * speed,
            size: 5 + Math.random() * 6,
            spin: (Math.random() - 0.5) * 0.4,
            rotation: Math.random() * Math.PI,
            color: colors[index % colors.length],
            round: Math.random() < 0.3,
        };
    });

    const start = performance.now();
    const frame = (now) => {
        const elapsed = now - start;
        context.clearRect(0, 0, innerWidth, innerHeight);
        context.globalAlpha = Math.max(0, 1 - Math.max(0, elapsed - 1600) / 600);
        for (const piece of pieces) {
            piece.vy += 0.32;
            piece.vx *= 0.985;
            piece.x += piece.vx;
            piece.y += piece.vy;
            piece.rotation += piece.spin;
            context.save();
            context.translate(piece.x, piece.y);
            context.rotate(piece.rotation);
            context.fillStyle = piece.color;
            if (piece.round) {
                context.beginPath();
                context.arc(0, 0, piece.size / 2, 0, Math.PI * 2);
                context.fill();
            } else {
                context.fillRect(-piece.size / 2, -piece.size / 4, piece.size, piece.size / 2);
            }
            context.restore();
        }
        if (elapsed < 2200) requestAnimationFrame(frame);
        else canvas.remove();
    };
    requestAnimationFrame(frame);
};

/* ---------------------------------------------------- Dropdown menus */

// Menu of a ⋮ button: positioned against the viewport (fixed), below the
// button or above it when there is no room, and closed on scroll.
Alpine.data('dropdownMenu', (floating = true) => ({
    open: false,
    toggle() {
        this.open = !this.open;
        if (!this.open || !floating) return;
        this.$nextTick(() => {
            const menu = this.$refs.menu;
            const box = this.$refs.trigger.getBoundingClientRect();
            const below = window.innerHeight - box.bottom;
            const up = below < menu.offsetHeight + 16 && box.top > menu.offsetHeight + 16;
            const alignRight = menu.classList.contains('right-0');
            Object.assign(menu.style, {
                position: 'fixed',
                marginTop: '0',
                top: up ? 'auto' : `${box.bottom + 8}px`,
                bottom: up ? `${window.innerHeight - box.top + 8}px` : 'auto',
                left: alignRight ? 'auto' : `${box.left}px`,
                right: alignRight ? `${window.innerWidth - box.right}px` : 'auto',
                // A full-width menu keeps the width of its button.
                width: menu.classList.contains('w-full') ? `${box.width}px` : '',
            });
        });
        const close = () => {
            this.open = false;
            window.removeEventListener('scroll', close, true);
        };
        window.addEventListener('scroll', close, true);
    },
}));

/* ---------------------------------------------------- Live lists */

// A filtered, paginated list that updates without reloading the page: the
// form (search typed with a short pause, selects at once) and the page links
// fetch the same page in the background and swap only the results. The
// address follows, so the back button and a reload give the same list.
Alpine.data('liveList', (target) => ({
    loading: false,
    syncing: false,
    form: null,
    request: null,
    timer: null,
    init() {
        const form = (this.form = this.$el.querySelector('form[data-live]'));
        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                this.load(this.urlOf(form));
            });
            form.addEventListener('input', (event) => {
                if (!event.target.matches('input')) return;
                clearTimeout(this.timer);
                // Typing replaces the address instead of adding a step to the history.
                this.timer = setTimeout(() => this.load(this.urlOf(form), { replace: true }), 300);
            });
            form.addEventListener('change', (event) => {
                if (event.target.matches('select') && !this.syncing) this.load(this.urlOf(form));
            });
        }
        this.$el.addEventListener('click', (event) => {
            // Page links, and links that change a filter (tabs): data-live-pages / data-live-link.
            const link = event.target.closest('[data-live-pages] a[href], a[data-live-link]');
            if (!link || event.metaKey || event.ctrlKey || event.shiftKey) return;
            event.preventDefault();
            // A filter link (tab, "clear filters") also resets the form to its address.
            this.load(link.href, { scroll: link.matches('[data-live-pages] a'), fill: true });
        });
        window.addEventListener('popstate', () => {
            this.fill(form);
            this.load(location.href, { history: false });
        });
    },
    // Puts the values of the address back in the form (back and forward buttons).
    fill(form) {
        if (!form) return;
        const params = new URL(location.href).searchParams;
        this.syncing = true;
        form.querySelectorAll('input[name], select[name]').forEach((field) => {
            if (field.type === 'hidden') return;
            field.value = params.get(field.name) ?? '';
            if (field.tagName === 'SELECT') field.dispatchEvent(new Event('change', { bubbles: true }));
        });
        this.syncing = false;
    },
    urlOf(form) {
        const url = new URL(form.getAttribute('action') || location.pathname, location.href);
        new FormData(form).forEach((value, key) => {
            if (value !== '') url.searchParams.set(key, value);
        });
        return url.toString();
    },
    async load(url, { scroll = false, history = true, fill = false, replace = false } = {}) {
        this.request?.abort();
        const request = (this.request = new AbortController());
        this.loading = true;
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: request.signal });
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            for (const id of [target, ...(this.$el.dataset.liveAlso || '').split(' ').filter(Boolean)]) {
                const fresh = page.getElementById(id);
                const current = document.getElementById(id);
                if (!fresh || !current) {
                    location.href = url;
                    return;
                }
                current.replaceWith(fresh);
            }
            if (history && url !== location.href) window.history[replace ? 'replaceState' : 'pushState']({}, '', url);
            if (fill) this.fill(this.form);
            if (scroll) this.$el.scrollIntoView({ block: 'start', behavior: 'smooth' });
        } catch (error) {
            if (error.name !== 'AbortError') location.href = url;
        } finally {
            if (this.request === request) this.loading = false;
        }
    },
}));

/* ---------------------------------------------------- Settings sections */

// A settings card shows its values; "Edit" reveals its fields. Cancel puts
// the fields back as they were, so the rest of the form posts them unchanged.
Alpine.data('settingsSection', (open = false) => ({
    editing: open,
    edit() {
        this.editing = true;
        this.$nextTick(() => this.$refs.fields?.querySelector('input:not([type=hidden]):not([disabled]):not(.sr-only), textarea')?.focus());
    },
    cancel() {
        this.$refs.fields.querySelectorAll('input, textarea, select').forEach((field) => {
            if (field.type === 'checkbox' || field.type === 'radio') {
                if (field.checked === field.defaultChecked) return;
                field.checked = field.defaultChecked;
                field.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (field.tagName === 'SELECT') {
                const initial = [...field.options].find((option) => option.defaultSelected) ?? field.options[0];
                if (!initial || field.value === initial.value) return;
                field.value = initial.value;
                field.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (field.type === 'file') {
                if (!field.value) return;
                field.value = '';
                field.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (field.value !== field.defaultValue) {
                field.value = field.defaultValue;
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        this.editing = false;
    },
}));

Alpine.data('selectBox', () => ({
    open: false,
    value: '',
    label: '',
    active: 0,
    options: [],
    init() {
        this.read();
        // x-model may set the native value after this component starts.
        this.$nextTick(() => this.read());
    },
    read() {
        const native = this.$refs.native;
        this.options = [...native.options].map((option) => ({ value: option.value, label: option.text }));
        this.sync();
    },
    sync() {
        const native = this.$refs.native;
        this.value = native.value;
        this.label = native.options[native.selectedIndex]?.text ?? '';
    },
    toggle() {
        this.open ? this.close() : this.show();
    },
    show() {
        this.open = true;
        this.active = Math.max(0, this.options.findIndex((option) => option.value === this.value));
        this.$nextTick(() => {
            this.place();
            this.reveal();
        });
        // The list floats above the page (cards may clip their content): it follows
        // the button while the page scrolls or resizes.
        this.follow = () => (this.open ? this.place() : null);
        window.addEventListener('scroll', this.follow, true);
        window.addEventListener('resize', this.follow);
    },
    close() {
        this.open = false;
        window.removeEventListener('scroll', this.follow, true);
        window.removeEventListener('resize', this.follow);
    },
    place() {
        const list = this.$refs.list;
        const box = this.$refs.button.getBoundingClientRect();
        const gap = 6;
        const below = window.innerHeight - box.bottom - gap - 8;
        const above = box.top - gap - 8;
        const height = Math.min(list.scrollHeight, 256);
        const up = below < height && above > below;

        Object.assign(list.style, {
            position: 'fixed',
            left: `${box.left}px`,
            width: `${box.width}px`,
            marginTop: '0',
            maxHeight: `${Math.max(120, Math.min(256, up ? above : below))}px`,
            top: up ? 'auto' : `${box.bottom + gap}px`,
            bottom: up ? `${window.innerHeight - box.top + gap}px` : 'auto',
        });
    },
    move(step) {
        if (!this.open) return this.show();
        this.active = (this.active + step + this.options.length) % this.options.length;
        this.$nextTick(() => this.reveal());
    },
    pick() {
        this.open ? this.choose(this.options[this.active].value) : this.show();
    },
    reveal() {
        this.$refs.list?.children[this.active + 1]?.scrollIntoView({ block: 'nearest' });
    },
    choose(value) {
        const native = this.$refs.native;
        native.value = value;
        this.sync();
        this.close();
        native.dispatchEvent(new Event('input', { bubbles: true }));
        native.dispatchEvent(new Event('change', { bubbles: true }));
        this.$refs.button.focus();
    },
}));

/* ---------------------------------------------------- Message composer */

Alpine.data('messageComposer', (config) => ({
    ab: config.ab ?? false,
    test: { sending: false, result: '', ok: false, url: null },
    // Sends the message as written to the test devices only, without leaving the page.
    async sendTest(url) {
        this.test = { sending: true, result: '', ok: false, url: null };
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(this.$root),
            });
            const body = await response.json().catch(() => ({}));
            this.test = response.ok
                ? { sending: false, result: body.message, ok: true, url: body.url }
                : { sending: false, result: body.error || body.message || response.statusText, ok: false, url: null };
        } catch (error) {
            this.test = { sending: false, result: error.message, ok: false, url: null };
        }
    },
    texts: config.texts,
    active: 0,
    audience: config.audience,
    included: config.included,
    excluded: config.excluded,
    platforms: config.platforms,
    image: config.image ?? '',
    buttons: config.buttons,
    data: config.data,
    schedule: config.schedule,
    channel: config.channel ?? 'push',
    emailSubject: config.emailSubject ?? '',
    emailPreheader: config.emailPreheader ?? '',
    emailBody: config.emailBody ?? '',
    advanced: false,
    estimate: { total: null, platforms: {}, error: null, loading: false },
    timer: null,

    init() {
        this.$watch('audience', () => this.refresh());
        this.$watch('included', () => this.refresh());
        this.$watch('excluded', () => this.refresh());
        this.$watch('platforms', () => this.refresh());
        this.$watch('channel', () => this.refresh());
        this.refresh();
    },

    get preview() {
        const text = this.texts[this.active] ?? {};
        return {
            title: text.title,
            subtitle: text.subtitle,
            body: text.body,
            image: this.image,
            buttons: this.buttons.map((b) => b.text).filter(Boolean),
        };
    },

    /** The email as a sample person sees it (variables filled in). */
    get emailPreview() {
        const sample = { email: 'ada@example.com', external_id: 'user-42', unsubscribe_url: '#' };
        const body = this.emailBody.trim() || '<p style="color:#a1a1aa;font:14px sans-serif">…</p>';
        return body.replace(/\{\{\s*([a-z_]+)(?:\.([A-Za-z0-9_\-]+))?\s*(?:\|\s*default:\s*"([^"]*)")?\s*\}\}/g, (all, name, key, fallback) => {
            if (name === 'tags' || name === 'tag') return fallback ?? (key === 'first_name' ? 'Ada' : '');
            return sample[name] ?? fallback ?? '';
        });
    },

    starterEmail: config.starterEmail ?? '',

    get unusedLanguages() {
        const used = this.texts.map((t) => t.language);
        return Object.entries(config.languages).filter(([code]) => !used.includes(code));
    },

    addLanguage(code) {
        if (!code) return;
        this.texts.push({ language: code, title: '', subtitle: '', body: '' });
        this.active = this.texts.length - 1;
    },

    removeLanguage(index) {
        this.texts.splice(index, 1);
        this.active = Math.max(0, Math.min(this.active, this.texts.length - 1));
    },

    toggle(list, value) {
        const index = this[list].indexOf(value);
        index === -1 ? this[list].push(value) : this[list].splice(index, 1);
    },

    /** Recipients for the current audience (debounced). */
    refresh() {
        // Template editor: no audience.
        if (!config.estimateUrl) return;
        clearTimeout(this.timer);
        this.timer = setTimeout(async () => {
            this.estimate.loading = true;
            try {
                const response = await fetch(config.estimateUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: new FormData(this.$root),
                });
                const result = await response.json();
                this.estimate = { total: result.total, platforms: result.platforms ?? {}, error: result.error ?? null, loading: false };
            } catch {
                this.estimate.loading = false;
            }
        }, 350);
    },

    format(value) {
        return new Intl.NumberFormat(document.documentElement.lang || undefined).format(value ?? 0);
    },
}));

/* ------------------------------------------------------ Segment builder */

/** Conditions AND-ed within a group, groups OR-ed (API "filters" format). */
Alpine.data('segmentBuilder', (config) => ({
    groups: [],
    count: null,
    error: null,
    loading: false,
    timer: null,

    init() {
        const groups = [[]];
        for (const filter of config.filters) {
            if (filter.operator === 'OR') groups.push([]);
            else groups[groups.length - 1].push({ field: 'tag', key: '', relation: '=', value: '', hours_ago: '', ...filter });
        }
        this.groups = groups.filter((g) => g.length).length ? groups.filter((g) => g.length) : [[this.blank()]];
        this.$watch('groups', () => this.refresh(), { deep: true });
        this.refresh();
    },

    blank() {
        return { field: 'tag', key: '', relation: '=', value: '', hours_ago: '' };
    },

    get filters() {
        return this.groups.flatMap((group, index) => [...(index ? [{ operator: 'OR' }] : []), ...group]);
    },

    get json() {
        return JSON.stringify(this.filters);
    },

    relations(field) {
        return config.relations[field] ?? config.relations.tag;
    },

    usesKey(field) {
        return field === 'tag';
    },

    usesHours(field) {
        return field === 'last_session' || field === 'first_session';
    },

    needsValue(condition) {
        return !['exists', 'not_exists'].includes(condition.relation) && !this.usesHours(condition.field);
    },

    fieldChanged(condition) {
        const relations = this.relations(condition.field);
        if (!relations.some(([value]) => value === condition.relation)) condition.relation = relations[0][0];
    },

    refresh() {
        // Template editor: no audience.
        if (!config.estimateUrl) return;
        clearTimeout(this.timer);
        this.timer = setTimeout(async () => {
            this.loading = true;
            try {
                const body = new FormData();
                body.append('filters', this.json);
                const response = await fetch(config.countUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body,
                });
                const result = await response.json();
                this.count = result.total;
                this.error = result.error ?? null;
            } finally {
                this.loading = false;
            }
        }, 400);
    },
}));

/* ------------------------------------------- Custom select (dynamic) */

/**
 * Same look as x-ui.select, for options computed by Alpine:
 * <div x-data="dropdownSelect(() => [[value, label], ...])" x-modelable="value" x-model="...">
 */
Alpine.data('dropdownSelect', (source) => ({
    open: false,
    value: '',
    active: 0,
    get options() {
        return (source() ?? []).map(([value, label]) => ({ value: String(value), label }));
    },
    get label() {
        return this.options.find((option) => option.value === String(this.value))?.label ?? '';
    },
    toggle() {
        this.open ? (this.open = false) : this.show();
    },
    show() {
        this.open = true;
        this.active = Math.max(0, this.options.findIndex((option) => option.value === String(this.value)));
    },
    move(step) {
        if (!this.open) return this.show();
        this.active = (this.active + step + this.options.length) % this.options.length;
    },
    pick() {
        this.open ? this.choose(this.options[this.active].value) : this.show();
    },
    choose(value) {
        this.value = value;
        this.open = false;
        this.$refs.button.focus();
        this.$dispatch('change');
    },
}));

Alpine.start();
