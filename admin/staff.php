<?php
// admin/staff.php
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Handle add staff
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $specialty = mysqli_real_escape_string($conn, $_POST['specialty']);
    $experience = mysqli_real_escape_string($conn, $_POST['experience']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);
    $password = password_hash('staff123', PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users (full_name, email, phone, password, role) VALUES ('$full_name', '$email', '$phone', '$password', 'staff')";
    if (mysqli_query($conn, $query)) {
        $user_id = mysqli_insert_id($conn);
        $detail_query = "INSERT INTO staff_details (user_id, specialty, experience_years, bio) VALUES ($user_id, '$specialty', '$experience', '$bio')";
        mysqli_query($conn, $detail_query);
    }
}

// Handle delete staff
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    mysqli_query($conn, "DELETE FROM staff_details WHERE user_id = $id");
    mysqli_query($conn, "DELETE FROM users WHERE id = $id AND role = 'staff'");
    redirect('staff.php');
}

$staff = mysqli_query($conn, "SELECT u.*, sd.* FROM users u JOIN staff_details sd ON u.id = sd.user_id WHERE u.role = 'staff'");

include '../includes/header.php';
?>

<div class="dashboard-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php">💇 Services</a></li>
            <li><a href="staff.php" class="active">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <li><a href="payments.php">💰 Payments</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Staff Management 👥</h1>
        
        <!-- Add Staff Form -->
        <div style="background: var(--gray); border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="color: var(--gold);">➕ Add New Staff Member</h3>
            <form method="POST">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <input type="text" name="full_name" placeholder="Full Name" class="form-control" required>
                    <input type="email" name="email" placeholder="Email" class="form-control" required>
                    <input type="tel" name="phone" placeholder="Phone Number" class="form-control" required>
                    <input type="text" name="specialty" placeholder="Specialty (e.g., Hair Stylist)" class="form-control" required>
                    <input type="number" name="experience" placeholder="Years of Experience" class="form-control" required>
                    <textarea name="bio" placeholder="Bio / Description" class="form-control" rows="2"></textarea>
                    <button type="submit" name="add_staff" class="btn btn-primary">Add Staff</button>
                </div>
            </form>
            <p style="margin-top: 1rem; font-size: 0.8rem;">Default password for new staff: <strong>staff123</strong></p>
        </div>
        
        <!-- Staff List -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Specialty</th>
                        <th>Experience</th>
                        <th>Bio</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($staff_member = mysqli_fetch_assoc($staff)): ?>
                    <tr>
                        <td><?php echo $staff_member['id']; ?></td>
                        <td><?php echo htmlspecialchars($staff_member['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($staff_member['email']); ?></td>
                        <td><?php echo htmlspecialchars($staff_member['phone']); ?></td>
                        <td><?php echo htmlspecialchars($staff_member['specialty']); ?></td>
                        <td><?php echo $staff_member['experience_years']; ?> years</td>
                        <td><?php echo htmlspecialchars(substr($staff_member['bio'], 0, 50)) . '...'; ?></td>
                        <td>
                            <a href="?delete=1&id=<?php echo $staff_member['id']; ?>" class="btn btn-outline" style="padding: 5px 10px; background: #dc3545; color: white;" onclick="return confirm('Remove this staff member?')">Remove</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>