<?php
session_start();
require_once '../auth/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'A') {
    header("Location: ../login.php");
    exit;
}

if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    // Check subscription status before delete
    $sub_check = $conn->query("SELECT * FROM user_subscriptions WHERE user_id = $user_id AND status='A'");
    if ($sub_check && $sub_check->num_rows > 0) {
        $message = "Cannot delete user with active subscription.";
    } else {
        $conn->query("DELETE FROM users WHERE user_id = $user_id");
        $message = "User deleted successfully.";
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY user_id DESC");

require 'header.php';
require 'admin_nav.php';
?>

<style>
    
    element.style {
    padding: 10px 15px;
    border-top: none;
    border-right: none;
    border-left: none;
    border-image: initial;
    background: rgb(35, 74, 35);
    cursor: pointer;
    font-size: 14px;
    color: rgb(255, 255, 255);
}
/* Your CSS styling as provided; no changes to style */
#searchInput, #filterSelect {
    font-size: 14px;
    border: 1px solid #ccc;
    border-radius: 4px;
}
#searchInput:focus, #filterSelect:focus {
    outline: none;
    border-color: #234a23;
    box-shadow: 0 0 5px rgba(0,124,186,0.3);
}
.user-row.hidden {
    display: none !important;
}
.message {
    background: #d4edda;
    color: #155724;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
}
.btn {
    padding: 8px 15px;
    background: #234a23;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.btn:hover {
    background: #005a8b;
}
#usersTable {
    width: 100%;
    border-collapse: collapse;
}
#usersTable th, 
#usersTable td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
#usersTable th {
    background-color: #f8f9fa;
    font-weight: bold;
}
#usersTable tr:hover {
    background-color: #f5f5f5;
}
@media screen and (max-width: 768px) {
    .main-content {
        padding: 10px;
    }
    .search-box {
        display: block;
        margin-bottom: 15px;
    }
    #searchInput {
        width: 100% !important;
        margin: 0 0 10px 0 !important;
        box-sizing: border-box;
    }
    #filterSelect {
        width: 100% !important;
        margin: 0 0 10px 0 !important;
        box-sizing: border-box;
    }
    #clearBtn {
        width: 100%;
        margin: 0;
    }
    #usersTable {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
        min-width: 600px;
    }
    #usersTable thead,
    #usersTable tbody,
    #usersTable th,
    #usersTable td,
    #usersTable tr {
        display: block;
    }
    #usersTable thead tr {
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
    #usersTable tr {
        border: 1px solid #ccc;
        margin-bottom: 10px;
        padding: 10px;
        background: white;
        display: block;
        position: relative;
    }
    #usersTable td {
        border: none;
        padding: 8px 0;
        display: block;
        text-align: left;
        white-space: normal;
        padding-left: 50%;
        position: relative;
    }
    #usersTable td:before {
        content: "";
        position: absolute;
        left: 6px;
        width: 45%;
        padding-right: 10px;
        white-space: nowrap;
        font-weight: bold;
    }
    #usersTable td:nth-of-type(1):before { content: "ID: "; }
    #usersTable td:nth-of-type(2):before { content: "Name: "; }
    #usersTable td:nth-of-type(3):before { content: "Email: "; }
    #usersTable td:nth-of-type(4):before { content: "Phone: "; }
    #usersTable td:nth-of-type(5):before { content: "Type: "; }
    #usersTable td:nth-of-type(6):before { content: "Actions: "; }
}
@media screen and (min-width: 769px) and (max-width: 1024px) {
    #searchInput {
        width: 300px !important;
    }
    .search-box {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
}
@media screen and (max-width: 480px) {
    .main-content {
        padding: 5px;
    }
    h1 {
        font-size: 20px;
        text-align: center;
    }
    #usersTable tr {
        margin-bottom: 8px;
        padding: 8px;
    }
    #usersTable td {
        padding: 5px 0;
        font-size: 14px;
    }
}
@media screen and (min-width: 1025px) {
    .search-box {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }
}
</style>



<div class="main-content">
<h1>Users</h1>
<?php if (isset($message)): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<div class="search-box">
    <input type="text" id="searchInput" placeholder="Search users..." style="padding: 8px; width: 900px; margin-right: 10px;">
    <select id="filterSelect" style="padding:8px; margin-right: 10px;">
        <option value="">All Users</option>
        <option value="Equipment Owner">Equipment Owners</option>
        <option value="Farmer">Farmers</option>
        <option value="Admin">Admins</option>
    </select>
    <button type="button" id="clearBtn" class="btn">Clear</button>
