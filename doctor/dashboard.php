<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('doctor'); 
require_once '../config/db.php';

// Get current logged-in doctor's ID
$doctor_id = $_SESSION['user_id'] ?? 0;
$action_msg = "";

// Handle appointment status updates (Approve or Cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appt_id = intval($_POST['appointment_id'] ?? 0);
    $status  = trim($_POST['status'] ?? ''); // 'Approved' or 'Cancelled'
    
    if ($appt_id > 0 && in_array($status, ['Approved', 'Cancelled'])) {
        $update_sql = "UPDATE appointments SET status = ? WHERE appointment_id = ? AND doctor_id = ?";
        $up_stmt = $conn->prepare($update_sql);
        $up_stmt->bind_param('sii', $status, $appt_id, $doctor_id);
        if ($up_stmt->execute()) {
            $action_msg = "Appointment #{$appt_id} marked as {$status}.";
        }
        $up_stmt->close();
    }
}

// Fetch doctor summary statistics
$stats = [
    'pending' => 0,
    'today'   => 0,
    'total'   => 0
];

$today_date = date('Y-m-d');
$stats_sql = "SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN appointment_date = ? AND status != 'Cancelled' THEN 1 ELSE 0 END) AS today
              FROM appointments 
              WHERE doctor_id = ?";
$st_stmt = $conn->prepare($stats_sql);
$st_stmt->bind_param('si', $today_date, $doctor_id);
$st_stmt->execute();
$stats_data = $st_stmt->get_result()->fetch_assoc();
if ($stats_data) {
    $stats['total']   = $stats_data['total'] ?? 0;
    $stats['pending'] = $stats_data['pending'] ?? 0;
    $stats['today']   = $stats_data['today'] ?? 0;
}
$st_stmt->close();

// Fetch active appointments for this doctor (excluding Cancelled)
$sql = "SELECT a.*, u.name AS patient_name, u.phone AS patient_phone 
        FROM appointments a 
        JOIN users u ON a.patient_id = u.user_id 
        WHERE a.doctor_id = ? AND a.status != 'Cancelled'
        ORDER BY a.appointment_date ASC, a.time_slot ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$appointments = $stmt->get_result();

// Fetch announcements targeted for doctors or all users
$notice_sql = "SELECT title, description, created_at 
               FROM system_notices 
               WHERE target_role = 'doctor' OR target_role = 'all' 
               ORDER BY created_at DESC 
               LIMIT 3";
$notices_result = $conn->query($notice_sql);

