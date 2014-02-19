#!/usr/bin/env php
<?php

require_once __DIR__.'/hilink.class.php';

$hilink = HiLink::create();

echo "Host: ".$hilink->getHost().PHP_EOL;
echo "Online: ".$hilink->online().PHP_EOL;
echo "External IP: ".$hilink->getExternalIp().PHP_EOL;
echo "Traffic Stats: ".print_r($hilink->getTrafficStatistic(), true).PHP_EOL;

?>
