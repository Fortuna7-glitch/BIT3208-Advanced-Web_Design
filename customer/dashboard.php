<?php
// customer/dashboard.php - RESPONSIVE REWRITE
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get upcoming appointments
$upcoming_query = "SELECT a.*, s.service_name, s.price, u.full_name as staff_name 
                FROM appointments a 
                JOIN services s ON a.service_id = s.id 
                LEFT JOIN users u ON a.staff_id = u.id 
                WHERE a.customer_id = $user_id AND a.status NOT IN ('completed', 'cancelled', 'served')
                ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$upcoming = mysqli_query($conn, $upcoming_query);

// Get total spent
$spent_query = "SELECT SUM(amount) as total FROM payments p 
                JOIN appointments a ON p.appointment_id = a.id 
                WHERE a.customer_id = $user_id AND p.payment_status = 'paid'";
$spent_result = mysqli_query($conn, $spent_query);
$total_spent = ($spent_result && mysqli_fetch_assoc($spent_result)['total']) ?? 0;

include '../includes/header.php';
?>

<style>
    .customer-container { display: flex; min-height: 100vh; }
    
    .sidebar {
        width: 280px;
        background: #050505;
        border-right: 1px solid #d4af37;
        padding: 2rem 1rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
        position: sticky;
        top: 70px;
        height: calc(100vh - 70px);
        overflow-y: auto;
    }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .sidebar-header h3 { color: #d4af37; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 12px 20px;
        color: white;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s;
        font-size: 0.95rem;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active {
        background: #d4af37;
        color: #050505;
    }
    
    .sidebar-toggle {
        display: none;
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        margin-bottom: 1rem;
        width: 100%;
    }
    .sidebar-toggle:hover { background: #f9e547; }

    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; min-width: 0; }
    .main-content h1 { color: #d4af37; margin-bottom: 0.5rem; }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        border-left: 4px solid #d4af37;
    }
    .stat-number { font-size: 2.5rem; font-weight: bold; color: #d4af37; }
    .stat-label { color: #aaa; margin-top: 0.3rem; font-size: 0.9rem; }

    .section-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    .section-card h3 { color: #d4af37; margin-bottom: 1rem; }

    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        margin-top: 1rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        min-width: 500px;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    .btn-outline {
        display: inline-block;
        padding: 5px 12px;
        border: 1px solid #d4af37;
        color: #d4af37;
        text-decoration: none;
        border-radius: 5px;
        font-size: 0.8rem;
    }
    .btn-outline:hover {
        background: #d4af37;
        color: #050505;
    }
    .btn-primary {
        display: inline-block;
        padding: 12px 25px;
        background: #d4af37;
        color: #050505;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-primary:hover { background: #f9e547; transform: translateY(-2px); }
    .action-buttons { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }

    @media (max-width: 1024px) {
        .sidebar { width: 240px; padding: 1.5rem 0.8rem; }
        .stats-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .stat-number { font-size: 2rem; }
    }

    @media (max-width: 768px) {
        .customer-container { flex-direction: column; }
        .sidebar {
            width: 100%;
            position: relative;
            top: 0;
            height: auto;
            border-right: none;
            border-bottom: 1px solid #d4af37;
            padding: 1rem;
            display: none;
        }
        .sidebar.open { display: block; }
        .sidebar-toggle { display: block; }

        .main-content { padding: 1rem; }
        .main-content h1 { font-size: 1.5rem; }

        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .stat-card { padding: 1rem; }
        .stat-number { font-size: 1.5rem; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .main-content { padding: 0.8rem; }
        .main-content h1 { font-size: 1.2rem; }

        table { font-size: 0.75rem; min-width: 400px; }
        th, td { padding: 8px; }
        .action-buttons { flex-direction: column; }
        .action-buttons .btn-primary, .action-buttons .btn-outline { text-align: center; }
    }
</style>

<div class="customer-container">

    <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle">✕ Close Menu</button>
        <div class="sidebar-header">
            <h3>👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <p>Customer</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="book.php">✨ New Booking</a></li>
            <li><a href="appointments.php">📅 My Appointments</a></li>
            <li><a href="update-profile.php">⚙️ Update Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="sidebar-toggle" id="sidebarOpen" style="display:none; margin-bottom:1rem;">☰ Menu</button>

        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! ✨</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo mysqli_num_rows($upcoming); ?></div>
                <div class="stat-label">Upcoming Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">KSh <?php echo number_format($total_spent, 2); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">✨</div>
                <div class="stat-label">Loyalty Points</div>
            </div>
        </div>

        <div class="section-card">
            <h3>📅 Upcoming Appointments</h3>
            <?php if($upcoming && mysqli_num_rows($upcoming) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Service</th><th>Staff</th><th>Date</th><th>Time</th><th>Price</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php while($apt = mysqli_fetch_assoc($upcoming)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['staff_name'] ?? 'Not Assigned'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                                <td>KSh <?php echo number_format($apt['price'], 2); ?></td>
                                <td><?php echo ucfirst($apt['status']); ?></td>
                                <td><a href="appointments.php?cancel=<?php echo $apt['id']; ?>" class="btn-outline" style="padding: 5px 10px;" onclick="return confirm('Cancel this appointment?')">Cancel</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No upcoming appointments. <a href="book.php">Book one now!</a></p>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="book.php" class="btn-primary">✨ Book New Appointment</a>
            <a href="appointments.php" class="btn-outline">View All Appointments</a>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOpen = document.getElementById('sidebarOpen');
        const sidebarToggle = document.getElementById('sidebarToggle');

        function isMobile() { return window.innerWidth <= 768; }

        function handleSidebar() {
            if (isMobile()) {
                sidebar.classList.remove('open');
                sidebarOpen.style.display = 'block';
                sidebarToggle.style.display = 'block';
            } else {
                sidebar.classList.add('open');
                sidebarOpen.style.display = 'none';
                sidebarToggle.style.display = 'none';
            }
        }

        if (sidebarOpen) {
            sidebarOpen.addEventListener('click', function() {
                sidebar.classList.add('open');
            });
        }
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.remove('open');
            });
        }

        document.addEventListener('click', function(event) {
            if (isMobile() && sidebar.classList.contains('open')) {
                if (!sidebar.contains(event.target) && event.target !== sidebarOpen) {
                    sidebar.classList.remove('open');
                }
            }
        });

        window.addEventListener('resize', handleSidebar);
        handleSidebar();
    });
</script>

<?php include '../includes/footer.php'; ?>