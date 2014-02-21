#!/usr/bin/env php
<?php

require_once __DIR__.'/hilink.class.php';

$hilink = HiLink::create();

echo "Host: ".$hilink->getHost().PHP_EOL;
echo "Online: ".$hilink->online().PHP_EOL;
echo "External IP: ".$hilink->getExternalIp().PHP_EOL;
$hilink->printTraffic().PHP_EOL;
$hilink->printStatus().PHP_EOL;
echo "Switch to 2G: ".$hilink->setConnectionType('2g').PHP_EOL;
echo "Check: ".$hilink->getConnectionType().PHP_EOL;
echo "Switch to auto: ".$hilink->setConnectionType('auto').PHP_EOL;
echo "Check: ".$hilink->getConnectionType().PHP_EOL;
?>
