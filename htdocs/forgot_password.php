<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

session_start();
include "db.php"; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email        = trim($_POST['email']);
    $phone        = trim($_POST['phone']);
    $newPassword  = trim($_POST['new_password']);
    $confirmPass  = trim($_POST['confirm_password']);

    if (!empty($email) && !empty($phone) && !empty($newPassword) && !empty($confirmPass)) {
        
        if ($newPassword !== $confirmPass) {
            $msg = "<p class='error'>Passwords do not match!</p>";
        } else {
            $stmt = $conn->prepare("SELECT u.UserID 
                                    FROM users u 
                                    INNER JOIN userdetails ud ON u.UserID = ud.UserID
                                    WHERE u.Email = ? AND ud.Phone = ?");
            $stmt->bind_param("ss", $email, $phone);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $hashedPassword = hash('sha256', $newPassword);

                $updateStmt = $conn->prepare("UPDATE users SET PasswordHash = ? WHERE Email = ?");
                $updateStmt->bind_param("ss", $hashedPassword, $email);
                $updateStmt->execute();
                $updateStmt->close();

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = "smtp.gmail.com"; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = "aldubai16osama@gmail.com"; 
                    $mail->Password   = "tcna czam ktuk yvoc"; 
                    $mail->SMTPSecure = "tls";
                    $mail->Port       = 587;

                    $mail->setFrom("aldubai16osama@gmail.com", "Dentara Store");
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = "Dentara Store - Password Changed";
                    $mail->Body    = "
                        <h3>Your password has been updated successfully</h3>
                        <p>Email: <b>$email</b></p>
                        <p>If this was not you, please contact support immediately.</p>
                    ";

                    $mail->send();

                    // ✅ بعد النجاح، توجه المستخدم مباشرة لصفحة تسجيل الدخول
                    header("Location: index.php?reset=success");
                    exit;
                } catch (Exception $e) {
                    $msg = "<p class='error'>Email could not be sent. Mailer Error: {$mail->ErrorInfo}</p>";
                }
            } else {
                $msg = "<p class='error'>Invalid email or phone number.</p>";
            }
            $stmt->close();
        }
    } else {
        $msg = "<p class='error'>Please fill all fields.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Dentara</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="brand">
        <div class="brand-name">Dentara Store</div>
        <div class="brand-tagline">.وفر وقتك، وخلي الباقي علينا</div>
    </div>

    <h2>Reset Password</h2>
    <?php echo $msg; ?>
    <form method="POST">
        <div class="form-group">
            <label>Email <span class="required">*</span></label>
            <input type="email" name="email" placeholder="Enter your email" required>
        </div>
        <div class="form-group">
            <label>Phone Number <span class="required">*</span></label>
            <input type="text" name="phone" placeholder="Enter your phone number" required>
        </div>
        <div class="form-group">
            <label>New Password <span class="required">*</span></label>
            <input type="password" name="new_password" placeholder="Enter new password" required>
        </div>
        <div class="form-group">
            <label>Confirm Password <span class="required">*</span></label>
            <input type="password" name="confirm_password" placeholder="Confirm new password" required>
        </div>
        <button type="submit">Update Password</button>
        <p class="login-link"><a href="index.php">Back to Login</a></p>
    </form>
</div>
</body>
</html>
