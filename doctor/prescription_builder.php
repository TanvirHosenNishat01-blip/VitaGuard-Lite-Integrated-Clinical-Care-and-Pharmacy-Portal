<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('doctor'); 
require_once '../config/db.php';

$doctor_id = $_SESSION['user_id'] ?? 0;
$message = "";
$error_msg = "";

// Handle prescription creation with database transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_prescription'])) {
    $patient_id   = intval($_POST['patient_id'] ?? 0);
    $instructions = trim($_POST['instructions'] ?? '');
    
    if ($patient_id > 0 && !empty($_POST['medicine_id'])) {
        // Begin transaction to ensure both prescription master and items persist together
        $conn->begin_transaction();
        
        try {
            // 1. Insert master prescription record
            $sql_pres = "INSERT INTO prescriptions (doctor_id, patient_id, instructions, status) VALUES (?, ?, ?, 'Active')";
            $stmt_pres = $conn->prepare($sql_pres);
            $stmt_pres->bind_param('iis', $doctor_id, $patient_id, $instructions);
            $stmt_pres->execute();
            
            $prescription_id = $conn->insert_id;
            $stmt_pres->close();
            
            // 2. Insert prescribed medicine items
            $sql_item = "INSERT INTO prescription_items (prescription_id, medicine_id, dosage, frequency, duration_days) VALUES (?, ?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);
            
            $medicines   = $_POST['medicine_id'] ?? [];
            $dosages     = $_POST['dosage'] ?? [];
            $frequencies = $_POST['frequency'] ?? [];
            $durations   = $_POST['duration'] ?? [];
            
            for ($i = 0; $i < count($medicines); $i++) {
                $med_id   = intval($medicines[$i] ?? 0);
                $dosage   = trim($dosages[$i] ?? '');
                $freq     = trim($frequencies[$i] ?? '');
                $duration = intval($durations[$i] ?? 0);
                
                if ($med_id > 0 && !empty($dosage) && !empty($freq) && $duration > 0) {
                    $stmt_item->bind_param('iissi', $prescription_id, $med_id, $dosage, $freq, $duration);
                    $stmt_item->execute();
                }
            }
            $stmt_item->close();
            
            // Commit transaction
            $conn->commit();
            $message = "<div class='alert-success'>Digital prescription #{$prescription_id} issued successfully!</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "<div class='alert-error'>Failed to issue prescription: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $error_msg = "<div class='alert-error'>Please select a patient and add at least one valid medication.</div>";
    }
}

// Fetch active registered patients for dropdown selection
$sql_patients = "SELECT user_id, name FROM users WHERE role = 'patient' ORDER BY name ASC";
$patients_result = $conn->query($sql_patients);

// Fetch catalog medicines for dropdown selection
$sql_meds = "SELECT medicine_id, trade_name, generic_name FROM medicines ORDER BY trade_name ASC";
$meds_result = $conn->query($sql_meds);

