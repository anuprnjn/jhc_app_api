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
$requiredParams = ['table_name', 'secret_key'];
foreach ($requiredParams as $param) {
    if (!isset($data[$param]) || empty($data[$param])) {
        http_response_code(400); // Bad Request
        echo json_encode([
            "success" => false,
            "message" => "Missing or empty required parameter: {$param}."
        ]);
        exit;
    }
}

// Assign values
$table_name = $data['table_name'];
$secret_key = $data['secret_key'];

// API endpoint (from config)
$regApiUrl = $BASE_URL82 . "hcOfficerContactNo.php";

// Prepare payload with only table_name and secret_key
$postData = json_encode([
    "table_name" => $table_name,
    "secret_key" => $secret_key
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
    http_response_code($httpCode); // Mirror internal API response code
    echo json_encode($decodedResponse);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON response from internal API.",
        "raw_response" => $response
    ]);
}
