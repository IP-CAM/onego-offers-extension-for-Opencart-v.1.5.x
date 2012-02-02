<?php
// HTTP
define('HTTP_SERVER', 'http://opencart/admin/');
define('HTTP_CATALOG', 'http://opencart/');
define('HTTP_IMAGE', 'http://opencart/image/');

// HTTPS
define('HTTPS_SERVER', 'http://opencart/admin/');
define('HTTPS_IMAGE', 'http://opencart/image/');

// DIR
define('DIR_ROOT', dirname(__DIR__).'/');
define('DIR_APPLICATION', DIR_ROOT.'admin/');
define('DIR_SYSTEM', DIR_ROOT.'system/');
define('DIR_DATABASE', DIR_ROOT.'system/database/');
define('DIR_LANGUAGE', DIR_ROOT.'admin/language/');
define('DIR_TEMPLATE', DIR_ROOT.'admin/view/template/');
define('DIR_CONFIG', DIR_ROOT.'system/config/');
define('DIR_IMAGE', DIR_ROOT.'image/');
define('DIR_CACHE', DIR_ROOT.'system/cache/');
define('DIR_DOWNLOAD', DIR_ROOT.'download/');
define('DIR_LOGS', DIR_ROOT.'system/logs/');
define('DIR_CATALOG', DIR_ROOT.'catalog/');

// DB
define('DB_DRIVER', 'mysql');
define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'opencart');
define('DB_PASSWORD', 'opencart');
define('DB_DATABASE', 'opencart');
define('DB_PREFIX', '');
?>