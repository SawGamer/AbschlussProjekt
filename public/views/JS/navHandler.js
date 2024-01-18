import { createTimeTrackerView, setupEventListeners } from "./user.js";
import { createWorkHoursView, attachEventListeners, initializeView, currentMonth } from "./Main.js";
import { fetchAndSetJwtToken } from "./Authentication.js";
const urlParams = new URLSearchParams(window.location.search);
const userId = urlParams.get('id');
document.addEventListener('DOMContentLoaded', async () => {
    await fetchAndSetJwtToken();  // Wait for the token to be fetched and set


    const homeLink = document.getElementById('homeLink');
    const zeiterfassungLink = document.getElementById('zeiterfassungLink');
    const logout = document.getElementById('logout');


    if (homeLink) {
        homeLink.addEventListener('click', activateUserView);
    }
    if (zeiterfassungLink) {
        zeiterfassungLink.addEventListener('click', activateWorkHoursView);
    }
    if (logout) {
        logout.addEventListener('click', Logout);
    }

    activateUserView();
});


function activateUserView() {
    createTimeTrackerView()
    setupEventListeners()
}

function activateWorkHoursView() {
    createWorkHoursView()
    attachEventListeners()
    initializeView()
}
function Logout() {
    sessionStorage.removeItem("jwt");
    window.location.href = "/NewProject/public/views/logout.php"
}
