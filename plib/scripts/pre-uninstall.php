<?php
// Copyright 2025. Simple Simon's Solutions.

pm_Loader::registerAutoload();
pm_Context::init('sss-dns-sync');

// This script is used to disable the custom backend service for DNS synchronization.
// It is called during the uninstallation process to ensure that the service is properly disabled before the module is removed.
// It is important to ensure that the service is disabled to prevent any issues with DNS synchronization after the module is uninstalled.
// The script will call the disable method of the Modules_DnsSyncManager_CustomBackendService class,
// which will handle the disabling of the custom backend service.   
// If an exception occurs during the disabling process, it will be caught and an error message will be displayed.   
// If the disabling completes, the script will exit with the result of the call to disable.

try {
    ($result = new Modules_SssDnsSync_CustomBackendService())->disable();
} catch (pm_Exception $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}
exit($result);