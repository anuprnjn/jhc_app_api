<?php

include 'config.php';
ini_set("display_errors", 0);

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST method is allowed."]);
    exit();
}

// Step 1: Read raw JSON input from POST
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Step 2: Validate input
if (!isset($data['cino']) || empty($data['cino'])) {
    http_response_code(400);
    echo json_encode(["error" => "CINO parameter is required."]);
    exit();
}

// Step 3: Assign CINO value from JSON input
$cino = trim($data['cino']);

// Step 4: Generate Access Token
$accessToken = generateAccessToken($NAPIX_API_KEY, $NAPIX_SEC_KEY, $TOKEN_GENERATE_URL);
if (!$accessToken) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to get access token."]);
    exit();
}

// Step 5: Prepare CNR API request string and encrypt
$cnrRequestStr = $HC_EST_CODE . "|cino={$cino}";
$cnrToken = generateHMACToken($cnrRequestStr);
$cnrEncryptedStr = encryptRequestStr($cnrRequestStr, $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);

// Step 6: Construct CNR API URL
$cnrApiUrl = $BASE_URL . "hc-cnr-api/CNR";
$cnrApiUrl .= "?dept_id={$DEPT_ID}&request_str={$cnrEncryptedStr}&request_token={$cnrToken}&version={$VERSION}";

// Step 7: Call CNR API
$cnrResponse = makeApiRequest($cnrApiUrl, $accessToken);
$cnrData = json_decode($cnrResponse, true);

// Step 8: Check and decrypt response
if (isset($cnrData['response_str'])) {
    $decryptedCnr = decryptResponseStr($cnrData['response_str'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
    $cnrJson = json_decode($decryptedCnr, true);
    
    // Step 9: Send response to user
    echo json_encode($cnrJson);
} elseif (isset($cnrData['status'])) {
    $decrypted = decryptResponseStr($cnrData['status'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
    echo $decrypted; // Output decrypted data (usually JSON)
} else {
    echo json_encode([
        "error" => "Invalid response from CNR API.",
        "response" => $cnrData
    ]);
}

?>
