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

echo PHP_EOL;

echo "External IP: ".$hilink->getExternalIp().PHP_EOL;
$hilink->printTraffic().PHP_EOL;
echo PHP_EOL;
$hilink->printStatus().PHP_EOL;

if ($hilink->getServiceStatus() == 'enter PIN') {
	echo "Enter PIN: ".$hilink->pinEnter(2681).PHP_EOL;
	sleep(2);
	$hilink->printStatus().PHP_EOL;
}

echo PHP_EOL;

$hilink->printPinStatus();

echo PHP_EOL;

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

echo PHP_EOL;

echo $hilink->getConnection();

//echo "Enable Autoconnect: ".$hilink->activateAutoconnect().PHP_EOL;
//echo "Disable Autoconnect: ".$hilink->deactivateAutoconnect().PHP_EOL;

echo PHP_EOL;

print_r($hilink->getApn());

echo PHP_EOL;

//print_r($hilink->createProfile('TestProfile', 'internet.eplus.de', 'eplus', 'internet')->asXML());
echo $hilink->listApn();


echo PHP_EOL;







?>
