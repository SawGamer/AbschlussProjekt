<!-- <?php
echo "Willkommen in unseren Zeiterfassung Webseite von FairVerify GmbH"
    ?>
<html>
    <head>


    <script   src="https://code.jquery.com/jquery-3.7.0.min.js"   integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g="   crossorigin="anonymous"></script>
<script type="text/javascript" >
$(document).ready(function(e){
let username=$("#username").val();
let password=$("#password").val();
$("#login").click(function(){
if(username=="" && password=="") // Applicable for more validation parameters ! 
{
alert("username or password is blank");
$("form").submit(function (e) {
            return false;
        });//prevent the form from submitting.
}
else{
    $("form").submit(function (e) {
            return true;
        }) 
}

})

})
</script>

    <body>
        <p>
            <form action="/PHP/Dashboard.php" method="POST">
            Username: <input type="text" name="username" id="username"/><br/>
            Password:  <input name="password" type="password" id="password" /><br/>

        <input name = "login" type="Submit" id="login" variant="contained" value="login" />
</form>
</p>
</body>
</head>
</html> -->

<?php
require_once('PHP/JWT.php');
$jwt = (new JWT());

// for demo purposes only, remove this and the sample function below when in use
require_once('PHP/functions.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JWT Test</title>
</head>

<body>
    <h1>JWT Test</h1>
    <?php
    $payload = [
        'id' => '1',
        'name' => 'John Doe',
        'iss' => 'jwt.local',
        'aud' => 'example.com'
    ];
    $token = $jwt->generate($payload);
    print_r($token);
    sample($jwt->is_valid($token));

    $payload = [
        'id' => '1',
        'name' => 'John Doe',
        'iss' => 'jwt.local',
    ];
    $token = $jwt->generate($payload);
    print_r("eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImlzcyI6Imp3dC5sb2NhbCIsImF1ZCI6ImV4YW1wbGUuY29tIn0.eyJpZCI6IjEiLCJuYW1lIjoiSm9obiBEb2UiLCJpc3MiOiJqd3QubG9jYWwiLCJhdWQiOiJleGFtcGxlLmNvbSIsImV4cCI6MTY5MjM3NjM5NH0.-2fF4JjK88CxHp2mMnQfJsqVAAoJStEYsiBmNX3aeq4");
    sample($jwt->is_valid($token));
    sample($jwt->is_valid("eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImlzcyI6Imp3dC5sb2NhbCIsImF1ZCI6ImV4YW1wbGUuY29tIn0.eyJpZCI6IjEiLCJuYW1lIjoiSm9obiBEb2UiLCJpc3MiOiJqd3QubG9jYWwiLCJhdWQiOiJleGFtcGxlLmNvbSIsImV4cCI6MTY5MjM3NjM5NH0.-2fF4JjK88CxHp2mMnQfJsqVAAoJStEYsiBmNX3aeq4"));

    sample($jwt->is_valid('test'));
    ?>
</body>

</html>