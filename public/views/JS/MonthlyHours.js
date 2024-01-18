import { currentYear, currentMonth, currentday, currentdate } from './Main.js';
import { isfeiertag, isUrlaub, addUrlaub } from '../../../Config/feiertage.js';


let userId;
const urlParams = new URLSearchParams(window.location.search);
userId = urlParams.get('id');




export function initializeMonthlyView() {
    updateMonthLabel();
    fetchDataFromAPI(currentYear, currentMonth, userId);
}

export function updateMonthlyView() {

    updateMonthLabel();
    fetchDataFromAPI(currentYear, currentMonth, userId);
}

function updateMonthLabel() {
    document.getElementById("currentPeriod").innerText = `${currentYear}-${currentMonth}`;
    title();
}

function title() {
    const title = document.getElementById("Title");
    title.textContent = "Monatlichesübersicht die Arbeitsstunden für " + currentYear;
}

function fetchDataFromAPI(year, month, employee_id) {
    const token = sessionStorage.getItem('jwt');
    fetch(`api/user/LogView?year=${year}&month=${month}&id=${employee_id}`
        , {
            headers: {
                'Authorization': `${token}`
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            return response.json();
        })
        .then(data => {
            generateMonthlySummaryTable(data);

        })
        .catch(error => {
            console.error("Error fetching data:", error);
        });
}


function generateMonthlySummaryTable(data) {
    const table = document.getElementById("workHoursTable");
    table.innerHTML = "";
    table.className = "work-hours-table";

    const headers = ["Datum", "WochenTag", "Start", "Ende", "Pause", "Überstunden", "Gesamtstunden", "Type", "Kommentar"];
    const headerRow = table.insertRow();
    headerRow.className = "table-header-row";
    headers.forEach(headerText => {
        const header = document.createElement("th");
        header.className = "table-header";
        header.appendChild(document.createTextNode(headerText));
        headerRow.appendChild(header);
    });
    const options = { weekday: "long" };
    for (let i = 1; i <= getDaysInMonth(currentYear, currentMonth - 1); i++) {
        const row = table.insertRow();

        const dateCell = row.insertCell(0);
        const dayCell = row.insertCell(1);
        dateCell.textContent = (i < 10 ? "0" + i : i) + "-" + (Number(currentMonth) < 10 ? "0" + Number(currentMonth) : Number(currentMonth - 1 + 1));
        const daytag = new Date(currentYear, currentMonth - 1, i);
        dayCell.textContent = new Intl.DateTimeFormat("en-US", options).format(daytag);

        const startHourCell = row.insertCell(2);
        const endHoursCell = row.insertCell(3);
        const PauseCell = row.insertCell(4);
        const OvertimeCell = row.insertCell(5);
        const GesamtstundenCell = row.insertCell(6);
        const kindOfHourCell = row.insertCell(7);
        const KommentarCell = row.insertCell(8);
        PauseCell.innerHTML = 30


        try {
            data.map((x) => {
                if (x["Day"] === i) {
                    startHourCell.innerHTML = x["Start"]
                    endHoursCell.innerHTML = x["End"]

                    PauseCell.innerHTML = x["pause"]
                    KommentarCell.textContent = x["Komment"] ? x["Komment"] : "-";


                }
            })
        }
        catch { error => { console.error("Error try data:", error); } }


        let istStunden = 0;
        [startHourCell, endHoursCell, PauseCell, KommentarCell].forEach((cell, cellIndex) => {
            const input = document.createElement("input");
            if (cell == startHourCell || cell == endHoursCell) {
                input.type = "time"
                input.step = "600"

            }
            else if (cell == PauseCell) {
                input.type = "number"
                input.min = 0
                input.step = 1
                input.onkeyup = enforceMinMax(PauseCell.innerHTML)
            }

            else input.type = "text";
            input.value = cell.textContent;
            input.onchange = function () {
                updateAndSave(row.rowIndex);
            };

            cell.innerHTML = "";
            cell.appendChild(input);
        })

        const gesamtstundenInput = document.createElement("lable");
        gesamtstundenInput.type = "text";
        gesamtstundenInput.innerHTML = 0
        gesamtstundenInput.onchange = function () {
            updateAndSave(row.rowIndex);
        };

        GesamtstundenCell.innerHTML = "";
        GesamtstundenCell.appendChild(gesamtstundenInput);

        const kindOfHourCellselect = document.createElement("select");
        kindOfHourCellselect.id = "kindselect";
        kindOfHourCellselect.onchange = function () {
            updateAndSave(row.rowIndex);
        };

        kindOfHourCell.innerHTML = "";
        kindOfHourCell.appendChild(kindOfHourCellselect);

        ["", "FehlTag", "Normal", "Urlaub", "Krank", "Feiertag", "Wochenende", "Notfall"].forEach((option, optionindex) => {

            const o = document.createElement("option")
            o.value = option
            o.text = option
            o.focus = null
            kindOfHourCellselect.appendChild(o)
        })

        try {
            data.map((x) => {
                if (x["Day"] === i) {
                    kindOfHourCellselect.value = x["type"]




                    istStunden = Number(diff_hours(
                        x["End"],
                        x["Start"]))







                    OvertimeCell.textContent = x["overtime"]
                    gesamtstundenInput.innerHTML = x["ActualHours"]
                    istStunden = 0

                }
            })
        }
        catch { error => console.log(error) }
    } total()
}
function updateAndSave(rowIndex) {
    const row = document.getElementById("workHoursTable").rows[rowIndex];
    const startHourCell = row.cells[2];
    const endHoursCell = row.cells[3];
    const pauseCell = row.cells[4];
    const overtimeCell = row.cells[5];
    const kindOfHourCell = row.cells[7];
    const gesamtstundenCell = row.cells[6];
    const kommentarCell = row.cells[8];

    console.log("day of the month: ", rowIndex)


    const startInput = row.cells[2].querySelector("input");
    const endInput = row.cells[3].querySelector("input");
    const pauseInput = row.cells[4].querySelector("input");
    const kindOfHourInput = row.cells[7].querySelector("select");
    const kommentarInput = row.cells[8].querySelector("input");
    const gesamtstundenInput = row.cells[6].querySelector("lable");

    const startInputValue = startInput.value;
    const endInputValue = endInput.value;
    const pauseInputValue = (Number(pauseInput.value));
    const kindOfHourInputValue = kindOfHourInput.value;
    const kommentarInputValue = kommentarInput.value;

    //IST-Stunden !
    const istStunden = Number(diff_hours(
        endInputValue ? endInputValue : currentdate,
        startInputValue ? startInputValue : currentdate))


    console.log("soll stunden for day : ", rowIndex, Number(sollstunden({ day: rowIndex })), isfeiertag(new Date(currentYear + "-" + currentMonth + "-" + rowIndex)))
    const OvertimeValue =
        istStunden - (pauseInputValue / 60).toFixed(2) > Number(sollstunden({ day: rowIndex })) ?
            (istStunden - (pauseInputValue / 60).toFixed(2) - Number(sollstunden({ day: rowIndex }))).toFixed(2) : 0;

    console.log(gesamtstundenCell)

    overtimeCell.textContent = OvertimeValue;

    gesamtstundenCell.innerHTML = istStunden - (pauseInputValue / 60).toFixed(2) > 0 ? (istStunden - (pauseInputValue / 60).toFixed(2)).toFixed(2) : 0

    const token = sessionStorage.getItem('jwt');

    fetch("api/user/log-hours", {
        method: "POST",
        headers: {
            'Authorization': `${token}`,
            "Content-Type": "text/json"
        },
        body: JSON.stringify({
            "date": currentYear + "-" + currentMonth + "-" + rowIndex
            , "actual_hours": Number(gesamtstundenCell.innerHTML)
            , "start_time": startInputValue
            , "end_time": endInputValue
            , "comment": kommentarInputValue
            , "overtime": OvertimeValue
            , "type": kindOfHourInputValue
            , "pause": pauseInputValue
            , "id": userId
        })
    })
        .then(response => response.json())
        .then(data => console.log("Changes saved:", data))
        .catch(error => console.error("Error saving changes:", error));
}
export function getDaysInMonth(year, month) {
    return new Date(year, month, 0).getDate();
}

function diff_hours(dt2, dt1) {


    try {
        dt2 = dt2.getTime()
        dt1 = dt1.getTime()
        console.log(dt1, dt2)
    }
    catch (error) {


        const sHour = dt1.split(":")[0]
        const sMinute = dt1.split(":")[1]
        const eHour = dt2.split(":")[0]
        const eMinute = dt2.split(":")[1]
        if (sHour > eHour || eHour == "00") {
            dt2 = new Date(currentYear, currentMonth - 1, (Number(currentday) + 1), eHour, eMinute)
            dt1 = new Date(currentYear, currentMonth - 1, currentday, sHour, sMinute)



            dt2 = dt2.getTime()
            dt1 = dt1.getTime()
        }
        else {
            dt2 = new Date(currentYear, currentMonth - 1, currentday, eHour, eMinute)
            dt1 = new Date(currentYear, currentMonth - 1, currentday, sHour, sMinute)



            dt2 = dt2.getTime()
            dt1 = dt1.getTime()

        }


    }



    let diff = (dt2 - dt1) / 1000;
    diff /= (60 * 60);

    return Math.abs((diff).toFixed(2));

}
function sollstunden({ date, employee_type, day }) {

    console.log(day)

    let normalsollstunden = 8
    const dateofrow = new Date(currentYear + "-" + currentMonth + "-" + day)
    const options = { weekday: "long" };
    const daytag = new Date(currentYear, currentMonth - 1, day)

    console.log(currentYear, currentMonth - 1, day)
    const dayname = new Intl.DateTimeFormat("de-DE", options).format(daytag)
    console.log(dayname)
    try {
        if (dayname === "Sonntag" || dayname === "Samstag") {
            //     console.log("WWTF !")
            normalsollstunden = 0
        }

    } catch { console.log(error) }

    if (isfeiertag(dateofrow)) { normalsollstunden = 0 }
    console.log(isfeiertag(dateofrow))


    return normalsollstunden
}
export function total(month) {
    let t = 0

    for (let i = 1; i <= getDaysInMonth(currentYear, month - 1); i++) {
        const row = document.getElementById("workHoursTable").rows[i];
        const context = Number(row.cells[6].textContent)

        t += context

    }
    return t

}
function enforceMinMax(el) {
    if (el.value != "") {
        if (parseInt(el.value) < parseInt(el.min)) {
            el.value = el.min;
        }
        if (parseInt(el.value) > parseInt(el.max)) {
            el.value = el.max;
        }
    }
}