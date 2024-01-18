<html>

<head>
    <script src="validation.js" type="text/javascript"></script>

    <link rel="stylesheet" herf="loginstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.1/css/bootstrap.min.css"
        integrity="sha512-Z/def5z5u2aR89OuzYcxmDJ0Bnd5V1cKqBEbvLOiUNWdg9PQeXVvXLI90SE4QOHGlfLqUnDNVAYyZi8UwUTmWQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"
        integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>

</head>

<body class="bg-light">
    <div class="container p-3">
        <div class="col-lg-6 m-auto d-block p-3 bg-white">
            <h2 class="pb-3 text-success">
                Welcome to FairVerify WorkForce test
            </h2>
            <div id="message"></div>
            <form method="POST" id="myform" action="api/login">
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="user1">
                            test Username:
                        </label>
                        <input type="text" name="username" id="username" class="form-control">
                        <span class="error" id="username_err"> </span>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="password">
                            test Password:
                        </label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control">

                            <span class="input-group-text" onclick="password_show_hide();">
                                <i class="fas fa-eye" id="show_eye"></i>
                                <i class="fas fa-eye-slash d-none" id="hide_eye"></i>
                            </span>
                        </div>
                    </div>
                    <span class="error" id="password_err"> </span>
                </div>


                <br />

                <div class="col-md-12">
                    <button type="Submit" id="submitbtn" class="btn btn-primary   ">Submit</button>
                </div>

        </div>
        <div id="messageafterlogin"></div>

        </form>
    </div>
    </div>

</body>
<script src="validation.js" type="text/javascript"></script>

</html>