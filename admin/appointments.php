<?php
// admin/appointments.php - ADMIN FULL ACCESS: All actions visible
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';

// ============================================
// HANDLE ACTIONS (Admin has full access)
// ============================================

// Serve appointment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'serve') {
    $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
    $query = "UPDATE appointments SET status = 'served' WHERE id = $appointment_id";
    if (mysqli_query($conn, $query)) {
        $success = "Appointment marked as served!";
    } else {
        $error = "Failed to mark as served: " . mysqli_error($conn);
    }
}

// Cancel appointment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel') {
    $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
    $query = "UPDATE appointments SET status = 'cancelled' WHERE id = $appointment_id";
    if (mysqli_query($conn, $query)) {
        $success = "Appointment cancelled successfully!";
    } else {
        $error = "Failed to cancel appointment: " . mysqli_error($conn);
    }
}

// Delete appointment
if (isset($_GET['delete'])) {
    $appointment_id = (int)$_GET['delete'];
    $query = "DELETE FROM appointments WHERE id = $appointment_id";
    if (mysqli_query($conn, $query)) {
        $success = "Appointment deleted successfully!";
    } else {
        $error = "Failed to delete appointment: " . mysqli_error($conn);
    }
}

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// ============================================
// GET APPOINTMENTS
// ============================================
$query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price, u.full_name as staff_name 
          FROM appointments a 
          JOIN users c ON a.customer_id = c.id 
          JOIN services s ON a.service_id = s.id 
          LEFT JOIN users u ON a.staff_id = u.id 
          WHERE 1=1";
if ($search) {
    $query .= " AND (c.full_name LIKE '%$search%' OR s.service_name LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}
if ($status_filter) {
    $query .= " AND a.status = '$status_filter'";
}
$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointments = mysqli_query($conn, $query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' OR status = 'confirmed' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'served' OR status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

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
        flex: 0 1 300px;
        min-width: 180px;
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
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
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

    .stat-card.orange { border-left-color: #ffc107; }
    .stat-card.orange .stat-number { color: #ffc107; }
    .stat-card.green { border-left-color: #28a745; }
    .stat-card.green .stat-number { color: #28a745; }
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

    .filter-bar select {
        padding: 8px 14px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: white;
        font-size: 0.85rem;
        min-width: 150px;
    }

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
        min-width: 900px;
    }

    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
        white-space: nowrap;
    }

    th {
        color: #d4af37;
        font-weight: 600;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    /* Status Badges */
    .status-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .status-badge.served { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }
    .status-badge.cancelled { background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid #dc3545; }
    .status-badge.pending { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .status-badge.confirmed { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .status-badge.completed { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }

    /* Action Buttons */
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

    .btn-serve {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-serve:hover {
        background: #28a745;
        color: white;
    }

    .btn-cancel {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-cancel:hover {
        background: #dc3545;
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

    .btn-view {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .btn-view:hover {
        background: #d4af37;
        color: #050505;
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

    /* Alerts */
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
    @media (max-width: 1024px) {
        table { min-width: 700px; font-size: 0.8rem; }
        th, td { padding: 8px; }
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
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar select { width: 100%; }
        table { min-width: 550px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
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
        <!-- Left: Title -->
        <div class="title-section">
            <h1>📅 All Appointments</h1>
            <p>Manage all salon appointments</p>
        </div>

        <!-- Center/Right: Quick Actions -->
        <div class="quick-actions">
            <a href="../staff/book_for_customer.php" class="quick-btn"><i class="fas fa-plus-circle"></i> Book</a>
            <a href="services.php" class="quick-btn"><i class="fas fa-scissors"></i> Services</a>
            <a href="customers.php" class="quick-btn"><i class="fas fa-users"></i> Customers</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>

        <!-- Right: Search -->
        <div class="search-section">
            <form method="GET" style="display: flex; gap: 0.5rem; width: 100%;">
                <input type="text" name="q" placeholder="🔍 Search appointments..." value="<?php echo htmlspecialchars($search); ?>">
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
       STATISTICS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Appointments</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div>
            <div class="stat-label">Cancelled</div>
        </div>
    </div>

    <!-- ============================================
       FILTER BAR
       ============================================ -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo ($status_filter == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                <option value="served" <?php echo ($status_filter == 'served') ? 'selected' : ''; ?>>Served</option>
                <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="appointments.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <!-- ============================================
       TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Staff</th>
                    <th>Status</th>
                    <th>Queue</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($appointments && mysqli_num_rows($appointments) > 0): ?>
                    <?php while($apt = mysqli_fetch_assoc($appointments)): ?>
                    <tr>
                        <td>#<?php echo $apt['id']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                        <td><?php echo htmlspecialchars($apt['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                        <td><?php echo htmlspecialchars($apt['staff_name'] ?? 'Unassigned'); ?></td>
                        <td>
                            <span class="status-badge <?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $apt['queue_position'] ?? '-'; ?></td>
                        <td class="action-cell">
                            <!-- View Button -->
                            <a href="appointments.php?view=<?php echo $apt['id']; ?>" class="btn btn-view">👁️ View</a>

                            <!-- Serve Button (if not served/cancelled) -->
                            <?php if($apt['status'] != 'served' && $apt['status'] != 'cancelled'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="serve">
                                    <button type="submit" class="btn btn-serve" onclick="return confirm('Mark this appointment as served?')">✅ Serve</button>
                                </form>
                            <?php endif; ?>

                            <!-- Cancel Button (if not served/cancelled) -->
                            <?php if($apt['status'] != 'served' && $apt['status'] != 'cancelled'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel this appointment?')">❌ Cancel</button>
                                </form>
                            <?php endif; ?>

                            <!-- Edit Button -->
                            <a href="../staff/book_for_customer.php?edit=<?php echo $apt['id']; ?>" class="btn btn-edit">✏️ Edit</a>

                            <!-- Delete Button -->
                            <a href="appointments.php?delete=<?php echo $apt['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this appointment permanently?')">🗑️ Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <div class="icon">📅</div>
                                <p>No appointments found.</p>
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