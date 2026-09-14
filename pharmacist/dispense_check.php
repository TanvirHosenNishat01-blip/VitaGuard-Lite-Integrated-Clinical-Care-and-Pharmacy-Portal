<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('pharmacist'); 
require_once '../config/db.php';

$message = "";
$error_msg = "";
$prescription_data = null;
$prescription_items = [];

// 1. Dispense Action: Update prescription status and deduct inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispense_prescription'])) {
    $pres_id = intval($_POST['prescription_id'] ?? 0);
    
    if ($pres_id > 0) {
        $conn->begin_transaction();
        
        try {
            // Check current status
            $check_sql = "SELECT status FROM prescriptions WHERE prescription_id = ? FOR UPDATE";
            $chk_stmt = $conn->prepare($check_sql);
            $chk_stmt->bind_param('i', $pres_id);
            $chk_stmt->execute();
            $pres_status = $chk_stmt->get_result()->fetch_assoc()['status'] ?? '';
            $chk_stmt->close();
            
            if ($pres_status === 'Dispensed') {
                throw new Exception("This prescription has already been dispensed.");
            }
            
            // Fetch prescription items and deduct stock
            $item_query = "SELECT medicine_id, duration_days FROM prescription_items WHERE prescription_id = ?";
            $it_stmt = $conn->prepare($item_query);
            $it_stmt->bind_param('i', $pres_id);
            $it_stmt->execute();
            $items_res = $it_stmt->get_result();
            
            $deduct_stmt = $conn->prepare("UPDATE medicines SET stock_quantity = GREATEST(0, stock_quantity - 1) WHERE medicine_id = ?");
            while ($item = $items_res->fetch_assoc()) {
                $med_id = intval($item['medicine_id']);
                $deduct_stmt->bind_param('i', $med_id);
                $deduct_stmt->execute();
            }
            $deduct_stmt->close();
            $it_stmt->close();
            
            // Mark prescription as Dispensed
            $update_sql = "UPDATE prescriptions SET status = 'Dispensed' WHERE prescription_id = ?";
            $up_stmt = $conn->prepare($update_sql);
            $up_stmt->bind_param('i', $pres_id);
            $up_stmt->execute();
            $up_stmt->close();
            
            $conn->commit();
            $message = "Prescription #{$pres_id} has been verified and successfully dispensed!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = $e->getMessage();
        }
    }
}

