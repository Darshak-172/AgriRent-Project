<?php
session_start();
require_once('../auth/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'F') {
    header('Location: ../login.php');
    exit();
}

$farmer_id = $_SESSION['user_id'];
$message = '';

// Handle order cancellation
if (isset($_GET['action']) && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'cancel') {
        $verify_stmt = $conn->prepare("SELECT po.Order_id, po.Status FROM product_orders WHERE Order_id = ? AND buyer_id = ?");
        
        if ($verify_stmt) {
            $verify_stmt->bind_param('ii', $order_id, $farmer_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows > 0) {
                $order_data = $verify_result->fetch_assoc();
                
                if ($order_data['Status'] !== 'COM' && $order_data['Status'] !== 'CAN') {
                    $update_stmt = $conn->prepare("UPDATE product_orders SET Status = 'CAN' WHERE Order_id = ?");
                    
                    if ($update_stmt) {
                        $update_stmt->bind_param('i', $order_id);
                        
                        if ($update_stmt->execute()) {
                            $message = "Order cancelled successfully.";
                        } else {
                            $message = "Error cancelling order: " . $conn->error;
                        }
                        $update_stmt->close();
                    } else {
                        $message = "Error preparing update statement: " . $conn->error;
                    }
                } else {
                    $message = "Error: This order cannot be cancelled.";
                }
            } else {
                $message = "Order not found or you don't have permission to cancel it.";
            }
            $verify_stmt->close();
        } else {
            $message = "Error preparing verification statement: " . $conn->error;
        }
        
        header("Location: my_product_orders.php" . ($message ? "?msg=" . urlencode($message) : ""));
        exit;
    }
}

// Display message from redirect
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// Filter by status
$status_filter = $_GET['status'] ?? 'all';

$where_clause = "WHERE po.buyer_id = ?";
$params = [$farmer_id];
$param_types = 'i';

