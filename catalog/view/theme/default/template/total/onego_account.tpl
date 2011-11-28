<div>
  <div class="cart-heading active"><?php echo $heading_title; ?></div>
  <div class="cart-content" id="onego" style="display: block;">
      
      <fieldset style="border: 1px solid #D8DEE1; margin: 0px 20px; border-radius: 5px;">
        <legend><img src="catalog/view/theme/default/image/onego.png" alt="OneGo benefits" title="OneGo benefits" /></legend>
        
      <div class="onego_funds">
          <form action="<?php echo $funds_action ?>" method="post" id="onego_funds_use">
              <table border="0" width="100%">
                  <tr>
                      <td>
                          <strong><?php echo $use_funds ?></strong>
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
                      <td align="right">
                          Logged in as <?php echo $onego_buyer ?>. <a href="<?php echo $onego_disable; ?>" id="onego_logout">Not you?</a>
                          <!--[<a href="<?php echo $onego_update ?>">upd</a>]-->
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
        $('form#onego_funds_use').submit();
    })
})

function logoutWidget()
{
    $('.onego_widget iframe').each(function(){
        $(this).attr('src', 'http://localhost/test/iframe.php');
    })
}
</script>