<?php
/**
 * Salon Pro — Staff: Products View
 * Luxury gold/black theme
 * Staff can view products if they have 'view_inventory' permission
 * Read-only access (no add/edit/delete)
 * Updated: Now shows product images
 */

require_once '../config/database.php';
require_once '../includes/permissions.php';

// Authentication check
if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['user_name'] ?? 'Staff';

// ============================================
// PERMISSION CHECK
// ============================================
if (!hasPermission($staff_id, 'view_inventory')) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Permission Denied</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
        <div style="text-align: center; padding: 3rem; background: #1a1a1a; border-radius: 15px; border: 1px solid rgba(212, 175, 55, 0.2); max-width: 500px; margin: 3rem auto;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
            <h2 style="color: #dc3545;">Permission Denied</h2>
            <p style="color: #aaa;">You don't have permission to view products/inventory.</p>
            <a href="dashboard.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Get salon_id from session
$salon_id = getCurrentSalonId();

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';

// ============================================
// FETCH PRODUCTS
// ============================================
$query = "SELECT * FROM products WHERE salon_id = $salon_id AND is_active = 1";
if ($search) {
    $query .= " AND (name LIKE '%$search%' OR description LIKE '%$search%' OR category LIKE '%$search%')";
}
if ($category) {
    $query .= " AND category = '$category'";
}
if ($stock_filter == 'in_stock') {
    $query .= " AND stock > 0";
} elseif ($stock_filter == 'out_of_stock') {
    $query .= " AND stock <= 0";
} elseif ($stock_filter == 'low_stock') {
    $query .= " AND stock > 0 AND stock <= 5";
}
$query .= " ORDER BY name ASC";
$products_result = mysqli_query($conn, $query);

// Get categories for filter
$cat_query = "SELECT DISTINCT category FROM products WHERE salon_id = $salon_id AND category IS NOT NULL AND category != '' AND is_active = 1";
$cat_result = mysqli_query($conn, $cat_query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(stock) as total_stock,
    SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) as in_stock,
    SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END) as low_stock,
    SUM(price * stock) as inventory_value
    FROM products WHERE salon_id = $salon_id AND is_active = 1";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Check if staff has permission to sell products
