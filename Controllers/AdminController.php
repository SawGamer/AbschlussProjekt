<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../utils/HelperFunctions.php';

class AdminController
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * All employees with this month's hours. Password hashes are never selected.
     */
    public function getAllUsers(): void
    {
        $users = $this->database->getAllUsers((int) date('Y'), (int) date('n'));
        HelperFunctions::json($users);
    }
}
