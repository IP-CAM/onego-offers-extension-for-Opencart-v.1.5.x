<?php
// Heading
$_['heading_title']    = 'OneGo';

// Text
$_['text_total']       = 'Order Totals';
$_['text_success']     = 'Success: You have modified OneGo extension settings!';

// Entry
$_['entry_status']     = 'Status:';
$_['entry_sort_order'] = 'Sort Order:';

$_['entry_clientId']  = 'Client ID: *';
$_['entry_clientSecret']  = 'Client secret: *';
$_['entry_terminalId']  = 'Terminal ID: *';
$_['entry_transactionTTL']  = 'Transaction TTL: *';
$_['entry_delayedTransactionTTL']  = 'Delayed transaction timeout: *';
$_['entry_shippingCode']  = 'Shipping code: *';
$_['entry_widgetShow']  = 'Enable OneGo widget';
$_['entry_widgetPosition']  = 'Widget position: *';
$_['entry_widgetTopOffset']  = 'Widget top offset: ';
$_['entry_widgetFrozen']  = 'Widget frozen: ';
$_['entry_autologinOn']  = 'Autologin enabled';
$_['entry_confirmOnOrderStatus'] = 'Order status on which OneGo transaction gets confirmed: *';
$_['entry_cancelOnOrderStatus'] = 'Order status on which OneGo transaction gets canceled: *';
$_['entry_checkCredentials'] = 'Check configuration:';

$_['entry_help_clientId']  = 'Client ID assigned to you on OneGo merchant sign up.';
$_['entry_help_clientSecret']  = 'Client secret assigned to you on OneGo merchant sign up.';
$_['entry_help_terminalId']  = 'Terminal ID you have assigned for your e-shop at OneGo merchant portal.';
$_['entry_help_transactionTTL']  = '(in minutes) - Unfinished OneGo transaction timeout - 
    defines duration after which buyer\'s funds and offers reserved for the transaction 
    are to be released when he leaves your e-shop without completing purchase.';
$_['entry_help_delayedTransactionTTL']  = '(in hours) - Defines time in which orders will be processed, 
    so OneGo transaction can be confirmed or canceled. Set this value big enough because when
    transaction expires buyer receives back all the funds he spent for the order and can use them again.';
$_['entry_help_shippingCode']  = 'ItemCode value for shipping - must be unique and different 
    from any SKU of products sold on your e-shop. You will use this code to create offers with shipping
    as a reward, i.e. "free shipping" or "discount on shipping".';
$_['entry_help_autologinOn']  = 'Detect returning buyers (if they are logged in on the widget) 
    and automatically start transaction for them.';
$_['entry_help_widgetShow']  = 'Enable sliding-in OneGo widget, where buyer can view 
    his account details and your currently active offers.';
$_['entry_help_widgetFrozen']  = 'Widget stays on the same place when user scrolls page.';
$_['entry_help_widgetTopOffset']  = 'How far off the top should the widget be placed (in pixels).';
$_['entry_help_sortorder'] = 'Set a low enough value to appear buyer benefits before 
    "taxes" and "total" rows, best to set the same as for "sub-total".';
$_['entry_help_confirmOnOrderStatus'] = 'OneGo transactions are confirmed when order 
    reaches these statuses, buyer\'s benefits are used and/or he receives rewards.';
$_['entry_help_cancelOnOrderStatus'] = 'OneGo transactions are canceled when order 
    reaches these statuses, buyer\'s benefits reserved for transaction are released, 
    he does not receive rewards.';

$_['onego_status'] = 'OneGo transaction status:';
$_['onego_status_short'] = 'OneGo:';
$_['transaction_status_undefined'] = 'Not initiated';
$_['transaction_status_confirm'] = 'Confirmed on %s (buyer\'s benefits used/rewarded)';
$_['transaction_status_cancel'] = 'Canceled on %s (buyer\'s benefits not used/rewarded)';
$_['transaction_status_delayed'] = 'Delayed on %s, expires %s';
$_['transaction_status_expired'] = 'Expired on %s (buyer\'s benefits not used/rewarded)';
$_['transaction_operation_failed'] = 'Transaction operation failed: [%s] %s - %s';
$_['button_confirm_transaction'] = 'Confirm';
$_['button_cancel_transaction'] = 'Cancel';
$_['button_check_credentials'] = 'Check';
$_['confirm_transaction_confirm'] = 'If you confirm this transaction, buyer\\\'s '
    .'benefits will be used/rewarded.\nIMPORTANT: this action cannot be undone.';
$_['confirm_transaction_cancel'] = 'If you cancel this transaction, buyer\\\'s '
    .'benefits will not be used/rewarded.\nIMPORTANT: this action cannot be undone.';
$_['confirm_transaction_delay'] = 'By delaying transaction, it will not expire until '
    .'the end of period and all buyer\\\'s benefits used/received for this order will stay '
    .'reserved.\nTransaction delay period can be changed anytime later, until it expires '
    .'or is confirmed/canceled.';
$_['delay_period'] = array(0 => 'Today', 1 => 'One day', 2 => 'Two days', 3 => 'Three days',
    5 => 'Five days', 7 => 'One week', 14 => 'Two weeks', 30 => '30 days', 60 => '60 days');
$_['delay_for_period'] = 'Delay for';
$_['button_delay_transaction'] = 'Delay';
$_['transaction_will_confirm'] = 'Transaction will be confirmed when you click "Add History". Buyer\\\'s benefits will be used and/or rewards received.';
$_['transaction_will_cancel'] = 'Transaction will be canceled when you click "Add History". Buyer\\\'s benefits will not be used and/or rewards received.';
$_['status_failure'] = 'failed';
$_['status_delayed'] = 'delayed until %s';
$_['status_confirmed'] = 'confirmed';
$_['status_canceled'] = 'canceled';
$_['status_expired'] = 'expired %s';

$_['check_credentials'] = 'Credentials check';
$_['ok'] = 'OK';
$_['failed'] = 'FAILED';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify OneGo total!';
$_['error_missing_required_fields'] = 'Error: please fill in all required fields';
$_['error_transaction_id_unknown'] = 'Unknown transaction ID';
$_['error_transaction_state_invalid'] = 'Invalid transaction state';
$_['error_connection_timeout'] = 'HTTP connection timeout, try again later';
$_['error_forbidden'] = 'Invalid credentials and/or account blocked';