<?php
/**
 * Salon Pro — Admin: Product & Inventory Management
 * Luxury gold/black theme
 * Admin can manage products for their salon only
 * Includes product image upload
 */

require_once '../config/database.php';
require_once '../includes/permissions.php';

// Authentication check
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Get salon_id from session
$salon_id = getCurrentSalonId();

$error = '';
$success = '';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// IMAGE UPLOAD FUNCTION
// ============================================
function uploadProductImage($file, $product_id) {
    $target_dir = '../assets/uploads/products/';
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] != 0) {
        return null; // No image uploaded or error
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        return 'error: Only JPG, PNG, WEBP, and GIF images are allowed.';
    }
    
    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return 'error: Image size must be less than 2MB.';
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . $product_id . '_' . time() . '.' . $extension;
    $target_file = $target_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $filename;
    }
    
    return 'error: Failed to upload image.';
}

// ============================================
// HANDLE ACTIONS
// ============================================

// Add or Update Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_product'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $reorder_level = (int)$_POST['reorder_level'];
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    
    $image_path = null;
    
    // Handle image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $upload_result = uploadProductImage($_FILES['product_image'], $product_id);
        if (strpos($upload_result, 'error:') === 0) {
            $error = $upload_result;
        } else {
            $image_path = $upload_result;
        }
    }
    
    // If no error from upload, proceed
    if (empty($error)) {
        if ($product_id > 0) {
            // UPDATE existing product
            $update_query = "UPDATE products SET 
                            name = '$name',
                            description = '$description',
                            category = '$category',
                            price = '$price',
                            stock = $stock,
                            reorder_level = $reorder_level,
                            sku = '$sku',
                            supplier = '$supplier'";
            
            // Add image to update if uploaded
            if ($image_path !== null) {
                // Delete old image if exists
                $old_image_query = "SELECT image FROM products WHERE id = $product_id AND salon_id = $salon_id";
                $old_image_result = mysqli_query($conn, $old_image_query);
                $old_image = mysqli_fetch_assoc($old_image_result);
                if ($old_image && !empty($old_image['image'])) {
                    $old_file = '../assets/uploads/products/' . $old_image['image'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                $update_query .= ", image = '$image_path'";
            }
            
            $update_query .= " WHERE id = $product_id AND salon_id = $salon_id";
            
            if (mysqli_query($conn, $update_query)) {
                logAudit('product_updated', 'inventory', "Updated product: $name (ID: $product_id)", $admin_id);
                $success = "Product updated successfully!";
                $edit_id = 0;
            } else {
                $error = "Failed to update product: " . mysqli_error($conn);
            }
        } else {
            // INSERT new product
            $insert_query = "INSERT INTO products (name, description, category, price, stock, reorder_level, sku, supplier, salon_id, image) 
                            VALUES ('$name', '$description', '$category', '$price', $stock, $reorder_level, '$sku', '$supplier', $salon_id, ";
            
            if ($image_path !== null) {
                $insert_query .= "'$image_path'";
            } else {
                $insert_query .= "NULL";
            }
            $insert_query .= ")";
            
            if (mysqli_query($conn, $insert_query)) {
                $product_id = mysqli_insert_id($conn);
                
                // If image was uploaded, rename file to include correct product ID
                if ($image_path !== null) {
                    $old_file = '../assets/uploads/products/' . $image_path;
                    $new_filename = 'product_' . $product_id . '_' . time() . '.' . pathinfo($image_path, PATHINFO_EXTENSION);
                    $new_file = '../assets/uploads/products/' . $new_filename;
                    if (file_exists($old_file)) {
                        rename($old_file, $new_file);
                        mysqli_query($conn, "UPDATE products SET image = '$new_filename' WHERE id = $product_id");
                    }
                }
                
                logAudit('product_created', 'inventory', "Created product: $name (ID: $product_id)", $admin_id);
                $success = "Product added successfully!";
            } else {
                $error = "Failed to add product: " . mysqli_error($conn);
            }
        }
    }
}

// Delete Product
if (isset($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    $product_query = "SELECT name, image FROM products WHERE id = $product_id AND salon_id = $salon_id";
    $product_result = mysqli_query($conn, $product_query);
    if ($product = mysqli_fetch_assoc($product_result)) {
        // Delete image file
        if (!empty($product['image'])) {
            $file = '../assets/uploads/products/' . $product['image'];
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $delete_query = "DELETE FROM products WHERE id = $product_id AND salon_id = $salon_id";
        if (mysqli_query($conn, $delete_query)) {
            logAudit('product_deleted', 'inventory', "Deleted product: {$product['name']} (ID: $product_id)", $admin_id);
            $success = "Product deleted successfully!";
        } else {
            $error = "Failed to delete product: " . mysqli_error($conn);
        }
    } else {
        $error = "Product not found.";
    }
}

// Restock Product
if (isset($_GET['restock'])) {
    $product_id = (int)$_GET['restock'];
    $quantity = isset($_GET['qty']) ? (int)$_GET['qty'] : 10;
    $update_query = "UPDATE products SET stock = stock + $quantity WHERE id = $product_id AND salon_id = $salon_id";
    if (mysqli_query($conn, $update_query)) {
        logAudit('product_restocked', 'inventory', "Restocked product ID $product_id with $quantity units", $admin_id);
        $success = "Stock updated! Added $quantity units.";
    } else {
        $error = "Failed to update stock: " . mysqli_error($conn);
    }
}

// Toggle Product Status
if (isset($_GET['toggle'])) {
    $product_id = (int)$_GET['toggle'];
    $update_query = "UPDATE products SET is_active = NOT is_active WHERE id = $product_id AND salon_id = $salon_id";
    if (mysqli_query($conn, $update_query)) {
        logAudit('product_toggled', 'inventory', "Toggled status for product ID $product_id", $admin_id);
        $success = "Product status updated!";
    } else {
        $error = "Failed to update status: " . mysqli_error($conn);
    }
}

// ============================================
// GET EDIT DATA
// ============================================
$edit_product = null;
if ($edit_id > 0) {
    $edit_query = "SELECT * FROM products WHERE id = $edit_id AND salon_id = $salon_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_product = mysqli_fetch_assoc($edit_result);
    if (!$edit_product) {
        $edit_id = 0;
        $error = "Product not found.";
    }
}

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';

// ============================================
// GET PRODUCTS
// ============================================
$query = "SELECT * FROM products WHERE salon_id = $salon_id";
if ($search) {
    $query .= " AND (name LIKE '%$search%' OR description LIKE '%$search%' OR sku LIKE '%$search%' OR category LIKE '%$search%')";
}
if ($category_filter) {
    $query .= " AND category = '$category_filter'";
}
if ($stock_filter == 'full') {
    $query .= " AND stock > reorder_level";
} elseif ($stock_filter == 'low') {
    $query .= " AND stock <= reorder_level AND stock > 0";
} elseif ($stock_filter == 'out') {
    $query .= " AND stock <= 0";
}
$query .= " ORDER BY name ASC";
$products_result = mysqli_query($conn, $query);

// Get categories for filter
$cat_query = "SELECT DISTINCT category FROM products WHERE salon_id = $salon_id AND category IS NOT NULL AND category != ''";
$cat_result = mysqli_query($conn, $cat_query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(stock) as total_stock,
    SUM(CASE WHEN stock > reorder_level THEN 1 ELSE 0 END) as full_stock,
    SUM(CASE WHEN stock <= reorder_level AND stock > 0 THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(price * stock) as inventory_value
    FROM products WHERE salon_id = $salon_id";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get unread notification count
$unread_count = 0;
if (function_exists('getUnreadNotificationCount')) {
    $unread_count = getUnreadNotificationCount();
}

include '../includes/header.php';
?>

<style>
    /* ============================================
       EXISTING STYLES (same as before)
       ============================================ */
    .main-content {
        padding: 0 2rem 2rem;
        background: #0a0a0a;
        min-height: 100vh;
        margin-top: 0.5rem;
    }

    .sticky-header {
        position: sticky;
        top: 65px;
        z-index: 100;
        background: #0a0a0a;
        padding: 0.5rem 0 0.8rem 0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.08);
    }

    .top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.2rem 0;
    }

    .top-bar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 0 0 auto;
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: #b8b2a0;
        font-size: 0.9rem;
    }
    .breadcrumb .current {
        color: #f0d878;
        font-weight: 600;
    }
    .breadcrumb .sep {
        color: #7a7568;
    }
    .breadcrumb .sub {
        color: #7a7568;
    }
    .breadcrumb .menu-icon {
        font-size: 1.3rem;
        color: #d4af37;
        cursor: pointer;
    }

    .top-bar-center {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        flex-wrap: wrap;
        flex: 1 1 auto;
        justify-content: center;
    }

    .quick-links {
        display: flex;
        align-items: center;
        gap: 0.1rem;
        flex-wrap: wrap;
    }

    .quick-links .link-sep {
        color: #7a7568;
        font-size: 0.7rem;
        opacity: 0.4;
        font-weight: 100;
    }

    .quick-links .qlink {
        color: #b8b2a0;
        text-decoration: none;
        font-size: 0.8rem;
        padding: 0.3rem 0.7rem;
        border-radius: 20px;
        transition: all 0.3s ease;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .quick-links .qlink:hover {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.08);
        border-color: rgba(212, 175, 55, 0.15);
    }

    .quick-links .qlink.active {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.12);
        border-color: rgba(212, 175, 55, 0.2);
    }

    .top-bar-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 0 auto;
    }

    .top-bar-right .icon-btn {
        position: relative;
        color: #f0d878;
        font-size: 1.1rem;
        cursor: pointer;
        text-decoration: none;
        padding: 0.3rem 0.5rem;
        border-radius: 6px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-bar-right .icon-btn .badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #dc3545;
        color: white;
        font-size: 0.5rem;
        font-weight: 700;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-bar-right .topbar-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #0e0e0e;
        border: 1px solid #d4af37;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f0d878;
        font-size: 0.9rem;
    }

    .welcome-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0.8rem 0 1.2rem 0;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .welcome-left h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #f0d878;
        font-family: 'Playfair Display', serif;
        margin: 0;
    }

    .welcome-left .subtitle {
        font-size: 0.9rem;
        color: #7a7568;
        margin-top: 0.2rem;
    }

    .date-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: #b8b2a0;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .date-badge i {
        color: #d4af37;
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
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        text-align: center;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: bold;
        color: #d4af37;
    }

    .stat-card .stat-label {
        color: #7a7568;
        font-size: 0.75rem;
        margin-top: 0.2rem;
    }

    .stat-card.green { border-left-color: #28a745; }
    .stat-card.green .stat-number { color: #28a745; }
    .stat-card.orange { border-left-color: #ffc107; }
    .stat-card.orange .stat-number { color: #ffc107; }
    .stat-card.red { border-left-color: #dc3545; }
    .stat-card.red .stat-number { color: #dc3545; }
    .stat-card.blue { border-left-color: #17a2b8; }
    .stat-card.blue .stat-number { color: #17a2b8; }
    .stat-card.purple { border-left-color: #6f42c1; }
    .stat-card.purple .stat-number { color: #6f42c1; }

    /* ============================================
       ADD/EDIT FORM
       ============================================ */
    .add-form {
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        margin-bottom: 2rem;
    }

    .add-form h3 {
        color: #f0d878;
        font-size: 1rem;
        margin-bottom: 1rem;
    }

    .add-form .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 1rem;
        align-items: flex-end;
    }

    .add-form .form-group label {
        display: block;
        color: #d4af37;
        font-size: 0.8rem;
        margin-bottom: 0.3rem;
    }

    .add-form .form-group input,
    .add-form .form-group select,
    .add-form .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: #f5f0e1;
        font-size: 0.85rem;
    }

    .add-form .form-group input:focus,
    .add-form .form-group select:focus,
    .add-form .form-group textarea:focus {
        outline: none;
        border-color: #d4af37;
    }

    .add-form .form-group textarea {
        resize: vertical;
        min-height: 40px;
        font-family: inherit;
    }

    /* ============================================
       IMAGE UPLOAD SPECIFIC STYLES
       ============================================ */
    .image-upload-wrapper {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        border: 2px dashed rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        background: #111;
        transition: all 0.3s;
        min-height: 100px;
        justify-content: center;
    }

    .image-upload-wrapper:hover {
        border-color: #d4af37;
        background: rgba(212, 175, 55, 0.05);
    }

    .image-upload-wrapper .image-preview {
        max-width: 150px;
        max-height: 150px;
        border-radius: 8px;
        object-fit: cover;
    }

    .image-upload-wrapper .upload-label {
        color: #7a7568;
        font-size: 0.8rem;
        cursor: pointer;
        padding: 0.3rem 1rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 20px;
        transition: all 0.3s;
    }

    .image-upload-wrapper .upload-label:hover {
        background: rgba(212, 175, 55, 0.1);
        color: #d4af37;
    }

    .image-upload-wrapper input[type="file"] {
        display: none;
    }

    .image-upload-wrapper .remove-image {
        color: #dc3545;
        font-size: 0.7rem;
        cursor: pointer;
        background: none;
        border: none;
        padding: 2px 8px;
    }

    .image-upload-wrapper .remove-image:hover {
        text-decoration: underline;
    }

    /* ============================================
       BUTTONS
       ============================================ */
    .add-form .btn-save {
        padding: 8px 25px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .add-form .btn-save:hover {
        background: #f0d878;
        transform: translateY(-2px);
    }

    .add-form .btn-cancel {
        padding: 8px 25px;
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

    .add-form .btn-cancel:hover {
        background: #333;
        color: white;
    }

    /* ============================================
       FILTER BAR
       ============================================ */
    .filter-bar {
        display: flex;
        gap: 0.8rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        align-items: center;
        background: #0e0e0e;
        padding: 0.8rem 1.2rem;
        border-radius: 12px;
        border: 1px solid rgba(212, 175, 55, 0.25);
    }

    .filter-bar input,
    .filter-bar select {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #f5f0e1;
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
        background: #f0d878;
    }

    .filter-bar .clear-btn {
        padding: 8px 20px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #7a7568;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        white-space: nowrap;
    }

    .filter-bar .clear-btn:hover {
        background: #2a2a2a;
        color: #f5f0e1;
    }

    /* ============================================
       TABLE
       ============================================ */
    .table-wrapper {
        overflow-x: auto;
        background: #0e0e0e;
        border-radius: 12px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.25);
        -webkit-overflow-scrolling: touch;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 850px;
    }

    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    th {
        color: #d4af37;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .product-thumbnail {
        width: 50px;
        height: 50px;
        border-radius: 6px;
        object-fit: cover;
        background: #111;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .product-thumbnail.no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #555;
        font-size: 1.2rem;
    }

    .stock-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .stock-badge.full {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .stock-badge.low {
        background: rgba(255, 193, 7, 0.15);
        color: #ffc107;
        border: 1px solid #ffc107;
    }

    .stock-badge.out {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .product-status {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .product-status.active {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .product-status.inactive {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .action-cell {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
    }

    .action-cell .btn {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-edit {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .btn-edit:hover {
        background: #17a2b8;
        color: white;
    }

    .btn-toggle {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-toggle:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-restock {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-restock:hover {
        background: #28a745;
        color: white;
    }

    .btn-delete {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-delete:hover {
        background: #dc3545;
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

    .empty-state {
        text-align: center;
        padding: 3rem 0;
        color: #7a7568;
    }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #f0d878;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    /* ============================================
       RESTOCK MODAL
       ============================================ */
    .restock-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .restock-modal-content {
        background: #1a1a1a;
        padding: 2rem;
        border-radius: 15px;
        max-width: 400px;
        width: 90%;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .restock-modal-content h3 {
        color: #d4af37;
        margin-bottom: 1rem;
    }

    .restock-modal-content .form-group {
        margin-bottom: 1rem;
    }

    .restock-modal-content .form-group label {
        display: block;
        color: #d4af37;
        margin-bottom: 0.3rem;
    }

    .restock-modal-content .form-group input {
        width: 100%;
        padding: 10px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
    }

    .restock-modal-content .modal-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .restock-modal-content .btn-cancel-modal {
        flex: 1;
        padding: 10px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .restock-modal-content .btn-confirm-modal {
        flex: 1;
        padding: 10px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 600;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        table { min-width: 600px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 0 1rem 1rem; }
        .top-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 0.5rem;
        }
        .top-bar-left { width: 100%; }
        .top-bar-center { width: 100%; justify-content: flex-start; }
        .top-bar-right { width: 100%; justify-content: flex-start; }
        .top-bar-right .icon-btn { font-size: 0.95rem; padding: 0.2rem 0.4rem; }
        .top-bar-right .topbar-avatar { width: 28px; height: 28px; font-size: 0.75rem; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .welcome-row { flex-direction: column; align-items: flex-start; }
        .welcome-left h1 { font-size: 1.3rem; }
        .date-badge { align-self: flex-start; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .add-form .form-row { grid-template-columns: 1fr; }
        .add-form .btn-save,
        .add-form .btn-cancel { width: 100%; text-align: center; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar input,
        .filter-bar select { width: 100%; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
        .restock-modal-content { padding: 1.5rem; }
        .image-upload-wrapper { min-height: 80px; }
        .image-upload-wrapper .image-preview { max-width: 120px; max-height: 120px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
        .image-upload-wrapper { min-height: 60px; }
        .image-upload-wrapper .image-preview { max-width: 80px; max-height: 80px; }
    }
</style>

<div class="main-content">

    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Products & Inventory</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="../staff/book_for_customer.php" class="qlink"><i class="ti ti-calendar-plus"></i> Book</a>
                    <span class="link-sep">|</span>
                    <a href="services.php" class="qlink"><i class="ti ti-scissors"></i> Services</a>
                    <span class="link-sep">|</span>
                    <a href="staff.php" class="qlink"><i class="ti ti-users"></i> Staff</a>
                    <span class="link-sep">|</span>
                    <a href="payroll.php" class="qlink"><i class="ti ti-coin"></i> Payroll</a>
                    <span class="link-sep">|</span>
                    <a href="permissions.php" class="qlink"><i class="ti ti-key"></i> Permissions</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink active"><i class="ti ti-box"></i> Products</a>
                    <span class="link-sep">|</span>
                    <a href="product_orders.php" class="qlink"><i class="ti ti-shopping-cart"></i> Orders</a>
                </div>
            </div>

            <div class="top-bar-right">
                <a href="#" class="icon-btn" id="searchToggle" title="Search (Ctrl+K)">
                    <i class="ti ti-search"></i>
                </a>
                <a href="notifications.php" class="icon-btn" title="Notifications">
                    <i class="ti ti-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo min($unread_count, 99); ?></span>
                    <?php endif; ?>
                </a>
                <div class="topbar-avatar"><i class="ti ti-crown"></i></div>
            </div>
        </div>
    </div>

    <div class="welcome-row">
        <div class="welcome-left">
            <h1>📦 Products & Inventory</h1>
            <p class="subtitle">Manage your salon products and track stock levels</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

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
            <div class="stat-number"><?php echo $stats['full_stock'] ?? 0; ?></div>
            <div class="stat-label">Full Stock</div>
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
        <div class="stat-card blue">
            <span class="stat-icon">💰</span>
            <div class="stat-number">KSh <?php echo number_format($stats['inventory_value'] ?? 0, 2); ?></div>
            <div class="stat-label">Inventory Value</div>
        </div>
        <div class="stat-card purple">
            <span class="stat-icon">📊</span>
            <div class="stat-number"><?php echo $stats['total_stock'] ?? 0; ?></div>
            <div class="stat-label">Total Units</div>
        </div>
    </div>

    <!-- ============================================
       ADD/EDIT FORM WITH IMAGE UPLOAD
       ============================================ -->
    <div class="add-form">
        <h3><?php echo $edit_id > 0 ? '✏️ Edit Product' : '➕ Add New Product'; ?></h3>
        <form method="POST" enctype="multipart/form-data">
            <?php if ($edit_id > 0): ?>
                <input type="hidden" name="product_id" value="<?php echo $edit_id; ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" placeholder="e.g., Shampoo" value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief description"><?php echo $edit_product ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" placeholder="e.g., Hair Care" value="<?php echo $edit_product ? htmlspecialchars($edit_product['category']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Price (KSh)</label>
                    <input type="number" name="price" step="0.01" placeholder="0.00" value="<?php echo $edit_product ? $edit_product['price'] : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" name="stock" placeholder="0" value="<?php echo $edit_product ? $edit_product['stock'] : '0'; ?>" required>
                </div>
                <div class="form-group">
                    <label>Reorder Level</label>
                    <input type="number" name="reorder_level" placeholder="5" value="<?php echo $edit_product ? $edit_product['reorder_level'] : '5'; ?>" required>
                </div>
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" placeholder="SKU-001" value="<?php echo $edit_product ? htmlspecialchars($edit_product['sku']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <input type="text" name="supplier" placeholder="Supplier name" value="<?php echo $edit_product ? htmlspecialchars($edit_product['supplier']) : ''; ?>">
                </div>
                <!-- IMAGE UPLOAD FIELD -->
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Product Image</label>
                    <div class="image-upload-wrapper" id="imageUploadWrapper">
                        <?php if ($edit_product && !empty($edit_product['image']) && file_exists('../assets/uploads/products/' . $edit_product['image'])): ?>
                            <img src="../assets/uploads/products/<?php echo $edit_product['image']; ?>" alt="<?php echo htmlspecialchars($edit_product['name']); ?>" class="image-preview" id="imagePreview">
                        <?php else: ?>
                            <img src="#" alt="Product Image" class="image-preview" id="imagePreview" style="display:none;">
                        <?php endif; ?>
                        <div id="uploadPlaceholder" style="text-align: center; <?php echo ($edit_product && !empty($edit_product['image'])) ? 'display:none;' : ''; ?>">
                            <span style="font-size: 2rem;">📷</span>
                            <p style="color: #7a7568; font-size: 0.8rem; margin-top: 0.5rem;">Click to upload product image</p>
                            <p style="color: #555; font-size: 0.7rem;">JPG, PNG, WEBP, GIF (Max 2MB)</p>
                        </div>
                        <input type="file" name="product_image" id="productImageInput" accept="image/jpeg,image/png,image/webp,image/gif">
                        <label for="productImageInput" class="upload-label">📤 Choose Image</label>
                        <?php if ($edit_product && !empty($edit_product['image'])): ?>
                            <button type="button" class="remove-image" onclick="removeImage()">✕ Remove Image</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group" style="display: flex; gap: 0.5rem; align-items: center; grid-column: 1 / -1;">
                    <?php if ($edit_id > 0): ?>
                        <button type="submit" name="save_product" class="btn-save">💾 Update Product</button>
                        <a href="products.php" class="btn-cancel">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="save_product" class="btn-save">➕ Add Product</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
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
                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($category_filter == $cat['category']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="stock">
                <option value="">All Stock</option>
                <option value="full" <?php echo ($stock_filter == 'full') ? 'selected' : ''; ?>>Full Stock</option>
                <option value="low" <?php echo ($stock_filter == 'low') ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?php echo ($stock_filter == 'out') ? 'selected' : ''; ?>>Out of Stock</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="products.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <!-- ============================================
       PRODUCT TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($products_result) > 0): ?>
                    <?php while($product = mysqli_fetch_assoc($products_result)): 
                        $stock_status = 'full';
                        $stock_label = '✅ Full';
                        if ($product['stock'] <= 0) {
                            $stock_status = 'out';
                            $stock_label = '🚫 Out';
                        } elseif ($product['stock'] <= $product['reorder_level']) {
                            $stock_status = 'low';
                            $stock_label = '⚠️ Low';
                        }
                        
                        // Check if image exists
                        $has_image = !empty($product['image']) && file_exists('../assets/uploads/products/' . $product['image']);
                    ?>
                        <tr>
                            <td>
                                <?php if ($has_image): ?>
                                    <img src="../assets/uploads/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-thumbnail">
                                <?php else: ?>
                                    <div class="product-thumbnail no-image">📷</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $product['id']; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                            <td>KSh <?php echo number_format($product['price'], 2); ?></td>
                            <td>
                                <?php echo $product['stock']; ?>
                                <?php if ($product['stock'] <= $product['reorder_level'] && $product['stock'] > 0): ?>
                                    <span style="color: #ffc107; font-size: 0.6rem;">(Reorder at <?php echo $product['reorder_level']; ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="stock-badge <?php echo $stock_status; ?>">
                                    <?php echo $stock_label; ?>
                                </span>
                            </td>
                            <td class="action-cell">
                                <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                                <a href="products.php?toggle=<?php echo $product['id']; ?>" class="btn btn-toggle" onclick="return confirm('Toggle product status?')">
                                    <?php echo $product['is_active'] ? '🔴' : '🟢'; ?>
                                </a>
                                <button class="btn btn-restock" onclick="openRestockModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">📦 Restock</button>
                                <a href="products.php?delete=<?php echo $product['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this product?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No products found. Add your first product above!</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<!-- Restock Modal -->
<div id="restockModal" class="restock-modal">
    <div class="restock-modal-content">
        <h3>📦 Restock Product</h3>
        <p id="restockProductName" style="color: #b8b2a0; margin-bottom: 1rem;"></p>
        <form method="GET">
            <input type="hidden" name="restock" id="restockProductId">
            <div class="form-group">
                <label>Quantity to Add</label>
                <input type="number" name="qty" id="restockQuantity" value="10" min="1">
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel-modal" onclick="closeRestockModal()">Cancel</button>
                <button type="submit" class="btn-confirm-modal">✅ Add Stock</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ============================================
    // IMAGE UPLOAD PREVIEW
    // ============================================
    document.getElementById('productImageInput').addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const preview = document.getElementById('imagePreview');
                preview.src = event.target.result;
                preview.style.display = 'block';
                document.getElementById('uploadPlaceholder').style.display = 'none';
            }
            reader.readAsDataURL(file);
        }
    });

    function removeImage() {
        const preview = document.getElementById('imagePreview');
        preview.src = '#';
        preview.style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
        document.getElementById('productImageInput').value = '';
        
        // Add hidden input to indicate image removal
        const wrapper = document.getElementById('imageUploadWrapper');
        const existing = document.querySelector('input[name="remove_image"]');
        if (!existing) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remove_image';
            input.value = '1';
            wrapper.appendChild(input);
        }
    }

    // ============================================
    // RESTOCK MODAL
    // ============================================
    function openRestockModal(productId, productName) {
        document.getElementById('restockProductId').value = productId;
        document.getElementById('restockProductName').textContent = 'Product: ' + productName;
        document.getElementById('restockModal').style.display = 'flex';
        document.getElementById('restockQuantity').value = 10;
    }

    function closeRestockModal() {
        document.getElementById('restockModal').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('restockModal')) {
            closeRestockModal();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>