<?php
// Copyright 1999-2017. Parallels IP Holdings GmbH.
// Modifications Copyright 2025. Simple Simon's Solutions. All rights reserved.
// This file is part of the SSS-DNS-Sync module for Plesk.
// https://docs.plesk.com/en-US/obsidian/extensions-guide/plesk-features-available-for-extensions/integrate-with-system-services/dns/thirdparty-dns-services.72158/#integration-script-input-parameters

pm_Loader::registerAutoload();
pm_Context::init('sss-dns-sync');

$jsonInput = file_get_contents('php://stdin');
$data = json_decode($jsonInput);
if (!is_array($data)) { // One or more tasks
    echo "Invalid json data input: $jsonInput\n";
    exit(1);
}

foreach ($data as $task) {
    $command = (string)$task->command;
    if (!in_array($command, ['create', 'update', 'delete'])) {
        continue;
    }   // Other values (eg. createPTRs, deletePTRs) are not supported by this script.

    $domain = substr((string)$task->zone->name, 0, -1);
    if (!$domain) {
        echo "Invalid zone name: {$task->zone->name}\n";
        continue;
    }

    $client = pm_Session::getClient(); // Get the client from the session -
    // except we're running outside of a session, asynchronously
    /*    
     * If the task is run asynchronously, we need to find the client by the domain name.
     * This is a workaround for the fact that pm_Session::getClient() 
     * does not work in CLI mode.
    if (!$client) {
        echo "No client found for zone: {$task->zone->name}\n";
        continue;
    }
     */

    $rndc = new Modules_SlaveDnsManager_Rndc();

    switch ($command) {
        case 'create':
            $rndc->addZone($domain);
            break;
        case 'update':
            $rndc->updateZone($domain);
            break;
        case 'delete':
            $rndc->deleteZone($domain);
            break;
    }
}
