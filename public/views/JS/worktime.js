// Holidays and daily target hours. Mirrors Models/Holidays.php and
// Models/WorkTime.php so the browser can show targets without a request.

const DEFAULT_WEEKLY_HOURS = 40;
const WORKING_DAYS_PER_WEEK = 5;
const holidayCache = new Map();

/** Local date as "YYYY-MM-DD" (no time-zone shift, unlike toISOString). */
export function dateKey(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Easter Sunday (anonymous Gregorian algorithm by Meeus, Jones and Butcher). */
export function easterSunday(year) {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31);
    const day = ((h + l - 7 * m + 114) % 31) + 1;
    return new Date(year, month - 1, day);
}

/** Map of "YYYY-MM-DD" => holiday name for the given year. */
export function holidaysForYear(year) {
    if (!holidayCache.has(year)) {
        const easter = easterSunday(year);
        const relative = (days) => dateKey(new Date(year, easter.getMonth(), easter.getDate() + days));
        holidayCache.set(year, new Map([
            [`${year}-01-01`, 'Neujahr'],
            [`${year}-01-06`, 'Heilige Drei Könige'],
            [relative(-2), 'Karfreitag'],
            [relative(1), 'Ostermontag'],
            [`${year}-05-01`, 'Tag der Arbeit'],
            [relative(39), 'Christi Himmelfahrt'],
            [relative(50), 'Pfingstmontag'],
            [relative(60), 'Fronleichnam'],
            [`${year}-10-03`, 'Tag der Deutschen Einheit'],
            [`${year}-11-01`, 'Allerheiligen'],
            [`${year}-12-25`, '1. Weihnachtstag'],
            [`${year}-12-26`, '2. Weihnachtstag'],
        ]));
    }
    return holidayCache.get(year);
}

/** Holiday name, or undefined on a normal day. */
export function holidayName(date) {
    return holidaysForYear(date.getFullYear()).get(dateKey(date));
}

export function isWeekend(date) {
    const day = date.getDay();
    return day === 0 || day === 6;
}

/** Target hours: a fifth of the weekly hours on working days, 0 otherwise. */
export function targetHours(date, weeklyHours = DEFAULT_WEEKLY_HOURS) {
    if (isWeekend(date) || holidayName(date)) {
        return 0;
    }
    return Math.round((weeklyHours / WORKING_DAYS_PER_WEEK) * 100) / 100;
}

/** Number of working days from one ISO date to another, inclusive. */
export function countWorkingDays(fromIso, toIso) {
    const from = new Date(`${fromIso}T00:00:00`);
    const to = new Date(`${toIso}T00:00:00`);
    let count = 0;
    for (const day = new Date(from); day <= to; day.setDate(day.getDate() + 1)) {
        if (!isWeekend(day) && !holidayName(day)) count++;
    }
    return count;
}

/** Days in a month; month is 1-12. */
export function getDaysInMonth(year, month) {
    return new Date(year, month, 0).getDate();
}
