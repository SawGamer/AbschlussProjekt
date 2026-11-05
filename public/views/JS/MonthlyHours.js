import { currentYear, currentMonth, weeklyHours, subjectId, showTableMessage, formatHours } from './Main.js';
import { apiFetch } from './Authentication.js';
import { getDaysInMonth, holidayName, isWeekend, targetHours } from './worktime.js';

const DAY_TYPES = ["", "FehlTag", "Normal", "Urlaub", "Krank", "Feiertag", "Wochenende", "Notfall"];
const DEFAULT_PAUSE_MINUTES = 30;

export function initializeMonthlyView() {
    updateMonthlyView();
}

export function updateMonthlyView() {
    updateMonthLabel();
    fetchDataFromAPI(currentYear, currentMonth, subjectId);
}

function updateMonthLabel() {
    const label = new Date(currentYear, currentMonth - 1).toLocaleString('de-DE', { month: 'long', year: 'numeric' });
    document.getElementById("currentPeriod").textContent = label;
}

async function fetchDataFromAPI(year, month, employeeId) {
    try {
        const data = await apiFetch(`api/user/hours?year=${year}&month=${month}&id=${encodeURIComponent(employeeId)}`);
        generateMonthlySummaryTable(data);
    } catch (error) {
        generateMonthlySummaryTable([]);
        showTableMessage(`Daten konnten nicht geladen werden: ${error.message}`, true);
    }
}

function generateMonthlySummaryTable(data) {
    const table = document.getElementById("workHoursTable");
    table.innerHTML = "";
    table.className = "work-hours-table monthly";

    const headers = ["Datum", "Wochentag", "Beginn", "Ende", "Pause (Min.)", "Überstunden", "Gesamtstunden", "Art", "Kommentar"];
    const headerRow = table.createTHead().insertRow();
    headerRow.className = "table-header-row";
    headers.forEach(headerText => {
        const header = document.createElement("th");
        header.className = "table-header";
        header.textContent = headerText;
        headerRow.appendChild(header);
    });

    const byDay = new Map(data.map((entry) => [Number(entry.Day), entry]));
    const body = table.createTBody();

    for (let day = 1; day <= getDaysInMonth(currentYear, currentMonth); day++) {
        const date = new Date(currentYear, currentMonth - 1, day);
        const entry = byDay.get(day);
        const holiday = holidayName(date);

        const row = body.insertRow();
        row.dataset.day = day;
        if (isWeekend(date)) row.classList.add('weekend');
        if (holiday) {
            row.classList.add('holiday');
            row.title = holiday;
        }

        row.insertCell().textContent = date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
        const weekdayCell = row.insertCell();
        weekdayCell.textContent = date.toLocaleDateString('de-DE', { weekday: 'long' });
        if (holiday) {
            const name = document.createElement('small');
            name.className = 'holiday-name';
            name.textContent = holiday;
            weekdayCell.appendChild(name);
        }

        const start = timeInput(entry?.Start);
        const end = timeInput(entry?.End);
        const pause = document.createElement('input');
        pause.type = 'number';
        pause.min = 0;
        pause.max = 1440;
        pause.step = 1;
        // Days without an entry show the usual break as a placeholder; it applies once times are entered.
        pause.value = entry ? entry.pause : '';
        if (!entry && targetHours(date, weeklyHours) > 0) pause.placeholder = String(DEFAULT_PAUSE_MINUTES);
        pause.addEventListener('keyup', () => enforceMinMax(pause));

        const overtime = document.createElement('span');
        overtime.className = 'overtime';
        overtime.textContent = formatHours(entry?.overtime);
        overtime.dataset.value = entry?.overtime ?? 0;

        const total = document.createElement('span');
        total.className = 'actual';
        total.textContent = formatHours(entry?.ActualHours);
        total.dataset.value = entry?.ActualHours ?? 0;

        const kind = document.createElement('select');
        DAY_TYPES.forEach((option) => kind.add(new Option(option || '–', option)));
        kind.value = entry?.type ?? '';

        const comment = document.createElement('input');
        comment.type = 'text';
        comment.maxLength = 255;
        comment.value = entry?.Komment ?? '';

        [start, end, pause, overtime, total, kind, comment].forEach((element) => {
            row.insertCell().appendChild(element);
            if (element.tagName !== 'SPAN') {
                element.addEventListener('change', () => updateAndSave(row));
            }
        });
    }

    const footer = table.createTFoot().insertRow();
    footer.className = 'total-row';
    footer.insertCell().textContent = 'Summe';
    footer.cells[0].colSpan = 5;
    footer.insertCell().id = 'totalOvertime';
    footer.insertCell().id = 'totalHours';
    footer.insertCell().colSpan = 2;
    updateTotals();
}

/** Saves one row. The server calculates hours and overtime and sends them back. */
async function updateAndSave(row) {
    const day = Number(row.dataset.day);
    const [start, end, pause] = row.querySelectorAll('input');
    const kind = row.querySelector('select');
    const comment = row.querySelectorAll('input')[3];
    const pad = (n) => String(n).padStart(2, '0');

    row.classList.remove('save-error');
    try {
        const result = await apiFetch('api/user/hours', {
            method: 'POST',
            body: {
                id: subjectId,
                date: `${currentYear}-${pad(currentMonth)}-${pad(day)}`,
                start_time: start.value,
                end_time: end.value,
                pause: pause.value === '' ? Number(pause.placeholder || 0) * (start.value && end.value ? 1 : 0) : Number(pause.value),
                type: kind.value,
                comment: comment.value,
            },
        });
        setValue(row.querySelector('.actual'), result.actual_hours);
        setValue(row.querySelector('.overtime'), result.overtime);
        updateTotals();
        row.classList.add('saved');
        setTimeout(() => row.classList.remove('saved'), 1200);
    } catch (error) {
        row.classList.add('save-error');
        showTableMessage(`Tag ${day} wurde nicht gespeichert: ${error.message}`, true);
    }
}

function setValue(element, value) {
    element.dataset.value = value;
    element.textContent = formatHours(value);
}

/** Sum row under the table. */
export function updateTotals() {
    const sum = (selector) => [...document.querySelectorAll(`#workHoursTable ${selector}`)]
        .reduce((total, element) => total + Number(element.dataset.value || 0), 0);
    document.getElementById('totalHours').textContent = formatHours(sum('.actual'));
    document.getElementById('totalOvertime').textContent = formatHours(sum('.overtime'));
}

function timeInput(value) {
    const input = document.createElement('input');
    input.type = 'time';
    input.step = 60;
    input.value = value ?? '';
    return input;
}

function enforceMinMax(el) {
    if (el.value !== "") {
        if (parseInt(el.value) < parseInt(el.min)) {
            el.value = el.min;
        }
        if (parseInt(el.value) > parseInt(el.max)) {
            el.value = el.max;
        }
    }
}
