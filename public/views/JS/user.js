import { animateElement } from "./Animations.js";
import { apiFetch } from "./Authentication.js";
import { setActiveNav, setWeeklyHours, formatHours } from "./Main.js";
import { dateKey } from "./worktime.js";

let timer = null;
let sessionStartTime = null;
let pausetime = 0;      // milliseconds spent on break today
let pauseon = null;     // when the current break started

const urlParams = new URLSearchParams(window.location.search);
const userId = urlParams.get('id');

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

        return fetchuser();
    }
    console.error("Some elements did not load properly.");
}

/** Loads the employee's name and today's clock times, then shows the matching buttons. */
async function fetchuser() {
    try {
        const today = await apiFetch(`api/user/today?id=${encodeURIComponent(userId)}`);
        setWeeklyHours(Number(today.ShouldHours));
        document.getElementById("name").textContent = `Willkommen zurück, ${today.Username}`;
        showDayState(today);
    } catch (error) {
        showResponse(`Daten konnten nicht geladen werden: ${error.message}`, true);
    }
}

function showDayState({ start, end }) {
    stopTicking();

    if (!start) {
        setButtons({ start: true });
        displayTime(0);
        return;
    }

    if (end) {
        setButtons({});
        displayLoggedTime(start, end);
        return;
    }

    // Clocked in and still working (also after a page reload).
    sessionStartTime = todayAt(start);
    setButtons({ pause: true, stop: true });
    startTicking();
}

async function startTimer() {
    if (pauseon) {
        // Back from a break
        pausetime += Date.now() - pauseon.getTime();
        pauseon = null;
        setButtons({ pause: true, stop: true });
        startTicking();
        return;
    }

    try {
        const result = await apiFetch('api/user/clock', { method: 'POST', body: { id: userId, pause: 0 } });
        pausetime = 0;
        showDayState({ start: result.start, end: null });
        showResponse(`Eingestempelt um ${result.start.slice(0, 5)} Uhr`);
    } catch (error) {
        showResponse(error.message, true);
    }
}

function pauseTimer() {
    stopTicking();
    pauseon = new Date();
    setButtons({ start: true, stop: true });
    document.getElementById("startButton").textContent = "Weiter";
}

async function stopTimer() {
    if (pauseon) {
        pausetime += Date.now() - pauseon.getTime();
        pauseon = null;
    }
    const pauseMinutes = Math.round(pausetime / 60000);

    try {
        const result = await apiFetch('api/user/clock', { method: 'POST', body: { id: userId, pause: pauseMinutes } });
        pausetime = 0;
        showDayState(result);
        showResponse(`Ausgestempelt: ${formatHours(result.actual_hours)} Stunden gearbeitet`);
    } catch (error) {
        showResponse(error.message, true);
    }
}

function startTicking() {
    if (!timer) {
        timer = setInterval(updateTimerDisplay, 1000);
    }
    updateTimerDisplay();
}

function stopTicking() {
    clearInterval(timer);
    timer = null;
}

function updateTimerDisplay() {
    const elapsed = Date.now() - sessionStartTime.getTime() - pausetime;
    displayTime(Math.max(Math.floor(elapsed / 1000), 0));
}

function displayLoggedTime(start, end) {
    const display = document.getElementById('timerDisplay');
    display.textContent = '';

    const login = document.createElement("span");
    login.id = "Loginspan";
    login.textContent = `Kommen: ${start.slice(0, 5)}`;

    const logout = document.createElement("span");
    logout.id = "Logoutspan";
    logout.textContent = `Gehen: ${end.slice(0, 5)}`;

    display.append(login, logout);
    display.classList.add('done');
}

function displayTime(seconds) {
    const hours = Math.floor(seconds / 3600).toString().padStart(2, '0');
    const minutes = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
    const remainingSeconds = (seconds % 60).toString().padStart(2, '0');
    const display = document.getElementById('timerDisplay');
    display.classList.remove('done');
    display.textContent = `${hours}:${minutes}:${remainingSeconds}`;
}

/** Shows only the given buttons, for example { pause: true, stop: true }. */
function setButtons(visible) {
    const startButton = document.getElementById("startButton");
    startButton.textContent = "Start";
    startButton.hidden = !visible.start;
    document.getElementById("pauseButton").hidden = !visible.pause;
    document.getElementById("stopButton").hidden = !visible.stop;
}