// Resolve doctor display name
$raw_name = trim($_SESSION['name'] ?? 'Doctor');
$doctor_name = str_starts_with($raw_name, 'Dr.') ? $raw_name : 'Dr. ' . $raw_name;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | VitaGuard Lite</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f6; display: flex; }
        
        /* Sidebar Styling */
        .sidebar { width: 250px; background-color: #2c3e50; color: white; min-height: 100vh; padding: 20px; flex-shrink: 0; }
        .sidebar h2 { margin-top: 0; font-size: 22px; }
        .sidebar p { color: #bdc3c7; font-size: 14px; margin-bottom: 20px; }
        .sidebar a { color: #bdc3c7; text-decoration: none; display: block; padding: 12px 0; font-size: 15px; border-bottom: 1px solid #34495e; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { color: #ffffff; padding-left: 6px; }
        
        /* Main Layout */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; }
        .dashboard-title { margin-top: 0; color: #2c3e50; }
        
        /* Alert Banner */
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }

        /* Stats Cards */
        .stats-grid { display: flex; gap: 20px; margin: 20px 0 30px 0; flex-wrap: wrap; }
        .stat-card { background: white; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; flex: 1; min-width: 180px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .stat-card h4 { margin: 0; color: #7f8c8d; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-value { font-size: 32px; font-weight: bold; color: #2c3e50; margin-top: 8px; }
        
        /* Content Card & Table */
        .card { background: white; border: 1px solid #ddd; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); margin-bottom: 30px; }
        .card h3 { margin-top: 0; color: #2c3e50; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 15px; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: left; }
        th { background-color: #2c3e50; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Status Badges */
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }

        /* Button Styles */
        .btn { display: inline-block; padding: 6px 12px; background-color: #117a65; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn:hover { background-color: #0e6251; }
        .btn-danger { background-color: #e74c3c; width: 100%; margin-top: 30px; font-weight: bold; padding: 10px; }
        .btn-danger:hover { background-color: #c0392b; }
        .btn-danger-inline { background-color: #e74c3c; padding: 6px 12px; border-radius: 4px; border: none; color: white; cursor: pointer; font-size: 13px; }
        .btn-danger-inline:hover { background-color: #c0392b; }

        /* Announcements Box */
        .notice-box { background: #fff8e6; padding: 20px; border-left: 5px solid #f39c12; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>VitaGuard Lite</h2>
        <p>Role: Doctor</p>
        
        <a href="dashboard.php" class="active">🏠 Consultation Queue</a>
        <a href="patient_history.php">🩺 Patient Records</a>
        <a href="create_prescription.php">✍️ Digital Prescription</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn btn-danger">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Dynamic Header Greeting -->
        <h1 class="dashboard-title">Welcome, <?php echo htmlspecialchars($doctor_name); ?>!</h1>
        <p style="color: #666; margin-top: -5px;">Manage your appointments and patient consultations.</p>
        
        <?php if (!empty($action_msg)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($action_msg); ?></div>
        <?php endif; ?>

        <!-- Metric Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Today's Appointments</h4>
                <div class="stat-value"><?php echo $stats['today']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Pending Requests</h4>
                <div class="stat-value" style="color: #e67e22;"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Total Consultations</h4>
                <div class="stat-value" style="color: #117a65;"><?php echo $stats['total']; ?></div>
            </div>
        </div>

        <!-- Consultation Queue Table -->
        <div class="card">
            <h3>Consultation Queue (Appointments)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Contact</th>
                        <th>Date</th>
                        <th>Time Slot</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($appointments && $appointments->num_rows > 0): ?>
                    <?php while ($row = $appointments->fetch_assoc()): ?>
                        <?php 
                            $id     = $row['appointment_id'];
                            $name   = htmlspecialchars($row['patient_name']);
                            $phone  = htmlspecialchars($row['patient_phone'] ?? 'N/A');
                            $date   = date("d M Y", strtotime($row['appointment_date']));
                            $time   = htmlspecialchars($row['time_slot']);
                            $status = htmlspecialchars($row['status']);
                        ?>
                        <tr>
                            <td><strong><?php echo $name; ?></strong></td>
                            <td><?php echo $phone; ?></td>
                            <td><?php echo $date; ?></td>
                            <td><?php echo $time; ?></td>
                            <td>
                                <span class="badge <?php echo $status === 'Approved' ? 'badge-approved' : 'badge-pending'; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($status === 'Pending'): ?>
                                    <form action="" method="post" style="display:inline;">
                                        <input type="hidden" name="appointment_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="status" value="Approved">
                                        <button type="submit" name="update_status" class="btn">Approve</button>
                                    </form>
                                <?php endif; ?>
                                
                                <form action="" method="post" style="display:inline;" onsubmit="return confirm('Cancel this appointment?');">
                                    <input type="hidden" name="appointment_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="status" value="Cancelled">
                                    <button type="submit" name="update_status" class="btn btn-danger-inline">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color: #888; padding: 25px;">No upcoming appointments found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- System Announcements Section -->
        <div class="notice-box">
            <h3 style="margin-top: 0; color: #b7791f;">📢 System Announcements</h3>
            <?php if ($notices_result && $notices_result->num_rows > 0): ?>
                <?php while ($notice = $notices_result->fetch_assoc()): ?>
                    <?php 
                        $n_title = htmlspecialchars($notice['title']);
                        $n_desc  = htmlspecialchars($notice['description']);
                        $n_date  = date("d M Y", strtotime($notice['created_at']));
                    ?>
                    <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #fce5b5;">
                        <strong style="font-size: 15px; color: #78350f;"><?php echo $n_title; ?></strong> 
                        <span style="font-size: 12px; color: #92400e;">(<?php echo $n_date; ?>)</span><br>
                        <span style="font-size: 13.5px; color: #4b5563; display: block; margin-top: 4px;"><?php echo $n_desc; ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #92400e; font-size: 14px; margin: 0;">No announcements published for doctors.</p>
            <?php endif; ?>
        </div>
        
    </div>

</body>
</html>