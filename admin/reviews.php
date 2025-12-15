<?php
session_start();
require_once '../auth/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'A') {
    header('Location: ../login.php');
    exit;
}

$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['E', 'P']) ? $_GET['type'] : 'ALL';

$message = '';
$error = '';
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $review_id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM reviews WHERE Review_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $review_id);
        if ($stmt->execute()) {
            $message = "Review deleted successfully!";
        } else {
            $error = "Error deleting review: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "Error preparing delete statement: " . $conn->error;
    }
}

$where = '';
if ($type_filter === 'E') {
    $where = "WHERE r.Review_type = 'E'";
} elseif ($type_filter === 'P') {
    $where = "WHERE r.Review_type = 'P'";
}

$sql = "SELECT r.*, u.Name AS reviewer_name, u.Email AS reviewer_email, u.Phone AS reviewer_phone 
        FROM reviews r 
        JOIN users u ON r.Reviewer_id = u.user_id 
        $where 
        ORDER BY r.created_date DESC";
$res = $conn->query($sql);

$reviews = [];
while ($row = $res->fetch_assoc()) {
    if ($row['Review_type'] === 'E') {
        $item_query = "SELECT Title as item_name, Owner_id FROM equipment WHERE Equipment_id = " . intval($row['ID']);
        $item_res = $conn->query($item_query);
        $item = $item_res ? $item_res->fetch_assoc() : null;
        $row['item_name'] = isset($item['item_name']) ? $item['item_name'] : 'Unknown';
        $owner_name = 'Unknown';
        if ($item && isset($item['Owner_id'])) {
            $own_res = $conn->query("SELECT Name FROM users WHERE user_id = " . intval($item['Owner_id']));
            $own = $own_res ? $own_res->fetch_assoc() : null;
            $owner_name = isset($own['Name']) ? $own['Name'] : 'Unknown';
        }
        $row['owner_name'] = $owner_name;
    } else {
        $item_query = "SELECT Name as item_name, seller_id FROM product WHERE product_id = " . intval($row['ID']);
        $item_res = $conn->query($item_query);
        $item = $item_res ? $item_res->fetch_assoc() : null;
        $row['item_name'] = isset($item['item_name']) ? $item['item_name'] : 'Unknown';
        $owner_name = 'Unknown';
        if ($item && isset($item['seller_id'])) {
            $own_res = $conn->query("SELECT Name FROM users WHERE user_id = " . intval($item['seller_id']));
            $own = $own_res ? $own_res->fetch_assoc() : null;
            $owner_name = isset($own['Name']) ? $own['Name'] : 'Unknown';
        }
        $row['owner_name'] = $owner_name;
    }
    $reviews[] = $row;
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Admin Reviews</title>
        
        <style>
            body {
                background: #f7f9fb;
                font-family: "Segoe UI",sans-serif;
                flex-direction: column;
                margin: 0;
                padding: 0;
            }
            .admin-layout {
                display: flex;
                min-height: 100vh;
                background: #f7f9fb;
            }
            .sidebar {
                min-width: 245px;
                max-width: 265px;
                background: #234a23;
                color: #fff;
                padding: 0;
                border-right: 1px solid #e7e7e7;
                height: 100vh;
                z-index: 10;
            }
            .main-content {
                flex: 1;
                padding: 0;
                width: auto;
            }
            .page-inner {
                padding: 34px 40px;
            }
            .tabs {
                display: flex;
                gap: 1rem;
                margin-bottom: 20px;
            }
            .tab {
                padding: 8px 28px;
                border-radius: 8px 8px 0 0;
                background: #f5f7fa;
                color: #234a23;
                font-weight: bold;
                font-size: 1.09rem;
                border: none;
                outline: none;
                text-decoration: none;
                cursor: pointer;
                transition: background .15s;
            }
            .tab.active, .tab:hover {
                background: #234a23;
                color: #fff;
            }
            .search-box {
                margin-bottom: 18px;
                display: flex;
                gap: 10px;
            }
            .search-box input[type="text"] {
                padding: 9px 16px;
                border-radius: 6px;
                border: 1px solid #bfc7c6;
                width: 350px;
                font-size: 1rem;
                transition: border-color .2s;
                background: #fdfdfd;
            }
            .search-box input:focus {
                outline: none;
                border-color: #234a23;
            }
            .search-box .btn {
                background: #234a23;
                color: #fff;
                border: none;
                padding: 0 17px;
                border-radius: 5px;
                font-weight: 500;
                cursor: pointer;
                transition: background .2s;
            }
            .search-box .btn:hover {
                background: #1c3920;
            }
            .table-container {
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 2px 16px rgba(163,176,198,0.13);
                overflow: hidden;
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
            .type-label {
                
                border-radius: 6px;
                padding: 5px 18px;
                font-weight: bold;
                letter-spacing: .04em;
                font-size: 1.01rem;
                display: inline-block;
            }
            .type-label.product {
                color: #ffc107;
            }
            .type-label.equipment {
                color: #27ae60;
            }
            .btn-view {
                display: inline-block;
                padding: 10px 20px;
                background: #234a23;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                border: none;
                cursor: pointer;
                margin-right: 10px;
                font-size: 14px;
            }
            .btn-view:hover {
                background: #1c3920;
            }
            .delete-btn {
                background: transparent !important;
                color: red !important;
                border: none !important;
                padding: 6px 12px;
                margin: 2px;
                text-decoration: none;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .delete-btn:hover {
                background: transparent !important;
                color: darkred !important;
                border: none !important;
            }
            .message {
                margin: 16px 0;
                background: #effaf0;
                color: #138c28;
                padding: 11px 18px;
                border-radius: 6px;
                font-size: 1rem;
            }
            .message-error {
                margin: 16px 0;
                background: #f8d6da;
                color: #721c24;
                padding: 11px 18px;
                border-radius: 6px;
                font-size: 1rem;
            }
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
                max-width: 480px;
                max-height: 90vh;
                overflow-y: auto;
                position: relative;
            }
            .modal-header {
                background-color: #234a23;
                color: white;
                padding: 18px 24px;
                border-radius: 8px 8px 0 0;
                font-size: 1.5em;
                font-weight: bold;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .modal-body {
                padding: 24px 14px;
            }
            .close {
                color: white;
                font-size: 30px;
                cursor: pointer;
            }
            .close:hover {
                color: #ccc;
            }
            .review-details-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 0;
            }
            .review-details-table th, .review-details-table td {
                font-size: 1rem;
                padding: 9px 7px;
                border-bottom: 1px solid #e9ecef;
                text-align: left;
                vertical-align: top;
            }
            .review-details-table th {
                background: #f7f9fb;
                color: #234a23;
                width: 40%;
            }
            .review-details-table td {
                background: #fff;
                color: #222;
            }
        </style>
    </head>
    <body>
        
        <div class="admin-layout">
            <div class="sidebar">
                <?php require 'header.php';
                require 'admin_nav.php'; ?>
            </div>
            <div class="main-content">
                
                <div class="page-inner">
                    <h1>Reviews</h1>
                    <?php if ($message): ?>
                        <div class="message"><?= htmlspecialchars($message) ?></div>
                    <?php elseif ($error): ?>
                        <div class="message-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
                    <div class="tabs">
                        <a href="?type=ALL" class="tab <?= $type_filter === 'ALL' ? 'active' : '' ?>">All Reviews</a>
                        <a href="?type=E" class="tab <?= $type_filter === 'E' ? 'active' : '' ?>">Equipment</a>
                        <a href="?type=P" class="tab <?= $type_filter === 'P' ? 'active' : '' ?>">Product</a>
                    </div>
                    <div class="search-box">
                        <input type="text" id="liveSearch" placeholder="Search reviews...">
                        <button type="button" id="clearSearch" class="btn">Clear</button>
                    </div>
                    <div class="table-container">
                        <table id="reviewTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Reviewer</th>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Rating</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
<?php foreach ($reviews as $review): ?>
                                    <tr class="review-row">
                                        <td><?= $review['Review_id'] ?></td>
                                        <td><?= htmlspecialchars($review['reviewer_name']) ?></td>
                                        <td><?= htmlspecialchars($review['item_name']) ?></td>
                                        <td>
                                            <span class="type-label <?= $review['Review_type'] === 'E' ? 'equipment' : 'product' ?>">
    <?= $review['Review_type'] === 'E' ? 'Equipment' : 'Product' ?>
                                            </span>
                                        </td>
                                        <td><?= $review['Rating'] ?>/5</td>
                                        <td>
                                            <button class="btn-view" onclick="viewDetails(<?=
                                            htmlspecialchars(json_encode([
                                                'Review_id' => $review['Review_id'],
                                                'reviewer_name' => $review['reviewer_name'],
                                                'reviewer_email' => $review['reviewer_email'],
                                                'reviewer_phone' => $review['reviewer_phone'],
                                                'item_name' => $review['item_name'],
                                                'owner_name' => $review['owner_name'],
                                                'Review_type' => $review['Review_type'],
                                                'Rating' => $review['Rating'],
                                                'comment' => $review['comment'],
                                                'created_date' => $review['created_date']
                                                    ]), ENT_QUOTES, 'UTF-8')
                                            ?>)">View</button>
                                            <button class="delete-btn"
                                                    onclick="if (confirm('Delete this review?')) {
                                                        window.location = '?action=delete&id=<?= $review['Review_id'] ?>&type=<?= $type_filter ?>'
                                                    }">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
