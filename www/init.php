<?php
require_once '../config/defs.php';

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/../lib/');
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('OS_');

define('URL', 'http://' . $_SERVER['HTTP_HOST']);
define('AP', dirname(__FILE__));