<?php
$oneGoConfig = array();

// transaction timeout, in seconds
$oneGoConfig['transactionTTL'] = 900;

// itemCode for shipping
$oneGoConfig['shippingCode'] = 'shipping';

// itemCode prefix, added to cart items having no SKU specified
$oneGoConfig['cartItemCodePrefix'] = 'eshopitem_';


$oneGoConfig['apiURI'] = 'http://api.dev.onego.com/pos/v1/';


$oneGoConfig['authorizationURI'] = 'http://mobile-local.dev.onego.com/authorize';


$oneGoConfig['oAuthURI'] = 'http://oauth.dev.onego.cloud:8080/oauth';


$oneGoConfig['authAgentURI'] = 'http://authwidget.dev.onego.com/agent';


$oneGoConfig['authWidgetURI'] = 'http://authwidget.dev.onego.com';
