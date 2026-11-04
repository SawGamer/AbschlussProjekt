import { currentYear, weeklyHours, toggleViewMonth, showTableMessage, formatHours } from './Main.js';
import { apiFetch } from './Authentication.js';
import { getDaysInMonth, targetHours } from './worktime.js';

const urlParams = new URLSearchParams(window.location.search);
const userId = urlParams.get('id');

export function initializeYearlyView() {
    updateYearlyView();
}

export function updateYearlyView() {
    updateYearLabel();
    loadYear(currentYear);
}

function updateYearLabel() {
    document.getElementById("currentPeriod").textContent = `Jahr ${currentYear}`;
    document.getElementById("Title").textContent = `Jahresübersicht: ${currentYear}`;
}

async function loadYear(year) {
    try {
        generateYearlySummaryTable(await apiFetch(`api/user/hours?year=${year}&id=${encodeURIComponent(userId)}`));
    } catch (error) {
        generateYearlySummaryTable([]);
        showTableMessage(`Daten konnten nicht geladen werden: ${error.message}`, true);
    }
}

function generateYearlySummaryTable(data) {
    const table = document.getElementById("workHoursTable");
    table.innerHTML = "";
    table.className = "work-hours-table yearly";

    const headers = ["Monat", "Soll-Stunden", "Ist-Stunden", "Differenz", "Überstunden", ""];
    const headerRow = table.createTHead().insertRow();
    headerRow.className = "table-header-row";
    headers.forEach(headerText => {
        const header = document.createElement("th");
        header.className = "table-header";
        header.textContent = headerText;
        headerRow.appendChild(header);
    });

    const byMonth = new Map(data.map((entry) => [Number(entry.Month), entry]));
    const totals = { target: 0, elapsedTarget: 0, actual: 0, overtime: 0 };
    const body = table.createTBody();
    const today = new Date();

    for (let month = 1; month <= 12; month++) {
        const entry = byMonth.get(month);
        // Vacation, sick and holiday entries are credited, so they lower the target.
        const credit = Number(entry?.AbsenceDays ?? 0) * dailyTarget();
        const target = Math.max(monthlyTarget(month) - credit, 0);
        const elapsedTarget = Math.max(monthlyTarget(month, today) - credit, 0); // only days up to today count
        const actual = Number(entry?.TotalHours ?? 0);
        const overtime = Number(entry?.Overtime ?? 0);
        const isFuture = new Date(currentYear, month - 1, 1) > today;
        totals.target += target;
        totals.elapsedTarget += elapsedTarget;
        totals.actual += actual;
        totals.overtime += overtime;

        const row = body.insertRow();
        row.insertCell().textContent = new Date(currentYear, month - 1).toLocaleString("de-DE", { month: "long" });
        row.insertCell().textContent = formatHours(target);
        row.insertCell().textContent = formatHours(actual);
        const differenceCell = row.insertCell();
        if (isFuture) {
            differenceCell.textContent = '–';
        } else {
            const difference = actual - elapsedTarget;
            differenceCell.textContent = formatHours(difference);
            differenceCell.className = difference < 0 ? 'negative' : 'positive';
        }
        row.insertCell().textContent = formatHours(overtime);

        const editButton = document.createElement("button");
        editButton.textContent = "Bearbeiten";
        editButton.className = 'secondary small';
        editButton.addEventListener('click', () => toggleViewMonth(month));
        row.insertCell().appendChild(editButton);
    }

    const footer = table.createTFoot().insertRow();
    footer.className = 'total-row';
    footer.insertCell().textContent = 'Summe';
    footer.insertCell().textContent = formatHours(totals.target);
    footer.insertCell().textContent = formatHours(totals.actual);
    footer.insertCell().textContent = formatHours(totals.actual - totals.elapsedTarget);
    footer.insertCell().textContent = formatHours(totals.overtime);
    footer.insertCell();
}

/** Target hours of a normal working day, for example 8 for 40 hours per week. */
function dailyTarget() {
    return targetHours(new Date(2024, 0, 2), weeklyHours); // 2 January 2024 was a regular Tuesday
}

/**
 * Target hours for a month: working days times the daily target.
 * With `until`, only days up to and including that date are counted.
 */
function monthlyTarget(month, until = null) {
    let target = 0;
    for (let day = 1; day <= getDaysInMonth(currentYear, month); day++) {
        const date = new Date(currentYear, month - 1, day);
        if (until && date > until) {
            break;
        }
        target += targetHours(date, weeklyHours);
    }
    return target;
}
