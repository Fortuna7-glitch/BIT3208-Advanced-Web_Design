<?php
// admin/services.php - ADMIN FULL ACCESS: All actions visible
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';

// ============================================
// HANDLE ACTIONS (Admin has full access)
// ============================================

// Add Service
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $service_name = mysqli_real_escape_string($conn, $_POST['service_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $duration = mysqli_real_escape_string($conn, $_POST['duration']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);

    $query = "INSERT INTO services (service_name, description, price, duration_minutes, category) 
              VALUES ('$service_name', '$description', '$price', '$duration', '$category')";
    if (mysqli_query($conn, $query)) {
        $success = "Service added successfully!";
    } else {
        $error = "Failed to add service: " . mysqli_error($conn);
    }
}

// Update Service
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $service_name = mysqli_real_escape_string($conn, $_POST['service_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $duration = mysqli_real_escape_string($conn, $_POST['duration']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);

    $query = "UPDATE services SET service_name='$service_name', description='$description', 
              price='$price', duration_minutes='$duration', category='$category' WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        $success = "Service updated successfully!";
    } else {
        $error = "Failed to update service: " . mysqli_error($conn);
    }
}

// Delete Service
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $query = "DELETE FROM services WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        $success = "Service deleted successfully!";
    } else {
        $error = "Failed to delete service: " . mysqli_error($conn);
    }
}

// Toggle Service Status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $query = "UPDATE services SET is_active = NOT is_active WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        $success = "Service status toggled!";
    } else {
        $error = "Failed to toggle status: " . mysqli_error($conn);
    }
}

// ============================================
// GET SERVICES
// ============================================
$services_query = "SELECT * FROM services ORDER BY id DESC";
$services = mysqli_query($conn, $services_query);

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    /* ============================================
       HEADER WITH QUICK ACTIONS & SEARCH
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
       ADD FORM
       ============================================ */
    .add-form {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.2rem 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
        margin-bottom: 2rem;
    }

    .add-form h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1rem;
    }

    .add-form .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        align-items: flex-end;
    }

    .add-form .form-group label {
        display: block;
        color: #d4af37;
        font-size: 0.8rem;
        margin-bottom: 0.3rem;
    }

    .add-form .form-group input {
        width: 100%;
        padding: 8px 12px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 0.85rem;
    }

    .add-form .form-group input:focus {
        outline: none;
        border-color: #d4af37;
    }

    .add-form .btn-add {
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

    .add-form .btn-add:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    /* ============================================
       TABLE
       ============================================ */
    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.2);
        -webkit-overflow-scrolling: touch;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 700px;
    }

    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    th {
        color: #d4af37;
        font-weight: 600;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .service-status {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .service-status.active {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .service-status.inactive {
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

    .btn-update {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .btn-update:hover {
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
        color: #666;
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
    @media (max-width: 1024px) {
        table { min-width: 600px; }
    }

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
        .add-form .form-row { grid-template-columns: 1fr; }
        .add-form .btn-add { width: 100%; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER: Title + Quick Actions + Search
       ============================================ -->
    <div class="page-header">
        <div class="title-section">
            <h1>💇 Services Management</h1>
            <p>Manage salon services and prices</p>
        </div>
        <div class="quick-actions">
            <a href="appointments.php" class="quick-btn"><i class="fas fa-calendar"></i> Appointments</a>
            <a href="staff.php" class="quick-btn"><i class="fas fa-users"></i> Staff</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
        <div class="search-section">
            <form method="GET" style="display: flex; gap: 0.5rem; width: 100%;">
                <input type="text" name="q" placeholder="🔍 Search services..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>
    </div>

    <!-- ============================================
       ALERTS
       ============================================ -->
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ============================================
       ADD SERVICE FORM
       ============================================ -->
    <div class="add-form">
        <h3>➕ Add New Service</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" name="service_name" placeholder="e.g. Haircut" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="Brief description">
                </div>
                <div class="form-group">
                    <label>Price (KSh)</label>
                    <input type="number" name="price" placeholder="0.00" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration" placeholder="30" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" placeholder="e.g. Hair, Nails">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-add">➕ Add Service</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ============================================
       SERVICES TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Price (KSh)</th>
                    <th>Duration</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($services) > 0): ?>
                    <?php while($service = mysqli_fetch_assoc($services)): ?>
                    <tr>
                        <form method="POST" style="display: contents;">
                            <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                            <input type="hidden" name="action" value="update">
                            <td><?php echo $service['id']; ?></td>
                            <td><input type="text" name="service_name" value="<?php echo htmlspecialchars($service['service_name']); ?>" style="background:transparent;border:none;color:white;width:100%;"></td>
                            <td><input type="text" name="description" value="<?php echo htmlspecialchars($service['description'] ?? ''); ?>" style="background:transparent;border:none;color:white;width:100%;"></td>
                            <td><input type="number" name="price" value="<?php echo $service['price']; ?>" step="0.01" style="background:transparent;border:none;color:white;width:70px;"></td>
                            <td><input type="number" name="duration" value="<?php echo $service['duration_minutes']; ?>" style="background:transparent;border:none;color:white;width:50px;"></td>
                            <td><input type="text" name="category" value="<?php echo htmlspecialchars($service['category'] ?? ''); ?>" style="background:transparent;border:none;color:white;width:100px;"></td>
                            <td>
                                <span class="service-status <?php echo $service['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="action-cell">
                                <button type="submit" class="btn btn-update">💾 Update</button>
                                <a href="services.php?toggle=<?php echo $service['id']; ?>" class="btn btn-toggle" onclick="return confirm('Toggle service status?')">
                                    <?php echo $service['is_active'] ? '🔴' : '🟢'; ?>
                                </a>
                                <a href="services.php?delete=<?php echo $service['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this service?')">🗑️ Delete</a>
                            </td>
                        </form>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No services found. Add your first service above!</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>