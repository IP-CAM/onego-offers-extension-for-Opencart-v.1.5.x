<div id="onego_panel">
  <div id="onego_panel_label"></div>
  <div id="onego_panel_content">
      <form action="" method="post" id="onego_account">
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
          <div id="onego_giftcardredeem_container">
              <div style="padding-bottom: 5px;"><?php echo $onego_vgc_invitation ?></div>
              <input type="text" name="onego_giftcard" id="onego_giftcard_number" style="width: 140px;" class="onego_watermark" value="<?php echo $onego_vgc_number ?>" autocomplete="off" />
              <a href="#" class="button" id="onego_giftcard_redeem"><span><?php echo $onego_button_redeem ?></span></a>
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
              <div class="onego_giftcard">
                  <input type="text" name="onego_giftcard" id="onego_giftcard_number" style="width: 140px;" value="<?php echo $onego_vgc_number ?>" class="onego_watermark" autocomplete="off" />
                  <input type="button" id="onego_giftcard_redeem" value="<?php echo $onego_button_redeem ?>" />
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
<?php if (!empty($onego_user_authenticated)) { ?>
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
    OneGoOpencart.promptLogin(
        spendPrepaid,
        cancelUseFundsCheck
    )
}

function spendPrepaid()
{
    OneGoOpencart.spendPrepaid(
        $('#use_onego_funds'),
        function(data) {
            <?php echo $js_page_reload_callback ?>();
        },
        function (errorMessage, error) {
            if (error && error == 'OneGoAuthenticationRequiredException') {
                cancelUseFundsCheck();
                promptLoginForFundsUse();
            } else if (errorMessage) {
                OneGoOpencart.flashWarningBefore($('#onego_panel'), errorMessage);
            } else {
                OneGoOpencart.flashWarningBefore($('#onego_panel'), '<?php echo $onego_error_spend_prepaid ?>');
            }
        }
    );
}

function cancelUseFundsCheck()
{
    $('#use_onego_funds').attr('checked', !$('#use_onego_funds').attr('checked'));
}

<?php } else { // when user is not authenticated ?>
    
$('#onego_agree').unbind().change(function(e){
    $.ajax({
        url: 'index.php?route=total/onego/agree', 
        type: 'post',
        data: { 'agree': $(this).is(':checked') ? 1 : 0 },
        dataType: 'json',
        beforeSend: function() {
            OneGoOpencart.setAsLoading($('#onego_agree'));
        },	
        success: function() {
            <?php echo $js_page_reload_callback ?>();
        }
    });
})

$('#onego_login').unbind().click(function(e){
    e.preventDefault();
    OneGoOpencart.promptLogin(<?php echo $js_page_reload_callback ?>);
    //OneGoOpencart.promptLogin2();
});
<?php } ?>

$('#onego_giftcard_redeem').unbind('click').click(function(e) {
    e.preventDefault();
    //OneGoOpencart.promptAnonymousGiftCardRedeem();
    
    var cardNumber = $('#onego_giftcard_number').val();
    if (!cardNumber.length || (cardNumber == '<?php echo $onego_vgc_number ?>')) {
        $('#onego_giftcard_number').focus();
        return false;
    } else {
        OneGoOpencart.redeemGiftCard(
            cardNumber,
            function() {
                <?php echo $js_page_reload_callback ?>();
            },
            function(errorMessage) {
                var errorMessage = errorMessage || '<?php echo $onego_redeem_failed ?>';
                OneGoOpencart.flashWarningBefore($('#onego_panel'), errorMessage);
            }
        );
    }
});

<?php if ($isAjaxRequest) { ?>
OneGo.plugins.init();
<?php } ?>
</script>
