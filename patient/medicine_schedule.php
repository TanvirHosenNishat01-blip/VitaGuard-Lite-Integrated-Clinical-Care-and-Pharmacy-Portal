<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('patient'); 
require_once '../config/db.php';

$message = "";
$error_msg = "";
$patient_id = $_SESSION['user_id'] ?? 0; 

// 1. Update intake status (Taken / Skipped)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $tracker_id = intval($_POST['tracker_id'] ?? 0);
    $status     = trim($_POST['status'] ?? '');
    
    if ($tracker_id > 0 && in_array($status, ['Taken', 'Skipped'])) {
        $sql = "UPDATE medication_tracker SET status = ? WHERE tracker_id = ? AND patient_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sii', $status, $tracker_id, $patient_id);
        
        if ($stmt->execute()) {
            $message = "Medication status updated to {$status}!";
        } else {
            $error_msg = "Failed to update intake status.";
        }
        $stmt->close();
    }
}

// 2. Delete schedule record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record'])) {
    $tracker_id = intval($_POST['tracker_id'] ?? 0);
    
    if ($tracker_id > 0) {
        $sql = "DELETE FROM medication_tracker WHERE tracker_id = ? AND patient_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $tracker_id, $patient_id);
        
        if ($stmt->execute()) {
            $message = "Schedule entry removed successfully!";
        } else {
            $error_msg = "Failed to delete schedule entry.";
        }
        $stmt->close();
    }
}

// 3. Add new medication schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $medicine_id = intval($_POST['medicine_id'] ?? 0);
    $intake_time = trim($_POST['intake_time'] ?? '');
    $log_date    = trim($_POST['log_date'] ?? '');
    $status      = 'Skipped'; // Default tracking status
    
    if ($medicine_id > 0 && !empty($intake_time) && !empty($log_date)) {
        $sql = "INSERT INTO medication_tracker (patient_id, medicine_id, intake_time, status, log_date) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iisss', $patient_id, $medicine_id, $intake_time, $status, $log_date);
        
        if ($stmt->execute()) {
            $message = "New medication schedule added successfully!";
        } else {
            $error_msg = "Failed to create medication schedule.";
        }
        $stmt->close();
    } else {
        $error_msg = "Please fill in all schedule details.";
    }
}

// Fetch available medicines for the dropdown list
$med_sql = "SELECT medicine_id, trade_name FROM medicines ORDER BY trade_name ASC";
$med_result = $conn->query($med_sql);

// Fetch patient medication schedule joined with medicine names
$schedule_sql = "SELECT mt.*, m.trade_name 
                 FROM medication_tracker mt 
                 JOIN medicines m ON mt.medicine_id = m.medicine_id 
                 WHERE mt.patient_id = ? 
                 ORDER BY mt.log_date DESC, mt.intake_time ASC";
$stmt = $conn->prepare($schedule_sql);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$schedule_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medication Tracker | VitaGuard Lite</title>
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
        
        /* Form Flex Grid */
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .form-group { flex: 1; min-width: 180px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 13px; }
        .form-group select, 
        .form-group input { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; outline: none; }
        .form-group select:focus, 
        .form-group input:focus { border-color: #117a65; }
        
        /* Table Layout */
        .table-container { background: white; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); overflow: hidden; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: left; }
        th { background-color: #117a65; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Status Badges */
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-taken { background: #d4edda; color: #155724; }
        .status-skipped { background: #f8d7da; color: #721c24; }
        
        /* Buttons */
        .btn { display: inline-block; padding: 10px 18px; background-color: #117a65; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn:hover { background-color: #0e6251; }
        .btn-action { padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 12px; font-weight: 600; color: white; }
        .btn-success { background-color: #27ae60; }
        .btn-success:hover { background-color: #219653; }
        .btn-warning { background-color: #f39c12; }
        .btn-warning:hover { background-color: #d68910; }
        .btn-danger-inline { background-color: #e74c3c; }
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
        <a href="medicine_schedule.php" class="active">💊 Medication Tracker</a>
        <a href="log_vitals.php">❤️ Health Vitals</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1 class="page-title">My Medication Tracker</h1>
        <p style="color: #64748b; margin-top: -5px;">Track daily doses, mark intake progress, and set medication reminders.</p>
        
        <!-- Action Alerts -->
        <?php if (!empty($message)) echo "<div class='alert-success'>$message</div>"; ?>
        <?php if (!empty($error_msg)) echo "<div class='alert-error'>$error_msg</div>"; ?>

        <!-- Add Schedule Form Card -->
        <div class="card">
            <h3>Add Medication to Schedule</h3>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Medicine Name</label>
                        <select name="medicine_id" required>
                            <option value="">Select Medicine...</option>
                            <?php 
                            if ($med_result && $med_result->num_rows > 0) {
                                while ($m = $med_result->fetch_assoc()) {
                                    echo "<option value='" . $m['medicine_id'] . "'>" . htmlspecialchars($m['trade_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Intake Time</label>
                        <input type="time" name="intake_time" required>
                    </div>
                    <div class="form-group">
                        <label>Schedule Date</label>
                        <input type="date" name="log_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <button type="submit" name="add_schedule" class="btn">Add Schedule</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Schedule Table Card -->
        <div class="card">
            <h3>Daily Dose Progress</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Medicine Name</th>
                            <th>Current Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($schedule_result && $schedule_result->num_rows > 0): ?>
                        <?php while ($row = $schedule_result->fetch_assoc()): ?>
                            <?php 
                                $id       = $row['tracker_id'];
                                $date     = date("d M Y", strtotime($row['log_date']));
                                $time     = date("h:i A", strtotime($row['intake_time']));
                                $med_name = htmlspecialchars($row['trade_name']);
                                $status   = htmlspecialchars($row['status']);
                                $badge_cls = ($status === 'Taken') ? 'status-taken' : 'status-skipped';
                            ?>
                            <tr>
                                <td><?php echo $date; ?></td>
                                <td><?php echo $time; ?></td>
                                <td><strong><?php echo $med_name; ?></strong></td>
                                <td><span class="status-badge <?php echo $badge_cls; ?>"><?php echo $status; ?></span></td>
                                <td>
                                    <!-- Mark Taken Action -->
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" style="display:inline;">
                                        <input type="hidden" name="tracker_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="status" value="Taken">
                                        <button type="submit" name="update_status" class="btn-action btn-success">Taken</button>
                                    </form>
                                    
                                    <!-- Mark Skipped Action -->
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" style="display:inline;">
                                        <input type="hidden" name="tracker_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="status" value="Skipped">
                                        <button type="submit" name="update_status" class="btn-action btn-warning">Skipped</button>
                                    </form>
                                    
                                    <!-- Delete Schedule Entry -->
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" style="display:inline;" onsubmit="return confirm('Remove this medication entry?');">
                                        <input type="hidden" name="tracker_id" value="<?php echo $id; ?>">
                                        <button type="submit" name="delete_record" class="btn-action btn-danger-inline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 25px; color: #64748b;">No medication schedules added yet.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>