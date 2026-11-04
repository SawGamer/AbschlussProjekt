<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmelden | WorkForce</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Ubuntu:400,500,700">
    <link rel="stylesheet" href="loginstyle.css">
</head>

<body class="login-page">
    <main class="login-card">
        <h1>WorkForce</h1>
        <p class="subtitle">Zeiterfassung</p>

        <div id="message">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">Benutzername oder Passwort ist falsch.</div>
            <?php elseif (isset($_GET['expired'])): ?>
                <div class="alert alert-warning">Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.</div>
            <?php endif; ?>
        </div>

        <form method="POST" id="myform" action="../api/login" novalidate>
            <div class="field">
                <label for="username">Benutzername</label>
                <input type="text" name="username" id="username" autocomplete="username" autofocus>
                <span class="error" id="username_err"></span>
            </div>

            <div class="field">
                <label for="password">Passwort</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" autocomplete="current-password">
                    <button type="button" id="togglePassword" aria-label="Passwort anzeigen">Anzeigen</button>
                </div>
                <span class="error" id="password_err"></span>
            </div>

            <button type="submit" id="submitbtn">Anmelden</button>
        </form>
    </main>

    <script src="JS/validation.js"></script>
</body>

</html>
