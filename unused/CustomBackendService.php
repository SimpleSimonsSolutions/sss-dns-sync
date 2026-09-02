<?php
// Copyright 1999-2021. Plesk International GmbH. All rights reserved.
// Modifications Copyright 2025. Simple Simon's Solutions. All rights reserved.
// This file is part of the SSS-DNS-Sync module for Plesk.
/**
 * This class provides methods to manage the custom backend service for DNS synchronization.
 * It allows enabling the service and checking if it is enabled in the database.
 * It also checks if the script command is correctly set in the database.
 */


class Modules_SssDnsSync_CustomBackendService
{
    public function enable(): void
    {
        pm_ApiCli::call('server_dns', ['--enable-custom-backend', $this->getCommand()]);
        // No clue what/where these two calls actually display.
        // $this->_status->addInfo(lmsg('customBackendEnablingError', ['error' => e.message] ));
        // $this->view->message(lmsg('customBackendEnabled'));

    }

    public function disable(): void
    {
        pm_ApiCli::call('server_dns', ['--disable-custom-backend']);
    }

    /**
     * @param Zend_Db_Adapter_Abstract $db from pm_Bootstrap::getDbAdapter()
     * @return bool
     */

    public function isEnabled(Zend_Db_Adapter_Abstract $db): bool
    {
        $select = $db->select()
            ->from('ServiceNodeConfiguration', ['value']) /* should be 'true' */
            ->where("section='dnsConnector' AND name='custom'");
        // There is a 3rd row with name = 'plesk' and value = 'true' which is not used by us.
        $row = $db->fetchRow($select);
        return !empty($row['value']) && $this->checkScriptCommand($db);
    }


    private function checkScriptCommand(Zend_Db_Adapter_Abstract $db): bool
    {
        $select = $db->select()
            ->from('ServiceNodeConfiguration', ['value']) /* will be the existing command */
            ->where("section='dnsConnector' AND name='custom_script'");
        $row = $db->fetchRow($select);
        return isset($row['value']) && trim($row['value']) === $this->getCommand();
    }

    private function getCommand(): string
    {
        $moduleId = pm_Context::getModuleId();
        $fileManager = new pm_ServerFileManager();
        $productRoot = pm_ProductInfo::getProductRootDir();
        $cmd = $fileManager->joinPath($productRoot, 'bin', 'extension');
        if ( pm_ProductInfo::isWindows() ) { $cmd .= '.exe'; }
        return "\"{$cmd}\" --exec {$moduleId} dns-ionos.php";
    }
}
