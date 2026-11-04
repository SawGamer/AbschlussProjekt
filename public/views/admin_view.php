<?php $base = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\') . '/'; ?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars($base) ?>" />
    <title>Admin | WorkForce</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Ubuntu:400,500,700">
    <link rel="stylesheet" href="views/styling.css">
</head>

<body>
    <nav class="head">
        <div class="box1">WorkForce</div>
        <div id="name" class="box2">Hallo</div>
    </nav>
    <nav class="side-nav">
        <a class="menulink active" href="views/admin_view.php">Mitarbeitende</a>
        <a id="logout" class="menulink" href="javascript:void(0)">Abmelden</a>
    </nav>
    <main id="contentPlaceholder" class="container">
        <h2>Mitarbeitende: <span id="monthLabel"></span></h2>
        <div id="tableMessage" class="message" hidden></div>
        <div class="work-hours-table-container">
            <table id="userList" class="work-hours-table">
                <thead>
                    <tr class="table-header-row">
                        <th>Name</th>
                        <th>Benutzername</th>
                        <th>Firma</th>
                        <th>Wochenstunden</th>
                        <th>Stunden im Monat</th>
                        <th>Überstunden im Monat</th>
                        <th>Rolle</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </main>

    <script type="module" src="views/JS/admin.js"></script>
</body>

</html>