</div>
<table id="usersTable">
    <thead>
        <tr class="table-header">
            <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Type</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($users->num_rows > 0): ?>
        <?php while($user = $users->fetch_assoc()): ?>
            <tr class="user-row">
                <td><?= $user['user_id'] ?></td>
                <td class="user-name"><?= htmlspecialchars($user['Name']) ?></td>
                <td class="user-email"><?= htmlspecialchars($user['Email']) ?></td>
                <td class="user-phone"><?= htmlspecialchars($user['Phone']) ?></td>
                <td class="user-type"><?= $user['User_type']=='O' ? 'Equipment Owner' : ($user['User_type']=='F' ? 'Farmer' : 'Admin') ?></td>
                <td>
    <?php if ($user['User_type'] !== 'A'): // Show button only if not admin ?>
        <button class="btn-view-detail" data-user-id="<?= $user['user_id'] ?>" data-user-type="<?= $user['User_type'] ?>" style="margin-right:8px; padding:6px 12px; background:#234a23; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:13px;">View Detail</button>
    <?php endif; ?>

    <?php if ($user['User_type'] == 'O' || $user['User_type'] == 'F'): ?>
        <a href="?delete=<?= $user['user_id'] ?>" onclick="return confirm('Delete this user?')">Delete</a>
    <?php else: ?>
        -
    <?php endif; ?>
</td>

            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="6" style="text-align:center;">No users found.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- User Detail Modal -->
<div id="userDetailModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
<div style="background:#fff; max-width:900px; max-height:85vh; margin:3% auto; padding:25px; border-radius:8px; overflow-y:auto; position:relative;">
<button class="modal-close" style="position:absolute; top:10px; right:15px; font-size:28px; border:none; background:none; cursor:pointer;">&times;</button>
<h2 style="color:#234a23; border-bottom:2px solid #234a23; padding-bottom:10px; margin-bottom:15px;">User Complete Profile</h2>
<div style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:20px; border-bottom:2px solid #ddd; padding-bottom:10px;">
<button class="modal-tab-btn active" data-tab="basic-info" style="padding:10px 15px; border:none; background:#234a23; color:#fff; cursor:pointer; font-size:14px;">Basic Info</button>
<button class="modal-tab-btn" data-tab="equipment" style="padding:10px 15px; border:none; background:#f0f0f0; cursor:pointer; font-size:14px;">Equipment</button>
<button class="modal-tab-btn" data-tab="products" style="padding:10px 15px; border:none; background:#f0f0f0; cursor:pointer; font-size:14px;">Products</button>
<button class="modal-tab-btn" data-tab="bookings" style="padding:10px 15px; border:none; background:#f0f0f0; cursor:pointer; font-size:14px;">Bookings</button>
<button class="modal-tab-btn" data-tab="orders" style="padding:10px 15px; border:none; background:#f0f0f0; cursor:pointer; font-size:14px;">Orders</button>
<button class="modal-tab-btn" data-tab="subscriptions" style="padding:10px 15px; border:none; background:#f0f0f0; cursor:pointer; font-size:14px;">Subscriptions</button>
<button class="modal-tab-btn" data-tab="addresses" style="padding:10px 15px; border:none; background:#f0f0f0; cursor:pointer; font-size:14px;">Addresses</button>
</div>
<div id="detailContent">
<p style="text-align:center; padding:30px; color:#666;">Loading user details...</p>
</div>
</div>
</div>



