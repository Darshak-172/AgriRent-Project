<?php
session_start();
require_once '../auth/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'A') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID not provided']);
    exit;
}

$user_id = intval($_GET['user_id']);

try {
    // Fetch user
    $userQuery = "SELECT * FROM users WHERE user_id = $user_id";
    $userResult = $conn->query($userQuery);
    
    if (!$userResult) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $user = $userResult->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $response = ['user' => $user];
    
    // Fetch Equipment (if Equipment Owner)
    if ($user['User_type'] == 'O') {
        $equipQuery = "SELECT * FROM equipment WHERE Owner_id = $user_id ORDER BY listed_date DESC";
        $equipResult = $conn->query($equipQuery);
        $response['equipment'] = $equipResult ? $equipResult->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $response['equipment'] = [];
    }
    
    // Fetch Products (if Farmer)
    if ($user['User_type'] == 'F') {
        $prodQuery = "SELECT * FROM product WHERE seller_id = $user_id ORDER BY listed_date DESC";
        $prodResult = $conn->query($prodQuery);
        $response['products'] = $prodResult ? $prodResult->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $response['products'] = [];
    }
    
    // Fetch Equipment Bookings (for Equipment Owner)
    if ($user['User_type'] == 'O') {
        $bookingQuery = "
            SELECT eb.*, e.Title as equipmentTitle, u.Name as customerName 
            FROM equipment_bookings eb
            JOIN equipment e ON eb.equipment_id = e.Equipment_id
            JOIN users u ON eb.customer_id = u.user_id
            WHERE e.Owner_id = $user_id
            ORDER BY eb.start_date DESC
        ";
        $bookingResult = $conn->query($bookingQuery);
        $response['equipmentBookings'] = $bookingResult ? $bookingResult->fetch_all(MYSQLI_ASSOC) : [];
        
        // Equipment Stats
        $statsQuery = "
            SELECT COALESCE(SUM(eb.total_amount), 0) as totalRevenue 
            FROM equipment_bookings eb
            JOIN equipment e ON eb.equipment_id = e.Equipment_id
            WHERE e.Owner_id = $user_id AND eb.status IN ('COM', 'CON')
        ";
        $statsResult = $conn->query($statsQuery);
        $response['equipmentStats'] = $statsResult ? $statsResult->fetch_assoc() : ['totalRevenue' => 0];
    } else {
        $response['equipmentBookings'] = [];
        $response['equipmentStats'] = ['totalRevenue' => 0];
    }
    
    // Fetch Product Orders (for Farmer)
    if ($user['User_type'] == 'F') {
        $orderQuery = "
            SELECT po.*, p.Name as productName, u.Name as buyerName
            FROM product_orders po
            JOIN product p ON po.Product_id = p.product_id
            JOIN users u ON po.buyer_id = u.user_id
            WHERE p.seller_id = $user_id
            ORDER BY po.order_date DESC
        ";
        $orderResult = $conn->query($orderQuery);
        $response['productOrders'] = $orderResult ? $orderResult->fetch_all(MYSQLI_ASSOC) : [];
        
        // Orders Stats
        $orderStatsQuery = "
            SELECT COALESCE(SUM(po.total_price), 0) as totalAmount 
            FROM product_orders po
            JOIN product p ON po.Product_id = p.product_id
            WHERE p.seller_id = $user_id AND po.Status IN ('COM', 'CON')
        ";
        $orderStatsResult = $conn->query($orderStatsQuery);
        $response['orderStats'] = $orderStatsResult ? $orderStatsResult->fetch_assoc() : ['totalAmount' => 0];
    } else {
        $response['productOrders'] = [];
        $response['orderStats'] = ['totalAmount' => 0];
    }
    
    // Fetch Subscriptions
    $subQuery = "
        SELECT us.*, sp.Plan_name, sp.price
        FROM user_subscriptions us
        JOIN subscription_plans sp ON us.plan_id = sp.plan_id
        WHERE us.user_id = $user_id
        ORDER BY us.start_date DESC
    ";
    $subResult = $conn->query($subQuery);
    $response['subscriptions'] = $subResult ? $subResult->fetch_all(MYSQLI_ASSOC) : [];
    
    // Fetch Reviews (for user's equipment or products)
    $reviewQuery = "
        SELECT r.*, u.Name as reviewerName
        FROM reviews r
        JOIN users u ON r.Reviewer_id = u.user_id
        WHERE (
            r.Review_type = 'E' AND r.ID IN (
                SELECT Equipment_id FROM equipment WHERE Owner_id = $user_id
            )
        ) OR (
            r.Review_type = 'P' AND r.ID IN (
                SELECT product_id FROM product WHERE seller_id = $user_id
            )
        )
        ORDER BY r.created_date DESC
    ";
    $reviewResult = $conn->query($reviewQuery);
    $response['reviews'] = $reviewResult ? $reviewResult->fetch_all(MYSQLI_ASSOC) : [];
    
    // Fetch Addresses
    $addrQuery = "SELECT * FROM user_addresses WHERE user_id = $user_id ORDER BY address_id DESC";
    $addrResult = $conn->query($addrQuery);
    $response['addresses'] = $addrResult ? $addrResult->fetch_all(MYSQLI_ASSOC) : [];
    
    echo json_encode(['success' => true, 'data' => $response]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
