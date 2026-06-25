<?php
// salon.php - RESPONSIVE REWRITE
require_once 'config/database.php';
include 'includes/header.php';

$salon_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($salon_id <= 0) {
    header("Location: find_salons.php");
    exit();
}

$salon_query = "SELECT * FROM salons WHERE id = $salon_id AND subscription_status = 'active'";
$salon_result = mysqli_query($conn, $salon_query);
if (mysqli_num_rows($salon_result) == 0) {
    header("Location: find_salons.php");
    exit();
}
$salon = mysqli_fetch_assoc($salon_result);

$services_query = "SELECT * FROM services WHERE salon_id = $salon_id AND is_active = 1 ORDER BY price ASC";
$services = mysqli_query($conn, $services_query);

$staff_query = "SELECT u.*, sd.specialty, sd.experience_years, sd.bio FROM users u LEFT JOIN staff_details sd ON u.id = sd.user_id WHERE u.role = 'staff' AND u.salon_id = $salon_id AND u.is_active = 1";
$staff = mysqli_query($conn, $staff_query);
?>

<style>
    .salon-hero {
        background: linear-gradient(135deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.7) 100%), url('https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=1600') center/cover;
        padding: 4rem 5%;
        text-align: center;
    }
    .salon-hero h1 {
        font-size: 2.5rem;
        font-family: 'Playfair Display', serif;
        margin-bottom: 0.5rem;
    }
    .salon-hero h1 span { color: #d4af37; }
    .salon-rating { color: #d4af37; font-size: 1.2rem; margin-bottom: 1rem; }
    .salon-contact-info {
        display: flex;
        justify-content: center;
        gap: 2rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }
    .salon-contact-info div {
        background: rgba(0,0,0,0.5);
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.9rem;
    }

    .section-container {
        padding: 3rem 5%;
        max-width: 1200px;
        margin: 0 auto;
    }
    .section-title {
        font-size: 1.8rem;
        font-family: 'Playfair Display', serif;
        margin-bottom: 1.5rem;
        border-left: 4px solid #d4af37;
        padding-left: 1rem;
    }
    .section-title span { color: #d4af37; }

    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .service-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1.2rem;
        transition: all 0.3s;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    .service-card:hover {
        transform: translateY(-3px);
        border-color: #d4af37;
    }
    .service-name { font-size: 1.1rem; font-weight: bold; margin-bottom: 0.3rem; }
    .service-description { font-size: 0.85rem; color: #aaa; margin-bottom: 0.8rem; }
    .service-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .service-price { font-size: 1.3rem; font-weight: bold; color: #d4af37; }
    .service-duration { font-size: 0.75rem; color: #888; }

    .staff-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
    }
    .staff-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1.2rem;
        text-align: center;
        border: 1px solid rgba(212, 175, 55, 0.2);
        transition: all 0.3s;
    }
    .staff-card:hover {
        transform: translateY(-3px);
        border-color: #d4af37;
    }
    .staff-name { font-size: 1.1rem; font-weight: bold; margin-bottom: 0.3rem; }
    .staff-specialty { color: #d4af37; font-size: 0.85rem; margin-bottom: 0.5rem; }
    .staff-experience { font-size: 0.75rem; color: #888; margin-bottom: 0.5rem; }
    .staff-bio { font-size: 0.8rem; color: #aaa; margin-top: 0.5rem; }

    .book-now-section {
        text-align: center;
        padding: 3rem 5%;
        background: #1a1a1a;
    }
    .book-now-btn {
        display: inline-block;
        padding: 15px 40px;
        background: #d4af37;
        color: #050505;
        text-decoration: none;
        border-radius: 50px;
        font-weight: bold;
        font-size: 1.1rem;
        transition: all 0.3s;
    }
    .book-now-btn:hover {
        background: #f9e547;
        transform: scale(1.05);
    }

    @media (max-width: 1024px) {
        .salon-hero h1 { font-size: 2.2rem; }
        .services-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
        .staff-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
    }

    @media (max-width: 768px) {
        .salon-hero { padding: 2.5rem 4%; }
        .salon-hero h1 { font-size: 1.8rem; }
        .salon-contact-info { flex-direction: column; align-items: center; gap: 0.5rem; }
        .salon-contact-info div { font-size: 0.85rem; }

        .section-container { padding: 2rem 4%; }
        .section-title { font-size: 1.4rem; }
        .services-grid { grid-template-columns: 1fr; max-width: 500px; margin: 0 auto; }
        .staff-grid { grid-template-columns: 1fr 1fr; }

        .book-now-section { padding: 2rem 4%; }
        .book-now-btn { padding: 12px 30px; font-size: 1rem; }
    }

    @media (max-width: 480px) {
        .salon-hero { padding: 2rem 4%; }
        .salon-hero h1 { font-size: 1.5rem; }
        .salon-contact-info div { font-size: 0.75rem; padding: 0.4rem 0.8rem; }

        .section-title { font-size: 1.2rem; padding-left: 0.8rem; }
        .staff-grid { grid-template-columns: 1fr; }

        .service-card { padding: 1rem; }
        .service-name { font-size: 1rem; }
        .service-price { font-size: 1.1rem; }

        .book-now-btn { padding: 10px 25px; font-size: 0.9rem; width: 100%; max-width: 300px; }
    }
</style>

<section class="salon-hero">
    <h1><span>🏢</span> <?php echo htmlspecialchars($salon['salon_name']); ?></h1>
    <div class="salon-rating">⭐⭐⭐⭐⭐ 4.8 (120+ reviews)</div>
    <div class="salon-contact-info">
        <div>📍 <?php echo htmlspecialchars($salon['salon_address'] ?? 'Address not specified'); ?></div>
        <div>📞 <?php echo htmlspecialchars($salon['salon_phone']); ?></div>
        <div>✉️ <?php echo htmlspecialchars($salon['salon_email']); ?></div>
    </div>
</section>

<section class="section-container">
    <h2 class="section-title">Our <span>Services</span></h2>
    <div class="services-grid">
        <?php if(mysqli_num_rows($services) > 0): ?>
            <?php while($service = mysqli_fetch_assoc($services)): ?>
            <div class="service-card">
                <div class="service-name">💇 <?php echo htmlspecialchars($service['service_name']); ?></div>
                <div class="service-description"><?php echo htmlspecialchars($service['description'] ?? 'Professional service by our expert stylists.'); ?></div>
                <div class="service-footer">
                    <span class="service-price">KSh <?php echo number_format($service['price'], 2); ?></span>
                    <span class="service-duration">⏱️ <?php echo $service['duration_minutes']; ?> mins</span>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="service-card">
                <div class="service-name">No services available yet</div>
                <div class="service-description">Check back soon for services at this salon.</div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if(mysqli_num_rows($staff) > 0): ?>
<section class="section-container" style="background: #0a0a0a;">
    <h2 class="section-title">Meet Our <span>Stylists</span></h2>
    <div class="staff-grid">
        <?php while($staff_member = mysqli_fetch_assoc($staff)): ?>
        <div class="staff-card">
            <div class="staff-name">👤 <?php echo htmlspecialchars($staff_member['full_name']); ?></div>
            <div class="staff-specialty">✂️ <?php echo htmlspecialchars($staff_member['specialty'] ?? 'Professional Stylist'); ?></div>
            <div class="staff-experience">📅 <?php echo $staff_member['experience_years'] ?? 0; ?>+ years experience</div>
            <div class="staff-bio"><?php echo htmlspecialchars($staff_member['bio'] ?? 'Passionate about making you look and feel your best.'); ?></div>
        </div>
        <?php endwhile; ?>
    </div>
</section>
<?php endif; ?>

<section class="book-now-section">
    <h2 class="section-title" style="border-left: none; text-align: center;">Ready to <span>Book</span>?</h2>
    <p style="margin-bottom: 1.5rem; color: #aaa;">Choose your service and preferred stylist to get started</p>
    <a href="customer/book.php?salon_id=<?php echo $salon_id; ?>" class="book-now-btn">📅 Book Appointment Now →</a>
</section>

<?php include 'includes/footer.php'; ?>