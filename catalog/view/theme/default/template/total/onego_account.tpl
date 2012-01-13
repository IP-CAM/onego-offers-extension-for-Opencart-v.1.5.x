<div>
  <div class="cart-heading active"><?php echo $heading_title; ?></div>
  <div class="cart-content" id="onego" style="display: block;">
      
      <div id="onego_panel">
          <div id="onego_panel_label"></div>
          <div id="onego_panel_content">
              <div class="onego_funds">
                  <form action="<?php echo $onego_action ?>" method="post" id="onego_account">
                      <table border="0" width="100%">
                          <tr>
                              <td rowspan="2" align="left" valign="top">
                                  <div id="onego_authwidget_container">
                                      <img src="catalog/view/theme/default/image/loading.gif" />
                                  </div>
                                  <script type="text/javascript">
                                      var authwidget = OneGo.plugins.authWidget('onego_authwidget_container', {
                                          'text-color': 'black',
                                          'link-color': '#38B0E3',
                                          'font-size': '12px',
                                          'font': 'arial',
                                          'height': 35
                                      });
                                  </script>
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
                                      <input type="text" name="onego_giftcard" id="onego_giftcard" style="width: 140px;" value="Gift Card Number" class="onego_watermark" />
                                      <input type="button" id="onego_giftcard_redeem" value="redeem" />
                                  <?php } ?>
                              </td>
                          </tr>
                      </table>
                  </form>
              </div>
          </div>
    </div>
  </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    $('#use_onego_funds').change(function(e){
        $('.warning').remove();
        <?php if (!empty($onego_scope_extended)) { 
            // user has sufficient scope, do not prompt login
            ?>
        OneGo.opencart.processFundUsage(
            $(this),
            function(data, textStatus, jqXHR){
                if (typeof data.error != 'undefined') {
                    OneGo.opencart.flashWarningBefore($('#onego_panel'), data.message);
                    OneGo.lib.unsetAsLoading($('#use_onego_funds'));
                } else {
                    location.href = location.href;
                }
            }
        );
        <?php } else { 
            // scope insufficient, prompt login before
            ?>
        OneGo.opencart.promptLogin(
            function(){
                OneGo.opencart.processFundUsage(
                    $('#use_onego_funds'),
                    function(data, textStatus, jqXHR){
                        if (typeof data.status != 'undefined') {
                            location.href = location.href;
                        }
                    }
                );
            },
            function(){
                $('#use_onego_funds').attr('checked', !$('#use_onego_funds').attr('checked'));
            }
        )
        <?php } ?>
    })
    $('#onego_giftcard_redeem').click(function(e){
        $('form#onego_account').submit();
    })
})
</script>