<?php
session_start();
require_once '../auth/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'A') {
    header('Location: ../login.php');
    exit;
}

$status = isset($_GET['status']) ? $_GET['status'] : 'PEN';

$sql = "
    SELECT po.*, p.Name as product_name, p.Unit, p.Description, p.Price as price_per_unit,
           buyer.Name as buyer_name, buyer.Email as buyer_email, buyer.Phone as buyer_phone,
           seller.Name as seller_name, seller.Email as seller_email, seller.Phone as seller_phone,
           i.image_url as product_image,
           ua.address, ua.city, ua.state, ua.Pin_code
    FROM product_orders po
    JOIN product p ON po.Product_id = p.product_id
    JOIN users buyer ON po.buyer_id = buyer.user_id
    JOIN users seller ON p.seller_id = seller.user_id
    LEFT JOIN images i ON (i.image_type = 'P' AND i.ID = p.product_id)
    LEFT JOIN user_addresses ua ON po.delivery_address = ua.address_id
    WHERE po.Status = '$status'
    ORDER BY po.order_date DESC
";

$orders = $conn->query($sql);

if (!$orders) {
    die("SQL Error: " . $conn->error . "<br>Query: " . $sql);
}

require 'header.php';
require 'admin_nav.php';
?>

<div class="main-content">
    <h1>Orders (View Only)</h1>

    <div class="tabs">
        <a href="?status=PEN" class="tab <?= $status == 'PEN' ? 'active' : '' ?>">Pending</a>
        <a href="?status=CON" class="tab <?= $status == 'CON' ? 'active' : '' ?>">Confirmed</a>
        <a href="?status=COM" class="tab <?= $status == 'COM' ? 'active' : '' ?>">Completed</a>
        <a href="?status=CAN" class="tab <?= $status == 'CAN' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <div class="search-box">
        <input type="text" id="liveSearch" placeholder="Search orders by ID, product, buyer, seller..." style="padding: 8px; width: 800px; margin-bottom: 10px;">
        <button type="button" id="clearSearch" class="btn" style="margin-left: 10px; width: 85px;">Clear</button>
    </div>

    <table id="ordersTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Buyer</th>
                <th>Seller</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Order Date</th>
                <th>Status</th>
                <th>View</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($orders && $orders->num_rows > 0): ?>
                <?php while ($order = $orders->fetch_assoc()): ?>
                    <tr class="order-row">
                        <td><?= $order['Order_id'] ?></td>
                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                        <td><?= htmlspecialchars($order['buyer_name']) ?></td>
                        <td><?= htmlspecialchars($order['seller_name']) ?></td>
                        <td><?= $order['quantity'] ?> <?= $order['Unit'] == 'K' ? 'kg' : 'liter' ?></td>
                        <td>Rs.<?= number_format($order['total_price'], 2) ?></td>
                        <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                        <td>
                            <?php 
                            $statusText = 'Unknown';
                            $statusColor = 'black';
                            if ($order['Status'] == 'PEN') {
                                $statusText = 'Pending'; $statusColor = 'orange';
                            } elseif ($order['Status'] == 'CON') {
                                $statusText = 'Confirmed'; $statusColor = 'green';
                            } elseif ($order['Status'] == 'COM') {
                                $statusText = 'Completed'; $statusColor = 'blue';
                            } elseif ($order['Status'] == 'CAN') {
                                $statusText = 'Cancelled'; $statusColor = 'red';
                            }
                            ?>
                            <span style="color: <?= $statusColor ?>; font-weight: bold;"><?= $statusText ?></span>
                        </td>
                        <td>
                            <button type="button" class="btn-view" onclick="viewOrder(<?= htmlspecialchars(json_encode($order)) ?>)">View Details</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9">No orders found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px;">
        <strong>Note:</strong> Admin can only view orders.
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="modal" style="display: none;">
    <div class="modal-content-large">
        <span class="close" onclick="closeOrderModal()">&times;</span>
        <h2 id="modalTitle">Order Details</h2>

        <div class="modal-body">
            <div class="product-section">
                <h3>Product Information</h3>
                <div class="product-content">
                    <div class="product-image-container">
                        <div id="productImageDisplay"></div>
                    </div>
                    <div class="product-details">
                        <div class="details-grid">
                            <div class="detail-row"><strong>Product Name:</strong><span id="detailProductName"></span></div>
                            <div class="detail-row"><strong>Description:</strong><span id="detailProductDescription"></span></div>
                            <div class="detail-row"><strong>Unit Price:</strong><span id="detailProductPrice"></span></div>
                            <div class="detail-row"><strong>Unit Type:</strong><span id="detailProductUnit"></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-details-section">
                <h3>Order Information</h3>
                <div class="details-grid">
                    <div class="detail-row"><strong>Order ID:</strong><span id="detailOrderId"></span></div>
                    <div class="detail-row"><strong>Quantity Ordered:</strong><span id="detailQuantity"></span></div>
                    <div class="detail-row"><strong>Total Amount:</strong><span id="detailTotalAmount"></span></div>
                    <div class="detail-row"><strong>Order Date:</strong><span id="detailOrderDate"></span></div>
                    <div class="detail-row"><strong>Status:</strong><span id="detailOrderStatus"></span></div>
                    <div class="detail-row"><strong>Delivery Address:</strong><span id="detailDeliveryAddress"></span></div>
                </div>
            </div>

            <div class="buyer-details-section">
                <h3>Buyer Information</h3>
                <div class="details-grid">
                    <div class="detail-row"><strong>Buyer Name:</strong><span id="detailBuyerName"></span></div>
                    <div class="detail-row"><strong>Email:</strong><span id="detailBuyerEmail"></span></div>
                    <div class="detail-row"><strong>Phone:</strong><span id="detailBuyerPhone"></span></div>
                </div>
            </div>

            <div class="seller-details-section">
                <h3>Product Seller Information</h3>
                <div class="details-grid">
                    <div class="detail-row"><strong>Seller Name:</strong><span id="detailSellerName"></span></div>
                    <div class="detail-row"><strong>Email:</strong><span id="detailSellerEmail"></span></div>
                    <div class="detail-row"><strong>Phone:</strong><span id="detailSellerPhone"></span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fullscreen Image Modal -->
