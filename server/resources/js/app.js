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
