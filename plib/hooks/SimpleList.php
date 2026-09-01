<?php

// SimpleList.php

// Define namespace if needed
// namespace YourNamespace;

class Modules_SssDnsSync_SimpleList extends pm_Hook_SimpleList
{
    /**
     * This method is called to modify the data before it is displayed in the list.
     * It adds a new column for DNS sync status if the controller and action match.
     *
     * @param string $controller The name of the controller.
     * @param string $action The name of the action.
     * @param string $activeList The name of the active list.
     * @param array $data The data to be displayed in the list.
     * @return array The modified data with the new column added.
     */

    private function isDomainList($controller, $action)
    {
        return ($controller === 'domain' && $action === 'list')
            || ($controller === 'customer' && $action === 'domains')
            || ($controller === 'reseller' && $action === 'domains');
    }

    // This method is called to modify the data before it is displayed in the list.
    public function getData($controller, $action, $activeList, $data)
    {   
        If (false) { // ( isDomainList($controller, $action) ) {        
            foreach ($data as &$row) {
                 $row['DomainDNSsyncStatus'] = $activeList+"?"; // Replace with actual logic to determine DNS sync status
            }
        }
        return $data;
    }
    public function getColumns($controller, $action, $activeList)
    {
        If (false) { // ( isDomainList($controller, $action) ) { 
            // This method is called to add a new column for DNS sync status
            // Only add the column if we are in the right controller and action
            // This prevents unnecessary modifications in other contexts 
    
            return [
                'DomainDNSsyncStatus' => [
                    'title' => 'DNS Sync Status',
                    'width' => 100,
                    'sortable' => true,
                    'align' => 'center',
                    /* 'insertBefore' => -3, */
                ],
            ];
        }

        // If not the right controller/action, return an empty array
        // This prevents the addition of the column in other contexts
        // and avoids potential errors.
        return [];    
    }
    /** useful function to get domain object
     * $domain = new pm_Domain($domainId); /* short integer - not GUID */

    public function getListName()
    {
        // This method returns the name of the list
        // It is used to identify the list in the UI
        return 'DomainDNSsyncStatus';
    }
}