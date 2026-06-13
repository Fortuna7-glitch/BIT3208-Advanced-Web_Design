<?php
// customer/book.php - UPDATED with salon_id filtering
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    // Save the redirect URL for after login
    $redirect_url = 'customer/book.php';
    $salon_id_param = isset($_GET['salon_id']) ? (int)$_GET['salon_id'] : 0;
    header("Location: ../auth/login.php?redirect=$redirect_url&salon_id=$salon_id_param");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get salon_id from URL parameter
$salon_id = isset($_GET['salon_id']) ? (int)$_GET['salon_id'] : 0;

// If no salon_id, redirect to find salons page
if ($salon_id <= 0) {
    header("Location: ../find_salons.php");
    exit();
}

// Verify salon exists and is active
$salon_check = mysqli_query($conn, "SELECT id, salon_name FROM salons WHERE id = $salon_id AND subscription_status = 'active'");
if (mysqli_num_rows($salon_check) == 0) {
    header("Location: ../find_salons.php");
    exit();
}
$salon = mysqli_fetch_assoc($salon_check);

// Get services for THIS specific salon only
$services_query = "SELECT * FROM services WHERE salon_id = $salon_id AND is_active = 1 ORDER BY price ASC";
$services = mysqli_query($conn, $services_query);

// Get staff for THIS specific salon only
$staff_query = "SELECT u.*, sd.specialty 
                FROM users u 
                LEFT JOIN staff_details sd ON u.id = sd.user_id 
                WHERE u.role = 'staff' AND u.salon_id = $salon_id AND u.is_active = 1";
$staff = mysqli_query($conn, $staff_query);

// Get selected service from URL (if coming from salon page)
$selected_service = isset($_GET['service']) ? (int)$_GET['service'] : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $staff_id = !empty($_POST['staff_id']) ? mysqli_real_escape_string($conn, $_POST['staff_id']) : 'NULL';
    $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);
    $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    
    // Verify service belongs to this salon
    $service_check = mysqli_query($conn, "SELECT price, service_name FROM services WHERE id = $service_id AND salon_id = $salon_id");
    if (mysqli_num_rows($service_check) == 0) {
        $error = "Invalid service selected.";
    } else {
        $service = mysqli_fetch_assoc($service_check);
        
        // Verify staff belongs to this salon (if selected)
        if ($staff_id != 'NULL') {
            $staff_check = mysqli_query($conn, "SELECT id FROM users WHERE id = $staff_id AND salon_id = $salon_id AND role = 'staff'");
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
                      VALUES ($user_id, $staff_id, $service_id, $salon_id, '$appointment_date', '$appointment_time', $queue_position, '$payment_method', 'pending')";
            
            if (mysqli_query($conn, $query)) {
                $appointment_id = mysqli_insert_id($conn);
                
                // Record payment
                $payment_query = "INSERT INTO payments (appointment_id, amount, payment_method, payment_status, salon_id) 
                                 VALUES ($appointment_id, {$service['price']}, '$payment_method', 'pending', $salon_id)";
                mysqli_query($conn, $payment_query);
                
                // Get customer details
                $user_query = "SELECT full_name, phone, email FROM users WHERE id = $user_id";
                $user_result = mysqli_query($conn, $user_query);
                $customer = mysqli_fetch_assoc($user_result);
                
                // Send notification to customer
                $message = "Dear {$customer['full_name']}, your appointment for {$service['service_name']} at {$salon['salon_name']} on $appointment_date at $appointment_time has been confirmed. Queue position: $queue_position. Thank you for choosing Salon Pro!";
                sendNotification($user_id, "Appointment Confirmed", $message, 'email');
                sendSMS($customer['phone'], $message);
                sendEmail($customer['email'], "Appointment Confirmed - Salon Pro", $message);
                
                // Send notification to staff if assigned
                if ($staff_id != 'NULL') {
                    $staff_query = "SELECT full_name, phone, email FROM users WHERE id = $staff_id";
                    $staff_result = mysqli_query($conn, $staff_query);
                    if ($staff_result && $staff_member = mysqli_fetch_assoc($staff_result)) {
                        $staff_message = "New appointment: {$service['service_name']} with {$customer['full_name']} at {$salon['salon_name']} on $appointment_date at $appointment_time. Queue position: $queue_position";
                        sendNotification($staff_id, "New Appointment", $staff_message, 'email');
                        sendSMS($staff_member['phone'], $staff_message);
                    }
                }
                
                $success = "Appointment booked successfully at {$salon['salon_name']}!";
                echo "<meta http-equiv='refresh' content='2;url=dashboard.php'>";
            } else {
                $error = "Booking failed: " . mysqli_error($conn);
            }
        }
    }
}

