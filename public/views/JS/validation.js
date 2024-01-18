$(document).ready(function () {
    $('#username').on('input', function () {
        checkuser();
    });
    $('#password').on('input', function () {
        checkpass();
    });


    $('#submitbtn').click(function () {


        if (!checkuser() && !checkpass()) {
            console.log("er1");
            $("#message").html(`<div class="alert alert-warning">Please fill all required field</div>`);
        } else if (!checkuser() || !checkpass()) {
            $("#message").html(`<div class="alert alert-warning">Please fill all required field</div>`);
            console.log("er");
        }
        else {
            console.log("ok");
            $("#message").html("");
            var form = $('#myform')[0];
            var data = new FormData(form);
           
        }
    });
});


function checkuser() {
    var pattern = /^[A-Za-z0-9]+$/;
    var user = $('#username').val();
    var validuser = pattern.test(user);

    if (!validuser) {
        $('#username_err').html('OHOH !!');
        return false;
    } else {
        $('#username_err').html('');
        return true;
    }
}

function checkpass() {
   
    return true;

}




function password_show_hide() {
    console.log('ok');
    var x = document.getElementById("password");
    var show_eye = document.getElementById("show_eye");
    var hide_eye = document.getElementById("hide_eye");
    hide_eye.classList.remove("d-none");
    if (x.type === "password") {
        x.type = "text";
        show_eye.style.display = "none";
        hide_eye.style.display = "block";
    } else {
        x.type = "password";
        show_eye.style.display = "block";
        hide_eye.style.display = "none";
    }
}
