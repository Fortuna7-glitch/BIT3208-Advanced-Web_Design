<?php
// index.php - RESPONSIVE REWRITE
require_once 'config/database.php';
include 'includes/header.php';

// Get platform statistics
$stats = [];

$salons_query = "SELECT COUNT(*) as count FROM salons WHERE subscription_status = 'active'";
$salons_result = mysqli_query($conn, $salons_query);
$stats['total_salons'] = mysqli_fetch_assoc($salons_result)['count'] ?? 0;

$staff_query = "SELECT COUNT(*) as count FROM users WHERE role = 'staff'";
$staff_result = mysqli_query($conn, $staff_query);
$stats['total_staff'] = mysqli_fetch_assoc($staff_result)['count'] ?? 0;

$services_query = "SELECT COUNT(*) as count FROM services WHERE is_active = 1";
$services_result = mysqli_query($conn, $services_query);
$stats['total_services'] = mysqli_fetch_assoc($services_result)['count'] ?? 0;

$stats['avg_rating'] = '4.8';

$featured_query = "SELECT * FROM salons WHERE subscription_status = 'active' ORDER BY id DESC LIMIT 6";
$featured_salons = mysqli_query($conn, $featured_query);
?>

<style>
    /* Reset & Base */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background: #0a0a0a; color: #ffffff; line-height: 1.6; }
    
    /* ============================================
       HERO SECTION
       ============================================ */
    .hero {
        background: linear-gradient(135deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.7) 100%), url('https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=1600') center/cover;
        padding: 5rem 5%;
        text-align: center;
        min-height: 60vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .hero h1 {
        font-size: 3.5rem;
        font-family: 'Playfair Display', serif;
        margin-bottom: 1rem;
        line-height: 1.2;
    }
    .hero h1 span { color: #d4af37; }
    .hero p {
        font-size: 1.2rem;
        color: #ccc;
        max-width: 600px;
        margin: 0 auto 2rem auto;
    }
    .cta-buttons {
        display: flex;
        justify-content: center;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    .cta-btn {
        display: inline-block;
        padding: 14px 35px;
        border-radius: 50px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
    }
    .cta-btn-primary {
        background: #d4af37;
        color: #050505;
    }
    .cta-btn-primary:hover {
        background: #f9e547;
        transform: translateY(-3px);
    }
    .cta-btn-secondary {
        border: 2px solid #d4af37;
        color: #d4af37;
        background: transparent;
    }
    .cta-btn-secondary:hover {
        background: #d4af37;
        color: #050505;
        transform: translateY(-3px);
    }

    /* ============================================
       STATS SECTION
       ============================================ */
    .stats-section {
        padding: 3rem 5%;
        background: #1a1a1a;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 2rem;
        max-width: 1000px;
        margin: 0 auto;
        text-align: center;
    }
    .stat-box {
        background: #0a0a0a;
        padding: 1.5rem;
        border-radius: 15px;
        border-left: 3px solid #d4af37;
    }
    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: #d4af37;
    }
    .stat-label { color: #aaa; margin-top: 0.5rem; }

    /* ============================================
       FEATURED SALONS
       ============================================ */
    .featured-section {
        padding: 4rem 5%;
    }
    .section-title {
        text-align: center;
        font-size: 2.2rem;
        font-family: 'Playfair Display', serif;
        margin-bottom: 2rem;
    }
    .section-title span { color: #d4af37; }
    .salons-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
        max-width: 1200px;
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
    }
    .salon-name {
        font-size: 1.2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    .salon-rating { color: #d4af37; margin-bottom: 0.5rem; }
    .salon-location { color: #aaa; margin-bottom: 1rem; font-size: 0.9rem; }
    .salon-contact { font-size: 0.8rem; color: #888; margin-bottom: 1rem; }
    .btn-view {
        display: inline-block;
        padding: 8px 20px;
        border: 1px solid #d4af37;
        color: #d4af37;
        text-decoration: none;
        border-radius: 25px;
        transition: all 0.3s;
    }
    .btn-view:hover {
        background: #d4af37;
        color: #050505;
    }
    .browse-link {
        text-align: center;
        margin-top: 2rem;
    }
    .browse-link a {
        color: #d4af37;
        text-decoration: none;
        font-weight: 500;
    }
    .browse-link a:hover { text-decoration: underline; }

    /* ============================================
       HOW IT WORKS
       ============================================ */
    .how-it-works {
        background: #1a1a1a;
        padding: 4rem 5%;
    }
    .steps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
        max-width: 900px;
        margin: 0 auto;
        text-align: center;
    }
    .step {
        background: #0a0a0a;
        padding: 1.5rem;
        border-radius: 15px;
    }
    .step-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: #d4af37;
        margin-bottom: 0.5rem;
    }

    /* ============================================
       FEATURES
       ============================================ */
    .features-section {
        padding: 4rem 5%;
    }
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
        max-width: 1000px;
        margin: 0 auto;
    }
    .feature {
        text-align: center;
        padding: 1rem;
    }
    .feature-icon { font-size: 2rem; margin-bottom: 0.5rem; }

    /* ============================================
       TESTIMONIALS
       ============================================ */
    .testimonials-section {
        background: #1a1a1a;
        padding: 4rem 5%;
    }
    .testimonials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        max-width: 1000px;
        margin: 0 auto;
    }
    .testimonial {
        background: #0a0a0a;
        padding: 1.5rem;
        border-radius: 15px;
        text-align: center;
        border-left: 3px solid #d4af37;
    }
    .testimonial-text {
        font-style: italic;
        margin-bottom: 1rem;
        color: #ccc;
    }
    .testimonial-author { color: #d4af37; font-weight: bold; }

    /* ============================================
       RESPONSIVE BREAKPOINTS
       ============================================ */
    @media (max-width: 1024px) {
        .hero h1 { font-size: 2.8rem; }
        .salons-grid { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    }

    @media (max-width: 768px) {
        .hero { padding: 3rem 5%; min-height: 50vh; }
        .hero h1 { font-size: 2.2rem; }
        .hero p { font-size: 1rem; }
        .cta-buttons { flex-direction: column; align-items: center; }
        .cta-btn { width: 100%; max-width: 280px; text-align: center; }

        .stats-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-number { font-size: 2rem; }

        .featured-section { padding: 3rem 4%; }
        .salons-grid { grid-template-columns: 1fr; max-width: 400px; margin: 0 auto; }

        .section-title { font-size: 1.8rem; }
        .how-it-works { padding: 3rem 4%; }
        .steps-grid { grid-template-columns: 1fr; }
        .features-section { padding: 3rem 4%; }
        .features-grid { grid-template-columns: 1fr 1fr; }
        .testimonials-section { padding: 3rem 4%; }
        .testimonials-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 480px) {
        .hero { padding: 2rem 4%; min-height: 40vh; }
        .hero h1 { font-size: 1.8rem; }
        .cta-btn { padding: 12px 25px; font-size: 0.9rem; }

        .stats-grid { grid-template-columns: 1fr; }
        .stat-box { padding: 1rem; }
        .stat-number { font-size: 1.8rem; }

        .section-title { font-size: 1.5rem; }
        .features-grid { grid-template-columns: 1fr; }
        .salon-card { padding: 1rem; }
    }
</style>

<!-- HERO -->
<section class="hero">
    <div>
        <h1>Welcome to <span>SALON PRO</span></h1>
        <p>Enterprise Salon Management System. Manage multiple salons, staff, and bookings from one powerful platform.</p>
        <div class="cta-buttons">
            <a href="auth/login.php" class="cta-btn cta-btn-primary">👑 For Salon Owners</a>
            <a href="find_salons.php" class="cta-btn cta-btn-secondary">👤 Find a Salon</a>
        </div>
    </div>
</section>

<!-- STATS -->
<section class="stats-section">
    <div class="stats-grid">
        <div class="stat-box"><div class="stat-number"><?php echo $stats['total_salons']; ?>+</div><div class="stat-label">🏢 Salons</div></div>
        <div class="stat-box"><div class="stat-number"><?php echo $stats['total_staff']; ?>+</div><div class="stat-label">👥 Stylists</div></div>
        <div class="stat-box"><div class="stat-number"><?php echo $stats['total_services']; ?>+</div><div class="stat-label">💇 Services</div></div>
        <div class="stat-box"><div class="stat-number">⭐ <?php echo $stats['avg_rating']; ?></div><div class="stat-label">Customer Rating</div></div>
    </div>
</section>

<!-- FEATURED SALONS -->
<section class="featured-section">
    <h2 class="section-title">Featured <span>Salons</span></h2>
    <div class="salons-grid">
        <?php if(mysqli_num_rows($featured_salons) > 0): ?>
            <?php while($salon = mysqli_fetch_assoc($featured_salons)): ?>
            <div class="salon-card">
                <div class="salon-name">🏢 <?php echo htmlspecialchars($salon['salon_name']); ?></div>
                <div class="salon-rating">⭐⭐⭐⭐⭐</div>
                <div class="salon-location">📍 <?php echo htmlspecialchars($salon['salon_address'] ?? 'Nairobi'); ?></div>
                <div class="salon-contact">📞 <?php echo htmlspecialchars($salon['salon_phone']); ?></div>
                <a href="salon.php?id=<?php echo $salon['id']; ?>" class="btn-view">View Salon →</a>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="salon-card"><div class="salon-name">No salons available yet</div><div class="salon-location">Check back soon</div></div>
        <?php endif; ?>
    </div>
    <div class="browse-link"><a href="find_salons.php">Browse All Salons →</a></div>
</section>

<!-- HOW IT WORKS -->
<section class="how-it-works">
    <h2 class="section-title">How It <span>Works</span></h2>
    <div class="steps-grid">
        <div class="step"><div class="step-number">1</div><div>Choose a Salon</div><div style="color:#aaa; font-size:0.85rem;">Browse our directory</div></div>
        <div class="step"><div class="step-number">2</div><div>Select a Service</div><div style="color:#aaa; font-size:0.85rem;">Choose from our range</div></div>
        <div class="step"><div class="step-number">3</div><div>Confirm Booking</div><div style="color:#aaa; font-size:0.85rem;">Pick date, time & payment</div></div>
    </div>
</section>

<!-- FEATURES -->
<section class="features-section">
    <h2 class="section-title">Why Choose <span>Salon Pro</span></h2>
    <div class="features-grid">
        <div class="feature"><div class="feature-icon">🏢</div><div>Multi-tenant Support</div></div>
        <div class="feature"><div class="feature-icon">📅</div><div>24/7 Online Booking</div></div>
        <div class="feature"><div class="feature-icon">👥</div><div>Staff Management</div></div>
        <div class="feature"><div class="feature-icon">💰</div><div>Payment Processing</div></div>
        <div class="feature"><div class="feature-icon">📊</div><div>Real-time Reports</div></div>
        <div class="feature"><div class="feature-icon">🔔</div><div>SMS/Email Alerts</div></div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials-section">
    <h2 class="section-title">What Our <span>Clients Say</span></h2>
    <div class="testimonials-grid">
        <div class="testimonial"><div class="testimonial-text">"Salon Pro transformed my business! I can now manage all my branches from one dashboard."</div><div class="testimonial-author">- Sarah M., Salon Owner</div></div>
        <div class="testimonial"><div class="testimonial-text">"Finding and booking appointments has never been easier."</div><div class="testimonial-author">- James K., Regular Customer</div></div>
        <div class="testimonial"><div class="testimonial-text">"The staff management and reporting features have helped me grow my salon business."</div><div class="testimonial-author">- Mary W., Salon Owner</div></div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>