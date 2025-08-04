<?php

include 'config.php';
ini_set("display_errors", 0);

// CORS headers
header("Access-Control-Allow-Origin: *"); // Use specific domain in production
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["error" => "Only POST method is allowed."]);
    exit();
}

// Step 1: Read raw JSON input from POST
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Step 2: Validate input
if (!isset($data['pend_disp'], $data['advocate_name'], $data['reg_year'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "Missing required parameters."]);
    exit();
}

// Step 3: Assign values from JSON input
$pend_disp = $data['pend_disp'];
$advocate_name = $data['advocate_name'];
$reg_year = $data['reg_year'];

// Step 4: Generate Access Token
$accessToken = generateAccessToken($NAPIX_API_KEY, $NAPIX_SEC_KEY, $TOKEN_GENERATE_URL);
if (!$accessToken) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to get access token."]);
    exit();
}

// Step 5: Prepare request string and encrypt it
$requestData = $HC_EST_CODE . "|pend_disp={$pend_disp}|advocate_name={$advocate_name}|reg_year={$reg_year}";
$requestToken = generateHMACToken($requestData);
$encryptedStr = encryptRequestStr($requestData, $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);

// Step 6: Construct API URL
$apiUrl = $BASE_URL . "hc-advocate-name-api";
$apiUrl .= "?dept_id={$DEPT_ID}&request_str={$encryptedStr}&request_token={$requestToken}&version={$VERSION}";

// Step 7: Make API Call
$response = makeApiRequest($apiUrl, $accessToken);
$responseData = json_decode($response, true); // Debugging line, remove in production

// Step 8: Handle API response
if (isset($responseData['response_str'])) {
    $decrypted = decryptResponseStr($responseData['response_str'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
    echo $decrypted; // Output decrypted data (usually JSON)
} elseif (isset($responseData['status'])) {
    $decrypted = decryptResponseStr($responseData['status'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
    echo $decrypted; // Output decrypted data (usually JSON)
} else {
    http_response_code(500);
    echo json_encode(["error" => "Unexpected API response."]);
}
?>