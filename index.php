#!/usr/bin/env php
<?php

require_once __DIR__.'/hilink.class.php';

$hilink = HiLink::create();

echo "Host: ".$hilink->getHost().PHP_EOL;
echo "Online: ".$hilink->online().PHP_EOL;
if (!$hilink->online()) {
	echo "Abort: not online".PHP_EOL;
	exit;
}

echo "External IP: ".$hilink->getExternalIp().PHP_EOL;
$hilink->printTraffic().PHP_EOL;
$hilink->printStatus().PHP_EOL;

if ($hilink->getServiceStatus() == 'enter PIN') {
	echo "Enter PIN: ".$hilink->enterPin(2681).PHP_EOL;
	$hilink->printStatus().PHP_EOL;
}

$hilink->printPinStatus();

//echo "Connect: ".$hilink->connect().PHP_EOL;
//sleep(5);
//$hilink->printStatus();
//sleep(10);
//echo "Disconnect: ".$hilink->disconnect().PHP_EOL;
//sleep(5);
//echo "isConnected: ".$hilink->isConnected().PHP_EOL;

//echo "Switch to 2G: ".$hilink->setConnectionType('2g').PHP_EOL;
//sleep(10);
//echo "Check: ".$hilink->getConnectionType().PHP_EOL;
//sleep(2);
//echo "Switch to 3G: ".$hilink->setConnectionType('3g').PHP_EOL;
//sleep(10);
//echo "Check: ".$hilink->getConnectionType().PHP_EOL;

$hilink->printDeviceInfo();










?>
