<?php
/*
https://x.com/i/grok?conversation=1954727566893613164

Code Details:
Structure: Single dnsInterface function processes JSON input and $apiKey, returns JSON response.
Validation:Checks COMMAND validity.
Ensures required fields (NEW_* for Create, OLD_*+NEW_* for Update/Delete).
Validates OLD_TYPE and NEW_TYPE against IONOS’s supported list.

Zone Lookup: Uses GET /v1/zones to find zoneId by OLD/NEW_DOMAIN_NAME.
Record Matching: For Update/Delete, uses:
    GET /v1/zones/{zoneId}?recordName=OLD_HOSTNAME&recordType=OLD_TYPE and matches OLD_VALUE to find recordId.

CRUD Operations:
Create: POST /v1/zones/{zoneId}/records with NEW_* fields.
Delete: DELETE /v1/zones/{zoneId}/records/{recordId}.
Update: If OLD_TYPE ≠ NEW_TYPE, DELETE then POST (create); else PUT /v1/zones/{zoneId}/records/{recordId}.

API Calls: Uses IonosApiRequest with $apiKey, handling HTTP errors (400, 401, 404, 500).
Logging: Errors via logError, debug info via logDebug with var_export (simpler than var_dump for logging).
Response:Success: { "status": "success", "code": 200/201, "data": [...] } (IONOS response or empty for Delete).
Error: { "error": "message", "code": 400/401/404/500 } (unified for API and try/catch errors).

Notes:Skips processing if $apiKey is empty.
Passes TTL as-is from Plesk (NEW_TTL, default 3600).
Ignores rate limits (Dynamic DNS out of scope).
Uses var_export for cleaner debug logs.

Setup in Plesk: Place in /var/www/vhosts/yourdomain/httpdocs or CLI script path.
Ensure curl and json PHP extensions are enabled in Plesk.
Pass $apiKey from your Plesk extension (TBD).
Call dnsInterface($input, $apiKey) with JSON input.

Testing:Uncomment the example usage to test with your $apiKey.
Check logs via Plesk’s log viewer for pm_Log::err() output.
*/

    // declare(strict_types=1);

    const API_BASE_URL = "https://api.hosting.ionos.com/dns/v1/";  // MUST have trailing /
    const DEBUGGING = true;

    if (DEBUGGING) {
        $filename = '/root/ionos.log';
        $handle = fopen($filename, 'a'); // 'w' for write (overwrites), 'a' for append
        if (!$handle) { exit (1); }

        ob_start(); // Start output buffering
        $log = "\n************************** " . date('Y-m-d H:i:s', time()) . " < $argc >";
    	echo $log;
	    fwrite($handle, $log . "\n");
    }

    // Log errors
    function logError($message) {
        global $handle;
        $log = "SSS DNS Sync (api-ionos) logError: $message";
        if (DEBUGGING) {
            fwrite ($handle, $log . "\n");
        }
        pm_Log::err($log);
    }

    // Log debugging info
    function logDebug($message, $vardata = '') {
        global $handle;
        if (DEBUGGING) {
            $log = "SSS DNS Sync (api-ionos) logDebug: $message\n" . var_export($vardata, true);
            fwrite ($handle, $log . "\n");
            pm_Log::err($log);
        }
    }

/**
 * Make a request to the IONOS API
 *
 * @param string $method HTTP method (GET, POST, PUT, DELETE)
 * @param string $func API endpoint - NO leading slash with possible RARE exception.
 * @param string $apiKey API key for X-API-Key header
 * @param array|null $data Data to send with the request
 * @return array Response data
 */
    function IonosApiRequest($method, $func, $apiKey, $data = null) {
        global $handle;

        try {
            fwrite($handle, "API Request: $method $func \n");
            logDebug("API Request: $method $func", $data);
            $ch = curl_init();
        	fwrite($handle, "API Request: Inited \n");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));  // GET, POST, PUT, DELETE
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


