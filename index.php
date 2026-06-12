<?php
// index.php - FIXED VERSION
require_once 'config/database.php';
include 'includes/header.php';

// Get settings
$settings_query = "SELECT setting_key, setting_value FROM salon_settings";
$settings_result = mysqli_query($conn, $settings_query);
$settings = [];
if ($settings_result) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Get featured services
$services_query = "SELECT * FROM services WHERE is_active = 1 LIMIT 6";
$services = mysqli_query($conn, $services_query);

// Get staff
$staff_query = "SELECT u.*, sd.* FROM users u JOIN staff_details sd ON u.id = sd.user_id WHERE u.role = 'staff'";
$staff = mysqli_query($conn, $staff_query);
?>

<style>
    .hero {
        background: linear-gradient(135deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.7) 100%), url('https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=1600') center/cover;
    }
    .testimonial {
        background: var(--gray);
        border-radius: 15px;
        padding: 2rem;
        text-align: center;
        margin: 1rem;
    }
    .contact-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }
    .price-list {
        max-width: 600px;
        margin: 0 auto;
    }
    .price-item {
        display: flex;
        justify-content: space-between;
        padding: 0.8rem;
        border-bottom: 1px dashed rgba(212, 175, 55, 0.3);
    }
</style>

<section class="hero">
    <div class="hero-content">
        <h1>Welcome to <span>SALON PRO</span></h1>
        <p><?php echo htmlspecialchars($settings['salon_slogan'] ?? 'Where Beauty Meets Excellence'); ?></p>
        <a href="customer/book.php" class="btn btn-primary">✨ Book Appointment ✨</a>
    </div>
</section>

<section class="section">
    <h2 class="section-title">Our <span>Services</span></h2>
    <div class="services-grid">
        <?php if($services && mysqli_num_rows($services) > 0): ?>
            <?php while($service = mysqli_fetch_assoc($services)): ?>
            <div class="service-card">
                <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                <p><?php echo htmlspecialchars($service['description']); ?></p>
                <p class="price">KSh <?php echo number_format($service['price'], 2); ?></p>
                <small>⏱️ <?php echo $service['duration_minutes']; ?> mins</small><br><br>
                <a href="customer/book.php?service=<?php echo $service['id']; ?>" class="btn btn-outline">Book Now</a>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No services available yet. Please check back later.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section" style="background: var(--gray);">
    <h2 class="section-title">Our <span>Stylists</span></h2>
    <div class="staff-grid">
        <?php if($staff && mysqli_num_rows($staff) > 0): ?>
            <?php while($staff_member = mysqli_fetch_assoc($staff)): ?>
            <div class="staff-card">
                <h3><?php echo htmlspecialchars($staff_member['full_name']); ?></h3>
                <p>✨ <?php echo htmlspecialchars($staff_member['specialty']); ?></p>
                <p><?php echo $staff_member['experience_years']; ?>+ years experience</p>
                <p><small><?php echo htmlspecialchars($staff_member['bio']); ?></small></p>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No staff members listed yet.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <h2 class="section-title">Price <span>List</span></h2>
    <div class="price-list">
        <?php 
        $all_services = mysqli_query($conn, "SELECT * FROM services WHERE is_active = 1");
        if($all_services && mysqli_num_rows($all_services) > 0):
            while($s = mysqli_fetch_assoc($all_services)): 
        ?>
        <div class="price-item">
            <span><?php echo htmlspecialchars($s['service_name']); ?></span>
            <span class="price">KSh <?php echo number_format($s['price'], 2); ?></span>
        </div>
        <?php 
            endwhile;
        else:
        ?>
        <p class="price-item">No services available</p>
        <?php endif; ?>
    </div>
</section>

<section class="section" style="background: var(--gray);">
    <h2 class="section-title">Client <span>Testimonials</span></h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
        <div class="testimonial">
            <div>⭐⭐⭐⭐⭐</div>
            <p>"Best salon in town! The team is professional and the service is top-notch."</p>
            <h4>- Sarah M.</h4>
        </div>
        <div class="testimonial">
            <div>⭐⭐⭐⭐⭐</div>
            <p>"Luxury experience at affordable prices. I love my new hairstyle!"</p>
            <h4>- James K.</h4>
        </div>
        <div class="testimonial">
            <div>⭐⭐⭐⭐⭐</div>
            <p>"The booking system is so easy to use. Highly recommend!"</p>
            <h4>- Mary W.</h4>
        </div>
    </div>
</section>

<section class="section">
    <h2 class="section-title">Contact & <span>Location</span></h2>
    <div class="contact-info">
        <div>
            <h3>📍 Address</h3>
            <p><?php echo htmlspecialchars($settings['salon_address'] ?? '123 Luxury Mall, Nairobi'); ?></p>
        </div>
        <div>
            <h3>📞 Phone</h3>
            <p><?php echo htmlspecialchars($settings['salon_phone'] ?? '+254 712 345 678'); ?></p>
        </div>
        <div>
            <h3>✉️ Email</h3>
            <p><?php echo htmlspecialchars($settings['salon_email'] ?? 'info@salonpro.com'); ?></p>
        </div>
        <div>
            <h3>⏰ Hours</h3>
            <p>Mon-Sat: 9AM - 8PM<br>Sun: 10AM - 5PM</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>