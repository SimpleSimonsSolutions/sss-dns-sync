<?php
// Copyright 2025. Simple Simon's Solutions.

pm_Loader::registerAutoload();
pm_Context::init('sss-dns-sync');

// This script is used to enable the custom backend service for DNS synchronization.
// It is called during the installation process to ensure that the service is properly enabled for use.
// It is important to ensure that the service is enabled to ensure the module sees DNS changes.
// The script will call the enable method of the Modules_SssDnsSync_CustomBackendService class,
// which will handle the enabling of the custom backend service,
// setting the command in the database, and ensuring that the service is ready for use.
// The command is set to execute the dns-sync.php script with the module ID as a parameter.
// This allows the service to handle DNS synchronization tasks as needed.   
// If an exception occurs during the enabling process, it will be caught and an error message will be displayed.   
// If the enabling completes, the script will exit with the result of the call to enable.

// TODO: Create the SQL tables and populate dns-sync-vendors with initial data for DNS vendors.
try {
    (new Modules_SssDnsSync_CustomBackendService())->enable();
} catch (pm_Exception $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}
exit(0);
