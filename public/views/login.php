<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmelden | WorkForce</title>
    <script>
        try {
            const theme = localStorage.getItem('wf-theme');
            if (theme) document.documentElement.dataset.theme = theme;
        } catch (e) { /* storage may be blocked */ }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Semi+Condensed:wght@500;600&display=swap">
    <link rel="stylesheet" href="styling.css">
    <link rel="stylesheet" href="loginstyle.css">
</head>

<body class="login-page">
    <main class="login-card">
        <p class="login-brand">WorkForce</p>
        <h1>Anmelden</h1>
        <p class="subtitle">Stempeluhr, Arbeitszeiten und Urlaub an einem Ort.</p>

        <div id="message">
            <?php if (isset($_GET['error'])): ?>
                <div class="message error">Benutzername oder Passwort ist falsch.</div>
            <?php elseif (isset($_GET['inactive'])): ?>
                <div class="message error">Dieses Konto ist deaktiviert. Bitte wenden Sie sich an die Verwaltung.</div>
            <?php elseif (isset($_GET['expired'])): ?>
                <div class="message error">Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.</div>
            <?php endif; ?>
        </div>

        <form method="POST" id="myform" action="../api/login" novalidate>
            <label class="field" for="username">Benutzername
                <input type="text" name="username" id="username" autocomplete="username" autofocus>
            </label>
            <span class="error" id="username_err"></span>

            <label class="field" for="password">Passwort</label>
            <div class="input-group">
                <input type="password" name="password" id="password" autocomplete="current-password">
                <button type="button" id="togglePassword" class="secondary" aria-label="Passwort anzeigen">Anzeigen</button>
            </div>
            <span class="error" id="password_err"></span>

            <button type="submit" id="submitbtn">Anmelden</button>
        </form>
    </main>

    <script src="JS/validation.js"></script>
</body>

</html>
