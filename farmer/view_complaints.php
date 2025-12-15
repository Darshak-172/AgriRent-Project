<?php
session_start();
require_once('../auth/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'F') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$status_filter = $_GET['status'] ?? 'all';

// Get complaints - Simplified query
$sql = "SELECT c.Complaint_id, c.Complaint_type, c.Description, c.Status
        FROM complaints c
        WHERE c.User_id = ?";

$params = [$user_id];
$param_types = "i";

if ($status_filter !== 'all') {
    $sql .= " AND c.Status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$sql .= " ORDER BY c.Complaint_id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM complaints WHERE User_id = ?";
if ($status_filter !== 'all') {
    $count_sql .= " AND Status = ?";
}

$count_stmt = $conn->prepare($count_sql);
if ($status_filter !== 'all') {
    $count_stmt->bind_param('is', $user_id, $status_filter);
} else {
    $count_stmt->bind_param('i', $user_id);
}
$count_stmt->execute();
$total_complaints = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_complaints / $limit);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN Status = 'O' THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN Status = 'P' THEN 1 ELSE 0 END) as progress_count,
                SUM(CASE WHEN Status = 'R' THEN 1 ELSE 0 END) as resolved_count
              FROM complaints WHERE User_id = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

require 'fheader.php';
require 'farmer_nav.php';
?>

