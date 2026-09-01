<?php
// Copyright 1999-2017. Parallels IP Holdings GmbH.
// Major! modifications Copyright 2025. Simple Simon's Solutions. All rights reserved.
// This file is part of the SSS-DNS-Sync module for Plesk.
// /usr/local/psa/admin/plib/modules/sss-dns-sync/scripts/dns-sync.php
// https://x.com/i/grok?conversation=1949223611530703142
require_once '/usr/local/psa/admin/plib/api-common/cu.php';
cu::initCLI();

pm_Loader::registerAutoload();
pm_Context::init('sss-dns-sync');

const DEBUGGING = true;

// Log errors
function logError($message) {
    $log = "SSS DNS Sync (dns-sync) logError: $message";
    pm_Log::err($log);
}
// Log debugging info
function logDebug($message, $vardata = '') {
    if (DEBUGGING) {
        $log = "SSS DNS Sync (dns-sync): $message";
        pm_Log::err($log "\n" . var_dump($vardata));
    }
}

require_once pm_Context::getPlibDir() . '/library/IonosApi.php';
$api = new IonosApi();

// Function to decode JSON input and handle errors
function decodeJson($rawinput) {
    $input = json_decode($rawinput, true);
    if (empty($input)) {
        logError("Empty JSON input provided");
        exit(0);
    }
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("Invalid JSON input: " . json_last_error_msg() . " RAWINPUT: -- $rawinput --");
        exit(1);
    }
    return $input;
}

// Read JSON input
$rawinput = file_get_contents('php://stdin');
logError("RawInput: " . $rawinput);

// Determine if input is a JSON array (full DNS zone update) or just a single DNS record
If (substr($rawinput, 0, 1) == '[') { // it's an array
    exit 0;  // we don't do arrays (the whole zone dump command)
    // parse the JSON input as an array
    $input = decodeJson($rawinput);
    $input = $input[0] ?? $input; // Remove the empty shell, and handle array or object input
    $zoneName = $input['zone']['name'] ?? '';
    $clientGuid = $input['client_guid'] ?? '';
    $domainGuid = $input['domain_guid'] ?? '';
    // need to research what else we'll do with the data
}

//  The main path through the script is a single DNS record update
if (substr($rawinput, 0, 1) != '{') { // it's not an object
    logError("Invalid JSON input: Expected an object, got: $rawinput");
    logDebug("Invalid JSON input: Expected an object, got: $rawinput");
    exit(1);
}

// parse the JSON input as an object
$input = decodeJson($rawinput, true); // This has an empty JSON shell, so it will return an array with one element

// Create some generally needed values;
logDebug("Input: ", $input);

$zoneName = $input['zone']['name'] ?? '';
$clientGuid = $input['client_guid'] ?? '';
$domainGuid = $input['domain_guid'] ?? '';


// Check supported commands
$command = $input['command'] ?? '';
if (!in_array($command, ['dns_record_create', 'dns_record_update', 'dns_record_delete'])) {
    logError("Unsupported command: <$command>", $clientGuid, $domainGuid );
    exit(1);
}


// Fetch IONOS API key
// CREATE cmd: plesk bin extension --exec sss-dns-sync -c "pm_Settings::set('ionos_api_key', 'your.prefix.secret');"
$ionosApiKey = pm_Settings::get('ionos_api_key') ?: 'your.prefix.secret'; // Fallback for testing
/* TODO: Make this code fit in the bigger multi-client scope. For now, it's hard-coded in IonosApi.php
if (empty($ionosApiKey) || $ionosApiKey === 'your.prefix.secret') {
    logError("IONOS API key not configured", $clientGuid, $domainGuid);
    exit(1);
}
*/

// ********************** PLESK ALLOWS A "UPDATE" OF RECORD TYPE !!
// ********************** Need to translate to delete/create for Ionos (and other?) APIs

// Process command
exit(0);

