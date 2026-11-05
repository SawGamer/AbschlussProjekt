// Time tables (month and year view) with navigation and CSV export.
// Used by the employee panel for their own hours and by the admin panel for anyone's.

import { updateMonthlyView, initializeMonthlyView } from './MonthlyHours.js';
import { updateYearlyView, initializeYearlyView } from './YearlyHours.js';
import { apiRaw } from './Authentication.js';
import { h, toast, download } from './ui.js';

export let currentYear = new Date().getFullYear();
export let currentMonth = new Date().getMonth() + 1;

// Whose hours are shown, and their contracted hours per week.
export let subjectId = new URLSearchParams(window.location.search).get('id');
export let weeklyHours = 40;

export let isYearlyView = false;

export function setSubject(id, hours) {
    subjectId = String(id);
    setWeeklyHours(Number(hours));
}

export function setWeeklyHours(hours) {
    if (Number.isFinite(hours) && hours > 0) {
        weeklyHours = hours;
    }
}

export function initializeView() {
    updateViewButtons();
    if (isYearlyView) {
        initializeYearlyView();
    } else {
        initializeMonthlyView();
    }
}

function toggleView() {
    isYearlyView = !isYearlyView;
    initializeView();
}

export function toggleViewMonth(month) {
    currentMonth = month;
    isYearlyView = false;
    initializeView();
}

function updateViewButtons() {
    const toggle = document.getElementById('toggleView');
    const exportButton = document.getElementById('exportButton');
    if (toggle) toggle.textContent = isYearlyView ? 'Monatsansicht' : 'Jahresansicht';
    if (exportButton) exportButton.hidden = isYearlyView;
}

function changeDate(delta) {
    if (isYearlyView) {
        currentYear += delta;
        updateYearlyView();
        return;
    }
    currentMonth += delta;
    if (currentMonth > 12) {
        currentMonth = 1;
        currentYear++;
    } else if (currentMonth < 1) {
        currentMonth = 12;
        currentYear--;
    }
    updateMonthlyView();
}

async function exportMonth() {
    try {
        const response = await apiRaw(`api/user/export?id=${encodeURIComponent(subjectId)}&year=${currentYear}&month=${currentMonth}`);
        await download(response, `arbeitszeiten-${currentYear}-${currentMonth}.csv`);
        toast('Export heruntergeladen.');
    } catch (error) {
        toast(error.message, true);
    }
}

/** Builds navigation, message area and table into the container, then loads the data. */
export function createWorkHoursView(container) {
    container.append(
        h('div', { id: 'nav', class: 'navtracker' },
            h('button', { id: 'prevbutton', class: 'secondary', 'aria-label': 'Vorheriger Zeitraum', text: '‹ Zurück', on: { click: () => changeDate(-1) } }),
            h('span', { id: 'currentPeriod', 'aria-live': 'polite' }),
            h('button', { id: 'nextbutton', class: 'secondary', 'aria-label': 'Nächster Zeitraum', text: 'Weiter ›', on: { click: () => changeDate(1) } }),
            h('span', { class: 'spacer' }),
            h('button', { id: 'exportButton', class: 'secondary', text: 'Als CSV exportieren', on: { click: exportMonth } }),
            h('button', { id: 'toggleView', class: 'secondary', on: { click: toggleView } }),
        ),
        h('div', { id: 'tableMessage', class: 'message', hidden: true }),
        h('div', { class: 'work-hours-table-container' }, h('table', { id: 'workHoursTable' })),
    );
    initializeView();
}

/** Shows a short status or error message above the table. */
export function showTableMessage(text, isError = false) {
    const message = document.getElementById('tableMessage');
    if (!message) {
        return;
    }
    message.textContent = text;
    message.classList.toggle('error', isError);
    message.hidden = false;
    clearTimeout(showTableMessage.timer);
    showTableMessage.timer = setTimeout(() => { message.hidden = true; }, 5000);
}

export function formatHours(value) {
    return Number(value || 0).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