if ($status_filter !== 'all') {
    $where_clause .= " AND po.Status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

// Fetch orders YOU PLACED (as buyer) from other sellers WITH PRODUCT IMAGES
$orders = [];
try {
    $query = "SELECT po.Order_id, po.Product_id, po.quantity, po.total_price, po.Status, po.order_date,
                     p.Name as product_name, p.Price, p.Unit, p.Description,
                     seller.Name as seller_name, seller.Phone as seller_phone, seller.Email as seller_email,
                     (SELECT i.image_url FROM images i WHERE i.image_type = 'P' AND i.ID = p.product_id LIMIT 1) as image_url
              FROM product_orders po
              JOIN product p ON po.Product_id = p.product_id
              JOIN users seller ON p.seller_id = seller.user_id
              $where_clause
              ORDER BY po.order_date DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        die("SQL Prepare Error: " . $conn->error . "<br>Query: " . $query);
    }
    
    $stmt->bind_param($param_types, ...$params);
    
    if (!$stmt->execute()) {
        die("SQL Execute Error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        die("SQL Result Error: " . $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Calculate statistics
$total_orders = count($orders);
$pending_orders = count(array_filter($orders, fn($o) => $o['Status'] === 'PEN'));
$confirmed_orders = count(array_filter($orders, fn($o) => $o['Status'] === 'CON'));
$completed_orders = count(array_filter($orders, fn($o) => $o['Status'] === 'COM'));
$cancelled_orders = count(array_filter($orders, fn($o) => $o['Status'] === 'CAN'));
$total_spent = array_sum(array_map(fn($o) => ($o['Status'] === 'CON' || $o['Status'] === 'COM') ? $o['total_price'] : 0, $orders));

require 'fheader.php';
require 'farmer_nav.php';
?>

<link rel="stylesheet" href="../admin.css">

<div class="main-content">
    <h1>My Product Orders Placed</h1>
    <p style="color: #666; margin-bottom: 30px;">View and manage orders you placed to other farmers (sellers)</p>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="cards" style="margin-bottom: 30px;">
        <div class="card">
            <h3>Total Orders Placed</h3>
            <div class="count"><?= $total_orders ?></div>
        </div>
        <div class="card">
            <h3>Pending Orders</h3>
            <div class="count"><?= $pending_orders ?></div>
        </div>
        <div class="card">
            <h3>Confirmed Orders</h3>
            <div class="count"><?= $confirmed_orders ?></div>
        </div>
        <div class="card">
            <h3>Completed Orders</h3>
            <div class="count"><?= $completed_orders ?></div>
        </div>
        <div class="card">
            <h3>Cancelled Orders</h3>
            <div class="count"><?= $cancelled_orders ?></div>
        </div>
        <div class="card">
            <h3>Total Spent</h3>
            <div class="count">₹<?= number_format($total_spent, 0) ?></div>
        </div>
    </div>

    <!-- Filter and Actions -->
    <div class="quick-actions" style="margin-bottom: 20px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
        <form method="GET" style="display: inline-block;">
            <select name="status" onchange="this.form.submit()" style="padding: 10px; border-radius: 4px; border: 1px solid #ddd; background: white;">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Orders</option>
                <option value="PEN" <?= $status_filter === 'PEN' ? 'selected' : '' ?>>Pending</option>
                <option value="CON" <?= $status_filter === 'CON' ? 'selected' : '' ?>>Confirmed</option>
                <option value="COM" <?= $status_filter === 'COM' ? 'selected' : '' ?>>Completed</option>
                <option value="CAN" <?= $status_filter === 'CAN' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </form>

        <!-- Live Search Box -->
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="text" id="orderSearch" placeholder="Search orders..." style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 300px;">
            <button type="button" id="clearSearch" style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer;">Clear</button>
        </div>

        <span style="color: #666; font-size: 14px;">Showing <?= count($orders) ?> orders</span>
    </div>

    <!-- Orders Table -->
    <?php if (count($orders) > 0): ?>
        <table id="ordersTable">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Seller</th>
                    <th>Contact</th>
                    <th>Quantity</th>
                    <th>Price per Unit</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $status_map = [
                        'CON' => ['status-confirmed', 'Confirmed'],
                        'PEN' => ['status-pending', 'Pending'],
                        'COM' => ['status-completed', 'Completed'],
                        'CAN' => ['status-cancelled', 'Cancelled']
                    ];
                    list($status_class, $status_text) = $status_map[$order['Status']] ?? ['', 'Unknown'];
                    ?>
                    <tr class="order-row">
                        <td>
                            <strong style="color: #234a23; font-size: 16px;"><?= htmlspecialchars($order['product_name']) ?></strong><br>
                        </td>
                        <td><?= htmlspecialchars($order['seller_name']) ?></td>
                        <td>
                            <?php if ($order['seller_phone']): ?>
                                <a href="tel:<?= $order['seller_phone'] ?>" style="color: #234a23; text-decoration: none;">
                                    <i class="fas fa-phone" style="margin-right: 5px;"></i><?= htmlspecialchars($order['seller_phone']) ?>
                                </a><br>
                            <?php endif; ?>
                            <?php if ($order['seller_email']): ?>
                                <a href="mailto:<?= $order['seller_email'] ?>" style="color: #234a23; text-decoration: none; font-size: 12px;">
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?= $order['quantity'] ?> <?= strtoupper($order['Unit'] ?? 'UNIT') ?></td>
                        <td>₹<?= number_format($order['Price'], 2) ?></td>
                        <td><strong style="color: #28a745;">₹<?= number_format($order['total_price'], 2) ?></strong></td>
                        <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                        <td style="white-space: nowrap;">
                            <!-- Details button -->
                            <button type="button" onclick="viewOrderDetails(<?= htmlspecialchars(json_encode($order)) ?>)" 
                                    style="color: white; font-weight: 600; background: #234a23; border: none; cursor: pointer; padding: 5px; margin-right: 10px;">
                                <i class="fas fa-eye"></i> View
                            </button>
                            
                            <!-- Cancel button for pending and confirmed orders -->
                            <?php if ($order['Status'] === 'PEN' || $order['Status'] === 'CON'): ?>
                                <a href="?action=cancel&id=<?= $order['Order_id'] ?>&status=<?= $status_filter ?>" 
                                   onclick="return confirm('Are you sure you want to cancel this order?')"
                                   style="color: #dc3545; font-weight: 600; text-decoration: none; padding: 5px;">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="form-section" style="text-align: center; padding: 50px; background: white; border-radius: 8px;">
            <h3 style="color: #666; margin-bottom: 15px;">
                <?= $status_filter !== 'all' ? 'No orders found with status "' . strtoupper($status_filter) . '"' : 'No Product Orders Yet' ?>
            </h3>
            <p style="color: #666; margin-bottom: 25px;">
                <?= $status_filter !== 'all' ? 'Try changing the filter.' : 'When you place orders, they will appear here.' ?>
            </p>
            <a href="../products.php" class="action-btn">
                <i class="fas fa-apple-alt"></i> Browse Products
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Order Details Modal WITH PRODUCT IMAGE -->
<div id="orderDetailsModal" class="modal" style="display: none;">
    <div class="modal-content-large">
        <span class="close" onclick="closeOrderModal()">&times;</span>
        <h2 id="modalTitle">Order Details - As Buyer</h2>
        
        <div class="modal-body">
            <!-- Product Image Section -->
            <div class="product-image-section">
                <h3>Product Image</h3>
                <div id="productImageContainer">
                    <!-- Image will be inserted here -->
                </div>
            </div>

            <!-- Order Details Section -->
            <div class="order-details-section">
                <h3>Order Information</h3>
                <div class="details-grid">
                    <div class="detail-row">
                        <strong>Order ID:</strong>
                        <span id="detailOrderId"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Product Name:</strong>
                        <span id="detailProductName"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Product Description:</strong>
                        <span id="detailDescription"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Quantity Ordered:</strong>
                        <span id="detailQuantity"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Order Date:</strong>
                        <span id="detailOrderDate"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Status:</strong>
                        <span id="detailStatus"></span>
                    </div>
                </div>
            </div>

            <!-- Pricing Details Section -->
            <div class="pricing-details-section">
                <h3>Pricing Information</h3>
                <div class="details-grid">
                    <div class="detail-row">
                        <strong>Price per Unit:</strong>
                        <span id="detailPrice"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Total Amount:</strong>
                        <span id="detailTotalAmount"></span>
                    </div>
                </div>
            </div>

            <!-- Seller Details Section -->
            <div class="seller-details-section">
                <h3>Seller Information (Where You Bought From)</h3>
                <div class="details-grid">
                    <div class="detail-row">
                        <strong>Seller Name:</strong>
                        <span id="detailSellerName"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Phone Number:</strong>
                        <span id="detailSellerPhone"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Email:</strong>
                        <span id="detailSellerEmail"></span>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <div id="orderActions">
                    <!-- Action buttons will be inserted here based on status -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div id="imageViewerModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px; padding: 0; background: transparent; border: none; box-shadow: none;">
        <span class="close" onclick="closeImageViewer()" style="position: absolute; top: 10px; right: 25px; color: white; font-size: 40px; z-index: 1001;">&times;</span>
        <div style="text-align: center;">
            <img id="fullscreenImage" style="max-width: 100%; max-height: 90vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <p id="imageCaption" style="color: white; margin-top: 15px; font-size: 18px; font-weight: 600;"></p>
        </div>
    </div>
</div>

<style>
/* Live search styling */
.order-row.hidden {
    display: none !important;
}

#orderSearch:focus {
    outline: none;
    border-color: #234a23;
}

#clearSearch:hover {
    background: #f5f5f5;
    color: #234a23;
}

/* Status badge styling */
.status-completed {
    background-color: #234a23;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-cancelled {
    background-color: #6c757d;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-confirmed {
    background-color: #d4edda;
    color: #155724;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending {
    background-color: #fff3cd;
    color: #856404;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
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
}

.modal-body {
    padding: 20px;
}

.product-image-section,
.order-details-section,
.pricing-details-section,
.seller-details-section {
    margin-bottom: 30px;
}

.product-image-section h3,
.order-details-section h3,
.pricing-details-section h3,
.seller-details-section h3 {
    color: #234a23;
    margin-bottom: 15px;
    border-bottom: 2px solid #eee;
    padding-bottom: 5px;
}

#productImageContainer {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

#productImageContainer img {
    max-width: 100%;
    max-height: 300px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.2s ease;
}

