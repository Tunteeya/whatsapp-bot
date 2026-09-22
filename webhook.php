<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// ==============================
// CONFIGURATION (use env vars for production)
// ==============================

$verify_token = getenv('VERIFY_TOKEN') ?: 'my_secret_token_123';
$access_token = getenv('WHATSAPP_ACCESS_TOKEN') ?: 'EAArC1ZBrUHQ0BSt9rZBzCHPfQ9zrb6tydpkUZC73RRRpDz88g6Bi1LRhcWLbYawocFfO64VqoDD2zTIMMdQWZBmrDVAJYdbmDuGuZA5ZCifZCSrZBkOoxSIPuHMZCiCCjpNZAdhGrHdjEBlISvbZClOt697ZCL7jnQ0eZCEK37R3QhLB9pFnJLSGnBc9Hbsawjy7CshjnNDZAZCSz1W5U9Qa0o4sNEizBQfLVcdFCMrvutPPADvz2N04EY3u8lj4udRIeu25ahSV4HCCNSYQW4ElnH6U8Q0WDar';
$phone_number_id = getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '1350151684842334';
$business_account_id = getenv('WHATSAPP_BUSINESS_ACCOUNT_ID') ?: ''; // Optional: for more advanced features
$whatsapp_api_url = 'https://graph.instagram.com/v18.0';

// ==============================
// WEBHOOK VERIFICATION (GET)
// ==============================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && $token === $verify_token) {
        http_response_code(200);
        echo $challenge;
        error_log("Webhook verified successfully");
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    error_log("Webhook verification failed. Mode: $mode, Token match: " . ($token === $verify_token ? 'yes' : 'no'));
    exit;
}

// ==============================
// RECEIVE & RESPOND TO WEBHOOKS (POST)
// ==============================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    
    // Log all incoming requests
    file_put_contents(
        __DIR__ . '/webhook_log.txt',
        date('Y-m-d H:i:s') . " [INCOMING]\n" . $input . "\n\n",
        FILE_APPEND
    );

    error_log("Webhook POST received: " . substr($input, 0, 200));

    $data = json_decode($input, true);

    // Acknowledge receipt immediately
    http_response_code(200);
    echo json_encode(['status' => 'received']);

    // Exit early if not a message webhook
    if (!isset($data['entry']) || empty($data['entry'])) {
        exit;
    }

    // Process each entry in the webhook
    foreach ($data['entry'] as $entry) {
        if (!isset($entry['changes'])) continue;

        foreach ($entry['changes'] as $change) {
            if ($change['field'] !== 'messages') continue;

            $message_data = $change['value'] ?? [];
            
            // Extract incoming message details
            if (isset($message_data['messages']) && !empty($message_data['messages'])) {
                $message = $message_data['messages'][0];
                $from = $message['from'] ?? null;
                $message_id = $message['id'] ?? null;
                $message_type = $message['type'] ?? 'unknown';
                $timestamp = $message['timestamp'] ?? time();

                if (!$from) continue;

                // Extract message text
                $message_text = '';
                if ($message_type === 'text' && isset($message['text'])) {
                    $message_text = $message['text']['body'] ?? '';
                }

                error_log("Message received from $from: " . substr($message_text, 0, 100));

                // Send automatic reply
                $reply_text = "Thanks for your message! We received: \"" . substr($message_text, 0, 50) . (strlen($message_text) > 50 ? '...' : '') . "\"";
                
                if (sendMessage($access_token, $phone_number_id, $from, $reply_text)) {
                    error_log("Reply sent to $from");
                } else {
                    error_log("Failed to send reply to $from");
                }
            }

            // Log status updates (optional)
            if (isset($message_data['statuses']) && !empty($message_data['statuses'])) {
                $status = $message_data['statuses'][0];
                error_log("Message status update: ID=" . ($status['id'] ?? 'unknown') . ", Status=" . ($status['status'] ?? 'unknown'));
            }
        }
    }

    exit;
}

// ==============================
// OTHER REQUESTS
// ==============================

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
exit;

// ==============================
// HELPER FUNCTION: Send Message via WhatsApp API
// ==============================

function sendMessage($access_token, $phone_number_id, $to_number, $message_text) {
    global $whatsapp_api_url;

    $url = "$whatsapp_api_url/$phone_number_id/messages";

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to_number,
        'type' => 'text',
        'text' => [
            'body' => $message_text
        ]
    ];

    $ch = curl_init($url);
    
    if (!$ch) {
        error_log("Failed to initialize cURL");
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    curl_close($ch);

    if ($curl_error) {
        error_log("cURL error: $curl_error");
        return false;
    }

    error_log("WhatsApp API response (HTTP $http_code): " . substr($response, 0, 200));

    if ($http_code >= 200 && $http_code < 300) {
        return true;
    }

    return false;
}

?>

