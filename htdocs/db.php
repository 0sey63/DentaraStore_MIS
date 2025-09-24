<?php
$host = 'sql213.infinityfree.com';
$user = 'if0_39920796';  // المستخدم الذي أنشأته
$pass = '04w92v0TnJB';
$db   = 'if0_39920796_dentalstoredb';    // اسم القاعدة الصحيح

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
