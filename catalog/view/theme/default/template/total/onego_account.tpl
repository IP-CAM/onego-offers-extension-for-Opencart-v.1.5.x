<div>
  <div class="cart-heading active"><?php echo $heading_title; ?></div>
  <div class="cart-content-opened" id="onego">
      
      <div class="onego_funds">
          <form action="<?php echo $funds_action ?>" method="post" id="onego_funds_use">
              <strong><?php echo $use_funds ?>:</strong>
              <?php
              if (!empty($funds)) {
                  foreach ($funds as $key => $fund) {
                      $disabled = $fund['amount'] > 0 ? '' : ' disabled="disabled"';
                      $st = $fund['is_used'] ? ' checked="checked"' : '';
                      echo '<input type="hidden" name="use_onego_funds['.$key.']" value="n" />';
                      echo '<input type="checkbox" name="use_onego_funds['.$key.']" class="onego_funds" id="onego_funds_'.$key.'" value="y"'.$disabled.$st.' /> ';
                      echo '<label for="onego_funds_'.$key.'">'.$fund['title'].'</label>&nbsp;&nbsp;&nbsp;';
                  }
                  ?>

                  <?php
              } else {
                  ?>
              <em><?php echo $no_funds_available; ?></em>
                  <?php
              }
              ?>
          </form>
      </div>
      <br />
      
      <table border="1" style="display: none;">
          <tr>
              <td valign="top" width="50%">
                  <strong>opencart cart</strong><br />
                  <?php dbg($cart_products); ?>
              </td>
              <td valign="top" width="50%">
                  <strong>OneGo modified cart</strong><br />
                  <?php dbg($transaction->modifiedCart); ?>
              </td>
          </tr>
      </table>
      
    <a href="<?php echo $onego_update; ?>" class="button"><span><?php echo $button_update; ?></span></a>
    <a href="<?php echo $onego_disable; ?>" class="button"><span><?php echo $button_disable; ?></span></a>
        
  </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    $('input.onego_funds').change(function(e){
        $('form#onego_funds_use').submit();
    })
})
</script>