<!DOCTYPE html>
<html>
    <head>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            .main-content {
                padding: 30px 20px;
                background: #f5f7fa;
                min-height: calc(100vh - 70px);
            }

            .page-header {
                margin-bottom: 30px;
            }

            .page-header h1 {
                color: #234a23;
                font-size: 32px;
                margin-bottom: 5px;
                font-weight: 600;
            }

            .page-header p {
                color: #666;
                font-size: 14px;
            }

            .alert {
                padding: 15px 20px;
                border-radius: 6px;
                margin-bottom: 20px;
                font-weight: 500;
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

            /* Statistics Cards */
            .stats-container {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .stat-card {
                background: white;
                padding: 25px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                text-align: center;
                border-top: 4px solid #234a23;
            }

            .stat-card.open {
                border-top-color: #234a23;
            }

            .stat-card.progress {
                border-top-color: #234a23;
            }

            .stat-card.resolved {
                border-top-color: #234a23;
            }

            .stat-card h3 {
                font-size: 14px;
                color: #666;
                margin-bottom: 10px;
                font-weight: 600;
                text-transform: uppercase;
            }

            .stat-card .count {
                font-size: 40px;
                font-weight: bold;
                color: #234a23;
            }

            /* Filters */
            .filter-section {
                background: white;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 30px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
                align-items: center;
                display: flex;
                gap: 20px;
                flex-wrap: nowrap;
                justify-content: space-between;
                align-content: space-around;

            }

            .filter-group {
                display: flex;
                gap: 10px;
                align-items: center;
            }

            .filter-group label {
                font-weight: 600;
                color: #333;
                font-size: 14px;
            }

            .filter-group select {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: white;
                cursor: pointer;
                font-size: 14px;
            }

            .filter-group select:hover {
                border-color: #234a23;
            }

            .complaint-count {
                margin-left: auto;
                color: #666;
                font-size: 14px;
            }

            /* Complaints Table */
            .complaints-table {
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                margin-bottom: 30px;
            }

            .table-responsive {
                overflow-x: auto;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            table thead {
                background: #234a23;
                color: white;
            }

            table th {
                padding: 15px;
                text-align: left;
                font-weight: 600;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            table tbody tr {
                border-bottom: 1px solid #eee;
                transition: background 0.2s;
            }

            table tbody tr:hover {
                background: #f9f9f9;
            }

            table tbody td {
                padding: 15px;
                font-size: 14px;
                color: #333;
            }

            .complaint-id {
                font-weight: 600;
                color: #234a23;
            }

            .complaint-type {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }

            .type-equipment {
                background: #e3f2fd;
                color: #1976d2;
            }

            .type-product {
                background: #f3e5f5;
                color: #7b1fa2;
            }

            .status-badge {
                display: inline-block;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                color: white;
            }

            .status-o {
                background: #dc3545;
            }

            .status-p {
                background: #ffc107;
                color: #212529;
            }

            .status-r {
                background: #28a745;
            }

            .description-preview {
                max-width: 300px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                color: #666;
            }

            .user-info {
                font-size: 13px;
                color: #666;
            }

            .user-info strong {
                color: #234a23;
            }

            .action-buttons {
                display: flex;
                gap: 5px;
            }

            .btn {
                padding: 6px 12px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                font-weight: 600;
                transition: all 0.3s;
                text-decoration: none;
                display: inline-block;
            }

            .btn-view {
                background: #234a23;
                color: white;
            }

            .btn-view:hover {
                background: #235a23;
            }

            /* Modal */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
            }

            .modal.show {
                display: block;
            }

            .modal-content {
                background-color: white;
                margin: 5% auto;
                padding: 30px;
                border-radius: 8px;
                max-width: 600px;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }

            .modal-header h2 {
                color: #234a23;
                font-size: 20px;
                margin: 0;
            }

            .modal-close {
                font-size: 28px;
                font-weight: bold;
                color: #999;
                cursor: pointer;
                border: none;
                background: none;
                padding: 0;
                width: 30px;
                height: 30px;
            }

            .modal-close:hover {
                color: #333;
            }

            .modal-body {
                margin-bottom: 20px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #333;
            }

            .form-group select,
            .form-group textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-family: inherit;
                font-size: 14px;
            }

            .form-group textarea {
                resize: vertical;
                min-height: 120px;
            }

            .form-group select:focus,
            .form-group textarea:focus {
                outline: none;
                border-color: #234a23;
                box-shadow: 0 0 5px rgba(35, 74, 35, 0.2);
            }

            /* Pagination */
            .pagination {
                display: flex;
                justify-content: center;
                gap: 5px;
                margin-top: 30px;
            }

            .pagination a, .pagination span {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                color: #234a23;
                text-decoration: none;
                cursor: pointer;
            }

            .pagination a:hover {
                background: #234a23;
                color: white;
            }

            .pagination a.active {
                background: #234a23;
                color: white;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                background: white;
                border-radius: 8px;
                color: #999;
            }

            .empty-state h3 {
                font-size: 20px;
                color: #333;
                margin-bottom: 10px;
            }

            @media (max-width: 768px) {
                .stats-container {
                    grid-template-columns: repeat(2, 1fr);
                }

                .filter-section {
                    flex-direction: column;
                }

                .complaint-count {
                    margin-left: 0;
                }

                .table-responsive {
                    font-size: 12px;
                }

                table th, table td {
                    padding: 10px;
                }

                .description-preview {
                    max-width: 150px;
                }

                .action-buttons {
                    flex-direction: column;
                }

                .modal-content {
                    margin: 20% auto;
                    width: 90%;
                }
            }
        </style>


    </head>
    <body>

        <div class="main-content">
            <div class="page-header">
                <h1> My Complaints</h1>
                <p>Track all your submitted complaints</p>
            </div>

            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total</h3>
                    <div class="count"><?= $stats['total'] ?? 0 ?></div>
                </div>
                <div class="stat-card open">
                    <h3>Open</h3>
                    <div class="count"><?= $stats['open_count'] ?? 0 ?></div>
                </div>
                <div class="stat-card progress">
                    <h3>In Progress</h3>
                    <div class="count"><?= $stats['progress_count'] ?? 0 ?></div>
                </div>
                <div class="stat-card resolved">
                    <h3>Resolved</h3>
                    <div class="count"><?= $stats['resolved_count'] ?? 0 ?></div>
                </div>
            </div>

            <div class="filter-section">
                <form method="GET">
                    <div class="filter-group">
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="O" <?= $status_filter == 'O' ? 'selected' : '' ?>>Open</option>
                            <option value="P" <?= $status_filter == 'P' ? 'selected' : '' ?>>In Progress</option>
                            <option value="R" <?= $status_filter == 'R' ? 'selected' : '' ?>>Resolved</option>
                        </select>
                    </div>
                </form>
                <a href="add_complaint.php" class="add-btn" style="display: inline-block; margin-top: 20px; display: flex
                   ;
                   gap: 20px;
                   flex-wrap: nowrap;
                   justify-content: space-between;
                   align-content: space-around;
                   color: #234a23;">File New Complaint</a>
            </div>

            <?php if (count($complaints) > 0): ?>
                <div class="complaints-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td class="complaint-id"><?= $complaint['Complaint_id'] ?></td>
                                    <td>
                                        <span class="complaint-type type-<?= strtolower($complaint['Complaint_type']) ?>">
                                            <?=
                                            $complaint['Complaint_type'] == 'E' ? 'Equipment' :
                                                    ($complaint['Complaint_type'] == 'P' ? 'Product' : 'System')
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="description-preview" title="<?= htmlspecialchars($complaint['Description']) ?>">
        <?= htmlspecialchars(substr($complaint['Description'], 0, 50)) ?>...
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($complaint['Status']) ?>">
                                            <?=
                                            $complaint['Status'] == 'O' ? 'Open' :
                                                    ($complaint['Status'] == 'P' ? 'In Progress' : 'Resolved')
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-view" onclick="viewComplaint(<?= htmlspecialchars(json_encode($complaint)) ?>)">
                                            View
                                        </button>
                                    </td>
                                </tr>
    <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&status=<?= $status_filter ?>" <?= $page == $i ? 'class="active"' : '' ?>>
                            <?= $i ?>
                            </a>
                    <?php endfor; ?>
                    </div>
                <?php endif; ?>

<?php else: ?>
                <div class="empty-state">
                    <h3>No Complaints Found</h3>
                    <p><?= $status_filter !== 'all' ? 'No complaints with selected status.' : 'No complaints filed yet.' ?></p>
                    <a href="add_complaint.php" class="add-btn" style="display: inline-block; margin-top: 20px;">➕ File Your First Complaint</a>
                </div>
<?php endif; ?>
        </div>

        <div id="complaintModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Complaint Details</h2>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="complaint-detail" id="modal-detail"></div>
                <div class="complaint-description" id="modal-description"></div>
            </div>
        </div>

        <script>
            function viewComplaint(complaint) {
                let typeText = complaint.Complaint_type === 'E' ? 'Equipment' :
                        complaint.Complaint_type === 'P' ? 'Product' : 'System';
                let statusText = complaint.Status === 'O' ? 'Open' :
                        complaint.Status === 'P' ? 'In Progress' : 'Resolved';

                let detailHtml = `
                <div class="detail-row"><strong>ID:</strong> ${complaint.Complaint_id}</div>
                <div class="detail-row"><strong>Type:</strong> ${typeText}</div>
                <div class="detail-row"><strong>Status:</strong> <span class="status-badge status-${complaint.Status.toLowerCase()}">${statusText}</span></div>
            `;

                document.getElementById('modal-detail').innerHTML = detailHtml;
                document.getElementById('modal-description').textContent = complaint.Description;
                document.getElementById('complaintModal').classList.add('show');
            }

            function closeModal() {
                document.getElementById('complaintModal').classList.remove('show');
            }

            window.onclick = function (event) {
                const modal = document.getElementById('complaintModal');
                if (event.target == modal)
                    closeModal();
            }
        </script>

    </body>
</html>

<?php require 'ffooter.php'; ?>
