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
if (!isset($data['case_type'], $data['fil_no'], $data['fil_year'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters."]);
    exit();
}

// Step 3: Assign values from JSON input
$case_type = $data['case_type'];
$fil_no = $data['fil_no'];
$fil_year = $data['fil_year'];

// Step 4: Generate Access Token
$accessToken = generateAccessToken($NAPIX_API_KEY, $NAPIX_SEC_KEY, $TOKEN_GENERATE_URL);
if (!$accessToken) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to get access token."]);
    exit();
}

// Step 5: Prepare Filing request string and encrypt
$requestData = $HC_EST_CODE . "|case_type={$case_type}|fil_no={$fil_no}|fil_year={$fil_year}";
$requestToken = generateHMACToken($requestData);
$encryptedStr = encryptRequestStr($requestData, $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);

// Step 6: Construct Filing API URL
$filingApiUrl = $BASE_URL . "hc-filing-api/Filing";
$filingApiUrl .= "?dept_id={$DEPT_ID}&request_str={$encryptedStr}&request_token={$requestToken}&version={$VERSION}";

// Step 7: Call Filing API
$filingResponse = makeApiRequest($filingApiUrl, $accessToken);
$filingData = json_decode($filingResponse, true);

// Step 8: Check and decrypt response
if (isset($filingData['response_str'])) {
    $decryptedFiling = decryptResponseStr($filingData['response_str'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
    $filingJson = json_decode($decryptedFiling, true);

    // Step 9: Extract CINO
    if (isset($filingJson['casenos']['case1']['cino'])) {
        $cino = $filingJson['casenos']['case1']['cino'];

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
                "filing_data" => $filingJson,
                "cnr_data" => $cnrJson
            ]);
        } else {
            echo json_encode([
                "filing_data" => $filingJson,
                "cnr_error" => $cnrData
            ]);
        }
    } else {
        echo json_encode([
            "error" => "CINO not found in filing response.",
            "filing_data" => $filingJson
        ]);
    }
} elseif (isset($filingData['status'])) {
    $decrypted = decryptResponseStr($filingData['status'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
    echo $decrypted; // Output decrypted data (usually JSON)
} else {
    echo json_encode([
        "error" => "Invalid response from filing API.",
        "response" => $filingData
    ]);
}
