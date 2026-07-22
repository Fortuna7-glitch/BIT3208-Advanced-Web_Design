<?php
// generate_hashes.php - Generate correct password hashes for your system

echo "<!DOCTYPE html>
<html>
<head>
    <title>Generate Password Hashes</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #0a0a0a; color: #f5f0e1; padding: 20px; }
        .container { max-width: 700px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 12px; border: 1px solid #d4af37; }
        h1 { color: #d4af37; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #d4af37; color: #050505; padding: 8px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #333; font-size: 12px; word-break: break-all; }
        .role { font-weight: bold; }
        .super-admin { color: #d4af37; }
        .admin { color: #17a2b8; }
        .staff { color: #28a745; }
        .customer { color: #6f42c1; }
        .copy-btn { background: #d4af37; color: #050505; border: none; padding: 4px 12px; border-radius: 4px; cursor: pointer; }
        .copy-btn:hover { background: #f0d878; }
        .note { color: #7a7568; font-size: 14px; margin-top: 20px; border-top: 1px solid #333; padding-top: 20px; }
    </style>
</head>
<body>
    <div class=\"container\">
        <h1>🔐 Password Hash Generator</h1>
        <p>These are the <strong>real</strong> password hashes for your system. Copy them into phpMyAdmin.</p>";

// Define the passwords
$passwords = [
    'super123' => 'Super Admin',
    'owner123' => 'Admin',
    'staff123' => 'Staff',
    'admin123' => 'Customer'
];

echo "<table>
        <tr>
            <th>Role</th>
            <th>Password</th>
            <th>Hash (Copy This)</th>
            <th>Action</th>
        </tr>";

foreach ($passwords as $password => $role) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $role_class = strtolower(str_replace(' ', '-', $role));
    echo "<tr>
            <td class=\"role $role_class\">$role</td>
            <td><code>$password</code></td>
            <td><code id=\"hash_$role\">$hash</code></td>
            <td><button class=\"copy-btn\" onclick=\"copyHash('hash_$role')\">📋 Copy</button></td>
        </tr>";
}

echo "</table>";

// Also show the SQL INSERT statements
echo "<h2>📝 SQL Insert Statements</h2>
<p style='color: #7a7568;'>Copy these SQL statements directly into phpMyAdmin.</p>";

// Get the hashes for the SQL
$super_hash = password_hash('super123', PASSWORD_DEFAULT);
$owner_hash = password_hash('owner123', PASSWORD_DEFAULT);
$staff_hash = password_hash('staff123', PASSWORD_DEFAULT);
$customer_hash = password_hash('admin123', PASSWORD_DEFAULT);

echo "<pre style='background: #0a0a0a; padding: 15px; border-radius: 8px; border: 1px solid #333; overflow-x: auto; font-size: 12px; color: #b8b2a0;'>
-- Run this to update existing users with correct passwords

UPDATE users SET password = '$super_hash' WHERE email = 'fortuna@salonpro.com';
UPDATE users SET password = '$owner_hash' WHERE email = 'admin@salonpro.com';
UPDATE users SET password = '$staff_hash' WHERE email = 'jane@salonpro.com';
UPDATE users SET password = '$staff_hash' WHERE email = 'mary@salonpro.com';
UPDATE users SET password = '$customer_hash' WHERE email = 'customer@salonpro.com';

-- Or if you need to insert new users:
-- INSERT INTO users (full_name, email, phone, password, role, is_active) VALUES
-- ('Super Admin', 'fortuna@salonpro.com', '0712345678', '$super_hash', 'super_admin', 1),
-- ('Admin User', 'admin@salonpro.com', '0712345678', '$owner_hash', 'admin', 1),
-- ('Jane Smith', 'jane@salonpro.com', '0723456789', '$staff_hash', 'staff', 1),
-- ('Mary Johnson', 'mary@salonpro.com', '0734567890', '$staff_hash', 'staff', 1),
-- ('Customer User', 'customer@salonpro.com', '0712345678', '$customer_hash', 'customer', 1);
</pre>";

echo "<div class='note'>
        <p><strong>⚠️ Important:</strong></p>
        <p>1. Run the UPDATE statements to fix existing users.</p>
        <p>2. The hashes are unique each time you generate them, but they will all work!</p>
        <p>3. If a user doesn't exist, use the INSERT statement instead.</p>
      </div>";

echo "    </div>
    <script>
        function copyHash(elementId) {
            const el = document.getElementById(elementId);
            const text = el.textContent;
            navigator.clipboard.writeText(text).then(() => {
                const btn = el.parentElement.nextElementSibling.querySelector('.copy-btn');
                const originalText = btn.textContent;
                btn.textContent = '✅ Copied!';
                setTimeout(() => { btn.textContent = originalText; }, 2000);
            });
        }
    </script>
</body>
</html>";