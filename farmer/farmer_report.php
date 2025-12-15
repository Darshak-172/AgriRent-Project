<?php
session_start();
require_once('../auth/config.php');
require 'fheader.php';
require 'farmer_nav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'F') {
    header('Location: farmer_login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch equipment bookings
$bookings_query = "
    SELECT 
        eb.booking_id, e.Title, e.Brand, u.Name as owner_name,
        eb.start_date, eb.end_date, eb.total_amount, eb.status
    FROM equipment_bookings eb
    JOIN equipment e ON eb.equipment_id = e.Equipment_id
    JOIN users u ON e.Owner_id = u.user_id
    WHERE eb.customer_id = ? AND eb.status = 'COM'
    ORDER BY eb.booking_id DESC
";

$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bookings_total = array_sum(array_column($bookings_data, 'total_amount')) ?: 0;
$stmt->close();

// Fetch product orders
$orders_query = "
    SELECT 
        po.Order_id, p.Name as product_name, u.Name as seller_name,
        po.quantity, po.order_date, po.total_price, po.Status
    FROM product_orders po
    JOIN product p ON po.Product_id = p.product_id
    JOIN users u ON p.seller_id = u.user_id
    WHERE po.buyer_id = ? AND po.Status = 'COM'
    ORDER BY po.Order_id DESC
";

$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$orders_total = array_sum(array_column($orders_data, 'total_price')) ?: 0;
$stmt->close();

// Fetch product sales
$sales_query = "
    SELECT 
        po.Order_id, p.Name as product_name, u.Name as buyer_name,
        po.quantity, po.order_date, po.total_price, po.Status
    FROM product_orders po
    JOIN product p ON po.Product_id = p.product_id
    JOIN users u ON po.buyer_id = u.user_id
    WHERE p.seller_id = ? AND po.Status = 'COM'
    ORDER BY po.Order_id DESC
";

$stmt = $conn->prepare($sales_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sales_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sales_total = array_sum(array_column($sales_data, 'total_price')) ?: 0;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Financial Report</title>
    <style>
        body {
            background: #f7f9fb;
            font-family: "Segoe UI", sans-serif;
            flex-direction: column;
            margin: 0;
            padding: 0;
        }
        
        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: #f7f9fb;
        }
        
        .main-content {
            flex: 1;
            padding: 0;
            width: auto;
        }
        
        .page-inner {
            padding: 34px 40px;
        }
        
        .page-header {
            background: white;
            padding: 24px 40px;
            margin-bottom: 0;
            border-bottom: 1px solid #e1e5ea;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #234a23;
            font-weight: bold;
        }
        
        .page-header p {
            margin: 8px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 20px;
            background: white;
            padding: 0 40px;
            border-bottom: 1px solid #e1e5ea;
        }
        
        .tab {
            padding: 15px 28px;
            border-radius: 8px 8px 0 0;
            background: transparent;
            color: #234a23;
            font-weight: bold;
            font-size: 1rem;
            border: none;
            outline: none;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            background: transparent;
            color: #234a23;
            border-bottom-color: #234a23;
        }
        
        .tab:hover {
            color: #234a23;
            border-bottom-color: #234a23;
        }
        
        .export-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
        }
        
        .btn {
            background: #234a23;
            color: #fff;
            border: none;
            padding: 11px 22px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: #1c3920;
        }
        
        .btn-print {
            background: #234a23;
        }
        
        .btn-print:hover {
            background: #1c3920;
        }
        
        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(163, 176, 198, 0.13);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        th, td {
            padding: 15px 12px;
            text-align: left;
        }
        
        th {
            background: #f7f9fb;
            color: #234a23;
            font-size: 1.07rem;
            font-weight: bold;
            border-bottom: 2px solid #e1e5ea;
        }
        
        tr {
            background: #fff;
            border-bottom: 1px solid #ececec;
        }
        
        tr:hover {
            background: #f8fbfa;
        }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-style: italic;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #234a23;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e1e5ea;
        }
        
        .summary-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 16px rgba(163, 176, 198, 0.13);
            margin-top: 30px;
        }
        
        .summary-title {
            font-size: 18px;
            font-weight: bold;
            color: #234a23;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e1e5ea;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .summary-card {
            background: #f8fbfa;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e1e5ea;
            text-align: center;
        }
        
        .summary-card-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
        }
        
        .summary-card-value {
            font-size: 28px;
            font-weight: bold;
            color: #234a23;
        }
        
        .amount {
            text-align: right;
            font-weight: 600;
            color: #234a23;
        }
        
        .total-row {
            background: #f7f9fb;
            font-weight: bold;
            color: #234a23;
        }
        
        .total-row td {
            background: #234a23;
            color: white;
        }
        
        @media print {
            body {
                background: white;
            }
            .main-content {
                margin-left: 0;
            }
            .page-inner {
                padding: 20px;
            }
            .export-buttons {
                display: none;
            }
        }
        
        @media (max-width: 992px) {
            .page-inner {
                padding: 20px;
            }
            .page-header {
                padding: 20px;
            }
            .tabs {
                padding: 0 20px;
            }
        }
    </style>
    <style>
        body {
            background: #f7f9fb;
            font-family: "Segoe UI", sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: #f7f9fb;
        }
        
        .main-content {
            flex: 1;
            padding: 0;
            width: auto;
        }
        
        .page-inner {
            padding: 34px 40px;
        }
        
        .page-header {
            background: white;
            padding: 24px 40px;
            margin-bottom: 0;
            border-bottom: 1px solid #e1e5ea;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #234a23;
            font-weight: bold;
        }
        
        .page-header p {
            margin: 8px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 20px;
            background: white;
            padding: 0 40px;
            border-bottom: 1px solid #e1e5ea;
        }
        
        .tab {
            padding: 15px 28px;
            background: transparent;
            color: #234a23;
            font-weight: bold;
            font-size: 1rem;
            border: none;
            outline: none;
            text-decoration: none;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            border-bottom-color: #234a23;
        }
        
        .export-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
        }
        
        .btn {
            background: #234a23;
            color: #fff;
            border: none;
            padding: 11px 22px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: #1c3920;
        }
        
        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(163, 176, 198, 0.13);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        th, td {
            padding: 15px 12px;
            text-align: left;
        }
        
        th {
            background: #f7f9fb;
            color: #234a23;
            font-size: 1.07rem;
            font-weight: bold;
            border-bottom: 2px solid #e1e5ea;
        }
        
        tr {
            background: #fff;
            border-bottom: 1px solid #ececec;
        }
        
        tr:hover {
            background: #f8fbfa;
        }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-style: italic;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #234a23;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e1e5ea;
        }
        
        .summary-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 16px rgba(163, 176, 198, 0.13);
            margin-top: 30px;
        }
        
        .summary-title {
            font-size: 18px;
            font-weight: bold;
            color: #234a23;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e1e5ea;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .summary-card {
            background: #f8fbfa;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e1e5ea;
            text-align: center;
        }
        
        .summary-card-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
        }
        
        .summary-card-value {
            font-size: 28px;
            font-weight: bold;
            color: #234a23;
        }
        
        .amount {
            text-align: right;
            font-weight: 600;
            color: #234a23;
        }
        
        .total-row {
            background: #f7f9fb;
            font-weight: bold;
            color: #234a23;
        }
        
        .total-row td {
            background: #234a23;
            color: white;
        }
        
        @media print {
            body {
                background: white;
            }
            
            .export-buttons {
                display: none !important;
            }
            
            .page-header {
                border-bottom: none;
            }
            
            .tabs {
                display: none !important;
            }
            
            .page-inner {
                padding: 20px;
            }
            
            .main-content {
                margin: 0 !important;
            }
            
            .table-container {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="main-content">
            <div class="page-header">
                <h1>Farmer Financial Report</h1>
                <p>Farmer: <strong><?php echo htmlspecialchars($user_name); ?>  <br> Generated: <?php echo date('l, F j, Y H:i:s'); ?></p>
            </div>
            
            <div class="tabs">
                <button class="tab active">Report</button>
            </div>
            
            <div class="page-inner">
                <div class="export-buttons">
    <form method="POST" action="farmer_report_export.php" style="display:inline;">
        <button type="submit" name="export_format" value="pdf" class="btn">Export PDF</button>
        <button type="submit" name="export_format" value="excel" class="btn">Export Excel</button>
    </form>
    <button onclick="window.print()" class="btn">️ Print Report</button>
</div>

                
                <!-- Equipment Rentals -->
                <h2 class="section-title"> Equipment Rentals (Money Spent)</h2>
                <?php if (count($bookings_data) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Equipment</th>
                                    <th>Brand</th>
                                    <th>Owner</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Amount (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings_data as $booking): ?>
                                    <tr>
                                        <td><strong><?php echo $booking['booking_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($booking['Title']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['Brand'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['owner_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($booking['start_date'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($booking['end_date'])); ?></td>
                                        <td class="amount">₹<?php echo number_format($booking['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="6" style="text-align: right;">TOTAL SPENT ON RENTALS</td>
                                    <td class="amount">₹<?php echo number_format($bookings_total, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <div class="no-data">No completed equipment rentals</div>
                    </div>
                <?php endif; ?>
                
                <!-- Product Purchases -->
                <h2 class="section-title"> Product Purchases (Money Spent)</h2>
                <?php if (count($orders_data) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Product Name</th>
                                    <th>Seller</th>
                                    <th>Quantity</th>
                                    <th>Order Date</th>
                                    <th>Total (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders_data as $order): ?>
                                    <tr>
                                        <td><strong><?php echo $order['Order_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($order['seller_name']); ?></td>
                                        <td><?php echo $order['quantity']; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                                        <td class="amount">₹<?php echo number_format($order['total_price'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="5" style="text-align: right;">TOTAL SPENT ON PURCHASES</td>
                                    <td class="amount">₹<?php echo number_format($orders_total, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <div class="no-data">No completed product purchases</div>
                    </div>
                <?php endif; ?>
                
                <!-- Product Sales -->
                <h2 class="section-title"> Product Sales (Money Earned)</h2>
                <?php if (count($sales_data) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Product Name</th>
                                    <th>Buyer</th>
                                    <th>Quantity</th>
                                    <th>Sale Date</th>
                                    <th>Total (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales_data as $sale): ?>
                                    <tr>
                                        <td><strong><?php echo $sale['Order_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sale['buyer_name']); ?></td>
                                        <td><?php echo $sale['quantity']; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($sale['order_date'])); ?></td>
                                        <td class="amount">₹<?php echo number_format($sale['total_price'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="5" style="text-align: right;">TOTAL EARNED FROM SALES</td>
                                    <td class="amount">₹<?php echo number_format($sales_total, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <div class="no-data">No completed product sales</div>
                    </div>
                <?php endif; ?>
                
                <!-- Summary -->
                <div class="summary-container">
                    <h3 class="summary-title"> Financial Summary</h3>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <div class="summary-card-label"> Total Money Spent</div>
                            <div class="summary-card-value">₹<?php echo number_format($bookings_total + $orders_total, 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-card-label"> Total Money Earned</div>
                            <div class="summary-card-value">₹<?php echo number_format($sales_total, 2); ?></div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php require 'ffooter.php'; ?>
