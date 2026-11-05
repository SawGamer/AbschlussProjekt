// Admin panel: dashboard with the attendance board, employees, times, vacation, activity log.

import { fetchAndSetJwtToken, logout, currentClaims, apiFetch } from './Authentication.js';
import { createWorkHoursView, setSubject } from './Main.js';
import { h, fmt, pageHead, panel, figures, field, startRouter, initThemeButton, routeHref } from './ui.js';
import { attendanceBoard, hoursChart, legend } from './timeline.js';
import { renderEmployees } from './admin-employees.js';
import { renderLeaveRequests, decideLeave } from './admin-leave.js';
import { renderAudit } from './admin-audit.js';

const REFRESH_MS = 60_000;

document.addEventListener('DOMContentLoaded', async () => {
    await fetchAndSetJwtToken();
    const claims = currentClaims();
    if (!claims.admin) {
        window.location.href = `views/user_view.php?id=${encodeURIComponent(claims.id)}`;
        return;
    }

    document.getElementById('navName').textContent = claims.name ?? 'Admin';
    document.getElementById('ownTimesLink').href = `views/user_view.php?id=${encodeURIComponent(claims.id)}`;
    document.getElementById('logout').addEventListener('click', logout);
    initThemeButton(document.getElementById('themeButton'));
    window.addEventListener('wf:pending', (event) => setPendingCount(event.detail));
    apiFetch('api/admin/leave?status=pending').then((requests) => setPendingCount(requests.length)).catch(() => {});

    startRouter({
        dashboard: renderDashboard,
        mitarbeitende: renderEmployees,
        zeiten: renderTimes,
        urlaub: renderLeaveRequests,
        protokoll: renderAudit,
    }, 'dashboard', document.getElementById('contentPlaceholder'));
});

/** Shows the number of open vacation requests next to the nav link. */
export function setPendingCount(count) {
    const badge = document.getElementById('pendingCount');
    badge.textContent = String(count);
    badge.hidden = count === 0;
    badge.setAttribute('aria-label', `${count} offene Anträge`);
}

// --- Dashboard ------------------------------------------------------------------

async function renderDashboard(container) {
    const stamp = h('span', { class: 'hint', 'aria-live': 'polite' });
    const refresh = h('button', { class: 'secondary', text: 'Aktualisieren' });
    const content = h('div');
    container.append(pageHead('Heute', fmt.dateLong(), stamp, refresh), content);

    const load = async () => {
        content.style.opacity = '0.6';
        try {
            const data = await apiFetch('api/admin/overview');
            stamp.textContent = `Stand ${fmt.time(data.now)} Uhr`;
            renderOverview(content, data, load);
            setPendingCount(data.pending_leave.length);
        } finally {
            content.style.opacity = '';
        }
    };
    refresh.addEventListener('click', load);
    await load();

    // The board follows the day on its own while the dashboard is open.
    const timer = setInterval(load, REFRESH_MS);
    return () => clearInterval(timer);
}

function renderOverview(content, data, reload) {
    const board = data.board;
    const count = (status) => board.filter((row) => row.status === status).length;
    const present = count('working') + count('paused');
    const monthName = new Date().toLocaleString('de-DE', { month: 'long' });

    const boardPanel = h('section', { class: 'panel' },
        h('div', { class: 'panel-head' },
            h('div', {}, h('h2', { text: 'Anwesenheit' }), h('p', { text: 'Arbeitszeit und Pausen heute, der Strich zeigt die aktuelle Uhrzeit' })),
            legend()),
        attendanceBoard(board, data.now),
    );

    content.replaceChildren(
        figures([
            { label: 'Anwesend', value: `${present} von ${board.length}`, note: count('paused') ? `${count('paused')} davon in Pause` : 'niemand in Pause' },
            { label: 'Feierabend', value: String(count('done')), note: 'heute schon ausgestempelt' },
            { label: 'Abwesend', value: String(count('absent')), note: 'Urlaub oder krank' },
            { label: 'Offene Anträge', value: String(data.pending_leave.length), note: 'Urlaub zur Freigabe' },
        ]),
        boardPanel,
        h('div', { class: 'columns' },
            panel(`Stunden im ${monthName}`, 'Der Strich zeigt das Soll bis heute', hoursChart(data.month)),
            pendingPanel(data.pending_leave, reload),
        ),
    );
}

function pendingPanel(requests, reload) {
    if (!requests.length) {
        return panel('Offene Urlaubsanträge', null, h('p', { class: 'empty', text: 'Keine offenen Anträge. Neue Anträge erscheinen hier.' }));
    }
    const decide = async (request, decision) => {
        if (await decideLeave(request, decision)) await reload();
    };
    return panel('Offene Urlaubsanträge', `${requests.length} warten auf Freigabe`,
        h('div', { class: 'request-list' }, requests.map((request) => h('div', { class: 'request' },
            h('div', {},
                h('strong', { text: `${request.first_name} ${request.last_name}` }),
                h('p', { text: `${fmt.range(request.start_date, request.end_date)}, ${fmt.days(request.days)}` }),
                request.comment ? h('p', { class: 'hint', text: request.comment }) : null),
            h('div', { class: 'row-actions' },
                h('button', { class: 'small', text: 'Genehmigen', on: { click: () => decide(request, 'approve') } }),
                h('button', { class: 'secondary small', text: 'Ablehnen', on: { click: () => decide(request, 'reject') } })),
        ))),
        h('p', { class: 'chart-caption' }, h('a', { href: routeHref('urlaub'), text: 'Alle Anträge ansehen' })),
    );
}

// --- Times of one employee -----------------------------------------------------

async function renderTimes(container, id) {
    const employees = await apiFetch('api/admin/employees');
    const choices = employees.filter((e) => e.active || String(e.id) === id);
    const selected = choices.find((e) => String(e.id) === id)
        ?? choices.find((e) => !e.is_admin)
        ?? choices[0];
    setSubject(selected.id, selected.weekly_hours);

    const select = h('select', { 'aria-label': 'Mitarbeiter/in auswählen', on: { change: (e) => { location.hash = `#/zeiten/${e.target.value}`; } } },
        choices.map((e) => h('option', { value: String(e.id), text: `${e.first_name} ${e.last_name}` })));
    select.value = String(selected.id);

    container.append(pageHead(
        `Zeiten von ${selected.first_name} ${selected.last_name}`,
        'Korrekturen werden sofort gespeichert und im Protokoll vermerkt.',
        field('Mitarbeiter/in', select),
    ));
    createWorkHoursView(container);
}
