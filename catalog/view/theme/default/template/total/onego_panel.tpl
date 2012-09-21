<?php if (!empty($onego_warning)) { ?>
<div id="onego_warning" class="warning"><?php echo $onego_warning ?></div>
<?php } ?>

<?php if (!$isAjaxRequest) { // do not show OneGo panel in checkout page. Remove this condition check to restore ?>

<div id="onego_panel">
  <div id="onego_panel_label"></div>
  <div id="onego_panel_content">
      <form action="" method="post" id="onego_account">
          <input type="hidden" name="onego_cart_hash" id="onego_cart_hash" value="<?php echo $onego_modified_cart_hash ?>" />
          <?php if (empty($onego_user_authenticated)) { ?>
          <div id="onego_login_container">
              <div style="padding-bottom: 5px;"><?php echo $onego_login_invitation ?></div>
              <a href="<?php echo $onego_login_url; ?>" id="onego_login" class="button"><span><?php echo $onego_login_button; ?></span></a>
          </div>
          <?php } else { ?>
          <div id="onego_authwidget_container" class="onego-authwidget" data-textcolor="#000" data-linkcolor="#38B0E3" data-fontsize="12px" data-font="arial" data-height="40" data-width="350" data-text="<?php echo $authWidgetText ?>">
              <?php echo $authWidgetTextLoading ?> <img src="catalog/view/theme/default/image/loading.gif" />
          </div>
          <?php } ?>
          
          <?php if (empty($onego_transaction_started)) { ?>
          <div id="onego_rc_redeem_container">
              <div style="padding-bottom: 5px;"><?php echo $onego_rc_invitation ?></div>
              <div id="onego_rc_container">
                  <div>
                    <input type="text" name="onego_rc" id="onego_redeem_code_number" value="" autocomplete="off" />
                    <input type="text" id="onego_redeem_code_template" class="" value="XXXXX-XXXXX" />
                  </div>
              </div>
              <a href="#" class="button" id="onego_rc_redeem"><span><?php echo $onego_button_redeem ?></span></a>
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
                  }
                  ?>
              </div>
              <div class="onego_rc">
                  <div id="onego_rc_container">
                    <div>
                        <input type="text" name="onego_rc" id="onego_redeem_code_number" value="" autocomplete="off" />
                        <input type="text" id="onego_redeem_code_template" class="" value="XXXXX-XXXXX" />
                    </div>
                </div>
                  <input type="button" id="onego_rc_redeem" value="<?php echo $onego_button_redeem ?>" />
              </div>
          </div>
          <?php } ?>
          
          <div id="onego_panel_footer">
              <?php if (empty($onego_user_authenticated)) { ?>
              <hr />
              <input type="checkbox" id="onego_agree" value="y" <?php echo !empty($onego_agreed) ? 'checked="checked"' : '' ?> />
              - <label for="onego_agree"><?php echo $onego_agree_email_expose ?></label>
              <?php } ?>
          </div>
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
                OneGoOpencart.flashWarningBefore($('#onego_panel'), '<?php echo $onego_error_spend_prepaid ?>');
            }
            OneGoOpencart.unsetAsLoading($('#use_onego_funds'));
            cancelCheck($('#use_onego_funds'));
        }
    );
}

<?php } else { // when user is not authenticated ?>
    
$('#onego_agree').unbind().change(function(e){
    OneGoOpencart.setAsLoading($('#onego_agree'));
    $.ajax({
        url: OneGoOpencart.config.agreeRegisterUri,
        type: 'post',
        data: { 'agree': $(this).is(':checked') ? 1 : 0 },
        dataType: 'json',
        success: function() {
            <?php echo $js_page_reload_callback ?>();
        },
        error: function() {
            OneGoOpencart.unsetAsLoading($('#onego_agree'));
            cancelCheck($('#onego_agree'));
        }
    });
})

$('#onego_login').unbind().click(function(e){
    e.preventDefault();
    OneGoOpencart.setAsLoading($('#onego_login'));
    OneGoOpencart.promptLogin(
            <?php echo $js_page_reload_callback ?>, 
            function() { 
                OneGoOpencart.unsetAsLoading($('#onego_login')) 
            }
   );
});
<?php } ?>

$('#onego_rc_redeem').unbind('click').click(function(e) {
    e.preventDefault();
    
    OneGoOpencart.setAsLoading($('#onego_rc_redeem'));
    
    var redeemCode = $('#onego_redeem_code_number').val();
    if (!redeemCode.length) {
        $('#onego_redeem_code_number').focus();
        OneGoOpencart.unsetAsLoading($('#onego_rc_redeem'));
        return false;
    } else {
        OneGoOpencart.useRedeemCode(
            redeemCode,
            function() {
                <?php echo $js_page_reload_callback ?>();
            },
            function(errorMessage) {
                OneGoOpencart.unsetAsLoading($('#onego_rc_redeem'));
                var errorMessage = errorMessage || '<?php echo $onego_redeem_failed ?>';
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
    $('#onego_redeem_code_template').focus(function(){
        $('#onego_redeem_code_number').focus();
    })
    var rcnumber = ''
    $('#onego_redeem_code_number').keyup(function(e){
        if (rcnumber != e.target.value) {
            val = e.target.value.toUpperCase();
            valCleaned = val.replace(/[^A-Z0-9]/g, '');
            strlen = valCleaned.length
            if (strlen > 10) {
                valCleaned = valCleaned.substr(0, 10);
            }
            tplval = valCleaned;
            while (tplval.length < 10) {
                tplval += 'X'
            }
            separator = /^[^A-Z0-9]$/;
            if (strlen > 5 && valCleaned.substr(0, 1) != '-' || separator.test(val.substr(5, 1))) {
                valCleaned = valCleaned.substr(0, 5) + '-' + valCleaned.substr(5)
            }
            e.target.value = rcnumber = valCleaned;

            $('#onego_redeem_code_template').val(tplval.substr(0, 5) + '-' + tplval.substr(5))
        }
    })

    <?php if (!empty($onego_success)) { ?>
    OneGoOpencart.flashSuccessBefore($('#onego_panel'), '<?php echo $onego_success ?>');
    <?php } ?>
})
</script>
