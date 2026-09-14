<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('admin'); 
require_once '../config/db.php';

$message = "";
$admin_id = $_SESSION['user_id'] ?? 0;

// 1. Create Notice (Publish a new announcement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_notice'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $target_role = strtolower(trim($_POST['target_role'] ?? 'all')); // 'all', 'patient', 'doctor', 'pharmacist'
    
    if (!empty($title) && !empty($description)) {
        $sql = "INSERT INTO system_notices (admin_id, title, description, target_role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isss', $admin_id, $title, $description, $target_role);
        
        if ($stmt->execute()) {
            $message = "<div class='alert-success'>Notice published successfully!</div>";
        } else {
            $message = "<div class='alert-error'>Failed to publish notice.</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='alert-error'>Please fill in all required fields.</div>";
    }
}

// 2. Delete Notice (Remove an announcement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notice'])) {
    $notice_id = intval($_POST['notice_id'] ?? 0);
    
    if ($notice_id > 0) {
        $sql_delete = "DELETE FROM system_notices WHERE notice_id = ?";
        $stmt_del = $conn->prepare($sql_delete);
        $stmt_del->bind_param('i', $notice_id);
        
        if ($stmt_del->execute()) {
            $message = "<div class='alert-success'>Notice deleted successfully!</div>";
        }
        $stmt_del->close();
    }
}

// 3. Read Notices (Fetch all announcements)
$sql_notices = "SELECT * FROM system_notices ORDER BY created_at DESC";
$notices_result = $conn->query($sql_notices);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcast Notices | VitaGuard Lite</title>
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
        .layout-grid { display: flex; gap: 20px; align-items: flex-start; }
        .form-section { flex: 1; background: white; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .list-section { flex: 2; background: white; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        
        /* Form Inputs */
        .form-section label { font-size: 13px; font-weight: 600; color: #475569; display: block; margin-top: 12px; }
        .form-section input[type="text"], 
        .form-section select, 
        .form-section textarea { 
            width: 100%; 
            padding: 10px; 
            margin: 6px 0 12px 0; 
            border: 1px solid #cbd5e1; 
            border-radius: 5px; 
            font-family: inherit; 
            font-size: 14px; 
        }
        
        /* Table Layout */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: left; }
        th { background-color: #1b4f72; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Buttons */
        .btn-primary { background-color: #117a65; color: white; padding: 11px 15px; border-radius: 5px; border: none; cursor: pointer; width: 100%; font-size: 15px; font-weight: 600; }
        .btn-primary:hover { background-color: #0e6251; }
        .btn-danger { background-color: #e74c3c; color: white; padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; }
        .btn-danger:hover { background-color: #c0392b; }
        .btn-logout { background-color: #e74c3c; color: white; padding: 10px 15px; border-radius: 5px; margin-top: 30px; border: none; cursor: pointer; width: 100%; font-weight: bold; }
        
        /* Status Badges and Alerts */
        .target-badge { padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; color: white; background-color: #34495e; text-transform: uppercase; }
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
        <a href="manage_users.php">👥 User Management</a>
        <a href="drug_rules.php" class="active">📢 Broadcast Notices</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2 style="margin-top: 0; color: #1b4f72;">System Notices & Announcements</h2>
        
        <!-- Alert feedback message -->
        <?php if (!empty($message)) echo $message; ?>

        <div class="layout-grid">
            
            <!-- Broadcast Notice Composition Form -->
            <div class="form-section">
                <h3 style="margin-top: 0; color: #334155;">Publish New Notice</h3>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                    <label>Notice Title</label>
                    <input type="text" name="title" placeholder="e.g. Scheduled System Maintenance" required>
                    
                    <label>Target Audience</label>
                    <select name="target_role" required>
                        <option value="all">All Users</option>
                        <option value="patient">Patients Only</option>
                        <option value="doctor">Doctors Only</option>
                        <option value="pharmacist">Pharmacists Only</option>
                    </select>
                    
                    <label>Message Content</label>
                    <textarea name="description" rows="5" placeholder="Write your announcement details here..." required></textarea>
                    
                    <button type="submit" name="publish_notice" class="btn-primary">Publish Notice</button>
                </form>
            </div>

            <!-- Published Notices Table List -->
            <div class="list-section">
                <h3 style="margin-top: 0; color: #334155;">Notice History</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title & Content</th>
                            <th>Target</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($notices_result && $notices_result->num_rows > 0): ?>
                        <?php while ($row = $notices_result->fetch_assoc()): ?>
                            <?php 
                                $id = $row['notice_id'];
                                $date = date("d M Y", strtotime($row['created_at']));
                                $title = htmlspecialchars($row['title']);
                                $desc = htmlspecialchars($row['description']);
                                $target = htmlspecialchars($row['target_role']);
                            ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo $date; ?></td>
                                <td>
                                    <strong><?php echo $title; ?></strong><br>
                                    <span style="font-size: 13px; color: #64748b;"><?php echo $desc; ?></span>
                                </td>
                                <td><span class="target-badge"><?php echo $target; ?></span></td>
                                <td>
                                    <form action="" method="post" onsubmit="return confirm('Are you sure you want to delete this notice?');">
                                        <input type="hidden" name="notice_id" value="<?php echo $id; ?>">
                                        <button type="submit" name="delete_notice" class="btn-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color: #888; padding: 20px;">No notices published yet.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</body>
</html>