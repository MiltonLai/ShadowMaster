<?php
/**
 * Created by PhpStorm.
 * User: milton
 * Date: 2/14/19
 * Time: 11:59 AM
 */
define("DIR_BASE", __DIR__);
define("DIR_LOGS", DIR_BASE . DIRECTORY_SEPARATOR . 'logs');
define("DIR_WEB", DIR_BASE . DIRECTORY_SEPARATOR . 'webapp');

require 'config.php';
require 'apps/Autoloader.php';

new Server($config);
