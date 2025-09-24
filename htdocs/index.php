
<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

session_start();
include "db.php"; // ملف الاتصال بقاعدة البيانات
$error = "";


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        // تشفير كلمة المرور باستخدام SHA256 مثل C#
        $hashedPassword = hash('sha256', $password);

        // Prepared statement للبحث عن Customer فقط
        $stmt = $conn->prepare("
            SELECT u.UserID, ud.FullName
            FROM users u
            INNER JOIN userdetails ud ON u.UserID = ud.UserID
            WHERE u.Email = ? AND u.PasswordHash = ? AND ud.Role = 'Customer'
        ");

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ss", $email, $hashedPassword);

        if (!$stmt->execute()) {
            die("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION["UserID"] = $user['UserID'];
            $_SESSION["FullName"] = $user['FullName'];
            $_SESSION["email"] = $email;
            $_SESSION["role"] = 'Customer';
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }

        $stmt->close();
    } else {
        $error = "Please enter both email and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Login - Dentara</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="brand">
        <div class="brand-name">Dentara Store</div>
        <div class="brand-tagline">.وفر وقتك، وخلي الباقي علينا</div>
    </div>
    <form method="POST" action="">
        <h2>Customer Login</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button type="submit">Login</button>
        <p><a href="signup.php">Sign Up</a></p>
    </form>
</div>
</body>
</html>
