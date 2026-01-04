<?php 
session_start(); 

// Enable mysqli error reporting for better debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once 'auth/config.php';

// Check database connection
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Fetch 4 recent approved equipment with images
$equipment_query = "SELECT e.*, u.Name as owner_name, i.image_url, es.Subcategory_name 
                   FROM equipment e 
                   JOIN users u ON e.Owner_id = u.user_id 
                   LEFT JOIN images i ON (i.image_type = 'E' AND i.ID = e.Equipment_id)
                   LEFT JOIN equipment_subcategories es ON e.Subcategories_id = es.Subcategory_id
                   WHERE e.Approval_status = 'CON' 
                   ORDER BY e.listed_date DESC 
                   LIMIT 4";

try {
    $equipment_result = $conn->query($equipment_query);
    $featured_equipment = [];
    if ($equipment_result && $equipment_result->num_rows > 0) {
        while ($equip = $equipment_result->fetch_assoc()) {
            $featured_equipment[] = $equip;
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Equipment query error: " . $e->getMessage());
    $featured_equipment = [];
}

// Products query matching your equipment query structure
$products_query = "SELECT p.*, u.Name as seller_name, i.image_url, 
                   ps.Subcategory_name, pc.Category_name
                   FROM product p 
                   JOIN users u ON p.seller_id = u.user_id 
                   LEFT JOIN images i ON (i.image_type = 'P' AND i.ID = p.product_id)
                   LEFT JOIN product_subcategories ps ON p.Subcategory_id = ps.Subcategory_id
                   LEFT JOIN product_categories pc ON ps.Category_id = pc.Category_id
                   WHERE p.Approval_status = 'CON' 
                   ORDER BY p.listed_date DESC 
                   LIMIT 4";

// Initialize featured_products as empty array
$featured_products = [];

try {
    $products_result = $conn->query($products_query);
    if ($products_result && $products_result->num_rows > 0) {
        while ($product = $products_result->fetch_assoc()) {
            $featured_products[] = $product;
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Products query error: " . $e->getMessage());
    // featured_products remains as empty array
}

// Function to calculate available quantity for each product
function getAvailableQuantity($product_id, $conn) {
    $available_quantity = 0;
    
    try {
        // Get total quantity from product table
        $product_query = "SELECT Quantity FROM product WHERE product_id = ?";
        $stmt = $conn->prepare($product_query);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $product_result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$product_result) {
            return 0;
        }
        
        $total_quantity = $product_result['Quantity'];
        
        // Get total ordered quantity with status = 'CON'
        $ordered_query = "SELECT SUM(quantity) as total_ordered FROM product_orders 
                         WHERE Product_id = ? AND Status = 'CON'";
        $ordered_stmt = $conn->prepare($ordered_query);
        if (!$ordered_stmt) {
            return $total_quantity;
        }
        $ordered_stmt->bind_param('i', $product_id);
        $ordered_stmt->execute();
        $ordered_result = $ordered_stmt->get_result()->fetch_assoc();
        $ordered_stmt->close();
        
        $total_ordered = $ordered_result['total_ordered'] ?? 0;
        $available_quantity = $total_quantity - $total_ordered;
        
        return max(0, $available_quantity);
        
    } catch (Exception $e) {
        error_log("Error calculating available quantity: " . $e->getMessage());
        return 0;
    }
}

include 'includes/header.php';
include 'includes/navigation.php';
include 'includes/header-section.php';

?>

<script>
// Force reload if page comes from bfcache
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>

<!-- Equipment Section -->
<div class="equipment-section">
    <div class="container">
        <h2>Featured Equipment</h2>
        <p style="text-align: center; color: #666;">Recently approved farming equipment available for rent</p>
        
        <div class="equipment-row">
            <?php if (count($featured_equipment) > 0): ?>
                <?php foreach ($featured_equipment as $equipment): ?>
                    <div class="equipment-card">
                        <div class="equipment-image">
                            <?php if (!empty($equipment['image_url'])): ?>
                                <img src="<?= htmlspecialchars($equipment['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($equipment['Title']) ?>"
                                     style="width: 100%; height: 210px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 180px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); display: flex; align-items: center; justify-content: center; color: #666; font-size: 48px;">
                                    🚜
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="equipment-info">
                            <h3><?= htmlspecialchars($equipment['Title']) ?></h3>
                            <p><strong>Brand:</strong> <?= htmlspecialchars($equipment['Brand']) ?></p>
                            <p><strong>Model:</strong> <?= htmlspecialchars($equipment['Model']) ?></p>
                            <?php if ($equipment['Year']): ?>
                                <p><strong>Year:</strong> <?= htmlspecialchars($equipment['Year']) ?></p>
                            <?php endif; ?>
                            <p><strong>Category:</strong> <?= htmlspecialchars($equipment['Subcategory_name'] ?? 'N/A') ?></p>
                            <p><strong>Owner:</strong> <?= htmlspecialchars($equipment['owner_name']) ?></p>
                            <div class="price-box">
                                <?php if ($equipment['Daily_rate'] > 0): ?>
                                    <span class="price">₹<?= number_format($equipment['Daily_rate'], 0) ?>/day</span>
                                <?php endif; ?>
                                <?php if ($equipment['Hourly_rate'] > 0): ?>
                                    <small>₹<?= number_format($equipment['Hourly_rate'], 0) ?>/hour</small>
                                <?php endif; ?>
                            </div>
                            <a class="rent-btn"
                               href="<?= isset($_SESSION['logged_in']) && $_SESSION['logged_in'] ? 'equipment_details.php?id=' . $equipment['Equipment_id'] : 'login.php' ?>">
                               Rent Now
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px; width: 100%;">
                    <h3 style="color: #666; margin-bottom: 15px;">🚜 No Equipment Available</h3>
                    <p style="color: #666; margin-bottom: 25px;">Equipment owners can list their farming equipment for rent.</p>
                    <a href="<?= isset($_SESSION['logged_in']) && $_SESSION['user_type'] == 'O' ? 'owner/add_equipment.php' : 'register.php' ?>" 
                       style="background: #234a23; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">
                        <?= isset($_SESSION['logged_in']) && $_SESSION['user_type'] == 'O' ? 'List Your Equipment' : 'Join as Equipment Owner' ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="equipments.php" class="view-all-btn">View All Equipment</a>
        </div>
    </div>
</div>

<!-- Products Section -->
<div class="products-section">
    <div class="container">
        <h2>Farm Supplies & Products</h2>
        <p style="text-align: center; color: #666;">Quality agricultural products and supplies</p>
        
        <div class="products-row">
            <?php if (count($featured_products) > 0): ?>
                <?php foreach ($featured_products as $product): ?>
                    <?php 
                    // Calculate available quantity for each product
                    $available_qty = getAvailableQuantity($product['product_id'], $conn);
                    $is_out_of_stock = $available_qty <= 0;
                    ?>
                    <div class="equipment-card">
                        <div class="equipment-image" style="position: relative;">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($product['Name']) ?>"
                                     style="width: 100%; height: 210px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 180px; background: linear-gradient(135deg, #e8f5e8 0%, #a8e6a8 100%); display: flex; align-items: center; justify-content: center; color: #666; font-size: 48px;">
                                    📦
                                </div>
                            <?php endif; ?>
                            
                            <!-- Out of Stock Badge -->
                            <?php if ($is_out_of_stock): ?>
                                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; font-weight: bold; border-radius: 4px 4px 0 0;">
                                    Out of Stock
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="equipment-info">
                            <h3><?= htmlspecialchars($product['Name']) ?></h3>
                            <p><strong>Category:</strong> <?= htmlspecialchars($product['Category_name'] ?? 'N/A') ?></p>
                            <p><strong>Type:</strong> <?= htmlspecialchars($product['Subcategory_name'] ?? 'N/A') ?></p>
                            <p><strong>Seller:</strong> <?= htmlspecialchars($product['seller_name']) ?></p>
                            <p><strong>Available:</strong> 
                                <span style="color: <?= $is_out_of_stock ? '#dc3545' : '#7cb342' ?>; font-weight: bold;">
                                    <?= number_format($available_qty, 1) ?> <?= strtoupper($product['Unit']) ?>
                                </span>
                            </p>
                            <div class="price-box">
                                <span class="price">₹<?= number_format($product['Price'], 2) ?>/<?= strtoupper($product['Unit']) ?></span>
                            </div>
                            <a class="rent-btn"
                               style="background: <?= $is_out_of_stock ? '#ccc' : '#7cb342' ?>; color: <?= $is_out_of_stock ? '#999' : 'white' ?>; cursor: <?= $is_out_of_stock ? 'not-allowed' : 'pointer' ?>; opacity: <?= $is_out_of_stock ? '0.6' : '1' ?>;"
                               href="<?= !$is_out_of_stock && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] ? 'product_details.php?id=' . $product['product_id'] : ($is_out_of_stock ? '#' : 'login.php') ?>"
                               onclick="<?= $is_out_of_stock ? 'event.preventDefault(); alert(\'This product is currently out of stock.\');' : '' ?>">
                               <?= $is_out_of_stock ? '🔒 Out of Stock' : 'Buy Now' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px; width: 100%;">
                    <h3 style="color: #666; margin-bottom: 15px;">📦 No Products Available</h3>
                    <p style="color: #666; margin-bottom: 25px;">Sellers can list their agricultural products here.</p>
                    <a href="<?= isset($_SESSION['logged_in']) && $_SESSION['user_type'] == 'S' ? 'seller/add_product.php' : 'register.php' ?>" 
                       style="background: #234a23; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">
                        <?= isset($_SESSION['logged_in']) && $_SESSION['user_type'] == 'S' ? 'List Your Products' : 'Join as Seller' ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="products.php" class="view-all-btn">View All Products</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
