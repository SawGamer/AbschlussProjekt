<?php

require_once('../Models/Database.php');
require_once('../Models/JwtHandler.php');
require_once('../utils/HelperFunctions.php');
session_start();

class AuthController
{
    private $database;
    private $jwtHandler;

    public function __construct($database, $jwtConfig)
    {
        $this->database = $database;
        $this->jwtHandler = new JwtHandler($jwtConfig);
    }



    public function login($username, $password)
    {
        $username = HelperFunctions::sanitizeInput($username);
        $password = md5(HelperFunctions::sanitizeInput($password));

        $user = $this->database->checkLogindata($username, $password);
        $count = $user->numRows();
        if ($count == 1) {
            $id = $this->database->getID($username)[0]['id'];




            session_name('fairverify_Workforce');
            session_start();
            $token = $this->jwtHandler->generate([
                'name' => $username,
                'id' => $id,
                'iss' => 'jwt.Fair',
                'aud' => 'Fair.com'
            ]);

            $_SESSION["logged_in"] = $token;


            // header("location: ../public/views/test.php?id={$id}"); //TEST !
            if ($username == "Admin-FV") {
                header("location: ../admin_view.php");
            } else {

                header("location: ../user_view.php?id={$id}");
            }
        } else {
            http_response_code(401);
            return json_encode(['error' => 'Invalid credentials']);
        }
    }

    public function validate($token, $id)
    {
        $validate1 = $this->jwtHandler->is_valid($token);
        $validate2 = $this->jwtHandler->is_validTI($token, $id);
        return ($validate2 && $validate1);

    }
    public function removeauth($jwt, $id)
    {
        $this->jwtHandler->remove($jwt, $id);
    }


}
;
