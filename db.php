<?php
$host+ = "cluster0.mghjfgs.mongodb.net";
$user = "happy1boss123_db_user";
$pass = "NmM8AsXrNzupdMKp";
$dbname = "happy1boss123_db_user";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
