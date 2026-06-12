<?php
// auth/register.php - COMPLETELY FIXED
require_once '../config/database.php';

if (isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $check_query = "SELECT id FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = "Email already registered!";
        } else {
            // IMPORTANT: Explicitly set role to 'customer'
            $query = "INSERT INTO users (full_name, email, phone, password, role, is_active, created_at) 
                    VALUES ('$full_name', '$email', '$phone', '$hashed_password', 'customer', 1, NOW())";
            
            if (mysqli_query($conn, $query)) {
                $success = "Registration successful! Please login.";
                // Auto redirect to login after 2 seconds
                echo "<meta http-equiv='refresh' content='2;url=login.php'>";
            } else {
                $error = "Registration failed: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Salon Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            min-height: 100vh;
        }
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .auth-card {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(212, 175, 55, 0.3);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .auth-card h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: #d4af37;
            font-family: 'Playfair Display', serif;
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
        .password-wrapper {
            position: relative;
        }
        .form-control {
            width: 100%;
            padding: 12px 45px 12px 15px;
            background: #2a2a2a;
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.2);
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #d4af37;
            font-size: 1.1rem;
            transition: color 0.3s ease;
            background: transparent;
            border: none;
        }
        .toggle-password:hover {
            color: #f9e547;
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
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
        }
        .auth-footer a {
            color: #d4af37;
            text-decoration: none;
            transition: color 0.3s;
        }
        .auth-footer a:hover {
            color: #f9e547;
            text-decoration: underline;
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
            transition: all 0.3s ease;
        }
        button[type="submit"]:hover {
            background: #f9e547;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(212, 175, 55, 0.3);
        }
        .password-requirements {
            font-size: 0.7rem;
            color: #888;
            margin-top: 0.3rem;
        }
        .back-home {
            text-align: center;
            margin-top: 1rem;
        }
        .back-home a {
            color: #888;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .back-home a:hover {
            color: #d4af37;
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #d4af37;
        }
        .input-icon .form-control {
            padding-left: 40px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>✨ Create Account ✨</h2>
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?> Redirecting to login...</div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="full_name" class="form-control" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required placeholder="Enter your full name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required placeholder="Enter your email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <div class="input-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="phone" class="form-control" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required placeholder="0712345678">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-control" required placeholder="Create a password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', 'toggleIcon1')">
                            <i id="toggleIcon1" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-requirements">
                        <i class="fas fa-info-circle"></i> Password must be at least 6 characters
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="Confirm your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                            <i id="toggleIcon2" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit"><i class="fas fa-user-plus"></i> Register</button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login here</a></p>
            </div>
            
            <div class="back-home">
                <a href="../index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, iconId) {
            const passwordField = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            
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