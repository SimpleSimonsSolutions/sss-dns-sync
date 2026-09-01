<?php
// /usr/local/psa/admin/plib/modules/sss-dns-sync/library/EventListener.php
// Copyright 2025. Simple Simon's Solutions. All rights reserved.
// Assistant: https://x.com/i/grok?conversation=1949223611530703142

class Modules_SssDnsSync_EventListener implements EventListener
{
    const DEBUGGING = true;

    // Log errors with GUIDs
    function logError($message) {
        $log = "SSS DNS Sync (EventListener) logError: $message";
        pm_Log::err($log);
    }
    // Log debugging info
    function logDebug($message, $vardata = '') {
        if (DEBUGGING) {
            $log = "SSS DNS Sync (EventListener): $message";
            pm_Log::err($log);
            pm_Log::err(var_dump($vardata));
            // pm_Log::err(pm_Log::backtrace($log));
        }
    }

    public function filterActions()  // Ensure we aren't bothered by things we don't care about
    {
    	logError("filterActions");
    
        return [
            'dns_record_create',
            'dns_record_update',
            'dns_record_delete',
            'domain_dns_update'
        ];
    }

    public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues)
    {
        logError("Handling event: $objectType, $objectId, $action");
        logDebug("Old Values: ", $oldValues);
        logDebug("New Values: ", $newValues);

        switch ($action) {
            case 'phys_hosting_delete' :
               // delete by $objectId or $oldValues
                break;
        }
    }
}
