<td><?php echo $onego_status ?></td>
<td>

<?php if (!empty($onego_status_undefined)) { ?>
<div style="color: gray;"><?php echo $onego_status_undefined ?></div>
<?php } else { ?>
    
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
        <option value="<?php echo $days ?>"><?php echo $daystext ?></option>
    <?php } ?>
    </select>
    <a id="btn_onego_delay" class="button"><?php echo $onego_btn_delay ?></a>
    <?php } ?>
           
</div>
    
    <?php if (!empty($onego_status_failure)) { ?>
    <div id="onego_transaction_status_error" style="color: red;"><?php echo $onego_status_failure ?></div>
    <?php } ?>
    
<?php } ?>
    
    
<script type="text/javascript">
$(document).ready(function(){
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
                OneGoOpencart.loadOrderStatus('<?php echo $token ?>', <?php echo $order_id ?>);
            },
            'json'
        );
    }
    
    $('#btn_onego_confirm').unbind().click(function(e){
        OneGoOpencart.setAsLoading($(this));
        if (confirm('<?php echo $confirm_confirm ?>')) {
            endTransaction('<?php echo OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM ?>');
        } else {
            OneGoOpencart.unsetAsLoading($(this));
        }
    });
    $('#btn_onego_cancel').unbind().click(function(e){
        OneGoOpencart.setAsLoading($(this));
        if (confirm('<?php echo $confirm_cancel ?>')) {
            endTransaction('<?php echo OneGoAPI_DTO_TransactionEndDto::STATUS_CANCEL ?>');
        } else {
            OneGoOpencart.unsetAsLoading($(this));
        }
    });
    $('#btn_onego_delay').unbind().click(function(e){
        OneGoOpencart.setAsLoading($(this));
        if (confirm('<?php echo $confirm_delay ?>')) {
            var params = { duration: $('#onego_delay_duration').val() };
            endTransaction('<?php echo OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY ?>', params);
        } else {
            OneGoOpencart.unsetAsLoading($(this));
        }
    });
})
</script>

</td>