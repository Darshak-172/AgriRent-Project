<?php 
session_start(); 
require_once 'auth/config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
// Get categories for filter
$categories = [];
$cat_result = $conn->query("SELECT Category_id, Category_name FROM product_categories ORDER BY Category_name");
while ($cat = $cat_result->fetch_assoc()) {
    $categories[] = $cat;
}

include 'includes/header.php';
include 'includes/navigation.php';
?>

<!-- Include jQuery from CDN -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<div class="container" style="margin-top: 40px; margin-bottom: 40px;">
    <h1 id="pageTitle">All Agricultural Products</h1>
    <p id="pageSubtitle" style="color: #666; margin-bottom: 30px;">Browse quality agricultural products and supplies for your farming needs</p>
    
    <!-- Search and Filter -->
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <form id="searchForm" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <input type="text" 
                   id="searchInput" 
                   placeholder="Search products, name, or description..." 
                   style="flex: 1; min-width: 250px; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; transition: border-color 0.3s ease;"
                   autocomplete="off">
            
            <select id="categorySelect" style="padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['Category_id'] ?>">
                        <?= htmlspecialchars($cat['Category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" id="searchBtn" style="padding: 12px 25px; background: #234a23; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background-color 0.3s ease;">
                🔍 Search
            </button>
            
            <a href="#" id="clearBtn" style="padding: 12px 25px; background: #6c757d; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background-color 0.3s ease; display: none;">
                Clear
            </a>
        </form>
    </div>
    
    <!-- Loading Indicator -->
    <div id="loadingIndicator" style="display: none; text-align: center; padding: 20px;">
        <div style="display: inline-block; border: 4px solid #f3f3f3; border-top: 4px solid #234a23; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin-right: 10px; vertical-align: middle;"></div>
        <span style="color: #666; font-size: 16px; vertical-align: middle;">Loading products...</span>
    </div>
    
    <!-- Results Info -->
    <div id="resultsInfo" style="margin-bottom: 20px; display: none;">
        <p style="color: #666;" id="resultsMessage"></p>
    </div>
    
    <!-- Products Grid -->
    <div id="productsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <!-- Products will be loaded here by jQuery -->
    </div>
    
    <!-- Pagination Container -->
    <div id="paginationContainer" style="display: none;">
        <div id="paginationControls" style="display: flex; justify-content: center; align-items: center; margin-top: 40px; gap: 8px; flex-wrap: wrap;">
            <!-- Pagination will be added here -->
        </div>
        <div id="pageInfo" style="text-align: center; margin-top: 15px; color: #666;"></div>
    </div>
    
    <!-- No Results Message -->
    <div id="noResults" style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px; display: none;">
        <h3 style="color: #666; margin-bottom: 15px;">📦 No Products Found</h3>
        <p style="color: #666; margin-bottom: 25px;" id="noResultsMessage">Try adjusting your search criteria or browse all products.</p>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

#searchInput:focus,
#categorySelect:focus {
    border-color: #234a23 !important;
    outline: none;
    box-shadow: 0 0 5px rgba(35, 74, 35, 0.3) !important;
}

#searchBtn:hover {
    background-color: #2d5d2f;
    transform: translateY(-2px);
}

#clearBtn:hover {
    background-color: #5a6268;
    transform: translateY(-2px);
}

.equipment-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.equipment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.equipment-image {
    position: relative;
    overflow: hidden;
    background: #f5f5f5;
    height: 200px;
}

.equipment-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.equipment-card:hover .equipment-image img {
    transform: scale(1.05);
}

.equipment-info {
    padding: 20px;
}

.equipment-info h3 {
    margin: 0 0 10px 0;
    color: #234a23;
    font-size: 16px;
    font-weight: 600;
}

.equipment-info p {
    margin: 6px 0;
    font-size: 13px;
}

.price-box {
    margin: 15px 0;
    font-size: 18px;
    font-weight: bold;
    color: #234a23;
}

