<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\') . '/';
// Links to #/routes must name this page, because <base> points elsewhere.
$self = htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '#'));
$title = 'Verwaltung';
?>
<!DOCTYPE html>
<html lang="de">

<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>

<body>
    <nav class="side-nav" aria-label="Hauptmenü">
        <div class="brand">WorkForce <small>Verwaltung</small></div>
        <a class="menulink" data-route="dashboard" href="<?= $self ?>#/dashboard">Dashboard</a>
        <a class="menulink" data-route="mitarbeitende" href="<?= $self ?>#/mitarbeitende">Mitarbeitende</a>
        <a class="menulink" data-route="zeiten" href="<?= $self ?>#/zeiten">Zeiten</a>
        <a class="menulink" data-route="urlaub" href="<?= $self ?>#/urlaub">Urlaubsanträge <span id="pendingCount" class="count" hidden></span></a>
        <a class="menulink" data-route="protokoll" href="<?= $self ?>#/protokoll">Protokoll</a>

        <div class="nav-footer">
            <div class="nav-user">Angemeldet als <strong id="navName"></strong></div>
            <a id="ownTimesLink" class="menulink" href="views/user_view.php">Meine Zeiterfassung</a>
            <button id="themeButton" class="menulink nav-button" type="button">Darstellung</button>
            <button id="logout" class="menulink nav-button" type="button">Abmelden</button>
        </div>
    </nav>

    <main id="contentPlaceholder" tabindex="-1"></main>

    <script type="module" src="views/JS/admin.js"></script>
</body>

</html>