<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
$(function() {
    var modal = $('#userDetailModal');
    $('.btn-view-detail').click(function() {
        var userId = $(this).data('user-id');
        modal.show();
        $('#detailContent').html('<p style="text-align:center; padding: 30px; color: #666;">Loading user details...</p>');
        $('.modal-tab-btn').removeClass('active').css({'background': '#f0f0f0', 'color': '#000', 'border-bottom': '3px solid transparent'});
        $('.modal-tab-btn[data-tab="basic-info"]').addClass('active').css({'background': '#234a23', 'color': '#fff', 'border-bottom': '3px solid #234a23'});

        $.ajax({
            url: 'get_user_comprehensive_detail.php',
            method: 'GET',
            data: { user_id: userId },
            dataType: 'json',
            success: function(resp) {
                if(resp.success) {
                    buildDetailTabs(resp.data);
                    showTab('basic-info');
                } else {
                    $('#detailContent').html('<p style="color:red; padding:20px;">' + resp.message + '</p>');
                }
            },
            error: function() {
                $('#detailContent').html('<p style="color:red; padding:20px;">Failed to load user details.</p>');
            }
        });
    });

    $('.modal-close').click(function() {
        modal.hide();
    });
    $(window).click(function(e) {
        if (e.target == modal[0]) modal.hide();
    });
    $('.modal-tab-btn').click(function() {
        var tabName = $(this).data('tab');
        $('.modal-tab-btn').removeClass('active').css({'background': '#f0f0f0', 'color': '#000', 'border-bottom': '3px solid transparent'});
        $(this).addClass('active').css({'background': '#234a23', 'color': '#fff', 'border-bottom': '3px solid #234a23'});
        showTab(tabName);
    });
    function showTab(tab) {
        $('.tab-content').hide();
        $('#' + tab).show();
    }
    function buildDetailTabs(data) {
        var user = data.user;
        var userType = user.User_type == 'O' ? 'Equipment Owner' : (user.User_type == 'F' ? 'Farmer' : 'Admin');
        var status = user.status == 'A' ? 'Active' : 'Inactive';

        var basicHtml =
            `<div class="tab-content active" id="basic-info" style="padding: 1px;">
                <p><strong>User ID:</strong> ${user.user_id}</p><br>
                <p><strong>Name:</strong> ${user.Name}</p><br>
                <p><strong>Email:</strong> ${user.Email}</p><br>
                <p><strong>Phone:</strong> ${user.Phone}</p><br>
                <p><strong>User Type:</strong> ${userType}</p><br>
                <p><strong>Status:</strong> ${status}</p><br>
            </div>`;

        function renderTable(id, columns, items) {
            if (!items || items.length === 0) return `<div class="tab-content" id="${id}" style="display: none;"><p>No ${id} found</p></div>`;
            var header = `<tr>${columns.map(col => `<th>${col}</th>`).join('')}</tr>`;
            var body = items.map(item => `<tr>${columns.map(col => `<td>${item[col] !== undefined ? item[col] : ''}</td>`).join('')}</tr>`).join('');
            return `<div class="tab-content" id="${id}" style="display:none;"><table>${header}${body}</table></div>`;
        }
        var equipmentHtml = renderTable('equipment', ['Equipment_id', 'Title', 'Brand', 'Model', 'Hourly_rate', 'Daily_rate', 'Approval_status', 'listed_date'], data.equipment);
        var productsHtml = renderTable('products', ['product_id', 'Name', 'Price', 'Quantity', 'Approval_status', 'listed_date'], data.products);
        var bookingsHtml = renderTable('bookings', ['booking_id', 'equipmentTitle', 'customerName', 'start_date', 'end_date', 'total_amount', 'status', 'time_slot'], data.equipmentBookings);
        var ordersHtml = renderTable('orders', ['Order_id', 'productName', 'buyerName', 'quantity', 'total_price', 'Status', 'order_date'], data.productOrders);
        var subscriptionsHtml = renderTable('subscriptions', ['subscription_id', 'Plan_name', 'price', 'start_date', 'end_date', 'Status'], data.subscriptions);
        var reviewsHtml = renderTable('reviews', ['Review_id', 'reviewerName', 'Rating', 'comment', 'created_date'], data.reviews);

        // Addresses custom render because not all fields are shown in one table row
        var addressesHtml = '<div class="tab-content" id="addresses" style="display:none;">';
        if(data.addresses && data.addresses.length > 0){
            data.addresses.forEach(addr => {
                addressesHtml += `<div style="padding: 10px; border-bottom: 1px solid #ddd;">
                    <div><strong>Address:</strong> ${addr.address}</div>
                    <div><strong>City:</strong> ${addr.city}</div>
                    <div><strong>State:</strong> ${addr.state}</div>
                    <div><strong>Pin Code:</strong> ${addr.Pin_code}</div>
                </div>`;
            });
        } else {
            addressesHtml += '<p>No addresses found</p>';
        }
        addressesHtml += '</div>';

        $('#detailContent').html(basicHtml + equipmentHtml + productsHtml + bookingsHtml + ordersHtml + subscriptionsHtml + reviewsHtml + addressesHtml);
    }
});

$(document).ready(function(){
    // Auto hide message after 5 seconds
    setTimeout(function(){
        $('.message').fadeOut('slow', function(){
            $(this).remove();
        });
    }, 5000); // 5000 milliseconds = 5 seconds
});

$(document).ready(function() {
    function filterUsers() {
        var searchTerm = $('#searchInput').val().toLowerCase().trim();
        var filterType = $('#filterSelect').val().toLowerCase();

        var visibleCount = 0;

        $('#usersTable tbody tr.user-row').each(function() {
            var $row = $(this);
            var name = $row.find('.user-name').text().toLowerCase();
            var email = $row.find('.user-email').text().toLowerCase();
            var phone = $row.find('.user-phone').text().toLowerCase();
            var type = $row.find('.user-type').text().toLowerCase();

            var matchesSearch = searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm) || phone.includes(searchTerm);
            var matchesFilter = filterType === '' || type === filterType;

            if (matchesSearch && matchesFilter) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
            }
        });

        if (visibleCount === 0) {
            if ($('#noResultsRow').length === 0) {
                $('#usersTable tbody').append('<tr id="noResultsRow"><td colspan="6" style="text-align:center; font-style:italic; color:#999;">No users found</td></tr>');
            }
        } else {
            $('#noResultsRow').remove();
        }
    }

    $('#searchInput').on('input', filterUsers);
    $('#filterSelect').on('change', filterUsers);
    $('#clearBtn').click(function() {
        $('#searchInput').val('');
        $('#filterSelect').val('');
        filterUsers();
    });

    // Initial call to show all users
    filterUsers();
});


</script>

<?php require 'footer.php'; ?>
