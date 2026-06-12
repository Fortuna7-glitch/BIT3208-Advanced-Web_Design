<?php
// customer/update-profile.php - COMPLETE FILE
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
$user = mysqli_fetch_assoc($user_query);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    $query = "UPDATE users SET full_name = '$full_name', phone = '$phone' WHERE id = $user_id";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['user_name'] = $full_name;
        $success = "Profile updated successfully!";
        $user['full_name'] = $full_name;
        $user['phone'] = $phone;
    } else {
        $error = "Update failed: " . mysqli_error($conn);
    }
    
    // Handle password change
    if (!empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        if (password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            if (mysqli_query($conn, "UPDATE users SET password = '$hashed_password' WHERE id = $user_id")) {
                $success .= " Password changed successfully!";
            }
        } else {
            $error = "Current password is incorrect!";
        }
    }
}

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
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
    .form-control { width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; font-size: 1rem; }
    .btn-primary { padding: 12px 30px; background: #d4af37; color: #050505; border: none; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; }
    .btn-primary:hover { background: #f9e547; transform: translateY(-2px); }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
    hr { border-color: rgba(212, 175, 55, 0.3); margin: 2rem 0; }
    h1, h3 { color: #d4af37; }
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
    small { color: #d4af37; font-size: 0.8rem; }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <p>Customer</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="book.php">✨ New Booking</a></li>
            <li><a href="appointments.php">📅 My Appointments</a></li>
            <li><a href="update-profile.php" class="active">⚙️ Update Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Update Profile ⚙️</h1>
        
        <div style="max-width: 600px;">
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <small>Email cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                </div>
                
                <hr>
                
                <h3>Change Password</h3>
                
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control">
                </div>
                
                <button type="submit" class="btn-primary">Update Profile</button>
            </form>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>