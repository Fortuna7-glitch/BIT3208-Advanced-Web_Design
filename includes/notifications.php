<?php
// includes/notifications.php - Enhanced notification functions

function sendAppointmentConfirmation($conn, $appointment_id) {
    $query = "SELECT a.*, c.full_name as customer_name, c.email as customer_email, c.phone as customer_phone,
                     s.service_name, s.price, st.full_name as staff_name
              FROM appointments a
              JOIN users c ON a.customer_id = c.id
              JOIN services s ON a.service_id = s.id
              LEFT JOIN users st ON a.staff_id = st.id
              WHERE a.id = $appointment_id";
    
    $result = mysqli_query($conn, $query);
    $apt = mysqli_fetch_assoc($result);
    
    $message = "✨ SALON PRO APPOINTMENT CONFIRMATION ✨\n\n";
    $message .= "Dear {$apt['customer_name']},\n\n";
    $message .= "Your appointment has been confirmed!\n\n";
    $message .= "📅 Service: {$apt['service_name']}\n";
    $message .= "💰 Price: KSh " . number_format($apt['price'], 2) . "\n";
    $message .= "👤 Stylist: " . ($apt['staff_name'] ?? 'Any Available') . "\n";
    $message .= "📆 Date: " . date('l, F d, Y', strtotime($apt['appointment_date'])) . "\n";
    $message .= "⏰ Time: " . date('g:i A', strtotime($apt['appointment_time'])) . "\n";
    $message .= "🎫 Queue Position: " . ($apt['queue_position'] ?? '1') . "\n\n";
    $message .= "📍 Location: Luxury Mall, Nairobi\n";
    $message .= "📞 Contact: 0712345678\n\n";
    $message .= "Thank you for choosing Salon Pro! ✨";
    
    // Send email
    sendEmail($apt['customer_email'], "Appointment Confirmed - Salon Pro", nl2br($message));
    
    // Send SMS
    sendSMS($apt['customer_phone'], $message);
    
    // Store notification
    sendNotification($apt['customer_id'], "Appointment Confirmed", $message, 'email');
    
    return true;
}

function sendAppointmentReminder($conn) {
    // Check for appointments in next 24 hours
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $query = "SELECT a.*, c.full_name, c.email, c.phone, s.service_name
            FROM appointments a
            JOIN users c ON a.customer_id = c.id
            JOIN services s ON a.service_id = s.id
            WHERE a.appointment_date = '$tomorrow' AND a.status NOT IN ('cancelled', 'completed')";
    
    $result = mysqli_query($conn, $query);
    
    while ($apt = mysqli_fetch_assoc($result)) {
        $message = "🔔 REMINDER: Your appointment at Salon Pro is tomorrow, " . date('l, F d', strtotime($apt['appointment_date'])) . " at " . date('g:i A', strtotime($apt['appointment_time'])) . " for {$apt['service_name']}. See you soon! ✨";
        
        sendSMS($apt['phone'], $message);
        sendEmail($apt['email'], "Appointment Reminder - Salon Pro", $message);
    }
}

// Run reminder check (can be set as cron job)
// sendAppointmentReminder($conn);
?>