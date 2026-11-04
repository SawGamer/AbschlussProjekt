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
        throw new Error(data.error || `HTTP ${response.status}`);
    }
    return data;
}

export function logout() {
    sessionStorage.removeItem('jwt');
    window.location.href = 'views/logout.php';
}
