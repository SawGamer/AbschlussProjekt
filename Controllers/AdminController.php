<?php

require_once('../Models/Database.php');

class AdminController
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function getAllUsers()
    {
        $allUsers = $this->database->getAllUsers();

        header('Content-Type: application/json');
        echo json_encode($allUsers);
    }

    public function LogOut()
    {
        session_unset();
        header("location:../public/views/logout.php");

    }
}