try {
    // Find zone ID
    $zoneId = null;
       $response = $api->IonosApiRequest('GET', 'zones', $ionosApiKey);
    if (isset($response['error'])) {
        logError("Zone fetch failed: " . $response['error'], $clientGuid, $domainGuid);
        exit(1);
    }

    $zones = $response['body']['data'] ?? [];
    foreach ($zones as $zone) {
        if ($zone['name'] === $zoneName) {
            $zoneId = $zone['id'];
            break;
        }
    }
    if (!$zoneId) {
        logError("Zone $zoneName not found", $clientGuid, $domainGuid);
        exit(1);
    }

    // Handle records
    $records = $input['zone']['soa']['rr'] ?? [];
    if (empty($records)) {
        logError("No records provided for $command", $clientGuid, $domainGuid);
        exit(1);
    }

    foreach ($records as $record) {
        $recordName = $record['host'] === '@' ? $zoneName : rtrim($record['host'], '.') . '.' . $zoneName;
        $recordType = $record['type'] ?? '';
        $recordValue = $record['value'] ?? '';

        // Find existing record
        $recordId = null;
        $response = $api->IonosApiRequest('GET', "zones/$zoneId/records", $ionosApiKey);
        if (isset($response['error'])) {
            logError("Record fetch failed: " . $response['error'], $clientGuid, $domainGuid);
            exit(1);
        }
        $existingRecords = $response['body']['data'] ?? [];
            foreach ($existingRecords as $existing) {
            if ($existing['name'] === $recordName && 
                $existing['type'] === $recordType && 
                ($command !== 'delete' || $existing['content'] === $recordValue)) {
                $recordId = $existing['id'];
                break;
            }
        }

        if ($command === 'create') {
            $payload = [
                'name' => $recordName,
                'type' => $recordType,
                'content' => $recordValue,
                'ttl' => $record['ttl'] ?? 3600,
                'disabled' => false,
            ];
            if ($record['opt'] && in_array($recordType, ['MX', 'SRV'])) {
                $payload['priority'] = (int)$record['opt'];
            }
            $url = $recordId ? "zones/$zoneId/records/$recordId" : "zones/$zoneId/records";
            $method = $recordId ? 'PUT' : 'POST';
            $response = $api->IonosApiRequest($method, $url, $ionosApiKey, $payload);
            if (isset($response['error']) || !in_array($response['code'], [200, 201])) {
                logError("Failed to create record $recordName ($recordType): " . ($response['error'] ?? $response['body']['message'] ?? 'Unknown error'), $clientGuid, $domainGuid);
                exit(1);
            }
        } elseif ($command === 'update') {
            if (!$recordId) {
                logError("Record $recordName ($recordType) not found for update", $clientGuid, $domainGuid);
                exit(1);
            }
            $payload = [
                'name' => $recordName,
                'type' => $recordType,
                'content' => $recordValue,
                'ttl' => $record['ttl'] ?? 3600,
                'disabled' => false,
            ];
            if ($record['opt'] && in_array($recordType, ['MX', 'SRV'])) {
                $payload['priority'] = (int)$record['opt'];
            }
            $response = $api->IonosApiRequest('PUT', "zones/$zoneId/records/$recordId", $ionosApiKey, $payload);
            if (isset($response['error']) || $response['code'] !== 200) {
                logError("Failed to update record $recordName ($recordType): " . ($response['error'] ?? $response['body']['message'] ?? 'Unknown error'), $clientGuid, $domainGuid);
                exit(1);
            }
        } elseif ($command === 'delete') {
            if (!$recordId) {
                logError("Record $recordName ($recordType) with value $recordValue not found for deletion", $clientGuid, $domainGuid);
                exit(1);
            }
            $response = $api->IonosApiRequest('DELETE', "zones/$zoneId/records/$recordId", $ionosApiKey);
            if (isset($response['error']) || $response['code'] !== 204) {
                logError("Failed to delete record $recordName ($recordType): " . ($response['error'] ?? $response['body']['message'] ?? 'Unknown error'), $clientGuid, $domainGuid);
                exit(1);
            }
        }
    }
} catch (Exception $e) {
    logError("IONOS API error: " . $e->getMessage(), $clientGuid, $domainGuid);
    exit(1);
}

// Success
exit(0);