// 2. Search Prescription by ID
if (isset($_GET['search_id']) && !empty($_GET['search_id'])) {
    $search_id = intval($_GET['search_id']);
    
    if ($search_id > 0) {
        // Fetch prescription joined with doctor and patient information
        $sql_pres = "SELECT p.*, doc.name AS doctor_name, pat.name AS patient_name, pat.phone AS patient_phone 
                     FROM prescriptions p 
                     JOIN users doc ON p.doctor_id = doc.user_id 
                     JOIN users pat ON p.patient_id = pat.user_id 
                     WHERE p.prescription_id = ?";
        $stmt_pres = $conn->prepare($sql_pres);
        $stmt_pres->bind_param('i', $search_id);
        $stmt_pres->execute();
        $res_pres = $stmt_pres->get_result();
        
        if ($res_pres && $res_pres->num_rows > 0) {
            $prescription_data = $res_pres->fetch_assoc();
            
            // Fetch prescribed medicines and current inventory stock
            $sql_items = "SELECT pi.*, m.trade_name, m.generic_name, m.stock_quantity 
                          FROM prescription_items pi 
                          JOIN medicines m ON pi.medicine_id = m.medicine_id 
                          WHERE pi.prescription_id = ?";
            $stmt_items = $conn->prepare($sql_items);
            $stmt_items->bind_param('i', $search_id);
            $stmt_items->execute();
            $res_items = $stmt_items->get_result();
            
            while ($row = $res_items->fetch_assoc()) {
                $prescription_items[] = $row;
            }
            $stmt_items->close();
        } else {
            $error_msg = "No prescription found with ID #{$search_id}";
        }
        $stmt_pres->close();
    } else {
        $error_msg = "Please enter a valid prescription ID.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Verification | VitaGuard Lite</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f6; display: flex; }
        
        /* Sidebar Navigation */
        .sidebar { width: 250px; background-color: #1a5276; color: white; min-height: 100vh; padding: 20px; flex-shrink: 0; }
        .sidebar h2 { margin-top: 0; font-size: 22px; }
        .sidebar p { color: #d4e6f1; font-size: 14px; margin-bottom: 20px; }
        .sidebar a { color: #d4e6f1; text-decoration: none; display: block; padding: 12px 0; font-size: 15px; border-bottom: 1px solid #2980b9; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { color: #ffffff; padding-left: 6px; }
        
        /* Main Layout */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; }
        .page-title { margin-top: 0; color: #1a5276; }
        
        /* Search Box */
        .search-container { background: white; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px; }
        .search-form { display: flex; gap: 12px; align-items: center; }
        .search-input { flex: 1; max-width: 400px; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; }
        .search-input:focus { border-color: #117a65; }
        
        /* Details Container */
        .details-container { background: white; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-top: 20px; }
        .details-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 15px; }
        .details-header h3 { margin: 0; color: #1a5276; font-size: 18px; }
        
        /* Patient & Doctor Metadata Grid */
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .meta-item { font-size: 14px; color: #334155; }
        .meta-item strong { color: #0f172a; }
        
        /* Table Layout */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: left; }
        th { background-color: #117a65; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Badges */
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: white; }
        .status-active { background-color: #f39c12; }
        .status-dispensed { background-color: #27ae60; }
        .badge-out { color: #dc2626; font-weight: 600; }
        .badge-available { color: #16a34a; font-weight: 600; }
        
        /* Buttons */
        .btn { padding: 11px 20px; background-color: #117a65; color: white; border: none; cursor: pointer; border-radius: 6px; font-size: 14px; font-weight: 600; }
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
        <p>Role: Pharmacist</p>
        
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="inventory.php">📦 Medicine Inventory</a>
        <a href="dispense_check.php" class="active">✔️ Prescription Verify</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-danger">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1 class="page-title">Prescription Verification & Fulfillment</h1>
        <p style="color: #64748b; margin-top: -5px;">Search prescriptions by ID, verify medicine availability, and complete fulfillment.</p>
        
        <!-- Action Alerts -->
        <?php if (!empty($message)) echo "<div class='alert-success'>" . htmlspecialchars($message) . "</div>"; ?>
        <?php if (!empty($error_msg)) echo "<div class='alert-error'>" . htmlspecialchars($error_msg) . "</div>"; ?>

        <!-- Search Box -->
        <div class="search-container">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="search-form">
                <input type="number" name="search_id" class="search-input" placeholder="Enter Prescription ID (e.g. 5001)" required value="<?php echo isset($_GET['search_id']) ? htmlspecialchars($_GET['search_id']) : ''; ?>">
                <input type="submit" value="Search Prescription" class="btn">
            </form>
        </div>

        <!-- Prescription Details & Items -->
        <?php if ($prescription_data): ?>
            <?php 
                $is_dispensed = ($prescription_data['status'] === 'Dispensed');
                $raw_doc = trim($prescription_data['doctor_name']);
                $doc_display = str_starts_with($raw_doc, 'Dr.') ? $raw_doc : 'Dr. ' . $raw_doc;
            ?>
            <div class="details-container">
                <div class="details-header">
                    <h3>Prescription #<?php echo htmlspecialchars($prescription_data['prescription_id']); ?></h3>
                    <span class="status-badge <?php echo $is_dispensed ? 'status-dispensed' : 'status-active'; ?>">
                        <?php echo htmlspecialchars($prescription_data['status']); ?>
                    </span>
                </div>
                
                <!-- Clinical and Patient Context -->
                <div class="meta-grid">
                    <div class="meta-item"><strong>Patient:</strong> <?php echo htmlspecialchars($prescription_data['patient_name']); ?></div>
                    <div class="meta-item"><strong>Contact:</strong> <?php echo htmlspecialchars(!empty($prescription_data['patient_phone']) ? $prescription_data['patient_phone'] : 'N/A'); ?></div>
                    <div class="meta-item"><strong>Doctor:</strong> <?php echo htmlspecialchars($doc_display); ?></div>
                    <div class="meta-item"><strong>Date:</strong> <?php echo date("d M Y, h:i A", strtotime($prescription_data['created_at'])); ?></div>
                </div>
                
                <p style="font-size: 14px; color: #475569; margin: 10px 0 20px 0;">
                    <strong>Instructions:</strong> <?php echo htmlspecialchars(!empty($prescription_data['instructions']) ? $prescription_data['instructions'] : 'None provided.'); ?>
                </p>

                <h4 style="margin-top: 20px; color: #1a5276;">Prescribed Medications</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Item Name (Trade)</th>
                            <th>Generic Name</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Stock Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                        $can_dispense = true;
                        
                        if (count($prescription_items) > 0) {
                            foreach ($prescription_items as $item) {
                                $trade     = htmlspecialchars($item['trade_name']);
                                $generic   = htmlspecialchars($item['generic_name']);
                                $dosage    = htmlspecialchars($item['dosage']);
                                $frequency = htmlspecialchars($item['frequency']);
                                $duration  = intval($item['duration_days']) . " Days";
                                $stock     = intval($item['stock_quantity']);
                                
                                if ($stock <= 0) {
                                    $stock_display = "<span class='badge-out'>Out of Stock ({$stock})</span>";
                                    $can_dispense = false;
                                } else {
                                    $stock_display = "<span class='badge-available'>In Stock ({$stock})</span>";
                                }

                                echo "<tr>
                                        <td><strong>$trade</strong></td>
                                        <td>$generic</td>
                                        <td>$dosage</td>
                                        <td>$frequency</td>
                                        <td>$duration</td>
                                        <td>$stock_display</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; color: #64748b;'>No medication items linked to this prescription.</td></tr>";
                            $can_dispense = false;
                        }
                    ?>
                    </tbody>
                </table>

                <br>
                <!-- Dispense Action Section -->
                <?php if (!$is_dispensed && $can_dispense): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?search_id=' . urlencode($prescription_data['prescription_id']); ?>" method="post" onsubmit="return confirm('Confirm verification and dispense medication for Prescription #<?php echo $prescription_data['prescription_id']; ?>?');">
                        <input type="hidden" name="prescription_id" value="<?php echo htmlspecialchars($prescription_data['prescription_id']); ?>">
                        <button type="submit" name="dispense_prescription" class="btn">Verify & Dispense Medicines</button>
                    </form>
                <?php elseif ($is_dispensed): ?>
                    <div class="alert-success" style="margin-top: 15px; margin-bottom: 0;">This prescription has already been dispensed.</div>
                <?php else: ?>
                    <div class="alert-error" style="margin-top: 15px; margin-bottom: 0;">Cannot dispense: One or more prescribed medicines are out of stock.</div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>

</body>
</html>