<?php if (!empty($onego_warning)) { ?>
<div id="onego_warning" class="warning"><?php echo $onego_warning ?></div>
<?php } ?>

<?php if (!$isAjaxRequest) { // do not show OneGo panel in checkout page. Remove this condition check to restore ?>

<div id="onego_panel">
  <div id="onego_panel_content">
      <form action="" method="post" id="onego_account">
          <input type="hidden" name="onego_cart_hash" id="onego_cart_hash" value="<?php echo $onego_modified_cart_hash ?>" />
          <?php if (empty($onego_user_authenticated)) { ?>
          <div id="onego_login_container">
              <div><?php echo $lang->get('onego_text_see_offers') ?></div>
              <a href="#" id="onego_see_offers" class="button"><span><span class="onego_logo_white">&nbsp;</span></span></a>
          </div>
          <?php } else { ?>
          <div id="onego_authwidget_container" class="onego-authwidget" data-textcolor="#000" data-linkcolor="#38B0E3" data-fontsize="12px" data-font="arial" data-height="40" data-width="350" data-text="<?php echo $authWidgetText ?>">
              <?php echo $authWidgetTextLoading ?> <img src="catalog/view/theme/default/image/loading.gif" />
          </div>
          <?php } ?>
          
          <?php if (empty($onego_transaction_started)) { ?>
          <div id="onego_rc_redeem_container">
              <div><?php echo $onego_rc_invitation ?></div>
              <div id="onego_rc_container">
                  <div class="rc_inputs">
                      <div>
                        <input type="text" name="onego_rc" id="onego_redeem_code_number" value="" autocomplete="off" />
                        <input type="text" id="onego_redeem_code_template" value="XXXXX-XXXXX" />
                      </div>
                  </div>
                  <a href="#" class="button" id="onego_rc_redeem"><span><?php echo $onego_button_redeem ?></span></a>
              </div>
          </div>
          <?php } else { ?>
          <div id="onego_funds_container">
              <div class="onego_funds_available">
                  <?php
                  if (!empty($onego_funds)) {
                      $disabled = $onego_funds['amount'] > 0 ? '' : ' disabled="disabled"';
                      $st = $onego_prepaid_spent ? ' checked="checked"' : '';
                      echo '<label for="use_onego_funds">'.$onego_funds['title'].'</label> ';
                      echo '<input type="checkbox" name="use_onego_funds" class="onego_funds" id="use_onego_funds" value="y"'.$disabled.$st.' /> ';
                  } else {
                      echo $onego_rc_invitation;
                  }
                  ?>
              </div>
              <div id="onego_rc_container">
                  <div class="rc_inputs">
                      <div>
                        <input type="text" name="onego_rc" id="onego_redeem_code_number" value="" autocomplete="off" />
                        <input type="text" id="onego_redeem_code_template" value="XXXXX-XXXXX" />
                      </div>
                  </div>
                  <a href="#" class="button" id="onego_rc_redeem"><span><?php echo $onego_button_redeem ?></span></a>
              </div>
          </div>
          <?php } ?>
          <div style="clear: both;"></div>
      </form>
  </div>
</div>

<script type="text/javascript">
<?php
$js_page_reload_callback = $isAjaxRequest ? 'OneGoOpencart.reloadCheckoutOrderInfo' : 'OneGoOpencart.reloadPage';

if (!empty($onego_user_authenticated)) {
    ?>
$('#use_onego_funds').unbind('change').change(function(e) {
    $('.warning').remove();
    <?php if (!empty($onego_scope_sufficient)) { ?>
    spendPrepaid();
    <?php } else { ?>
    promptLoginForFundsUse();
    <?php } ?>
});

function promptLoginForFundsUse()
{
    OneGoOpencart.setAsLoading($('#use_onego_funds'));
    OneGoOpencart.promptLogin(
        spendPrepaid,
        function() { 
            cancelCheck($('#use_onego_funds'));
            OneGoOpencart.unsetAsLoading($('#use_onego_funds'));
        }
    )
}

function spendPrepaid()
{
    OneGoOpencart.setAsLoading($('#use_onego_funds'));
    OneGoOpencart.spendPrepaid(
        $('#use_onego_funds').is(':checked'),
        function(data) {
            <?php echo $js_page_reload_callback ?>();
        },
        function (errorMessage, error) {
            if (error && error == 'OneGoAuthenticationRequiredException') {
                promptLoginForFundsUse();
            } else if (errorMessage) {
                OneGoOpencart.flashWarningBefore($('#onego_panel'), errorMessage);
            } else {
                OneGoOpencart.flashWarningBefore($('#onego_panel'), '<?php echo OneGoUtils::escapeJsString($onego_error_spend_prepaid) ?>');
            }
            OneGoOpencart.unsetAsLoading($('#use_onego_funds'));
            cancelCheck($('#use_onego_funds'));
        }
    );
}
<?php } ?>

$('#onego_see_offers').unbind().click(function(e){
    e.preventDefault();
    OneGoWidget.show();
});

$('#onego_rc_redeem').unbind('click').click(function(e) {
    e.preventDefault();
    
    OneGoOpencart.setAsLoading($('#onego_rc_redeem'));
    
    var redemptionCode = $('#onego_redeem_code_number').val();
    if (!redemptionCode.length) {
        $('#onego_redeem_code_number').focus();
        OneGoOpencart.unsetAsLoading($('#onego_rc_redeem'));
        return false;
    } else {
        OneGoOpencart.useRedemptionCode(
            redemptionCode,
            function() {
                <?php echo $js_page_reload_callback ?>();
            },
            function(errorMessage) {
                OneGoOpencart.unsetAsLoading($('#onego_rc_redeem'));
                var errorMessage = errorMessage || '<?php echo OneGoUtils::escapeJsString($onego_redeem_failed) ?>';
                OneGoOpencart.flashWarningBefore($('#onego_panel'), errorMessage);
            },
            <?php echo $isAjaxRequest ? 'false' : 'true' ?>
        );
    }
});

function cancelCheck(checkboxElement)
{
    checkboxElement.attr('checked', !checkboxElement.attr('checked'));
}
</script>
<?php } ?>

<script type="text/javascript">
<?php if ($isAjaxRequest) { ?>
OneGo.plugins.init();
<?php } ?>

<?php if (!empty($onego_is_checkout_page)) { ?>
OneGoOpencart.catchOrderConfirmAction();
<?php } ?>

<?php if (!empty($enable_autorefresh)) { ?>
OneGoOpencart.setTransactionAutorefresh(<?php echo $enable_autorefresh[0]*1000 ?>, <?php echo $enable_autorefresh[1]*1000 ?>);
<?php } else if (!empty($disable_autorefresh)) { ?>
OneGoOpencart.resetTransactionAutorefresh();
<?php } ?>

$(document).ready(function(){
    OneGoOpencart.applyRedemptionCodeTemplate($('#onego_redeem_code_number'), $('#onego_redeem_code_template'));
    
    <?php if (!empty($onego_success)) { ?>
    OneGoOpencart.flashSuccessBefore($('#onego_panel'), '<?php echo OneGoUtils::escapeJsString($onego_success) ?>');
    <?php } ?>
})
</script>
