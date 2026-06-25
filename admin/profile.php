<?php
// admin/profile.php - UPDATED with new hamburger sidebar layout
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get admin data
$query = "SELECT * FROM users WHERE id = $admin_id";
$result = mysqli_query($conn, $query);
$admin = mysqli_fetch_assoc($result);

// Get salon info
$salon_query = "SELECT * FROM salons WHERE id = {$admin['salon_id']}";
$salon_result = mysqli_query($conn, $salon_query);
$salon = mysqli_fetch_assoc($salon_result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    $update = "UPDATE users SET full_name = '$full_name', phone = '$phone' WHERE id = $admin_id";
    if (mysqli_query($conn, $update)) {
        $_SESSION['user_name'] = $full_name;
        $success = "Profile updated successfully!";
        $admin['full_name'] = $full_name;
        $admin['phone'] = $phone;
    } else {
        $error = "Update failed: " . mysqli_error($conn);
    }
    
    // Handle password change
    if (!empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        if (password_verify($current_password, $admin['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pass_update = "UPDATE users SET password = '$hashed' WHERE id = $admin_id";
            if (mysqli_query($conn, $pass_update)) {
                $success .= " Password changed successfully!";
            } else {
                $error = "Failed to update password: " . mysqli_error($conn);
            }
        } else {
            $error = "Current password is incorrect!";
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

    .profile-container {
        max-width: 600px;
        margin: 0 auto;
        background: #1a1a1a;
        border-radius: 20px;
        padding: 2rem;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .salon-info-box {
        background: #0a0a0a;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 2rem;
        border-left: 4px solid #d4af37;
    }
    .salon-info-box h3 {
        color: #d4af37;
        margin-bottom: 0.5rem;
    }
    .salon-info-box p {
        color: #aaa;
        font-size: 0.9rem;
        margin: 0.3rem 0;
    }
    .plan-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: bold;
    }
    .plan-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .plan-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: #d4af37;
        font-weight: 500;
    }
    .form-control {
        width: 100%;
        padding: 12px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 1rem;
    }
    .form-control:focus {
        outline: none;
        border-color: #d4af37;
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

    hr {
        border-color: rgba(212, 175, 55, 0.3);
        margin: 2rem 0;
    }

    .sub-title {
        color: #d4af37;
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }

    small {
        color: #888;
        font-size: 0.8rem;
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
        .profile-container { padding: 1.5rem; }
        .section-title { font-size: 1.1rem; }
        .salon-info-box h3 { font-size: 1.1rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .profile-container { padding: 1rem; }
        .section-title { font-size: 1rem; }
        .form-control { padding: 10px; font-size: 0.9rem; }
        .btn-primary { padding: 10px; font-size: 0.9rem; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">⚙️ My Profile</h1>

    <div class="profile-container">

        <?php if($error): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Salon Info -->
        <div class="salon-info-box">
            <h3>🏢 <?php echo htmlspecialchars($salon['salon_name']); ?></h3>
            <p>📧 <?php echo htmlspecialchars($salon['salon_email']); ?> &nbsp;|&nbsp; 📞 <?php echo htmlspecialchars($salon['salon_phone']); ?></p>
            <p>Plan: <span class="plan-badge plan-<?php echo $salon['subscription_plan']; ?>">
                <?php echo ucfirst($salon['subscription_plan']); ?>
            </span></p>
        </div>

        <!-- Profile Update Form -->
        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" disabled>
                <small>Email cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone']); ?>" required>
            </div>
            
            <hr>
            
            <h3 class="sub-title">🔑 Change Password</h3>
            
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control">
            </div>
            
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control">
            </div>
            
            <button type="submit" class="btn-primary">💾 Update Profile</button>
        </form>

    </div>

</div>

<?php include '../includes/footer.php'; ?>