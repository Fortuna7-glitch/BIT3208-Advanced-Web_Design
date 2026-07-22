<?php
// customer/cart.php - FIXED: No permission check, only login check
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../auth/login.php');
}

// ============================================
// INITIALIZE CART
// ============================================
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ============================================
// HANDLE UPDATE CART
// ============================================
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $product_id => $quantity) {
        $quantity = (int)$quantity;
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$product_id]);
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
    }
    $message = "🔄 Cart updated!";
}

// ============================================
// HANDLE REMOVE ITEM
// ============================================
if (isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$product_id]);
    header('Location: cart.php');
    exit();
}

// ============================================
// GET CART ITEMS WITH IMAGES
// ============================================
$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    $query = "SELECT * FROM products WHERE id IN ($ids) AND is_active = 1";
    $result = mysqli_query($conn, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $product_id = $row['id'];
        $quantity = $_SESSION['cart'][$product_id];
        $subtotal = $row['price'] * $quantity;
        $row['quantity'] = $quantity;
        $row['subtotal'] = $subtotal;
        $row['image_exists'] = !empty($row['image']) && file_exists('../assets/uploads/products/' . $row['image']);
        $cart_items[] = $row;
        $total += $subtotal;
    }
}

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
    }

    .page-header .quick-actions .quick-btn:hover {
        background: #d4af37;
        color: #050505;
        transform: translateY(-2px);
    }

    .cart-container {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }

    .table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        min-width: 700px;
    }

    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        vertical-align: middle;
    }

    th {
        color: #d4af37;
        font-weight: 600;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .cart-item-image {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        object-fit: cover;
        background: #111;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .cart-item-image.no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #555;
        font-size: 1.5rem;
        background: #111;
    }

    .cart-item-name {
        font-weight: 500;
        color: #f5f0e1;
    }

    .cart-item-name small {
        display: block;
        color: #7a7568;
        font-size: 0.7rem;
        font-weight: 400;
    }

    .cart-total {
        text-align: right;
        font-size: 1.2rem;
        padding-top: 1rem;
        margin-top: 1rem;
        border-top: 2px solid rgba(212, 175, 55, 0.3);
    }

    .cart-total .total-label {
        color: #aaa;
    }

    .cart-total .total-amount {
        color: #d4af37;
        font-weight: bold;
        font-size: 1.5rem;
    }

    .cart-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }

    .cart-actions .btn {
        padding: 10px 25px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        text-align: center;
    }

    .btn-update {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-update:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-checkout {
        background: #d4af37;
        color: #050505;
        flex: 1;
        min-width: 150px;
    }

    .btn-checkout:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    .btn-checkout:disabled {
        opacity: 0.3;
        cursor: not-allowed;
        transform: none;
    }

    .btn-shop {
        background: #2a2a2a;
        color: #aaa;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }

    .btn-shop:hover {
        background: #333;
        color: white;
    }

    .btn-remove {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        text-decoration: none;
        transition: all 0.3s;
    }

    .btn-remove:hover {
        background: #dc3545;
        color: white;
    }

    .quantity-input {
        width: 60px;
        padding: 6px 8px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 5px;
        color: white;
        text-align: center;
        font-size: 0.85rem;
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

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #666;
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
        .cart-container { padding: 1rem; }
        table { font-size: 0.8rem; min-width: 500px; }
        th, td { padding: 8px; }
        .cart-item-image { width: 40px; height: 40px; }
        .cart-actions { flex-direction: column; }
        .cart-actions .btn { width: 100%; text-align: center; }
        .quantity-input { width: 50px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        table { font-size: 0.7rem; min-width: 400px; }
        th, td { padding: 6px; }
        .cart-item-image { width: 35px; height: 35px; font-size: 0.8rem; }
        .quantity-input { width: 40px; padding: 4px; }
    }
</style>

<div class="main-content">

    <div class="page-header">
        <div class="title-section">
            <h1>🛒 Your Cart</h1>
            <p><?php echo count($cart_items); ?> item(s) in your cart</p>
        </div>
        <div class="quick-actions">
            <a href="shop.php" class="quick-btn"><i class="fas fa-store"></i> Continue Shopping</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success">✅ <?php echo $message; ?></div>
    <?php endif; ?>

    <div class="cart-container">
        <?php if (empty($cart_items)): ?>
            <div class="empty-state">
                <div class="icon">🛒</div>
                <h3>Your Cart is Empty</h3>
                <p>Browse our products and add items to your cart.</p>
                <a href="shop.php" class="btn btn-shop" style="display: inline-block; margin-top: 1rem;">Start Shopping</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.8rem;">
                                            <?php if ($item['image_exists']): ?>
                                                <img src="../assets/uploads/products/<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image">
                                            <?php else: ?>
                                                <div class="cart-item-image no-image">📷</div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <small><?php echo htmlspecialchars($item['category'] ?? ''); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>KSh <?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <input type="number" name="quantity[<?php echo $item['id']; ?>]" 
                                               class="quantity-input" value="<?php echo $item['quantity']; ?>" 
                                               min="0" max="<?php echo $item['stock']; ?>">
                                    </td>
                                    <td>KSh <?php echo number_format($item['subtotal'], 2); ?></td>
                                    <td>
                                        <a href="cart.php?remove=<?php echo $item['id']; ?>" class="btn-remove" onclick="return confirm('Remove this item?')">✕ Remove</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cart-total">
                    <span class="total-label">Total:</span>
                    <span class="total-amount">KSh <?php echo number_format($total, 2); ?></span>
                </div>

                <div class="cart-actions">
                    <button type="submit" name="update_cart" class="btn btn-update">🔄 Update Cart</button>
                    <a href="checkout.php" class="btn btn-checkout">💳 Proceed to Checkout</a>
                    <a href="shop.php" class="btn btn-shop">Continue Shopping</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>