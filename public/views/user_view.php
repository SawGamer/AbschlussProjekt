<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Dashboard - Time Tracking</title>
    <link rel="stylesheet" href="styling.css">
    <link rel="stylesheet" href="animating.css">
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Ubuntu">
    <script src="https://cdn.jsdelivr.net/npm/three@0.125.2/build/three.min.js"></script>




    <base href="/NewProject/public/" />
</head>

<body>
    <nav id="info" class="head ">
        <div class="box1 ">FairVerify WorkForce</div>
        <div id="name" class="box2 ">
            Willkommen Zurück
        </div>
    </nav>
    <nav class="side-nav">
        <center>
            <a id="homeLink" class="menulink">
                Home
            </a>
            <br class="optbr" />
            <a id="zeiterfassungLink" class="menulink">
                Zeiterfassung&nbsp;Tabellen
            </a>
            <br class="optbr" />
            <a id="logout" class="menulink">
                abmelden
            </a>

        </center>



    </nav>
    <div id="contentPlaceholder"></div>
    <script type="module" src="views/JS/navHandler.js"></script>
    <script type="module" src="views/JS/user.js"></script>
    <script type="module" src="views/JS/Main.js"></script>
    <script type="module" src="views/JS/Authentication.js"></script>
    <script type="module" src="../Config/feiertage.js"></script>




    </div>
    </div>
    <script type="text/javascript">

    </script>
</body>

</html>