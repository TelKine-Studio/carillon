import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import flatpickr from 'flatpickr';
import { French } from 'flatpickr/dist/l10n/fr.js';
import 'flatpickr/dist/flatpickr.min.css';

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

Alpine.data('datePicker', (withTime = false, min = null) => ({
    init() {
        const french = document.documentElement.lang.startsWith('fr');
        this.picker = flatpickr(this.$el, {
            locale: french ? French : 'default',
            enableTime: withTime,
            time_24hr: true,
            minuteIncrement: 5,
            dateFormat: withTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
            altInput: true,
            altFormat: withTime ? (french ? 'j F Y à H:i' : 'F j, Y at H:i') : (french ? 'j F Y' : 'F j, Y'),
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
        this.$nextTick(() => this.reveal());
    },
    close() {
        this.open = false;
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

Alpine.start();
