<?php
include 'config.php';
ini_set("display_errors", 0);

// Set headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        "success" => false,
        "message" => "Only POST method is allowed."
    ]);
    exit;
}

// Read and decode raw JSON input
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Validate required parameters
if (empty($data['date']) || empty($data['bench']) || empty($data['ctype'])) {
    http_response_code(400); // Bad Request
    echo json_encode([
        "success" => false,
        "message" => "Missing required parameters: date, bench, or ctype."
    ]);
    exit;
}

$date = $data['date'];
$bench = $data['bench'];
$ctype = $data['ctype'];

// Validate date format
$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid date format. Expected format: YYYY-MM-DD."
    ]);
    exit;
}

// API endpoint (from config)
$regApiUrl = $BASE_URL82 . "CauselistDetails.php";

// Prepare payload
$postData = json_encode([
    "date" => $date,
    "bench" => $bench,
    "ctype" => $ctype
]);

// Initialize cURL
$ch = curl_init($regApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Handle cURL errors
if (curl_errno($ch)) {
    echo json_encode([
        "success" => false,
        "message" => "Request failed: " . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Validate response is JSON
$decodedResponse = json_decode($response, true);

// Return valid JSON or fallback
if (json_last_error() === JSON_ERROR_NONE) {
    http_response_code($httpCode);
    echo json_encode($decodedResponse);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON response from internal API.",
        "raw_response" => $response
    ]);
}
