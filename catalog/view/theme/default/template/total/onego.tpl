<div>
  <div class="cart-heading active"><?php echo $heading_title; ?></div>
  <div class="cart-content" id="onego" style="display: block;">
      <div id="onego_panel">
          <div id="onego_panel_label"></div>
          <div id="onego_panel_content">
              <table border="0" width="100%">
                  <tr>
                      <td width="45%" align="center">
                          <div style="padding-bottom: 5px;">Already have your benefits account?</div>
                          <a href="<?php echo $onego_login; ?>" class="button"><span><?php echo $button_onego_login; ?></span></a>
                      </td>
                      <td width="10%" align="center">
                          or
                      </td>
                      <td width="45%" align="center">
                          <div style="padding-bottom: 5px;">Got a gift card?</div>
                          <input type="text" name="onego_giftcard" id="onego_giftcard" style="width: 140px;" class="onego_watermark" value="Gift Card Number" />
                          <a href="javascript:OneGoOpencart.redeemGiftCardAnonymous();" class="button"><span>Redeem</span></a>
                      </td>
                  </tr>
                  <tr>
                      <td colspan="3" align="center">
                          <hr />
                          <input type="checkbox" id="onego_agree" value="y" <?php echo !empty($onego_agreed) ? 'checked="checked"' : '' ?> />
                          - <label for="onego_agree"><?php echo $onego_agree_email_expose ?></label>
                      </td>
                  </tr>
              </table>
          </div>
    </div>
  </div>
</div>

<script type="text/javascript">
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
            OneGoOpencart.reloadPage();
        }
    });
})
</script>