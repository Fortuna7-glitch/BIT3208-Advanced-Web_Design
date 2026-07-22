<?php
/**
 * Salon Pro — Super Admin: View All Products
 * Luxury gold/black theme
 * Fixed top bar: Breadcrumb | Quick Actions | Search
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$admin_name = $_SESSION['user_name'] ?? 'Super Admin';

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';

// ============================================
// GET ALL PRODUCTS (Read-only across all salons)
// ============================================
$query = "SELECT p.*, s.salon_name 
          FROM products p 
          LEFT JOIN salons s ON p.salon_id = s.id 
          WHERE 1=1";
if ($search) {
    $query .= " AND (p.name LIKE '%$search%' OR p.category LIKE '%$search%' OR s.salon_name LIKE '%$search%')";
}
$query .= " ORDER BY p.id DESC";
$products_result = mysqli_query($conn, $query);

// Get stats - FIXED: Removed 'stock' column references
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(price) as total_value
    FROM products";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

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
       STICKY HEADER - Same as Dashboard
       ============================================ */
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
        gap: 0.8rem;
        flex: 0 0 auto;
    }

    .top-bar-right .search-box {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 20px;
        padding: 0.35rem 1rem;
        color: #7a7568;
        font-size: 0.85rem;
        min-width: 180px;
        position: relative;
    }

    .top-bar-right .search-box input {
        background: none;
        border: none;
        outline: none;
        color: #f5f0e1;
        font-size: 0.85rem;
        flex: 1;
        width: 100px;
    }

    .top-bar-right .search-box input::placeholder {
        color: #7a7568;
    }

    .top-bar-right .search-box .search-icon {
        color: #d4af37;
        cursor: pointer;
    }

    .top-bar-right .icon-btn {
        position: relative;
        color: #f0d878;
        font-size: 1.2rem;
        cursor: pointer;
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

    /* ============================================
       WELCOME ROW
       ============================================ */
    .welcome-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin: 0.8rem 0 1.2rem 0;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .welcome-row h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #f0d878;
        font-family: 'Playfair Display', serif;
    }
    .welcome-row .subtitle {
        font-size: 0.9rem;
        color: #7a7568;
        margin-top: 0.3rem;
    }
    .welcome-row .date-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: #b8b2a0;
    }
    .welcome-row .date-badge i {
        color: #d4af37;
    }

    /* ============================================
       STATS GRID
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
        color: #7a7568;
        font-size: 0.75rem;
        margin-top: 0.2rem;
    }

    .stat-card.green { border-left-color: #28a745; }
    .stat-card.green .stat-number { color: #28a745; }

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

    .filter-bar input {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #f5f0e1;
        font-size: 0.85rem;
        flex: 1;
        min-width: 150px;
    }

    .filter-bar input:focus {
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
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .super-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 600;
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
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

    /* Responsive */
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
        .top-bar-right .search-box { flex: 1; min-width: 120px; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .welcome-row { flex-direction: column; }
        .welcome-row h1 { font-size: 1.3rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar input { width: 100%; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       STICKY HEADER
       ============================================ -->
    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Products</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="salons.php" class="qlink"><i class="ti ti-building-store"></i> Manage Salons</a>
                    <span class="link-sep">|</span>
                    <a href="admins.php" class="qlink"><i class="ti ti-user-shield"></i> Manage Owners</a>
                    <span class="link-sep">|</span>
                    <a href="subscriptions.php" class="qlink"><i class="ti ti-crown"></i> Subscriptions</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> System Settings</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink active"><i class="ti ti-box"></i> Products</a>
                </div>
            </div>

            <div class="top-bar-right">
                <div class="search-box">
                    <i class="ti ti-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Search products...">
                </div>
                <div class="icon-btn"><i class="ti ti-bell"></i></div>
                <div class="topbar-avatar"><i class="ti ti-crown"></i></div>
            </div>
        </div>
    </div>

    <!-- ============================================
       WELCOME ROW
       ============================================ -->
    <div class="welcome-row">
        <div>
            <h1>📦 All Products</h1>
            <p class="subtitle"><span class="super-badge">Super Admin View</span> Read-only view of all products across all salons</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- ============================================
       STATISTICS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number">KSh <?php echo number_format($stats['total_value'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Value</div>
        </div>
    </div>

    <!-- ============================================
       FILTER BAR
       ============================================ -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap;">
            <input type="text" name="q" placeholder="🔍 Search products by name, category, or salon..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="filter-btn">Search</button>
            <a href="products.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <!-- ============================================
       PRODUCTS TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Salon</th>
                    <th>Price</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($products_result) > 0): ?>
                    <?php while($product = mysqli_fetch_assoc($products_result)): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($product['salon_name'] ?? 'Unassigned'); ?></td>
                            <td>KSh <?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No products found.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<script>
    // Simple search functionality
    document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query.length > 0) {
                window.location.href = 'products.php?q=' + encodeURIComponent(query);
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>