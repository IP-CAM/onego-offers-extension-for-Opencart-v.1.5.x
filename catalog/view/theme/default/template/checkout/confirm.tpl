<script type="text/javascript">
<!--
$('#onego_login').unbind().click(function(e){
    e.preventDefault();
    OneGoOpencart.promptLogin(OneGoOpencart.reloadCheckoutOrderInfo);
});
$('#use_onego_funds').unbind('change').change(function(e) {
    $('.warning').remove();
    <?php if (!empty($onego_scope_extended)) { ?>
    OneGoOpencart.processFundUsage(
        $(this),
        function(data, textStatus, jqXHR){
            if (data.error && data.error == 'OneGoAuthenticationRequiredException') {
                OneGoOpencart.reloadCheckoutOrderInfo();
            } else if (data.error) {
                OneGoOpencart.flashWarningBefore($('#onego_panel'), data.message);
                OneGoOpencart.unsetAsLoading($('#use_onego_funds'));
            } else {
                OneGoOpencart.reloadCheckoutOrderInfo();
            }
        }
    );
    <?php } else { ?>
    OneGoOpencart.promptLogin(
        function(){
            OneGoOpencart.processFundUsage(
                $('#use_onego_funds'),
                function(data, textStatus, jqXHR){
                    if (typeof data.status != 'undefined') {
                        OneGoOpencart.reloadCheckoutOrderInfo();
                        OneGoOpencart.reloadWidget();
                    }
                }
            );
        },
        function(){
            $('#use_onego_funds').attr('checked', !$('#use_onego_funds').attr('checked'));
        }
    )
    <?php } ?>
});
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
            OneGoOpencart.reloadCheckoutOrderInfo();
        }
    });
})
$('#onego_giftcard_redeem').unbind('click').click(function(e) {
    $.ajax({
        url: 'index.php?route=checkout/confirm', 
        type: 'post',
        data: $('#onego_account').serialize(),
        dataType: 'json',
        beforeSend: function() {
            $(this).attr('disabled', true);
            $('#onego_controls').html('<span class="wait">&nbsp;<img src="catalog/view/theme/default/image/loading.gif" alt="" /></span>');
        },	
        complete: function() {
            $(this).attr('disabled', false);
            $('.wait').remove();
        },			
        success: function(json) {
            $('.warning').remove();

            if (json['redirect']) {
                location = json['redirect'];
            }

            if (json['error']) {
                if (json['error']['warning']) {
                    $('#confirm .checkout-content').prepend('<div class="warning" style="display: none;">' + json['error']['warning'] + '</div>');

                    $('.warning').fadeIn('slow');
                }			
            } else {
                $('#confirm .checkout-content').html(json['output']);
            }
        }
    });
});
//-->
</script>


<div id="onego_controls" style="margin-bottom: 10px;">
    <div id="onego_panel">
          <div id="onego_panel_label"></div>
          <div id="onego_panel_content">
            <?php if (empty($onego_authenticated)) { ?>
              <table border="0" width="100%">
                  <tr>
                      <td width="45%" align="center">
                          <div style="padding-bottom: 5px;">Already have your benefits account?</div>
                          <a href="<?php echo $onego_login_url; ?>" id="onego_login" class="button"><span><?php echo $onego_login_button; ?></span></a>
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
            <?php } else { ?>

              <div class="onego_funds">
                  <form action="<?php echo $onego_action ?>" method="post" id="onego_account">
                      <div id="onego_authwidget_container" class="onego-authwidget" data-textcolor="#000" data-linkcolor="#38B0E3" data-fontsize="12px" data-font="arial" data-height="40" data-width="350" data-text="<?php echo $authWidgetText ?>">
                          <?php echo $authWidgetTextLoading ?> <img src="catalog/view/theme/default/image/loading.gif" />
                      </div>
                      <script type="text/javascript">
                      OneGo.plugins.init();
                      </script>
                      <div id="onego_funds_container">
                          <div class="onego_funds_available">
                              <?php
                              if (!empty($onego_funds)) {
                                  $disabled = $onego_funds['amount'] > 0 ? '' : ' disabled="disabled"';
                                  $st = $onego_funds['is_used'] ? ' checked="checked"' : '';
                                  echo '<label for="use_onego_funds">'.$onego_funds['title'].'</label> ';
                                  echo '<input type="checkbox" name="use_onego_funds" class="onego_funds" id="use_onego_funds" value="y"'.$disabled.$st.' /> ';
                              }
                              ?>
                          </div>
                          <div class="onego_giftcard">
                              <?php if (!empty($onego_applied)) { ?>
                                  <input type="text" name="onego_giftcard" id="onego_giftcard" style="width: 140px;" value="Gift Card Number" class="onego_watermark" />
                                  <input type="button" id="onego_giftcard_redeem" value="redeem" />
                              <?php } ?>
                          </div>
                      </div>
                      <div style="clear: both;"></div>
                  </form>
              </div>

            <?php } ?>
          </div>
    </div>
</div>


<div class="checkout-product">
  <table>
    <thead>
      <tr>
        <td class="name"><?php echo $column_name; ?></td>
        <td class="model"><?php echo $column_model; ?></td>
        <td class="quantity"><?php echo $column_quantity; ?></td>
        <td class="price"><?php echo $column_price; ?></td>
        <td class="total"><?php echo $column_total; ?></td>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $product) { ?>
      <tr>
        <td class="name"><a href="<?php echo $product['href']; ?>"><?php echo $product['name']; ?></a>
          <?php foreach ($product['option'] as $option) { ?>
          <br />
          &nbsp;<small> - <?php echo $option['name']; ?>: <?php echo $option['value']; ?></small>
          <?php } ?></td>
        <td class="model"><?php echo $product['model']; ?></td>
        <td class="quantity"><?php echo $product['quantity']; ?></td>
        <td class="price"><?php echo $product['price']; ?></td>
        <td class="total"><?php echo $product['total']; ?></td>
      </tr>
      <?php } ?>
      <?php foreach ($vouchers as $voucher) { ?>
      <tr>
        <td class="name"><?php echo $voucher['description']; ?></td>
        <td class="model"></td>
        <td class="quantity">1</td>
        <td class="price"><?php echo $voucher['amount']; ?></td>
        <td class="total"><?php echo $voucher['amount']; ?></td>
      </tr>
      <?php } ?>
    </tbody>
    <tfoot>
      <?php foreach ($totals as $total) { ?>
      <tr>
        <td colspan="4" class="price"><b><?php echo $total['title']; ?>:</b></td>
        <td class="total"><?php echo $total['text']; ?></td>
      </tr>
      <?php } ?>
    </tfoot>
  </table>
</div>
<div class="payment"><?php echo $payment; ?></div>