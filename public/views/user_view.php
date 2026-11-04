<?php $base = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\') . '/'; ?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars($base) ?>" />
    <title>Zeiterfassung | WorkForce</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Ubuntu:400,500,700">
    <link rel="stylesheet" href="views/styling.css">
    <link rel="stylesheet" href="views/animating.css">
</head>

<body>
    <nav id="info" class="head">
        <div class="box1">WorkForce</div>
        <div id="name" class="box2">Willkommen zurück</div>
    </nav>
    <nav class="side-nav">
        <a id="homeLink" class="menulink" href="javascript:void(0)">Stempeluhr</a>
        <a id="zeiterfassungLink" class="menulink" href="javascript:void(0)">Zeiterfassung</a>
        <a id="logout" class="menulink" href="javascript:void(0)">Abmelden</a>
    </nav>
    <main id="contentPlaceholder"></main>

    <script type="module" src="views/JS/navHandler.js"></script>
</body>

</html>
