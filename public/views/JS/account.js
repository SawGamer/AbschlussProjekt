// Employee account page: contract figures and password change.

import { apiFetch, setToken } from './Authentication.js';
import { subjectId } from './Main.js';
import { h, fmt, pageHead, panel, field, toast } from './ui.js';

export async function renderAccount(container) {
    const summary = await apiFetch(`api/user/summary?id=${encodeURIComponent(subjectId)}`);

    const current = h('input', { type: 'password', name: 'current', autocomplete: 'current-password', required: true });
    const next = h('input', { type: 'password', name: 'next', autocomplete: 'new-password', minlength: '8', required: true });
    const repeat = h('input', { type: 'password', name: 'repeat', autocomplete: 'new-password', required: true });
    const error = h('p', { class: 'form-error', role: 'alert' });
    const submit = h('button', { type: 'submit', text: 'Passwort ändern' });

    const form = h('form', { class: 'form-grid', novalidate: true },
        h('div', { class: 'wide' }, field('Aktuelles Passwort', current)),
        field('Neues Passwort', next, 'Mindestens 8 Zeichen'),
        field('Neues Passwort wiederholen', repeat),
        h('div', { class: 'wide' }, error),
        h('div', { class: 'wide' }, submit),
    );

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.textContent = '';
        if (next.value.length < 8) {
            error.textContent = 'Das neue Passwort braucht mindestens 8 Zeichen.';
            return;
        }
        if (next.value !== repeat.value) {
            error.textContent = 'Die beiden neuen Passwörter stimmen nicht überein.';
            return;
        }
        submit.disabled = true;
        try {
            const result = await apiFetch('api/user/password', {
                method: 'POST',
                body: { id: Number(subjectId), current_password: current.value, new_password: next.value },
            });
            setToken(result.jwt);
            form.reset();
            toast('Passwort geändert. Andere Geräte wurden abgemeldet.');
        } catch (e) {
            error.textContent = e.message;
        }
        submit.disabled = false;
    });

    container.append(
        pageHead('Konto', 'Ihre Vertragsdaten und Ihr Passwort'),
        h('div', { class: 'columns' },
            panel('Passwort ändern', 'Nach der Änderung werden Sie auf allen anderen Geräten abgemeldet', form),
            panel('Vertrag', 'Änderungen nimmt die Verwaltung vor',
                h('dl', { class: 'facts' },
                    h('dt', { text: 'Wochenstunden' }), h('dd', { text: `${fmt.hours(summary.weekly_hours)} Std.` }),
                    h('dt', { text: 'Tagessoll' }), h('dd', { text: `${fmt.hours(summary.weekly_hours / 5)} Std.` }),
                    h('dt', { text: 'Urlaubsanspruch' }), h('dd', { text: fmt.days(summary.vacation.entitlement) }),
                )),
        ),
    );
}
