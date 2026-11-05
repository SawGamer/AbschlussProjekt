// Login token handling and authenticated API calls.

export const store = {
    _JWT: null,

    setJWT(data) {
        this._JWT = data;
    },

    getJWT() {
        return this._JWT ?? sessionStorage.getItem('jwt');
    }
};

/**
 * Picks up the token that the login request parked in the session.
 * After a page reload the token comes from sessionStorage instead.
 * Sends the browser to the login page when there is no token at all.
 */
export const fetchAndSetJwtToken = async () => {
    try {
        const response = await fetch('api/authenticate');
        if (response.status === 200) {
            const data = await response.json();
            sessionStorage.setItem('jwt', data.jwt);
        }
    } catch (error) {
        console.error('Error fetching token:', error);
    }

    store.setJWT(sessionStorage.getItem('jwt'));
    if (!store.getJWT()) {
        window.location.href = 'views/login.php';
    }
};

/** The token's claims (id, name, admin). Only for display, the server checks the signature. */
export function currentClaims() {
    try {
        const payload = store.getJWT().split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
        const bytes = Uint8Array.from(atob(payload), (c) => c.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    } catch {
        return {};
    }
}

/**
 * Calls the API with the login token. Resolves with the parsed JSON body and
 * rejects with the server's error message. An expired login goes back to the login page.
 */
export async function apiFetch(path, { method = 'GET', body } = {}) {
    const headers = { Authorization: `Bearer ${store.getJWT()}` };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(path, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));

    if (response.status === 401) {
        sessionStorage.removeItem('jwt');
        window.location.href = 'views/login.php?expired=1';
    }
    if (!response.ok) {
        throw new Error(translateError(data.error) || `Fehler ${response.status}`);
    }
    return data;
}

/** Like apiFetch, but returns the raw Response (for file downloads). */
export async function apiRaw(path) {
    const response = await fetch(path, { headers: { Authorization: `Bearer ${store.getJWT()}` } });
    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(translateError(data.error) || `Fehler ${response.status}`);
    }
    return response;
}

/** Replaces the stored token, for example after a password change. */
export function setToken(jwt) {
    sessionStorage.setItem('jwt', jwt);
    store.setJWT(jwt);
}

/** Ends the session on the server (all tokens stop working), then shows the logout page. */
export async function logout() {
    try {
        await fetch('api/logout', { method: 'POST', headers: { Authorization: `Bearer ${store.getJWT()}` } });
    } catch {
        // The page below clears the browser side either way.
    }
    sessionStorage.removeItem('jwt');
    window.location.href = 'views/logout.php';
}

// The API answers in English; the interface speaks German.
const ERRORS = [
    [/^Only (\d+) vacation days left$/, (m) => `Es sind nur noch ${m[1]} Urlaubstage übrig.`],
    [/^Hours are already recorded on (.+)$/, (m) => `Am ${m[1]} sind bereits Arbeitszeiten eingetragen.`],
    [/^Password must have at least (\d+) characters$/, (m) => `Das Passwort braucht mindestens ${m[1]} Zeichen.`],
    [/^Already clocked in today$/, () => 'Sie sind heute bereits eingestempelt.'],
    [/^Already clocked out today$/, () => 'Sie haben heute bereits ausgestempelt.'],
    [/^Today already has an entry/, () => 'Für heute gibt es schon einen Eintrag. Ändern Sie ihn in der Monatsübersicht.'],
    [/^A break can only start while you are working$/, () => 'Eine Pause geht nur während der Arbeitszeit.'],
    [/^No break is running$/, () => 'Es läuft keine Pause.'],
    [/^Not clocked in$/, () => 'Sie sind nicht eingestempelt.'],
    [/^Vacation cannot start in the past$/, () => 'Urlaub kann nicht in der Vergangenheit beginnen.'],
    [/^The last day must not be before the first day$/, () => 'Der letzte Tag liegt vor dem ersten Tag.'],
    [/^Split requests that cross the turn of the year$/, () => 'Bitte stellen Sie für jedes Jahr einen eigenen Antrag.'],
    [/^The range contains no working days$/, () => 'Im Zeitraum liegt kein Arbeitstag.'],
    [/^Another request already covers some of these days$/, () => 'Für einige dieser Tage gibt es schon einen Antrag.'],
    [/^Only pending requests can be withdrawn$/, () => 'Nur offene Anträge können zurückgezogen werden.'],
    [/^Current password is wrong$/, () => 'Das aktuelle Passwort ist falsch.'],
    [/^Username is already taken$/, () => 'Dieser Benutzername ist schon vergeben.'],
    [/^Username must be 3 to 50 letters or digits$/, () => 'Der Benutzername braucht 3 bis 50 Buchstaben oder Ziffern.'],
    [/^(First|Last) name must be 1 to 50 characters$/, (m) => `${m[1] === 'First' ? 'Vorname' : 'Nachname'}: 1 bis 50 Zeichen.`],
    [/^Weekly hours must be between 1 and 60$/, () => 'Wochenstunden: zwischen 1 und 60.'],
    [/^Vacation days must be a whole number between 0 and 60$/, () => 'Urlaubstage: ganze Zahl zwischen 0 und 60.'],
    [/^You cannot deactivate yourself or remove your own admin role$/, () => 'Sie können sich nicht selbst deaktivieren oder Ihre Admin-Rolle entfernen.'],
    [/^pause must be whole minutes/, () => 'Pause: ganze Minuten zwischen 0 und 1440.'],
    [/^(start|end)_time must be HH:MM$/, () => 'Bitte die Uhrzeit als HH:MM angeben.'],
    [/^Forbidden$/, () => 'Dafür fehlt Ihnen die Berechtigung.'],
    [/^Internal server error$/, () => 'Auf dem Server ist ein Fehler aufgetreten.'],
];

export function translateError(message) {
    if (!message) return message;
    for (const [pattern, translate] of ERRORS) {
        const match = message.match(pattern);
        if (match) return translate(match);
    }
    return message;
}