/**
 * Get domain zone ID via Ionos API
 *
 * @param string $domain Domain name
 * @param string $apiKey IONOS API key
 * @return array value, error, code
 */

    function GetZoneId (string $domain, string $apiKey): array {  // Find zoneId
        global $handle;
	    fwrite($handle, "GZI: ENTRY $domain $apiKey \n");

        $zoneResponse = IonosApiRequest('GET', 'zones', $apiKey);
	    fwrite($handle, "GZI: zoneResponse: $zoneResponse\n");
        
        if (isset($zoneResponse['error'])) {
            logError("Unable to get customer's Zones. Want: $domain");
            return ['value' => null, 'error' => $zoneResponse['error'], 'code' => $zoneResponse['code']];
        }

        $zoneId = null;
        foreach ($zoneResponse['body'] as $zone) {
            if ($zone['name'] === $domain) {
                $zoneId = $zone['id'];
                break;
            }
        }

        if (!$zoneId) {
            logError("Zone not found for domain: $domain");
            return ['value' => null, 'error' => "Zone not found for domain: $domain", 'code' => 404];
        }

        logDebug("Zone ID found", ['zoneId' => $zoneId, 'domain' => $domain]);
        return ['value' => $zoneId, 'error' => null, 'code' => 200];
    }


/**
 * Get DNS record ID via Ionos API
 *
 * @param string $zoneId ID of the DNS Zone for the domain
 * @param string $recordName DNS record name (hostname)
 * @param string $recordType DNS record type (A, CNAME, etc.)
 * @param string $recordValue DNS record value (content)
 * @param string $apiKey IONOS API key
 * @return array value, error, code
 */

    function GetRecordId (string $zoneId, string $recordName, string $recordType, string $recordValue, string $apiKey): array {  // Find recordId
        global $handle;
	    fwrite($handle, "GRI: ENTRY $zoneId\n");

        $recordResponse = IonosApiRequest('GET', "zones/$zoneId?recordName=$recordName&recordType=$recordType", $apiKey);
	    fwrite($handle, "GZI: recordResponse: $recordResponse\n");
        if (isset($recordResponse['error'])) {
            logError("Unable to find records for {$zoneId}.{$recordType}.{$recordName}.");
            return ['error' => $recordResponse['error'], 'code' => $recordResponse['code']];
        }

        $recordId = null;
        foreach ($recordResponse['body']['records'] as $record) {
            // Done in the API request: $record['name'] === $recordName && $record['type'] === $recordType && 
            if ($record['content'] === $recordValue) {
                $recordId = $record['id'];
                break;
            }
        }

        if (!$recordId) {
            logError("Record not found for {$zoneId}.{$recordName}.{$recordType}.{$recordValue}");
            return ['value' => null, 'error' => 'Record not found', 'code' => 404];
        }

        logDebug("Record found", ['recordId' => $recordId, 'recordName' => $recordName, 'recordType' => $recordType, 'recordValue' => $recordValue]);    
        return ['value' => $recordId, 'error' => null, 'code' => 200];

    }

