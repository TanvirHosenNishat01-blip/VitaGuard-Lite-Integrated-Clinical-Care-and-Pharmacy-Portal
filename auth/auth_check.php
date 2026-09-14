<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_access($allowed_role) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    if ($_SESSION['role'] !== $allowed_role) {
        switch ($_SESSION['role']) {
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
            default:
                header("Location: ../auth/login.php");
                break;
        }
        exit();
    }
}
?>