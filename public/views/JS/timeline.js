// Charts: the day timeline (one row per person, like a punch card),
// the week chart and the monthly hours chart. Colors come from CSS tokens
// (--work, --break); every mark has a tooltip and an accessible label.

import { h, fmt, attachTip, dayBadge } from './ui.js';

const DEFAULT_FROM = 6;
const DEFAULT_TO = 20;

/** "08:30:15" -> 8.504 */
function toHours(time) {
    const [hours, minutes = 0, seconds = 0] = String(time).split(':').map(Number);
    return hours + minutes / 60 + seconds / 3600;
}

function minutesBetween(from, to) {
    return Math.max(Math.round((toHours(to) - toHours(from)) * 60), 0);
}

/** Visible hour range: 06-20, widened to fit early starts and late ends. */
function rangeFor(states, now) {
    let from = DEFAULT_FROM;
    let to = DEFAULT_TO;
    for (const state of states) {
        if (!state.start) continue;
        from = Math.min(from, Math.floor(toHours(state.start)));
        to = Math.max(to, Math.ceil(toHours(state.end ?? now)));
    }
    return { from: Math.max(from, 0), to: Math.min(to, 24) };
}

/** Work and break intervals of one day, in order. */
function segments(state, now) {
    if (!state.start) return [];
    const end = state.end ?? now;
    const parts = [];
    let cursor = state.start;
    for (const pause of state.breaks ?? []) {
        const pauseEnd = pause.end ?? end;
        if (toHours(pause.start) > toHours(cursor)) parts.push({ kind: 'work', from: cursor, to: pause.start });
        parts.push({ kind: 'break', from: pause.start, to: pauseEnd, running: !pause.end && !state.end });
        cursor = pauseEnd;
    }
    // A running work segment is kept even at zero length, so clocking in shows up at once.
    if (toHours(end) > toHours(cursor) || (!state.end && !(state.breaks ?? []).some((b) => !b.end))) {
        parts.push({ kind: 'work', from: cursor, to: end, running: !state.end });
    }
    return parts;
}

function axis({ from, to }, now = null) {
    const span = to - from;
    const nowHours = now ? toHours(now) : null;
    const labels = [];
    for (let hour = from; hour <= to; hour += span > 16 ? 3 : 2) {
        if (nowHours !== null && Math.abs(hour - nowHours) < span * 0.045) continue; // the now label sits here
        labels.push(h('span', { style: { left: `${((hour - from) / span) * 100}%` }, text: `${String(hour).padStart(2, '0')}:00` }));
    }
    return h('div', { class: 'timeline-axis', 'aria-hidden': 'true' }, labels);
}

function track(state, now, range) {
    const span = range.to - range.from;
    const position = (time) => Math.min(Math.max((toHours(time) - range.from) / span, 0), 1) * 100;
    const element = h('div', { class: 'timeline-track', style: { '--hours': span } });
    element.style.setProperty('--hours', span);

    if (state.status === 'absent') {
        element.append(h('div', { class: 'timeline-absent', text: state.type }));
        return element;
    }

    const parts = segments(state, now);
    parts.forEach((part, index) => {
        const left = position(part.from);
        const width = position(part.to) - left;
        if (width <= 0 && !part.running) return; // a segment that just started still shows as a sliver
        const gap = index < parts.length - 1 ? '2px' : '0px'; // surface gap between touching segments
        const segment = h('div', {
            class: `timeline-segment ${part.kind === 'break' ? 'break' : ''} ${part.running ? 'running' : ''}`,
            style: { left: `${left}%`, width: `max(calc(${width}% - ${gap}), 2px)` },
        });
        attachTip(segment, () => ({
            value: `${fmt.time(part.from)} bis ${part.running ? 'jetzt' : fmt.time(part.to)}`,
            label: `${part.kind === 'break' ? 'Pause' : 'Arbeit'}, ${fmt.duration(minutesBetween(part.from, part.to))} Std.`,
        }));
        element.append(segment);
    });
    return element;
}

function nowMarker(now, range) {
    const hours = toHours(now);
    if (hours < range.from || hours > range.to) return null;
    const share = (hours - range.from) / (range.to - range.from);
    return h('div', {
        class: 'timeline-now',
        style: { left: `calc(var(--label-width, 0px) + (100% - var(--label-width, 0px) - var(--value-width, 0px)) * ${share})` },
        'aria-hidden': 'true',
    }, h('span', { text: fmt.time(now) }));
}

