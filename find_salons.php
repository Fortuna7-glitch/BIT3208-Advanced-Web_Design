<?php
// find_salons.php - RESPONSIVE REWRITE
require_once 'config/database.php';
include 'includes/header.php';

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$location = isset($_GET['location']) ? mysqli_real_escape_string($conn, $_GET['location']) : '';

$query = "SELECT * FROM salons WHERE subscription_status = 'active'";
if (!empty($search)) {
    $query .= " AND (salon_name LIKE '%$search%' OR salon_email LIKE '%$search%' OR salon_phone LIKE '%$search%')";
}
if (!empty($location)) {
    $query .= " AND salon_address LIKE '%$location%'";
}
$query .= " ORDER BY salon_name ASC";
$salons = mysqli_query($conn, $query);

$locations_query = "SELECT DISTINCT salon_address FROM salons WHERE subscription_status = 'active' AND salon_address IS NOT NULL AND salon_address != '' LIMIT 10";
$locations_result = mysqli_query($conn, $locations_query);
$locations = [];
while ($row = mysqli_fetch_assoc($locations_result)) {
    $locations[] = $row['salon_address'];
}
?>

<style>
    .directory-hero {
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
        padding: 3rem 5%;
        text-align: center;
        border-bottom: 1px solid rgba(212, 175, 55, 0.3);
    }
    .directory-hero h1 {
        font-size: 2.5rem;
        font-family: 'Playfair Display', serif;
        margin-bottom: 0.5rem;
    }
    .directory-hero h1 span { color: #d4af37; }
    .directory-hero p { color: #aaa; margin-bottom: 2rem; }

    .search-container { max-width: 800px; margin: 0 auto; }
    .search-form {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    .search-input {
        flex: 2;
        min-width: 180px;
        padding: 14px 18px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 50px;
        color: white;
        font-size: 1rem;
    }
    .search-input:focus {
        outline: none;
        border-color: #d4af37;
    }
    .location-select {
        flex: 1;
        min-width: 140px;
        padding: 14px 18px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 50px;
        color: white;
        font-size: 1rem;
        cursor: pointer;
    }
    .search-btn {
        padding: 14px 30px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 50px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    .search-btn:hover { background: #f9e547; transform: translateY(-2px); }
    .clear-btn {
        padding: 14px 25px;
        background: transparent;
        border: 1px solid #d4af37;
        color: #d4af37;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s;
    }
    .clear-btn:hover { background: rgba(212, 175, 55, 0.2); }

    .results-summary {
        padding: 1rem 5%;
        color: #aaa;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    .salons-container { padding: 3rem 5%; }
    .salons-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
        max-width: 1400px;
        margin: 0 auto;
    }
    .salon-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        transition: all 0.3s;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }
    .salon-card:hover {
        transform: translateY(-5px);
        border-color: #d4af37;
        box-shadow: 0 10px 30px rgba(212, 175, 55, 0.1);
    }
    .salon-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .salon-name { font-size: 1.2rem; font-weight: bold; }
    .salon-rating { color: #d4af37; font-size: 0.9rem; }
    .salon-address { color: #aaa; margin-bottom: 0.5rem; font-size: 0.9rem; }
    .salon-contact { color: #888; margin-bottom: 0.5rem; font-size: 0.85rem; }
    .salon-divider { height: 1px; background: rgba(212, 175, 55, 0.2); margin: 1rem 0; }
    .salon-stats {
        display: flex; gap: 1rem; margin-bottom: 1rem; font-size: 0.8rem; color: #888; flex-wrap: wrap;
    }
    .btn-view-salon {
        display: inline-block;
        padding: 10px 25px;
        background: transparent;
        border: 1px solid #d4af37;
        color: #d4af37;
        text-decoration: none;
        border-radius: 25px;
        transition: all 0.3s;
        width: 100%;
        text-align: center;
    }
    .btn-view-salon:hover {
        background: #d4af37;
        color: #050505;
    }

    .no-results {
        text-align: center;
        padding: 4rem;
        background: #1a1a1a;
        border-radius: 15px;
        max-width: 500px;
        margin: 0 auto;
    }
    .no-results h3 { color: #d4af37; margin-bottom: 1rem; }
    .no-results p { color: #aaa; margin-bottom: 1.5rem; }

    @media (max-width: 1024px) {
        .salons-grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
    }

    @media (max-width: 768px) {
        .directory-hero { padding: 2rem 4%; }
        .directory-hero h1 { font-size: 2rem; }
        .search-form { flex-direction: column; }
        .search-input, .location-select, .search-btn, .clear-btn { width: 100%; }

        .salons-container { padding: 2rem 4%; }
        .salons-grid { grid-template-columns: 1fr; max-width: 500px; margin: 0 auto; }
    }

    @media (max-width: 480px) {
        .directory-hero h1 { font-size: 1.6rem; }
        .directory-hero p { font-size: 0.9rem; }
        .search-input, .location-select { padding: 12px 14px; font-size: 0.9rem; }
        .search-btn, .clear-btn { padding: 12px 20px; font-size: 0.9rem; }

        .salon-card { padding: 1rem; }
        .salon-name { font-size: 1rem; }
        .salon-stats { font-size: 0.7rem; gap: 0.5rem; }
    }
</style>

<section class="directory-hero">
    <h1>Find Your Perfect <span>Salon</span></h1>
    <p>Discover and book appointments at the best salons near you</p>
    <div class="search-container">
        <form method="GET" action="" class="search-form">
            <input type="text" name="search" class="search-input" placeholder="🔍 Search by salon name..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="location" class="location-select">
                <option value="">📍 All Locations</option>
                <?php foreach($locations as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($location == $loc) ? 'selected' : ''; ?>>
                        📍 <?php echo htmlspecialchars($loc); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="search-btn">🔍 Search</button>
            <?php if(!empty($search) || !empty($location)): ?>
                <a href="find_salons.php" class="clear-btn">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="results-summary">
    <p>
        <?php
        $total = mysqli_num_rows($salons);
        echo "Found <strong style='color: #d4af37;'>$total</strong> salon" . ($total != 1 ? 's' : '');
        if(!empty($search)) echo " matching '<strong>$search</strong>'";
        if(!empty($location)) echo " in '<strong>$location</strong>'";
        ?>
    </p>
</section>

<section class="salons-container">
    <?php if(mysqli_num_rows($salons) > 0): ?>
        <div class="salons-grid">
            <?php while($salon = mysqli_fetch_assoc($salons)):
                $service_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM services WHERE salon_id = {$salon['id']} AND is_active = 1"))['count'] ?? 0;
                $staff_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE salon_id = {$salon['id']} AND role = 'staff'"))['count'] ?? 0;
            ?>
                <div class="salon-card">
                    <div class="salon-header">
                        <div class="salon-name">🏢 <?php echo htmlspecialchars($salon['salon_name']); ?></div>
                        <div class="salon-rating">⭐⭐⭐⭐⭐</div>
                    </div>
                    <div class="salon-address">📍 <?php echo htmlspecialchars($salon['salon_address'] ?? 'Address not specified'); ?></div>
                    <div class="salon-contact">📞 <?php echo htmlspecialchars($salon['salon_phone']); ?></div>
                    <div class="salon-contact">✉️ <?php echo htmlspecialchars($salon['salon_email']); ?></div>
                    <div class="salon-divider"></div>
                    <div class="salon-stats">
                        <span>💇 <?php echo $service_count; ?> services</span>
                        <span>👥 <?php echo $staff_count; ?> stylists</span>
                        <span>⭐ 4.8 rating</span>
                    </div>
                    <a href="salon.php?id=<?php echo $salon['id']; ?>" class="btn-view-salon">View Salon Details →</a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-results">
            <h3>😔 No Salons Found</h3>
            <p>We couldn't find any salons matching your search criteria.</p>
            <a href="find_salons.php" class="btn-view-salon" style="width: auto; display: inline-block;">View All Salons</a>
        </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>