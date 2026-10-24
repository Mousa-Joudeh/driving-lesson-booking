<?php

require_once __DIR__ . "/config/Database.php";
$database = new Database();
$conn = $database->connect();
echo "Database Connected Successfully!";