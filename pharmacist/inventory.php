<?php
// Security check and database connection
require_once '../auth/auth_check.php';
check_access('pharmacist'); 
require_once '../config/db.php';

$message = "";
$error_msg = "";

// 1. Create: Register new medicine item into inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    $trade_name     = trim($_POST['trade_name'] ?? '');
    $generic_name   = trim($_POST['generic_name'] ?? '');
    $category       = trim($_POST['category'] ?? '');
    $unit_price     = floatval($_POST['unit_price'] ?? 0);
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $expiry_date    = trim($_POST['expiry_date'] ?? '');

    if (!empty($trade_name) && !empty($generic_name) && !empty($category) && $unit_price > 0 && !empty($expiry_date)) {
        $sql = "INSERT INTO medicines (trade_name, generic_name, category, unit_price, stock_quantity, expiry_date) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssdis', $trade_name, $generic_name, $category, $unit_price, $stock_quantity, $expiry_date);
        
        if ($stmt->execute()) {
            $message = "Medicine added successfully to inventory!";
        } else {
            $error_msg = "Failed to add medicine: " . htmlspecialchars($conn->error);
        }
        $stmt->close();
    } else {
        $error_msg = "Please fill in all medicine details correctly.";
    }
}

// 2. Delete: Remove a medicine item from inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_medicine'])) {
    $medicine_id = intval($_POST['medicine_id'] ?? 0);
    
    if ($medicine_id > 0) {
        $sql = "DELETE FROM medicines WHERE medicine_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $medicine_id);
        
        if ($stmt->execute()) {
            $message = "Medicine item removed successfully!";
        } else {
            $error_msg = "Failed to remove medicine record.";
        }
        $stmt->close();
    }
}

// 3. Read: Fetch entire medicine stock list
$sql = "SELECT * FROM medicines ORDER BY medicine_id DESC";
$result = $conn->query($sql);
$medicinesList = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $medicinesList[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory | VitaGuard Lite</title>
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
        .page-title { margin-top: 0; color: #1a5276; }
        
        /* Form Card */
        .card { background: white; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .card h3 { margin-top: 0; color: #1a5276; font-size: 18px; margin-bottom: 18px; }
        
        /* Responsive Grid Form */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 13px; }
        .form-group input, 
        .form-group select { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; outline: none; }
        .form-group input:focus, 
        .form-group select:focus { border-color: #117a65; }
        
        /* Table Layout */
        .table-container { background: white; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); overflow: hidden; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        table, th, td { border: 1px solid #eee; padding: 12px 14px; text-align: left; }
        th { background-color: #117a65; color: white; font-size: 14px; font-weight: 600; }
        td { font-size: 14px; color: #333; }
        
        /* Stock Badges */
        .stock-normal { color: #16a34a; font-weight: 600; }
        .stock-low { color: #dc2626; font-weight: bold; }
        
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
        <p>Role: Pharmacist</p>
        
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="inventory.php" class="active">📦 Medicine Inventory</a>
        <a href="dispense_check.php">✔️ Prescription Verify</a>
        
        <!-- Logout Form -->
        <form action="../auth/logout.php" method="post">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1 class="page-title">Medicine Inventory Management</h1>
        <p style="color: #64748b; margin-top: -5px;">Add pharmaceutical items, restock quantities, and monitor unit costs.</p>
        
        <!-- Action Alerts -->
        <?php if (!empty($message)) echo "<div class='alert-success'>" . htmlspecialchars($message) . "</div>"; ?>
        <?php if (!empty($error_msg)) echo "<div class='alert-error'>" . htmlspecialchars($error_msg) . "</div>"; ?>

        <!-- Add Medicine Form Card -->
        <div class="card">
            <h3>Add New Medicine Item</h3>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Trade Name</label>
                        <input type="text" name="trade_name" placeholder="e.g. Napa Extra" required>
                    </div>
                    <div class="form-group">
                        <label>Generic Name</label>
                        <input type="text" name="generic_name" placeholder="e.g. Paracetamol + Caffeine" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" required>
                            <option value="">Select Category...</option>
                            <option value="Tablet">Tablet</option>
                            <option value="Syrup">Syrup</option>
                            <option value="Injection">Injection</option>
                            <option value="Capsule">Capsule</option>
                            <option value="Ointment">Ointment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unit Price (BDT)</label>
                        <input type="number" step="0.01" name="unit_price" placeholder="e.g. 2.50" required>
                    </div>
                    <div class="form-group">
                        <label>Stock Quantity</label>
                        <input type="number" name="stock_quantity" placeholder="e.g. 100" required>
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="expiry_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <button type="submit" name="add_medicine" class="btn">Add to Inventory</button>
            </form>
        </div>

        <!-- Inventory Data Table Card -->
        <div class="card">
            <h3>Current Stock List</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Trade Name</th>
                            <th>Generic Name</th>
                            <th>Category</th>
                            <th>Unit Price</th>
                            <th>Stock</th>
                            <th>Expiry Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($medicinesList) > 0): ?>
                        <?php foreach ($medicinesList as $med): ?>
                            <?php 
                                $id      = $med['medicine_id'];
                                $trade   = htmlspecialchars($med['trade_name']);
                                $generic = htmlspecialchars($med['generic_name']);
                                $cat     = htmlspecialchars($med['category']);
                                $price   = "৳ " . number_format($med['unit_price'], 2);
                                $stock   = intval($med['stock_quantity']);
                                $expiry  = date("d M Y", strtotime($med['expiry_date']));
                                
                                $stock_class = ($stock < 20) ? 'stock-low' : 'stock-normal';
                                $stock_label = ($stock < 20) ? "{$stock} (Low)" : $stock;
                            ?>
                            <tr>
                                <td>#<?php echo $id; ?></td>
                                <td><strong><?php echo $trade; ?></strong></td>
                                <td><?php echo $generic; ?></td>
                                <td><?php echo $cat; ?></td>
                                <td><?php echo $price; ?></td>
                                <td><span class="<?php echo $stock_class; ?>"><?php echo $stock_label; ?></span></td>
                                <td><?php echo $expiry; ?></td>
                                <td>
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" onsubmit="return confirm('Permanently remove this medicine from inventory?');">
                                        <input type="hidden" name="medicine_id" value="<?php echo $id; ?>">
                                        <button type="submit" name="delete_medicine" class="btn-danger-inline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 25px; color: #64748b;">No medicines found in inventory.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>