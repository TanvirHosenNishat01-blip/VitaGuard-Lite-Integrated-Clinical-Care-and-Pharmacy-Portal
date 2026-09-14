<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('patient'); 
require_once '../config/db.php';

// Fetch recent announcements targeted for patients or all users
$notice_sql = "SELECT title, description, created_at 
               FROM system_notices 
               WHERE target_role = 'patient' OR target_role = 'all' 
               ORDER BY created_at DESC 
               LIMIT 3";
$notices_result = $conn->query($notice_sql);

// Resolve patient display name from session
$patient_name = $_SESSION['name'] ?? 'Patient';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | VitaGuard Lite</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f6; display: flex; }
        
        /* Sidebar Styling */
        .sidebar { width: 250px; background-color: #117a65; color: white; min-height: 100vh; padding: 20px; }
        .sidebar h2 { margin-top: 0; font-size: 22px; }
        .sidebar a { color: #d1f2eb; text-decoration: none; display: block; padding: 12px 0; font-size: 16px; border-bottom: 1px solid #0e6251; }
        .sidebar a:hover { color: #ffffff; padding-left: 5px; transition: 0.3s; }
        
        /* Main Layout */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; }
        .card-container { display: flex; gap: 20px; margin-top: 30px; flex-wrap: wrap; }
        .card { background: white; border: 1px solid #ddd; padding: 25px; border-radius: 8px; flex: 1; min-width: 280px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card h3 { margin-top: 0; color: #117a65; }
        .card p { color: #666; font-size: 14px; line-height: 1.5; }
        
        /* Button Styles */
        .btn { display: inline-block; padding: 10px 16px; background-color: #117a65; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background-color: #0e6251; }
        .btn-danger { background-color: #e74c3c; width: 100%; margin-top: 30px; font-weight: bold; }
        .btn-danger:hover { background-color: #c0392b; }

        /* Announcement Notice Box */
        .notice-box { margin-top: 30px; background: #fff3cd; padding: 20px; border-left: 5px solid #ffc107; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>VitaGuard Lite</h2>
        <p>Role: Patient</p>
        <br>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="book_appointment.php">📅 Book Appointment</a>
        <a href="my_prescriptions.php">📜 My Prescriptions</a>
        <a href="medicine_schedule.php">💊 Medication Tracker</a>
        <a href="log_vitals.php">❤️ Health Vitals</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn btn-danger">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Dynamic Header Greeting -->
        <h1>Welcome, <?php echo htmlspecialchars($patient_name); ?>!</h1>
        <p>Your personal healthcare management overview.</p>
        
        <div class="card-container">
            <!-- Medication Tracker Card -->
            <div class="card">
                <h3>Medication Tracker</h3>
                <p>Track your daily medicine intake and view your prescription schedule.</p>
                <a href="medicine_schedule.php" class="btn">View Schedule</a>
            </div>
            
            <!-- Health Vitals Card -->
            <div class="card">
                <h3>Health Vitals</h3>
                <p>Log your daily blood pressure, sugar levels, and temperature.</p>
                <a href="log_vitals.php" class="btn">Log Vitals</a>
            </div>
        </div>

        <!-- System Announcements Section -->
        <div class="notice-box">
            <h3 style="margin-top: 0; color: #856404;">📢 System Announcements</h3>
            <?php if ($notices_result && $notices_result->num_rows > 0): ?>
                <?php while ($notice = $notices_result->fetch_assoc()): ?>
                    <?php 
                        $n_title = htmlspecialchars($notice['title']);
                        $n_desc  = htmlspecialchars($notice['description']);
                        $n_date  = date("d M Y", strtotime($notice['created_at']));
                    ?>
                    <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #ffeeba;">
                        <strong style="font-size: 16px; color: #856404;"><?php echo $n_title; ?></strong> 
                        <span style="font-size: 12px; color: #b58900;">(<?php echo $n_date; ?>)</span><br>
                        <span style="font-size: 14px; color: #666; display: block; margin-top: 5px;"><?php echo $n_desc; ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #856404; font-size: 14px; margin: 0;">No new announcements at this time.</p>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>