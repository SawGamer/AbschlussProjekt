// Admin dashboard: lists every employee with this month's hours.
import { fetchAndSetJwtToken, apiFetch, currentClaims, logout } from './Authentication.js';
import { formatHours } from './Main.js';

document.addEventListener('DOMContentLoaded', async () => {
    await fetchAndSetJwtToken();
    document.getElementById('logout').addEventListener('click', logout);
    document.getElementById('name').textContent = `Hallo, ${currentClaims().name ?? 'Admin'}`;
    document.getElementById('monthLabel').textContent =
        new Date().toLocaleString('de-DE', { month: 'long', year: 'numeric' });
    fetchAllUsers();
});

async function fetchAllUsers() {
    try {
        generateUserList(await apiFetch('api/admin/users'));
    } catch (error) {
        const message = document.getElementById('tableMessage');
        message.textContent = `Mitarbeitende konnten nicht geladen werden: ${error.message}`;
        message.classList.add('error');
        message.hidden = false;
    }
}

function generateUserList(users) {
    const body = document.querySelector('#userList tbody');
    body.innerHTML = '';

    users.forEach((user) => {
        const row = body.insertRow();
        row.insertCell().textContent = `${user.first_name} ${user.last_name}`;
        row.insertCell().textContent = user.Username;
        row.insertCell().textContent = user.CompanyName ?? '–';
        row.insertCell().textContent = formatHours(user.ShouldHours);
        row.insertCell().textContent = formatHours(user.MonthHours);
        row.insertCell().textContent = formatHours(user.MonthOvertime);
        row.insertCell().textContent = user.is_admin ? 'Admin' : 'Mitarbeiter/in';
    });
}
