<?php echo $header; ?>
<div id="content">
  <div class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
    <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
    <?php } ?>
  </div>
  <?php if ($error_warning) { ?>
  <div class="warning"><?php echo $error_warning; ?></div>
  <?php } ?>
  <div class="box">
    <div class="heading">
      <h1><img src="view/image/onego_icon.png" alt="" /> <?php echo $heading_title; ?></h1>
      <div class="buttons"><a onclick="$('#form').submit();" class="button"><span><?php echo $button_save; ?></span></a><a onclick="location = '<?php echo $cancel; ?>';" class="button"><span><?php echo $button_cancel; ?></span></a></div>
    </div>
    <div class="content">
      <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form">
        <div id="onego_extension_info">
          <?php echo $onego_extension_info ?>
        </div>
        <table class="form">
          <?php foreach ($onego_config_fields as $field => $row) { ?>
          <tr id="cfgRow_<?php echo $field ?>">
            <?php if (in_array('onego_'.$field, $invalid_fields)) { ?>
            <td style="color: red;"><?php echo $row['title']; ?></td>
            <?php } else { ?>
            <td><?php echo $row['title']; ?></td>
            <?php } ?>
            <td>
                <?php if (in_array($field, array('widgetFrozen'))) { ?>

                <input type="hidden" name="onego_<?php echo $field ?>" value="" />
                <input type="checkbox" name="onego_<?php echo $field ?>" id="cfgField_<?php echo $field ?>" value="Y" <?php echo $row['value'] == 'Y' ? ' checked="checked"' : '' ?> />
                <div style="display: inline; color: gray; margin-left: 10px;"><?php echo $row['help'] ?></div>
                
                <?php } else if (in_array($field, array('confirmOnOrderStatus', 'cancelOnOrderStatus'))) { ?>
                
                <div style="color: gray; margin-bottom: 5px;"><?php echo $row['help'] ?></div>
                <?php foreach ($order_statuses as $status) { ?>
                    <?php $st = in_array($status['order_status_id'], $row['value']) ? ' checked="true"' : '' ?>
                    <input type="checkbox" name="onego_<?php echo $field ?>[]" value="<?php echo $status['order_status_id'] ?>" id="<?php echo $field.'_'.$status['order_status_id'] ?>" <?php echo $st ?> class="orderstatus_<?php echo $status['order_status_id'] ?> orderstatus" />
                    <label for="<?php echo $field.'_'.$status['order_status_id'] ?>"><?php echo $status['name'] ?></label>&nbsp;
                <?php } ?>

                <?php } else if (in_array($field, array('checkCredentials'))) { ?>

                <input type="button" id="btn_onego_check" value="<?php echo $onego_button_check; ?>" />
                <div id="onego_check_results"></div>

                <?php } else { ?>
                
                <input type="text" name="onego_<?php echo $field ?>" id="cfgField_<?php echo $field ?>" value="<?php echo $row['value']; ?>" />
                <div style="display: inline; color: gray; margin-left: 10px;"><?php echo $row['help'] ?></div>
                
                <?php } ?>
            </td>
          </tr>
              <?php 
          } 
          ?>
          <tr>
            <td><?php echo $entry_status; ?></td>
            <td><select name="onego_status">
                <?php if ($onego_status) { ?>
                <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                <option value="0"><?php echo $text_disabled; ?></option>
                <?php } else { ?>
                <option value="1"><?php echo $text_enabled; ?></option>
                <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                <?php } ?>
              </select>
            </td>
          </tr>
          <tr>
            <td><?php echo $entry_sort_order; ?></td>
            <td>
                <input type="text" name="onego_sort_order" value="<?php echo $onego_sort_order; ?>" size="1" />
                <div style="display: inline; color: gray; margin-left: 10px;"><?php echo $onego_sortorder_text ?></div>
            </td>
          </tr>
        </table>
      </form>
    </div>
  </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    function checkScriptAvailability()
    {
        if (typeof OneGoOpencart == 'undefined') {
            $('#onego_check_results').html('<span class="onego_error"><?php echo $onego_error_js_missing ?></span>').slideDown();
            return false;
        }
        return true;
    }

    checkScriptAvailability();

    $('input.orderstatus').click(function(){
        if ($(this).is(':checked')) {
            $('input.orderstatus_'+$(this).val()).attr('checked', false);
            $(this).attr('checked', true);
        }
    })
    $('#btn_onego_check').click(function(){
        $('#onego_check_results').html('');
        if (checkScriptAvailability()) {
            OneGoOpencart.setAsLoading($('#btn_onego_check'));
            $.get(
                '<?php echo str_replace('&amp;', '&', $onego_check_uri) ?>&'+$('#form').serialize(),
                function(data){
                    $('#onego_check_results').html(data).slideDown();
                    OneGoOpencart.unsetAsLoading($('#btn_onego_check'));
                }
            );
        }
    })
})
</script>

<?php echo $footer; ?>