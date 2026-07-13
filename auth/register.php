<?php
// auth/register.php - FIXED: No default salon assignment
require_once '../config/database.php';

if (isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

// Get salon_id from URL parameter (if a specific salon was selected)
$salon_id = isset($_GET['salon_id']) ? (int)$_GET['salon_id'] : 0;

// Verify salon exists if a salon_id was provided
$salon = null;
if ($salon_id > 0) {
    $salon_check = mysqli_query($conn, "SELECT id, salon_name FROM salons WHERE id = $salon_id AND subscription_status = 'active'");
    if (mysqli_num_rows($salon_check) > 0) {
        $salon = mysqli_fetch_assoc($salon_check);
    } else {
        $salon_id = 0; // Reset if invalid
    }
}

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
            // INSERT without assigning a salon (customer will be assigned when they book)
            $query = "INSERT INTO users (full_name, email, phone, password, role, is_active) 
                      VALUES ('$full_name', '$email', '$phone', '$hashed_password', 'customer', 1)";
            
            if (mysqli_query($conn, $query)) {
                $success = "Registration successful! Please login.";
                // Redirect to login without salon_id
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%); min-height: 100vh; }
        .auth-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .auth-card { background: #1a1a1a; border-radius: 20px; padding: 2.5rem; width: 100%; max-width: 500px; border: 1px solid rgba(212, 175, 55, 0.3); box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .auth-card h2 { text-align: center; margin-bottom: 2rem; color: #d4af37; font-family: 'Playfair Display', serif; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
        .password-wrapper { position: relative; }
        .form-control { width: 100%; padding: 12px 45px 12px 15px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; font-size: 1rem; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #d4af37; background: transparent; border: none; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
        .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
        .auth-footer { text-align: center; margin-top: 1.5rem; }
        .auth-footer a { color: #d4af37; text-decoration: none; }
        button[type="submit"] { width: 100%; padding: 12px; background: #d4af37; color: #050505; border: none; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button[type="submit"]:hover { background: #f9e547; }
        .password-requirements { font-size: 0.7rem; color: #888; margin-top: 0.3rem; }
        .back-home { text-align: center; margin-top: 1rem; }
        .back-home a { color: #888; text-decoration: none; }
        .input-icon { position: relative; }
        .input-icon i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #d4af37; }
        .input-icon .form-control { padding-left: 40px; }
        .salon-notice {
            text-align: center;
            padding: 10px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 8px;
            margin-bottom: 1.5rem;
            color: #d4af37;
            font-size: 0.9rem;
        }
        .salon-notice .highlight {
            color: #fff;
            font-weight: 600;
        }
        
        /* Password Strength Styles */
        .password-strength { margin-top: 8px; padding: 8px; border-radius: 8px; background: #0a0a0a; }
        .strength-bar { height: 4px; border-radius: 2px; margin: 8px 0; transition: all 0.3s ease; }
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #17a2b8; width: 75%; }
        .strength-strong { background: #28a745; width: 100%; }
        .text-weak { color: #dc3545; }
        .text-fair { color: #ffc107; }
        .text-good { color: #17a2b8; }
        .text-strong { color: #28a745; }
        .requirement-list { list-style: none; margin-top: 8px; font-size: 0.7rem; }
        .requirement-list li { margin: 3px 0; color: #888; }
        .requirement-list li.valid { color: #28a745; }
        .requirement-list li.invalid { color: #dc3545; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>✨ Create Your Account</h2>
            
            <?php if($salon_id > 0 && $salon): ?>
                <div class="salon-notice">
                    📍 Registering for: <span class="highlight"><?php echo htmlspecialchars($salon['salon_name']); ?></span>
                </div>
            <?php else: ?>
                <div class="salon-notice">
                    🏢 Join Salon Pro — you can choose a salon after registering
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?> Redirecting to login...</div>
            <?php endif; ?>
            
            <form method="POST" id="registerForm">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="full_name" class="form-control" required placeholder="Enter your full name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" required placeholder="Enter your email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <div class="input-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="phone" class="form-control" required placeholder="0712345678">
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
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar" id="strengthBar"></div>
                        <div class="strength-text" id="strengthText">Enter a password</div>
                    </div>
                    <ul class="requirement-list" id="requirementList">
                        <li id="req-length">✗ At least 6 characters</li>
                        <li id="req-uppercase">✗ At least 1 uppercase letter (A-Z)</li>
                        <li id="req-lowercase">✗ At least 1 lowercase letter (a-z)</li>
                        <li id="req-number">✗ At least 1 number (0-9)</li>
                        <li id="req-special">✗ At least 1 special character (!@#$%^&*)</li>
                    </ul>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="Confirm your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                            <i id="toggleIcon2" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="matchMessage" style="font-size: 0.7rem; margin-top: 5px;"></div>
                </div>
                
                <button type="submit" id="submitBtn"><i class="fas fa-user-plus"></i> Register</button>
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
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
            
            document.getElementById('req-length').innerHTML = (password.length >= 6) ? '✓ At least 6 characters' : '✗ At least 6 characters';
            document.getElementById('req-length').style.color = (password.length >= 6) ? '#28a745' : '#dc3545';
            
            document.getElementById('req-uppercase').innerHTML = (/[A-Z]/.test(password)) ? '✓ At least 1 uppercase letter (A-Z)' : '✗ At least 1 uppercase letter (A-Z)';
            document.getElementById('req-uppercase').style.color = (/[A-Z]/.test(password)) ? '#28a745' : '#dc3545';
            
            document.getElementById('req-lowercase').innerHTML = (/[a-z]/.test(password)) ? '✓ At least 1 lowercase letter (a-z)' : '✗ At least 1 lowercase letter (a-z)';
            document.getElementById('req-lowercase').style.color = (/[a-z]/.test(password)) ? '#28a745' : '#dc3545';
            
            document.getElementById('req-number').innerHTML = (/[0-9]/.test(password)) ? '✓ At least 1 number (0-9)' : '✗ At least 1 number (0-9)';
            document.getElementById('req-number').style.color = (/[0-9]/.test(password)) ? '#28a745' : '#dc3545';
            
            document.getElementById('req-special').innerHTML = (/[!@#$%^&*(),.?":{}|<>]/.test(password)) ? '✓ At least 1 special character (!@#$%^&*)' : '✗ At least 1 special character (!@#$%^&*)';
            document.getElementById('req-special').style.color = (/[!@#$%^&*(),.?":{}|<>]/.test(password)) ? '#28a745' : '#dc3545';
            
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            if (password.length === 0) {
                strengthBar.className = 'strength-bar';
                strengthBar.style.width = '0%';
                strengthText.innerHTML = 'Enter a password';
                strengthText.className = 'strength-text';
                return 0;
            }
            
            if (strength <= 2) {
                strengthBar.className = 'strength-bar strength-weak';
                strengthText.innerHTML = 'Weak Password - Add more characters, numbers, and special characters';
                strengthText.className = 'strength-text text-weak';
            } else if (strength === 3) {
                strengthBar.className = 'strength-bar strength-fair';
                strengthText.innerHTML = 'Fair Password - Add uppercase and special characters for better security';
                strengthText.className = 'strength-text text-fair';
            } else if (strength === 4) {
                strengthBar.className = 'strength-bar strength-good';
                strengthText.innerHTML = 'Good Password - Add special characters for maximum security';
                strengthText.className = 'strength-text text-good';
            } else {
                strengthBar.className = 'strength-bar strength-strong';
                strengthText.innerHTML = 'Strong Password - Excellent security!';
                strengthText.className = 'strength-text text-strong';
            }
            
            return strength;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchMessage = document.getElementById('matchMessage');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchMessage.innerHTML = '✓ Passwords match!';
                    matchMessage.style.color = '#28a745';
                } else {
                    matchMessage.innerHTML = '✗ Passwords do not match!';
                    matchMessage.style.color = '#dc3545';
                }
            } else {
                matchMessage.innerHTML = '';
            }
        }
        
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            checkPasswordMatch();
        });
        
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const strength = checkPasswordStrength(password);
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (strength < 3) {
                e.preventDefault();
                alert('Please choose a stronger password. Your password should be at least Fair strength.');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>