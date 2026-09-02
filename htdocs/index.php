<?php
// Initialize Plesk's internal MVC application runner

pm_Context::init('sss-dns-sync');

$application = new pm_Application();
$application->run();
