// Employee overview: stamp clock with breaks, today's timeline, figures, week chart
// and a form to add time for an earlier day.

import { apiFetch } from './Authentication.js';
import { subjectId, setWeeklyHours } from './Main.js';
import { h, fmt, isoDate, greeting, dayBadge, pageHead, panel, figures, toast, field } from './ui.js';
import { dayTimeline, weekChart, legend } from './timeline.js';

const ACTIONS = {
    start: 'Eingestempelt',
    pause: 'Pause begonnen',
    resume: 'Pause beendet',
    stop: 'Ausgestempelt',
};

let state = null;

export async function renderOverview(container) {
    const [today, summary] = await Promise.all([
        apiFetch(`api/user/today?id=${encodeURIComponent(subjectId)}`),
        apiFetch(`api/user/summary?id=${encodeURIComponent(subjectId)}`),
    ]);
    setWeeklyHours(Number(today.ShouldHours));

    const clockPanel = h('section', { class: 'panel', 'aria-labelledby': 'clockTitle' });
    const summaryArea = h('div');
    container.append(
        pageHead(`${greeting()}, ${today.Username}`, fmt.dateLong()),
        clockPanel,
        summaryArea,
    );

    const refreshSummary = async () => {
        renderSummary(summaryArea, await apiFetch(`api/user/summary?id=${encodeURIComponent(subjectId)}`), refreshSummary);
    };
    renderClock(clockPanel, today, refreshSummary);
    renderSummary(summaryArea, summary, refreshSummary);

    // The display refreshes four times a second so it never lags behind the real second;
    // the timeline moves every half minute.
    let ticks = 0;
    const timer = setInterval(() => {
        updateClockTime();
        if (++ticks % 120 === 0 && ['working', 'paused'].includes(state?.status)) {
            renderClock(clockPanel, state, refreshSummary);
        }
    }, 250);
    return () => clearInterval(timer);
}

// --- Stamp clock -------------------------------------------------------------

function renderClock(container, today, onChange) {
    state = today;
    const buttons = {
        idle: [['start', 'Einstempeln', '']],
        working: [['pause', 'Pause beginnen', 'secondary'], ['stop', 'Ausstempeln', '']],
        paused: [['resume', 'Pause beenden', ''], ['stop', 'Ausstempeln', 'secondary']],
    }[today.status] ?? [];

    container.replaceChildren(h('div', { class: 'clock' },
        h('div', {},
            h('div', { class: 'panel-head' }, h('h2', { id: 'clockTitle', text: 'Stempeluhr' }), dayBadge(today)),
            h('div', { id: 'clockTime', class: 'clock-time', role: 'timer', 'aria-label': 'Arbeitszeit heute' }),
            h('div', { class: 'clock-meta' }, clockMeta(today)),
            buttons.length ? h('div', { class: 'clock-actions' }, buttons.map(([action, label, kind]) =>
                h('button', {
                    class: kind || null,
                    dataset: { action },
                    text: label,
                    on: { click: (event) => clockAction(action, event.currentTarget, container, onChange) },
                }))) : null,
        ),
        h('div', { class: 'clock-day' },
            h('div', { class: 'panel-head' }, h('h3', { text: 'Heute' }), legend()),
            dayTimeline(today, nowTime())),
    ));
    updateClockTime();
}

function clockMeta(today) {
    const item = (label, value) => h('span', {}, `${label} `, h('strong', { class: 'num', text: value }));
    switch (today.status) {
        case 'idle':
            return 'Noch nicht eingestempelt.';
        case 'absent':
            return `Heute ist als ${today.type} eingetragen.`;
        case 'paused':
            return [item('Kommen', fmt.time(today.start)), item('Pause seit', fmt.time(today.break_started))];
        case 'done':
            return [item('Kommen', fmt.time(today.start)), item('Gehen', fmt.time(today.end)), item('Pause', `${today.pause_minutes} Min.`)];
        default:
            return [item('Kommen', fmt.time(today.start)), item('Pause bisher', `${today.pause_minutes} Min.`)];
    }
}

