<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('admin'); 
require_once '../config/db.php';

// Initialize role counters
$stats = [
    'patient'    => 0,
    'doctor'     => 0,
    'pharmacist' => 0,
    'admin'      => 0
];

// Fetch total count for each role from users table
$sql = "SELECT role, COUNT(*) as total FROM users GROUP BY role";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if (array_key_exists($row['role'], $stats)) {
            $stats[$row['role']] = (int)$row['total'];
        }
    }
}

// Fetch total medicines count
$med_query = $conn->query("SELECT COUNT(*) as total_meds FROM medicines");
$total_medicines = ($med_query && $med_row = $med_query->fetch_assoc()) ? (int)$med_row['total_meds'] : 0;

// Fetch total broadcast notices
$notice_query = $conn->query("SELECT COUNT(*) as total_notices FROM system_notices");
$total_notices = ($notice_query && $n_row = $notice_query->fetch_assoc()) ? (int)$n_row['total_notices'] : 0;

// Fetch 5 most recent user registrations
$recent_users_sql = "SELECT user_id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($recent_users_sql);

// Resolve dynamic admin name from session
$admin_name = trim($_SESSION['name'] ?? 'System Administrator');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Administrator Dashboard | VitaGuard Lite</title>
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
        .dashboard-title { margin-top: 0; color: #1b4f72; }
        
        /* Metric Cards Grid */
        .card-container { display: flex; gap: 20px; margin: 25px 0 30px 0; flex-wrap: wrap; }
        .card { background: white; border: 1px solid #e2e8f0; padding: 22px; border-radius: 8px; flex: 1; min-width: 190px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); text-align: center; }
        .card h3 { margin: 0; color: #64748b; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .card h1 { margin: 10px 0 0 0; color: #1b4f72; font-size: 38px; }
        
        /* Content Card & Table */
        .content-card { background: white; border: 1px solid #ddd; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); margin-bottom: 30px; }
        .content-card h3 { margin-top: 0; color: #1b4f72; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 15px; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: left; }
        th { background-color: #1b4f72; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Role Badges */
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
        .badge-admin { background: #e0f2fe; color: #0369a1; }
        .badge-doctor { background: #fef3c7; color: #92400e; }
        .badge-pharmacist { background: #ede9fe; color: #5b21b6; }
        .badge-patient { background: #dcfce7; color: #166534; }

        /* Button Styles */
        .btn-danger { background-color: #e74c3c; color: white; padding: 10px 15px; border-radius: 5px; margin-top: 30px; border: none; cursor: pointer; width: 100%; font-weight: bold; font-size: 14px; }
        .btn-danger:hover { background-color: #c0392b; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>VitaGuard Lite</h2>
        <p>Role: System Admin</p>
        
        <a href="dashboard.php" class="active">📊 Overview</a>
        <a href="manage_users.php">👥 User Management</a>
        <a href="drug_rules.php">📢 Broadcast Notices</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-danger">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1 class="dashboard-title">System Administrator Dashboard</h1>
        <!-- Dynamic greeting displaying the database user name -->
        <p style="color: #666; margin-top: -5px;">Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong>! Here is the current system overview.</p>
        
        <!-- Quick Statistics Row -->
        <div class="card-container">
            <div class="card">
                <h3>Total Patients</h3>
                <h1><?php echo $stats['patient']; ?></h1>
            </div>
            
            <div class="card">
                <h3>Active Doctors</h3>
                <h1><?php echo $stats['doctor']; ?></h1>
            </div>
            
            <div class="card">
                <h3>Pharmacists</h3>
                <h1><?php echo $stats['pharmacist']; ?></h1>
            </div>

            <div class="card">
                <h3>Medicines in Stock</h3>
                <h1 style="color: #0e6251;"><?php echo $total_medicines; ?></h1>
            </div>
            
            <div class="card">
                <h3>Active Notices</h3>
                <h1 style="color: #b7791f;"><?php echo $total_notices; ?></h1>
            </div>
        </div>

        <!-- Recent Registrations Overview -->
        <div class="content-card">
            <h3>Recently Registered Accounts</h3>
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Assigned Role</th>
                        <th>Joined Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                    <?php while ($user = $recent_users->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($user['user_id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($user['role']); ?>">
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date("d M Y, h:i A", strtotime($user['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color: #888; padding: 20px;">No user records found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>