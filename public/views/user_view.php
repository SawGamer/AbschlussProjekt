<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\') . '/';
// Links to #/routes must name this page, because <base> points elsewhere.
$self = htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '#'));
$title = 'Zeiterfassung';
?>
<!DOCTYPE html>
<html lang="de">

<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>

<body>
    <nav class="side-nav" aria-label="Hauptmenü">
        <div class="brand">WorkForce <small>Zeiterfassung</small></div>
        <a class="menulink" data-route="uebersicht" href="<?= $self ?>#/uebersicht">Übersicht</a>
        <a class="menulink" data-route="zeiten" href="<?= $self ?>#/zeiten">Zeiten</a>
        <a class="menulink" data-route="urlaub" href="<?= $self ?>#/urlaub">Urlaub</a>
        <a class="menulink" data-route="konto" href="<?= $self ?>#/konto">Konto</a>

        <div class="nav-footer">
            <div class="nav-user">Angemeldet als <strong id="navName"></strong></div>
            <a id="adminLink" class="menulink" href="views/admin_view.php" hidden>Zum Admin-Bereich</a>
            <button id="themeButton" class="menulink nav-button" type="button">Darstellung</button>
            <button id="logout" class="menulink nav-button" type="button">Abmelden</button>
        </div>
    </nav>

    <main id="contentPlaceholder" tabindex="-1"></main>

    <script type="module" src="views/JS/navHandler.js"></script>
</body>

</html>
