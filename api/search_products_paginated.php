<?php
session_start();
header('Content-Type: application/json');

require_once '../auth/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $limit = 12;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause
    $where_clause = "WHERE p.Approval_status = 'CON'";
    $params = [];
    $param_types = "";

    if (!empty($search)) {
        $where_clause .= " AND (p.Name LIKE ? OR p.Description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $param_types .= "ss";
    }

    if ($category > 0) {
        $where_clause .= " AND ps.Category_id = ?";
        $params[] = $category;
        $param_types .= "i";
    }

    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM product p 
                    LEFT JOIN product_subcategories ps ON p.Subcategory_id = ps.Subcategory_id
                    LEFT JOIN product_categories pc ON ps.Category_id = pc.Category_id
                    $where_clause";

    $count_stmt = $conn->prepare($count_query);
    if ($count_stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if (!empty($param_types)) {
        if (!$count_stmt->bind_param($param_types, ...$params)) {
            throw new Exception("Bind param failed: " . $count_stmt->error);
        }
    }
    
    if (!$count_stmt->execute()) {
        throw new Exception("Execute failed: " . $count_stmt->error);
    }
    
    $total_count = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    $total_pages = max(1, ceil($total_count / $limit));

    // Get products
    $products_query = "SELECT p.product_id, p.Name, p.Price, p.Unit, p.Quantity, 
                              u.Name as seller_name, 
                              i.image_url, ps.Subcategory_name, pc.Category_name
                       FROM product p 
                       JOIN users u ON p.seller_id = u.user_id 
                       LEFT JOIN images i ON (i.image_type = 'P' AND i.ID = p.product_id)
                       LEFT JOIN product_subcategories ps ON p.Subcategory_id = ps.Subcategory_id
                       LEFT JOIN product_categories pc ON ps.Category_id = pc.Category_id
                       $where_clause 
                       ORDER BY p.listed_date DESC 
                       LIMIT ? OFFSET ?";

    $product_params = $params;
    $product_params[] = $limit;
    $product_params[] = $offset;
    $product_types = $param_types . "ii";

    $stmt = $conn->prepare($products_query);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if (!empty($product_types)) {
        if (!$stmt->bind_param($product_types, ...$product_params)) {
            throw new Exception("Bind param failed: " . $stmt->error);
        }
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $products_result = $stmt->get_result();
    $products_list = [];

    while ($product = $products_result->fetch_assoc()) {
        // Calculate available quantity
        $product_id = $product['product_id'];
        $ordered_query = "SELECT SUM(quantity) as total_ordered FROM product_orders 
                         WHERE Product_id = ? AND Status = 'CON'";
        $ordered_stmt = $conn->prepare($ordered_query);
        
        if ($ordered_stmt === false) {
            $available_quantity = $product['Quantity'];
        } else {
            $ordered_stmt->bind_param('i', $product_id);
            $ordered_stmt->execute();
            $ordered_result = $ordered_stmt->get_result()->fetch_assoc();
            $ordered_stmt->close();
            
            $total_ordered = $ordered_result['total_ordered'] ?? 0;
            $available_quantity = $product['Quantity'] - $total_ordered;
            $available_quantity = max(0, $available_quantity);
        }
        
        $product['available_quantity'] = (float)$available_quantity;
        $product['is_out_of_stock'] = $available_quantity <= 0;
        
        $products_list[] = $product;
    }
    $stmt->close();

    $message = '';
    if ($total_count > 0) {
        $message = "Found " . $total_count . " product(s)";
    } else {
        $message = "No products found";
    }

    echo json_encode([
        'results' => $products_list,
        'total_count' => (int)$total_count,
        'current_page' => (int)$page,
        'total_pages' => (int)$total_pages,
        'count' => count($products_list),
        'message' => $message,
        'success' => true
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'success' => false
    ]);
}
exit();
?>
