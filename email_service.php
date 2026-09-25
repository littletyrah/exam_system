<?php

function sendExamNotificationEmail($toEmail, $toName, $examTitle, $examDate, $examTime, $venue) {
    $apiKey = $_ENV['BREVO_API_KEY'];
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'];
    $senderName = $_ENV['BREVO_SENDER_NAME'];

    $data = [
        'sender' => [
            'name' => $senderName,
            'email' => $senderEmail
        ],
        'to' => [
            ['email' => $toEmail, 'name' => $toName]
        ],
        'subject' => "Exam Notification: $examTitle",
        'htmlContent' => "
            <h3>Exam Schedule Notification</h3>
            <p>Dear $toName,</p>
            <p>You have an upcoming examination:</p>
            <ul>
                <li><strong>Exam:</strong> $examTitle</li>
                <li><strong>Date:</strong> $examDate</li>
                <li><strong>Time:</strong> $examTime</li>
                <li><strong>Venue:</strong> $venue</li>
            </ul>
            <p>Please arrive on time. Good luck!</p>
        "
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}