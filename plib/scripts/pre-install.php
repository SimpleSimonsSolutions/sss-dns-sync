<?php
// Copyright 2025. Simple Simon's Solutions.
/**
 * Make sure we have all of the necessary bits and pieces in place before we start.
 * We should ensure that we are called from Plesk and
 *  that there is not another dns-manager module already installed.
 *  If there is, we should exit with an error message.
 * Probably not needed, but it doesn't hurt to be thorough.
 * 
 *
 */

 
/*
if (pm_ProductInfo::isWindows()) {
    if (!file_exists(PRODUCT_ROOT . '\dns\bin\rndc.exe')) {
        echo "BIND DNS Server is not installed. To install Slave DNS Manager in Plesk for Windows, copy the \"rndc.exe\" file to the \"" . PRODUCT_ROOT . 'dns\bin\rndc.exe' . "\" folder.";
        exit(1);
    }
}
*/

