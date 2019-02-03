<?php

chdir(dirname('.'));

// Set localtime zone
date_default_timezone_set("America/Bogota");

// Memory limit
ini_set("memory_limit","1024M");

// Run application
require_once("vendor/autoload.php");

function ifdef($value, array $needle)
{
    return \Drone\Util\ArrayDimension::ifdef($needle, include 'config/global.config.php', $value);
}

$config = include "config/application.config.php";
$mvc = new Drone\Mvc\Application($config);
$mvc->run();