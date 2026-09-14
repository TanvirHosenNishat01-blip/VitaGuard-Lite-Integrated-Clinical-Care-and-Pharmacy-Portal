<?php
// Security guard and database connection
require_once '../auth/auth_check.php';
check_access('admin'); 
require_once '../config/db.php';

$message = "";

// Handle user account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target_user_id = intval($_POST['user_id'] ?? 0);
    
    // Prevent deletion of admin accounts or invalid IDs
    if ($target_user_id > 0) {
        $sql_delete = "DELETE FROM users WHERE user_id = ? AND role != 'admin'";
        $stmt_del = $conn->prepare($sql_delete);
        $stmt_del->bind_param('i', $target_user_id);
        
        if ($stmt_del->execute()) {
            $message = "<div class='alert-success'>User account deleted successfully!</div>";
        } else {
            $message = "<div class='alert-error'>Failed to delete user account.</div>";
        }
        $stmt_del->close();
    }
}

// Fetch all non-admin users ordered by newest registrations
$sql_users = "SELECT user_id, name, email, role, phone, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC";
$users_result = $conn->query($sql_users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | VitaGuard Lite</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f6; display: flex; }
        
        /* Sidebar Styling */
        .sidebar { width: 250px; background-color: #1b4f72; color: white; min-height: 100vh; padding: 20px; flex-shrink: 0; }
        .sidebar h2 { margin-top: 0; font-size: 22px; }
        .sidebar p { color: #a9cce3; font-size: 14px; margin-bottom: 20px; }
        .sidebar a { color: #a9cce3; text-decoration: none; display: block; padding: 12px 0; font-size: 15px; border-bottom: 1px solid #21618c; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { color: #ffffff; padding-left: 6px; }
        
        /* Main Layout */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; }
        .page-header { margin-top: 0; color: #1b4f72; }
        
        /* Table Layout */
        .table-container { background: white; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); overflow: hidden; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: left; }
        th { background-color: #1b4f72; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Role Badges */
        .role-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: white; display: inline-block; }
        .role-patient { background-color: #117a65; }
        .role-doctor { background-color: #2c3e50; }
        .role-pharmacist { background-color: #f39c12; }
        
        /* Button Styles */
        .btn-danger { background-color: #e74c3c; color: white; padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; }
        .btn-danger:hover { background-color: #c0392b; }
        .btn-logout { background-color: #e74c3c; color: white; padding: 10px 15px; border-radius: 5px; margin-top: 30px; border: none; cursor: pointer; width: 100%; font-weight: bold; }
        
        /* Alerts */
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px 14px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px 14px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>VitaGuard Lite</h2>
        <p>Role: System Admin</p>
        
        <a href="dashboard.php">📊 Overview</a>
        <a href="manage_users.php" class="active">👥 User Management</a>
        <a href="drug_rules.php">📢 Broadcast Notices</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2 class="page-header">User Accounts Management</h2>
        
        <!-- Feedback messages -->
        <?php if (!empty($message)) echo $message; ?>

        <!-- Users Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Phone</th>
                        <th>Assigned Role</th>
                        <th>Joined Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($users_result && $users_result->num_rows > 0): ?>
                    <?php while ($row = $users_result->fetch_assoc()): ?>
                        <?php 
                            $id = $row['user_id'];
                            $name = htmlspecialchars($row['name']);
                            $email = htmlspecialchars($row['email']);
                            $phone = htmlspecialchars(!empty($row['phone']) ? $row['phone'] : 'N/A');
                            $role = htmlspecialchars($row['role']);
                            $joined = date("d M Y", strtotime($row['created_at']));
                            $role_class = 'role-' . strtolower($role);
                        ?>
                        <tr>
                            <td>#<?php echo $id; ?></td>
                            <td><strong><?php echo $name; ?></strong></td>
                            <td><?php echo $email; ?></td>
                            <td><?php echo $phone; ?></td>
                            <td><span class="role-badge <?php echo $role_class; ?>"><?php echo $role; ?></span></td>
                            <td><?php echo $joined; ?></td>
                            <td>
                                <form action="" method="post" onsubmit="return confirm('Are you sure you want to permanently delete this user account?');">
                                    <input type="hidden" name="user_id" value="<?php echo $id; ?>">
                                    <button type="submit" name="delete_user" class="btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color: #888; padding: 20px;">No registered users found in the system.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>