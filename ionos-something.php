// IONOS DNS API Library
<?php

    const API_BASE_URL = "https://api.hosting.ionos.com/dns/v1/";  // MUST have trailing /
    const DEBUGGING = true;
    $apiKey = '797a08f724bc44d48bc22e14c0a5d7e5.-OOIUb8Jyn8se5vMo2rgdl3vKXT4D-XecwFT--BKLqcrtFUYxugwqzl-pXEeLhqZh35yFVYEFjHbqhYLma9jkw';

    $filename = '/root/php-debug.log';
    $handle = fopen($filename, 'a'); // 'w' for write (overwrites), 'a' for append
    if (!$handle) { exit (1); }
    fwrite ($handle, "\n************************** " . date('Y-m-d H:i:s', time()) . "\n");

    // Log errors
    function logError($message) {
        global $handle;
        $log = "SSS DNS Sync (api-ionos) logError: $message";
        fwrite ($handle, $log . "\n");
    }

    // Log debugging info
    function logDebug($message, $vardata = '') {
        global $handle;
        if (DEBUGGING) {
            $log = "SSS DNS Sync (api-ionos) logDebug: $message\n" . var_export($vardata, true);
            fwrite ($handle, $log . "\n");
        }
    }

    // Make a request to the IONOS API

    function IonosApiRequest($method, $func, $apiKey, $data = null) {
        global $handle;

        try {
            fwrite($handle, "API Request: $method $func \n");
            logDebug("API Request: $method $func", $data);
            $ch = curl_init();
        	fwrite($handle, "API Request: Inited \n");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_URL, API_BASE_URL . $func);
        	curl_setopt($ch, CURLOPT_USERAGENT, 'curl/7.74.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "X-API-Key: $apiKey",
                "Content-Type: application/json",
            ]);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }

            fwrite($handle, "API Request: Executing \n");
            $response = curl_exec($ch);   // DO IT !
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            // curl_close($ch); No longer needed - it's a no op now.

            fwrite($handle, "API Response: $method, $func, $httpCode, $errno, $error, body: $response \n");
            logDebug("API Response: $method $func", ['code' => $httpCode, 'body' => $response]);

            if ($errno) {
                logError("cURL error: $errno $error");
                return ['error' => $error, 'code' => 500];
            }

            $body = $response ? json_decode($response, true) : null;
            if ($httpCode >= 400) {
                $errorMsg = isset($body[0]['message']) ? $body[0]['message'] : 'API request failed';
                logError("API error: $httpCode $errorMsg");
                return ['error' => $errorMsg, 'code' => $httpCode, 'body' => $body];
            }

            return ['code' => $httpCode, 'body' => $body];
        } catch (Exception $e) {
            $errMessage = $e->getMessage();
	        fwrite($handle, "IAR: Unexpected error: $errMessage \n");
            logError("IAR Unexpected error: " . $errMessage);
            return ['error' => 'Internal server error', 'code' => 500];
        }
    }

IonosApiRequest('GET', 'zones', $apiKey);

    // fwrite ($handle, $result . "\n");
    $output = ob_get_clean(); // Get the buffered output and clear the buffer
    fwrite($handle, "***** Output buffer *****\n");
    fwrite($handle, $output); // Write the captured output to the file
    fclose($handle); // Close the file handle
