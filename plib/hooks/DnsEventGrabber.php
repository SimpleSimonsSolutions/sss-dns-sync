<?php
/*
    // /usr/local/psa/admin/plib/modules/sss-dns-sync/hooks/DnsEventGrabber.php
    // Copyright 2025. Simple Simon's Solutions. All rights reserved.
    // This is the interface from Plesk to our DNS Event Handler
    // I have tried all the published methods of getting the Events directly,
    // but they all fail because of not getting the correct data or not even being called.
    // This is a workaround to get the DNS events to the DNS Event Handler (provider's API).
    // The Plesk Event Manager is used to hook actionlog__event_dns_record_[create,update,delete]]
    // to this script, which then grabs the environment variables and passes them to DNSEventHandler.
    // Event cmdline:
    // /opt/psa/admin/bin/php /opt/psa/admin/plib/modules/sss-dns-sync/hooks/DnsEventGrabber.php 'dns_record_******'
*/

    require_once '/usr/local/psa/admin/plib/api-common/cu.php';
   // cu::initCLI();

    pm_Loader::registerAutoload();
    pm_Context::init('sss-dns-sync');

    const DEBUGGING = true;

    // Log errors
    function logError($message) {
        $log = "SSS DNS Sync (DNSEventGrabber) logError: $message";
        pm_Log::err($log);
    }
    // Log debugging info
    function logDebug($message, $vardata = '') {
        if (DEBUGGING) {
            $log = "SSS DNS Sync (DNSEventGrabber) logDebug: $message" . var_dump($vardata);
            pm_Log::err($log);
        }
    }

    if (DEBUGGING) {
        $filename = '/root/DnsEventGrabber.log';
        $handle = fopen($filename, 'a'); // 'w' for write (overwrites), 'a' for append
        if (!$handle) { exit (1); }

        ob_start(); // Start output buffering
        echo "\n************************** ";
        echo phpversion() . ' ***** ' . date('Y-m-d H:i:s', time()) . " < $argc >" . "\n";
        // echo 'getenv()' . "\n";
	    // var_dump(getenv()); // environment vars by name
	    // echo "\n\n";
    }

	$dnsValues = getenv();  // creates associative array
	// for each element in $dnsvalues, remove all whose name does not begin with old_ or new_ 
	$dnsValues = array_filter($dnsValues, function($k) {
    	$p = substr($k, 0, 4);
    	return ($p == 'OLD_') || ($p == 'NEW_'); // Only keep the old/new value pairs
    }, ARRAY_FILTER_USE_KEY);
    
    if (DEBUGGING) {
        echo '$dnsValues' . "\n";
        var_dump($dnsValues);
    }

    // If we have no values, exit
    If (count($dnsValues) == 0) {
        logError('No OLD_ or NEW_ values found in environment variables - exiting');
        exit(1);
    }

    // returns string or false - use this in production so we know whether to continue
	$dnsJson = json_encode($dnsValues, JSON_PRETTY_PRINT + JSON_THROW_ON_ERROR);
    // insert the command at the top of the JSON - and kill the original opening left curly
	$dnsJson = "{\n	\"COMMAND\": \"$argv[1]\"," . substr($dnsJson, 1);

    // Now call Dns provider's API Interface
    $DnsProvider = 'Ionos'; // Hardcoded for now - later we will get this from the module settings
    $apiKey = '.--INSERT API KEY HERE--';
    // /opt/psa/admin/plib/modules/sss-dns-sync/plib/ 
    $scriptPath = pm_Context::getPlibDir() . "library/DnsApis/{$DnsProvider}.php";
    // $scriptPath = "/opt/psa/admin/plib/modules/sss-dns-sync/plib/library/DnsApis/{$DnsProvider}.php";
    $cmd = "/opt/psa/admin/bin/php  -d /root/php-error.log $scriptPath '$dnsJson' '$apiKey'";

    if (DEBUGGING) {
        echo '$cmd' . "\n$cmd\n";
    }
    // $result = exec($cmd, $output, $retval);
    $result = passthru($cmd, $retval);

    if (DEBUGGING) {
        $output = ob_get_clean(); // Get the buffered output and clear the buffer
        echo "\n\$result: $result \n\$retval: $retval \n\$output from exec:\n";
        echo var_dump($output);
        fwrite($handle, $output); // Write the captured output to the file
        fclose($handle); // Close the file handle
    }