async function submitManualTime() {
    const date = document.getElementById('manualDate').value;
    const start = document.getElementById('manualStartTime').value;
    const end = document.getElementById('manualEndTime').value;
    const pause = Number(document.getElementById('manualPause').value || 0);

    if (!date || !start || !end) {
        showResponse('Bitte Datum, Beginn und Ende ausfüllen.', true);
        return;
    }

    try {
        const result = await apiFetch('api/user/hours', {
            method: 'POST',
            body: { id: userId, date, start_time: start, end_time: end, pause, type: 'Normal', comment: '' },
        });
        showResponse(`Gespeichert: ${formatHours(result.actual_hours)} Stunden am ${new Date(date).toLocaleDateString('de-DE')}`);
        if (date === dateKey(new Date())) {
            fetchuser();
        }
    } catch (error) {
        showResponse(error.message, true);
    }
}

function showResponse(text, isError = false) {
    const response = document.getElementById('response');
    response.textContent = text;
    response.classList.toggle('error', isError);
    response.style.display = "block";
    clearTimeout(showResponse.timer);
    showResponse.timer = setTimeout(() => animateElement(response, 'fade-out-down'), 3000);
}

/** Today's date at the given "HH:MM" or "HH:MM:SS" time. */
function todayAt(time) {
    const [hours, minutes, seconds = 0] = time.split(':').map(Number);
    const date = new Date();
    date.setHours(hours, minutes, seconds, 0);
    return date;
}

export function createTimeTrackerView() {
    setActiveNav('homeLink');

    const container = document.getElementById('contentPlaceholder');
    container.className = '';
    container.innerHTML = '';

    const timetrackerDiv = document.createElement('div');
    timetrackerDiv.id = 'timetracker';
    timetrackerDiv.className = 'card';

    const title = document.createElement('h2');
    title.textContent = 'Stempeluhr';
    title.id = 'containername';
    timetrackerDiv.appendChild(title);

    const timerDisplay = document.createElement('div');
    timerDisplay.id = 'timerDisplay';
    timerDisplay.textContent = '00:00:00';
    timetrackerDiv.appendChild(timerDisplay);

    const timerControlsDiv = document.createElement('div');
    timerControlsDiv.id = 'timerControls';
    const startButton = createButton('startButton', 'Start');
    const pauseButton = createButton('pauseButton', 'Pause');
    const stopButton = createButton('stopButton', 'Ende');
    pauseButton.classList.add('secondary');
    stopButton.classList.add('danger');
    startButton.hidden = pauseButton.hidden = stopButton.hidden = true;
    timerControlsDiv.append(startButton, pauseButton, stopButton);
    timetrackerDiv.appendChild(timerControlsDiv);

    const responseSpan = document.createElement('span');
    responseSpan.id = 'response';
    responseSpan.className = 'message';
    responseSpan.style.display = 'none';
    timetrackerDiv.appendChild(responseSpan);

    const manualEntryDiv = document.createElement('div');
    manualEntryDiv.id = 'manualEntry';
    manualEntryDiv.className = 'card';

    const manualTitle = document.createElement('h3');
    manualTitle.textContent = 'Zeit nachtragen';
    manualEntryDiv.appendChild(manualTitle);

    const MEcomponentDiv = document.createElement('div');
    MEcomponentDiv.id = 'MEcomponent';
    MEcomponentDiv.append(
        labelled('Datum', createInput('date', 'manualDate', true)),
        labelled('Beginn', createInput('time', 'manualStartTime', true)),
        labelled('Ende', createInput('time', 'manualEndTime', true)),
        labelled('Pause (Min.)', createInput('number', 'manualPause', false, '30')),
        createButton('manualSubmit', 'Speichern'),
    );
    manualEntryDiv.appendChild(MEcomponentDiv);

    container.append(timetrackerDiv, manualEntryDiv);

    const pauseInput = document.getElementById('manualPause');
    pauseInput.value = '30';
    pauseInput.min = '0';
    pauseInput.max = '1440';
    document.getElementById('manualDate').value = dateKey(new Date());
}

function labelled(text, input) {
    const label = document.createElement('label');
    label.textContent = text;
    label.appendChild(input);
    return label;
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
