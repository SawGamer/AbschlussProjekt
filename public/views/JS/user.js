import { animateElement } from "./Animations.js"

let timer = null;
let worktime = 0;
let sessionStartTime = null;
let pausetime = 0;
let pauseon = null
let username;

const urlParams = new URLSearchParams(window.location.search);
const userId = urlParams.get('id');


const month = new Date().getMonth().toString()
const day = new Date().getDate()
const year = new Date().getFullYear()

export function setupEventListeners() {
    const startButton = document.getElementById('startButton');
    const pauseButton = document.getElementById('pauseButton');
    const stopButton = document.getElementById('stopButton');
    const manualSubmit = document.getElementById('manualSubmit');

    if (startButton && pauseButton && stopButton && manualSubmit) {
        startButton.addEventListener('click', startTimer);
        pauseButton.addEventListener('click', pauseTimer);
        stopButton.addEventListener('click', stopTimer);
        manualSubmit.addEventListener('click', submitManualTime);


        fetchuser();
    } else {
        console.error("Some elements did not load properly.");
    }
}


function fetchuser() {
    const token = sessionStorage.getItem("jwt");
    sessionStartTime = new Date();
    postData(`api/user/get-user-data?id=${userId}`, token)
        .then(user => {
            username = user[0]['Username']

            document.getElementById("name").innerText = "Willkommen Zurück, " + username
            if (user[0]['start']) {
                username = user['Username']
                const end = user[0]['end'] || null
                //sessionStartTime = new Date(`${year}-${month.padStart(2, '0')}-${day}T${start}`)
                displayLoggedTime(user)

                document.getElementById("pauseButton").hidden = true
                document.getElementById("manualEntry").innerHTML = ''

                if (timer) {
                    console.log(timer);
                    return;
                }


                document.getElementById("startButton").hidden = true
                if (end) {
                    document.getElementById("stopButton").hidden = true
                    document.getElementById("containername").innerHTML = ''

                }
            }
        })
        .catch(error => {
            console.error("Error fetching users:", error);
        });
}



function startTimer() {
    const token = sessionStorage.getItem("jwt");


    if (document.getElementById("startButton").innerHTML === "Continue") {
        pausetime += ((new Date().getTime()) - pauseon.getTime());
        pauseon = null;
        document.getElementById("startButton").innerHTML = "Start"
        document.getElementById("startButton").hidden = true


    } else {
        sessionStartTime = new Date();
        const startTime = formatTimeForApi(sessionStartTime)

        postData(`api/Time?&id=${userId}&time=${startTime}&pause=0`, token)
            .then(data => {
                document.getElementById('response').style.display = "block"
                setTimeout(function () { animateElement(document.getElementById('response'), 'hide-element') }, 2000)
                document.getElementById('response').innerHTML = "Eingestempelt"
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }






    if (timer) {
        console.log(timer);
        return;
    }

    timer = setInterval(updateTimerDisplay, 1000);

    disableButton('startButton');
    document.getElementById("startButton").hidden = true

    enableButton('pauseButton');
    enableButton('stopButton');


}





function pauseTimer() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }

    pauseon = new Date();

    enableButton('startButton');
    disableButton('pauseButton');
    document.getElementById("startButton").innerHTML = "Continue";
    document.getElementById("startButton").hidden = false

}

function stopTimer() {
    const token = sessionStorage.getItem("jwt");

    clearInterval(timer);
    timer = null;
    resetTimerDisplay();



    let endTime = new Date();


    try { pausetime += ((new Date().getTime()) - pauseon.getTime()) }
    catch { error => console.log(error) };
    const pause = Math.floor(pausetime / 1000 / 60) / 60
    endTime = formatTimeForApi(endTime)
    sessionStartTime ?
        postData(`api/Time?&id=${userId}&time=${endTime}&pause=${pause}`, token)

            .catch(error => {
                console.error('Error Api StopTime:', error);
            }) : null


    postData(`api/user/get-user-data?id=${userId}`, token)
        .then(data => {

            displayLoggedTime(data)
            document.getElementById("startButton").hidden = true
            document.getElementById("stopButton").hidden = true
            document.getElementById("pauseButton").hidden = true

            document.getElementById("containername").innerHTML = ''
            document.getElementById("manualEntry").innerHTML = ''

        })
        .catch(error => {
            console.error('Error:', error);
        });


    sessionStartTime = null;
    worktime = 0
    pausetime = 0


    disableButton('pauseButton');
    disableButton('stopButton');
    enableButton('startButton');

    document.getElementById("startButton").innerHTML = "Start";
    document.getElementById("startButton").hidden = false

    document.getElementById('response').style.display = "block"
    document.getElementById('response').innerHTML = "Ausgestempelt"
    setTimeout(function () { animateElement(document.getElementById('response'), 'fade-out-down') }, 2000)




}
function updateTimerDisplay() {
    const currentSessionElapsed = Date.now() - sessionStartTime.getTime();
    worktime = Math.floor((currentSessionElapsed - pausetime) / 1000);

    displayTime(worktime);
}


