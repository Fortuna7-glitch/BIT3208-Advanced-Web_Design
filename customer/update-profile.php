<?php
// customer/update-profile.php - UPDATED with new hamburger sidebar layout
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../auth/login.php');
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

    small {
        color: #888;
        font-size: 0.8rem;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .profile-container { padding: 1.5rem; }
        .section-title { font-size: 1.1rem; }
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

    <h1 class="section-title">⚙️ Update Profile</h1>

    <div class="profile-container">
        <?php if($error): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
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