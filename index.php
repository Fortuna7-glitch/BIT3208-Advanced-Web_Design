<?php
// index.php - COMPLETE: Clean Landing Page (No extra logo, no duplicate headers)
require_once 'config/database.php';
include 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salon Pro - Enterprise Salon Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ============================================
           LANDING PAGE STYLES
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        .landing-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #0a0a0a;
            position: relative;
            overflow: hidden;
        }

        /* Subtle Background Glow */
        .landing-page::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 50% 80%, rgba(212, 175, 55, 0.06) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* ============================================
           HERO SECTION
           ============================================ */
        .hero-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem 5% 4rem;
            position: relative;
            z-index: 1;
            min-height: 70vh;
        }

        .hero-content {
            max-width: 750px;
        }

        .hero-content .welcome-text {
            font-size: 0.9rem;
            color: #d4af37;
            text-transform: uppercase;
            letter-spacing: 4px;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .hero-content h1 {
            font-size: 4rem;
            font-family: 'Playfair Display', serif;
            color: white;
            line-height: 1.1;
            margin-bottom: 0.5rem;
        }

        .hero-content h1 span {
            color: #d4af37;
        }

        .hero-content .sub-headline {
            font-size: 1.2rem;
            color: #ccc;
            max-width: 550px;
            margin: 1rem auto 2.5rem;
            font-weight: 300;
            line-height: 1.8;
        }

        .hero-content .sub-headline strong {
            color: #d4af37;
            font-weight: 500;
        }

        /* ============================================
           CTA BUTTONS
           ============================================ */
        .cta-group {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-cta {
            display: inline-block;
            padding: 14px 40px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.3s;
            min-width: 180px;
        }

        .btn-cta.primary {
            background: #d4af37;
            color: #050505;
        }

        .btn-cta.primary:hover {
            background: #f9e547;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        }

        .btn-cta.secondary {
            background: transparent;
            color: #d4af37;
            border: 2px solid #d4af37;
        }

        .btn-cta.secondary:hover {
            background: rgba(212, 175, 55, 0.1);
            transform: translateY(-3px);
        }

        .btn-cta .icon {
            margin-right: 0.5rem;
        }

        /* ============================================
           FOOTER
           ============================================ */
        .landing-footer {
            text-align: center;
            padding: 1.5rem 5%;
            border-top: 1px solid rgba(212, 175, 55, 0.1);
            position: relative;
            z-index: 1;
        }

        .landing-footer p {
            color: #555;
            font-size: 0.8rem;
        }
        .landing-footer p .gold {
            color: #d4af37;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 1024px) {
            .hero-content h1 { font-size: 3.5rem; }
        }

        @media (max-width: 768px) {
            .hero-section { padding: 2rem 4% 3rem; min-height: 60vh; }
            .hero-content h1 { font-size: 2.5rem; }
            .hero-content .sub-headline { font-size: 1rem; }

            .cta-group { flex-direction: column; align-items: center; }
            .btn-cta { width: 100%; max-width: 280px; text-align: center; }
        }

        @media (max-width: 480px) {
            .hero-content h1 { font-size: 2rem; }
            .landing-footer p { font-size: 0.7rem; }
        }
    </style>
</head>
<body>

    <div class="landing-page">

        <!-- ============================================
           HERO SECTION
           ============================================ -->
        <section class="hero-section">
            <div class="hero-content">
                <!-- WELCOME TEXT - NO LOGO IMAGE HERE -->
                <p class="welcome-text">Welcome to</p>
                <h1><span>SALON</span> PRO</h1>
                <p class="sub-headline">
                    Enterprise Salon Management System.<br>
                    Manage multiple salons, staff, and bookings from <strong>one powerful platform</strong>.
                </p>

                <div class="cta-group">
                    <a href="auth/register.php" class="btn-cta primary">
                        <i class="fas fa-rocket icon"></i> Get Started
                    </a>
                    <a href="auth/login.php" class="btn-cta secondary">
                        <i class="fas fa-lock icon"></i> Log In
                    </a>
                </div>
            </div>
        </section>

        <!-- ============================================
           FOOTER
           ============================================ -->
        <footer class="landing-footer">
            <p>&copy; <?php echo date('Y'); ?> <span class="gold">Salon Pro</span>. All rights reserved. ✨</p>
        </footer>

    </div>

    <script>
        // ============================================
        // SMOOTH SCROLL & INTERACTIONS
        // ============================================
        document.querySelectorAll('.btn-cta').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            });
        });
    </script>

</body>
</html>