<?php
$oneGoConfig = array();

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

// authAgent URL address
$oneGoConfig['authAgentURI'] = 'http://authwidget.dev.onego.com/agent';

// authWidget URL address
$oneGoConfig['authWidgetURI'] = 'http://authwidget.dev.onego.com';

$oneGoConfig['widgetShow'] = 'Y';
$oneGoConfig['widgetTopOffset'] = '50';
$oneGoConfig['widgetFrozen'] = 'Y';