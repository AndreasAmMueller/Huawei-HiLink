#!/usr/bin/env php
<?php

require_once __DIR__.'/hilink.class.php';

$hilink = HiLink::create();

echo "Host: ".$hilink->getHost().PHP_EOL;


?>
