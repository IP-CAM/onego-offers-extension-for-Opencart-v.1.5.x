<?php
// do not change or remove this
define('ONEGO_EXTENSION_VERSION', '0.9.8');

$oneGoConfig = array();

// transaction timeout, in minutes
$oneGoConfig['transactionTTL'] = 15;

// seconds until transaction expiration, when AJAX transaction refresh will be triggered, to prevent it from expiration
// set 0 to disable
$oneGoConfig['transactionRefreshIn'] = 15;

// itemCode for shipping
$oneGoConfig['shippingCode'] = 'shipping';

// Opencart order statuses on which to apply OneGo transaction/end operations (CONFIRM or CANCEL)
$oneGoConfig['confirmOnOrderStatus'] = array(5, 15, 3);
$oneGoConfig['cancelOnOrderStatus'] = array(7, 9, 13, 8, 14, 10, 11, 12, 16);

// delayed transaction timeout, in hours
$oneGoConfig['delayedTransactionTTL'] = 2400; // 100 days

// itemCode prefix, added to cart items having no SKU specified
$oneGoConfig['cartItemCodePrefix'] = 'eshopitem_';

// OneGo API URL address
$oneGoConfig['apiURI'] = 'https://api.onego.com/pos/v1/';

// OneGo OAuth service base URI
$oneGoConfig['oAuthBaseURI'] = 'https://auth.onego.com/oauth2/';

// OneGo JS SDK host URI
$oneGoConfig['jsSdkURI'] = '//plugins.onego.com/webapp/v1/main.js';

$oneGoConfig['widgetTopOffset'] = '50';
$oneGoConfig['widgetFrozen'] = 'Y';

// HTTP connection timeout - seconds
$oneGoConfig['httpConnectionTimeout'] = 10;

$oneGoConfig['logFile'] = DIR_LOGS.'onego.log';

// show extension activity details in browser console
// ATTENTION: do not use on live e-shop, because revealing debug info may be a 
// security threat
$oneGoConfig['debugModeOn'] = false;

// log API calls to logFile (works only if debugModeOn=true)
$oneGoConfig['logAPICalls'] = true;
