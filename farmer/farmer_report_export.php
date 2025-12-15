<?php
session_start();
require_once('../auth/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'F') {
    header('Location: farmer_login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$export_format = isset($_POST['export_format']) ? $_POST['export_format'] : '';

if (empty($export_format)) {
    exit('Invalid export format');
}

ob_end_clean();

// Fetch data
$bookings_query = "
    SELECT eb.booking_id, e.Title, e.Brand, u.Name as owner_name,
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

$orders_query = "
    SELECT po.Order_id, p.Name as product_name, u.Name as seller_name,
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

$sales_query = "
    SELECT po.Order_id, p.Name as product_name, u.Name as buyer_name,
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

// PDF Export
if ($export_format == 'pdf') {
    echo generatePDFReport($user_name, $bookings_data, $bookings_total, $orders_data, $orders_total, $sales_data, $sales_total);
    exit();
}

// Excel Export
elseif ($export_format == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="farmer_report_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    exportToCSV($user_name, $bookings_data, $bookings_total, $orders_data, $orders_total, $sales_data, $sales_total);
    exit();
}

exit('Invalid format');

function generatePDFReport($user_name, $bookings_data, $bookings_total, $orders_data, $orders_total, $sales_data, $sales_total) {
    $net_balance = $sales_total - ($bookings_total + $orders_total);
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Farmer Financial Report</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.4;
                margin: 20px;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #234a23;
                padding-bottom: 20px;
            }
            .header h1 {
                color: #234a23;
                font-size: 24px;
                margin: 0;
            }
            .header p {
                color: #666;
                margin: 5px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f8f9fa;
                font-weight: bold;
                color: #234a23;
            }
            .price {
                text-align: right;
                font-weight: bold;
                color: #28a745;
            }
            .summary {
                margin: 20px 0;
                padding: 15px;
                background: #f8f9fa;
                border-left: 4px solid #234a23;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            @media print {
                body { background: white; }
                .no-print { display: none; }
            }
        </style>
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 1000);
            }
        </script>
    </head>
    <body>
        <div class="header">
            <h1>💰 Farmer Financial Report</h1>
            <p><strong>Farmer:</strong> ' . htmlspecialchars($user_name) . '</p>
            <p>Generated on: ' . date('d M Y, h:i A') . '</p>
            <p>Status: ✅ Completed Transactions Only</p>
        </div>';
    
    // Equipment Rentals
    $html .= '<h2>Equipment Rentals (Money Spent)</h2>';
    $html .= '<table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Equipment</th>
                        <th>Brand</th>
                        <th>Owner</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th class="price">Amount (Rs)</th>
                    </tr>
                </thead>
                <tbody>';
    
    if (count($bookings_data) > 0) {
        foreach ($bookings_data as $booking) {
            $html .= '<tr>
                        <td>#' . $booking['booking_id'] . '</td>
                        <td>' . htmlspecialchars($booking['Title']) . '</td>
                        <td>' . htmlspecialchars($booking['Brand'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($booking['owner_name']) . '</td>
                        <td>' . date('d M Y', strtotime($booking['start_date'])) . '</td>
                        <td>' . date('d M Y', strtotime($booking['end_date'])) . '</td>
                        <td class="price">' . number_format($booking['total_amount'], 2) . '</td>
                      </tr>';
        }
        $html .= '<tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="6" style="text-align: right;">TOTAL</td>
                    <td class="price">' . number_format($bookings_total, 2) . '</td>
                  </tr>';
    } else {
        $html .= '<tr><td colspan="7">No completed equipment rentals</td></tr>';
    }
    
    $html .= '</tbody></table>';
    
    // Product Purchases
    $html .= '<h2>Product Purchases (Money Spent)</h2>';
    $html .= '<table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Product</th>
                        <th>Seller</th>
                        <th>Qty</th>
                        <th>Date</th>
                        <th class="price">Total (Rs)</th>
                    </tr>
                </thead>
                <tbody>';
    
    if (count($orders_data) > 0) {
        foreach ($orders_data as $order) {
            $html .= '<tr>
                        <td>#' . $order['Order_id'] . '</td>
                        <td>' . htmlspecialchars($order['product_name']) . '</td>
                        <td>' . htmlspecialchars($order['seller_name']) . '</td>
                        <td>' . $order['quantity'] . '</td>
                        <td>' . date('d M Y', strtotime($order['order_date'])) . '</td>
                        <td class="price">' . number_format($order['total_price'], 2) . '</td>
                      </tr>';
        }
        $html .= '<tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="5" style="text-align: right;">TOTAL</td>
                    <td class="price">' . number_format($orders_total, 2) . '</td>
                  </tr>';
    } else {
        $html .= '<tr><td colspan="6">No completed product purchases</td></tr>';
    }
    
    $html .= '</tbody></table>';
    
    // Product Sales
    $html .= '<h2>Product Sales (Money Earned)</h2>';
    $html .= '<table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Product</th>
                        <th>Buyer</th>
                        <th>Qty</th>
                        <th>Date</th>
                        <th class="price">Total (Rs)</th>
                    </tr>
                </thead>
                <tbody>';
    
    if (count($sales_data) > 0) {
        foreach ($sales_data as $sale) {
            $html .= '<tr>
                        <td>#' . $sale['Order_id'] . '</td>
                        <td>' . htmlspecialchars($sale['product_name']) . '</td>
                        <td>' . htmlspecialchars($sale['buyer_name']) . '</td>
                        <td>' . $sale['quantity'] . '</td>
                        <td>' . date('d M Y', strtotime($sale['order_date'])) . '</td>
                        <td class="price">' . number_format($sale['total_price'], 2) . '</td>
                      </tr>';
        }
        $html .= '<tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="5" style="text-align: right;">TOTAL</td>
                    <td class="price">' . number_format($sales_total, 2) . '</td>
                  </tr>';
    } else {
        $html .= '<tr><td colspan="6">No completed product sales</td></tr>';
    }
    
    $html .= '</tbody></table>';
    
    // Summary
    $html .= '<div class="summary">
                <h3>Financial Summary</h3>
                <p><strong>Total Money Spent:</strong> Rs. ' . number_format($bookings_total + $orders_total, 2) . '</p>
                <p><strong>Total Money Earned:</strong> Rs. ' . number_format($sales_total, 2) . '</p>
                <p><strong>Net Balance:</strong> Rs. ' . number_format($net_balance, 2) . '</p>
              </div>';
    
    $html .= '<div class="footer">
                <p>This report was generated by AgriRent Farmer Dashboard</p>
                <p>© ' . date('Y') . ' AgriRent. All rights reserved.</p>
              </div>';
    
    $html .= '</body></html>';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="farmer_report_' . date('Y-m-d') . '.html"');
    
    return $html;
}

