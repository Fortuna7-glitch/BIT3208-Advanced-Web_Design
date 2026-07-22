<?php
// customer/checkout.php - FIXED: No permission check, only login check
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../auth/login.php');
}

// ============================================
// CHECK IF CART IS EMPTY
// ============================================
if (empty($_SESSION['cart'])) {
    header('Location: shop.php');
    exit();
}

// ============================================
// GET USER DETAILS
// ============================================
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// ============================================
// GET CART ITEMS WITH IMAGES
// ============================================
$cart_items = [];
$total = 0;
$error = '';

$ids = implode(',', array_keys($_SESSION['cart']));
$query = "SELECT * FROM products WHERE id IN ($ids) AND is_active = 1";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    $product_id = $row['id'];
    $quantity = $_SESSION['cart'][$product_id];
    $subtotal = $row['price'] * $quantity;
    
    if ($row['stock'] < $quantity) {
        $error = "Insufficient stock for {$row['name']}. Available: {$row['stock']}";
    }
    
    $row['quantity'] = $quantity;
    $row['subtotal'] = $subtotal;
    $row['image_exists'] = !empty($row['image']) && file_exists('../assets/uploads/products/' . $row['image']);
    $cart_items[] = $row;
    $total += $subtotal;
}

// ============================================
// HANDLE ORDER PLACEMENT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    
    mysqli_begin_transaction($conn);
    
    try {
        $salon_id = $user['salon_id'] ?? 1;
        
        $order_query = "INSERT INTO orders (user_id, salon_id, total_amount, address, payment_method, order_date, status) 
                        VALUES ($user_id, $salon_id, $total, '$address', '$payment_method', NOW(), 'pending')";
        mysqli_query($conn, $order_query);
        $order_id = mysqli_insert_id($conn);
        
        foreach ($cart_items as $item) {
            $product_id = $item['id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            
            $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                           VALUES ($order_id, $product_id, $quantity, $price)";
            mysqli_query($conn, $item_query);
            
            $new_stock = $item['stock'] - $quantity;
            $update_stock = "UPDATE products SET stock = $new_stock WHERE id = $product_id";
            mysqli_query($conn, $update_stock);
        }
        
        $_SESSION['cart'] = [];
        
        mysqli_commit($conn);
        
        $message = "Dear {$user['full_name']}, your order #$order_id has been placed successfully. Total: KSh " . number_format($total, 2);
        sendNotification($user_id, "Order Confirmation", $message, 'email');
        sendSMS($user['phone'], $message);
        
        header("Location: my_orders.php?success=1");
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Order failed! Please try again.";
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

    .checkout-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }

    .checkout-section {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }

    .checkout-section h2 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    .order-summary-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.6rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .order-summary-item:last-child {
        border-bottom: none;
    }

    .order-summary-item .item-image {
        width: 50px;
        height: 50px;
        border-radius: 6px;
        object-fit: cover;
        background: #111;
        border: 1px solid rgba(212, 175, 55, 0.1);
        flex-shrink: 0;
    }

    .order-summary-item .item-image.no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #555;
        font-size: 1.2rem;
        background: #111;
    }

    .order-summary-item .item-details {
        flex: 1;
    }

    .order-summary-item .item-details .item-name {
        color: #f5f0e1;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .order-summary-item .item-details .item-meta {
        color: #7a7568;
        font-size: 0.75rem;
    }

    .order-summary-item .item-price {
        color: #d4af37;
        font-weight: 600;
        font-size: 0.9rem;
        white-space: nowrap;
    }

    .order-total {
        display: flex;
        justify-content: space-between;
        padding-top: 1rem;
        margin-top: 1rem;
        border-top: 2px solid rgba(212, 175, 55, 0.3);
        font-size: 1.2rem;
    }

    .order-total .total-label {
        color: #aaa;
        font-weight: 600;
    }

    .order-total .total-amount {
        color: #d4af37;
        font-weight: bold;
        font-size: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.2rem;
    }

    .form-group label {
        display: block;
        color: #d4af37;
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .form-group .form-control,
    .form-group select {
        width: 100%;
        padding: 10px 12px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 0.95rem;
    }

    .form-group .form-control:focus,
    .form-group select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .form-group textarea.form-control {
        resize: vertical;
        min-height: 80px;
        font-family: inherit;
    }

    .btn-place-order {
        width: 100%;
        padding: 14px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 50px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 0.5rem;
    }

    .btn-place-order:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    .btn-place-order:disabled {
        opacity: 0.3;
        cursor: not-allowed;
        transform: none;
    }

    .btn-back {
        display: inline-block;
        padding: 10px 20px;
        background: #2a2a2a;
        color: #aaa;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        text-align: center;
    }

    .btn-back:hover {
        background: #333;
        color: white;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid #dc3545;
        color: #dc3545;
    }

    @media (max-width: 1024px) {
        .checkout-container {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        .page-header .title-section h1 { font-size: 1.2rem; }
        .checkout-section { padding: 1rem; }
        .order-total { font-size: 1rem; }
        .order-total .total-amount { font-size: 1.2rem; }
        .order-summary-item .item-image { width: 40px; height: 40px; }
        .order-summary-item .item-details .item-name { font-size: 0.8rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .checkout-section { padding: 0.8rem; }
        .form-group .form-control,
        .form-group select { font-size: 0.85rem; padding: 8px 10px; }
        .btn-place-order { font-size: 0.9rem; padding: 12px; }
        .order-summary-item .item-image { width: 35px; height: 35px; font-size: 0.8rem; }
        .order-summary-item .item-details .item-name { font-size: 0.75rem; }
        .order-summary-item .item-details .item-meta { font-size: 0.65rem; }
        .order-summary-item .item-price { font-size: 0.8rem; }
    }
</style>

<div class="main-content">

    <div class="page-header">
        <div class="title-section">
            <h1>💳 Checkout</h1>
            <p>Review your order and complete purchase</p>
        </div>
        <a href="cart.php" class="btn-back">← Back to Cart</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!$error): ?>
        <div class="checkout-container">

            <div class="checkout-section">
                <h2>📋 Order Summary</h2>
                <?php foreach ($cart_items as $item): ?>
                    <div class="order-summary-item">
                        <?php if ($item['image_exists']): ?>
                            <img src="../assets/uploads/products/<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                        <?php else: ?>
                            <div class="item-image no-image">📷</div>
                        <?php endif; ?>
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-meta"><?php echo $item['quantity']; ?> × KSh <?php echo number_format($item['price'], 2); ?></div>
                        </div>
                        <span class="item-price">KSh <?php echo number_format($item['subtotal'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="order-total">
                    <span class="total-label">Total</span>
                    <span class="total-amount">KSh <?php echo number_format($total, 2); ?></span>
                </div>
            </div>

            <div class="checkout-section">
                <h2>✏️ Shipping & Payment</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Delivery Address</label>
                        <textarea name="address" class="form-control" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="">-- Select Payment Method --</option>
                            <option value="cash">💵 Cash on Delivery</option>
                            <option value="mpesa">📱 M-PESA</option>
                            <option value="card">💳 Credit/Debit Card</option>
                            <option value="bank">🏦 Bank Transfer</option>
                        </select>
                    </div>

                    <button type="submit" name="place_order" class="btn-place-order">✅ Place Order</button>
                </form>
            </div>

        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 1rem;">
            <a href="cart.php" class="btn-back">← Return to Cart</a>
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>