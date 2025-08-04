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
if (!isset($data['case_type'], $data['reg_no'], $data['reg_year'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters."]);
    exit();
}

// Step 3: Assign values from JSON input
$case_type = $data['case_type'];
$reg_no = $data['reg_no'];
$reg_year = $data['reg_year'];

// Step 4: Generate Access Token
$accessToken = generateAccessToken($NAPIX_API_KEY, $NAPIX_SEC_KEY, $TOKEN_GENERATE_URL);
if (!$accessToken) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to get access token."]);
    exit();
}

// Step 5: Prepare registration request string and encrypt
$requestData = $HC_EST_CODE . "|case_type={$case_type}|reg_no={$reg_no}|reg_year={$reg_year}";
$requestToken = generateHMACToken($requestData);
$encryptedStr = encryptRequestStr($requestData, $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);

// Step 6: Construct Registration API URL
$regApiUrl = $BASE_URL . "hc-case-search-api/casesearch";
$regApiUrl .= "?dept_id={$DEPT_ID}&request_str={$encryptedStr}&request_token={$requestToken}&version={$VERSION}";

// Step 7: Call Registration API
$regResponse = makeApiRequest($regApiUrl, $accessToken);
$regData = json_decode($regResponse, true);

// Step 8: Check and decrypt response
if (isset($regData['response_str'])) {
    $decryptedReg = decryptResponseStr($regData['response_str'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
    $regJson = json_decode($decryptedReg, true);

    // Step 9: Extract CINO
    if (isset($regJson['casenos']['case1']['cino'])) {
        $cino = $regJson['casenos']['case1']['cino'];

        // Step 10: Prepare CNR API request
        $cnrRequestStr = $HC_EST_CODE . "|cino={$cino}";
        $cnrToken = generateHMACToken($cnrRequestStr);
        $cnrEncryptedStr = encryptRequestStr($cnrRequestStr, $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);

        $cnrApiUrl = $BASE_URL . "hc-cnr-api/CNR";
        $cnrApiUrl .= "?dept_id={$DEPT_ID}&request_str={$cnrEncryptedStr}&request_token={$cnrToken}&version={$VERSION}";

        // Step 11: Call CNR API
        $cnrResponse = makeApiRequest($cnrApiUrl, $accessToken);
        $cnrData = json_decode($cnrResponse, true);

        // Step 12: Decrypt CNR response
        if (isset($cnrData['response_str'])) {
            $decryptedCnr = decryptResponseStr($cnrData['response_str'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
            $cnrJson = json_decode($decryptedCnr, true);

            // Step 13: Send combined response to user
            echo json_encode([
                "registration_data" => $regJson,
                "cnr_data" => $cnrJson
            ]);
        } else {
            echo json_encode([
                "registration_data" => $regJson,
                "cnr_error" => $cnrData
            ]);
        }
    } else {
        echo json_encode([
            "error" => "CINO not found in registration response.",
            "registration_data" => $regJson
        ]);
    }
} elseif (isset($regData['status'])) {
    $decrypted = decryptResponseStr($regData['status'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
    echo $decrypted; // Output decrypted data (usually JSON)
} else {
    echo json_encode([
        "error" => "Invalid response from registration API.",
        "response" => $regData
    ]);
}
?>