function exportToCSV($user_name, $bookings_data, $bookings_total, $orders_data, $orders_total, $sales_data, $sales_total) {
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['FARMER FINANCIAL REPORT']);
    fputcsv($output, ['Farmer Name', $user_name]);
    fputcsv($output, ['Generated Date', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Status', 'Completed Transactions Only']);
    fputcsv($output, []);
    
    // Equipment Rentals
    fputcsv($output, ['EQUIPMENT RENTALS (MONEY SPENT)']);
    fputcsv($output, ['Booking ID', 'Equipment', 'Brand', 'Owner', 'Start Date', 'End Date', 'Amount (Rs)']);
    
    foreach ($bookings_data as $booking) {
        fputcsv($output, [
            $booking['booking_id'],
            $booking['Title'],
            $booking['Brand'] ?? 'N/A',
            $booking['owner_name'],
            date('Y-m-d', strtotime($booking['start_date'])),
            date('Y-m-d', strtotime($booking['end_date'])),
            number_format($booking['total_amount'], 2)
        ]);
    }
    
    fputcsv($output, ['TOTAL', '', '', '', '', '', number_format($bookings_total, 2)]);
    fputcsv($output, []);
    
    // Product Purchases
    fputcsv($output, ['PRODUCT PURCHASES (MONEY SPENT)']);
    fputcsv($output, ['Order ID', 'Product', 'Seller', 'Quantity', 'Date', 'Total (Rs)']);
    
    foreach ($orders_data as $order) {
        fputcsv($output, [
            $order['Order_id'],
            $order['product_name'],
            $order['seller_name'],
            $order['quantity'],
            date('Y-m-d', strtotime($order['order_date'])),
            number_format($order['total_price'], 2)
        ]);
    }
    
    fputcsv($output, ['TOTAL', '', '', '', '', number_format($orders_total, 2)]);
    fputcsv($output, []);
    
    // Product Sales
    fputcsv($output, ['PRODUCT SALES (MONEY EARNED)']);
    fputcsv($output, ['Order ID', 'Product', 'Buyer', 'Quantity', 'Date', 'Total (Rs)']);
    
    foreach ($sales_data as $sale) {
        fputcsv($output, [
            $sale['Order_id'],
            $sale['product_name'],
            $sale['buyer_name'],
            $sale['quantity'],
            date('Y-m-d', strtotime($sale['order_date'])),
            number_format($sale['total_price'], 2)
        ]);
    }
    
    $net_balance = $sales_total - ($bookings_total + $orders_total);
    fputcsv($output, ['TOTAL', '', '', '', '', number_format($sales_total, 2)]);
    fputcsv($output, []);
    fputcsv($output, ['FINANCIAL SUMMARY']);
    fputcsv($output, ['Total Money Spent', number_format($bookings_total + $orders_total, 2)]);
    fputcsv($output, ['Total Money Earned', number_format($sales_total, 2)]);
    fputcsv($output, ['Net Balance', number_format($net_balance, 2)]);
    
    fclose($output);
}
?>