/**
 * DNS Middleware for sending Plesk DNS events to Ionos API
 *
 * @param array $input JSON input from Plesk
 * @param string $apiKey IONOS API key
 * @return array JSON response
 */
    function dnsInterface(string $input, string $apiKey): array {
        global $handle;
	    fwrite($handle, "dnsI: ENTRY \n");
	    fwrite($handle, "dnsI: apiKey: $apiKey \n");
	    fwrite($handle, "dnsI: input: $input \n");

        $input = json_decode($input, true);

        try {
            // Skip if no API key
            if (empty($apiKey)) {
                logError("No API key provided");
                return ['error' => 'No API key provided', 'code' => 400];
            }

            // Validate COMMAND and do initial processing of it

            $command = $input['COMMAND'];
            $supportedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'SOA', 'SRV', 'TXT', 'CAA', 'TLSA', 'SMIMEA', 'SSHFP', 'DS', 'HTTPS', 'SVCB', 'CERT', 'URI', 'RP', 'LOC', 'OPENPGPKEY'];
            $requiredFields = [];

	        fwrite($handle, "dnsI: Validating Command: $command \n");

            switch ($command ?? '') {
                case 'dns_record_create': // Use NEW_ values
                    $domainName = $input['NEW_DOMAIN_NAME'] ?? '';
                    $requiredFields = array_merge($requiredFields, ['NEW_DOMAIN_NAME', 'NEW_HOSTNAME', 'NEW_TYPE', 'NEW_VALUE']);
                    // Ensure there is no existing record with same name/type/value

                    break;
                case 'dns_record_update': // Use both OLD_ and NEW_ values
                    $domainName = $input['OLD_DOMAIN_NAME'] ?? '';
                    $requiredFields = array_merge($requiredFields, ['OLD_DOMAIN_NAME', 'OLD_HOSTNAME', 'OLD_TYPE', 'OLD_VALUE']);
                    $requiredFields = array_merge($requiredFields, ['NEW_DOMAIN_NAME', 'NEW_HOSTNAME', 'NEW_TYPE', 'NEW_VALUE']);
                    // Find existing record using OLD_ values
                    // If OLD_TYPE ≠ NEW_TYPE (we know registrar = Plesk.old), Delete then Create;
                    break;
                case 'dns_record_delete': // Use OLD_ values
                    $domainName = $input['OLD_DOMAIN_NAME'] ?? '';
                    $requiredFields = array_merge($requiredFields, ['OLD_DOMAIN_NAME', 'OLD_HOSTNAME', 'OLD_TYPE', 'OLD_VALUE']);
                    // Find existing record
                    break;
                default:
                    logError("Invalid or missing COMMAND");
                    return ['error' => 'Invalid or missing COMMAND', 'code' => 400];
            }

            // Validate required fields

	        fwrite($handle, "dnsI: Validating required fields 0 \n");
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    logError("Missing or empty required field: $field");
                    return ['error' => "Missing or empty required field: $field", 'code' => 400];
                }
            }

	        fwrite($handle, "dnsI: Validating required fields 1 \n");
            if ($command === 'dns_record_create' || $command === 'dns_record_update') {
                if (!in_array($input['NEW_TYPE'], $supportedTypes)) {
                    logError("Invalid NEW_TYPE: {$input['NEW_TYPE']}");
                    return ['error' => "Invalid NEW_TYPE: {$input['NEW_TYPE']}", 'code' => 400];
                }
            }

            fwrite($handle, "dnsI: Validating required fields 2 \n");
            if ($command === 'dns_record_update' || $command === 'dns_record_delete') {
                if (!in_array($input['OLD_TYPE'], $supportedTypes)) {
                    logError("Invalid OLD_TYPE: {$input['OLD_TYPE']}");
                    return ['error' => "Invalid OLD_TYPE: {$input['OLD_TYPE']}", 'code' => 400];
                }
            }

            fwrite($handle, "dnsI: Get ZoneID $domainName \n");
            fwrite($handle, "dnsI: Get ZoneID $apiKey \n");
            $zoneResponse = GetZoneId($domainName, $apiKey);  // Find zoneId
            logDebug("Zone response", $zoneResponse);
            
	        fwrite($handle, "dnsI: Zone response: $zoneResponse \n");

            if (isset($zoneResponse['error'])) {
                return $zoneResponse;  // fail out passing the error back to caller
            }

            $zoneId = $zoneResponse['value'];

            // Find existing record. Depending on Command we may or may not want it to exist.
            $recordResponse = GetRecordId($zoneId, $input['OLD_HOSTNAME'], $input['OLD_TYPE'], $input['OLD_VALUE'], $apiKey);
            logDebug("Record response", $recordResponse);

	        fwrite($handle, "dnsI: Record response: $recordResponse \n");

            /*
            if (isset($recordResponse['error'])) {
                return $recordResponse;  // fail out passing the error back to caller
            }
            */

            $recordId = $recordResponse['value'];

	        fwrite($handle, "dnsI: Record ID: $recordId \n");

            // Handle CRUD operations

            switch ($command) {
                case 'dns_record_create':
                    if ($recordId) {
                        logError("Record already exists for creation: {$input['NEW_HOSTNAME']}, {$input['NEW_TYPE']}, {$input['NEW_VALUE']}");
                        return ['error' => 'Record already exists', 'code' => 400];
                    }

                    // Create new record
                    $record = [
                        'name' => $input['NEW_HOSTNAME'],
                        'type' => $input['NEW_TYPE'],
                        'content' => $input['NEW_VALUE'],
                        'ttl' => isset($input['NEW_TTL']) ? (int)$input['NEW_TTL'] : 3600,
                        'prio' => isset($input['NEW_PRIO']) ? (int)$input['NEW_PRIO'] : 0,
                        'disabled' => false
                    ];

                    $response = IonosApiRequest('POST', "zones/$zoneId/records", $apiKey, [$record]);
                    fwrite($handle, "dnsI: Create Response: $response \n");
                    if (isset($response['error'])) {
                        return ['error' => $response['error'], 'code' => $response['code']];
                    }
                    logDebug("Record created", $response['body']);
                    return ['status' => 'success', 'code' => 201, 'data' => $response['body']];

                case 'dns_record_delete':
                    if (!$recordId) {
                        logError("Record not found for deletion: {$input['OLD_HOSTNAME']}, {$input['OLD_TYPE']}, {$input['OLD_VALUE']}");
                        return ['error' => 'Record not found', 'code' => 404];
                    }

                    $response = IonosApiRequest('DELETE', "zones/$zoneId/records/$recordId", $apiKey);
                    fwrite($handle, "dnsI: Delete Response: $response \n");
                    if (isset($response['error'])) {
                        return ['error' => $response['error'], 'code' => $response['code']];
                    }
                    logDebug("Record deleted", ['recordId' => $recordId]);
                    return ['status' => 'success', 'code' => 200, 'data' => []];

                case 'dns_record_update':
                    if (!$recordId) {
                        logError("Record not found for update: {$input['OLD_HOSTNAME']}, {$input['OLD_TYPE']}, {$input['OLD_VALUE']}");
                        return ['error' => 'Record not found', 'code' => 404];
                    }

                    // Handle Update
                    if ($input['OLD_TYPE'] !== $input['NEW_TYPE']) {
                        // Type change: Delete then Create
                        $deleteResponse = IonosApiRequest('DELETE', "zones/$zoneId/records/$recordId", $apiKey);
                        fwrite($handle, "dnsI: Update <> ResponseD: $deleteResponse \n");
                        if (isset($deleteResponse['error'])) {
                            return ['error' => $deleteResponse['error'], 'code' => $deleteResponse['code']];
                        }
                        logDebug("Record deleted for type change", ['recordId' => $recordId]);

                        $record = [
                            'name' => $input['NEW_HOSTNAME'],
                            'type' => $input['NEW_TYPE'],
                            'content' => $input['NEW_VALUE'],
                            'ttl' => isset($input['NEW_TTL']) ? (int)$input['NEW_TTL'] : 3600,
                            'prio' => isset($input['NEW_PRIO']) ? (int)$input['NEW_PRIO'] : 0,
                            'disabled' => false
                        ];
                        $response = IonosApiRequest('POST', "zones/$zoneId/records", $apiKey, [$record]);
                        fwrite($handle, "dnsI: Update <> ResponseC: $response \n");
                        if (isset($response['error'])) {
                            return ['error' => $response['error'], 'code' => $response['code']];
                        }
                        logDebug("Record created for type change", $response['body']);
                        return ['status' => 'success', 'code' => 201, 'data' => $response['body']];
                    }

                    // Normal Update
                    $record = [
                        'content' => $input['NEW_VALUE'],
                        'ttl' => isset($input['NEW_TTL']) ? (int)$input['NEW_TTL'] : 3600,
                        'prio' => isset($input['NEW_PRIO']) ? (int)$input['NEW_PRIO'] : 0,
                        'disabled' => false
                    ];
                    $response = IonosApiRequest('PUT', "zones/$zoneId/records/$recordId", $apiKey, $record);
                    fwrite($handle, "dnsI: Create Response: $response \n");
                    if (isset($response['error'])) {
                        return ['error' => $response['error'], 'code' => $response['code']];
                    }
                    logDebug("Record updated", $response['body']);
                    return ['status' => 'success', 'code' => 200, 'data' => $response['body']];

                default:
                    logError("Unhandled COMMAND: $command"); // Should not reach here due to earlier validation
                    return ['error' => 'Unhandled COMMAND', 'code' => 400];
            }
        
        } catch (Exception $e) {
            $errMessage = $e->getMessage();
	        fwrite($handle, "dnsI: Unexpected error: $errMessage \n");
            logError("Unexpected error: " . $errMessage);
            return ['error' => 'Internal server error', 'code' => 500];
        }
    }

// Example usage (for testing):
/*
$input = [
    'COMMAND' => 'dns_record_create',
    'NEW_DOMAIN_NAME' => 'simplesimonssolutions.com',
    'NEW_HOSTNAME' => '_acme-challenge.simplesimonssolutions.com.',
    'NEW_TYPE' => 'TXT',
    'NEW_VALUE' => 'idD60bep2W-PvmLIKWXiJXcQU-tzlhCUns5qW5eDxls',
];
$apiKey = 'your-api-key-here';
*/
// $argv[1] = input json $argv[2] = apiKey

fwrite ($handle, "*** Invoking dnsInterface $argc \n");
fwrite ($handle, "*** Inp: $argv[1] \n");
fwrite ($handle, "*** Key: $argv[2] \n");
fwrite ($handle, "*** 3rd: $argv[3] \n");
$result = dnsInterface($argv[1], $argv[2]);
echo $result;
fwrite ($handle, "***** Result: $result \n");

if (DEBUGGING) {
    $output = ob_get_clean(); // Get the buffered output and clear the buffer
    fwrite($handle, "***** Output buffer *****\n");
    fwrite($handle, $output); // Write the captured output to the file
    fclose($handle); // Close the file handle
}