// Build medicine dropdown options string for JavaScript dynamic rows
$med_options = "<option value=''>Select Medicine...</option>";
if ($meds_result && $meds_result->num_rows > 0) {
    while ($m = $meds_result->fetch_assoc()) {
        $med_options .= "<option value='" . $m['medicine_id'] . "'>" . htmlspecialchars($m['trade_name'] . " (" . $m['generic_name'] . ")") . "</option>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Prescription | VitaGuard Lite</title>
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
        .page-title { margin-top: 0; color: #2c3e50; }
        
        /* Form Card */
        .form-container { background: white; padding: 30px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px; max-width: 900px; }
        .section-heading { color: #334155; font-size: 16px; margin: 20px 0 10px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; }
        .form-container select, .form-container textarea { padding: 10px 14px; margin: 6px 0 14px 0; width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; outline: none; }
        .form-container select:focus, .form-container textarea:focus { border-color: #117a65; }
        
        /* Dynamic Medicine Rows */
        .medicine-row { display: flex; gap: 10px; margin-bottom: 12px; align-items: center; }
        .medicine-row select { flex: 3; margin: 0; }
        .medicine-row input { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; }
        .medicine-row input:focus { border-color: #117a65; }
        .medicine-row input.dosage-field { flex: 2; }
        .medicine-row input.freq-field { flex: 1.5; }
        .medicine-row input.duration-field { flex: 1; }
        
        /* Buttons */
        .btn { padding: 10px 16px; background-color: #117a65; color: white; border: none; cursor: pointer; border-radius: 6px; font-weight: 600; font-size: 14px; }
        .btn:hover { background-color: #0e6251; }
        .btn-secondary { background-color: #475569; margin: 10px 0 20px 0; font-size: 13px; }
        .btn-secondary:hover { background-color: #334155; }
        .btn-remove { background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; padding: 10px 14px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-remove:hover { background-color: #fecaca; }
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
        <p>Role: Doctor</p>
        
        <a href="dashboard.php">🏠 Consultation Queue</a>
        <a href="patient_vitals.php">🩺 Patient Records</a>
        <a href="prescription_builder.php" class="active">✍️ Digital Prescription</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-danger">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2 class="page-title">Issue Digital Prescription</h2>
        
        <?php 
        if (!empty($message)) echo $message; 
        if (!empty($error_msg)) echo $error_msg; 
        ?>

        <div class="form-container">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                
                <!-- 1. Patient Selection -->
                <h3 class="section-heading">1. Select Target Patient</h3>
                <select name="patient_id" required>
                    <option value="">Choose Patient...</option>
                    <?php if ($patients_result && $patients_result->num_rows > 0): ?>
                        <?php while ($p = $patients_result->fetch_assoc()): ?>
                            <option value="<?php echo $p['user_id']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?> (ID #<?php echo $p['user_id']; ?>)
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>

                <!-- 2. Prescription Items Section -->
                <h3 class="section-heading">2. Prescribe Medications</h3>
                <div id="medicine-list">
                    <!-- Initial Item Row -->
                    <div class="medicine-row">
                        <select name="medicine_id[]" required>
                            <?php echo $med_options; ?>
                        </select>
                        <input type="text" name="dosage[]" placeholder="Dosage (e.g. 500mg)" class="dosage-field" required>
                        <input type="text" name="frequency[]" placeholder="Frequency (e.g. 1+0+1)" class="freq-field" required>
                        <input type="number" name="duration[]" placeholder="Days" class="duration-field" min="1" required>
                    </div>
                </div>
                
                <!-- Dynamic Field Trigger -->
                <button type="button" class="btn btn-secondary" onclick="addMedicineRow()">+ Add Another Medication</button>

                <!-- 3. General Instructions -->
                <h3 class="section-heading">3. Clinical Advice & Instructions</h3>
                <textarea name="instructions" rows="4" placeholder="e.g. Take medications after meals. Maintain adequate hydration and report if symptoms persist."></textarea>
                
                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;">
                <input type="submit" name="issue_prescription" value="Submit & Issue Prescription" class="btn" style="width: 100%; font-size: 16px; padding: 13px;">
            </form>
        </div>
    </div>

    <!-- Dynamic Medicine Row JavaScript -->
    <script>
        function addMedicineRow() {
            const container = document.getElementById('medicine-list');
            const rowHTML = `
                <div class="medicine-row">
                    <select name="medicine_id[]" required>
                        <?php echo $med_options; ?>
                    </select>
                    <input type="text" name="dosage[]" placeholder="Dosage (e.g. 500mg)" class="dosage-field" required>
                    <input type="text" name="frequency[]" placeholder="Frequency (e.g. 1+0+1)" class="freq-field" required>
                    <input type="number" name="duration[]" placeholder="Days" class="duration-field" min="1" required>
                    <button type="button" class="btn-remove" onclick="this.parentElement.remove()" title="Remove line">✕</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rowHTML);
        }
    </script>

</body>
</html>