<?php
/**
 * super_admin/api/search.php - Search API Endpoint
 * Handles search requests, returns JSON results
 * Categories: all, salons, owners, staff
 */

require_once '../../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : 'search';

header('Content-Type: application/json');

// ============================================
// ACTION: GET RECENT SEARCHES
// ============================================
if ($action === 'recent') {
    $result = getRecentSearches($user_id, 5);
    echo json_encode(['success' => true, 'searches' => $result]);
    exit();
}

// ============================================
// ACTION: REMOVE RECENT SEARCH
// ============================================
if ($action === 'remove_recent') {
    $search_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($search_id > 0) {
        $result = deleteRecentSearch($user_id, $search_id);
        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid search ID']);
    }
    exit();
}

// ============================================
// ACTION: CLEAR RECENT SEARCHES
// ============================================
if ($action === 'clear_recent') {
    $result = clearRecentSearches($user_id);
    echo json_encode(['success' => $result]);
    exit();
}

// ============================================
// ACTION: SEARCH
// ============================================
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => [], 'total' => 0]);
    exit();
}

// Save search to history
saveRecentSearch($user_id, $query, $category);

// ============================================
// SEARCH QUERIES
// ============================================
$results = [];
$total = 0;

// Escape the query for SQL
$search_term = '%' . mysqli_real_escape_string($conn, $query) . '%';

// ============================================
// SEARCH SALONS
// ============================================
if ($category === 'all' || $category === 'salons') {
    $sql = "SELECT 
                id, 
                salon_name as name, 
                'salon' as type,
                salon_email as email,
                salon_phone as phone,
                subscription_status as status,
                created_at
            FROM salons 
            WHERE salon_name LIKE '$search_term' 
               OR salon_email LIKE '$search_term' 
               OR salon_phone LIKE '$search_term' 
            ORDER BY salon_name ASC";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['url'] = 'salons.php?view=' . $row['id'];
            $row['icon'] = '🏪';
            $row['salon_name'] = '-';
            $results[] = $row;
            $total++;
        }
    }
}

// ============================================
// SEARCH OWNERS (Admins)
// ============================================
if ($category === 'all' || $category === 'owners') {
    $sql = "SELECT 
                u.id, 
                u.full_name as name, 
                'owner' as type,
                u.email,
                u.phone,
                'Active' as status,
                s.salon_name,
                u.created_at
            FROM users u
            LEFT JOIN salons s ON u.salon_id = s.id
            WHERE u.role = 'admin' 
               AND (u.full_name LIKE '$search_term' 
                    OR u.email LIKE '$search_term' 
                    OR u.phone LIKE '$search_term')
            ORDER BY u.full_name ASC";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['url'] = 'admins.php?view=' . $row['id'];
            $row['icon'] = '👤';
            $row['salon_name'] = $row['salon_name'] ?? 'Unassigned';
            $results[] = $row;
            $total++;
        }
    }
}

// ============================================
// SEARCH STAFF
// ============================================
if ($category === 'all' || $category === 'staff') {
    $sql = "SELECT 
                u.id, 
                u.full_name as name, 
                'staff' as type,
                u.email,
                u.phone,
                'Active' as status,
                s.salon_name,
                sd.specialty,
                u.created_at
            FROM users u
            LEFT JOIN salons s ON u.salon_id = s.id
            LEFT JOIN staff_details sd ON u.id = sd.user_id
            WHERE u.role = 'staff' 
               AND (u.full_name LIKE '$search_term' 
                    OR u.email LIKE '$search_term' 
                    OR u.phone LIKE '$search_term'
                    OR sd.specialty LIKE '$search_term')
            ORDER BY u.full_name ASC";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['url'] = 'staff.php?view=' . $row['id'];
            $row['icon'] = '👥';
            $row['salon_name'] = $row['salon_name'] ?? 'Unassigned';
            if (!empty($row['specialty'])) {
                $row['name'] .= ' (' . $row['specialty'] . ')';
            }
            $results[] = $row;
            $total++;
        }
    }
}

// ============================================
// RETURN RESULTS
// ============================================
echo json_encode([
    'success' => true,
    'results' => $results,
    'total' => $total,
    'category' => $category,
    'query' => $query
]);
?>