<?php if (count($reviews) === 0): ?>
                                    <tr><td colspan="6" style="text-align:center;">No reviews found for this filter.</td></tr>
<?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="modal" class="modal" style="display:none;">
            <div class="modal-content-large">
                <div class="modal-header">
                    Review Details
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <div class="modal-body" id="modalBody"></div>
            </div>
        </div>
        <script>
            document.getElementById('liveSearch').addEventListener('input', function () {
                var term = this.value.toLowerCase();
                document.querySelectorAll('.review-row').forEach(function (row) {
                    var text = row.innerText.toLowerCase();
                    row.style.display = text.indexOf(term) !== -1 ? '' : 'none';
                });
            });
            document.getElementById('clearSearch').onclick = function () {
                document.getElementById('liveSearch').value = '';
                document.querySelectorAll('.review-row').forEach(function (row) {
                    row.style.display = '';
                });
            };
            function viewDetails(review) {
                const container = document.getElementById('modalBody');
                container.innerHTML =
                        `<table class="review-details-table">
                    <tr><th>Review ID</th><td>${review.Review_id}</td></tr>
                    <tr><th>Reviewer Name</th><td>${review.reviewer_name}</td></tr>
                    <tr><th>Reviewer Email</th><td>${review.reviewer_email}</td></tr>
                    <tr><th>Phone</th><td>${review.reviewer_phone ? review.reviewer_phone : 'N/A'}</td></tr>
                    <tr><th>Item</th><td>${review.item_name}</td></tr>
                    <tr><th>Owner Name</th><td>${review.owner_name}</td></tr>
                    <tr><th>Type</th><td><span class="type-label ${review.Review_type === 'E' ? 'equipment' : 'product'}">
                    ${review.Review_type === 'E' ? 'Equipment' : 'Product'}</span></td></tr>
                    <tr><th>Rating</th><td>${review.Rating}/5</td></tr>
                    <tr><th>Comment</th><td>${review.comment ? review.comment.replace(/\n/g, "<br>") : 'No comment'}</td></tr>
                    <tr><th>Date</th><td>${new Date(review.created_date).toLocaleString()}</td></tr>
                </table>`;
                document.getElementById('modal').style.display = 'block';
            }
            function closeModal() {
                document.getElementById('modal').style.display = 'none';
            }
            window.onclick = (e) => {
                if (e.target === document.getElementById('modal')) {
                    closeModal();
                }
            };
        </script>
    </body>
</html>
