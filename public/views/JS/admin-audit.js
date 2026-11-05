// Admin: activity log of changes made by admins and of vacation decisions.

import { apiFetch } from './Authentication.js';
import { h, fmt, pageHead, table } from './ui.js';

const ACTIONS = {
    hours_edited: 'Zeiten geändert',
    employee_created: 'Konto angelegt',
    employee_updated: 'Konto geändert',
    leave_approved: 'Urlaub genehmigt',
    leave_rejected: 'Urlaub abgelehnt',
    leave_cancelled: 'Urlaub storniert',
    password_changed: 'Passwort geändert',
};

export async function renderAudit(container) {
    const entries = await apiFetch('api/admin/audit');
    container.append(pageHead('Protokoll', 'Die letzten 200 Änderungen an Konten, Zeiten und Urlaub'));

    if (!entries.length) {
        container.append(h('p', { class: 'empty', text: 'Noch keine Einträge.' }));
        return;
    }

    container.append(table(
        [{ label: 'Zeitpunkt' }, { label: 'Von' }, { label: 'Aktion' }, { label: 'Betrifft' }, { label: 'Details' }],
        entries.map((entry) => [
            h('span', { class: 'num', text: `${fmt.date(entry.created_at.slice(0, 10))}, ${entry.created_at.slice(11, 16)}` }),
            entry.actor ?? '–',
            ACTIONS[entry.action] ?? entry.action,
            entry.subject ?? '–',
            entry.details ?? '',
        ]),
    ));
}
