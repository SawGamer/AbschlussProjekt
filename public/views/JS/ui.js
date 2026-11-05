// Small UI toolkit shared by both panels: DOM builder, formatting, toasts,
// dialogs, tooltip, theme switch and hash routing. Plain JavaScript, no dependencies.

/**
 * Creates an element. attrs: class, text, dataset, on (event handlers), and any attribute.
 * Children may be nodes, strings, numbers, arrays, or null/false (skipped).
 * Text always goes in as text, never as HTML.
 */
export function h(tag, attrs = {}, ...children) {
    const element = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs ?? {})) {
        if (value === null || value === undefined || value === false) continue;
        if (key === 'class') element.className = value;
        else if (key === 'text') element.textContent = value;
        else if (key === 'dataset') Object.assign(element.dataset, value);
        else if (key === 'style') {
            for (const [property, styleValue] of Object.entries(value)) {
                if (property.startsWith('--')) element.style.setProperty(property, styleValue);
                else element.style[property] = styleValue;
            }
        }
        else if (key === 'on') for (const [event, handler] of Object.entries(value)) element.addEventListener(event, handler);
        else if (key in element && typeof value !== 'string') element[key] = value;
        else element.setAttribute(key, value === true ? '' : value);
    }
    append(element, children);
    return element;
}

function append(parent, children) {
    for (const child of children) {
        if (child === null || child === undefined || child === false) continue;
        if (Array.isArray(child)) append(parent, child);
        else parent.append(child instanceof Node ? child : String(child));
    }
}

// --- Formatting ----------------------------------------------------------

const numberFormat = new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const fmt = {
    hours: (value) => numberFormat.format(Number(value || 0)),
    signed: (value) => (Number(value) > 0 ? '+' : '') + numberFormat.format(Number(value || 0)),
    time: (value) => (value ? String(value).slice(0, 5) : '–'),
    date: (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }),
    dateShort: (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('de-DE', { day: 'numeric', month: 'short' }),
    dateLong: (date = new Date()) => date.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
    month: (year, month) => new Date(year, month - 1).toLocaleString('de-DE', { month: 'long', year: 'numeric' }),
    range: (from, to) => (from === to ? fmt.date(from) : `${fmt.date(from)} bis ${fmt.date(to)}`),
    /** "3:58" for 238 minutes */
    duration: (minutes) => `${Math.floor(minutes / 60)}:${String(Math.round(minutes % 60)).padStart(2, '0')}`,
    days: (count) => `${count} ${Math.abs(count) === 1 ? 'Tag' : 'Tage'}`,
};

/** Local date as YYYY-MM-DD. */
export function isoDate(date = new Date()) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function greeting(date = new Date()) {
    const hour = date.getHours();
    if (hour < 11) return 'Guten Morgen';
    if (hour < 18) return 'Guten Tag';
    return 'Guten Abend';
}

// --- Status badges -------------------------------------------------------

const DAY_STATUS = {
    working: ['good', 'Anwesend'],
    paused: ['warning', 'Pause'],
    done: ['neutral', 'Feierabend'],
    idle: ['', 'Nicht eingestempelt'],
};

const LEAVE_STATUS = {
    pending: ['warning', 'Offen'],
    approved: ['good', 'Genehmigt'],
    rejected: ['critical', 'Abgelehnt'],
    cancelled: ['', 'Zurückgezogen'],
};

/** Badge for a clock state; absent days show their type (Urlaub, Krank, ...). */
export function dayBadge(state) {
    const [kind, label] = state.status === 'absent' ? ['', state.type] : DAY_STATUS[state.status] ?? ['', state.status];
    return h('span', { class: `badge ${kind}`, text: label });
}

export function leaveBadge(status) {
    const [kind, label] = LEAVE_STATUS[status] ?? ['', status];
    return h('span', { class: `badge ${kind}`, text: label });
}

// --- Page helpers --------------------------------------------------------

export function pageHead(title, subtitle, ...actions) {
    return h('header', { class: 'page-head' },
        h('div', {}, h('h1', { text: title }), subtitle ? h('p', { text: subtitle }) : null),
        actions.length ? h('div', { class: 'page-actions' }, actions) : null,
    );
}

export function panel(title, subtitle, ...children) {
    return h('section', { class: 'panel' },
        title ? h('div', { class: 'panel-head' }, h('h2', { text: title }), subtitle ? h('p', { text: subtitle }) : null) : null,
        children,
    );
}

/** One band of figures: [{label, value, note, tone}] */
export function figures(items) {
    return h('div', { class: 'figures' }, items.map((item) =>
        h('div', { class: 'figure' },
            h('div', { class: 'figure-label', text: item.label }),
            h('div', { class: `figure-value ${item.tone ?? ''}`, text: item.value }),
            item.note ? h('div', { class: 'figure-note', text: item.note }) : null,
        )));
}

/** Data table: columns [{label, num}], rows of cell arrays. */
export function table(columns, rows, { rowClass } = {}) {
    return h('div', { class: 'table-wrap' },
        h('table', { class: 'data' },
            h('thead', {}, h('tr', {}, columns.map((c) => h('th', { class: c.num ? 'num' : null, text: c.label })))),
            h('tbody', {}, rows.map((cells, index) => h('tr', { class: rowClass?.(index) ?? null },
                cells.map((cell, i) => h('td', { class: columns[i]?.num ? 'num' : null }, cell)))))));
}

/** Link to a route of the current page (plain "#/..." would resolve against <base>). */
export function routeHref(route) {
    return `${location.pathname}${location.search}#/${route}`;
}

