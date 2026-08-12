<?php
// mail_helper.php

/**
 * Simulates sending an email via an API key (e.g., SendGrid, Resend, etc.)
 */
function sendVerificationEmail($email, $username) {
    // You mentioned you have an API key!
    // Paste it here when you are ready to connect to your provider.
    $api_key = "YOUR_API_KEY_HERE";
    $api_endpoint = "https://api.yourprovider.com/v1/send";
    
    // Simulate API Payload
    $payload = [
        'to' => $email,
        'subject' => "Welcome to Vibeesta, $username!",
        'body' => "Your account has been created. Please wait for an Admin to approve your access."
    ];
    
    // In production, use cURL to POST $payload to $api_endpoint using $api_key
    // For now, we will log it so the app doesn't crash during testing.
    error_log("Simulated Email Sent to: $email using API Key. Subject: " . $payload['subject']);
    
    return true; // Assume success
}
?>
