<?php
// customer/shop.php - FIXED: No permission check, only login check
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../auth/login.php');
}

// ============================================
// INITIALIZE CART IF NOT EXISTS
// ============================================
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ============================================
// HANDLE ADD TO CART
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Check product exists and has stock
    $query = "SELECT * FROM products WHERE id = $product_id AND stock > 0 AND is_active = 1";
    $result = mysqli_query($conn, $query);
    $product = mysqli_fetch_assoc($result);
    
    if ($product) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
        $success = "✅ Product added to cart!";
    } else {
        $error = "❌ Product not available!";
    }
}

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';

// ============================================
// FETCH PRODUCTS
// ============================================
$query = "SELECT * FROM products WHERE stock > 0 AND is_active = 1";
if ($search) {
    $query .= " AND (name LIKE '%$search%' OR description LIKE '%$search%' OR category LIKE '%$search%')";
}
if ($category) {
    $query .= " AND category = '$category'";
}
$query .= " ORDER BY name";
$result = mysqli_query($conn, $query);

// Get categories for filter
$cat_query = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND stock > 0 AND is_active = 1";
$cat_result = mysqli_query($conn, $cat_query);

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

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
        font-size: 1.5rem;
        font-family: 'Playfair Display', serif;
    }

    .page-header .title-section p {
        color: #aaa;
        font-size: 0.85rem;
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
        position: relative;
    }

    .page-header .quick-actions .quick-btn:hover {
        background: #d4af37;
        color: #050505;
        transform: translateY(-2px);
    }

    .page-header .quick-actions .quick-btn .badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #dc3545;
        color: white;
        font-size: 0.55rem;
        font-weight: bold;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

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
        flex: 1;
        min-width: 150px;
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

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.2);
        border: 1px solid #28a745;
        color: #28a745;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid #dc3545;
        color: #dc3545;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1.5rem;
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
        transform: translateY(-6px);
        border-color: rgba(212, 175, 55, 0.4);
        box-shadow: 0 10px 30px rgba(212, 175, 55, 0.1);
    }

    .product-card .product-image {
        width: 100%;
        height: 200px;
        background: #111;
        border-radius: 8px;
        margin-bottom: 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid rgba(212, 175, 55, 0.08);
    }

    .product-card .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .product-card .product-image .no-image {
        color: #555;
        font-size: 2.5rem;
        opacity: 0.3;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.3rem;
    }

    .product-card .product-image .no-image span {
        font-size: 0.7rem;
        color: #444;
    }

    .product-card .product-name {
        font-weight: 600;
        font-size: 1.05rem;
        color: white;
        margin-bottom: 0.2rem;
    }

    .product-card .product-category {
        font-size: 0.7rem;
        color: #d4af37;
        margin-bottom: 0.4rem;
    }

    .product-card .product-price {
        font-size: 1.3rem;
        font-weight: bold;
        color: #d4af37;
        margin-top: 0.2rem;
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

    .product-card .product-actions {
        margin-top: 0.8rem;
        display: flex;
        gap: 0.5rem;
        border-top: 1px solid rgba(212, 175, 55, 0.1);
        padding-top: 0.8rem;
    }

    .product-card .product-actions form {
        display: flex;
        gap: 0.5rem;
        flex: 1;
        flex-wrap: wrap;
    }

    .product-card .product-actions input[type="number"] {
        width: 55px;
        padding: 6px 8px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 5px;
        color: white;
        font-size: 0.85rem;
        text-align: center;
    }

    .product-card .product-actions .btn {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        flex: 1;
    }

    .btn-cart {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-cart:hover {
        background: #d4af37;
        color: #050505;
        transform: scale(1.02);
    }

    .btn-cart:disabled {
        opacity: 0.3;
        cursor: not-allowed;
        transform: none;
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

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        .page-header .title-section h1 { font-size: 1.2rem; }
        .page-header .quick-actions { justify-content: flex-start; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar input,
        .filter-bar select { min-width: auto; width: 100%; }
        .products-grid { grid-template-columns: 1fr 1fr; }
        .product-card .product-image { height: 160px; }
        .product-card .product-actions form { flex-direction: column; align-items: stretch; }
        .product-card .product-actions input[type="number"] { width: 100%; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .products-grid { grid-template-columns: 1fr; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        .product-card .product-image { height: 200px; }
    }
</style>

<div class="main-content">

    <div class="page-header">
        <div class="title-section">
            <h1>🛍️ Shop</h1>
            <p>Browse our premium products</p>
        </div>
        <div class="quick-actions">
            <a href="cart.php" class="quick-btn">
                <i class="fas fa-shopping-cart"></i> Cart
                <?php if (isset($_SESSION['cart']) && array_sum($_SESSION['cart']) > 0): ?>
                    <span class="badge"><?php echo array_sum($_SESSION['cart']); ?></span>
                <?php endif; ?>
            </a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap;">
            <input type="text" name="q" placeholder="🔍 Search products..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php while($cat = mysqli_fetch_assoc($cat_result)): ?>
                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($category == $cat['category']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="filter-btn">Search</button>
            <a href="shop.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <div class="products-grid">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): 
                $stock_class = '';
                if ($row['stock'] <= 5) {
                    $stock_class = 'low';
                }
                $has_image = !empty($row['image']) && file_exists('../assets/uploads/products/' . $row['image']);
            ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($has_image): ?>
                            <img src="../assets/uploads/products/<?php echo $row['image']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-image">
                                📷
                                <span>No Image</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="product-name"><?php echo htmlspecialchars($row['name']); ?></div>
                    <div class="product-category"><?php echo htmlspecialchars($row['category'] ?? 'Uncategorized'); ?></div>
                    <div class="product-price">KSh <?php echo number_format($row['price'], 2); ?></div>
                    <div class="product-stock <?php echo $stock_class; ?>">
                        <?php echo $row['stock']; ?> in stock
                    </div>

                    <div class="product-actions">
                        <form method="POST">
                            <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $row['stock']; ?>">
                            <button type="submit" name="add_to_cart" class="btn btn-cart" <?php echo $row['stock'] <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-cart-plus"></i> Add
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🛍️</div>
                <p>No products available at the moment.</p>
                <p style="font-size: 0.85rem; color: #555;">Check back later for new arrivals!</p>
            </div>
        <?php endif; ?>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>