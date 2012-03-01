<?php
// do not change or remove this
define('ONEGO_EXTENSION_VERSION', '0.9.4');

$oneGoConfig = array();

$oneGoConfig['autologinOn'] = true;

// transaction timeout, in minutes
$oneGoConfig['transactionTTL'] = 15;

// itemCode for shipping
$oneGoConfig['shippingCode'] = 'shipping';

// Opencart order statuses on which to apply OneGo transaction/end operations (CONFIRM or CANCEL)
$oneGoConfig['confirmOnOrderStatus'] = array(5);
$oneGoConfig['cancelOnOrderStatus'] = array(7, 9, 13, 8, 14, 10, 11, 12, 16);

// delayed transaction timeout, in hours
$oneGoConfig['delayedTransactionTTL'] = 2400; // 100 days

// itemCode prefix, added to cart items having no SKU specified
$oneGoConfig['cartItemCodePrefix'] = 'eshopitem_';

// OneGo API URL address
$oneGoConfig['apiURI'] = 'http://api.dev.onego.com/pos/v1/';

// OneGo authorization endpoint URL address
$oneGoConfig['authorizationURI'] = 'http://mobile-local.dev.onego.com/authorize';

// OneGo OAuth service URL address
$oneGoConfig['oAuthURI'] = 'http://oauth.dev.onego.cloud:8080/oauth';

$oneGoConfig['widgetShow'] = 'Y';
$oneGoConfig['widgetTopOffset'] = '50';
$oneGoConfig['widgetFrozen'] = 'Y';

// link to onego.com registration page for newly created buyers
$oneGoConfig['anonymousRegistrationURI'] = 'http://register.onego.com';

// HTTP connection timeout - seconds
$oneGoConfig['httpConnectionTimeout'] = 10;

$oneGoConfig['logFile'] = DIR_LOGS.'onego.log';

// show extension activity details in browser console
// ATTENTION: do not use on live e-shop, because revealing debug info may be a 
// security threat
$oneGoConfig['debugModeOn'] = true;

// log API calls to logFile (works only if debugModeOn=true)
$oneGoConfig['logAPICalls'] = true;