<div id="fullscreenImageModal" class="fullscreen-modal" style="display: none;">
    <span class="fullscreen-close" onclick="closeFullscreenImage()">&times;</span>
    <img id="fullscreenImage" class="fullscreen-image" src="" alt="Fullscreen Product Image">
    <div id="fullscreenImageCaption" class="fullscreen-caption"></div>
</div>

<style>
.status-completed {
    background: #cce5ff;
    color: #004085;
}

.modal, .modal-content-large, .modal-body, .product-section, .order-details-section,
.buyer-details-section, .seller-details-section, .product-content, .product-image-container,
.product-details, .details-grid, .detail-row, .btn-view, #liveSearch, .order-row.hidden,
.status-badge /* Include other styles you use as needed */
{
    /* Your existing CSS - omitted here for brevity */
}
</style>
<style>
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
            margin: 1% auto;
            padding: 0;
            border: none;
            border-radius: 8px;
            width: 95%;
            max-width: 1000px;
            max-height: 95vh;
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

        .product-section, .order-details-section, .buyer-details-section, .seller-details-section {
            margin-bottom: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }

        .product-section h3, .order-details-section h3, .buyer-details-section h3, .seller-details-section h3 {
            color: #234a23;
            margin-bottom: 15px;
            margin-top: 0;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }

        .product-content {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            align-items: start;
        }

        .product-image-container {
            text-align: center;
        }

        #productImageDisplay {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #productImageDisplay img {
            max-width: 100%;
            max-height: 250px;
            object-fit: contain;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        #productImageDisplay img:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .product-details {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .detail-row {
            display: flex;
            padding: 12px;
            background: #fff;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }

        .detail-row:hover {
            border-color: #234a23;
            box-shadow: 0 2px 4px rgba(35, 74, 35, 0.1);
        }

        .detail-row strong {
            min-width: 180px;
            color: #234a23;
            font-weight: 600;
        }

        .detail-row span {
            flex: 1;
            margin-left: 15px;
            color: #495057;
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

        /* Fullscreen Image Modal Styles */
        .fullscreen-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.95);
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .fullscreen-image {
            margin: auto;
            display: block;
            width: auto;
            height: auto;
            max-width: 95%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(255, 255, 255, 0.1);
            animation: zoomIn 0.3s ease-in-out;
        }

        @keyframes zoomIn {
            from {
                transform: translate(-50%, -50%) scale(0.5);
            }
            to {
                transform: translate(-50%, -50%) scale(1);
            }
        }

        .fullscreen-close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #ffffff;
            font-size: 50px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
            z-index: 2001;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .fullscreen-close:hover,
        .fullscreen-close:focus {
            color: #ffcccc;
            text-decoration: none;
        }

        .fullscreen-caption {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            text-align: center;
            color: #ffffff;
            padding: 10px 0;
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            border-radius: 4px;
            font-size: 16px;
            line-height: 1.4;
        }

        .btn-view {
            background: #234a23;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }

        .btn-view:hover {
            background: #236a23;
        }

        /* Search Box Styling */
        #liveSearch {
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        #liveSearch:focus {
            outline: none;
            border-color: #234a23;
            box-shadow: 0 0 5px rgba(35, 74, 35, 0.3);
        }

        .order-row.hidden {
            display: none !important;
        }

        /* Status badges */
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-Pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        /* Responsive Design */
        @media only screen and (max-width: 768px) {
            .modal-content-large {
                width: 98%;
                margin: 1% auto;
            }

            .product-content {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .detail-row {
                flex-direction: column;
                gap: 5px;
            }

            .detail-row strong {
                min-width: auto;
            }

            .detail-row span {
                margin-left: 0;
            }

            #liveSearch {
                width: 100%;
                margin-bottom: 10px;
            }

            .fullscreen-close {
                top: 10px;
                right: 20px;
                font-size: 40px;
            }

            .fullscreen-caption {
                width: 95%;
                bottom: 10px;
                font-size: 14px;
            }

            .fullscreen-image {
                max-width: 98%;
                max-height: 85%;
            }
        }
    </style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function viewOrder(order) {
    document.getElementById('detailProductName').textContent = order.product_name || 'N/A';
    document.getElementById('detailProductDescription').textContent = order.Description || 'No description available';
    document.getElementById('detailProductPrice').textContent = 'Rs.' + (order.price_per_unit || '0') + ' per ' + (order.Unit == 'K' ? 'kg' : 'liter');
    document.getElementById('detailProductUnit').textContent = order.Unit == 'K' ? 'Kilogram' : 'Liter';

    const imageContainer = document.getElementById('productImageDisplay');
    if (order.product_image) {
        imageContainer.innerHTML = '<img src="../' + order.product_image + '" alt="Product Image" onclick="openFullscreenImage(this, \'' + (order.product_name || 'Product') + '\')" title="Click to view fullscreen">';
    } else {
        imageContainer.innerHTML = '<div style="color: #6c757d; text-align: center;"><i class="fas fa-image" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>No Image Available</div>';
    }

    document.getElementById('detailOrderId').textContent = order.Order_id;
    document.getElementById('detailQuantity').textContent = (order.quantity || '0') + ' ' + (order.Unit == 'K' ? 'kg' : 'liter');
    document.getElementById('detailTotalAmount').textContent = 'Rs.' + parseFloat(order.total_price || 0).toLocaleString();

    let orderDateText = 'N/A';
    if (order.order_date) {
        const orderDate = new Date(order.order_date);
        orderDateText = orderDate.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
    }
    document.getElementById('detailOrderDate').textContent = orderDateText;

    let deliveryAddressParts = [];
    if (order.address) deliveryAddressParts.push(order.address);
    if (order.city) deliveryAddressParts.push(order.city);
    if (order.state) deliveryAddressParts.push(order.state);
    if (order.Pincode) deliveryAddressParts.push(order.Pincode);

    let fullAddress = deliveryAddressParts.length > 0 ? deliveryAddressParts.join(', ') : 'Not specified';
    document.getElementById('detailDeliveryAddress').textContent = fullAddress;

    const statusElement = document.getElementById('detailOrderStatus');
    let statusClass = '';
    let statusText = '';

    switch(order.Status) {
        case 'PEN':
            statusClass = 'status-Pending'; statusText = 'Pending'; break;
        case 'CON':
            statusClass = 'status-confirmed'; statusText = 'Confirmed'; break;
        case 'CAN':
            statusClass = 'status-cancelled'; statusText = 'Cancelled'; break;
        case 'COM':
            statusClass = 'status-completed'; statusText = 'Completed'; break;
        default:
            statusClass = 'status-Pending'; statusText = order.Status || 'Unknown';
    }
    statusElement.innerHTML = '<span class="status-badge ' + statusClass + '">' + statusText + '</span>';

    document.getElementById('detailBuyerName').textContent = order.buyer_name || 'N/A';
    document.getElementById('detailBuyerEmail').textContent = order.buyer_email || 'N/A';
    document.getElementById('detailBuyerPhone').textContent = order.buyer_phone || 'N/A';

    document.getElementById('detailSellerName').textContent = order.seller_name || 'N/A';
    document.getElementById('detailSellerEmail').textContent = order.seller_email || 'N/A';
    document.getElementById('detailSellerPhone').textContent = order.seller_phone || 'N/A';

    document.getElementById('orderModal').style.display = 'block';
}

