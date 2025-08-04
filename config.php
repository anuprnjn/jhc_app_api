<?php

$NAPIX_API_KEY = "7142afec36bb82b50fba82e1a94b7de2";
$NAPIX_SEC_KEY = "45a6d25bd815fb12a80a371a5a93fea2";
$TOKEN_GENERATE_URL = 'https://delhigw.napix.gov.in/nic/ecourts//oauth2/token';
$NAPIX_AUTHENTICATION_KEY = "S8j9oXnoruCtYlGk";
$DEPT_ID = 'ecourt_jharkhand';
$VERSION = 'v1.0';
$IV_VALUE = 'S8j9oXnoruCtYlGk';
$BASE_URL = 'https://delhigw.napix.gov.in/nic/ecourts/';
$HC_EST_CODE = "est_code=JHHC01";

function generateAccessToken($clientId, $clientSecret, $tokenUrl)
{
    $combinedKey = base64_encode($clientId . ':' . $clientSecret);
    $postFields = "grant_type=client_credentials&scope=napix";

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $tokenUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => array("Authorization: Basic " . $combinedKey),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true)['access_token'] ?? null;
}

function generateHMACToken($data, $secret = "15081947")
{
    return hash_hmac('sha256', $data, $secret);
}

function makeApiRequest($url, $accessToken)
{
    $headers = ['Authorization: Bearer ' . $accessToken];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo curl_error($ch);
    }
    curl_close($ch);
    return $response;
}

function encryptRequestStr($input, $key, $iv)
{
    $encrypted = openssl_encrypt($input, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return urlencode(base64_encode($encrypted));
}

function decryptResponseStr($encryptedStr, $key, $iv)
{
    $decoded = base64_decode($encryptedStr);
    if ($decoded === false) {
        die("Base64 decoding failed.");
    }

    $decrypted = openssl_decrypt($decoded, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        die("AES decryption failed.");
    }

    return $decrypted;
}


?>
