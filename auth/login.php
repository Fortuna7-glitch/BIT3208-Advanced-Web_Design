<?php
// auth/login.php - UPDATED with redirect and salon_id parameters
require_once '../config/database.php';

// Capture redirect parameters from URL
$redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$salon_id = isset($_GET['salon_id']) ? (int)$_GET['salon_id'] : 0;

// If already logged in, redirect based on role
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    // If there's a redirect parameter, go there first
    if (!empty($redirect_url)) {
        $redirect_param = !empty($salon_id) ? "?salon_id=$salon_id" : "";
        header("Location: ../$redirect_url$redirect_param");
        exit();
    }
    
    // Otherwise go to role-specific dashboard
    if ($_SESSION['user_role'] == 'super_admin') {
        header("Location: ../super_admin/dashboard.php");
        exit();
    } elseif ($_SESSION['user_role'] == 'admin') {
        header("Location: ../admin/dashboard.php");
        exit();
    } elseif ($_SESSION['user_role'] == 'staff') {
        header("Location: ../staff/dashboard.php");
        exit();
    } elseif ($_SESSION['user_role'] == 'customer') {
        header("Location: ../customer/dashboard.php");
        exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    $query = "SELECT * FROM users WHERE email = '$email' AND is_active = 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['salon_id'] = $user['salon_id'] ?? 1;
            
            // Check if there's a redirect parameter
            if (!empty($redirect_url)) {
                $redirect_param = !empty($salon_id) ? "?salon_id=$salon_id" : "";
                header("Location: ../$redirect_url$redirect_param");
                exit();
            }
            
            // Redirect based on role
            if ($user['role'] == 'super_admin') {
                header("Location: ../super_admin/dashboard.php");
                exit();
            } elseif ($user['role'] == 'admin') {
                header("Location: ../admin/dashboard.php");
                exit();
            } elseif ($user['role'] == 'staff') {
                header("Location: ../staff/dashboard.php");
                exit();
            } elseif ($user['role'] == 'customer') {
                header("Location: ../customer/dashboard.php");
                exit();
            } else {
                $error = "Account role not recognized. Please contact admin.";
            }
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Email not found or account inactive!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Salon Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%); min-height: 100vh; }
        .auth-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .auth-card { background: #1a1a1a; border-radius: 20px; padding: 2.5rem; width: 100%; max-width: 450px; border: 1px solid rgba(212, 175, 55, 0.3); box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .auth-card h2 { text-align: center; margin-bottom: 2rem; color: #d4af37; font-family: 'Playfair Display', serif; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
        .password-wrapper { position: relative; }
        .form-control { width: 100%; padding: 12px 45px 12px 15px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; font-size: 1rem; }
        .form-control:focus { outline: none; border-color: #d4af37; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #d4af37; background: transparent; border: none; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
        .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
        button[type="submit"] { width: 100%; padding: 12px; background: #d4af37; color: #050505; border: none; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        button[type="submit"]:hover { background: #f9e547; transform: translateY(-2px); }
        .demo-credentials { margin-top: 1.5rem; padding: 1rem; background: #0a0a0a; border-radius: 8px; font-size: 0.8rem; border-left: 3px solid #d4af37; }
        .demo-credentials strong { color: #d4af37; }
        .auth-footer { text-align: center; margin-top: 1.5rem; }
        .auth-footer a { color: #d4af37; text-decoration: none; }
        .back-home { text-align: center; margin-top: 1rem; }
        .back-home a { color: #888; text-decoration: none; }
        .redirect-notice {
            background: rgba(212, 175, 55, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.85rem;
            color: #d4af37;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>🔐 Welcome Back</h2>
            
            <!-- Show redirect notice if returning to booking -->
            <?php if($redirect_url == 'customer/book.php' && $salon_id > 0): ?>
            <div class="redirect-notice">
                📍 Please login to complete your booking
            </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-control" required placeholder="Enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i id="toggleIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit">Login</button>
            </form>
            
            <div class="demo-credentials">
                <strong>✨ Demo Credentials:</strong><br>
                👑 Super Admin: fortuna@salonpro.com / super123<br>
                👨‍💼 Salon Owner: admin@salonpro.com / owner123<br>
                👤 Customer: (Register new account)
            </div>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php?redirect=<?php echo urlencode($redirect_url); ?>&salon_id=<?php echo $salon_id; ?>">Register here</a></p>
            </div>
            
            <div class="back-home">
                <a href="../index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
    </div>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>