function displayLoggedTime(loggedTime) {
    const start = loggedTime[0]['start']
    const end = loggedTime[0]['end']

    document.getElementById('timerDisplay').innerHTML = '';


    const Login = document.createElement("span")
    Login.id = "Loginspan"
    Login.textContent = `Login Time: ${start}`

    const Logout = document.createElement("span")
    Logout.id = "Logoutspan"
    Logout.textContent = `Logout Time:${end}`;


    start ? document.getElementById('timerDisplay').appendChild(Login) : null
    end ? document.getElementById('timerDisplay').appendChild(Logout) : null

}





function displayTime(seconds) {
    const hours = Math.floor(seconds / 3600).toString().padStart(2, '0');
    const minutes = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
    const remainingSeconds = (seconds % 60).toString().padStart(2, '0');
    document.getElementById('timerDisplay').textContent = `${hours}:${minutes}:${remainingSeconds}`;
}

function resetTimerDisplay() {
    timer = 0
    document.getElementById('timerDisplay').textContent = '00:00:00';
}
function disableButton(buttonId) {
    document.getElementById(buttonId).disabled = true;
}
function enableButton(buttonId) {
    document.getElementById(buttonId).disabled = false;
}

function submitManualTime() {
    const manualDate = document.getElementById('manualDate').value;
    const startTime = document.getElementById('manualStartTime').value;
    const endTime = document.getElementById('manualEndTime').value;
    submitTimeLog(manualDate + ' ' + startTime, manualDate + ' ' + endTime);
}

function submitTimeLog(startDateTime, endDateTime, pause) {
    const formattedStartTime = formatTimeForApi(startDateTime);
    const formattedEndTime = formatTimeForApi(endDateTime);

    postData('api/logTime', { startDate: formattedStartTime, endDate: formattedEndTime, pause: pause })
        .then(data => {
            console.log('Success:', data);
        })
        .catch((error) => {
            console.error('Error:', error);
        });
}

function formatTimeForApi(dateTime) {
    const date = new Date(dateTime);
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return hours + ':' + minutes;
}

function postData(url = '', token) {
    return fetch(url, {
        headers: {
            'Authorization': `${token}`,
        }
    })
        .then(response => response.json());
}



export function createTimeTrackerView() {


    const navbutton = document.getElementById('homeLink');
    navbutton.style.backgroundColor = "#5486B8";
    const navbutton2 = document.getElementById('zeiterfassungLink');
    navbutton2.style.backgroundColor = "";


    const container = document.getElementById('contentPlaceholder');
    container.innerHTML = '';

    const timetrackerDiv = document.createElement('div');
    timetrackerDiv.id = 'timetracker';

    const title = document.createElement('h1');

    title.textContent = 'Stempeluhr';
    title.id = 'containername'
    timetrackerDiv.appendChild(title);

    const timerControlsDiv = document.createElement('div');
    timerControlsDiv.id = 'timerControls';

    const startButton = createButton('startButton', 'Start');
    const pauseButton = createButton('pauseButton', 'Pause');
    const stopButton = createButton('stopButton', 'End');

    timerControlsDiv.appendChild(startButton);
    timerControlsDiv.appendChild(pauseButton);
    timerControlsDiv.appendChild(stopButton);

    const timerDisplay = document.createElement('div');
    timerDisplay.id = 'timerDisplay';
    timerDisplay.textContent = '00:00:00';

    timerControlsDiv.appendChild(timerDisplay);
    timetrackerDiv.appendChild(timerControlsDiv);

    const manualEntryDiv = document.createElement('div');
    manualEntryDiv.id = 'manualEntry';

    const manualEntrySpan = document.createElement('span');
    manualEntrySpan.textContent = 'Manuelle eintragen';
    manualEntryDiv.appendChild(manualEntrySpan);

    const MEcomponentDiv = document.createElement('div');
    MEcomponentDiv.id = 'MEcomponent';

    const manualDate = createInput('date', 'manualDate', true);
    const manualStartTime = createInput('time', 'manualStartTime', true, 'Start Time (HH:MM)');
    const manualEndTime = createInput('time', 'manualEndTime', true, 'End Time (HH:MM)');

    MEcomponentDiv.appendChild(manualDate);
    MEcomponentDiv.appendChild(manualStartTime);
    MEcomponentDiv.appendChild(manualEndTime);

    const manualSubmitButton = createButton('manualSubmit', 'Submit Time');
    MEcomponentDiv.appendChild(manualSubmitButton);
    manualEntryDiv.appendChild(MEcomponentDiv);

    const responseSpan = document.createElement('span');
    responseSpan.id = 'response';
    responseSpan.style.display = 'none';
    manualEntryDiv.appendChild(responseSpan);

    timetrackerDiv.appendChild(manualEntryDiv);
    container.appendChild(timetrackerDiv);
}

function createButton(id, text) {
    const button = document.createElement('button');
    button.id = id;
    button.textContent = text;
    return button;
}

function createInput(type, id, required, placeholder = '') {
    const input = document.createElement('input');
    input.type = type;
    input.id = id;
    input.required = required;
    if (placeholder) input.placeholder = placeholder;
    return input;
}