.action-btn {
    display: block;
    padding: 12px 20px;
    text-align: center;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.pagination-btn {
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid #28a745;
    display: inline-block;
}

.pagination-btn.active {
    background: #28a745;
    color: white;
}

.pagination-btn.inactive {
    background: #e0e0e0;
    color: #999;
    cursor: not-allowed;
}

.pagination-btn.page {
    background: white;
    color: #28a745;
}

.pagination-btn:not(.inactive):hover {
    background: #28a745 !important;
    color: white;
}
</style>

<script>
$(document).ready(function() {
    console.log('jQuery search initialized');
    
    let searchTimeout;
    let currentPage = 1;
    let currentSearch = '';
    let currentCategory = 0;

    // Prevent form submission
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');
        currentPage = 1;
        performSearch();
    });

    // Real-time search
    $('#searchInput').on('input', function() {
        clearTimeout(searchTimeout);
        let searchValue = $.trim($(this).val());
        console.log('Input: ' + searchValue);
        
        if (searchValue.length >= 2 || parseInt($('#categorySelect').val()) > 0) {
            searchTimeout = setTimeout(function() {
                currentPage = 1;
                performSearch();
            }, 300);
        } else if (searchValue.length === 0 && parseInt($('#categorySelect').val()) === 0) {
            currentPage = 1;
            performSearch();
        }
    });

    // Category change
    $('#categorySelect').on('change', function() {
        console.log('Category changed: ' + $(this).val());
        currentPage = 1;
        performSearch();
    });

    // Clear button
    $('#clearBtn').on('click', function(e) {
        e.preventDefault();
        console.log('Clear clicked');
        $('#searchInput').val('');
        $('#categorySelect').val('0');
        currentPage = 1;
        performSearch();
    });

    function performSearch(page = 1) {
        let search = $.trim($('#searchInput').val());
        let category = $('#categorySelect').val();
        
        console.log('Performing search: search=' + search + ', category=' + category + ', page=' + page);
        
        currentSearch = search;
        currentCategory = category;
        currentPage = page;
        
        // Show/hide elements
        $('#loadingIndicator').show();
        $('#resultsInfo').hide();
        $('#productsGrid').hide();
        $('#noResults').hide();
        $('#paginationContainer').hide();
        
        // Toggle clear button
        $('#clearBtn').toggle(search !== '' || parseInt(category) > 0);
        
        // Update title
        if (search || parseInt(category) > 0) {
            $('#pageTitle').text('Search Results');
            $('#pageSubtitle').text('Find agricultural products and supplies for your farming needs');
        } else {
            $('#pageTitle').text('All Agricultural Products');
            $('#pageSubtitle').text('Browse quality agricultural products and supplies for your farming needs');
        }
        
        // AJAX request
        $.ajax({
            url: 'api/search_products_paginated.php',
            method: 'POST',
            data: {
                search: search,
                category: category,
                page: page
            },
            dataType: 'json',
            timeout: 10000,
            success: function(data) {
                console.log('Response received:', data);
                
                $('#loadingIndicator').hide();
                
                if (data.success && data.results && data.results.length > 0) {
                    displayProducts(data.results);
                    $('#resultsInfo').show();
                    $('#resultsMessage').text(data.message || 'Found ' + data.total_count + ' product(s)');
                    
                    if (data.total_pages > 1) {
                        displayPagination(data.current_page, data.total_pages);
                        $('#paginationContainer').show();
                    }
                } else {
                    $('#noResults').show();
                    $('#noResultsMessage').text(data.message || 'No products found.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.log('Response:', xhr.responseText);
                $('#loadingIndicator').hide();
                $('#noResults').show();
                $('#noResultsMessage').text('Error: ' + error + '. Check browser console for details.');
            }
        });
    }

    function displayProducts(products) {
        $('#productsGrid').empty();
        console.log('Displaying ' + products.length + ' products');
        
        $.each(products, function(i, product) {
            let isOOS = product.is_out_of_stock;
            let btnColor = isOOS ? '#ccc' : '#28a745';
            let btnText = isOOS ? '#999' : 'white';
            let qtyColor = isOOS ? '#dc3545' : '#28a745';
            let btnLabel = isOOS ? '🔒 Out of Stock' : 'View Details';
            let url = isOOS ? '#' : 'product_details.php?id=' + product.product_id;
            
            let html = `
                <div class="equipment-card">
                    <div class="equipment-image">
                        <img src="${escapeHtml(product.image_url || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22200%22%3E%3Crect fill=%22%23f5f5f5%22 width=%22300%22 height=%22200%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2224%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22%3E📦%3C/text%3E%3C/svg%3E')}" alt="${escapeHtml(product.Name)}">
                        ${isOOS ? '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">Out of Stock</div>' : ''}
                    </div>
                    <div class="equipment-info">
                        <h3>${escapeHtml(product.Name)}</h3>
                        <p><strong>Category:</strong> ${escapeHtml(product.Category_name || 'N/A')}</p>
                        <p><strong>Type:</strong> ${escapeHtml(product.Subcategory_name || 'N/A')}</p>
                        <p><strong>Seller:</strong> ${escapeHtml(product.seller_name)}</p>
                        <p><strong>Available:</strong> <span style="color: ${qtyColor}; font-weight: bold;">${parseFloat(product.available_quantity).toFixed(1)} ${product.Unit.toUpperCase()}</span></p>
                        <div class="price-box">₹${parseFloat(product.Price).toFixed(2)}/${product.Unit.toUpperCase()}</div>
                        <a href="${url}" class="action-btn" style="background: ${btnColor}; color: ${btnText}; cursor: ${isOOS ? 'not-allowed' : 'pointer'}; opacity: ${isOOS ? '0.6' : '1'};" ${isOOS ? 'onclick="event.preventDefault(); alert(\'Out of stock\');"' : ''}>${btnLabel}</a>
                    </div>
                </div>
            `;
            
            $('#productsGrid').append(html);
        });
        
        $('#productsGrid').show();
    }

    function displayPagination(currentPage, totalPages) {
        $('#paginationControls').empty();
        
        // Previous
        if (currentPage > 1) {
            let btn = $('<a href="#" class="pagination-btn">← Previous</a>');
            btn.on('click', function(e) { e.preventDefault(); performSearch(currentPage - 1); });
            $('#paginationControls').append(btn);
        } else {
            $('#paginationControls').append('<span class="pagination-btn inactive">← Previous</span>');
        }
        
        // Pages
        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, currentPage + 2);
        
        if (start > 1) {
            let btn = $('<a href="#" class="pagination-btn page">1</a>');
            btn.on('click', function(e) { e.preventDefault(); performSearch(1); });
            $('#paginationControls').append(btn);
            if (start > 2) $('#paginationControls').append('<span style="color: #666;">...</span>');
        }
        
        for (let i = start; i <= end; i++) {
            let btn = $(`<a href="#" class="pagination-btn ${i === currentPage ? 'active' : 'page'}">${i}</a>`);
            (function(page) {
                btn.on('click', function(e) { e.preventDefault(); performSearch(page); });
            })(i);
            $('#paginationControls').append(btn);
        }
        
        if (end < totalPages) {
            if (end < totalPages - 1) $('#paginationControls').append('<span style="color: #666;">...</span>');
            let btn = $(`<a href="#" class="pagination-btn page">${totalPages}</a>`);
            btn.on('click', function(e) { e.preventDefault(); performSearch(totalPages); });
            $('#paginationControls').append(btn);
        }
        
        // Next
        if (currentPage < totalPages) {
            let btn = $('<a href="#" class="pagination-btn">Next →</a>');
            btn.on('click', function(e) { e.preventDefault(); performSearch(currentPage + 1); });
            $('#paginationControls').append(btn);
        } else {
            $('#paginationControls').append('<span class="pagination-btn inactive">Next →</span>');
        }
        
        $('#pageInfo').html(`<small>Page <strong>${currentPage}</strong> of <strong>${totalPages}</strong></small>`);
    }

    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    // Load initial products
    console.log('Loading initial products...');
    performSearch(1);
});
</script>

<?php include 'includes/footer.php'; ?>
