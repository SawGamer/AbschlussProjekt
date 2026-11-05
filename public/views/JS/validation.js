// Login form checks. Plain JavaScript, so the page works without any CDN.

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('myform');
    const username = document.getElementById('username');
    const password = document.getElementById('password');

    username.addEventListener('input', checkuser);
    password.addEventListener('input', checkpass);
    document.getElementById('togglePassword').addEventListener('click', password_show_hide);

    // Stop the form from being sent when a field is empty or invalid.
    form.addEventListener('submit', (event) => {
        const userOk = checkuser();
        const passOk = checkpass();
        if (!userOk || !passOk) {
            event.preventDefault();
            document.getElementById('message').innerHTML =
                '<div class="message error">Bitte alle Felder ausfüllen.</div>';
        }
    });
});

function checkuser() {
    const validuser = /^[A-Za-z0-9]+$/.test(document.getElementById('username').value);
    document.getElementById('username_err').textContent = validuser ? '' : 'Nur Buchstaben und Ziffern erlaubt.';
    return validuser;
}

function checkpass() {
    const validpass = document.getElementById('password').value.length > 0;
    document.getElementById('password_err').textContent = validpass ? '' : 'Bitte Passwort eingeben.';
    return validpass;
}

function password_show_hide() {
    const password = document.getElementById('password');
    const toggle = document.getElementById('togglePassword');
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Verbergen' : 'Anzeigen';
    toggle.setAttribute('aria-label', show ? 'Passwort verbergen' : 'Passwort anzeigen');
}
