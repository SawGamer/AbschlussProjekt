// Admin: vacation requests. Approving books the working days as "Urlaub".

import { apiFetch } from './Authentication.js';
import { h, fmt, pageHead, table, field, formDialog, confirmDialog, toast, leaveBadge } from './ui.js';

/**
 * approve, reject (with an optional note) or cancel an approved request.
 * Returns true when something changed.
 */
export async function decideLeave(request, decision) {
    const who = `${request.first_name} ${request.last_name}`;
    const range = fmt.range(request.start_date, request.end_date);
    try {
        if (decision === 'approve') {
            await apiFetch(`api/admin/leave/${request.id}/approve`, { method: 'POST', body: {} });
            toast(`Urlaub für ${who} genehmigt. Die Tage sind gebucht.`);
            return true;
        }
        if (decision === 'reject') {
            const note = h('input', { name: 'note', maxlength: '255', placeholder: 'Zum Beispiel: Projektabgabe in dieser Woche' });
            const done = await formDialog({
                title: 'Antrag ablehnen?',
                fields: [h('p', { text: `${who}, ${range}, ${fmt.days(request.days)}` }), h('br'), field('Begründung für die Person (optional)', note)],
                submitLabel: 'Ablehnen',
                danger: true,
                onSubmit: (data) => apiFetch(`api/admin/leave/${request.id}/reject`, { method: 'POST', body: { note: data.get('note') } }),
            });
            if (done) toast(`Antrag von ${who} abgelehnt.`);
            return !!done;
        }
        if (decision === 'cancel') {
            if (!await confirmDialog('Genehmigten Urlaub stornieren?', `Die gebuchten Urlaubstage von ${who} (${range}) werden wieder entfernt.`, 'Stornieren', true)) {
                return false;
            }
            await apiFetch(`api/admin/leave/${request.id}/cancel`, { method: 'POST', body: {} });
            toast(`Urlaub von ${who} storniert.`);
            return true;
        }
    } catch (error) {
        toast(error.message, true);
    }
    return false;
}

export async function renderLeaveRequests(container, tab = 'offen') {
    const decided = tab === 'entschieden';
    const [requests, employees] = await Promise.all([
        apiFetch(`api/admin/leave?status=${decided ? 'decided' : 'pending'}`),
        apiFetch('api/admin/employees'),
    ]);
    const byId = new Map(employees.map((e) => [e.id, e]));
    const reload = () => {
        container.replaceChildren();
        return renderLeaveRequests(container, tab);
    };

    const tabs = h('div', { class: 'segmented', role: 'group', 'aria-label': 'Anträge filtern' },
        h('button', { 'aria-pressed': String(!decided), text: 'Offen', on: { click: () => { location.hash = '#/urlaub/offen'; } } }),
        h('button', { 'aria-pressed': String(decided), text: 'Entschieden', on: { click: () => { location.hash = '#/urlaub/entschieden'; } } }));

    const act = async (request, decision) => {
        if (await decideLeave(request, decision)) await reload();
    };

    container.append(pageHead('Urlaubsanträge', decided ? 'Genehmigte, abgelehnte und zurückgezogene Anträge' : 'Anträge, die auf Ihre Entscheidung warten', tabs));
    if (!decided) {
        window.dispatchEvent(new CustomEvent('wf:pending', { detail: requests.length }));
    }

    if (!requests.length) {
        container.append(h('p', { class: 'empty', text: decided ? 'Noch keine entschiedenen Anträge.' : 'Keine offenen Anträge. Neue Anträge erscheinen hier und im Dashboard.' }));
        return;
    }

    container.append(table(
        [
            { label: 'Mitarbeiter/in' },
            { label: 'Zeitraum' },
            { label: 'Arbeitstage', num: true },
            { label: decided ? 'Entschieden' : 'Resturlaub danach', num: !decided },
            { label: 'Status' },
            { label: '' },
        ],
        requests.map((r) => {
            const employee = byId.get(r.employee_id);
            const remaining = employee ? employee.vacation_days - employee.vacation_taken - employee.vacation_pending : null;
            return [
                `${r.first_name} ${r.last_name}`,
                h('span', {}, fmt.range(r.start_date, r.end_date),
                    r.comment ? h('span', { class: 'cell-sub', text: r.comment }) : null,
                    r.decision_note ? h('span', { class: 'cell-sub', text: `Notiz: ${r.decision_note}` }) : null),
                String(r.days),
                decided
                    ? h('span', {}, r.decided_by ?? '–', r.decided_at ? h('span', { class: 'cell-sub', text: fmt.date(r.decided_at.slice(0, 10)) }) : null)
                    : (remaining === null ? '–' : fmt.days(remaining)),
                leaveBadge(r.status),
                h('div', { class: 'row-actions' },
                    r.status === 'pending' ? [
                        h('button', { class: 'small', text: 'Genehmigen', on: { click: () => act(r, 'approve') } }),
                        h('button', { class: 'secondary small', text: 'Ablehnen', on: { click: () => act(r, 'reject') } }),
                    ] : null,
                    r.status === 'approved' ? h('button', { class: 'ghost small', text: 'Stornieren', on: { click: () => act(r, 'cancel') } }) : null),
            ];
        }),
    ));
}