export function setActiveNav(route) {
    document.querySelectorAll('.menulink[data-route]').forEach((link) => {
        const active = link.dataset.route === route;
        link.classList.toggle('active', active);
        if (active) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    });
}

// --- Toasts --------------------------------------------------------------

export function toast(text, isError = false) {
    let region = document.querySelector('.toasts');
    if (!region) {
        region = h('div', { class: 'toasts', role: 'status', 'aria-live': 'polite' });
        document.body.append(region);
    }
    const item = h('div', { class: `toast ${isError ? 'error' : ''}`, text });
    region.append(item);
    setTimeout(() => item.remove(), isError ? 6000 : 3500);
}

// --- Dialogs ---------------------------------------------------------------

/**
 * Opens a modal form. onSubmit(formData) may throw; the error is shown in the dialog.
 * Resolves with onSubmit's result, or null when cancelled.
 */
export function formDialog({ title, fields, submitLabel, danger = false, onSubmit }) {
    return new Promise((resolve) => {
        const error = h('p', { class: 'form-error', role: 'alert' });
        const submit = h('button', { type: 'submit', class: danger ? 'danger' : null, text: submitLabel });
        const form = h('form', { method: 'dialog', novalidate: true },
            h('div', { class: 'dialog-body' }, h('h2', { text: title }), fields, error),
            h('div', { class: 'dialog-actions' },
                h('button', { type: 'button', class: 'secondary', text: 'Abbrechen', on: { click: () => close(null) } }),
                submit),
        );
        const dialog = h('dialog', { 'aria-label': title }, form);
        document.body.append(dialog);

        function close(result) {
            dialog.close();
            dialog.remove();
            resolve(result);
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            error.textContent = '';
            submit.disabled = true;
            try {
                close(await onSubmit(new FormData(form), form));
            } catch (e) {
                error.textContent = e.message;
                submit.disabled = false;
            }
        });
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            close(null);
        });

        dialog.showModal();
        form.querySelector('input, select, textarea')?.focus();
    });
}

export function confirmDialog(title, text, confirmLabel, danger = false) {
    return formDialog({
        title,
        fields: h('p', { text }),
        submitLabel: confirmLabel,
        danger,
        onSubmit: () => true,
    }).then((result) => result === true);
}

export function field(label, input, hint) {
    return h('label', { class: 'field' }, label, input, hint ? h('span', { class: 'hint', text: hint }) : null);
}

// --- Tooltip (charts) --------------------------------------------------------

let tip;

/** Shows {value, label} next to the pointer or the focused element. */
export function attachTip(element, content) {
    const show = (x, y) => {
        tip ??= document.body.appendChild(h('div', { class: 'viz-tip', role: 'tooltip' }));
        const { value, label } = content();
        tip.replaceChildren(h('strong', { text: value }), h('span', { text: label }));
        tip.hidden = false;
        const box = tip.getBoundingClientRect();
        tip.style.left = `${Math.min(x + 14, window.innerWidth - box.width - 8)}px`;
        tip.style.top = `${Math.max(y - box.height - 12, 8)}px`;
    };
    const hide = () => { if (tip) tip.hidden = true; };
    element.addEventListener('pointermove', (e) => show(e.clientX, e.clientY));
    element.addEventListener('pointerleave', hide);
    element.addEventListener('focus', () => {
        const r = element.getBoundingClientRect();
        show(r.left + r.width / 2, r.top);
    });
    element.addEventListener('blur', hide);
    if (!element.hasAttribute('tabindex')) element.tabIndex = 0;
    const { value, label } = content();
    element.setAttribute('aria-label', `${label}: ${value}`);
}

// --- Theme switch: system, light, dark --------------------------------------

const THEMES = [['', 'System'], ['light', 'Hell'], ['dark', 'Dunkel']];

export function initThemeButton(button) {
    const current = () => document.documentElement.dataset.theme ?? '';
    const render = () => {
        const label = THEMES.find(([key]) => key === current())?.[1] ?? 'System';
        button.textContent = `Darstellung: ${label}`;
    };
    button.addEventListener('click', () => {
        const index = THEMES.findIndex(([key]) => key === current());
        const [next] = THEMES[(index + 1) % THEMES.length];
        if (next) document.documentElement.dataset.theme = next;
        else delete document.documentElement.dataset.theme;
        try { localStorage.setItem('wf-theme', next); } catch { /* storage may be blocked */ }
        render();
    });
    render();
}

// --- Hash routing ------------------------------------------------------------

/**
 * Renders routes[name](container, ...params) for #/name/param1/... and keeps the
 * active nav link in sync. Each render may return a cleanup function.
 */
export function startRouter(routes, fallback, container) {
    let cleanup = null;
    const render = async () => {
        const [name, ...params] = location.hash.replace(/^#\/?/, '').split('/').filter(Boolean);
        const route = routes[name] ? name : fallback;
        if (typeof cleanup === 'function') cleanup();
        cleanup = null;
        setActiveNav(route);
        container.replaceChildren();
        container.focus({ preventScroll: true });
        window.scrollTo(0, 0);
        try {
            cleanup = await routes[route](container, ...params);
        } catch (error) {
            container.append(h('p', { class: 'message error', text: `Die Seite konnte nicht geladen werden: ${error.message}` }));
        }
    };
    window.addEventListener('hashchange', render);
    render();
}

/** Saves a fetch Response as a file. */
export async function download(response, fallbackName) {
    const disposition = response.headers.get('Content-Disposition') ?? '';
    const name = disposition.match(/filename="([^"]+)"/)?.[1] ?? fallbackName;
    const url = URL.createObjectURL(await response.blob());
    const link = h('a', { href: url, download: name });
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
