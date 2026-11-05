// Employee vacation page: remaining days, request form, own requests.

import { apiFetch } from './Authentication.js';
import { subjectId } from './Main.js';
import { h, fmt, isoDate, pageHead, panel, figures, table, leaveBadge, field, toast, confirmDialog } from './ui.js';
import { countWorkingDays } from './worktime.js';

export async function renderLeave(container) {
    const data = await apiFetch(`api/user/leave?id=${encodeURIComponent(subjectId)}`);
    const { vacation, requests } = data;
    const year = new Date().getFullYear();
    const reload = () => {
        container.replaceChildren();
        return renderLeave(container);
    };

    container.append(
        pageHead('Urlaub', `Ihr Urlaubsanspruch für ${year}`),
        figures([
            { label: 'Anspruch', value: fmt.days(vacation.entitlement) },
            { label: 'Genommen oder gebucht', value: fmt.days(vacation.taken) },
            { label: 'Beantragt', value: fmt.days(vacation.pending), note: vacation.pending ? 'wartet auf Freigabe' : null },
            { label: 'Verbleibend', value: fmt.days(vacation.remaining), tone: vacation.remaining < 0 ? 'negative' : '' },
        ]),
        h('div', { class: 'columns' },
            requestList(requests, reload),
            requestForm(vacation, reload),
        ),
    );
}

function requestForm(vacation, reload) {
    const today = isoDate();
    const from = h('input', { type: 'date', name: 'start_date', min: today, required: true });
    const to = h('input', { type: 'date', name: 'end_date', min: today, required: true });
    const comment = h('input', { type: 'text', name: 'comment', maxlength: '255', placeholder: 'Optional, zum Beispiel Reiseziel' });
    const count = h('p', { class: 'hint', 'aria-live': 'polite', text: 'Wählen Sie den ersten und letzten Urlaubstag.' });
    const submit = h('button', { type: 'submit', text: 'Antrag stellen' });

    const updateCount = () => {
        if (from.value && !to.value) to.value = from.value;
        to.min = from.value || today;
        if (!from.value || !to.value || to.value < from.value) {
            count.textContent = 'Wählen Sie den ersten und letzten Urlaubstag.';
            return;
        }
        const days = countWorkingDays(from.value, to.value);
        count.textContent = days > vacation.remaining
            ? `${fmt.days(days)} Arbeitstage, aber nur ${fmt.days(vacation.remaining)} übrig.`
            : `${fmt.days(days)} Arbeitstage. Danach bleiben ${fmt.days(vacation.remaining - days)}.`;
    };
    from.addEventListener('change', updateCount);
    to.addEventListener('change', updateCount);

    const form = h('form', { class: 'form-grid', novalidate: true },
        field('Erster Tag', from),
        field('Letzter Tag', to),
        h('div', { class: 'wide' }, field('Kommentar', comment)),
        h('div', { class: 'wide' }, count),
        h('div', { class: 'wide' }, submit),
    );
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!from.value || !to.value) {
            toast('Bitte ersten und letzten Tag wählen.', true);
            return;
        }
        submit.disabled = true;
        try {
            const result = await apiFetch('api/user/leave', {
                method: 'POST',
                body: { id: subjectId, start_date: from.value, end_date: to.value, comment: comment.value },
            });
            toast(`Antrag über ${fmt.days(result.days)} gestellt.`);
            await reload();
        } catch (error) {
            toast(error.message, true);
            submit.disabled = false;
        }
    });

    return panel('Urlaub beantragen', 'Wochenenden und Feiertage zählen nicht mit', form);
}

function requestList(requests, reload) {
    if (!requests.length) {
        return panel('Meine Anträge', null, h('p', { class: 'empty', text: 'Sie haben noch keinen Urlaub beantragt.' }));
    }

    const withdraw = async (request) => {
        if (!await confirmDialog('Antrag zurückziehen?', `Der Antrag für ${fmt.range(request.start_date, request.end_date)} wird zurückgezogen.`, 'Zurückziehen', true)) {
            return;
        }
        try {
            await apiFetch(`api/user/leave/${request.id}/cancel`, { method: 'POST', body: { id: subjectId } });
            toast('Antrag zurückgezogen.');
            await reload();
        } catch (error) {
            toast(error.message, true);
        }
    };

    return panel('Meine Anträge', null, table(
        [{ label: 'Zeitraum' }, { label: 'Tage', num: true }, { label: 'Status' }, { label: '' }],
        requests.map((request) => [
            h('span', {},
                fmt.range(request.start_date, request.end_date),
                request.comment ? h('span', { class: 'cell-sub', text: request.comment }) : null,
                request.decision_note ? h('span', { class: 'cell-sub', text: `Notiz: ${request.decision_note}` }) : null),
            String(request.days),
            leaveBadge(request.status),
            request.status === 'pending'
                ? h('div', { class: 'row-actions' }, h('button', { class: 'ghost small', text: 'Zurückziehen', on: { click: () => withdraw(request) } }))
                : '',
        ]),
    ));
}