include '../includes/header.php';
?>

<style>
    .booking-container {
        max-width: 600px;
        margin: 2rem auto;
        background: #1a1a1a;
        border-radius: 20px;
        padding: 2rem;
    }
    .booking-container h2 {
        text-align: center;
        color: #d4af37;
        margin-bottom: 0.5rem;
    }
    .salon-name-badge {
        text-align: center;
        color: #aaa;
        margin-bottom: 2rem;
        font-size: 0.9rem;
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
    .price {
        font-size: 1.5rem;
        color: #d4af37;
        font-weight: bold;
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
    button[type="submit"] {
        width: 100%;
        padding: 12px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 50px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
    }
    button[type="submit"]:hover {
        background: #f9e547;
    }
    .back-link {
        display: block;
        text-align: center;
        margin-top: 1rem;
        color: #d4af37;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }
</style>

<div class="booking-container">
    <h2>✨ Book Your Appointment ✨</h2>
    <div class="salon-name-badge">🏢 Booking at: <strong><?php echo htmlspecialchars($salon['salon_name']); ?></strong></div>
    
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?> Redirecting...</div>
    <?php endif; ?>
    
    <?php if(!$success): ?>
    <form method="POST">
        <div class="form-group">
            <label>Select Service</label>
            <select name="service_id" class="form-control" required id="service_select">
                <option value="">-- Choose a service --</option>
                <?php while($service = mysqli_fetch_assoc($services)): ?>
                <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>" <?php echo ($selected_service == $service['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($service['service_name']); ?> - KSh <?php echo number_format($service['price'], 2); ?> (<?php echo $service['duration_minutes']; ?> mins)
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Select Stylist (Optional)</label>
            <select name="staff_id" class="form-control">
                <option value="">-- Any stylist --</option>
                <?php while($staff_member = mysqli_fetch_assoc($staff)): ?>
                <option value="<?php echo $staff_member['id']; ?>">
                    <?php echo htmlspecialchars($staff_member['full_name']); ?> <?php echo !empty($staff_member['specialty']) ? '(' . htmlspecialchars($staff_member['specialty']) . ')' : ''; ?>
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
        
        <div class="form-group">
            <label>Total Amount</label>
            <div class="price" id="total_amount">KSh 0.00</div>
        </div>
        
        <button type="submit">Confirm Booking</button>
        <a href="../salon.php?id=<?php echo $salon_id; ?>" class="back-link">← Back to Salon</a>
    </form>
    <?php endif; ?>
</div>

<script>
    const serviceSelect = document.getElementById('service_select');
    const totalSpan = document.getElementById('total_amount');
    
    if (serviceSelect) {
        serviceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            if (price) {
                totalSpan.innerHTML = 'KSh ' + parseFloat(price).toLocaleString('en-KE', {minimumFractionDigits: 2});
            } else {
                totalSpan.innerHTML = 'KSh 0.00';
            }
        });
        
        // Trigger change if pre-selected
        if (serviceSelect.value) {
            serviceSelect.dispatchEvent(new Event('change'));
        }
    }
</script>

<?php include '../includes/footer.php'; ?>