<?php
// staff/sell_product.php - MODIFIED: Permission check for sell_products
require_once '../config/database.php';
require_once '../includes/permissions.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];

// ============================================
// PERMISSION CHECK
// ============================================
if (!hasPermission($staff_id, 'sell_products')) {
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
            <p style="color: #aaa;">You don't have permission to sell products.</p>
            <a href="dashboard.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$message = '';
$error = '';

// ============================================
// GET CUSTOMERS FOR DROPDOWN
// ============================================
$customers_query = "SELECT id, full_name, phone FROM users WHERE role = 'customer' AND is_active = 1 ORDER BY full_name";
$customers_result = mysqli_query($conn, $customers_query);

// ============================================
// HANDLE SALE SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell_product'])) {
    // Double-check permission
    if (!hasPermission($staff_id, 'sell_products')) {
        $error = "You don't have permission to sell products.";
    } else {
        $product_id = (int)$_POST['product_id'];
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $quantity = (int)$_POST['quantity'];
        
        // Fetch product details
        $query = "SELECT * FROM products WHERE id = $product_id";
        $result = mysqli_query($conn, $query);
        $product = mysqli_fetch_assoc($result);
        
        if ($product) {
            if ($product['stock'] >= $quantity) {
                $total_price = $product['price'] * $quantity;
                
                // Update stock
                $new_stock = $product['stock'] - $quantity;
                $update = "UPDATE products SET stock = $new_stock WHERE id = $product_id";
                mysqli_query($conn, $update);
                
                // Insert sale record
                $insert = "INSERT INTO sales (product_id, staff_id, customer_id, quantity, total_price, sale_date) 
                           VALUES ($product_id, $staff_id, " . ($customer_id ? $customer_id : 'NULL') . ", $quantity, $total_price, NOW())";
                mysqli_query($conn, $insert);
                
                // Get customer info for notification
                if ($customer_id) {
                    $customer_query = "SELECT full_name, phone, email FROM users WHERE id = $customer_id";
                    $customer_result = mysqli_query($conn, $customer_query);
                    $customer = mysqli_fetch_assoc($customer_result);
                    
                    if ($customer) {
                        $message_body = "Dear {$customer['full_name']}, your purchase of {$quantity}x {$product['name']} (KSh $total_price) has been completed. Thank you for your business!";
                        sendNotification($customer_id, "Purchase Confirmation", $message_body, 'email');
                        sendSMS($customer['phone'], $message_body);
                    }
                }
                
                $message = "Sale successful! Total: KSh " . number_format($total_price, 2);
            } else {
                $error = "Insufficient stock! Available: {$product['stock']}";
            }
        } else {
            $error = "Product not found!";
        }
    }
}

// ============================================
// GET PRODUCTS
// ============================================
$products_query = "SELECT * FROM products WHERE stock > 0 ORDER BY name";
$products_result = mysqli_query($conn, $products_query);

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    /* ============================================
       HEADER WITH QUICK ACTIONS
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

    /* Form Container */
    .sell-container {
        max-width: 550px;
        margin: 0 auto;
        background: #1a1a1a;
        border-radius: 20px;
        padding: 2rem;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: #d4af37;
        font-weight: 500;
    }

    .form-group .form-control,
    .form-group select {
        width: 100%;
        padding: 12px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 1rem;
    }

    .form-group .form-control:focus,
    .form-group select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .form-group .form-control option {
        background: #1a1a1a;
    }

    .btn-primary {
        width: 100%;
        padding: 12px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 50px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    .btn-primary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
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

    .price-display {
        font-size: 1.5rem;
        font-weight: bold;
        color: #d4af37;
        margin-top: 0.5rem;
    }

    .back-link {
        display: inline-block;
        margin-top: 1rem;
        color: #d4af37;
        text-decoration: none;
        text-align: center;
        width: 100%;
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
        .sell-container { padding: 1.5rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .sell-container { padding: 1rem; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        .form-group .form-control,
        .form-group select { padding: 10px; font-size: 0.9rem; }
        .btn-primary { padding: 10px; font-size: 0.9rem; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER: Title + Quick Actions
       ============================================ -->
    <div class="page-header">
        <div class="title-section">
            <h1>🛒 Sell Product</h1>
            <p>Sell products to customers</p>
        </div>
        <div class="quick-actions">
            <a href="products.php" class="quick-btn"><i class="fas fa-box"></i> Products</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <!-- ============================================
       SELL FORM
       ============================================ -->
    <div class="sell-container">
        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
        <form method="POST">
            <div class="form-group">
                <label>Select Product</label>
                <select name="product_id" class="form-control" required id="product_select">
                    <option value="">-- Choose a product --</option>
                    <?php while ($row = mysqli_fetch_assoc($products_result)): ?>
                        <option value="<?php echo $row['id']; ?>" data-price="<?php echo $row['price']; ?>" data-stock="<?php echo $row['stock']; ?>">
                            <?php echo htmlspecialchars($row['name']); ?> - KSh <?php echo number_format($row['price'], 2); ?> (Stock: <?php echo $row['stock']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Customer (Optional)</label>
                <select name="customer_id" class="form-control">
                    <option value="">-- Walk-in customer --</option>
                    <?php while ($customer = mysqli_fetch_assoc($customers_result)): ?>
                        <option value="<?php echo $customer['id']; ?>">
                            <?php echo htmlspecialchars($customer['full_name']); ?> (<?php echo htmlspecialchars($customer['phone']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" class="form-control" min="1" required id="quantity_input">
                <div style="color: #888; font-size: 0.8rem; margin-top: 0.3rem;" id="stock_info">Available stock: --</div>
            </div>

            <div class="form-group">
                <label>Total Amount</label>
                <div class="price-display" id="total_amount">KSh 0.00</div>
            </div>

            <button type="submit" name="sell_product" class="btn-primary">💵 Complete Sale</button>
        </form>
        <?php else: ?>
            <div style="text-align: center; padding: 1rem 0;">
                <a href="sell_product.php" class="btn-primary" style="display: inline-block; width: auto; padding: 10px 30px;">🛒 Sell Another</a>
            </div>
        <?php endif; ?>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<script>
    const productSelect = document.getElementById('product_select');
    const quantityInput = document.getElementById('quantity_input');
    const totalSpan = document.getElementById('total_amount');
    const stockInfo = document.getElementById('stock_info');

    function updateTotal() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const price = parseFloat(selectedOption.getAttribute('data-price'));
        const stock = parseInt(selectedOption.getAttribute('data-stock'));
        const quantity = parseInt(quantityInput.value) || 0;
        
        if (price && quantity > 0) {
            totalSpan.innerHTML = 'KSh ' + (price * quantity).toLocaleString('en-KE', {minimumFractionDigits: 2});
        } else {
            totalSpan.innerHTML = 'KSh 0.00';
        }
        
        if (stock !== undefined) {
            stockInfo.textContent = 'Available stock: ' + stock;
            if (quantity > stock) {
                stockInfo.style.color = '#dc3545';
            } else {
                stockInfo.style.color = '#888';
            }
        }
    }

    productSelect.addEventListener('change', updateTotal);
    quantityInput.addEventListener('input', updateTotal);
</script>

<?php include '../includes/footer.php'; ?>