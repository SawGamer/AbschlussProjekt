// Employee panel: picks up the login token, then routes between the pages.

import { fetchAndSetJwtToken, logout, currentClaims, apiFetch } from "./Authentication.js";
import { createWorkHoursView, subjectId, setWeeklyHours } from "./Main.js";
import { renderOverview } from "./user.js";
import { renderLeave } from "./leave.js";
import { renderAccount } from "./account.js";
import { pageHead, startRouter, initThemeButton } from "./ui.js";

async function renderTimes(container) {
    // The month table needs the weekly hours for its default break.
    const today = await apiFetch(`api/user/today?id=${encodeURIComponent(subjectId)}`);
    setWeeklyHours(Number(today.ShouldHours));
    container.append(pageHead('Zeiterfassung', 'Änderungen in der Tabelle werden sofort gespeichert. Stunden und Überstunden rechnet der Server.'));
    createWorkHoursView(container);
}

document.addEventListener('DOMContentLoaded', async () => {
    await fetchAndSetJwtToken();  // Wait for the token to be fetched and set

    const claims = currentClaims();
    document.getElementById('navName').textContent = claims.name ?? '';
    document.getElementById('adminLink').hidden = !claims.admin;
    document.getElementById('logout').addEventListener('click', logout);
    initThemeButton(document.getElementById('themeButton'));

    startRouter({
        uebersicht: renderOverview,
        zeiten: renderTimes,
        urlaub: renderLeave,
        konto: renderAccount,
    }, 'uebersicht', document.getElementById('contentPlaceholder'));
});