#productImageContainer img:hover {
    transform: scale(1.02);
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
}

.detail-row span {
    flex: 1;
    margin-left: 10px;
}

.modal-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid #eee;
    text-align: center;
}

.modal-actions a {
    display: inline-block;
    margin: 0 5px;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 4px;
    font-weight: bold;
}

.btn-cancel {
    background: #dc3545;
    color: white;
}

.btn-cancel:hover {
    background: #dc4545;
    color: white;
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

/* Table styling */
table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 5px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

table thead {
    background-color: #234a23;
    color: white;
}

table th, table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

table tbody tr:hover {
    background-color: #f9f9f9;
}

/* Image viewer modal */
#imageViewerModal {
    background-color: rgba(0,0,0,0.9);
}

/* Responsive */
@media (max-width: 768px) {
    .quick-actions {
        flex-direction: column;
        align-items: stretch !important;
        gap: 15px !important;
    }
    
    #orderSearch {
        width: 100% !important;
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

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
$(document).ready(function() {
    // Live search functionality
    $('#orderSearch').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('#ordersTable tbody tr.order-row').each(function() {
            var rowText = $(this).text().toLowerCase();
            if (rowText.indexOf(searchTerm) === -1) {
                $(this).addClass('hidden');
            } else {
                $(this).removeClass('hidden');
            }
        });
    });

    // Clear search functionality
    $('#clearSearch').on('click', function() {
        $('#orderSearch').val('');
        $('#ordersTable tbody tr.order-row').removeClass('hidden');
        $('#orderSearch').focus();
    });
});

// View product image in fullscreen
function viewProductImage(imageUrl, productName) {
    document.getElementById('fullscreenImage').src = '../' + imageUrl;
    document.getElementById('imageCaption').textContent = productName;
    document.getElementById('imageViewerModal').style.display = 'block';
}

