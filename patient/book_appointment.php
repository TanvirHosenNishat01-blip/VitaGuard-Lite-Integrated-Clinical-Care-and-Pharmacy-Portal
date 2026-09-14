<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('patient'); 
require_once '../config/db.php';

$patient_id = $_SESSION['user_id'] ?? 0;
$msg = "";
$error = "";

// Handle appointment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appt'])) {
    $doctor_id        = intval($_POST['doctor_id'] ?? 0);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $time_slot        = trim($_POST['time_slot'] ?? '');
    $reason           = trim($_POST['reason'] ?? '');

    if ($doctor_id > 0 && !empty($appointment_date) && !empty($time_slot)) {
        $insert_sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, time_slot, status, reason) VALUES (?, ?, ?, ?, 'Pending', ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param('iisss', $patient_id, $doctor_id, $appointment_date, $time_slot, $reason);
        
        if ($stmt->execute()) {
            $msg = "Appointment requested successfully! Waiting for doctor's approval.";
        } else {
            $error = "Failed to book appointment. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Fetch doctor list for dropdown selection
$doctors_result = $conn->query("SELECT user_id, name FROM users WHERE role = 'doctor' ORDER BY name ASC");

// Fetch existing appointments booked by this patient
$appt_sql = "SELECT a.*, u.name AS doctor_name 
             FROM appointments a 
             JOIN users u ON a.doctor_id = u.user_id 
             WHERE a.patient_id = ? 
             ORDER BY a.appointment_date DESC, a.time_slot DESC";
$appt_stmt = $conn->prepare($appt_sql);
$appt_stmt->bind_param('i', $patient_id);
$appt_stmt->execute();
$appointments = $appt_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | VitaGuard Lite</title>
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
        
        /* Card Container */
        .card { background: white; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .card h3 { margin-top: 0; color: #117a65; font-size: 18px; }
        
        /* Form Inputs */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 13px; }
        .form-group select, 
        .form-group input, 
        .form-group textarea { 
            width: 100%; 
            padding: 10px 14px; 
            border: 1px solid #cbd5e1; 
            border-radius: 6px; 
            font-family: inherit; 
            font-size: 14px; 
            outline: none; 
        }
        .form-group select:focus, 
        .form-group input:focus, 
        .form-group textarea:focus { border-color: #117a65; }
        
        /* Table Layout */
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 15px; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: left; }
        th { background-color: #117a65; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Badges */
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }

        /* Buttons */
        .btn { display: inline-block; padding: 10px 18px; background-color: #117a65; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn:hover { background-color: #0e6251; }
        .btn-danger { background-color: #e74c3c; width: 100%; margin-top: 30px; padding: 10px 15px; border-radius: 5px; border: none; cursor: pointer; color: white; font-weight: bold; }
        .btn-danger:hover { background-color: #c0392b; }
        
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
        <a href="book_appointment.php" class="active">📅 Book Appointment</a>
        <a href="my_prescriptions.php">📜 My Prescriptions</a>
        <a href="medicine_schedule.php">💊 Medication Tracker</a>
        <a href="log_vitals.php">❤️ Health Vitals</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-danger">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1 class="page-title">Appointment Management</h1>
        <p style="color: #64748b; margin-top: -5px;">Book a consultation with a doctor and track your request statuses.</p>
        
        <!-- Action Alerts -->
        <?php if (!empty($msg)) echo "<div class='alert-success'>$msg</div>"; ?>
        <?php if (!empty($error)) echo "<div class='alert-error'>$error</div>"; ?>

        <!-- Appointment Booking Form Card -->
        <div class="card">
            <h3>Book New Appointment</h3>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="form-group">
                    <label>Select Doctor:</label>
                    <select name="doctor_id" required>
                        <option value="">Choose a doctor...</option>
                        <?php 
                        if ($doctors_result && $doctors_result->num_rows > 0) {
                            while ($doc = $doctors_result->fetch_assoc()) {
                                $raw_name = trim($doc['name']);
                                $doc_display = str_starts_with($raw_name, 'Dr.') ? $raw_name : 'Dr. ' . $raw_name;
                                echo "<option value='{$doc['user_id']}'>" . htmlspecialchars($doc_display) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Appointment Date:</label>
                    <input type="date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label>Time Slot:</label>
                    <select name="time_slot" required>
                        <option value="">Select Time Slot...</option>
                        <option value="09:00 AM">09:00 AM</option>
                        <option value="10:30 AM">10:30 AM</option>
                        <option value="11:30 AM">11:30 AM</option>
                        <option value="02:00 PM">02:00 PM</option>
                        <option value="04:00 PM">04:00 PM</option>
                        <option value="06:00 PM">06:00 PM</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Reason for Visit:</label>
                    <textarea name="reason" rows="3" placeholder="Briefly describe your symptoms or reason for appointment..."></textarea>
                </div>

                <button type="submit" name="book_appt" class="btn">Request Appointment</button>
            </form>
        </div>

        <!-- Appointment Statuses Table Card -->
        <div class="card">
            <h3>My Appointment Requests</h3>
            <table>
                <thead>
                    <tr>
                        <th>Doctor Name</th>
                        <th>Date</th>
                        <th>Time Slot</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($appointments && $appointments->num_rows > 0): ?>
                    <?php while ($row = $appointments->fetch_assoc()): ?>
                        <?php 
                            $raw_dname = trim($row['doctor_name']);
                            $doc_name  = str_starts_with($raw_dname, 'Dr.') ? $raw_dname : 'Dr. ' . $raw_dname;
                            $date      = date("d M Y", strtotime($row['appointment_date']));
                            $time      = htmlspecialchars($row['time_slot']);
                            $reason    = htmlspecialchars(!empty($row['reason']) ? $row['reason'] : 'General Consultation');
                            $status    = htmlspecialchars($row['status']);

                            $badge_class = 'badge-pending';
                            if ($status === 'Approved') $badge_class = 'badge-approved';
                            if ($status === 'Cancelled') $badge_class = 'badge-cancelled';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($doc_name); ?></strong></td>
                            <td><?php echo $date; ?></td>
                            <td><?php echo $time; ?></td>
                            <td><?php echo $reason; ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color: #888; padding: 25px;">No appointment requests found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>