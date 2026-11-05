<?php

// End the PHP session; the page's script removes the token from sessionStorage.
// The API call before this page (POST /api/logout) has already revoked every token.
session_name('workforce');
session_start();
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abgemeldet | WorkForce</title>
    <script>
        try {
            const theme = localStorage.getItem('wf-theme');
            if (theme) document.documentElement.dataset.theme = theme;
        } catch (e) { /* storage may be blocked */ }
    </script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Semi+Condensed:wght@500;600&display=swap">
    <link rel="stylesheet" href="styling.css">
    <link rel="stylesheet" href="loginstyle.css">
</head>

<body class="login-page">
    <main class="login-card">
        <p class="login-brand">WorkForce</p>
        <h1>Abgemeldet</h1>
        <p class="subtitle">Sie sind auf allen Geräten abgemeldet. Bis zum nächsten Mal.</p>
        <a class="button" href="login.php">Wieder anmelden</a>
    </main>
    <script>sessionStorage.removeItem('jwt');</script>
</body>

</html>
