<?php
session_start();
require_once('../auth/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'F') {
    header("Location: ../login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Fetch summary counts and totals
$total_products = $conn->query("SELECT COUNT(*) as c FROM product WHERE seller_id = $farmer_id")->fetch_assoc()['c'];
$total_bookings = $conn->query("SELECT COUNT(*) as c FROM equipment_bookings WHERE customer_id = $farmer_id")->fetch_assoc()['c'];
$product_orders = $conn->query("SELECT COUNT(*) as c FROM product_orders po 
                               JOIN product p ON po.Product_id = p.product_id 
                               WHERE p.seller_id = $farmer_id")->fetch_assoc()['c'];
$total_earnings = $conn->query("SELECT COALESCE(SUM(po.total_price), 0) as earnings 
                               FROM product_orders po 
                               JOIN product p ON po.Product_id = p.product_id 
                               WHERE p.seller_id = $farmer_id AND po.Status = 'CON'")->fetch_assoc()['earnings'];

// SECTION 1: Fetch recent product orders PLACED BY THIS FARMER (as buyer)
$recent_product_orders_query = $conn->query("SELECT po.Order_id, po.quantity, po.total_price, po.Status, po.order_date,
                                              p.Name as product_name, u.Name as seller_name
                                      FROM product_orders po
                                      JOIN product p ON po.Product_id = p.product_id
                                      JOIN users u ON p.seller_id = u.user_id
                                      WHERE po.buyer_id = $farmer_id
                                      ORDER BY po.order_date DESC
                                      LIMIT 5");

if (!$recent_product_orders_query) {
    die("Error in recent product orders query: " . $conn->error);
}
$recent_product_orders = $recent_product_orders_query;

// SECTION 2: Fetch recent equipment bookings
$recent_bookings_query = $conn->query("SELECT eb.booking_id, eb.start_date, eb.end_date, eb.total_amount, eb.status,
                                        e.Title as equipment_title, u.Name as owner_name
                                FROM equipment_bookings eb
                                JOIN equipment e ON eb.equipment_id = e.Equipment_id
                                JOIN users u ON e.Owner_id = u.user_id
                                WHERE eb.customer_id = $farmer_id
                                ORDER BY eb.booking_id DESC
                                LIMIT 5");

if (!$recent_bookings_query) {
    die("Error in recent bookings query: " . $conn->error);
}
$recent_bookings = $recent_bookings_query;

// SECTION 3: Fetch recent MY PRODUCTS ORDERS (orders received for YOUR products from other buyers)
$recent_my_products_orders_query = $conn->query("SELECT po.Order_id, po.quantity, po.total_price, po.Status, po.order_date,
                                                  p.Name as product_name, u.Name as buyer_name
                                          FROM product_orders po
                                          JOIN product p ON po.Product_id = p.product_id
                                          JOIN users u ON po.buyer_id = u.user_id
                                          WHERE p.seller_id = $farmer_id
                                          ORDER BY po.order_date DESC
                                          LIMIT 5");

if (!$recent_my_products_orders_query) {
    die("Error in recent my products orders query: " . $conn->error);
}
$recent_my_products_orders = $recent_my_products_orders_query;

require 'fheader.php';
require 'farmer_nav.php';
?>

<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.view-btn {
    background-color: #234a23;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.view-btn:hover {
    background-color: #1a3a1a;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-confirmed {
    background-color: #d4edda;
    color: #155724;
}

.status-pending {
    background-color: #fff3cd;
    color: #856404;
}

.status-cancelled {
    background-color: #f8d7da;
    color: #721c24;
}

.status-rejected {
    background-color: #f8d7da;
    color: #721c24;
}
</style>

<div class="main-content">
    <h1>Farmer Dashboard</h1>
    <h2>Welcome <?= htmlspecialchars($_SESSION['user_name']); ?></h2>

    <div class="cards">
        <?php if ($total_products > 0): ?>
        <a href="manage_products.php" class="card-link">
            <div class="card">
                <h3>My Products</h3>
                <div class="count"><?= $total_products; ?></div>
            </div>
        </a>
        <?php endif; ?>

        <?php if ($total_bookings > 0): ?>
        <a href="equipment_bookings.php" class="card-link">
            <div class="card">
                <h3>Equipment Bookings</h3>
                <div class="count"><?= $total_bookings; ?></div>
            </div>
        </a>
        <?php endif; ?>

        <?php if ($product_orders > 0): ?>
        <a href="product_orders.php" class="card-link">
            <div class="card">
                <h3>Product Orders</h3>
                <div class="count"><?= $product_orders; ?></div>
            </div>
        </a>
        <?php endif; ?>

        <?php if ($total_earnings > 0): ?>
        <div class="card">
            <h3>Total Earnings</h3>
            <div class="count">₹<?= number_format($total_earnings, 2); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <br><br>

    <!-- SECTION 1: Recent Product Orders (Orders YOU placed as a buyer) -->
    <h2>Recent Product Orders</h2>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Seller</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>Order Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($recent_product_orders && $recent_product_orders->num_rows > 0): ?>
                <?php while ($order = $recent_product_orders->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['product_name']); ?></td>
                        <td><?= htmlspecialchars($order['seller_name']); ?></td>
                        <td><?= $order['quantity']; ?></td>
                        <td>₹<?= number_format($order['total_price'], 2); ?></td>
                        <td>
                            <?php 
                            $status_text = '';
                            $status_class = '';
                            switch ($order['Status']) {
                                case 'CON':
                                    $status_text = 'Confirmed';
                                    $status_class = 'status-confirmed';
                                    break;
                                case 'PEN':
                                    $status_text = 'Pending';
                                    $status_class = 'status-pending';
                                    break;
                                case 'CAN':
                                    $status_text = 'Cancelled';
                                    $status_class = 'status-cancelled';
                                    break;
                            }
                            ?>
                            <span class="status-badge <?= $status_class; ?>"><?= $status_text; ?></span>
                        </td>
                        <td><?= date('M j, Y', strtotime($order['order_date'])); ?></td>
                        <td>
                            <a href="my_product_orders.php?order_id=<?= $order['Order_id']; ?>" class="view-btn">View</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; color:#666;">No product orders placed yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <br><br>

    <!-- SECTION 2: Recent My Equipment Bookings (Equipment YOU booked) -->
    <h2>Recent My Equipment Bookings</h2>
    <table>
        <thead>
            <tr>
                <th>Equipment</th>
                <th>Owner</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($booking['equipment_title']); ?></td>
                        <td><?= htmlspecialchars($booking['owner_name']); ?></td>
                        <td><?= date('M j, Y', strtotime($booking['start_date'])); ?></td>
                        <td><?= date('M j, Y', strtotime($booking['end_date'])); ?></td>
                        <td>₹<?= number_format($booking['total_amount'], 2); ?></td>
                        <td>
                            <?php 
                            $status_text = '';
                            $status_class = '';
                            switch ($booking['status']) {
                                case 'CON':
                                    $status_text = 'Confirmed';
                                    $status_class = 'status-confirmed';
                                    break;
                                case 'PEN':
                                    $status_text = 'Pending';
                                    $status_class = 'status-pending';
                                    break;
                                case 'REJ':
                                    $status_text = 'Rejected';
                                    $status_class = 'status-rejected';
                                    break;
                            }
                            ?>
                            <span class="status-badge <?= $status_class; ?>"><?= $status_text; ?></span>
                        </td>
                        <td>
                            <a href="equipment_bookings.php?booking_id=<?= $booking['booking_id']; ?>" class="view-btn">View</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; color:#666;">No equipment bookings yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <br><br>

    <!-- SECTION 3: Recent My Products Order (Orders received FOR YOUR products from other farmers) -->
    <h2>Recent My Products Order</h2>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Buyer</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>Order Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($recent_my_products_orders && $recent_my_products_orders->num_rows > 0): ?>
                <?php while ($order = $recent_my_products_orders->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['product_name']); ?></td>
                        <td><?= htmlspecialchars($order['buyer_name']); ?></td>
                        <td><?= $order['quantity']; ?></td>
                        <td>₹<?= number_format($order['total_price'], 2); ?></td>
                        <td>
                            <?php 
                            $status_text = '';
                            $status_class = '';
                            switch ($order['Status']) {
                                case 'CON':
                                    $status_text = 'Confirmed';
                                    $status_class = 'status-confirmed';
                                    break;
                                case 'PEN':
                                    $status_text = 'Pending';
                                    $status_class = 'status-pending';
                                    break;
                                case 'CAN':
                                    $status_text = 'Cancelled';
                                    $status_class = 'status-cancelled';
                                    break;
                            }
                            ?>
                            <span class="status-badge <?= $status_class; ?>"><?= $status_text; ?></span>
                        </td>
                        <td><?= date('M j, Y', strtotime($order['order_date'])); ?></td>
                        <td>
                            <a href="product_orders.php?order_id=<?= $order['Order_id']; ?>" class="view-btn">View</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; color:#666;">No product orders received yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require 'ffooter.php'; ?>
