<?php
// do not change or remove this
define('ONEGO_EXTENSION_VERSION', '1.0.0b');

$oneGoConfig = array();

$oneGoConfig['debugModeOn'] = true;

$oneGoConfig['autologinOn'] = true;

// transaction timeout, in seconds
$oneGoConfig['transactionTTL'] = 900;

// itemCode for shipping
$oneGoConfig['shippingCode'] = 'shipping';

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