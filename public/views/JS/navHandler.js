import { createTimeTrackerView, setupEventListeners } from "./user.js";
import { createWorkHoursView, attachEventListeners, initializeView } from "./Main.js";
import { fetchAndSetJwtToken, logout } from "./Authentication.js";

document.addEventListener('DOMContentLoaded', async () => {
    await fetchAndSetJwtToken();  // Wait for the token to be fetched and set

    document.getElementById('homeLink')?.addEventListener('click', activateUserView);
    document.getElementById('zeiterfassungLink')?.addEventListener('click', activateWorkHoursView);
    document.getElementById('logout')?.addEventListener('click', logout);

    activateUserView();
});

function activateUserView() {
    createTimeTrackerView();
    setupEventListeners();
}

function activateWorkHoursView() {
    createWorkHoursView();
    attachEventListeners();
    initializeView();
}
