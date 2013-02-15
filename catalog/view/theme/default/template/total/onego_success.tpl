<div id="onego_panel">
  <div id="onego_panel_label"></div>
  <div id="onego_panel_content">

    <?php if (!empty($onego_prepaid_received)) { ?>
      <?php echo !empty($onego_prepaid_received_pending) ? $onego_prepaid_received.' '.$onego_prepaid_received_pending : $onego_prepaid_received; ?>
      <br />
    <?php } ?>

    <?php if (!empty($onego_giftcard_balance)) echo $onego_giftcard_balance.'<br />'; ?>
      
    <?php
    if (!empty($onego_rc_funds)) {
        foreach ($onego_rc_funds as $key => $text) {
            echo $text.'<br />';
        }
    }
    ?>

    <?php echo !empty($onego_transaction_notice) ? '<br />'.$onego_transaction_notice.'<br />' : '' ?>

    <?php if (!empty($show_registration_invite)) { ?>
      <br />
      <?php echo $onego_anonymous_buyer_invitation ?>
      <br />
      <?php echo !empty($onego_registration_notification) ? $onego_registration_notification.'<br />' : '' ?>
      <br />

      <a id="onego_register_anonymous" class="button"><span><?php echo str_replace('OneGo', '<img src="catalog/view/theme/default/image/onego.png" style="vertical-align: -1px;" />', $onego_registration_button) ?></span></a>
    <?php } ?>
  </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    $('#onego_register_anonymous').click(function(e){
        e.preventDefault();
        OneGoWidget.getWidget().loadRegistrationPage('<?php echo $buyer_email ?>');
        OneGoWidget.show();
    })
})
</script>
