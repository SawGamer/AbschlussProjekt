// Admin: employee list with search, create and edit dialogs.

import { apiFetch } from './Authentication.js';
import { h, fmt, pageHead, table, field, formDialog, toast, routeHref } from './ui.js';

export async function renderEmployees(container) {
    const employees = await apiFetch('api/admin/employees');
    const reload = () => {
        container.replaceChildren();
        return renderEmployees(container);
    };

    const active = employees.filter((e) => e.active).length;
    const search = h('input', { type: 'search', placeholder: 'Name oder Benutzername', 'aria-label': 'Mitarbeitende suchen' });
    const add = h('button', { text: 'Mitarbeiter/in anlegen', on: { click: () => openEmployeeDialog(null, reload) } });
    const area = h('div');

    const render = () => {
        const query = search.value.trim().toLowerCase();
        const visible = employees.filter((e) =>
            `${e.first_name} ${e.last_name} ${e.username}`.toLowerCase().includes(query));
        area.replaceChildren(visible.length
            ? employeeTable(visible, reload)
            : h('p', { class: 'empty', text: `Niemand passt zu „${search.value}“.` }));
    };
    search.addEventListener('input', render);

    container.append(
        pageHead('Mitarbeitende', `${active} aktiv, ${employees.length - active} inaktiv`, search, add),
        area,
    );
    render();
}

function employeeTable(employees, reload) {
    return table(
        [
            { label: 'Name' },
            { label: 'Wochenstunden', num: true },
            { label: 'Urlaub', num: true },
            { label: 'Stunden im Monat', num: true },
            { label: 'Überstunden im Monat', num: true },
            { label: 'Rolle' },
            { label: 'Status' },
            { label: '' },
        ],
        employees.map((e) => [
            h('span', {}, `${e.first_name} ${e.last_name}`, h('span', { class: 'cell-sub', text: e.username })),
            fmt.hours(e.weekly_hours),
            h('span', {},
                `${e.vacation_taken} von ${e.vacation_days}`,
                Number(e.vacation_pending) > 0 ? h('span', { class: 'cell-sub', text: `${e.vacation_pending} beantragt` }) : null),
            fmt.hours(e.month_hours),
            fmt.hours(e.month_overtime),
            e.is_admin ? 'Admin' : 'Mitarbeiter/in',
            h('span', { class: `badge ${e.active ? 'good' : ''}`, text: e.active ? 'Aktiv' : 'Inaktiv' }),
            h('div', { class: 'row-actions' },
                h('a', { class: 'button secondary small', href: routeHref(`zeiten/${e.id}`), text: 'Zeiten' }),
                h('button', { class: 'secondary small', text: 'Bearbeiten', on: { click: () => openEmployeeDialog(e, reload) } })),
        ]),
        { rowClass: (index) => (employees[index].active ? null : 'inactive') },
    );
}

/** Create (employee = null) or edit dialog. */
function openEmployeeDialog(employee, reload) {
    const isNew = employee === null;
    const input = (name, attrs = {}) => {
        const element = h('input', { name, ...attrs });
        if (!isNew && employee[name] !== undefined) element.value = employee[name];
        return element;
    };
    const check = (name, label, checked) => h('label', { class: 'check' },
        h('input', { type: 'checkbox', name, checked }), label);

    const fields = h('div', { class: 'form-grid' },
        field('Vorname', input('first_name', { required: true, maxlength: '50', autocomplete: 'off' })),
        field('Nachname', input('last_name', { required: true, maxlength: '50', autocomplete: 'off' })),
        field('Benutzername', input('username', { required: true, pattern: '[A-Za-z0-9]{3,50}', autocomplete: 'off' }), '3 bis 50 Buchstaben oder Ziffern'),
        field('Wochenstunden', input('weekly_hours', { type: 'number', min: '1', max: '60', step: '0.5', required: true, value: isNew ? '40' : null })),
        field('Urlaubstage pro Jahr', input('vacation_days', { type: 'number', min: '0', max: '60', step: '1', required: true, value: isNew ? '30' : null })),
        field(isNew ? 'Startpasswort' : 'Neues Passwort', h('input', { name: 'password', type: 'password', autocomplete: 'new-password', minlength: '8' }),
            isNew ? 'Mindestens 8 Zeichen. Teilen Sie es der Person persönlich mit.' : 'Leer lassen, um es nicht zu ändern. Ein neues Passwort meldet die Person überall ab.'),
        h('div', { class: 'wide form-row' },
            check('is_admin', 'Admin-Rechte', !isNew && !!employee.is_admin),
            isNew ? null : check('active', 'Konto aktiv', !!employee.active)),
    );

    return formDialog({
        title: isNew ? 'Mitarbeiter/in anlegen' : `${employee.first_name} ${employee.last_name} bearbeiten`,
        fields,
        submitLabel: isNew ? 'Anlegen' : 'Änderungen speichern',
        onSubmit: async (data) => {
            const body = {
                first_name: data.get('first_name').trim(),
                last_name: data.get('last_name').trim(),
                username: data.get('username').trim(),
                weekly_hours: Number(data.get('weekly_hours')),
                vacation_days: Number(data.get('vacation_days')),
                is_admin: data.get('is_admin') === 'on',
            };
            if (!isNew) body.active = data.get('active') === 'on';
            const password = data.get('password');
            if (isNew || password) body.password = password;

            const saved = await apiFetch(isNew ? 'api/admin/employees' : `api/admin/employees/${employee.id}`, {
                method: isNew ? 'POST' : 'PATCH',
                body,
            });
            toast(isNew ? `${saved.first_name} ${saved.last_name} wurde angelegt.` : 'Änderungen gespeichert.');
            await reload();
            return saved;
        },
    });
}
