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
                          if (!empty($onego_funds)) {
                              $disabled = $onego_funds['amount'] > 0 ? '' : ' disabled="disabled"';
                              $st = $onego_funds['is_used'] ? ' checked="checked"' : '';
                              echo '<label for="use_onego_funds">'.$onego_funds['title'].'</label> ';
                              echo '<input type="checkbox" name="use_onego_funds" class="onego_funds" id="use_onego_funds" value="y"'.$disabled.$st.' /> ';
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
    $('#use_onego_funds').change(function(e){
        var isChecked = $(this).is(':checked');
        $.ajax({
            url: '<?php echo $onego_use_funds_url ?>', 
            type: 'post',
            data: { 'use_funds': isChecked },
            dataType: 'json',
            beforeSend: function(jqXHR, settings) {
                OneGo.lib.setAsLoading($('#use_onego_funds'));
            },
            complete: function(jqXHR, textStatus) {
                OneGo.lib.unsetAsLoading($('#use_onego_funds'));
            },
            success: function(data, textStatus, jqXHR) {
                console.dir({'res': data});
                if (typeof data.error != 'undefined') {
                    $('#use_onego_funds').attr('checked', !isChecked);
                } else {
                    $('#use_onego_funds').attr('checked', data.status == '1');
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                $('#use_onego_funds').attr('checked', !isChecked);
            }
        })
    })
    $('#onego_giftcard_redeem').click(function(e){
        $('form#onego_account').submit();
    })
})
</script>