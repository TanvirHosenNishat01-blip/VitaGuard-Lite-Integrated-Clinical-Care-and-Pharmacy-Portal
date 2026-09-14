<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('pharmacist'); 
require_once '../config/db.php'; 

// Fetch total medicines in stock
$med_count_res = $conn->query("SELECT COUNT(*) AS total_meds FROM medicines");
$total_meds = ($med_count_res && $row = $med_count_res->fetch_assoc()) ? (int)$row['total_meds'] : 0;

// Fetch low stock medicines (stock quantity < 20)
$low_stock_res = $conn->query("SELECT COUNT(*) AS low_stock FROM medicines WHERE stock_quantity < 20");
$low_stock_count = ($low_stock_res && $row = $low_stock_res->fetch_assoc()) ? (int)$row['low_stock'] : 0;

// Fetch active prescriptions awaiting fulfillment
$active_pres_res = $conn->query("SELECT COUNT(*) AS active_pres FROM prescriptions WHERE status = 'Active'");
$active_pres_count = ($active_pres_res && $row = $active_pres_res->fetch_assoc()) ? (int)$row['active_pres'] : 0;

// Fetch announcements targeted for pharmacists or all users
$notice_sql = "SELECT title, description, created_at 
               FROM system_notices 
               WHERE target_role = 'pharmacist' OR target_role = 'all' 
               ORDER BY created_at DESC 
               LIMIT 3";
$notices_result = $conn->query($notice_sql);

// Resolve pharmacist display name from standardized session variable
$pharmacist_name = trim($_SESSION['name'] ?? 'Pharmacist');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacist Dashboard | VitaGuard Lite</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f6; display: flex; }
        
        /* Sidebar Styling */
        .sidebar { width: 250px; background-color: #1a5276; color: white; min-height: 100vh; padding: 20px; flex-shrink: 0; }
        .sidebar h2 { margin-top: 0; font-size: 22px; }
        .sidebar p { color: #d4e6f1; font-size: 14px; margin-bottom: 20px; }
        .sidebar a { color: #d4e6f1; text-decoration: none; display: block; padding: 12px 0; font-size: 15px; border-bottom: 1px solid #2980b9; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { color: #ffffff; padding-left: 6px; }
        
        /* Main Layout */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; }
        .dashboard-title { margin-top: 0; color: #1a5276; }
        
        /* Metric Stats Row */
        .stats-grid { display: flex; gap: 20px; margin: 25px 0 30px 0; flex-wrap: wrap; }
        .stat-card { background: white; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; flex: 1; min-width: 180px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); text-align: center; }
        .stat-card h4 { margin: 0; color: #64748b; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-value { font-size: 36px; font-weight: bold; color: #1a5276; margin-top: 8px; }
        
        /* Action Cards */
        .card-container { display: flex; gap: 20px; margin-top: 10px; flex-wrap: wrap; }
        .card { background: white; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; flex: 1; min-width: 280px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .card h3 { margin-top: 0; color: #1a5276; font-size: 18px; }
        .card p { color: #64748b; font-size: 14px; line-height: 1.5; }
        
        /* Buttons */
        .btn { display: inline-block; padding: 10px 16px; background-color: #117a65; color: white; text-decoration: none; border-radius: 5px; margin-top: 12px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn:hover { background-color: #0e6251; }
        .btn-danger { background-color: #e74c3c; width: 100%; margin-top: 30px; padding: 10px 15px; border-radius: 5px; border: none; cursor: pointer; color: white; font-weight: bold; }
        .btn-danger:hover { background-color: #c0392b; }
        
        /* Announcements Section */
        .notice-box { margin-top: 35px; background: #fff8e6; padding: 20px; border-left: 5px solid #f39c12; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>VitaGuard Lite</h2>
        <p>Role: Pharmacist</p>
        
        <a href="dashboard.php" class="active">🏠 Dashboard</a>
        <a href="inventory.php">📦 Medicine Inventory</a>
        <a href="dispense_check.php">✔️ Prescription Verify</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn btn-danger">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Dynamic Header Greeting -->
        <h1 class="dashboard-title">Welcome, <?php echo htmlspecialchars($pharmacist_name); ?>!</h1>
        <p style="color: #64748b; margin-top: -5px;">Real-time Pharmacy Overview & Inventory Status.</p>
        
        <!-- Quick Inventory & Dispense Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Stocked Medicines</h4>
                <div class="stat-value"><?php echo $total_meds; ?></div>
            </div>
            <div class="stat-card">
                <h4>Low Stock Alerts</h4>
                <div class="stat-value" style="color: #e74c3c;"><?php echo $low_stock_count; ?></div>
            </div>
            <div class="stat-card">
                <h4>Active Prescriptions</h4>
                <div class="stat-value" style="color: #f39c12;"><?php echo $active_pres_count; ?></div>
            </div>
        </div>

        <!-- Navigation Action Cards -->
        <div class="card-container">
            <!-- Inventory Card -->
            <div class="card">
                <h3>Medicine Inventory</h3>
                <p>Register new medicines, replenish inventory quantities, and adjust retail pricing.</p>
                <a href="inventory.php" class="btn">Manage Inventory</a>
            </div>
            
            <!-- Prescription Fulfillment Card -->
            <div class="card">
                <h3>Prescription Verification</h3>
                <p>Lookup active doctor prescriptions by Prescription ID, inspect dosages, and dispense medicines.</p>
                <a href="dispense_check.php" class="btn">Verify & Dispense</a>
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
                    <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #fce5b5;">
                        <strong style="font-size: 15px; color: #856404;"><?php echo $n_title; ?></strong> 
                        <span style="font-size: 12px; color: #b58900;">(<?php echo $n_date; ?>)</span><br>
                        <span style="font-size: 13.5px; color: #64748b; display: block; margin-top: 4px;"><?php echo $n_desc; ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #856404; font-size: 14px; margin: 0;">No new announcements at this time.</p>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>