async function clockAction(action, button, container, onChange) {
    button.disabled = true;
    try {
        const today = await apiFetch('api/user/clock', { method: 'POST', body: { id: subjectId, action } });
        renderClock(container, today, onChange);
        toast(action === 'stop'
            ? `${ACTIONS.stop}: ${fmt.hours(today.worked_hours)} Stunden gearbeitet.`
            : `${ACTIONS[action]} um ${fmt.time(nowTime())} Uhr.`);
        onChange();
    } catch (error) {
        toast(error.message, true);
        button.disabled = false;
    }
}

/** Worked time today in seconds, counted in the browser between server updates. */
function workedSeconds(today) {
    if (!today.start) return 0;
    const now = seconds(today.end ?? nowTime());
    const breaks = (today.breaks ?? []).reduce((sum, b) => sum + Math.max(seconds(b.end ?? today.end ?? nowTime()) - seconds(b.start), 0), 0);
    return Math.max(now - seconds(today.start) - breaks, 0);
}

function updateClockTime() {
    const element = document.getElementById('clockTime');
    if (!element || !state) return;
    const total = workedSeconds(state);
    const pad = (n) => String(n).padStart(2, '0');
    element.textContent = `${pad(Math.floor(total / 3600))}:${pad(Math.floor((total % 3600) / 60))}:${pad(total % 60)}`;
}

function seconds(time) {
    const [hh, mm = 0, ss = 0] = String(time).split(':').map(Number);
    return hh * 3600 + mm * 60 + ss;
}

function nowTime() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
}

// --- Figures, week chart, manual entry --------------------------------------------

function renderSummary(container, summary, onChange) {
    const monthName = new Date().toLocaleString('de-DE', { month: 'long' });
    const balance = summary.overtime_balance;
    const vacation = summary.vacation;

    container.replaceChildren(
        figures([
            { label: 'Diese Woche', value: `${fmt.hours(summary.week.hours)} Std.`, note: `von ${fmt.hours(summary.week.target)} Std. Soll` },
            { label: monthName, value: `${fmt.hours(summary.month.hours)} Std.`, note: `von ${fmt.hours(summary.month.target)} Std. Soll` },
            {
                label: 'Überstundenkonto',
                value: `${fmt.signed(balance)} Std.`,
                note: 'seit 1. Januar',
                tone: balance > 0 ? 'positive' : balance < 0 ? 'negative' : '',
            },
            {
                label: 'Resturlaub',
                value: fmt.days(vacation.remaining),
                note: vacation.pending > 0 ? `${fmt.days(vacation.pending)} beantragt` : `von ${vacation.entitlement} Tagen`,
            },
        ]),
        h('div', { class: 'columns' },
            panel('Diese Woche', 'Stunden je Tag, der Strich zeigt das Soll', weekChart(summary.week.days, isoDate())),
            manualEntry(onChange),
        ),
    );
}

function manualEntry(onChange) {
    const date = h('input', { type: 'date', name: 'date', required: true, max: isoDate() });
    date.value = isoDate(new Date(Date.now() - 86400000));
    const start = h('input', { type: 'time', name: 'start', required: true });
    const end = h('input', { type: 'time', name: 'end', required: true });
    const pause = h('input', { type: 'number', name: 'pause', min: '0', max: '1440', step: '5' });
    pause.value = '30';

    const form = h('form', { class: 'form-grid', novalidate: true },
        h('div', { class: 'wide' }, field('Tag', date)),
        field('Beginn', start),
        field('Ende', end),
        field('Pause in Minuten', pause),
        h('div', { class: 'form-row', style: { alignSelf: 'end' } }, h('button', { type: 'submit', text: 'Eintragen' })),
    );

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!date.value || !start.value || !end.value) {
            toast('Bitte Tag, Beginn und Ende ausfüllen.', true);
            return;
        }
        try {
            const result = await apiFetch('api/user/hours', {
                method: 'POST',
                body: { id: subjectId, date: date.value, start_time: start.value, end_time: end.value, pause: Number(pause.value || 0), type: 'Normal', comment: '' },
            });
            toast(`Eingetragen: ${fmt.hours(result.actual_hours)} Stunden am ${fmt.date(date.value)}.`);
            start.value = '';
            end.value = '';
            onChange();
        } catch (error) {
            toast(error.message, true);
        }
    });

    return panel('Zeit nachtragen', 'Für Tage, an denen die Stempeluhr nicht lief', form);
}
