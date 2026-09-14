<?php
session_start();
require_once '../config/db.php';

// If user is already logged in, redirect to respective dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            exit();
        case 'doctor':
            header("Location: ../doctor/dashboard.php");
            exit();
        case 'pharmacist':
            header("Location: ../pharmacist/dashboard.php");
            exit();
        case 'patient':
            header("Location: ../patient/dashboard.php");
            exit();
    }
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role     = trim($_POST['role'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($role) || empty($email) || empty($password)) {
        $error = "All fields are required!";
    } else {
        $stmt = $conn->prepare("SELECT user_id, name, email, password_hash, role FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify hashed password
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: ../admin/dashboard.php");
                        break;
                    case 'doctor':
                        header("Location: ../doctor/dashboard.php");
                        break;
                    case 'pharmacist':
                        header("Location: ../pharmacist/dashboard.php");
                        break;
                    case 'patient':
                        header("Location: ../patient/dashboard.php");
                        break;
                }
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "No account found matching this email and role!";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitaGuard Lite - Login</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f4f7f6; }
        .login-card { background: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-card h2 { text-align: center; color: #1c5d5a; margin-bottom: 8px; }
        .login-card p { text-align: center; color: #666; font-size: 14px; margin-bottom: 24px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #333; font-size: 14px; }
        .form-group select, .form-group input { width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; }
        .form-group select:focus, .form-group input:focus { border-color: #1c5d5a; }
        .btn-submit { width: 100%; padding: 12px; background-color: #1c5d5a; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s; }
        .btn-submit:hover { background-color: #144442; }
        .error-msg { background-color: #ffe6e6; color: #d9534f; padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; text-align: center; }
        .footer-link { text-align: center; margin-top: 18px; font-size: 13px; color: #666; }
        .footer-link a { color: #1c5d5a; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Welcome Back</h2>
    <p>Please log in to continue</p>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="role">I am a...</label>
            <select name="role" id="role" required>
                <option value="patient">Patient</option>
                <option value="doctor">Doctor</option>
                <option value="pharmacist">Pharmacist</option>
                <option value="admin">System Administrator</option>
            </select>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" placeholder="example@gmail.com" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-submit">Log In</button>
    </form>

    <div class="footer-link">
        Don't have an account? <a href="register.php">Create one here</a>
    </div>
</div>

</body>
</html>