function closeImageViewer() {
    document.getElementById('imageViewerModal').style.display = 'none';
}

// View order details function WITH IMAGE
function viewOrderDetails(order) {
    document.getElementById('detailOrderId').textContent = order.Order_id;
    document.getElementById('detailProductName').textContent = order.product_name || 'N/A';
    document.getElementById('detailDescription').textContent = order.Description || 'No description available';
    document.getElementById('detailQuantity').textContent = order.quantity + ' ' + (order.Unit || 'UNIT');
    
    const orderDate = new Date(order.order_date);
    document.getElementById('detailOrderDate').textContent = orderDate.toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });

    // Handle product image in modal
    const imageContainer = document.getElementById('productImageContainer');
    if (order.image_url) {
        imageContainer.innerHTML = '<img src="../' + order.image_url + '" alt="Product Image" onclick="viewProductImage(\'' + order.image_url + '\', \'' + order.product_name + '\')" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); object-fit: cover; cursor: pointer; transition: transform 0.2s ease;" onmouseover="this.style.transform=\'scale(1.02)\'" onmouseout="this.style.transform=\'scale(1)\'">';
    } else {
        imageContainer.innerHTML = '<div style="padding: 50px; background: #f0f0f0; border-radius: 8px; color: #666;"><i class="fas fa-apple-alt" style="font-size: 48px; margin-bottom: 10px;"></i><br>No Product Image Available</div>';
    }

    // Set status with color
    const statusSpan = document.getElementById('detailStatus');
    const statusMap = {
        'PEN': { class: 'status-pending', text: 'Pending' },
        'CON': { class: 'status-confirmed', text: 'Confirmed' },
        'COM': { class: 'status-completed', text: 'Completed' },
        'CAN': { class: 'status-cancelled', text: 'Cancelled' }
    };
    const statusInfo = statusMap[order.Status] || { class: '', text: 'Unknown' };
    statusSpan.innerHTML = '<span class="status-badge ' + statusInfo.class + '">' + statusInfo.text + '</span>';

    // Populate pricing details
    document.getElementById('detailPrice').textContent = '₹' + parseFloat(order.Price || 0).toFixed(2);
    document.getElementById('detailTotalAmount').innerHTML = '<span style="color: #28a745; font-weight: bold; font-size: 18px;">₹' + parseFloat(order.total_price).toFixed(2) + '</span>';

    // Populate SELLER details (where you bought from)
    document.getElementById('detailSellerName').textContent = order.seller_name || 'N/A';
    document.getElementById('detailSellerPhone').innerHTML = order.seller_phone ? 
        '<a href="tel:' + order.seller_phone + '" style="color: #0d6efd; text-decoration: none;">' + order.seller_phone + '</a>' : 'N/A';
    document.getElementById('detailSellerEmail').innerHTML = order.seller_email ? 
        '<a href="mailto:' + order.seller_email + '" style="color: #0d6efd; text-decoration: none;">' + order.seller_email + '</a>' : 'N/A';

    // Handle action buttons based on status
    const actionsContainer = document.getElementById('orderActions');
    let actionsHTML = '';
    
    const currentStatus = new URLSearchParams(window.location.search).get('status') || 'all';
    
    if (order.Status === 'PEN' || order.Status === 'CON') {
        actionsHTML += '<a href="?action=cancel&id=' + order.Order_id + '&status=' + currentStatus + '" class="btn-cancel" onclick="return confirm(\'Are you sure you want to cancel this order?\')">Cancel Order</a>';
    }
    
    actionsContainer.innerHTML = actionsHTML;

    // Show modal
    document.getElementById('orderDetailsModal').style.display = 'block';
}

function closeOrderModal() {
    document.getElementById('orderDetailsModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const detailsModal = document.getElementById('orderDetailsModal');
    const imageModal = document.getElementById('imageViewerModal');
    
    if (event.target === detailsModal) {
        detailsModal.style.display = 'none';
    }
    if (event.target === imageModal) {
        imageModal.style.display = 'none';
    }
}

// Keyboard navigation
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const detailsModal = document.getElementById('orderDetailsModal');
        const imageModal = document.getElementById('imageViewerModal');
        
        if (detailsModal.style.display === 'block') {
            closeOrderModal();
        }
        if (imageModal.style.display === 'block') {
            closeImageViewer();
        }
    }
});
</script>

<script>
// Auto hide message after 5 seconds
$(document).ready(function() {
    if ($('.message').length > 0) {
        setTimeout(function() {
            $('.message').fadeOut(1000, function() {
                $(this).remove();
            });
        }, 5000);
    }
});
</script>

<?php require 'ffooter.php'; ?>
