<?php
// staff/book_for_customer.php - Staff books appointment on behalf of customer
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Check if staff has this permission
if (!hasPermission($_SESSION['user_id'], 'book_for_customers')) {
    redirect('dashboard.php');
}

$staff_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get all customers
$customers_query = "SELECT id, full_name, email, phone FROM users WHERE role = 'customer' AND is_active = 1 ORDER BY full_name";
$customers = mysqli_query($conn, $customers_query);

// Get all services
$services_query = "SELECT * FROM services WHERE is_active = 1";
$services = mysqli_query($conn, $services_query);

// Get staff members (for assigning stylist)
$staff_list_query = "SELECT id, full_name FROM users WHERE role = 'staff' AND is_active = 1";
$staff_list = mysqli_query($conn, $staff_list_query);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $assigned_staff_id = !empty($_POST['staff_id']) ? mysqli_real_escape_string($conn, $_POST['staff_id']) : $staff_id;
    $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);
    $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    
    // Get service price
    $price_query = "SELECT price, service_name FROM services WHERE id = $service_id";
    $price_result = mysqli_query($conn, $price_query);
    $service = mysqli_fetch_assoc($price_result);
    
    // Get queue position
    $queue_query = "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = '$appointment_date' AND status NOT IN ('completed', 'cancelled', 'served')";
    $queue_result = mysqli_query($conn, $queue_query);
    $queue_position = ($queue_result && mysqli_fetch_assoc($queue_result)['count']) + 1;
    
    $query = "INSERT INTO appointments (customer_id, staff_id, service_id, appointment_date, appointment_time, queue_position, payment_method, status) 
              VALUES ($customer_id, $assigned_staff_id, $service_id, '$appointment_date', '$appointment_time', $queue_position, '$payment_method', 'pending')";
    
    if (mysqli_query($conn, $query)) {
        $appointment_id = mysqli_insert_id($conn);
        
        // Record payment
        $payment_query = "INSERT INTO payments (appointment_id, amount, payment_method, payment_status) 
                         VALUES ($appointment_id, {$service['price']}, '$payment_method', 'pending')";
        mysqli_query($conn, $payment_query);
        
        // Get customer details for notification
        $cust_query = "SELECT full_name, phone, email FROM users WHERE id = $customer_id";
        $cust_result = mysqli_query($conn, $cust_query);
        $customer = mysqli_fetch_assoc($cust_result);
        
        // Send notification to customer
        $message = "Dear {$customer['full_name']}, your appointment for {$service['service_name']} on $appointment_date at $appointment_time has been booked by our staff. Queue position: $queue_position. Thank you for choosing Salon Pro!";
        sendNotification($customer_id, "Appointment Booked by Staff", $message);
        sendSMS($customer['phone'], $message);
        
        $success = "Appointment booked successfully for {$customer['full_name']}!";
        
        // Auto redirect
        echo "<meta http-equiv='refresh' content='2;url=dashboard.php'>";
    } else {
        $error = "Booking failed: " . mysqli_error($conn);
    }
}

include '../includes/header.php';
?>

<style>
    .staff-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    .booking-container { max-width: 600px; margin: 0 auto; background: #1a1a1a; border-radius: 20px; padding: 2rem; }
    .booking-container h2 { text-align: center; color: #d4af37; margin-bottom: 2rem; }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
    .form-control, select { width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; font-size: 1rem; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
    button[type="submit"] { width: 100%; padding: 12px; background: #d4af37; color: #050505; border: none; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; }
    button[type="submit"]:hover { background: #f9e547; }
    h1 { color: #d4af37; margin-bottom: 2rem; }
    @media (max-width: 768px) { .staff-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="staff-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <p>Staff Member</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 My Appointments</a></li>
            <li><a href="book_for_customer.php" class="active">📝 Book for Customer</a></li>
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>📝 Book Appointment for Customer</h1>
        
        <div class="booking-container">
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?> Redirecting...</div>
            <?php endif; ?>
            
            <?php if(!$success): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Select Customer</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">-- Choose a customer --</option>
                        <?php while($customer = mysqli_fetch_assoc($customers)): ?>
                        <option value="<?php echo $customer['id']; ?>">
                            <?php echo htmlspecialchars($customer['full_name']); ?> (<?php echo htmlspecialchars($customer['phone']); ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Select Service</label>
                    <select name="service_id" class="form-control" required id="service_select">
                        <option value="">-- Choose a service --</option>
                        <?php while($service = mysqli_fetch_assoc($services)): ?>
                        <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>">
                            <?php echo htmlspecialchars($service['service_name']); ?> - KSh <?php echo number_format($service['price'], 2); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Assign Stylist (Optional - leave blank to assign yourself)</label>
                    <select name="staff_id" class="form-control">
                        <option value="">-- Assign to myself --</option>
                        <?php while($staff_member = mysqli_fetch_assoc($staff_list)): ?>
                        <option value="<?php echo $staff_member['id']; ?>">
                            <?php echo htmlspecialchars($staff_member['full_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Appointment Date</label>
                    <input type="date" name="appointment_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Appointment Time</label>
                    <input type="time" name="appointment_time" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">💵 Cash</option>
                        <option value="mpesa">📱 M-PESA</option>
                    </select>
                </div>
                
                <button type="submit">Confirm Booking</button>
            </form>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    const serviceSelect = document.getElementById('service_select');
    if (serviceSelect && serviceSelect.value) {
        serviceSelect.dispatchEvent(new Event('change'));
    }
</script>

<?php include '../includes/footer.php'; ?>