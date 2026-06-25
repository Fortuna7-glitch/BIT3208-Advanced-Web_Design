<?php
// staff/book_for_customer.php - UPDATED with new hamburger sidebar layout
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

// Get salon_id from session
$salon_id = $_SESSION['salon_id'] ?? 0;
if ($salon_id <= 0) {
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $staff_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

// Get all customers from this salon
$customers_query = "SELECT id, full_name, email, phone FROM users WHERE role = 'customer' AND salon_id = $salon_id AND is_active = 1 ORDER BY full_name";
$customers = mysqli_query($conn, $customers_query);

// Get all services from this salon
$services_query = "SELECT * FROM services WHERE salon_id = $salon_id AND is_active = 1";
$services = mysqli_query($conn, $services_query);

// Get staff members from this salon
$staff_list_query = "SELECT id, full_name FROM users WHERE role = 'staff' AND salon_id = $salon_id AND is_active = 1";
$staff_list = mysqli_query($conn, $staff_list_query);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $assigned_staff_id = !empty($_POST['staff_id']) ? mysqli_real_escape_string($conn, $_POST['staff_id']) : $staff_id;
    $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);
    $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    
    // Verify service belongs to this salon
    $service_check = mysqli_query($conn, "SELECT price, service_name FROM services WHERE id = $service_id AND salon_id = $salon_id");
    if (mysqli_num_rows($service_check) == 0) {
        $error = "Invalid service selected.";
    } else {
        $service = mysqli_fetch_assoc($service_check);
        
        // Verify customer belongs to this salon
        $customer_check = mysqli_query($conn, "SELECT id, full_name, phone, email FROM users WHERE id = $customer_id AND salon_id = $salon_id AND role = 'customer'");
        if (mysqli_num_rows($customer_check) == 0) {
            $error = "Invalid customer selected.";
        } else {
            $customer = mysqli_fetch_assoc($customer_check);
        }
        
        // Verify staff belongs to this salon (if selected)
        if ($assigned_staff_id != $staff_id) {
            $staff_check = mysqli_query($conn, "SELECT id FROM users WHERE id = $assigned_staff_id AND salon_id = $salon_id AND role = 'staff'");
            if (mysqli_num_rows($staff_check) == 0) {
                $error = "Invalid staff member selected.";
            }
        }
        
        if (empty($error)) {
            // Get queue position for this salon
            $queue_query = "SELECT COUNT(*) as count FROM appointments 
                           WHERE appointment_date = '$appointment_date' 
                           AND salon_id = $salon_id 
                           AND status NOT IN ('completed', 'cancelled', 'served')";
            $queue_result = mysqli_query($conn, $queue_query);
            $queue_position = ($queue_result && mysqli_fetch_assoc($queue_result)['count']) + 1;
            
            $query = "INSERT INTO appointments (customer_id, staff_id, service_id, salon_id, appointment_date, appointment_time, queue_position, payment_method, status) 
                      VALUES ($customer_id, $assigned_staff_id, $service_id, $salon_id, '$appointment_date', '$appointment_time', $queue_position, '$payment_method', 'pending')";
            
            if (mysqli_query($conn, $query)) {
                $appointment_id = mysqli_insert_id($conn);
                
                // Record payment
                $payment_query = "INSERT INTO payments (appointment_id, amount, payment_method, payment_status, salon_id) 
                                 VALUES ($appointment_id, {$service['price']}, '$payment_method', 'pending', $salon_id)";
                mysqli_query($conn, $payment_query);
                
                // Send notification to customer
                $message = "Dear {$customer['full_name']}, your appointment for {$service['service_name']} at {$salon['salon_name']} on $appointment_date at $appointment_time has been booked by our staff. Queue position: $queue_position. Thank you for choosing Salon Pro!";
                sendNotification($customer_id, "Appointment Booked by Staff", $message, 'email');
                sendSMS($customer['phone'], $message);
                
                // Send notification to assigned staff
                if ($assigned_staff_id != $staff_id) {
                    $staff_info = mysqli_query($conn, "SELECT full_name, phone, email FROM users WHERE id = $assigned_staff_id");
                    if ($staff_info && $staff_member = mysqli_fetch_assoc($staff_info)) {
                        $staff_message = "New appointment assigned: {$service['service_name']} with {$customer['full_name']} on $appointment_date at $appointment_time. Queue position: $queue_position";
                        sendNotification($assigned_staff_id, "New Appointment", $staff_message, 'email');
                        sendSMS($staff_member['phone'], $staff_message);
                    }
                }
                
                $success = "Appointment booked successfully for {$customer['full_name']}!";
            } else {
                $error = "Booking failed: " . mysqli_error($conn);
            }
        }
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

    .section-title {
        color: #d4af37;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    .booking-container {
        max-width: 600px;
        margin: 0 auto;
        background: #1a1a1a;
        border-radius: 20px;
        padding: 2rem;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }
    .booking-container h2 {
        text-align: center;
        color: #d4af37;
        margin-bottom: 0.5rem;
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
    .form-control, select {
        width: 100%;
        padding: 12px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 1rem;
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

    .back-link {
        display: block;
        text-align: center;
        margin-top: 1.5rem;
        color: #d4af37;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .booking-container { padding: 1.5rem; }
        .booking-container h2 { font-size: 1.3rem; }
        .section-title { font-size: 1.1rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .booking-container { padding: 1rem; }
        .booking-container h2 { font-size: 1.1rem; }
        .section-title { font-size: 1rem; }
        .form-control, select { padding: 10px; font-size: 0.9rem; }
        .btn-primary { padding: 10px; font-size: 0.9rem; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">📝 Book Appointment for Customer</h1>

    <div class="booking-container">

        <?php if($error): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
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
                <select name="service_id" class="form-control" required>
                    <option value="">-- Choose a service --</option>
                    <?php while($service = mysqli_fetch_assoc($services)): ?>
                    <option value="<?php echo $service['id']; ?>">
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
            
            <button type="submit" class="btn-primary">Confirm Booking</button>
        </form>
        <?php endif; ?>

        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

    </div>

</div>

<?php include '../includes/footer.php'; ?>