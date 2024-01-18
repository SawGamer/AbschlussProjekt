<?php

require_once('../Models/Database.php');
require_once('../utils/HelperFunctions.php');

class UserController
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function getUserprofile($userId)
    {
        $userProfile = $this->database->getUserprofile($userId);


        header('Content-Type: application/json');
        echo json_encode($userProfile);

    }

    public function getUserDataHour($userId)
    {
        $userHourLog = $this->database->getUserDataHour($userId);


        if ($userHourLog) {
            header('Content-Type: application/json');
            echo json_encode($userHourLog);
        } else {
            header('Content-Type: application/json');

            echo ($this->getUserprofile($userId));
        }



    }
    public function getUserHoursLogMonth($year, $month, $userId)
    {
        $userHours = $this->database->getHoursLogMonth($year, $month, $userId);


        header('Content-Type: application/json');
        echo json_encode($userHours);
    }

    public function getUserHoursLogYear($year, $userId)
    {
        $userHours = $this->database->getHoursLogYear($year, $userId);


        header('Content-Type: application/json');
        echo json_encode($userHours);
    }



    public function logTime($userId, $Time, $pause)
    {
        // $formattedDate = date('Y-m-d H:i', strtotime($startDate));



        $success = $this->database->logTime($userId, $Time, $pause);

        if ($success) {
            echo json_encode(['message' => 'Time logged successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to log time']);
        }
    }


    public function LogOut()
    {

        header("location:/NewProject/public/views/logout.php");

    }


}