$can_sell = hasPermission($staff_id, 'sell_products');

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 0 2rem 2rem;
        background: #0a0a0a;
        min-height: 100vh;
        margin-top: 0.5rem;
    }

    /* ============================================
       HEADER
       ============================================ */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .page-header .title-section h1 {
        color: #d4af37;
        font-size: 1.3rem;
        font-weight: 600;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    .page-header .title-section p {
        color: #aaa;
        font-size: 0.85rem;
        margin-top: 0.2rem;
        padding-left: 1rem;
    }

    .page-header .quick-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .page-header .quick-actions .quick-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 8px 16px;
        border-radius: 25px;
        font-size: 0.75rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        background: rgba(212, 175, 55, 0.1);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }

    .page-header .quick-actions .quick-btn:hover {
        background: #d4af37;
        color: #050505;
        transform: translateY(-2px);
    }

    .page-header .quick-actions .quick-btn i {
        font-size: 0.8rem;
    }

    .page-header .search-section {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 1 280px;
        min-width: 160px;
    }

    .page-header .search-section input {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: white;
        font-size: 0.85rem;
        width: 100%;
        transition: all 0.3s;
    }

    .page-header .search-section input:focus {
        outline: none;
        border-color: #d4af37;
        box-shadow: 0 0 20px rgba(212, 175, 55, 0.1);
    }

    .page-header .search-section input::placeholder {
        color: #666;
    }

    .page-header .search-section .search-btn {
        padding: 8px 14px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .page-header .search-section .search-btn:hover {
        background: #f9e547;
    }

    /* ============================================
       STATS GRID
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        text-align: center;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
    }

    .stat-card .stat-icon {
        font-size: 1.5rem;
        opacity: 0.2;
        position: absolute;
        right: 1rem;
        top: 1rem;
    }

    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: bold;
        color: #d4af37;
    }

    .stat-card .stat-label {
        color: #aaa;
        font-size: 0.75rem;
        margin-top: 0.2rem;
    }

    .stat-card.green { border-left-color: #28a745; }
    .stat-card.green .stat-number { color: #28a745; }
    .stat-card.orange { border-left-color: #ffc107; }
    .stat-card.orange .stat-number { color: #ffc107; }
    .stat-card.red { border-left-color: #dc3545; }
    .stat-card.red .stat-number { color: #dc3545; }

    /* ============================================
       FILTER BAR
       ============================================ */
    .filter-bar {
        display: flex;
        gap: 0.8rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        align-items: center;
        background: #1a1a1a;
        padding: 0.8rem 1.2rem;
        border-radius: 12px;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .filter-bar input,
    .filter-bar select {
        padding: 8px 14px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: white;
        font-size: 0.85rem;
        min-width: 130px;
    }

    .filter-bar input:focus,
    .filter-bar select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .filter-bar .filter-btn {
        padding: 8px 20px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .filter-bar .filter-btn:hover {
        background: #f9e547;
    }

    .filter-bar .clear-btn {
        padding: 8px 20px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #aaa;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        white-space: nowrap;
    }

    .filter-bar .clear-btn:hover {
        background: #333;
        color: white;
    }

    /* ============================================
       PRODUCT GRID
       ============================================ */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1.2rem;
    }

    .product-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1.2rem;
        border: 1px solid rgba(212, 175, 55, 0.1);
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
    }

    .product-card:hover {
        transform: translateY(-4px);
        border-color: rgba(212, 175, 55, 0.3);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
    }

    .product-card .product-image {
        width: 100%;
        height: 180px;
        background: #111;
        border-radius: 8px;
        margin-bottom: 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .product-card .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .product-card .product-image .no-image {
        color: #555;
        font-size: 2rem;
        opacity: 0.3;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.3rem;
    }

    .product-card .product-image .no-image span {
        font-size: 0.6rem;
        color: #444;
    }

    .product-card .product-name {
        font-weight: 600;
        font-size: 1rem;
        color: white;
        margin-bottom: 0.2rem;
    }

    .product-card .product-category {
        font-size: 0.7rem;
        color: #d4af37;
        margin-bottom: 0.4rem;
    }

    .product-card .product-price {
        font-size: 1.2rem;
        font-weight: bold;
        color: #d4af37;
    }

    .product-card .product-stock {
        font-size: 0.8rem;
        color: #888;
        margin-top: 0.3rem;
    }

    .product-card .product-stock.low {
        color: #ffc107;
        font-weight: 600;
    }

    .product-card .product-stock.out {
        color: #dc3545;
        font-weight: 600;
    }

    .product-card .product-actions {
        margin-top: 0.8rem;
        padding-top: 0.8rem;
        border-top: 1px solid rgba(212, 175, 55, 0.1);
        display: flex;
        gap: 0.5rem;
    }

    .product-card .product-actions .btn {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        flex: 1;
        text-align: center;
    }

    .btn-sell {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-sell:hover {
        background: #28a745;
        color: white;
    }

    .btn-sell:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }

    .btn-view {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .btn-view:hover {
        background: #d4af37;
        color: #050505;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #666;
        grid-column: 1 / -1;
    }

    .empty-state .icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #d4af37;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        .page-header .title-section h1 { font-size: 1.1rem; }
        .page-header .quick-actions { justify-content: flex-start; }
        .page-header .search-section { flex: 1; }
        .page-header .search-section input { width: 100%; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar input,
        .filter-bar select { width: 100%; }
        .products-grid { grid-template-columns: 1fr 1fr; }
        .product-card .product-image { height: 140px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .products-grid { grid-template-columns: 1fr; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        .product-card .product-image { height: 180px; }
        .product-card .product-actions { flex-direction: column; }
        .product-card .product-actions .btn { width: 100%; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER
       ============================================ -->
    <div class="page-header">
        <div class="title-section">
            <h1>📦 Products (View Only)</h1>
            <p>Browse available products in your salon</p>
        </div>
        <div class="quick-actions">
            <?php if ($can_sell): ?>
                <a href="sell_product.php" class="quick-btn"><i class="fas fa-cash-register"></i> Sell</a>
            <?php endif; ?>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
        <div class="search-section">
            <form method="GET" style="display: flex; gap: 0.5rem; width: 100%;">
                <input type="text" name="q" placeholder="🔍 Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>
    </div>

    <!-- ============================================
       STATS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">📦</span>
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number"><?php echo $stats['in_stock'] ?? 0; ?></div>
            <div class="stat-label">In Stock</div>
        </div>
        <div class="stat-card orange">
            <span class="stat-icon">⚠️</span>
            <div class="stat-number"><?php echo $stats['low_stock'] ?? 0; ?></div>
            <div class="stat-label">Low Stock</div>
        </div>
        <div class="stat-card red">
            <span class="stat-icon">🚫</span>
            <div class="stat-number"><?php echo $stats['out_of_stock'] ?? 0; ?></div>
            <div class="stat-label">Out of Stock</div>
        </div>
    </div>

    <!-- ============================================
       FILTER BAR
       ============================================ -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap; align-items: center;">
            <input type="text" name="q" placeholder="🔍 Search products..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php while($cat = mysqli_fetch_assoc($cat_result)): ?>
                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($category == $cat['category']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="stock">
                <option value="">All Stock</option>
                <option value="in_stock" <?php echo ($stock_filter == 'in_stock') ? 'selected' : ''; ?>>In Stock</option>
                <option value="low_stock" <?php echo ($stock_filter == 'low_stock') ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out_of_stock" <?php echo ($stock_filter == 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="products.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <!-- ============================================
       PRODUCT GRID WITH IMAGES
       ============================================ -->
    <div class="products-grid">
        <?php if (mysqli_num_rows($products_result) > 0): ?>
            <?php while ($product = mysqli_fetch_assoc($products_result)): 
                $stock_class = '';
                $stock_label = 'In Stock';
                if ($product['stock'] <= 0) {
                    $stock_class = 'out';
                    $stock_label = 'Out of Stock';
                } elseif ($product['stock'] <= 5) {
                    $stock_class = 'low';
                    $stock_label = 'Low Stock (' . $product['stock'] . ' left)';
                }
                
                // Check if image exists
                $has_image = !empty($product['image']) && file_exists('../assets/uploads/products/' . $product['image']);
            ?>
                <div class="product-card">
                    <!-- Product Image -->
                    <div class="product-image">
                        <?php if ($has_image): ?>
                            <img src="../assets/uploads/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="no-image">
                                📷
                                <span>No Image</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    <div class="product-category"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></div>
                    <div class="product-price">KSh <?php echo number_format($product['price'], 2); ?></div>
                    <div class="product-stock <?php echo $stock_class; ?>">
                        <?php echo $stock_label; ?>
                        <?php if ($product['stock'] > 0): ?>
                            <span style="color: #888; font-size: 0.7rem;">(<?php echo $product['stock']; ?> available)</span>
                        <?php endif; ?>
                    </div>

                    <div class="product-actions">
                        <?php if ($can_sell && $product['stock'] > 0): ?>
                            <a href="sell_product.php?product=<?php echo $product['id']; ?>" class="btn btn-sell">🛒 Sell</a>
                        <?php endif; ?>
                        <a href="#" class="btn btn-view">👁️ View</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">📦</div>
                <p>No products found matching your criteria.</p>
            </div>
        <?php endif; ?>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>