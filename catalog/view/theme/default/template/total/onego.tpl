<div>
  <div class="cart-heading active"><?php echo $heading_title; ?></div>
  <div class="cart-content" id="onego" style="display: block;">
      <div id="onego_panel">
          <div id="onego_panel_label"></div>
          <div id="onego_panel_content">
              <table border="0" width="100%">
                  <tr>
                      <td width="45%" align="center">
                          <div style="padding-bottom: 5px;">Do you already have OneGo account?</div>
                          <a href="<?php echo $onego_login; ?>" class="button"><span><?php echo $button_onego_login; ?></span></a>
                      </td>
                      <td width="10%" align="center">
                          or
                      </td>
                      <td width="45%" align="center">
                          <div style="padding-bottom: 5px;">Got a gift card?</div>
                          <input type="text" name="onego_giftcard" id="onego_giftcard" style="width: 140px;" class="onego_watermark" value="Gift Card Number" />
                          <a href="javascript:OneGo.opencart.redeemGiftCardAnonymous();" class="button"><span>Redeem</span></a>
                      </td>
                  </tr>
              </table>
          </div>
    </div>
  </div>
</div>