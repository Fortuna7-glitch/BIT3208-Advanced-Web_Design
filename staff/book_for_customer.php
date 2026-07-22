<?php
// staff/book_for_customer.php - MODIFIED: Permission check for create_appointments
require_once '../config/database.php';
require_once '../includes/permissions.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];

// ============================================
// PERMISSION CHECK
// ============================================
if (!hasPermission($staff_id, 'create_appointments')) {
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
            <p style="color: #aaa;">You don't have permission to book appointments for customers.</p>
            <a href="dashboard.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Get salon_id from session
$salon_id = $_SESSION['salon_id'] ?? 0;
if ($salon_id <= 0) {
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $staff_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

$error = '';
$success = '';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_appointment = null;

// ============================================
// GET EDIT APPOINTMENT DATA (if editing)
// ============================================
if ($edit_id > 0) {
    if (!hasPermission($staff_id, 'edit_appointments')) {
        $error = "You don't have permission to edit appointments.";
    } else {
        $edit_query = "SELECT * FROM appointments WHERE id = $edit_id AND staff_id = $staff_id";
        $edit_result = mysqli_query($conn, $edit_query);
        $edit_appointment = mysqli_fetch_assoc($edit_result);
        if (!$edit_appointment) {
            $error = "Appointment not found or not assigned to you.";
        }
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

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    // Double-check permission
    if (!hasPermission($staff_id, 'create_appointments')) {
        $error = "You don't have permission to book appointments.";
    } else {
        $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
        $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
        $assigned_staff_id = !empty($_POST['staff_id']) ? mysqli_real_escape_string($conn, $_POST['staff_id']) : $staff_id;
        $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);
        $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        
        // If editing, use the edit ID
        if ($edit_id > 0) {
            $appointment_id = $edit_id;
        } else {
            $appointment_id = 0;
        }
        
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
                if ($appointment_id > 0) {
                    // UPDATE existing appointment
                    $query = "UPDATE appointments SET 
                              customer_id = $customer_id, 
                              staff_id = $assigned_staff_id, 
                              service_id = $service_id, 
                              appointment_date = '$appointment_date', 
                              appointment_time = '$appointment_time', 
                              payment_method = '$payment_method'
                              WHERE id = $appointment_id AND staff_id = $staff_id";
                    
                    if (mysqli_query($conn, $query)) {
                        $success = "Appointment updated successfully!";
                        $edit_appointment = null;
                        $edit_id = 0;
                    } else {
                        $error = "Failed to update appointment: " . mysqli_error($conn);
                    }
                } else {
                    // INSERT new appointment
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
                        $message = "Dear {$customer['full_name']}, your appointment for {$service['service_name']} at {$salon_name} on $appointment_date at $appointment_time has been booked by our staff. Queue position: $queue_position. Thank you for choosing Salon Pro!";
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
    }
}

// If editing, pre-populate form data
$edit_data = [];
if ($edit_appointment) {
    $edit_data = $edit_appointment;
}

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

    /* Booking Container */
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

    .booking-container .subtitle {
        text-align: center;
        color: #888;
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
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

    .btn-secondary {
        display: inline-block;
        padding: 10px 25px;
        background: #2a2a2a;
        color: #aaa;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        text-align: center;
    }

    .btn-secondary:hover {
        background: #333;
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

    .price-display {
        font-size: 1.5rem;
        font-weight: bold;
        color: #d4af37;
        margin-top: 0.5rem;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .form-actions .btn-primary {
        flex: 2;
    }

    .form-actions .btn-secondary {
        flex: 1;
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
        .booking-container { padding: 1.5rem; }
        .form-actions { flex-direction: column; }
        .form-actions .btn-secondary { width: 100%; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .booking-container { padding: 1rem; }
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
            <h1><?php echo $edit_id > 0 ? '✏️ Edit Appointment' : '📝 Book Appointment for Customer'; ?></h1>
            <p><?php echo $edit_id > 0 ? 'Update appointment details' : 'Create a new appointment on behalf of a customer'; ?></p>
        </div>
        <div class="quick-actions">
            <a href="appointments.php" class="quick-btn"><i class="fas fa-list"></i> My Appointments</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <!-- ============================================
       BOOKING FORM
       ============================================ -->
    <div class="booking-container">

        <?php if($error): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <?php if(!$success || $edit_id > 0): ?>
        <form method="POST">
            <div class="form-group">
                <label>Select Customer</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">-- Choose a customer --</option>
                    <?php while($customer = mysqli_fetch_assoc($customers)): ?>
                    <option value="<?php echo $customer['id']; ?>" <?php echo (isset($edit_data['customer_id']) && $edit_data['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
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
                    <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>" <?php echo (isset($edit_data['service_id']) && $edit_data['service_id'] == $service['id']) ? 'selected' : ''; ?>>
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
                    <option value="<?php echo $staff_member['id']; ?>" <?php echo (isset($edit_data['staff_id']) && $edit_data['staff_id'] == $staff_member['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($staff_member['full_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Appointment Date</label>
                <input type="date" name="appointment_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($edit_data['appointment_date']) ? $edit_data['appointment_date'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Appointment Time</label>
                <input type="time" name="appointment_time" class="form-control" required value="<?php echo isset($edit_data['appointment_time']) ? $edit_data['appointment_time'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" class="form-control" required>
                    <option value="cash" <?php echo (isset($edit_data['payment_method']) && $edit_data['payment_method'] == 'cash') ? 'selected' : ''; ?>>💵 Cash</option>
                    <option value="mpesa" <?php echo (isset($edit_data['payment_method']) && $edit_data['payment_method'] == 'mpesa') ? 'selected' : ''; ?>>📱 M-PESA</option>
                </select>
            </div>

            <div class="form-group">
                <label>Total Amount</label>
                <div class="price-display" id="total_amount">
                    KSh <?php echo isset($edit_data['price']) ? number_format($edit_data['price'], 2) : '0.00'; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="book_appointment" class="btn-primary">
                    <?php echo $edit_id > 0 ? '💾 Update Appointment' : '✅ Confirm Booking'; ?>
                </button>
                <a href="appointments.php" class="btn-secondary">Cancel</a>
            </div>
        </form>
        <?php else: ?>
            <div style="text-align: center; padding: 1rem 0;">
                <a href="book_for_customer.php" class="btn-primary" style="display: inline-block; width: auto; padding: 10px 30px;">📝 Book Another</a>
                <a href="appointments.php" class="btn-secondary" style="display: inline-block; margin-top: 0.5rem;">View My Appointments</a>
            </div>
        <?php endif; ?>

    </div>

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
    }
</script>

<?php include '../includes/footer.php'; ?>