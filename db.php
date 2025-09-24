<?php
$host = "localhost";
$user = "root";   // الافتراضي في XAMPP
$pass = "";       // الافتراضي فارغ
$db   = "dentalstoredb";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
