// Browser tests for both panels with Playwright. Needs freshly seeded data:
//   mysql -u root < database/schema.sql && php database/seed.php
//   php -S localhost:8000 -t public public/router.php
//   npm install playwright && npx playwright install chromium
//   node tests/e2e.js [screenshot-folder]
// BASE_URL and APP_TIMEZONE (default Europe/Berlin) must match the server.

const { chromium } = require('playwright');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const TIMEZONE = process.env.APP_TIMEZONE || 'Europe/Berlin';
const SHOTS = process.argv[2] || null;
const results = [];
const check = (name, ok, detail = '') => results.push(`${ok ? 'PASS' : 'FAIL'}  ${name}${!ok && detail ? `  (${detail})` : ''}`);

function isoDate(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Monday at least `weeks` weeks from today, as YYYY-MM-DD. */
function mondayIn(weeks) {
    const date = new Date();
    date.setDate(date.getDate() + weeks * 7 + ((8 - date.getDay()) % 7 || 7));
    return date;
}

async function shot(page, name, options = {}) {
    if (SHOTS) await page.screenshot({ path: `${SHOTS}/${name}.png`, ...options });
}

async function login(page, username, password) {
    await page.goto(`${BASE}/views/login.php`);
    await page.fill('#username', username);
    await page.fill('#password', password);
    await Promise.all([page.waitForNavigation(), page.click('#submitbtn')]);
}

(async () => {
    const browser = await chromium.launch({ args: ['--lang=de-DE'], env: { ...process.env, LANG: 'de_DE.UTF-8' } });
    const newPage = async (viewport = { width: 1440, height: 960 }) => {
        const context = await browser.newContext({ viewport, deviceScaleFactor: 1, timezoneId: TIMEZONE, locale: 'de-DE', acceptDownloads: true });
        const page = await context.newPage();
        page.problems = [];
        page.on('pageerror', (e) => page.problems.push(e.message));
        // Expected HTTP errors (wrong password, revoked token) are checked separately;
        // only script errors count here.
        page.on('console', (m) => { if (m.type() === 'error' && !m.text().startsWith('Failed to load resource')) page.problems.push(m.text()); });
        return page;
    };

    // --- Login page -------------------------------------------------------------
    const page = await newPage();
    await page.goto(`${BASE}/`);
    check('root opens the login page', page.url().endsWith('/views/login.php'));
    await page.click('#submitbtn');
    check('empty login is blocked in the browser', (await page.textContent('#message')).includes('ausfüllen'));
    await login(page, 'max', 'falsch');
    check('wrong password shows an error', (await page.textContent('#message')).includes('falsch'));
    page.problems.length = 0;

    // --- Screenshots of the untouched demo data ---------------------------------------
    if (SHOTS) {
        const demo = await newPage();
        await login(demo, 'admin', 'admin1234');
        await demo.waitForSelector('.board .timeline-row');
        await demo.waitForTimeout(400);
        await shot(demo, 'admin-dashboard', { fullPage: true });
        await demo.click('#themeButton');
        await demo.click('#themeButton');
        await demo.waitForTimeout(300);
        await shot(demo, 'admin-dashboard-dark', { fullPage: true });
        await demo.context().close();
    }

    // --- Employee: stamp clock with breaks ---------------------------------------
    await login(page, 'max', 'demo1234');
    await page.waitForSelector('#clockTime');
    check('overview greets the employee', (await page.textContent('h1')).includes('Max'));
    check('figures show four values', (await page.locator('.figure').count()) === 4);
    check('week chart has seven days', (await page.locator('.week-col').count()) === 7);
    check('admin link is hidden for employees', !(await page.isVisible('#adminLink')));

    await page.click('button[data-action="start"]');
    await page.waitForSelector('.badge.good');
    await page.waitForTimeout(2200);
    const timer = await page.textContent('#clockTime');
    check('clock in starts the timer', /^00:0\d:\d\d$/.test(timer) && timer !== '00:00:00', timer);
    check('timeline shows a work segment', (await page.locator('.clock-day .timeline-segment').count()) >= 1);

    await page.click('button[data-action="pause"]');
    await page.waitForSelector('.badge.warning');
    await page.reload();
    await page.waitForSelector('#clockTime');
    check('a running break survives a page reload', (await page.locator('.badge.warning').count()) === 1 && await page.isVisible('button[data-action="resume"]'));
    check('timeline shows the break', (await page.locator('.clock-day .timeline-segment.break').count()) === 1);

    await page.click('button[data-action="resume"]');
    await page.waitForSelector('button[data-action="stop"]');
    await page.click('button[data-action="stop"]');
    await page.waitForSelector('.clock-meta:has-text("Gehen")');
    check('clock out shows the finished day', (await page.textContent('.badge')).includes('Feierabend'));

    // Manual entry for an earlier working day
    const lastMonday = new Date();
    lastMonday.setDate(lastMonday.getDate() - ((lastMonday.getDay() + 6) % 7) - 7);
    await page.fill('input[name="date"]', isoDate(lastMonday));
    await page.fill('input[name="start"]', '08:00');
    await page.fill('input[name="end"]', '17:30');
    await page.fill('input[name="pause"]', '30');
    await page.click('button:has-text("Eintragen")');
    await page.waitForSelector('.toast:has-text("Eingetragen: 9,00")');
    check('manual entry saves 9 hours', true);

    // --- Employee: time tables and export ---------------------------------------------
    await page.click('a[data-route="zeiten"]');
    await page.waitForSelector('#workHoursTable tbody tr');
    const now = new Date();
    const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    check('month table has one row per day', (await page.locator('#workHoursTable tbody tr').count()) === daysInMonth);

    const download = page.waitForEvent('download');
    await page.click('#exportButton');
    const file = await download;
    const csv = require('fs').readFileSync(await file.path(), 'utf8');
    check('CSV export downloads a German spreadsheet', file.suggestedFilename().startsWith('arbeitszeiten-max-') && csv.startsWith('﻿Datum;Wochentag'));

    await page.click('#toggleView');
    await page.waitForFunction(() => document.querySelectorAll('#workHoursTable tbody tr').length === 12);
    check('year view lists twelve months', true);
    await page.click('#toggleView');

    // --- Employee: vacation request --------------------------------------------------
    await page.click('a[data-route="urlaub"]');
    await page.waitForSelector('input[name="start_date"]');
    const first = mondayIn(10);
    const last = new Date(first);
    last.setDate(first.getDate() + 1);
    await page.fill('input[name="start_date"]', isoDate(first));
    await page.dispatchEvent('input[name="start_date"]', 'change');
    await page.fill('input[name="end_date"]', isoDate(last));
    await page.dispatchEvent('input[name="end_date"]', 'change');
    check('form counts the working days', (await page.textContent('.panel .hint[aria-live]')).includes('Arbeitstage'));
    await page.fill('input[name="comment"]', 'Hochzeit');
    await page.click('button:has-text("Antrag stellen")');
    await page.waitForSelector('td:has-text("Hochzeit")');
    check('new request is listed as open', (await page.locator('tr:has-text("Hochzeit") .badge.warning').count()) === 1);

    // --- Employee: password change and logout ---------------------------------------------
    await page.click('a[data-route="konto"]');
    await page.waitForSelector('input[name="current"]');
    await page.fill('input[name="current"]', 'falsch123');
    await page.fill('input[name="next"]', 'neuesPasswort9');
    await page.fill('input[name="repeat"]', 'neuesPasswort9');
    await page.click('button:has-text("Passwort ändern")');
    await page.waitForSelector('.form-error:has-text("falsch")');
    check('wrong current password is reported', true);
    await page.fill('input[name="current"]', 'demo1234');
    await page.click('button:has-text("Passwort ändern")');
    await page.waitForSelector('.toast:has-text("Passwort geändert")');
    check('password change keeps the user logged in', (await page.locator('.toast:has-text("Passwort geändert")').count()) === 1);
    const token = await page.evaluate(() => sessionStorage.getItem('jwt'));
    await page.click('#logout');
    await page.waitForURL('**/views/logout.php');
    const revoked = await page.evaluate(async (jwt) => (await fetch('../api/user/today?id=2', { headers: { Authorization: `Bearer ${jwt}` } })).status, token);
    check('logout revokes the token on the server', revoked === 401, String(revoked));
    await login(page, 'max', 'neuesPasswort9');
    check('login works with the new password', page.url().includes('user_view.php'));
    check('employee pages have no script errors', page.problems.length === 0, page.problems.join(' | '));

    // --- Admin ------------------------------------------------------------------------------
    const admin = await newPage();
    await login(admin, 'admin', 'admin1234');
    await admin.waitForSelector('.board .timeline-row');
    check('dashboard lists every active employee', (await admin.locator('.board .timeline-row').count()) === 6);
    await admin.waitForFunction(() => document.getElementById('pendingCount').textContent === '3');
    check('nav shows three open requests', true);

    const maxRequest = admin.locator('.request:has-text("Hochzeit")');
    await maxRequest.locator('button:has-text("Genehmigen")').click();
    await admin.waitForSelector('.toast:has-text("genehmigt")');
    await admin.waitForFunction(() => document.getElementById('pendingCount').textContent === '2');
    check('approving from the dashboard updates the count', true);

    // Employees: search, create, edit, deactivate
    await admin.click('a[data-route="mitarbeitende"]');
    await admin.waitForSelector('table.data tbody tr');
    await admin.fill('input[type="search"]', 'eri');
    check('search filters the list', (await admin.locator('table.data tbody tr').count()) === 1);
    await admin.fill('input[type="search"]', '');

    await admin.click('button:has-text("Mitarbeiter/in anlegen")');
    await admin.fill('dialog input[name="first_name"]', 'Nina');
    await admin.fill('dialog input[name="last_name"]', 'Neumann');
    await admin.fill('dialog input[name="username"]', 'nina');
    await admin.fill('dialog input[name="weekly_hours"]', '25');
    await admin.fill('dialog input[name="password"]', 'kurz');
    await shot(admin, 'admin-dialog');
    await admin.click('dialog button:has-text("Anlegen")');
    await admin.waitForSelector('dialog .form-error:has-text("mindestens 8")');
    check('dialog shows a weak-password error', true);
    await admin.fill('dialog input[name="password"]', 'start12345');
    await admin.click('dialog button:has-text("Anlegen")');
    await admin.waitForSelector('table.data tbody tr:has-text("Nina Neumann")');
    check('new employee appears in the list', (await admin.locator('table.data tbody tr').count()) === 7);

    await admin.locator('tr:has-text("Nina Neumann") button:has-text("Bearbeiten")').click();
    await admin.uncheck('dialog input[name="active"]');
    await admin.click('dialog button:has-text("Änderungen speichern")');
    await admin.waitForSelector('tr.inactive:has-text("Nina Neumann")');
    check('deactivated employee is marked inactive', true);
    await shot(admin, 'admin-employees');

    // Times of another employee
    await admin.goto(`${BASE}/views/admin_view.php#/zeiten/3`);
    await admin.waitForSelector('#workHoursTable tbody tr');
    check('times page opens Erika', (await admin.textContent('h1')).includes('Erika'));
    const row = admin.locator(`#workHoursTable tbody tr[data-day="${lastMonday.getMonth() === now.getMonth() ? lastMonday.getDate() : 1}"]`);
    await row.locator('input').nth(0).fill('07:00');
    await row.locator('input').nth(1).fill('15:00');
    await row.locator('input').nth(1).dispatchEvent('change');
    await admin.waitForSelector('#workHoursTable tr.saved');
    check('admin corrects a day of another employee', true);

    // Vacation requests: reject with a note
    await admin.click('a[data-route="urlaub"]');
    await admin.waitForSelector('table.data tbody tr');
    await admin.locator('tr:has-text("Tom Weber") button:has-text("Ablehnen")').click();
    await admin.fill('dialog input[name="note"]', 'Prüfungsphase im Team');
    await admin.click('dialog button:has-text("Ablehnen")');
    await admin.waitForSelector('.toast:has-text("abgelehnt")');
    await admin.click('button:has-text("Entschieden")');
    await admin.waitForSelector('tr:has-text("Prüfungsphase im Team")');
    check('rejected request shows the note', true);

    // Activity log
    await admin.click('a[data-route="protokoll"]');
    await admin.waitForSelector('h1:has-text("Protokoll")');
    await admin.waitForSelector('table.data tbody tr');
    const log = await admin.textContent('table.data');
    check('activity log records the admin actions', ['Zeiten geändert', 'Konto angelegt', 'Konto geändert', 'Urlaub genehmigt', 'Urlaub abgelehnt'].every((t) => log.includes(t)));

    // Dark mode
    await admin.click('a[data-route="dashboard"]');
    await admin.waitForSelector('.board .timeline-row');
    for (let i = 0; i < 2; i++) await admin.click('#themeButton');
    check('theme switch turns on dark mode', (await admin.evaluate(() => document.documentElement.dataset.theme)) === 'dark');
    await admin.click('#themeButton');
    check('admin pages have no script errors', admin.problems.length === 0, admin.problems.join(' | '));

    // --- Screenshots of an employee mid-day, and the phone layout --------------------------------
    const jonas = await newPage();
    await login(jonas, 'jonas', 'demo1234');
    await jonas.waitForSelector('#clockTime');
    await jonas.waitForTimeout(400);
    await shot(jonas, 'employee-overview', { fullPage: true });
    await jonas.click('a[data-route="urlaub"]');
    await jonas.waitForSelector('input[name="start_date"]');
    await shot(jonas, 'employee-leave');

    for (const [user, password, name] of [['erika', 'demo1234', 'phone-employee'], ['admin', 'admin1234', 'phone-admin']]) {
        const phone = await newPage({ width: 390, height: 844 });
        await login(phone, user, password);
        await phone.waitForSelector(user === 'admin' ? '.board .timeline-row' : '#clockTime');
        const overflow = await phone.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        check(`no horizontal scrolling on a phone (${user})`, !overflow);
        await shot(phone, name, { fullPage: true });
    }

    const failed = results.filter((line) => line.startsWith('FAIL'));
    console.log(results.join('\n'));
    console.log(failed.length ? `\n${failed.length} failed` : `\nOK  ${results.length} browser checks passed`);
    await browser.close();
    process.exit(failed.length ? 1 : 0);
})().catch((error) => {
    console.log(results.join('\n'));
    console.error('CRASH', error);
    process.exit(1);
});
