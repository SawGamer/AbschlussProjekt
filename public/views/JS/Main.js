import { updateMonthlyView, initializeMonthlyView } from './MonthlyHours.js';
import { updateYearlyView, initializeYearlyView } from './YearlyHours.js';

export let currentYear = new Date().getFullYear();
export let currentMonth = new Date().getMonth() + 1;

// Contracted hours per week, loaded with the employee's data on the home view.
export let weeklyHours = 40;

export let isYearlyView = false;

export function setWeeklyHours(hours) {
    if (Number.isFinite(hours) && hours > 0) {
        weeklyHours = hours;
    }
}

export function initializeView() {
    if (isYearlyView) {
        initializeYearlyView();
    } else {
        initializeMonthlyView();
    }
}

export function attachEventListeners() {
    const prevButton = document.getElementById('prevbutton');
    const nextButton = document.getElementById('nextbutton');
    const toggleViewButton = document.getElementById('toggleView');

    if (prevButton && nextButton && toggleViewButton) {
        prevButton.addEventListener('click', () => changeDate(-1));
        nextButton.addEventListener('click', () => changeDate(1));
        toggleViewButton.addEventListener('click', toggleViewYear);
    } else {
        console.error('Navigation buttons not found');
    }
}

function toggleViewYear() {
    isYearlyView = !isYearlyView;
    updateToggleLabel();
    initializeView();
}

export function toggleViewMonth(month) {
    currentMonth = month;
    isYearlyView = false;
    updateToggleLabel();
    initializeView();
}

function updateToggleLabel() {
    const button = document.getElementById('toggleView');
    if (button) {
        button.textContent = isYearlyView ? 'Monatsansicht' : 'Jahresansicht';
    }
}

function changeDate(delta) {
    if (isYearlyView) {
        changeYear(delta);
    } else {
        changeMonth(delta);
    }
}

function changeMonth(delta) {
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

function changeYear(delta) {
    currentYear += delta;
    updateYearlyView();
}

export function setActiveNav(id) {
    document.querySelectorAll('.menulink').forEach((link) => {
        link.classList.toggle('active', link.id === id);
    });
}

export function createWorkHoursView() {
    setActiveNav('zeiterfassungLink');

    const container = document.getElementById('contentPlaceholder');
    container.className = 'container';
    container.innerHTML = '';

    const titleDiv = document.createElement('div');
    const title = document.createElement('h2');
    title.id = 'Title';
    titleDiv.appendChild(title);
    container.appendChild(titleDiv);

    const navDiv = document.createElement('div');
    navDiv.id = 'nav';
    navDiv.className = 'navtracker';

    const prevButton = createButton('prevbutton', '‹ Zurück');
    const nextButton = createButton('nextbutton', 'Weiter ›');
    const toggleViewButton = createButton('toggleView', isYearlyView ? 'Monatsansicht' : 'Jahresansicht');
    toggleViewButton.classList.add('secondary');

    const currentPeriodSpan = document.createElement('span');
    currentPeriodSpan.id = 'currentPeriod';

    navDiv.appendChild(prevButton);
    navDiv.appendChild(currentPeriodSpan);
    navDiv.appendChild(nextButton);
    navDiv.appendChild(toggleViewButton);
    container.appendChild(navDiv);

    const message = document.createElement('div');
    message.id = 'tableMessage';
    message.className = 'message';
    message.hidden = true;
    container.appendChild(message);

    const workHoursContainer = document.createElement('div');
    workHoursContainer.className = 'work-hours-table-container';

    const table = document.createElement('table');
    table.id = 'workHoursTable';
    workHoursContainer.appendChild(table);

    container.appendChild(workHoursContainer);
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
    showTableMessage.timer = setTimeout(() => { message.hidden = true; }, 4000);
}

export function formatHours(value) {
    return Number(value || 0).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function createButton(id, text) {
    const button = document.createElement('button');
    button.id = id;
    button.textContent = text;
    return button;
}
