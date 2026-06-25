<?php
// staff/profile.php - UPDATED with new hamburger sidebar layout
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get staff data
$query = "SELECT u.*, sd.specialty, sd.experience_years, sd.bio 
          FROM users u 
          LEFT JOIN staff_details sd ON u.id = sd.user_id 
          WHERE u.id = $staff_id";
$result = mysqli_query($conn, $query);
$staff = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $specialty = mysqli_real_escape_string($conn, $_POST['specialty']);
    $experience_years = intval($_POST['experience_years']);
    $staff_bio = mysqli_real_escape_string($conn, $_POST['staff_bio']);
    
    // Update user table
    $update = "UPDATE users SET full_name = '$full_name', phone = '$phone' WHERE id = $staff_id";
    if (mysqli_query($conn, $update)) {
        $_SESSION['user_name'] = $full_name;
        $success = "Profile updated successfully!";
        $staff['full_name'] = $full_name;
        $staff['phone'] = $phone;
    } else {
        $error = "Update failed: " . mysqli_error($conn);
    }
    
    // Update staff details
    $detail_update = "UPDATE staff_details SET 
                      specialty = '$specialty', 
                      experience_years = $experience_years, 
                      bio = '$staff_bio' 
                      WHERE user_id = $staff_id";
    if (mysqli_query($conn, $detail_update)) {
        if (empty($error)) {
            $success = "Profile updated successfully!";
        }
        $staff['specialty'] = $specialty;
        $staff['experience_years'] = $experience_years;
        $staff['bio'] = $staff_bio;
    } else {
        $error = "Failed to update staff details: " . mysqli_error($conn);
    }
    
    // Handle password change
    if (!empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        if (password_verify($current_password, $staff['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pass_update = "UPDATE users SET password = '$hashed' WHERE id = $staff_id";
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
        .form-control { font-size: 0.9rem; padding: 10px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .profile-container { padding: 1rem; }
        .section-title { font-size: 1rem; }
        .form-control { font-size: 0.85rem; padding: 8px; }
        .btn-primary { font-size: 0.9rem; padding: 10px; }
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

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($staff['email']); ?>" disabled>
                <small>Email cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($staff['phone']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Specialization</label>
                <select name="specialty" class="form-control" required>
                    <option value="">-- Select Specialization --</option>
                    <option value="Hair Stylist" <?php echo ($staff['specialty'] == 'Hair Stylist') ? 'selected' : ''; ?>>💇 Hair Stylist</option>
                    <option value="Makeup Artist" <?php echo ($staff['specialty'] == 'Makeup Artist') ? 'selected' : ''; ?>>💄 Makeup Artist</option>
                    <option value="Nail Technician" <?php echo ($staff['specialty'] == 'Nail Technician') ? 'selected' : ''; ?>>💅 Nail Technician</option>
                    <option value="Massage Therapist" <?php echo ($staff['specialty'] == 'Massage Therapist') ? 'selected' : ''; ?>>💆 Massage Therapist</option>
                    <option value="Barber" <?php echo ($staff['specialty'] == 'Barber') ? 'selected' : ''; ?>>✂️ Barber</option>
                    <option value="Esthetician" <?php echo ($staff['specialty'] == 'Esthetician') ? 'selected' : ''; ?>>✨ Esthetician</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Years of Experience</label>
                <input type="number" name="experience_years" class="form-control" value="<?php echo $staff['experience_years']; ?>" min="0" max="50" required>
            </div>
            
            <div class="form-group">
                <label>Bio / About Me</label>
                <textarea name="staff_bio" class="form-control" rows="4"><?php echo htmlspecialchars($staff['bio']); ?></textarea>
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