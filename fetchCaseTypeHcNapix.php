<?php

include 'config.php';
ini_set("display_errors", 0);

// Load credentials from config
$accessToken = generateAccessToken($NAPIX_API_KEY, $NAPIX_SEC_KEY, $TOKEN_GENERATE_URL);
if (!$accessToken) {
    die("Failed to get access token.");
}

$requestData = $HC_EST_CODE;
$requestToken = generateHMACToken($requestData);
$encryptedStr = encryptRequestStr($requestData, $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);

// Construct full API URL
$apiUrl =  $BASE_URL."hc-case-type-master-api/casetypemaster";
$apiUrl .= "?dept_id={$DEPT_ID}&request_str={$encryptedStr}&request_token={$requestToken}&version={$VERSION}";

// Call the API
$response = makeApiRequest($apiUrl, $accessToken);
$responseData = json_decode($response, true);

// Handle response
if (isset($responseData['response_str'])) {
    $decrypted = decryptResponseStr($responseData['response_str'], $NAPIX_AUTHENTICATION_KEY, $IV_VALUE);
    echo $decrypted;

    $finalData = json_decode($decrypted, true);
} elseif (isset($responseData['status'])) {
    echo $response;
}

?>
