<?php

// End the PHP session; the page's script removes the token from sessionStorage.
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
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Ubuntu:400,500,700">
    <link rel="stylesheet" href="loginstyle.css">
</head>

<body class="login-page">
    <main class="login-card">
        <h1>WorkForce</h1>
        <p>Sie wurden abgemeldet. Bis zum nächsten Mal!</p>
        <a class="login-link" href="login.php">Erneut anmelden</a>
    </main>
    <script>sessionStorage.removeItem('jwt');</script>
</body>

</html>
