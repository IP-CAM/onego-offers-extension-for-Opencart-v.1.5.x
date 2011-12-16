<div>
  <div class="cart-heading active"><?php echo $heading_title; ?></div>
  <div class="cart-content" id="onego" style="display: block;">
      
      <fieldset id="onego_panel">
        <legend><img src="catalog/view/theme/default/image/onego.png" alt="OneGo benefits" title="OneGo benefits" /></legend>
        
      <div class="onego_funds">
          <form action="<?php echo $onego_action ?>" method="post" id="onego_account">
              <table border="0" width="100%">
                  <tr>
                      <td rowspan="2" align="left" valign="top">
                          <div id="onego_authwidget_container">
                              <img src="catalog/view/theme/default/image/loading.gif" /> Checking OneGo user identity... <a href="<?php echo $onego_disable; ?>" id="onego_logout">Wish to log out?</a>
                          </div>
                          <a href="<?php echo $onego_disable; ?>" id="onego_logout" style="color: silver;">[logoff]</a>
                          <a href="<?php echo $onego_update ?>" style="color: silver;">[upd]</a>
                      </td>
                      <td align="right">
                          <?php
                          if (!empty($onego_applied)) {
                              foreach ($funds as $key => $fund) {
                                  $disabled = $fund['amount'] > 0 ? '' : ' disabled="disabled"';
                                  $st = $fund['is_used'] ? ' checked="checked"' : '';
                                  echo '<label for="onego_funds_'.$key.'">'.$fund['title'].'</label> ';
                                  echo '<input type="hidden" name="use_onego_funds['.$key.']" value="n" />';
                                  echo '<input type="checkbox" name="use_onego_funds['.$key.']" class="onego_funds" id="onego_funds_'.$key.'" value="y"'.$disabled.$st.' /> ';

                              }
                              ?>

                              <?php
                          }
                          ?>
                      </td>
                      
                  </tr>
                  <tr>
                      <td align="right">
                          <?php if (!empty($onego_applied)) { ?>
                              <input type="text" name="onego_giftcard" id="onego_giftcard" style="width: 140px;" value="Gift Card Number" class="watermark" />
                              <input type="button" id="onego_giftcard_redeem" value="redeem" />
                          <?php } ?>
                      </td>
                  </tr>
              </table>
          </form>
      </div>
        
        </legend>
      </fieldset>
        
  </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    $('input.onego_funds').change(function(e){
        $('form#onego_account').submit();
    })
    $('#onego_giftcard_redeem').click(function(e){
        $('form#onego_account').submit();
    })
})
</script>