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
                                  <div id="onego_authwidget_container" class="onego-authwidget" data-textcolor="#000" data-linkcolor="#38B0E3" data-fontsize="12px" data-font="arial" data-height="40" data-width="350" data-text="<?php echo $authWidgetText ?>">
                                      Benefits by: <img src="catalog/view/theme/default/image/loading.gif" />
                                  </div>
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
        <?php if (!empty($onego_scope_extended)) { ?>
        OneGoOpencart.processFundUsage(
            $(this),
            function(data, textStatus, jqXHR){
                if (data.error && data.error == 'OneGoAuthenticationRequiredException') {
                    OneGoOpencart.unsetAsLoading($('#use_onego_funds'));
                    cancelUseFundsCheck();
                    promptLoginForFundsUse();
                } else if (data.error) {
                    OneGoOpencart.flashWarningBefore($('#onego_panel'), data.message);
                    OneGoOpencart.unsetAsLoading($('#use_onego_funds'));
                } else {
                    OneGoOpencart.reloadPage();
                }
            }
        );
        <?php } else { ?>
        promptLoginForFundsUse();
        <?php } ?>
    })
    $('#onego_giftcard_redeem').click(function(e){
        $('form#onego_account').submit();
    })
})

function promptLoginForFundsUse()
{
    OneGoOpencart.promptLogin(
        function(){
            OneGoOpencart.processFundUsage(
                $('#use_onego_funds'),
                function(data, textStatus, jqXHR){
                    if (typeof data.status != 'undefined') {
                        OneGoOpencart.reloadPage();
                    }
                }
            );
        },
        cancelUseFundsCheck
    )
}

function cancelUseFundsCheck()
{
    $('#use_onego_funds').attr('checked', !$('#use_onego_funds').attr('checked'));
}
</script>