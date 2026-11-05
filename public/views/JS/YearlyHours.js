import { currentYear, subjectId, toggleViewMonth, showTableMessage, formatHours } from './Main.js';
import { apiFetch } from './Authentication.js';

export function initializeYearlyView() {
    updateYearlyView();
}

export function updateYearlyView() {
    document.getElementById("currentPeriod").textContent = `Jahr ${currentYear}`;
    loadYear(currentYear);
}

async function loadYear(year) {
    try {
        generateYearlySummaryTable(await apiFetch(`api/user/hours?year=${year}&id=${encodeURIComponent(subjectId)}`));
    } catch (error) {
        generateYearlySummaryTable([]);
        showTableMessage(`Daten konnten nicht geladen werden: ${error.message}`, true);
    }
}

/**
 * The server sends, per month, the hours, overtime and two targets: for the whole
 * month and up to today. Vacation, sick and holiday days are already credited.
 */
function generateYearlySummaryTable(data) {
    const table = document.getElementById("workHoursTable");
    table.innerHTML = "";
    table.className = "work-hours-table yearly";

    const headers = ["Monat", "Soll-Stunden", "Ist-Stunden", "Differenz bis heute", "Überstunden", ""];
    const headerRow = table.createTHead().insertRow();
    headerRow.className = "table-header-row";
    headers.forEach(headerText => {
        const header = document.createElement("th");
        header.className = "table-header";
        header.textContent = headerText;
        headerRow.appendChild(header);
    });

    const byMonth = new Map(data.map((entry) => [Number(entry.Month), entry]));
    const totals = { target: 0, toDate: 0, actual: 0, overtime: 0 };
    const body = table.createTBody();
    const today = new Date();

    for (let month = 1; month <= 12; month++) {
        const entry = byMonth.get(month) ?? {};
        const target = Number(entry.Target ?? 0);
        const targetToDate = Number(entry.TargetToDate ?? 0);
        const actual = Number(entry.TotalHours ?? 0);
        const overtime = Number(entry.Overtime ?? 0);
        const isFuture = new Date(currentYear, month - 1, 1) > today;
        totals.target += target;
        totals.toDate += targetToDate;
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
            const difference = actual - targetToDate;
            differenceCell.textContent = (difference > 0 ? '+' : '') + formatHours(difference);
            differenceCell.className = difference < 0 ? 'negative' : 'positive';
        }
        row.insertCell().textContent = formatHours(overtime);
        [1, 2, 3, 4].forEach((index) => row.cells[index].classList.add('num'));

        const editButton = document.createElement("button");
        editButton.textContent = "Tage ansehen";
        editButton.className = 'secondary small';
        editButton.addEventListener('click', () => toggleViewMonth(month));
        row.insertCell().appendChild(editButton);
    }

    const difference = totals.actual - totals.toDate;
    const footer = table.createTFoot().insertRow();
    footer.className = 'total-row';
    footer.insertCell().textContent = 'Summe';
    footer.insertCell().textContent = formatHours(totals.target);
    footer.insertCell().textContent = formatHours(totals.actual);
    footer.insertCell().textContent = (difference > 0 ? '+' : '') + formatHours(difference);
    footer.insertCell().textContent = formatHours(totals.overtime);
    footer.insertCell();
}
