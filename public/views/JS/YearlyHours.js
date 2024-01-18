import { isfeiertag } from '../../../Config/feiertage.js';
import { currentYear, currentday, currentMonth, currentdate, toggleViewMonth } from './Main.js';
import { getDaysInMonth } from './MonthlyHours.js';

const urlParams = new URLSearchParams(window.location.search);
const userId = urlParams.get('id');
let diff = 0

let usersollstunden
export function initializeYearlyView() {
    updateYearLabel();
    request2(currentYear);
}

export function updateYearlyView() {
    updateYearLabel();
    request2(currentYear);
}

function updateYearLabel() {
    document.getElementById("currentPeriod").innerText = `Jahr: ${currentYear}`;
    title();
}

function title() {
    const title = document.getElementById("Title");
    title.textContent = "Jahresübersicht die Arbeitsstunden für " + currentYear;
}



const request1 = async () => {
    const token = sessionStorage.getItem('jwt')
    fetch(`api/user/getUserprofile?id=${userId}`, {
        headers: {
            'Authorization': `${token}`,
        }
    }).then(response => response.json())
        .then(data => {

            usersollstunden = data['ShouldHours']
        })
}

const request2 = async (year) => {
    const token = sessionStorage.getItem('jwt')

    fetch(`api/user/LogView?year=${year}&id=${userId}`, {
        headers: {
            'Authorization': `${token}`,
        }
    }).then(response => response.json())
        .then(data => generateYearlySummaryTable(data))
        .catch(error => {
            console.error(error);
            generateYearlySummaryTable()
        });

}




function generateYearlySummaryTable(data) {
    const table = document.getElementById("workHoursTable");
    table.innerHTML = "";
    table.className = "work-hours-table";


    const headers = ["Monatsname", "Soll Stunden", "Ist Stunden", "Differenz", "Überstunden", "Bearbeiten"];
    const headerRow = table.insertRow();
    headerRow.className = "table-header-row";

    headers.forEach(headerText => {
        const header = document.createElement("th");
        header.appendChild(document.createTextNode(headerText));
        headerRow.appendChild(header);
        header.className = "table-header";

    });

    for (let month = 0; month < 12; month++) {

        const row = table.insertRow();

        const monthNameCell = row.insertCell(0);

        monthNameCell.textContent = new Date(currentYear, month).toLocaleString("de-DE", { month: "long" });


        // Add cells for Soll Stunden, Ist Stunden, Differenz, Überstunden
        const sollStundenCell = row.insertCell(1);
        sollStundenCell.innerHTML = sollJährlichStunden(month)

        const istStundenCell = row.insertCell(2);
        istStundenCell.innerHTML = 0




        const differenzCell = row.insertCell(3);



        const ueberstundenCell = row.insertCell(4);
        ueberstundenCell.innerHTML = 0


        data.map(x => {
            if (x['Month'] === monthNameCell.textContent) {
                istStundenCell.innerHTML = Number(x['TotalHours']).toFixed(2)
                ueberstundenCell.innerHTML = Number(x['Overtime']).toFixed(2)
            }
        })
        const d = Number(istStundenCell.innerHTML - sollStundenCell.innerHTML)
        differenzCell.innerHTML = d



        const editCell = row.insertCell(5);
        const editButton = document.createElement("button");
        editButton.textContent = "Bearbeiten";
        editButton.onclick = function () {
            toggleViewMonth(month + 1)
        };
        editCell.appendChild(editButton);
    }
}

function sollJährlichStunden(monat) {
    const options = { weekday: "long" };

    let off = 0
    for (let i = 1; i <= getDaysInMonth(currentYear, monat + 1); i++) {

        const daytag = new Date(currentYear, monat, i)
        const day = new Intl.DateTimeFormat("de-DE", options).format(daytag)
            ;
        const dateofrow = new Date(
            currentYear
            + "-" +
            (Number(monat + 1) < 10 ? "0" + Number(monat + 1) : Number(monat + 1))
            + "-" +
            (i < 10 ? "0" + i : i))

        if (day === "Sonntag" || day === "Samstag" || isfeiertag(dateofrow)) {
            off += 1
        }

    }
    return ((getDaysInMonth(currentYear, monat + 1) * 8) - (off * 8))

}
