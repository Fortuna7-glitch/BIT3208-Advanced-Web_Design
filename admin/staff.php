<?php
// admin/staff.php - COMPLETE FIXED FILE with working Add/Deactivate/Activate/Delete
require_once '../config/database.php';

// ============================================
// AUTHENTICATION CHECK
// ============================================
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// ============================================
// GET SALON_ID DIRECTLY FROM DATABASE
// ============================================
$user_id = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $user_id");
if ($user_result = mysqli_fetch_assoc($user_query)) {
    $salon_id = $user_result['salon_id'];
    $_SESSION['salon_id'] = $salon_id;
} else {
    $salon_id = 0;
}

// ============================================
// HANDLE ADD STAFF (FIXED)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $specialty = mysqli_real_escape_string($conn, $_POST['specialty'] ?? '');
    $experience = (int)($_POST['experience'] ?? 0);
    $bio = mysqli_real_escape_string($conn, $_POST['bio'] ?? '');
    $password = password_hash('staff123', PASSWORD_DEFAULT);
    
    // Validate required fields
    if (empty($full_name) || empty($email) || empty($phone) || empty($specialty)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if email already exists
        $email_check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        if (mysqli_num_rows($email_check) > 0) {
            $error = "Email already registered!";
        } else {
            // Insert user
            $query = "INSERT INTO users (full_name, email, phone, password, role, salon_id, is_active) 
                    VALUES ('$full_name', '$email', '$phone', '$password', 'staff', $salon_id, 1)";
            
            if (mysqli_query($conn, $query)) {
                $new_user_id = mysqli_insert_id($conn);
                
                // Insert staff details
                $detail_query = "INSERT INTO staff_details (user_id, specialty, experience_years, bio, salon_id) 
                                VALUES ($new_user_id, '$specialty', '$experience', '$bio', $salon_id)";
                mysqli_query($conn, $detail_query);
                
                $success = "Staff added successfully! Default password: staff123";
            } else {
                $error = "Failed to add staff: " . mysqli_error($conn);
            }
        }
    }
}

// ============================================
// HANDLE DEACTIVATE STAFF
// ============================================
if (isset($_GET['deactivate']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $id AND salon_id = $salon_id AND role = 'staff'");
    if (mysqli_num_rows($check) == 1) {
        $query = "UPDATE users SET is_active = 0 WHERE id = $id AND role = 'staff' AND salon_id = $salon_id";
        if (mysqli_query($conn, $query)) {
            $success = "Staff deactivated successfully!";
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    } else {
        $error = "Staff not found or does not belong to your salon.";
    }
}

// ============================================
// HANDLE ACTIVATE STAFF
// ============================================
if (isset($_GET['activate']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $id AND salon_id = $salon_id AND role = 'staff'");
    if (mysqli_num_rows($check) == 1) {
        $query = "UPDATE users SET is_active = 1 WHERE id = $id AND role = 'staff' AND salon_id = $salon_id";
        if (mysqli_query($conn, $query)) {
            $success = "Staff activated successfully!";
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    } else {
        $error = "Staff not found or does not belong to your salon.";
    }
}

// ============================================
// HANDLE DELETE STAFF (PERMANENT)
// ============================================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $id AND salon_id = $salon_id AND role = 'staff'");
    if (mysqli_num_rows($check) == 1) {
        mysqli_query($conn, "DELETE FROM staff_details WHERE user_id = $id");
        $query = "DELETE FROM users WHERE id = $id AND role = 'staff' AND salon_id = $salon_id";
        if (mysqli_query($conn, $query)) {
            $success = "Staff permanently deleted!";
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    } else {
        $error = "Staff not found or does not belong to your salon.";
    }
}

// ============================================
// GET STAFF LIST (USING CORRECT USER ID)
// ============================================
$staff_query = "SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.created_at,
                    sd.specialty, sd.experience_years, sd.bio
                FROM users u 
                LEFT JOIN staff_details sd ON u.id = sd.user_id 
                WHERE u.role = 'staff' AND u.salon_id = $salon_id 
                ORDER BY u.is_active DESC, u.full_name ASC";
$staff = mysqli_query($conn, $staff_query);

// ============================================
// INCLUDE HEADER
// ============================================
include '../includes/header.php';
?>

<style>
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    
    .form-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid rgba(212, 175, 55, 0.3); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
    .form-control, select, textarea { width: 100%; padding: 10px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; font-size: 0.95rem; }
    .btn-primary { background: #d4af37; color: #050505; border: none; padding: 10px 25px; border-radius: 25px; cursor: pointer; font-weight: 600; transition: all 0.3s; }
    .btn-primary:hover { background: #f9e547; transform: translateY(-2px); }
    .btn-danger { background: #dc3545; color: white; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; transition: all 0.3s; }
    .btn-danger:hover { background: #c82333; }
    .btn-warning { background: #d4af37; color: #050505; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; transition: all 0.3s; }
    .btn-warning:hover { background: #f9e547; }
    .btn-success { background: #28a745; color: white; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; transition: all 0.3s; }
    .btn-success:hover { background: #218838; }
    
    .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 1rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
    th { color: #d4af37; font-weight: 600; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
    
    .status-active { color: #28a745; font-weight: bold; }
    .status-inactive { color: #dc3545; font-weight: bold; }
    
    h1 { color: #d4af37; }
    
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php">💇 Services</a></li>
            <li><a href="staff.php" class="active">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <li><a href="payments.php">💰 Payments</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Staff Management 👥</h1>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Add Staff Form -->
        <div class="form-card">
            <h3 style="color: #d4af37;">➕ Add New Staff Member</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Specialty</label>
                        <input type="text" name="specialty" class="form-control" placeholder="e.g., Hair Stylist" required>
                    </div>
                    <div class="form-group">
                        <label>Years of Experience</label>
                        <input type="number" name="experience" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Bio / Description</label>
                        <textarea name="bio" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <button type="submit" name="add_staff" class="btn-primary">➕ Add Staff</button>
            </form>
            <p style="margin-top: 1rem; font-size: 0.8rem; color: #888;">Default password for new staff: <strong style="color: #d4af37;">staff123</strong></p>
        </div>
        
        <!-- Staff List -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Specialty</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($staff && mysqli_num_rows($staff) > 0): ?>
                        <?php while($staff_member = mysqli_fetch_assoc($staff)): ?>
                        <tr>
                            <td><?php echo $staff_member['id']; ?></td>
                            <td><?php echo htmlspecialchars($staff_member['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($staff_member['email']); ?></td>
                            <td><?php echo htmlspecialchars($staff_member['phone']); ?></td>
                            <td><?php echo htmlspecialchars($staff_member['specialty']); ?></td>
                            <td><?php echo $staff_member['experience_years']; ?> years</td>
                            <td class="status-<?php echo $staff_member['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $staff_member['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                            </td>
                            <td>
                                <?php if($staff_member['is_active']): ?>
                                    <a href="?deactivate=1&id=<?php echo $staff_member['id']; ?>" class="btn-warning" onclick="return confirm('Deactivate this staff member? They will not be able to log in.')">⏸️ Deactivate</a>
                                <?php else: ?>
                                    <a href="?activate=1&id=<?php echo $staff_member['id']; ?>" class="btn-success" onclick="return confirm('Activate this staff member?')">▶️ Activate</a>
                                <?php endif; ?>
                                <a href="?delete=1&id=<?php echo $staff_member['id']; ?>" class="btn-danger" onclick="return confirm('⚠️ PERMANENTLY DELETE this staff member? This cannot be undone!')">🗑️ Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No staff members found for your salon.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>