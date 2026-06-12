<?php
// services.php - Public services page
require_once 'config/database.php';
include 'includes/header.php';

$services = mysqli_query($conn, "SELECT * FROM services WHERE is_active = 1");
?>

<style>
    .services-hero {
        background: linear-gradient(135deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.6) 100%), url('https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=1600') center/cover;
        padding: 4rem;
        text-align: center;
    }
</style>

<section class="services-hero">
    <h1>Our Premium Services ✨</h1>
    <p>Choose from our wide range of luxury beauty services</p>
</section>

<section style="padding: 4rem 5%;">
    <div class="services-grid">
        <?php while($service = mysqli_fetch_assoc($services)): ?>
        <div class="service-card">
            <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
            <p><?php echo htmlspecialchars($service['description']); ?></p>
            <p class="price">KSh <?php echo number_format($service['price'], 2); ?></p>
            <small>⏱️ <?php echo $service['duration_minutes']; ?> minutes</small>
            <br><br>
            <a href="customer/book.php?service=<?php echo $service['id']; ?>" class="btn btn-primary">Book Now</a>
        </div>
        <?php endwhile; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>