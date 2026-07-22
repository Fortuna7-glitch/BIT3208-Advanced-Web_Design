<?php
// super_admin/search.php - Live search backend for Super Admin dashboard
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$query = isset($_GET['q']) ? mysqli_real_escape_string($conn, trim($_GET['q'])) : '';
$results = [];

if (strlen($query) >= 2) {
    
    // ============================================
    // 1. SEARCH SALONS
    // ============================================
    $salon_query = "SELECT id, salon_name, salon_email, subscription_status, 'Salon' as type 
                    FROM salons 
                    WHERE salon_name LIKE '%$query%' 
                       OR salon_email LIKE '%$query%'
                    LIMIT 5";
    $salon_result = mysqli_query($conn, $salon_query);
    while ($row = mysqli_fetch_assoc($salon_result)) {
        $results[] = [
            'label' => htmlspecialchars($row['salon_name']) . ' (' . htmlspecialchars($row['subscription_status']) . ')',
            'type' => 'Salon',
            'url' => 'salons.php?view=' . $row['id'],
            'icon' => '🏢'
        ];
    }

    // ============================================
    // 2. SEARCH OWNERS (Admins)
    // ============================================
    $owner_query = "SELECT u.id, u.full_name, u.email, u.phone, s.salon_name, 'Owner' as type 
                    FROM users u 
                    LEFT JOIN salons s ON u.salon_id = s.id 
                    WHERE u.role = 'admin' 
                    AND (u.full_name LIKE '%$query%' 
                       OR u.email LIKE '%$query%'
                       OR u.phone LIKE '%$query%')
                    LIMIT 5";
    $owner_result = mysqli_query($conn, $owner_query);
    while ($row = mysqli_fetch_assoc($owner_result)) {
        $results[] = [
            'label' => htmlspecialchars($row['full_name']) . ' (' . htmlspecialchars($row['salon_name'] ?? 'No Salon') . ')',
            'type' => 'Owner',
            'url' => 'admins.php?view=' . $row['id'],
            'icon' => '👨‍💼'
        ];
    }

    // ============================================
    // 3. SEARCH STAFF
    // ============================================
    $staff_query = "SELECT u.id, u.full_name, u.email, u.phone, s.salon_name, 'Staff' as type 
                    FROM users u 
                    LEFT JOIN salons s ON u.salon_id = s.id 
                    WHERE u.role = 'staff' 
                    AND (u.full_name LIKE '%$query%' 
                       OR u.email LIKE '%$query%'
                       OR u.phone LIKE '%$query%')
                    LIMIT 5";
    $staff_result = mysqli_query($conn, $staff_query);
    while ($row = mysqli_fetch_assoc($staff_result)) {
        $results[] = [
            'label' => htmlspecialchars($row['full_name']) . ' (' . htmlspecialchars($row['salon_name'] ?? 'No Salon') . ')',
            'type' => 'Staff',
            'url' => 'salons.php?view=' . $row['salon_id'],
            'icon' => '👥'
        ];
    }

    // ============================================
    // 4. SEARCH CUSTOMERS
    // ============================================
    $customer_query = "SELECT u.id, u.full_name, u.email, u.phone, s.salon_name, 'Customer' as type 
                       FROM users u 
                       LEFT JOIN salons s ON u.salon_id = s.id 
                       WHERE u.role = 'customer' 
                       AND (u.full_name LIKE '%$query%' 
                          OR u.email LIKE '%$query%'
                          OR u.phone LIKE '%$query%')
                       LIMIT 5";
    $customer_result = mysqli_query($conn, $customer_query);
    while ($row = mysqli_fetch_assoc($customer_result)) {
        $results[] = [
            'label' => htmlspecialchars($row['full_name']) . ' (' . htmlspecialchars($row['salon_name'] ?? 'No Salon') . ')',
            'type' => 'Customer',
            'url' => 'salons.php?view=' . $row['salon_id'],
            'icon' => '👤'
        ];
    }

    // ============================================
    // 5. SEARCH SUBSCRIPTIONS
    // ============================================
    $sub_query = "SELECT sh.id, s.salon_name, sh.plan, sh.amount, 'Subscription' as type 
                  FROM subscription_history sh 
                  JOIN salons s ON sh.salon_id = s.id 
                  WHERE s.salon_name LIKE '%$query%' 
                     OR sh.plan LIKE '%$query%'
                  LIMIT 5";
    $sub_result = mysqli_query($conn, $sub_query);
    while ($row = mysqli_fetch_assoc($sub_result)) {
        $results[] = [
            'label' => htmlspecialchars($row['salon_name']) . ' - ' . ucfirst($row['plan']) . ' (KSh ' . number_format($row['amount']) . ')',
            'type' => 'Subscription',
            'url' => 'subscriptions.php?view=' . $row['id'],
            'icon' => '💰'
        ];
    }

    // ============================================
    // 6. SEARCH SERVICES (Optional)
    // ============================================
    $service_query = "SELECT id, service_name, description, price, 'Service' as type 
                      FROM services 
                      WHERE service_name LIKE '%$query%' 
                      OR description LIKE '%$query%'
                      LIMIT 5";
    $service_result = mysqli_query($conn, $service_query);
    while ($row = mysqli_fetch_assoc($service_result)) {
        $results[] = [
            'label' => htmlspecialchars($row['service_name']) . ' (KSh ' . number_format($row['price']) . ')',
            'type' => 'Service',
            'url' => '../admin/services.php?view=' . $row['id'],
            'icon' => '💇'
        ];
    }
}

// ============================================
// LIMIT RESULTS TO PREVENT OVERLOAD
// ============================================
$results = array_slice($results, 0, 15);

// ============================================
// RETURN JSON RESPONSE
// ============================================
header('Content-Type: application/json');
echo json_encode($results);
exit();
?>