export function legend(withTarget = false) {
    return h('div', { class: 'legend' },
        h('span', {}, h('i'), 'Arbeit'),
        withTarget ? h('span', {}, h('i', { class: 'target' }), 'Soll') : h('span', {}, h('i', { class: 'break' }), 'Pause'),
    );
}

/** One person's day (employee overview). */
export function dayTimeline(state, now) {
    const range = rangeFor([state], now);
    return h('div', { class: 'timeline', role: 'group', 'aria-label': 'Heutiger Tag' },
        axis(range, now),
        h('div', { class: 'timeline-row' }, h('div'), track(state, now, range), h('div')),
        nowMarker(now, range),
    );
}

/**
 * Attendance board: one row per employee with name, status, today's bar and hours.
 * rows: [{first_name, last_name, ...state}]
 */
export function attendanceBoard(rows, now) {
    const range = rangeFor(rows, now);
    return h('div', { class: 'timeline board', style: { '--label-width': '220px', '--value-width': '90px' } },
        axis(range, now),
        rows.map((row) => h('div', { class: 'timeline-row' },
            h('div', { class: 'timeline-label' },
                h('strong', { text: `${row.first_name} ${row.last_name}` }),
                dayBadge(row)),
            track(row, now, range),
            h('div', { class: 'timeline-value', text: row.start ? `${fmt.hours(row.worked_hours)} Std.` : '' }))),
        nowMarker(now, range),
    );
}

/** Week chart: hours per day as columns with a target tick. days: [{date, hours, target, type}] */
export function weekChart(days, todayIso) {
    const scale = Math.max(10, ...days.map((d) => Math.max(d.hours, d.target))) * 1.1;
    const absenceTypes = ['Urlaub', 'Krank', 'Feiertag'];
    return h('div', { class: 'week-chart' }, days.map((day) => {
        const date = new Date(`${day.date}T00:00:00`);
        const weekday = date.toLocaleDateString('de-DE', { weekday: 'short' }).replace('.', '');
        const absent = absenceTypes.includes(day.type);
        const plot = h('div', { class: 'week-plot' },
            day.hours > 0 ? h('div', { class: 'week-bar', style: { height: `${(day.hours / scale) * 100}%` } }) : null,
            absent ? h('div', { class: 'week-bar absence', style: { height: `${(day.target > 0 ? day.target : 8) / scale * 100}%` } }) : null,
            day.target > 0 ? h('div', { class: 'week-target', style: { bottom: `calc(${(day.target / scale) * 100}% - 1px)` } }) : null,
        );
        const column = h('div', { class: `week-col ${day.date === todayIso ? 'today' : ''}` },
            plot,
            h('span', { class: 'week-day', text: weekday }),
            h('span', { class: 'week-hours', text: absent ? day.type : fmt.hours(day.hours) }),
        );
        attachTip(column, () => ({
            value: absent ? day.type : `${fmt.hours(day.hours)} Std.`,
            label: `${date.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long' })}, Soll ${fmt.hours(day.target)} Std.`,
        }));
        return column;
    }));
}

/** Horizontal bars: hours this month per person, with a tick at the target to date. */
export function hoursChart(rows) {
    const scale = Math.max(1, ...rows.map((r) => Math.max(r.hours, r.target_to_date))) * 1.05;
    return h('div', { class: 'hbar-chart' }, rows.map((row) => {
        const bar = h('div', { class: 'hbar-track' },
            h('div', { class: 'hbar-fill', style: { width: `${(row.hours / scale) * 100}%` } }),
            row.target_to_date > 0 ? h('div', { class: 'hbar-target', style: { left: `${(row.target_to_date / scale) * 100}%` } }) : null);
        attachTip(bar, () => ({
            value: `${fmt.hours(row.hours)} Std.`,
            label: `${row.first_name} ${row.last_name}, Soll bis heute ${fmt.hours(row.target_to_date)} Std.`,
        }));
        return h('div', { class: 'hbar-row' },
            h('span', { class: 'hbar-name', text: `${row.first_name} ${row.last_name}` }),
            bar,
            h('span', { class: 'hbar-value', text: `${fmt.hours(row.hours)} / ${fmt.hours(row.target_to_date)}` }));
    }));
}
