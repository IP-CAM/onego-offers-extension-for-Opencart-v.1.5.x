<td><?php echo $onego_status ?></td>
<td>

<?php if (!empty($onego_status_undefined)) { ?>
<div class="onego_notification"><?php echo $onego_status_undefined ?></div>
<?php } else { ?>
    
<div id="onego_transaction_change_notification"></div>

<div id="onego_transaction_status">
    <?php echo $onego_status_success ?>

    <?php if (!empty($onego_btn_confirm)) { ?>
    &nbsp;&nbsp;<a id="btn_onego_confirm" class="button"><?php echo $onego_btn_confirm ?></a>
    <?php } ?>
    
    <?php if (!empty($onego_btn_cancel)) { ?>
    &nbsp;&nbsp;<a id="btn_onego_cancel" class="button"><?php echo $onego_btn_cancel ?></a>
    <?php } ?>
    
    <?php if (!empty($onego_btn_delay)) { ?>
    &nbsp;&nbsp;<?php echo $delay_for_period ?>
    <select name="onego_delay_duration" id="onego_delay_duration">
    <?php foreach ($delay_periods as $days => $daystext) { ?>
        <option value="<?php echo $days ?>" <?php echo $days == 7 ? 'selected="selected"' : '' ?>><?php echo $daystext ?></option>
    <?php } ?>
    </select>
    <a id="btn_onego_delay" class="button"><?php echo $onego_btn_delay ?></a>
    <?php } ?>
           
</div>
    
    <?php if (!empty($onego_status_failure)) { ?>
    <div id="onego_transaction_status_error"><?php echo $onego_status_failure ?></div>
    <?php } ?>
    
<?php } ?>
    
    
<script type="text/javascript">
$(document).ready(function(){
    function reloadTransactionStatus()
    {
        OneGoOpencart.loadOrderStatus('<?php echo $token ?>', <?php echo $order_id ?>);
    }
    function endTransaction(action, params)
    {
        var params = params || {};
        params['order_id'] = <?php echo $order_id ?>;
        params['action'] = action;
        $.post(
            'index.php?route=total/onego/endtransaction&token=<?php echo $token ?>',
            params,
            function(data){
                if (data.error) {
                    alert('Error: '+data.error);
                }
                reloadTransactionStatus();
            },
            'json'
        );
    }
    function detectStatusChange()
    {
        if (!$('select[name=order_status_id]').length) {
            alert('OneGo extension seems to be not fully compatible with your version of Opencart and may not work as expected.');
        }
        <?php if (!empty($onego_allow_status_change)) { ?>
        var status = parseInt($('select[name=order_status_id]').val());
        var confirmStatuses = [<?php echo implode(', ', $confirm_statuses) ?>];
        var cancelStatuses = [<?php echo implode(', ', $cancel_statuses) ?>];
        var shouldConfirm = (jQuery.inArray(status, confirmStatuses) >= 0);
        var shouldCancel = (jQuery.inArray(status, cancelStatuses) >= 0);
        if (shouldConfirm) {
            return 'confirm';
        } else if (shouldCancel) {
            return 'cancel';
        }
        <?php } ?>
        return false;
    }
    function warnStatusChange()
    {
        <?php if (!empty($onego_allow_status_change)) { ?>
                
        var st = detectStatusChange();
        console.log('status', st);
        var shouldConfirm = (st == 'confirm');
        var shouldCancel = (st == 'cancel');
        if (shouldConfirm || shouldCancel) {
            if (shouldConfirm) {
                var notification = '<?php echo $status_will_confirm ?>';
            } else {
                var notification = '<?php echo $status_will_cancel ?>';
            }
            $('#onego_transaction_change_notification').html(notification);
            $('#onego_transaction_change_notification').show();
        } else {
            $('#onego_transaction_change_notification').hide();
            $('#onego_transaction_change_notification').html('');
        }
        
        
        <?php } else { ?>
            
        return false;
        
        <?php } ?>
    }
    
    $('#btn_onego_confirm').unbind('click').click(function(e){
        OneGoOpencart.setAsLoading($(this));
        if (confirm('<?php echo $confirm_confirm ?>')) {
            endTransaction('<?php echo OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM ?>');
        } else {
            OneGoOpencart.unsetAsLoading($(this));
        }
    });
    $('#btn_onego_cancel').unbind('click').click(function(e){
        OneGoOpencart.setAsLoading($(this));
        if (confirm('<?php echo $confirm_cancel ?>')) {
            endTransaction('<?php echo OneGoAPI_DTO_TransactionEndDto::STATUS_CANCEL ?>');
        } else {
            OneGoOpencart.unsetAsLoading($(this));
        }
    });
    $('#btn_onego_delay').unbind('click').click(function(e){
        OneGoOpencart.setAsLoading($(this));
        if (confirm('<?php echo $confirm_delay ?>')) {
            var params = { duration: $('#onego_delay_duration').val() };
            endTransaction('<?php echo OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY ?>', params);
        } else {
            OneGoOpencart.unsetAsLoading($(this));
        }
    });
    
    $('select[name=order_status_id]').unbind('change.onego').bind('change.onego', warnStatusChange);
    
    $('#button-history').unbind('click.onego').bind('click.onego', function(e){
        var st = detectStatusChange();
        var shouldConfirm = (st == 'confirm');
        var shouldCancel = (st == 'cancel');
        if (shouldConfirm) {
            endTransaction('<?php echo OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM ?>');
        } else if (shouldCancel) {
            endTransaction('<?php echo OneGoAPI_DTO_TransactionEndDto::STATUS_CANCEL ?>');
        }
    });
    
    warnStatusChange();
})
</script>

</td>