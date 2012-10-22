<?php
// Heading
$_['heading_title']    = 'OneGo';

// Text
$_['text_total']       = 'Order Totals';
$_['text_success']     = 'OneGo extension settings have been modified.';

// Entry
$_['entry_status']     = 'Status:';
$_['entry_sort_order'] = 'Sort Order:';

$_['entry_clientId']  = 'API key: *';
$_['entry_clientSecret']  = 'API secret: *';
$_['entry_terminalId']  = 'Terminal ID: *';
$_['entry_transactionTTL']  = 'Transaction TTL: *';
$_['entry_delayedTransactionTTL']  = 'Delayed transaction timeout: *';
$_['entry_shippingCode']  = 'Shipping code: *';
$_['entry_widgetPosition']  = 'Widget position: *';
$_['entry_widgetTopOffset']  = 'Widget top offset: ';
$_['entry_widgetFrozen']  = 'Freeze widget: ';
$_['entry_confirmOnOrderStatus'] = 'Confirm OneGo transactions when order status is: *';
$_['entry_cancelOnOrderStatus'] = 'Cancel OneGo transactions when order status is: *';
$_['entry_checkCredentials'] = 'Check configuration:';

$_['entry_help_clientId']  = 'Your API key is generated in your OneGo business account in the "Point of Sale" section under "Manage".';
$_['entry_help_clientSecret']  = 'Your API secret is generated in your OneGo business account in the "Point of Sale" section under "Manage".';
$_['entry_help_terminalId']  = 'A unique identifier for your e-shop - you can use your store name here.';
$_['entry_help_transactionTTL']  = 'Cancels OneGo transactions when customers fail to complete purchase (in minutes).';
$_['entry_help_delayedTransactionTTL']  = 'Number of hours for an order to complete processing. OneGo transactions will be
    confirmed or canceled based upon process status. Ensure the amount of time is long enough to complete the process as
    customers are refunded any funds used from gift card balance when the delayed transaction expires.';
$_['entry_help_shippingCode']  = 'ItemCode value for shipping. Shipping is included in OneGo transaction cart entries list, to allow paying
    for shipping with buyer\'s gift card balance.';
$_['entry_help_widgetFrozen']  = 'Widget stays on the same place when user scrolls page.';
$_['entry_help_widgetTopOffset']  = 'How far the widget should be placed from the top of the page (in pixels).';
$_['entry_help_sortorder'] = 'Set value to show OneGo discounts above the \'Total\' row';
$_['entry_help_confirmOnOrderStatus'] = 'OneGo transactions are confirmed when an order reaches these statuses.
    Gift card balances are used and/or rewards are received.';
$_['entry_help_cancelOnOrderStatus'] = 'OneGo transactions are cancelled when an order reaches these statuses.
    Gift card balances are returned to the account and/or rewards are cancelled.';

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
$_['confirm_transaction_confirm'] = 'If you confirm this transaction, your customer\'s gift card balance will be used/rewarded.
    \nIMPORTANT: this action cannot be undone.';
$_['confirm_transaction_cancel'] = 'If you cancel this transaction, your customer\'s gift card balance will not be used/rewarded.
    \nIMPORTANT: this action cannot be undone.';
$_['confirm_transaction_delay'] = 'Delaying transactions ensures they do not expire before a customer\'s order is completely
    processed (either confirming or cancelling an order). This ensures that all gift card balance transactions are appropriately
    applied and offers marked as used.';
$_['delay_period'] = array(0 => 'Today', 1 => 'One day', 2 => 'Two days', 3 => 'Three days',
    5 => 'Five days', 7 => 'One week', 14 => 'Two weeks', 30 => '30 days', 60 => '60 days');
$_['delay_for_period'] = 'Delay for';
$_['button_delay_transaction'] = 'Delay';
$_['transaction_will_confirm'] = 'Transaction will be confirmed when you save your changes. Customer\'s gift card balance will be used and/or rewards received.';
$_['transaction_will_cancel'] = 'Transaction will be canceled when you save your changes. Customer\'s gift card balance will not be used and/or rewards received.';
$_['status_failure'] = 'failed';
$_['status_delayed'] = 'delayed until %s';
$_['status_confirmed'] = 'confirmed';
$_['status_canceled'] = 'canceled';
$_['status_expired'] = 'expired %s';

$_['check_credentials'] = 'Credential check';
$_['check_environment'] = 'Server environment check';
$_['check_opencart_version'] = 'Opencart version check';
$_['ok'] = 'OK';
$_['failed'] = 'FAILED';
$_['cannot_check'] = 'cannot be checked';
$_['version_unsupported'] = 'Your version of Opencart is not officially supported by this extension. While it may be partially or even fully functional, there may be incompatibilities. Use at your own risk.';

