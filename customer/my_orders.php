<?php
// customer/my_orders.php - FIXED: No permission check, only login check
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// ============================================
// HANDLE ORDER CANCELLATION
// ============================================
if (isset($_GET['cancel'])) {
    $order_id = (int)$_GET['cancel'];
    $check_query = "SELECT id, status FROM orders WHERE id = $order_id AND user_id = $user_id AND status = 'pending'";
    $check_result = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_result) > 0) {
        $update_query = "UPDATE orders SET status = 'cancelled' WHERE id = $order_id";
        mysqli_query($conn, $update_query);
        $success = "✅ Order cancelled successfully!";
    }
}

// ============================================
// FETCH ORDERS
// ============================================
$orders_query = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY order_date DESC";
$orders_result = mysqli_query($conn, $orders_query);

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

    .order-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.15);
        margin-bottom: 1.5rem;
        transition: all 0.3s;
    }

    .order-card:hover {
        border-color: rgba(212, 175, 55, 0.3);
    }

    .order-card .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
        padding-bottom: 0.8rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
        margin-bottom: 0.8rem;
    }

    .order-card .order-header .order-id {
        font-weight: 600;
        color: white;
    }

    .order-card .order-header .order-date {
        color: #aaa;
        font-size: 0.85rem;
    }

    .order-card .order-header .order-status {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .order-status.pending { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .order-status.processing { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .order-status.shipped { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }
    .order-status.delivered { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
    .order-status.cancelled { background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid #dc3545; }

    .order-card .order-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.5rem;
        padding: 0.5rem 0;
    }

    .order-card .order-details .detail-item {
        color: #aaa;
        font-size: 0.85rem;
    }

    .order-card .order-details .detail-item strong {
        color: #ddd;
    }

    .order-items {
        margin-top: 0.8rem;
        padding-top: 0.8rem;
        border-top: 1px solid rgba(212, 175, 55, 0.1);
    }

    .order-item-row {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .order-item-row:last-child {
        border-bottom: none;
    }

    .order-item-row .item-image {
        width: 45px;
        height: 45px;
        border-radius: 6px;
        object-fit: cover;
        background: #111;
        border: 1px solid rgba(212, 175, 55, 0.1);
        flex-shrink: 0;
    }

    .order-item-row .item-image.no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #555;
        font-size: 1rem;
        background: #111;
    }

    .order-item-row .item-details {
        flex: 1;
    }

    .order-item-row .item-details .item-name {
        color: #ddd;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .order-item-row .item-details .item-meta {
        color: #7a7568;
        font-size: 0.7rem;
    }

    .order-item-row .item-price {
        color: #d4af37;
        font-size: 0.85rem;
        font-weight: 500;
        white-space: nowrap;
    }

    .order-total-display {
        text-align: right;
        padding-top: 0.8rem;
        margin-top: 0.8rem;
        border-top: 2px solid rgba(212, 175, 55, 0.2);
        font-size: 1.1rem;
    }

    .order-total-display .total-label {
        color: #aaa;
    }

    .order-total-display .total-amount {
        color: #d4af37;
        font-weight: bold;
        font-size: 1.3rem;
    }

    .order-actions {
        margin-top: 1rem;
        padding-top: 0.8rem;
        border-top: 1px solid rgba(212, 175, 55, 0.1);
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .order-actions .btn {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-cancel-order {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-cancel-order:hover {
        background: #dc3545;
        color: white;
    }

    .btn-view-order {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .btn-view-order:hover {
        background: #d4af37;
        color: #050505;
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
        .order-card { padding: 1rem; }
        .order-card .order-header { flex-direction: column; align-items: flex-start; }
        .order-card .order-details { grid-template-columns: 1fr; }
        .order-item-row .item-image { width: 35px; height: 35px; }
        .order-item-row .item-details .item-name { font-size: 0.8rem; }
        .order-item-row .item-price { font-size: 0.8rem; }
        .order-actions { flex-direction: column; }
        .order-actions .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        .order-item-row { flex-wrap: wrap; }
        .order-item-row .item-details { width: 100%; }
        .order-total-display { font-size: 1rem; }
        .order-total-display .total-amount { font-size: 1.1rem; }
    }
</style>

<div class="main-content">

    <div class="page-header">
        <div class="title-section">
            <h1>📋 My Orders</h1>
            <p>View your order history</p>
        </div>
        <div class="quick-actions">
            <a href="shop.php" class="quick-btn"><i class="fas fa-store"></i> Shop</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (mysqli_num_rows($orders_result) > 0): ?>
        <?php while($order = mysqli_fetch_assoc($orders_result)): 
            $items_query = "SELECT oi.*, p.name, p.image 
                            FROM order_items oi 
                            JOIN products p ON oi.product_id = p.id 
                            WHERE oi.order_id = {$order['id']}";
            $items_result = mysqli_query($conn, $items_query);
            $items_count = mysqli_num_rows($items_result);
        ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <span class="order-id">Order #<?php echo $order['id']; ?></span>
                        <span class="order-date">📅 <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></span>
                    </div>
                    <span class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
                </div>

                <div class="order-details">
                    <div class="detail-item"><strong>📍 Address:</strong> <?php echo htmlspecialchars($order['address']); ?></div>
                    <div class="detail-item"><strong>💳 Payment:</strong> <?php echo ucfirst($order['payment_method']); ?></div>
                    <div class="detail-item"><strong>📦 Items:</strong> <?php echo $items_count; ?> product(s)</div>
                </div>

                <div class="order-items">
                    <?php 
                    mysqli_data_seek($items_result, 0);
                    while($item = mysqli_fetch_assoc($items_result)):
                        $has_image = !empty($item['image']) && file_exists('../assets/uploads/products/' . $item['image']);
                    ?>
                        <div class="order-item-row">
                            <?php if ($has_image): ?>
                                <img src="../assets/uploads/products/<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                            <?php else: ?>
                                <div class="item-image no-image">📷</div>
                            <?php endif; ?>
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="item-meta"><?php echo $item['quantity']; ?> × KSh <?php echo number_format($item['price'], 2); ?></div>
                            </div>
                            <span class="item-price">KSh <?php echo number_format($item['quantity'] * $item['price'], 2); ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="order-total-display">
                    <span class="total-label">Total:</span>
                    <span class="total-amount">KSh <?php echo number_format($order['total_amount'], 2); ?></span>
                </div>

                <?php if ($order['status'] == 'pending'): ?>
                    <div class="order-actions">
                        <a href="my_orders.php?cancel=<?php echo $order['id']; ?>" class="btn btn-cancel-order" onclick="return confirm('Cancel this order?')">❌ Cancel Order</a>
                        <a href="#" class="btn btn-view-order">👁️ View Details</a>
                    </div>
                <?php elseif ($order['status'] != 'cancelled' && $order['status'] != 'delivered'): ?>
                    <div class="order-actions">
                        <a href="#" class="btn btn-view-order">👁️ Track Order</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="icon">📋</div>
            <h3>No Orders Yet</h3>
            <p>Start shopping to see your orders here.</p>
            <a href="shop.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">🛍️ Start Shopping</a>
        </div>
    <?php endif; ?>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>