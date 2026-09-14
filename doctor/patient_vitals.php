<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('doctor'); 
require_once '../config/db.php';

$patient_info = null;
$vitals_result = null;
$error_msg = "";

// Search patient health records by Patient ID
if (isset($_GET['search_patient']) && !empty($_GET['patient_id'])) {
    $search_id = intval($_GET['patient_id'] ?? 0);
    
    if ($search_id > 0) {
        // Fetch patient profile details
        $sql_user = "SELECT user_id, name, email, phone FROM users WHERE user_id = ? AND role = 'patient'";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param('i', $search_id);
        $stmt_user->execute();
        $res_user = $stmt_user->get_result();
        
        if ($res_user && $res_user->num_rows > 0) {
            $patient_info = $res_user->fetch_assoc();
            
            // Fetch patient logged vitals history
            $sql_vitals = "SELECT * FROM health_records WHERE patient_id = ? ORDER BY recorded_at DESC";
            $stmt_vitals = $conn->prepare($sql_vitals);
            $stmt_vitals->bind_param('i', $search_id);
            $stmt_vitals->execute();
            $vitals_result = $stmt_vitals->get_result();
        } else {
            $error_msg = "<div class='alert-error'>No patient record found matching ID #" . htmlspecialchars($search_id) . "</div>";
        }
        $stmt_user->close();
    } else {
        $error_msg = "<div class='alert-error'>Please enter a valid patient ID.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records | VitaGuard Lite</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f6; display: flex; }
        
        /* Sidebar Navigation */
        .sidebar { width: 250px; background-color: #2c3e50; color: white; min-height: 100vh; padding: 20px; flex-shrink: 0; }
        .sidebar h2 { margin-top: 0; font-size: 22px; }
        .sidebar p { color: #bdc3c7; font-size: 14px; margin-bottom: 20px; }
        .sidebar a { color: #bdc3c7; text-decoration: none; display: block; padding: 12px 0; font-size: 15px; border-bottom: 1px solid #34495e; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { color: #ffffff; padding-left: 6px; }
        
        /* Main Layout */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; }
        .page-title { margin-top: 0; color: #2c3e50; }
        
        /* Search Bar Box */
        .search-box { background: white; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px; }
        .search-form { display: flex; gap: 12px; align-items: center; }
        .search-input { flex: 1; max-width: 400px; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; }
        .search-input:focus { border-color: #117a65; }
        
        /* Patient Summary Banner */
        .info-card { background: white; padding: 20px; border-radius: 8px; border-left: 5px solid #117a65; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px; }
        .info-card h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 18px; }
        .info-card p { margin: 5px 0; font-size: 14px; color: #475569; }
        
        /* History Data Table */
        .table-container { background: white; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); overflow: hidden; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: center; }
        th { background-color: #2c3e50; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Button Styles */
        .btn { padding: 11px 20px; background-color: #117a65; color: white; border: none; cursor: pointer; border-radius: 6px; font-weight: 600; font-size: 14px; }
        .btn:hover { background-color: #0e6251; }
        .btn-danger { background-color: #e74c3c; width: 100%; margin-top: 30px; padding: 10px 15px; border-radius: 5px; border: none; cursor: pointer; color: white; font-weight: bold; }
        .btn-danger:hover { background-color: #c0392b; }
        
        /* Alerts */
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px 14px; border-radius: 5px; margin-top: 15px; font-size: 14px; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>VitaGuard Lite</h2>
        <p>Role: Doctor</p>
        
        <a href="dashboard.php">🏠 Consultation Queue</a>
        <a href="patient_vitals.php" class="active">🩺 Patient Records</a>
        <a href="prescription_builder.php">✍️ Digital Prescription</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-danger">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2 class="page-title">Review Patient Medical History</h2>
        
        <!-- Patient Search Box -->
        <div class="search-box">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="search-form">
                <input type="number" name="patient_id" class="search-input" placeholder="Enter Patient ID (e.g. 101)" required value="<?php echo isset($_GET['patient_id']) ? htmlspecialchars($_GET['patient_id']) : ''; ?>">
                <input type="submit" name="search_patient" value="Search Records" class="btn">
            </form>
            <?php if (!empty($error_msg)) echo $error_msg; ?>
        </div>

        <?php if ($patient_info): ?>
            <!-- Patient Profile Overview -->
            <div class="info-card">
                <h3>Patient Profile Summary</h3>
                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($patient_info['name']); ?> (ID #<?php echo htmlspecialchars($patient_info['user_id']); ?>)</p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($patient_info['email']); ?> &nbsp;|&nbsp; <strong>Phone:</strong> <?php echo htmlspecialchars(!empty($patient_info['phone']) ? $patient_info['phone'] : 'N/A'); ?></p>
            </div>

            <!-- Health Vitals Log Table -->
            <h3 style="color: #2c3e50; margin-top: 25px;">Recorded Health Vitals</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Blood Pressure</th>
                            <th>Blood Sugar (mg/dL)</th>
                            <th>Pulse Rate (bpm)</th>
                            <th>Temperature (°F)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($vitals_result && $vitals_result->num_rows > 0): ?>
                        <?php while ($row = $vitals_result->fetch_assoc()): ?>
                            <?php 
                                $datetime = date("d M Y, h:i A", strtotime($row['recorded_at']));
                                $bp       = htmlspecialchars($row['blood_pressure']);
                                $bs       = htmlspecialchars($row['blood_sugar']);
                                $pr       = htmlspecialchars($row['pulse_rate']);
                                $temp     = htmlspecialchars($row['temperature']);
                            ?>
                            <tr>
                                <td><?php echo $datetime; ?></td>
                                <td><strong><?php echo $bp; ?></strong></td>
                                <td><?php echo $bs; ?></td>
                                <td><?php echo $pr; ?></td>
                                <td><?php echo $temp; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 20px; color: #64748b;">No health vitals logged by this patient yet.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>