// Error
$_['error_permission'] = 'You do not have permission to modify this extension.';
$_['error_missing_required_fields'] = 'Error: please fill in all required fields';
$_['error_transaction_id_unknown'] = 'Unknown transaction ID';
$_['error_transaction_state_invalid'] = 'Invalid transaction state';
$_['error_connection_timeout'] = 'HTTP connection timeout, try again later';
$_['error_forbidden'] = 'Credentials are invalid or your account has been blocked';
$_['error_curl_missing'] = 'PHP CURL extension is not installed';
$_['error_javascript_not_loaded'] = 'Extension dependencies could not be loaded: check if extension is installed correctly and VQMOD is working.';

$_['onego_extension_info'] = <<<END
Onego.com allows merchants to create, manage, publish and analyze offers, rewards and reloadable gift cards.
This extension integrates your eShop with OneGo, allowing customers to use offers, accrue rewards, use gift card
balances and allows you to track use. <br />For more information on this extension and a demo visit:
<a href="http://developers.onego.com/eshop/about">developers.onego.com/eshop/about</a><br />
For more information on OneGo loyalty system visit: <a href="http://business.onego.com">business.onego.com</a>
END;

$_['menu_item_onego_rc'] = 'Redemption Codes';
$_['rc_heading_title'] = 'OneGo Redemption Codes';
$_['rc_upload'] = 'Add Redemption Codes for sale';
$_['rc_no_batches_available'] = 'No Redemption Codes are currently added for sale. Click "Add" button to import OneGo Redemption Codes and start selling them through your e-shop.';
$_['rc_error_codes_import_duplicate'] = 'Error: codes of multiple nominals are being added. Someone may be adding codes at the same time. Please click "cancel" and start again.';
$_['rc_upload_csv'] = 'Step 1. Upload CSV file(s) with OneGo Redemption Codes list';
$_['rc_upload_description'] = 'To add Redemption Codes for sale, you have to generate new codes on your merchant portal page and export them to a CSV file. Then upload this file (or several) below.';
$_['rc_error_cant_read_uploaded_file'] = 'Error: cannot read uploaded file';
$_['rc_error_csv_file_format'] = 'Error: CSV file invalid at row %s, expected format: redemption code number, code value, code is active';
$_['rc_error_csv_nominal'] = 'Error: codes value in this file (%s) differs from those added before. Please complete this operation and add codes of different value separately.';
$_['rc_codes_loaded'] = '%s codes loaded from file.';
$_['rc_no_codes_loaded'] = 'No new active codes found in this file.';
$_['rc_to_be_added'] = 'Redemption Codes to be added';
$_['rc_nominal'] = 'Code value';
$_['rc_number'] = 'Code number';
$_['rc_create_product'] = 'Step 2. Create/assign catalog product for Redemption Codes sale';
$_['rc_create_product_details'] = 'Create a new product in the catalog';
$_['rc_select_product'] = 'Select existing catalog product';
$_['rc_error_price'] = 'Product Price must be a number!';
$_['rc_error_generic'] = 'Error: there was an error adding Redemption Codes to the products.';
$_['rc_product_added'] = 'Redemption Codes were successfully added to stock and a product "%s" was created. It was not enabled yet - you should
    review it on <a href="%s">product\'s update page</a> and enable it to appear for sale.<br /><strong>Note:</strong> do not change quantity value for
    this product manually as it is automatically updated when adding/selling Redemption Codes.';
$_['rc_added_to_product'] = 'Redemption Codes were successfully added to catalog product "<a href="%s">%s</a>".<br /><strong>Note:</strong> do not change quantity value for
    this product manually as it is automatically updated when adding/selling Redemption Codes.';
$_['rc_sold_numbers'] = '%s of %s';
$_['rc_sold'] = 'Codes sold';
$_['button_enable'] = 'Enable';
$_['button_disable'] = 'Disable';
$_['rc_product_disabled'] = 'Product disabled.';
$_['rc_product_enabled'] = 'Product enabled.';
$_['rc_button_delete_codes'] = 'Delete unsold codes';
$_['rc_confirm_codes_delete'] = 'Are you sure you want to delete all unsold codes from this product?';
$_['rc_codes_deleted'] = 'Unsold codes removed from the product.';
$_['text_success_rc_disabled'] = 'Success: You have modified OneGo extension settings. OneGo extension was disabled, and so were your Redemption Code products.';
$_['extension_disabled'] = 'OneGo extension is disabled.';

$_['rc_email_greeting_text'] = 'Thank you for purchasing our Redemption Codes. Here they are:';
$_['rc_email_instructions'] = 'You can use these Redemption Codes on your next purchase, or give them to your friends as a gift.';
$_['rc_email_footer'] = 'Please reply to this email if you have any questions.';
$_['rc_email_subject'] = 'Your Redemption Codes';
$_['rc_download_filename'] = 'Your Redemption Codes (%s)';