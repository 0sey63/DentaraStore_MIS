<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

session_start();
include "db.php";

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $fullname = trim($_POST['fullname']);
    $gender = $_POST['gender'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    // Validate form data
    if (empty($email) || empty($password) || empty($fullname) || empty($phone) || empty($address)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists
        $check_email = $conn->prepare("SELECT UserID FROM users WHERE Email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();

        if ($check_email->num_rows > 0) {
            $error = "Email already exists. Please use a different email.";
        } else {
            // Hash the password
            $hashedPassword = hash('sha256', $password);

            // Insert into Users table
            $stmt = $conn->prepare("INSERT INTO users (Email, PasswordHash) VALUES (?, ?)");
            $stmt->bind_param("ss", $email, $hashedPassword);

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;

                // Insert into UserDetails table
                $stmt2 = $conn->prepare("INSERT INTO userdetails (UserID, FullName, Gender, Phone, Address, Role) VALUES (?, ?, ?, ?, ?, 'Customer')");
                $stmt2->bind_param("issss", $user_id, $fullname, $gender, $phone, $address);

                if ($stmt2->execute()) {
                    // Set session variables
                    $_SESSION["UserID"] = $user_id;
                    $_SESSION["FullName"] = $fullname;
                    $_SESSION["email"] = $email;
                    $_SESSION["role"] = 'Customer';

                    $success = "Account created successfully!";
                    // Redirect to index.php
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Error creating user details. Please try again.";
                }
                $stmt2->close();
            } else {
                $error = "Error creating account. Please try again.";
            }
            $stmt->close();
        }
        $check_email->close();
    }
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Signup - Dentara</title>
    <link rel="stylesheet" href="style.css">
   
</head>
<body>
    <div class="container">
        <div class="brand">
            <div class="brand-name">Dentara Store</div>
            <div class="brand-tagline">.وفر وقتك، وخلي الباقي علينا</div>
        </div>
        
        <div class="form-container">
            <h2>Customer Sign Up</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <label for="fullname">Full Name <span class="required">*</span></label>
                    <input type="text" id="fullname" name="fullname" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address <span class="required">*</span></label>
                    <input type="text" id="address" name="address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required>
                </div>
                
                <button type="submit">Create Account</button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="index.php">Login here</a>
            </div>
        </div>
    </div>
</body>
</html>