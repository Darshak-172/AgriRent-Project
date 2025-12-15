<?php
// Disable error display and log errors instead
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Ensure we output JSON
ob_start();

try {
    require_once '../auth/config.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Method not allowed");
    }

    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $limit = 12;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause
    $where_clause = "WHERE e.Approval_status = 'CON'";
    $params = [];
    $param_types = "";

    if (!empty($search)) {
        $where_clause .= " AND (e.Title LIKE ? OR e.Brand LIKE ? OR e.Model LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $param_types .= "sss";
    }

    if ($category > 0) {
        $where_clause .= " AND es.Category_id = ?";
        $params[] = $category;
        $param_types .= "i";
    }

    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM equipment e 
                    LEFT JOIN equipment_subcategories es ON e.Subcategories_id = es.Subcategory_id
                    LEFT JOIN equipment_categories ec ON es.Category_id = ec.category_id
                    $where_clause";

    $count_stmt = $conn->prepare($count_query);
    if ($count_stmt === false) {
        throw new Exception("Count Prepare Error: " . $conn->error);
    }
    
    if (!empty($param_types)) {
        $count_types = $param_types;
        if (!$count_stmt->bind_param($count_types, ...$params)) {
            throw new Exception("Count Bind Error: " . $count_stmt->error);
        }
    }
    
    if (!$count_stmt->execute()) {
        throw new Exception("Count Execute Error: " . $count_stmt->error);
    }
    
    $count_result = $count_stmt->get_result();
    if (!$count_result) {
        throw new Exception("Count Result Error: " . $count_stmt->error);
    }
    
    $count_row = $count_result->fetch_assoc();
    $total_count = $count_row['total'] ?? 0;
    $count_stmt->close();

    $total_pages = max(1, ceil($total_count / $limit));

    // Get equipment
    $equipment_query = "SELECT e.Equipment_id, e.Title, e.Brand, e.Model, e.Year, 
                               e.Daily_rate, e.Hourly_rate, u.Name as owner_name, 
                               i.image_url, es.Subcategory_name, ec.Name as category_name
                        FROM equipment e 
                        JOIN users u ON e.Owner_id = u.user_id 
                        LEFT JOIN images i ON (i.image_type = 'E' AND i.ID = e.Equipment_id)
                        LEFT JOIN equipment_subcategories es ON e.Subcategories_id = es.Subcategory_id
                        LEFT JOIN equipment_categories ec ON es.Category_id = ec.category_id
                        $where_clause 
                        ORDER BY e.listed_date DESC 
                        LIMIT ? OFFSET ?";

    $equipment_params = $params;
    $equipment_params[] = $limit;
    $equipment_params[] = $offset;
    $equipment_types = $param_types . "ii";

    $stmt = $conn->prepare($equipment_query);
    if ($stmt === false) {
        throw new Exception("Equipment Prepare Error: " . $conn->error);
    }
    
    if (!empty($equipment_types)) {
        if (!$stmt->bind_param($equipment_types, ...$equipment_params)) {
            throw new Exception("Equipment Bind Error: " . $stmt->error);
        }
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Equipment Execute Error: " . $stmt->error);
    }
    
    $equipment_result = $stmt->get_result();
    if (!$equipment_result) {
        throw new Exception("Equipment Result Error: " . $stmt->error);
    }
    
    $equipment_list = [];
    while ($equipment = $equipment_result->fetch_assoc()) {
        $equipment_list[] = $equipment;
    }
    $stmt->close();

    $message = '';
    if ($total_count > 0) {
        $message = "Found " . $total_count . " equipment";
    } else {
        $message = "No equipment found";
    }

    // Clear any output
    ob_end_clean();
    
    // Return JSON
    echo json_encode([
        'results' => $equipment_list,
        'total_count' => (int)$total_count,
        'current_page' => (int)$page,
        'total_pages' => (int)$total_pages,
        'count' => count($equipment_list),
        'message' => $message,
        'success' => true
    ]);

} catch (Exception $e) {
    // Clear any output
    ob_end_clean();
    
    // Return JSON error
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'success' => false,
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
exit();
?>