function closeOrderModal() {
    document.getElementById('orderModal').style.display = 'none';
}

function openFullscreenImage(imgElement, caption) {
    const modal = document.getElementById('fullscreenImageModal');
    const fullscreenImg = document.getElementById('fullscreenImage');
    const captionElement = document.getElementById('fullscreenImageCaption');

    modal.style.display = 'block';
    fullscreenImg.src = imgElement.src;
    captionElement.innerHTML = caption || 'Product Image';

    document.body.style.overflow = 'hidden';
}

function closeFullscreenImage() {
    const modal = document.getElementById('fullscreenImageModal');
    modal.style.display = 'none';

    document.body.style.overflow = 'auto';
}

$(document).ready(function() {
    $('#liveSearch').on('keyup', function() {
        var val = $(this).val().toLowerCase();
        $('#ordersTable tbody tr.order-row').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(val) > -1);
        });
    });

    $('#clearSearch').on('click', function() {
        $('#liveSearch').val('');
        $('#ordersTable tbody tr.order-row').show();
    });

    $('.tab').on('click', function() {
        $('#liveSearch').val('');
    });
});

window.onclick = function(event) {
    const orderModal = document.getElementById('orderModal');
    const fullscreenModal = document.getElementById('fullscreenImageModal');
    if (event.target == orderModal) {
        orderModal.style.display = 'none';
    }
    if (event.target == fullscreenModal) {
        closeFullscreenImage();
    }
};

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        if (document.getElementById('fullscreenImageModal').style.display === 'block') {
            closeFullscreenImage();
        }
        if (document.getElementById('orderModal').style.display === 'block') {
            closeOrderModal();
        }
    }
});
</script>

<?php require 'footer.php'; ?>
