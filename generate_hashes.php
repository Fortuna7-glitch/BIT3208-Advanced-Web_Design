<?php
// generate_hashes.php - Run this ONCE, then save the output
$passwords = ['super123', 'owner123', 'admin123', 'staff123']; // Add any other default passwords you need

echo "<h1>Generated Password Hashes for YOUR Server</h1>";
echo "<pre>";
foreach ($passwords as $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "Password: '$password' → Hash: '$hash'\n";
    echo "Copy this hash for use in SQL\n\n";
}
echo "</pre>";
echo "<p><strong>IMPORTANT:</strong> Copy these hashes and use them in your SQL UPDATE statements.</p>";
?>