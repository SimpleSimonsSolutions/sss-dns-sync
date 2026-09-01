<?php
// /usr/local/psa/admin/plib/modules/sss-dns-sync/library/IonosApi.php
// https://x.com/i/grok?conversation=1949223611530703142

class IonosApi {
	const DEBUGGING = true;
    const API_BASE_URL = "https://api.hosting.ionos.com/dns/v1/";

    /* private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    */

	// Log errors with GUIDs
	private function logError($message, $clientGuid = '', $domainGuid = '') {
    	$log = "SSS DNS Sync (IonosApi): $message";
    	if ($clientGuid) $log .= " [Client GUID: $clientGuid]";
    	if ($domainGuid) $log .= " [Domain GUID: $domainGuid]";
    	pm_Log::err($log);
	}
	// Log debugging info
	private function logDebug($message, $vardata) {
    //	if (DEBUGGING) {
	    	$log = "SSS DNS Sync (IonosApi): $message";
    		pm_Log::backtrace($log);
    		pm_Log::vardump($vardata);
    //    }
	}

    /**
     * Make a request to the IONOS API
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $func API endpoint
     * @param array|null $data Data to send with the request
     * @return array Response data
     */
    public function IonosApiRequest($method, $func, $apiKey, $data = null) {
    	logDebug("Entry", $data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, API_BASE_URL . $func);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-API-KEY: 797a08f724bc44d48bc22e14c0a5d7e5.-OOIUb8Jyn8se5vMo2rgdl3vKXT4D-XecwFT--BKLqcrtFUYxugwqzl-pXEeLhqZh35yFVYEFjHbqhYLma9jkw",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);  // DO IT!
    	logDebug("Response", $response);
    
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
    	if ($errno) {
        	logError('cURL error: ' . $errno . ' ' . $error);
            return ['error' => $error];
        } else {
	        return [
    	        'code' => $httpCode,
        	    'body' => $response ? json_decode($response, true) : null,
        	];
        }
    }
}