<?php
// customer/dashboard.php - COMPLETE FIXED VERSION
// THIS MUST BE THE VERY FIRST THING IN THE FILE - NO SPACES BEFORE <?php

require_once '../config/database.php';

// Debug: Check session (optional - remove in production)
error_log("Customer Dashboard - User Role: " . ($_SESSION['user_role'] ?? 'NOT SET'));

// Check if user is logged in AND has customer role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    // Not a customer, redirect to login
    header("Location: ../auth/login.php");
    exit();
}

// If we reach here, user is a valid customer
$user_id = $_SESSION['user_id'];

// Get upcoming appointments
$upcoming_query = "SELECT a.*, s.service_name, s.price, u.full_name as staff_name 
                FROM appointments a 
                JOIN services s ON a.service_id = s.id 
                LEFT JOIN users u ON a.staff_id = u.id 
                WHERE a.customer_id = $user_id AND a.status NOT IN ('completed', 'cancelled', 'served')
                ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$upcoming = mysqli_query($conn, $upcoming_query);

// Get appointment history
$history_query = "SELECT a.*, s.service_name, s.price, u.full_name as staff_name 
                FROM appointments a 
                JOIN services s ON a.service_id = s.id 
                LEFT JOIN users u ON a.staff_id = u.id 
                WHERE a.customer_id = $user_id AND a.status IN ('completed', 'served')
                ORDER BY a.appointment_date DESC LIMIT 10";
$history = mysqli_query($conn, $history_query);

// Get total spent
$spent_query = "SELECT SUM(amount) as total FROM payments p 
                JOIN appointments a ON p.appointment_id = a.id 
                WHERE a.customer_id = $user_id AND p.payment_status = 'paid'";
$spent_result = mysqli_query($conn, $spent_query);
$total_spent = ($spent_result && mysqli_fetch_assoc($spent_result)['total']) ?? 0;

include '../includes/header.php';
?>

<style>
    .dashboard-container {
        display: flex;
        min-height: 100vh;
    }
    .sidebar {
        width: 280px;
        background: #050505;
        border-right: 1px solid #d4af37;
        padding: 2rem 1rem;
    }
    .sidebar-menu {
        list-style: none;
        padding: 0;
    }
    .sidebar-menu li {
        margin-bottom: 0.5rem;
    }
    .sidebar-menu a {
        display: block;
        padding: 12px 20px;
        color: white;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active {
        background: #d4af37;
        color: #050505;
    }
    .main-content {
        flex: 1;
        padding: 2rem;
        background: #0a0a0a;
    }
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
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #d4af37;
    }
    .section-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    .section-card h3 {
        color: #d4af37;
        margin-bottom: 1rem;
    }
    .table-container {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.2);
    }
    th {
        color: #d4af37;
    }
    .btn-outline {
        display: inline-block;
        padding: 8px 15px;
        border: 1px solid #d4af37;
        color: #d4af37;
        text-decoration: none;
        border-radius: 5px;
        transition: all 0.3s;
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
    .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }
    .action-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }
    @media (max-width: 768px) {
        .dashboard-container {
            flex-direction: column;
        }
        .sidebar {
            width: 100%;
        }
    }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
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
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! ✨</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo mysqli_num_rows($upcoming); ?></div>
                <p>Upcoming Appointments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">KSh <?php echo number_format($total_spent, 2); ?></div>
                <p>Total Spent</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">✨</div>
                <p>Loyalty Points</p>
            </div>
        </div>
        
        <div class="section-card">
            <h3>📅 Upcoming Appointments</h3>
            <?php if($upcoming && mysqli_num_rows($upcoming) > 0): ?>
                <div class="table-container">
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

<?php include '../includes/footer.php'; ?>