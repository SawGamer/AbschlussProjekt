<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//require_once('../api/config/database.php');
require_once('../Config/jwt.php');
require_once('../Controllers/AuthController.php');
require_once('../Controllers/UserController.php');
require_once('../Controllers/AdminController.php');


$headers = getallheaders();
$database = new Database;

$authController = new AuthController($database, $jwtConfig);

$userController = new UserController($database);
$adminController = new AdminController($database);

$requestUri = parse_url($_SERVER['REQUEST_URI'])['path'];

$token = isset($headers['Authorization']) ? $headers['Authorization'] : "null";


// Login 
if (strpos($requestUri, '/api/login') !== false) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    echo $authController->login($username, $password);
    exit;
}
//Token
if (strpos($requestUri, '/authenticate') !== false) {
    header('Content-Type: application/json');
    if (isset($_SESSION['logged_in'])) {
        echo json_encode(['jwt' => $_SESSION["logged_in"]]);
        unset($_SESSION["logged_in"]);
        exit;
    }
    echo "NOTHING!";
    exit;


}

if ($requestUri === '/NewProject/public/api/user/getUserprofile') {
    if ($token) {
        $userId = $_GET['id'];
        if ($authController->validate($token, $userId)) {
            return $userController->getUserprofile($userId);
        }
        http_response_code(401);
        echo json_encode(['error' => 'Manipulation , this Accident would be reported']);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
    }
    exit;
}


if ($requestUri === '/NewProject/public/api/user/get-user-data') {
    $userId = $_GET['id'];

    if ($token && $authController->validate($token, $userId)) {

        return $userController->getUserDataHour($userId);

        http_response_code(401);
        echo json_encode(['error' => 'Manipulation , this Accident would be reported']);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
    }
    exit;
}



if (strpos($requestUri, '/api/admin/dashboard' !== false)) {


    if ($token && $authController->validate($token, $userId)) {
        $adminController->getAllUsers();
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
    }
    exit;
}




if ($requestUri === '/NewProject/public/api/user/LogView') {
    $userId = $_GET['id'];

    if ($token && $authController->validate($token, $userId)) {
        $year = $_GET['year'];
        if (isset($_GET['month'], $_GET['id'], $_GET['year'])) {
            $month = $_GET['month'];
            $userController->getUserHoursLogMonth($year, $month, $userId);
        } elseif ($year and $userId) {
            $userController->getUserHoursLogYear($year, $userId);

        } else {
            http_response_code(500);

            echo json_encode(['error' => 'Failed to get parameter']);
        }
    } else {
        http_response_code(401);
        print_r($headers);
        echo json_encode(['error' => 'Unauthorized']);
    }
    exit;
}



if ($requestUri === '/NewProject/public/api/Time') {
    $userId = $_GET['id'];

    if ($token && $authController->validate($token, $userId) && isset($_GET['time'])) {
        $time = $_GET['time'];
        $pause = $_GET['pause'];

        $userController->logTime($userId, $time, $pause);



    } else {
        http_response_code(401);
        print_r($headers);
        echo json_encode(['error' => 'Unauthorized']);
    }
    exit;
}

if ($requestUri === '/NewProject/public/api/user/log-hours') {
    header('Content-Type: text/json');
    $body = json_decode(file_get_contents("php://input"), true);
    $userId = $body['id'];
    print_r($body);

    if ($token && $authController->validate($token, $userId)) {
        $actual_hours = $body['actual_hours'];
        $date = $body['date'];
        $startHour = $body['start_time'];
        $endHour = $body['end_time'];
        $type = $body['type'];

        $comment = $body['comment'];

        $overtime = $body['overtime'];
        $pause = $body['pause'];

        $success = $database->editLog(
            $userId,
            $actual_hours,
            $startHour,
            $endHour,
            $overtime,

            $type,
            $date,

            $comment,
            $pause
        )
        ;

        if ($success) {
            echo json_encode(['message' => 'Hours logged successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to log hours']);
        }
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
    }
    exit;
}


/*if ($requestUri === '/NewProject/public/api/logout') {
    $userController->logout();
    exit;

}*/



//Not Found
http_response_code(404);
echo $requestUri;
print_r(strpos($requestUri, 'api/user/get-user-data' !== false));
echo ' NotT Found';
