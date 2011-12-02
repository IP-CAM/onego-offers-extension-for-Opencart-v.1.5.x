<?php echo $receivables; ?>
<script type="text/javascript">
<!--
$("#onego_apply").fancybox({
    'width': 500,
    'height': 380,
    'autoScale': true,
    'autoDimensions': true,
    'transitionIn': 'none',
    'transitionOut': 'none',
    'type': 'iframe',
    'onClosed': function() {
        OneGo.opencart.reloadCheckoutOrderInfo();
    }
});
$('#onego_logout').unbind().click(function(e){
    e.preventDefault();
    $(this).after('<span class="wait">&nbsp;<img src="catalog/view/theme/default/image/loading.gif" alt="" /></span>');
    $(this).remove();
    OneGo.opencart.processLogoffDynamic();
})
$('#onego_account input.onego_funds').unbind('change').change(function(e) {
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
$('#onego_agree').unbind().change(function(e){
    $.ajax({
        url: 'index.php?route=total/onego/agree', 
        type: 'post',
        data: { 'agree': $(this).is(':checked') ? 1 : 0 },
        dataType: 'json',
        beforeSend: function() {
            $(this).attr('disabled', true);
        },	
        success: function() {
            $(this).removeAttr('disabled');
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

$(document).ready(function(){
    OneGo.decorator.apply();
})
//-->
</script>


<div id="onego_controls" style="margin-bottom: 10px;">
    <fieldset id="onego_panel">
        <legend><img src="catalog/view/theme/default/image/onego.png" alt="OneGo benefits" title="OneGo benefits" /></legend>
        <?php if (empty($onego_applied)) { ?>
        <ul style="margin: 0px; padding: 0px 20px;">
            <li>
                I already have my OneGo account - <a href="<?php echo $onego_login_url ?>" class="button" id="onego_apply"><span>Apply my benefits</span></a>
            </li>
            <li>
                I wish to receive OneGo rewards for the purchase and I agree that my e-mail address is exposed to OneGo: 
                <input type="checkbox" id="onego_agree" value="y" <?php echo !empty($onego_agreed) ? 'checked="checked"' : '' ?> />
            </li>
            <li>
                I wish to redeem my Gift Card:
                <input type="text" name="onego_giftcard" id="onego_giftcard" style="width: 140px;" class="watermark" value="Gift Card Number" />
                <input type="button" value="Redeem" onclick="OneGo.opencart.redeemGiftCardAnonymous();" />
            </li>
        </ul>

        <?php } else { ?>

          <div class="onego_funds">
              <form action="<?php echo $funds_action ?>" method="post" id="onego_account">
                  <table border="0" width="100%">
                      <tr>
                          <td align="left" rowspan="2">
                              Logged in as <?php echo $onego_buyer ?>. <a href="javascript:logoutOnego();" id="onego_logout">Not you?</a>
                          </td>
                          <td align="right">
                  <?php
                  if (!empty($funds)) {
                      foreach ($funds as $key => $fund) {
                          $disabled = $fund['amount'] > 0 ? '' : ' disabled="disabled"';
                          $st = $fund['is_used'] ? ' checked="checked"' : '';
                          echo '<label for="onego_funds_'.$key.'">'.$fund['title'].'</label> ';
                          echo '<input type="hidden" name="use_onego_funds['.$key.']" value="n" />';
                          echo '<input type="checkbox" name="use_onego_funds['.$key.']" class="onego_funds" id="onego_funds_'.$key.'" value="y"'.$disabled.$st.' /> ';

                      }
                      ?>

                      <?php
                  } else {
                      ?>
                  <em><?php echo $no_funds_available; ?></em>
                      <?php
                  }
                  ?>
                          </td>
                      </tr>
                      <tr>
                          <td align="right" valign="top">
                              <input type="text" name="onego_giftcard" id="onego_giftcard" style="width: 140px;" value="Gift Card Number" class="watermark" />
                              <input type="button" id="onego_giftcard_redeem" value="redeem" />
                          </td>
                      </tr>
                  </table>
              </form>
          </div>

        <?php } ?>
    </fieldset>
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