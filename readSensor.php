<?php

require_once("nrfPowerMonitor.php");

$powermon = new nRFPowerMonitor("4", "04", true);
$powermon->enableRelay();

echo "Read 1: \n";
echo "--------------------------------------\n";
var_dump($powermon->readData());

echo "Read 2: \n";
echo "--------------------------------------\n";
var_dump($powermon->readData());
