<?php
// test_hash.php - Run this ONCE to diagnose
echo "<h1>Password Hash Diagnosis</h1>";

// Test 1: Can PHP generate a hash?
$test_password = "admin123";
$generated_hash = password_hash($test_password, PASSWORD_DEFAULT);

echo "<p><strong>Test 1:</strong> PHP can generate hashes: ✅ YES</p>";
echo "<p>Password: <code>admin123</code></p>";
echo "<p>Generated Hash: <code>" . $generated_hash . "</code></p>";

// Test 2: Can PHP verify the hash?
$verification = password_verify($test_password, $generated_hash);
echo "<p><strong>Test 2:</strong> Hash verification: " . ($verification ? "✅ PASS" : "❌ FAIL") . "</p>";

// Test 3: Check your existing database hash
$existing_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$test_existing = password_verify($test_password, $existing_hash);
echo "<p><strong>Test 3:</strong> Testing existing hash '$existing_hash'</p>";
echo "<p>Result: " . ($test_existing ? "✅ Works - password is 'admin123'" : "❌ FAIL - hash doesn't work on this server") . "</p>";
?>