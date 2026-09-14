<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('patient'); 
require_once '../config/db.php';

$message = "";
$error_msg = "";
$patient_id = $_SESSION['user_id'] ?? 0; 

// 1. Create: Log new health vitals entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_vitals'])) {
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $blood_sugar    = floatval($_POST['blood_sugar'] ?? 0);
    $pulse_rate     = intval($_POST['pulse_rate'] ?? 0);
    $temperature    = floatval($_POST['temperature'] ?? 0);
    
    if (!empty($blood_pressure) && $blood_sugar > 0 && $pulse_rate > 0 && $temperature > 0) {
        $sql = "INSERT INTO health_records (patient_id, blood_pressure, blood_sugar, pulse_rate, temperature) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isdid', $patient_id, $blood_pressure, $blood_sugar, $pulse_rate, $temperature);
        
        if ($stmt->execute()) {
            $message = "Health vitals recorded successfully!";
        } else {
            $error_msg = "Failed to log vitals: " . htmlspecialchars($conn->error);
        }
        $stmt->close();
    } else {
        $error_msg = "Please provide valid inputs for all vitals fields.";
    }
}

// 2. Delete: Remove a specific health record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record'])) {
    $record_id = intval($_POST['record_id'] ?? 0);
    
    if ($record_id > 0) {
        $sql = "DELETE FROM health_records WHERE record_id = ? AND patient_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $record_id, $patient_id);
        
        if ($stmt->execute()) {
            $message = "Record deleted successfully!";
        } else {
            $error_msg = "Failed to delete record.";
        }
        $stmt->close();
    }
}

// 3. Read: Fetch patient's recorded vitals history
$sql = "SELECT * FROM health_records WHERE patient_id = ? ORDER BY recorded_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$vitals_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Vitals | VitaGuard Lite</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f6; display: flex; }
        
        /* Sidebar Styling */
        .sidebar { width: 250px; background-color: #117a65; color: white; min-height: 100vh; padding: 20px; flex-shrink: 0; }
        .sidebar h2 { margin-top: 0; font-size: 22px; }
        .sidebar p { color: #d1f2eb; font-size: 14px; margin-bottom: 20px; }
        .sidebar a { color: #d1f2eb; text-decoration: none; display: block; padding: 12px 0; font-size: 15px; border-bottom: 1px solid #0e6251; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { color: #ffffff; padding-left: 6px; }
        
        /* Main Layout */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; }
        .page-title { margin-top: 0; color: #117a65; }
        
        /* Form Card */
        .card { background: white; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .card h3 { margin-top: 0; color: #117a65; font-size: 18px; margin-bottom: 18px; }
        
        /* Form Grid Layout */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 13px; }
        .form-group input { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #117a65; }
        
        /* Table Layout */
        .table-container { background: white; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); overflow: hidden; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: center; }
        th { background-color: #117a65; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Buttons */
        .btn { display: inline-block; padding: 10px 18px; background-color: #117a65; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn:hover { background-color: #0e6251; }
        .btn-danger-inline { background-color: #e74c3c; color: white; padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; }
        .btn-danger-inline:hover { background-color: #c0392b; }
        .btn-logout { background-color: #e74c3c; width: 100%; margin-top: 30px; padding: 10px 15px; border-radius: 5px; border: none; cursor: pointer; color: white; font-weight: bold; }
        .btn-logout:hover { background-color: #c0392b; }
        
        /* Alerts */
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>VitaGuard Lite</h2>
        <p>Role: Patient</p>
        
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="book_appointment.php">📅 Book Appointment</a>
        <a href="my_prescriptions.php">📜 My Prescriptions</a>
        <a href="medicine_schedule.php">💊 Medication Tracker</a>
        <a href="log_vitals.php" class="active">❤️ Health Vitals</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1 class="page-title">Health Vitals Log</h1>
        <p style="color: #64748b; margin-top: -5px;">Track and record your daily physiological indicators.</p>
        
        <!-- Action Alerts -->
        <?php if (!empty($message)) echo "<div class='alert-success'>$message</div>"; ?>
        <?php if (!empty($error_msg)) echo "<div class='alert-error'>$error_msg</div>"; ?>

        <!-- Vitals Input Form Card -->
        <div class="card">
            <h3>Record New Measurements</h3>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Blood Pressure</label>
                        <input type="text" name="blood_pressure" placeholder="e.g. 120/80" required>
                    </div>
                    <div class="form-group">
                        <label>Blood Sugar (mg/dL)</label>
                        <input type="number" step="0.1" name="blood_sugar" placeholder="e.g. 95.5" required>
                    </div>
                    <div class="form-group">
                        <label>Pulse Rate (bpm)</label>
                        <input type="number" step="1" name="pulse_rate" placeholder="e.g. 72" required>
                    </div>
                    <div class="form-group">
                        <label>Body Temperature (°F)</label>
                        <input type="number" step="0.1" name="temperature" placeholder="e.g. 98.6" required>
                    </div>
                </div>
                
                <button type="submit" name="log_vitals" class="btn">Save Record</button>
            </form>
        </div>

        <!-- History Table Card -->
        <div class="card">
            <h3>Vitals History</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Blood Pressure</th>
                            <th>Blood Sugar (mg/dL)</th>
                            <th>Pulse Rate (bpm)</th>
                            <th>Temperature (°F)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($vitals_result && $vitals_result->num_rows > 0): ?>
                        <?php while ($row = $vitals_result->fetch_assoc()): ?>
                            <?php 
                                $id       = $row['record_id'];
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
                                <td>
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" onsubmit="return confirm('Delete this record entry?');">
                                        <input type="hidden" name="record_id" value="<?php echo $id; ?>">
                                        <button type="submit" name="delete_record" class="btn-danger-inline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding: 25px; color: #64748b;">No health vitals logged yet.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>