<?php
session_start();
require_once '../config/db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = trim($_POST['role'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    // Form validations
    if (empty($name) || empty($email) || empty($phone) || empty($role) || empty($password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "This email is already registered!";
        } else {
            // Hash password and insert record
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $password_hash, $role, $phone);

            if ($stmt->execute()) {
                $success = "Registration successful! You can now log in.";
            } else {
                $error = "Registration failed! Please try again.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitaGuard Lite - Register</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f4f7f6; padding: 20px 0; }
        .reg-card { background: #ffffff; padding: 35px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        .reg-card h2 { text-align: center; color: #1c5d5a; margin-bottom: 8px; }
        .reg-card p { text-align: center; color: #666; font-size: 14px; margin-bottom: 20px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 13px; }
        .form-group select, .form-group input { width: 100%; padding: 9px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; }
        .form-group select:focus, .form-group input:focus { border-color: #1c5d5a; }
        .btn-submit { width: 100%; padding: 12px; background-color: #1c5d5a; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 10px; }
        .btn-submit:hover { background-color: #144442; }
        .error-msg { background-color: #ffe6e6; color: #d9534f; padding: 10px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; text-align: center; }
        .success-msg { background-color: #e6ffed; color: #28a745; padding: 10px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; text-align: center; }
        .footer-link { text-align: center; margin-top: 16px; font-size: 13px; color: #666; }
        .footer-link a { color: #1c5d5a; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="reg-card">
    <h2>Create an Account</h2>
    <p>Sign up to access VitaGuard Lite</p>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" name="name" id="name" placeholder="John Doe" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" placeholder="example@gmail.com" required>
        </div>

        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" name="phone" id="phone" placeholder="017xxxxxxxx" required>
        </div>

        <div class="form-group">
            <label for="role">Register as</label>
            <select name="role" id="role" required>
                <option value="patient">Patient</option>
                <option value="doctor">Doctor</option>
                <option value="pharmacist">Pharmacist</option>
                <option value="admin">System Administrator</option>
            </select>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="••••••••" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-submit">Register</button>
    </form>

    <div class="footer-link">
        Already have an account? <a href="login.php">Log In here</a>
    </div>
</div>

</body>
</html>