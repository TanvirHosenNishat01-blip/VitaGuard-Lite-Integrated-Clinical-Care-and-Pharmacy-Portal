<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('patient'); 
require_once '../config/db.php';

$patient_id = $_SESSION['user_id'] ?? 0;

// Fetch patient's prescriptions along with the issuing doctor's name
$sql = "SELECT p.*, u.name AS doctor_name 
        FROM prescriptions p 
        JOIN users u ON p.doctor_id = u.user_id 
        WHERE p.patient_id = ? 
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$prescriptions = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions | VitaGuard Lite</title>
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
        
        /* Prescription Card Layout */
        .prescription-card { background: white; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 15px; }
        .card-header h3 { margin: 0; color: #117a65; font-size: 18px; }
        .card-meta { font-size: 13px; color: #64748b; }
        
        /* Status Badges */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-dispensed { background: #e0f2fe; color: #0369a1; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        /* Table Layout */
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 15px; }
        table, th, td { border: 1px solid #eee; padding: 10px 14px; text-align: left; }
        th { background-color: #f8fafc; color: #334155; font-size: 13px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Buttons */
        .btn-logout { background-color: #e74c3c; width: 100%; margin-top: 30px; padding: 10px 15px; border-radius: 5px; border: none; cursor: pointer; color: white; font-weight: bold; }
        .btn-logout:hover { background-color: #c0392b; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>VitaGuard Lite</h2>
        <p>Role: Patient</p>
        
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="book_appointment.php">📅 Book Appointment</a>
        <a href="my_prescriptions.php" class="active">📜 My Prescriptions</a>
        <a href="medicine_schedule.php">💊 Medication Tracker</a>
        <a href="log_vitals.php">❤️ Health Vitals</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1 class="page-title">My Prescriptions</h1>
        <p style="color: #64748b; margin-top: -5px;">View all digital prescriptions and medication regimens issued by your physicians.</p>
        
        <?php if ($prescriptions && $prescriptions->num_rows > 0): ?>
            <?php while ($p = $prescriptions->fetch_assoc()): ?>
                <?php 
                    $p_id         = $p['prescription_id'];
                    $raw_doc      = trim($p['doctor_name']);
                    $doctor_name  = str_starts_with($raw_doc, 'Dr.') ? $raw_doc : 'Dr. ' . $raw_doc;
                    $instructions = htmlspecialchars(!empty($p['instructions']) ? $p['instructions'] : 'None provided.');
                    $date         = date("d M Y, h:i A", strtotime($p['created_at']));
                    $status       = htmlspecialchars($p['status']);

                    $badge_class = 'badge-active';
                    if ($status === 'Dispensed') $badge_class = 'badge-dispensed';
                    if ($status === 'Cancelled') $badge_class = 'badge-cancelled';

                    // Fetch medication items prescribed under this prescription
                    $item_stmt = $conn->prepare("SELECT pi.*, m.trade_name, m.generic_name 
                                                 FROM prescription_items pi 
                                                 JOIN medicines m ON pi.medicine_id = m.medicine_id 
                                                 WHERE pi.prescription_id = ?");
                    $item_stmt->bind_param('i', $p_id);
                    $item_stmt->execute();
                    $items = $item_stmt->get_result();
                ?>
                
                <div class="prescription-card">
                    <div class="card-header">
                        <h3>Prescription #<?php echo $p_id; ?> &bull; <?php echo htmlspecialchars($doctor_name); ?></h3>
                        <div class="card-meta">
                            <span>Date: <?php echo $date; ?></span> &nbsp;|&nbsp;
                            <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                        </div>
                    </div>
                    
                    <p style="margin: 8px 0; font-size: 14px; color: #475569;">
                        <strong>Clinical Advice:</strong> <?php echo $instructions; ?>
                    </p>
                    
                    <?php if ($items && $items->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Medicine Name</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $items->fetch_assoc()): ?>
                                    <?php 
                                        $t_name   = htmlspecialchars($item['trade_name']);
                                        $g_name   = htmlspecialchars($item['generic_name']);
                                        $dosage   = htmlspecialchars($item['dosage']);
                                        $freq     = htmlspecialchars($item['frequency']);
                                        $duration = intval($item['duration_days']) . " Days";
                                    ?>
                                    <tr>
                                        <td><strong><?php echo $t_name; ?></strong> <span style="color: #64748b; font-size: 12px;">(<?php echo $g_name; ?>)</span></td>
                                        <td><?php echo $dosage; ?></td>
                                        <td><?php echo $freq; ?></td>
                                        <td><?php echo $duration; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #94a3b8; font-size: 13px; margin-top: 10px;">No medicine items attached to this prescription record.</p>
                    <?php endif; ?>
                    <?php $item_stmt->close(); ?>
                </div>

            <?php endwhile; ?>
            <?php $stmt->close(); ?>
        <?php else: ?>
            <div class="prescription-card">
                <p style="text-align: center; color: #64748b; margin: 20px 0;">No prescription records found from any physician yet.</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>