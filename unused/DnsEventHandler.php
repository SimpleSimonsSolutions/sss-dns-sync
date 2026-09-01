<?php
    // /usr/local/psa/admin/plib/modules/sss-dns-sync/hooks/DnsEventHandler.php
    // Copyright 2025. Simple Simon's Solutions. All rights reserved.

class Modules_SssDnsSync_DnsEventHandler extends pm_Hook_Dns
{
    const DEBUGGING = true;

    // Log errors
    function logError($message) {
        $log = "SSS DNS Sync (DNSEventHandler) logError: $message";
        pm_Log::err($log);
    }
    // Log debugging info
    function logDebug($message, $vardata = '') {
        if (DEBUGGING) {
            $log = "SSS DNS Sync (DNSEventHandler) logDebug: $message" . var_dump($vardata);
            pm_Log::err($log);
        }
    }

    public function handleEvent($event, $data)
    {
        $supportedEvents = ['dns_record_add', 'dns_record_update', 'dns_record_remove'];
        if (!in_array($event, $supportedEvents)) {
            return;
        }

        $jsonInput = json_encode($this->mapEventToJson($event, $data));
        $scriptPath = pm_Context::getBasePath() . 'plib/scripts/dns-sync.php';

        logError("Handling event: $event");
        logDebug("Raw Data: ", $data);
        logDebug("JSON input: ", $jsonInput);

        exit(0); // Exit early if debugging
        
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open("php $scriptPath", $descriptors, $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], $jsonInput);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $returnCode = proc_close($process);
            if ($returnCode !== 0) {
                pm_Log::err("SSS DNS Sync script failed [Client GUID: {$data['client_guid']}] [Domain GUID: {$data['domain_guid']}]: $errors");
            }
        } else {
            pm_Log::err("Failed to execute SSS DNS Sync script [Client GUID: {$data['client_guid']}] [Domain GUID: {$data['domain_guid']}]");
        }
    }

    private function mapEventToJson($event, $data)
    {
        $commandMap = [
            'dns_record_add' => 'create',
            'dns_record_update' => 'update',
            'dns_record_remove' => 'delete',
        ];
        $command = $commandMap[$event] ?? 'create';
        $records = isset($data['records']) ? (array)$data['records'] : [[
            'host' => $data['hostname'] ?? '',
            'type' => $data['type'] ?? '',
            'value' => $data['value'] ?? '',
            'priority' => $data['priority'] ?? '',
            'ttl' => $data['ttl'] ?? 3600,
        ]];
        $json = [
            'command' => $command,
            'zone' => [
                'name' => $data['domain_name'] ?? '',
                'displayName' => $data['domain_name'] ?? '',
                'soa' => [
                    'rr' => array_map(function ($record) {
                        return [
                            'host' => $record['host'] ?? '@',
                            'displayHost' => $record['host'] ?? '@',
                            'type' => $record['type'] ?? '',
                            'opt' => $record['priority'] ?? '',
                            'value' => $record['value'] ?? '',
                            'displayValue' => $record['value'] ?? '',
                            'ttl' => $record['ttl'] ?? 3600,
                        ];
                    }, $records),
                ],
            ],
            'event_timestamp' => gmdate('c'),
            'plesk_instance' => [
                'version' => pm_ProductInfo::getVersion(),
                'id' => pm_Settings::get('instance_id') ?? 'plesk-001',
            ],
            'client_guid' => $data['client_guid'] ?? '',
            'domain_guid' => $data['domain_guid'] ?? '',
        ];
        return $json;
    }
}