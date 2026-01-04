<?php
session_start();

require_once 'auth/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Alert messages for complaint
$alert_msg  = '';
$alert_type = ''; // success / error

/* ---------------------------------
   HANDLE COMPLAINT SUBMISSION
---------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $complaint_type = $_POST['complaint_type'] ?? '';
    $target_id      = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
    $description    = trim($_POST['complaint_text'] ?? '');

    if (!in_array($complaint_type, ['E', 'P'], true)) {
        $alert_msg  = 'Invalid complaint type.';
        $alert_type = 'error';
    } elseif ($target_id <= 0) {
        $alert_msg  = 'Invalid target reference.';
        $alert_type = 'error';
    } elseif ($description === '') {
        $alert_msg  = 'Complaint description is required.';
        $alert_type = 'error';
    } else {
        // Verify that this user really has this equipment booking / product order
        if ($complaint_type === 'E') {
            $check_sql = "SELECT booking_id 
                          FROM equipment_bookings 
                          WHERE customer_id = ? AND equipment_id = ? 
                          LIMIT 1";
        } else { // 'P'
            $check_sql = "SELECT Order_id 
                          FROM product_orders 
                          WHERE buyer_id = ? AND Product_id = ? 
                          LIMIT 1";
        }

        $check_stmt = $conn->prepare($check_sql);
        if ($check_stmt) {
            $check_stmt->bind_param('ii', $user_id, $target_id);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            $row       = $check_res->fetch_assoc();
            $check_stmt->close();

            if (!$row) {
                $alert_msg  = 'You cannot file a complaint for this item.';
                $alert_type = 'error';
            } else {
                // Insert into complaints table: Status default Open (O)
                $ins_sql = "INSERT INTO complaints (User_id, Complaint_type, ID, Description, Status) 
                            VALUES (?, ?, ?, ?, 'O')";
                $ins_stmt = $conn->prepare($ins_sql);
                if ($ins_stmt) {
                    $ins_stmt->bind_param('isis', $user_id, $complaint_type, $target_id, $description);
                    if ($ins_stmt->execute()) {
                        $alert_msg  = 'Your complaint has been submitted successfully.';
                        $alert_type = 'success';
                    } else {
                        $alert_msg  = 'Failed to submit complaint. Please try again.';
                        $alert_type = 'error';
                    }
                    $ins_stmt->close();
                } else {
                    $alert_msg  = 'Error while preparing complaint query.';
                    $alert_type = 'error';
                }
            }
        } else {
            $alert_msg  = 'Error while checking booking/order for complaint.';
            $alert_type = 'error';
        }
    }
}

// Get filter type from URL parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Status badge function
function getStatusBadge($status) {
    switch($status) {
        case 'PEN': return '<span class="status-pending">Pending</span>';
        case 'CON': return '<span class="status-confirmed">Confirmed</span>';
        case 'REJ': return '<span class="status-rejected">Rejected</span>';
        case 'COM': return '<span class="status-completed">Completed</span>';
        default: return '<span class="status-pending">' . htmlspecialchars(ucfirst(strtolower($status))) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings & Orders - AgriRent</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            padding: 30px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f5f5f5;
        }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Page Header Styles */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .header-left h1 {
            margin: 0 0 5px 0;
            color: #234a23;
            font-size: 28px;
            font-weight: 600;
        }

        .header-left p {
            color: #666;
            font-size: 14px;
        }

        .header-right {
            flex-shrink: 0;
        }

        /* Button Styles */
        .btn-primary {
            background: #234a23;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary:hover {
            background: #1a3a1a;
            color: white;
        }

        .btn-details {
            background: #234a23;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 4px;
        }

        .btn-details:hover {
            background: #237a23;
            transform: translateY(-1px);
        }

        .btn-complain {
            background: #ffc107;
            color: #212529;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-complain i {
            font-size: 11px;
        }
        .btn-complain:hover {
            background: #e0a800;
        }

        .complaint-chip {
            margin-top: 4px;
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .complaint-chip.pending {
            background: #fff3cd;
            color: #856404;
        }
        .complaint-chip.replied {
            background: #d4edda;
            color: #155724;
        }

        /* Navigation Tabs */
        .nav-tabs {
            margin-bottom: 25px;
        }

        .nav-tabs a {
            display: inline-block;
            padding: 12px 20px;
            margin-right: 10px;
            background: white;
            color: #234a23;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }

        .nav-tabs a.active,
        .nav-tabs a:hover {
            background: #234a23;
            color: white;
            border-color: #234a23;
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .table-header {
            background: #234a23;
            color: white;
            padding: 15px 20px;
        }

        .table-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: #234a23;
            color: white;
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            color: #333;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-pending {
            background-color: #ffc107;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-confirmed {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-rejected {
            background-color: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-completed {
            background-color: #234a23;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content-large {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: none;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-content-large h2 {
            background: #234a23;
            color: white;
            margin: 0;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            font-size: 20px;
            font-weight: 600;
        }

        .modal-body {
            padding: 20px;
        }

        .booking-image-section, 
        .booking-details-section, 
        .product-image-section, 
        .product-details-section,
        .booking-complaint-section,
        .order-complaint-section {
            margin-bottom: 30px;
        }

        .booking-image-section h3, 
        .booking-details-section h3, 
        .product-image-section h3, 
        .product-details-section h3,
        .booking-complaint-section h3,
        .order-complaint-section h3 {
            color: #234a23;
            margin-bottom: 15px;
            border-bottom: 2px solid #eee;
            padding-bottom: 5px;
            font-size: 16px;
            font-weight: 600;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .detail-row {
            display: flex;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #234a23;
        }

        .detail-row strong {
            min-width: 150px;
            color: #234a23;
            font-weight: 600;
        }

        .detail-row span {
            flex: 1;
            margin-left: 10px;
            color: #333;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 25px;
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
        }

        .close:hover {
            color: #ccc;
        }

        /* Complaint Modal specifics */
        .complaint-info-row {
            margin-bottom: 10px;
            font-size: 14px;
        }
        .complaint-info-row strong {
            color: #234a23;
        }
        .complaint-form-group {
            margin-bottom: 12px;
        }
        .complaint-form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #333;
        }
        .complaint-form-group textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
            min-height: 90px;
            font-family: inherit;
        }
        .complaint-form-group textarea:focus {
            outline: none;
            border-color: #234a23;
            box-shadow: 0 0 4px rgba(35,74,35,0.25);
        }
        .complaint-actions {
            text-align: right;
            margin-top: 10px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
            display: block;
        }

        .empty-state strong {
            display: block;
            font-size: 18px;
            margin-bottom: 8px;
            color: #333;
        }

        .empty-state small {
            color: #999;
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
                gap: 15px;
            }

            .nav-tabs a {
                display: block;
                margin-bottom: 8px;
                margin-right: 0;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 8px 6px;
            }

            .modal-content-large {
                width: 95%;
                margin: 1% auto;
            }

            .detail-row {
                flex-direction: column;
            }

            .detail-row strong {
                min-width: auto;
                margin-bottom: 5px;
            }

            .detail-row span {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-left">
            <h1>My Bookings & Orders</h1>
            <p>Track and manage all your equipment bookings and product orders</p>
        </div>
        <div class="header-right">
            <a href="index.php" class="btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Home 
            </a>
        </div>
    </div>

    <?php if ($alert_msg !== ''): ?>
        <div class="alert <?php echo $alert_type === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($alert_msg); ?>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="nav-tabs">
        <a href="mybooking_orders.php?filter=all" class="<?php echo $filter == 'all' ? 'active' : ''; ?>">
            All Records
        </a>
        <a href="mybooking_orders.php?filter=equipment" class="<?php echo $filter == 'equipment' ? 'active' : ''; ?>">
            Equipment Bookings
        </a>
        <a href="mybooking_orders.php?filter=product" class="<?php echo $filter == 'product' ? 'active' : ''; ?>">
            Product Orders
        </a>
    </div>

    <?php
    // Equipment Bookings Section
    if ($filter == 'all' || $filter == 'equipment') {
        echo '<div class="table-container">
                <div class="table-header">
                    <h2>Equipment Bookings</h2>
                </div>
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Equipment</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Time Slot</th>
                        <th>Duration</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

        // Fetch equipment bookings with latest complaint (if any)
        $query = "SELECT 
                    eb.*, 
                    e.Title, e.Brand, e.Model, 
                    img.image_url,
                    c.Status      AS complaint_status,
                    c.reply       AS complaint_reply,
                    c.Description AS complaint_description,
                    c.updated_at  AS complaint_updated_at
                  FROM equipment_bookings eb 
                  LEFT JOIN equipment e ON eb.equipment_id = e.Equipment_id 
                  LEFT JOIN images img ON img.ID = e.Equipment_id AND img.image_type = 'E'
                  LEFT JOIN complaints c
                    ON c.User_id = eb.customer_id
                   AND c.Complaint_type = 'E'
                   AND c.ID = eb.equipment_id
                   AND c.created_at = (
                        SELECT MAX(c2.created_at)
                        FROM complaints c2
                        WHERE c2.User_id = eb.customer_id
                          AND c2.Complaint_type = 'E'
                          AND c2.ID = eb.equipment_id
                   )
                  WHERE eb.customer_id = ? 
                  ORDER BY eb.booking_id DESC";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                while ($booking = $result->fetch_assoc()) {
                    $equipment_name = $booking['Title'] ?? 'N/A';
                    $duration = ($booking['Hours'] ?? 0) . ' hours';

                    $has_complaint = !empty($booking['complaint_status']) || !empty($booking['complaint_description']);
                    $has_reply     = !empty($booking['complaint_reply']);
                    
                    // Prepare booking data for JavaScript
                    $booking_json = htmlspecialchars(json_encode($booking), ENT_QUOTES, 'UTF-8');
                    
                    echo '<tr>
                            <td><strong>' . $booking['booking_id'] . '</strong></td>
                            <td>' . htmlspecialchars($equipment_name) . '</td>
                            <td>' . date('M d, Y', strtotime($booking['start_date'])) . '</td>
                            <td>' . date('M d, Y', strtotime($booking['end_date'])) . '</td>
                            <td>' . ($booking['time_slot'] ?? 'N/A') . '</td>
                            <td>' . $duration . '</td>
                            <td><strong>Rs. ' . number_format($booking['total_amount'], 2) . '</strong></td>
                            <td>' . getStatusBadge($booking['status']) . '</td>
                            <td>
                                <button class="btn-details" onclick=\'viewBookingDetails(' . $booking_json . ')\'>
                                     Details
                                </button><br>
                                <button class="btn-complain" onclick=\'openBookingComplaint(' . $booking_json . ')\' title="File complaint for this booking">
                                    <i class="fas fa-exclamation-circle"></i> Complaint
                                </button>';

                    if ($has_reply) {
                        echo '<div class="complaint-chip replied">Reply received</div>';
                    } elseif ($has_complaint) {
                        echo '<div class="complaint-chip pending">Complaint submitted</div>';
                    }

                    echo    '</td>
                          </tr>';
                }
            } else {
                echo '<tr><td colspan="9" class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <strong>No Equipment Bookings Found</strong>
                        <small>Your equipment bookings will appear here</small>
                      </td></tr>';
            }
        } else {
            echo '<tr><td colspan="9" class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Error Loading Bookings</strong>
                    <small>' . htmlspecialchars($conn->error) . '</small>
                  </td></tr>';
        }

        echo '</tbody></table></div>';
    }

    // Product Orders Section
    if ($filter == 'all' || $filter == 'product') {
        echo '<div class="table-container">
                <div class="table-header">
                    <h2>Product Orders</h2>
                </div>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

        // Fetch product orders with latest complaint (if any)
        $query = "SELECT 
                    po.*,
                    p.Name AS product_name,
                    img.image_url,
                    c.Status      AS complaint_status,
                    c.reply       AS complaint_reply,
                    c.Description AS complaint_description,
                    c.updated_at  AS complaint_updated_at
                  FROM product_orders po 
                  LEFT JOIN product p ON po.Product_id = p.product_id 
                  LEFT JOIN images img ON img.ID = p.product_id AND img.image_type = 'P'
                  LEFT JOIN complaints c
                    ON c.User_id = po.buyer_id
                   AND c.Complaint_type = 'P'
                   AND c.ID = po.Product_id
                   AND c.created_at = (
                        SELECT MAX(c2.created_at)
                        FROM complaints c2
                        WHERE c2.User_id = po.buyer_id
                          AND c2.Complaint_type = 'P'
                          AND c2.ID = po.Product_id
                   )
                  WHERE po.buyer_id = ? 
                  ORDER BY po.Order_id DESC";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                while ($order = $result->fetch_assoc()) {
                    $product_name = $order['product_name'] ?? 'N/A';

                    $has_complaint = !empty($order['complaint_status']) || !empty($order['complaint_description']);
                    $has_reply     = !empty($order['complaint_reply']);
                    
                    // Prepare order data for JavaScript
                    $order_json = htmlspecialchars(json_encode($order), ENT_QUOTES, 'UTF-8');
                    
                    echo '<tr>
                            <td><strong>' . $order['Order_id'] . '</strong></td>
                            <td>' . htmlspecialchars($product_name) . '</td>
                            <td>' . number_format($order['quantity'], 2) . '</td>
                            <td><strong>Rs. ' . number_format($order['total_price'], 2) . '</strong></td>
                            <td>' . date('M d, Y', strtotime($order['order_date'])) . '</td>
                            <td>' . getStatusBadge($order['Status']) . '</td>
                            <td>
                                <button class="btn-details" onclick=\'viewOrderDetails(' . $order_json . ')\' >
                                     Details
                                </button><br>
                                <button class="btn-complain" onclick=\'openOrderComplaint(' . $order_json . ')\' title="File complaint for this order">
                                    <i class="fas fa-exclamation-circle"></i> Complaint
                                </button>';

                    if ($has_reply) {
                        echo '<div class="complaint-chip replied">Reply received</div>';
                    } elseif ($has_complaint) {
                        echo '<div class="complaint-chip pending">Complaint submitted</div>';
                    }

                    echo    '</td>
                          </tr>';
                }
            } else {
                echo '<tr><td colspan="7" class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <strong>No Product Orders Found</strong>
                        <small>Your product orders will appear here</small>
                      </td></tr>';
            }
        } else {
            echo '<tr><td colspan="7" class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Error Loading Orders</strong>
                    <small>' . htmlspecialchars($conn->error) . '</small>
                  </td></tr>';
        }

        echo '</tbody></table></div>';
    }
    ?>

    <!-- Booking Details Modal -->
    <div id="bookingDetailsModal" class="modal" style="display: none;">
        <div class="modal-content-large">
            <span class="close" onclick="closeBookingModal()">&times;</span>
            <h2>Booking Details</h2>
            
            <div class="modal-body">
                <div class="booking-image-section">
                    <h3>Equipment Image</h3>
                    <div id="bookingImageContainer"></div>
                </div>
                
                <div class="booking-details-section">
                    <h3>Booking Information</h3>
                    <div class="details-grid">
                        <div class="detail-row">
                            <strong>Booking ID:</strong>
                            <span id="detailBookingId"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Equipment:</strong>
                            <span id="detailEquipmentTitle"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Brand/Model:</strong>
                            <span id="detailBrandModel"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Start Date:</strong>
                            <span id="detailStartDate"></span>
                        </div>
                        <div class="detail-row">
                            <strong>End Date:</strong>
                            <span id="detailEndDate"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Time Slot:</strong>
                            <span id="detailTimeSlot"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Duration:</strong>
                            <span id="detailHours"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Total Amount:</strong>
                            <span id="detailTotalAmount"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Status:</strong>
                            <span id="detailStatus"></span>
                        </div>
                    </div>
                </div>

                <!-- Complaint & Reply for Booking -->
                <div class="booking-complaint-section" id="bookingComplaintSection" style="display:none;">
                    <h3>Complaint & Reply</h3>
                    <div class="details-grid">
                        <div class="detail-row">
                            <strong>Last Complaint:</strong>
                            <span id="bookingComplaintDescription"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Complaint Status:</strong>
                            <span id="bookingComplaintStatus"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Reply:</strong>
                            <span id="bookingComplaintReply"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Last Updated:</strong>
                            <span id="bookingComplaintUpdated"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal" style="display: none;">
        <div class="modal-content-large">
            <span class="close" onclick="closeOrderModal()">&times;</span>
            <h2>Order Details</h2>
            
            <div class="modal-body">
                <div class="product-image-section">
                    <h3>Product Image</h3>
                    <div id="orderImageContainer"></div>
                </div>
                
                <div class="product-details-section">
                    <h3>Order Information</h3>
                    <div class="details-grid">
                        <div class="detail-row">
                            <strong>Order ID:</strong>
                            <span id="detailOrderId"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Product:</strong>
                            <span id="detailProductName"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Quantity:</strong>
                            <span id="detailQuantity"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Total Price:</strong>
                            <span id="detailTotalPrice"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Order Date:</strong>
                            <span id="detailOrderDate"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Status:</strong>
                            <span id="detailOrderStatus"></span>
                        </div>
                    </div>
                </div>

                <!-- Complaint & Reply for Order -->
                <div class="order-complaint-section" id="orderComplaintSection" style="display:none;">
                    <h3>Complaint & Reply</h3>
                    <div class="details-grid">
                        <div class="detail-row">
                            <strong>Last Complaint:</strong>
                            <span id="orderComplaintDescription"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Complaint Status:</strong>
                            <span id="orderComplaintStatus"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Reply:</strong>
                            <span id="orderComplaintReply"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Last Updated:</strong>
                            <span id="orderComplaintUpdated"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Complaint Modal (common for booking & order) -->
    <div id="complaintModal" class="modal" style="display:none;">
        <div class="modal-content-large">
            <span class="close" onclick="closeComplaintModal()">&times;</span>
            <h2>File a Complaint</h2>
            <div class="modal-body">
                <div class="complaint-info-row">
                    <strong>Type:</strong> <span id="complaintTypeText"></span>
                </div>
                <div class="complaint-info-row">
                    <strong>Item:</strong> <span id="complaintItemText"></span>
                </div>
                <form method="POST">
                    <input type="hidden" name="complaint_type" id="complaint_type" value="">
                    <input type="hidden" name="target_id" id="complaint_target_id" value="">

                    <div class="complaint-form-group">
                        <label for="complaint_text">Complaint Description <span style="color:#dc3545;">*</span></label>
                        <textarea name="complaint_text" id="complaint_text" required
                                  placeholder="Describe the issue you faced with this booking/order..."></textarea>
                    </div>

                    <div class="complaint-actions">
                        <button type="button" class="btn-details" style="background:#6c757d;" onclick="closeComplaintModal()">
                            Cancel
                        </button>
                        <button type="submit" name="submit_complaint" class="btn-details" style="margin-left:8px;">
                            Submit Complaint
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // View booking details function
        function viewBookingDetails(booking) {
            document.getElementById('detailBookingId').textContent = '' + String(booking.booking_id);
            document.getElementById('detailEquipmentTitle').textContent = booking.Title || 'N/A';
            document.getElementById('detailBrandModel').textContent = (booking.Brand || 'N/A') + ' ' + (booking.Model || '');
            document.getElementById('detailHours').textContent = (booking.Hours || 'N/A') + ' hours';
            document.getElementById('detailTimeSlot').textContent = booking.time_slot || 'N/A';
            
            // Format dates
            if (booking.start_date) {
                const startDate = new Date(booking.start_date);
                document.getElementById('detailStartDate').textContent = startDate.toLocaleDateString('en-US', {
                    year: 'numeric', month: 'short', day: 'numeric'
                });
            }
            
            if (booking.end_date) {
                const endDate = new Date(booking.end_date);
                document.getElementById('detailEndDate').textContent = endDate.toLocaleDateString('en-US', {
                    year: 'numeric', month: 'short', day: 'numeric'
                });
            }
            
            // Set status
            const statusSpan = document.getElementById('detailStatus');
            let statusHtml = '';
            switch(booking.status) {
                case 'PEN': statusHtml = '<span class="status-pending">Pending</span>'; break;
                case 'CON': statusHtml = '<span class="status-confirmed">Confirmed</span>'; break;
                case 'REJ': statusHtml = '<span class="status-rejected">Rejected</span>'; break;
                case 'COM': statusHtml = '<span class="status-completed">Completed</span>'; break;
                default: statusHtml = '<span class="status-pending">' + booking.status + '</span>';
            }
            statusSpan.innerHTML = statusHtml;
            
            document.getElementById('detailTotalAmount').textContent = 'Rs. ' + parseFloat(booking.total_amount).toLocaleString();
            
            // Handle image from images table
            const imageContainer = document.getElementById('bookingImageContainer');
            if (booking.image_url) {
                imageContainer.innerHTML = '<img src="../' + booking.image_url + '" alt="Equipment" style="max-width: 100%; border-radius: 8px;">';
            } else {
                imageContainer.innerHTML = '<div style="padding: 50px; background: #f8f9fa; border-radius: 8px; text-align: center; color: #666;"><i class="fas fa-image" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>No Image Available</div>';
            }

            // Complaint & reply section for booking
            const section = document.getElementById('bookingComplaintSection');
            const descEl  = document.getElementById('bookingComplaintDescription');
            const statusEl= document.getElementById('bookingComplaintStatus');
            const replyEl = document.getElementById('bookingComplaintReply');
            const updEl   = document.getElementById('bookingComplaintUpdated');

            if (booking.complaint_status || booking.complaint_reply || booking.complaint_description) {
                section.style.display = 'block';
                descEl.textContent  = booking.complaint_description || '—';

                let cStatusText = '—';
                if (booking.complaint_status === 'O') cStatusText = 'Open';
                else if (booking.complaint_status === 'P') cStatusText = 'In Progress';
                else if (booking.complaint_status === 'R') cStatusText = 'Resolved';
                else if (booking.complaint_status) cStatusText = booking.complaint_status;
                statusEl.textContent = cStatusText;

                replyEl.textContent = booking.complaint_reply || 'No reply yet.';
                updEl.textContent   = booking.complaint_updated_at || '—';
            } else {
                section.style.display = 'none';
            }
            
            document.getElementById('bookingDetailsModal').style.display = 'block';
        }

        // View order details function
        function viewOrderDetails(order) {
            document.getElementById('detailOrderId').textContent = String(order.Order_id);
            document.getElementById('detailProductName').textContent = order.product_name || 'N/A';
            document.getElementById('detailQuantity').textContent = parseFloat(order.quantity).toLocaleString();
            document.getElementById('detailTotalPrice').textContent = 'Rs. ' + parseFloat(order.total_price).toLocaleString();
            
            if (order.order_date) {
                const orderDate = new Date(order.order_date);
                document.getElementById('detailOrderDate').textContent = orderDate.toLocaleDateString('en-US', {
                    year: 'numeric', month: 'short', day: 'numeric'
                });
            }
            
            // Set status
            const statusSpan = document.getElementById('detailOrderStatus');
            let statusHtml = '';
            switch(order.Status) {
                case 'PEN': statusHtml = '<span class="status-pending">Pending</span>'; break;
                case 'CON': statusHtml = '<span class="status-confirmed">Confirmed</span>'; break;
                case 'REJ': statusHtml = '<span class="status-rejected">Rejected</span>'; break;
                case 'COM': statusHtml = '<span class="status-completed">Completed</span>'; break;
                default: statusHtml = '<span class="status-pending">' + order.Status + '</span>';
            }
            statusSpan.innerHTML = statusHtml;
            
            // Handle image from images table
            const imageContainer = document.getElementById('orderImageContainer');
            if (order.image_url) {
                imageContainer.innerHTML = '<img src="../' + order.image_url + '" alt="Product" style="max-width: 100%; border-radius: 8px;">';
            } else {
                imageContainer.innerHTML = '<div style="padding: 50px; background: #f8f9fa; border-radius: 8px; text-align: center; color: #666;"><i class="fas fa-box" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>No Image Available</div>';
            }

            // Complaint & reply section for order
            const section = document.getElementById('orderComplaintSection');
            const descEl  = document.getElementById('orderComplaintDescription');
            const statusEl= document.getElementById('orderComplaintStatus');
            const replyEl = document.getElementById('orderComplaintReply');
            const updEl   = document.getElementById('orderComplaintUpdated');

            if (order.complaint_status || order.complaint_reply || order.complaint_description) {
                section.style.display = 'block';
                descEl.textContent  = order.complaint_description || '—';

                let cStatusText = '—';
                if (order.complaint_status === 'O') cStatusText = 'Open';
                else if (order.complaint_status === 'P') cStatusText = 'In Progress';
                else if (order.complaint_status === 'R') cStatusText = 'Resolved';
                else if (order.complaint_status) cStatusText = order.complaint_status;
                statusEl.textContent = cStatusText;

                replyEl.textContent = order.complaint_reply || 'No reply yet.';
                updEl.textContent   = order.complaint_updated_at || '—';
            } else {
                section.style.display = 'none';
            }
            
            document.getElementById('orderDetailsModal').style.display = 'block';
        }

        function closeBookingModal() {
            document.getElementById('bookingDetailsModal').style.display = 'none';
        }

        function closeOrderModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }

        // OPEN COMPLAINT from booking
        function openBookingComplaint(booking) {
            document.getElementById('complaint_type').value      = 'E';
            document.getElementById('complaint_target_id').value = booking.equipment_id;
            document.getElementById('complaintTypeText').textContent = 'Equipment Booking';
            document.getElementById('complaintItemText').textContent =
                (booking.Title || 'N/A') + ' (Booking ID: EB-' + String(booking.booking_id).padStart(4, '0') + ')';
            document.getElementById('complaint_text').value = '';
            document.getElementById('complaintModal').style.display = 'block';
        }

        // OPEN COMPLAINT from order
        function openOrderComplaint(order) {
            document.getElementById('complaint_type').value      = 'P';
            document.getElementById('complaint_target_id').value = order.Product_id;
            document.getElementById('complaintTypeText').textContent = 'Product Order';
            document.getElementById('complaintItemText').textContent =
                (order.product_name || 'N/A') + ' (Order ID: PO-' + String(order.Order_id).padStart(4, '0') + ')';
            document.getElementById('complaint_text').value = '';
            document.getElementById('complaintModal').style.display = 'block';
        }

        function closeComplaintModal() {
            document.getElementById('complaintModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('bookingDetailsModal')) {
                closeBookingModal();
            }
            if (event.target == document.getElementById('orderDetailsModal')) {
                closeOrderModal();
            }
            if (event.target == document.getElementById('complaintModal')) {
                closeComplaintModal();
            }
        }
    </script>
</body>
</html>
