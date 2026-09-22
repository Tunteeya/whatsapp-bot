
<?php

// ==========================================
// CONFIGURATION
// ==========================================

$verify_token = "my_secret_token_123";

// IMPORTANT: Put your NEW Meta access token here.
// Do NOT send the token to me.
$accessToken = "EAArC1ZBrUHQ0BSt9rZBzCHPfQ9zrb6tydpkUZC73RRRpDz88g6Bi1LRhcWLbYawocFfO64VqoDD2zTIMMdQWZBmrDVAJYdbmDuGuZA5ZCifZCSrZBkOoxSIPuHMZCiCCjpNZAdhGrHdjEBlISvbZClOt697ZCL7jnQ0eZCEK37R3QhLB9pFnJLSGnBc9Hbsawjy7CshjnNDZAZCSz1W5U9Qa0o4sNEizBQfLVcdFCMrvutPPADvz2N04EY3u8lj4udRIeu25ahSV4HCCNSYQW4ElnH6U8Q0WDar";

$phoneNumberId = "1350151684842334";


// ==========================================
// WEBHOOK VERIFICATION
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // PHP converts dots in parameter names to underscores.
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && $token === $verify_token) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}


// ==========================================
// RECEIVE WHATSAPP WEBHOOK
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = file_get_contents('php://input');

    // Save incoming webhook for debugging.
    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s') . " - INCOMING: " . $input . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    $data = json_decode($input, true);

    // ==========================================
    // FIND THE WHATSAPP MESSAGE
    // ==========================================

    $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    // Ignore events that are not actual messages.
    if (!$message) {
        http_response_code(200);
        echo 'EVENT_RECEIVED';
        exit;
    }

    // Only respond to text messages.
    if (($message['type'] ?? '') !== 'text') {
        http_response_code(200);
        echo 'EVENT_RECEIVED';
        exit;
    }

    // Person who sent the WhatsApp message.
    $from = $message['from'] ?? '';

    // Message they sent.
    $incomingText = $message['text']['body'] ?? '';

    if ($from === '') {
        http_response_code(200);
        echo 'EVENT_RECEIVED';
        exit;
    }


    // ==========================================
    // BOT REPLY
    // ==========================================

    $reply = "Hello! 👋\n\n"
           . "Welcome to my Business Bot. 🚀\n\n"
           . "You said: " . $incomingText;


    // ==========================================
    // SEND REPLY THROUGH WHATSAPP CLOUD API
    // ==========================================

    $url = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";

    $sendData = [
        "messaging_product" => "whatsapp",
        "recipient_type" => "individual",
        "to" => $from,
        "type" => "text",
        "text" => [
            "preview_url" => false,
            "body" => $reply
        ]
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $accessToken,
        "Content-Type: application/json"
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sendData));

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlError = curl_error($ch);

    curl_close($ch);


    // ==========================================
    // SAVE SEND RESULT FOR DEBUGGING
    // ==========================================

    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s')
        . " - SEND HTTP: " . $httpCode
        . " - RESPONSE: " . $response
        . " - CURL ERROR: " . $curlError
        . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );


    // ==========================================
    // TELL META WE RECEIVED THE WEBHOOK
    // ==========================================

    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}


// ==========================================
// OTHER REQUESTS
// ==========================================

http_response_code(404);
echo 'Not Found';




