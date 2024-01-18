import { updateMonthlyView, initializeMonthlyView } from './MonthlyHours.js';
import { updateYearlyView, initializeYearlyView } from './YearlyHours.js';

export let currentYear = new Date().getFullYear();
export let currentMonth = new Date().getMonth() + 1;
export let currentday = new Date().getDay();
export let currentdate = new Date();


export let isYearlyView = false;



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
    initializeView();
}
export function toggleViewMonth(month) {
    currentMonth = month
    isYearlyView = !isYearlyView;
    initializeView();
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



export function createWorkHoursView() {
    const navbutton = document.getElementById('homeLink');
    navbutton.style.backgroundColor = "";
    const navbutton2 = document.getElementById('zeiterfassungLink');
    navbutton2.style.backgroundColor = "#5486B8";





    const container = document.getElementById('contentPlaceholder');
    container.className = 'container'
    container.innerHTML = '';


    const titleDiv = document.createElement('div');
    const title = document.createElement('h3');
    title.id = 'Title';
    titleDiv.appendChild(title);
    container.appendChild(titleDiv);

    const navDiv = document.createElement('div');
    navDiv.id = 'nav';
    navDiv.className = 'navtracker';

    const prevButton = createButton('prevbutton', 'Previous');
    const nextButton = createButton('nextbutton', 'Next');
    const toggleViewButton = createButton('toggleView', 'Toggle Year/Month View');

    const currentPeriodSpan = document.createElement('span');
    currentPeriodSpan.id = 'currentPeriod';

    navDiv.appendChild(prevButton);
    navDiv.appendChild(currentPeriodSpan);
    navDiv.appendChild(nextButton);
    navDiv.appendChild(toggleViewButton);
    container.appendChild(navDiv);

    const workHoursContainer = document.createElement('div');
    workHoursContainer.className = 'work-hours-table-container';

    const table = document.createElement('table');
    table.id = 'workHoursTable';
    workHoursContainer.appendChild(table);

    container.appendChild(workHoursContainer);
}

function createButton(id, text) {
    const button = document.createElement('button');
    button.id = id;
    button.textContent = text;
    return button;
}

