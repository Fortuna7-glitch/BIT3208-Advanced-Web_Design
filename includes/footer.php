<?php
// includes/footer.php - RESPONSIVE
?>
    </main>
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Salon Pro. Where Beauty Meets Excellence.</p>
        <p style="margin-top: 0.5rem; color: #d4af37; font-size: 0.85rem;">✨ Luxury Beauty at Your Fingertips ✨</p>
    </footer>

    <!-- Include dashboard.js for ALL pages -->
    <script src="<?php echo $base_path; ?>assets/js/dashboard.js"></script>
    
    <style>
        .footer {
            background: #050505;
            padding: 2rem 5%;
            text-align: center;
            border-top: 1px solid #d4af37;
            margin-top: 2rem;
        }
        .footer p { margin: 0.3rem 0; }
        
        @media (max-width: 768px) {
            .footer { padding: 1.5rem 4%; }
            .footer p { font-size: 0.85rem; }
        }
        @media (max-width: 480px) {
            .footer { padding: 1rem 3%; }
            .footer p { font-size: 0.75rem; }
        }
    </style>
</body>
</html>