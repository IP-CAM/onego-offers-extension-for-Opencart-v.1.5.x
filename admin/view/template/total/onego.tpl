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
      <h1><img src="view/image/total.png" alt="" /> <?php echo $heading_title; ?></h1>
      <div class="buttons"><a onclick="$('#form').submit();" class="button"><?php echo $button_save; ?></a><a onclick="location = '<?php echo $cancel; ?>';" class="button"><?php echo $button_cancel; ?></a></div>
    </div>
    <div class="content">
      <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form">
        <table class="form">
          <?php foreach ($onego_config_fields as $field => $row) { ?>
          <tr id="cfgRow_<?php echo $field ?>">
            <td><?php echo $row['title']; ?></td>
            <td>
                <?php if (in_array($field, array('widgetShow', 'widgetFrozen', 'autologinOn'))) { ?>
                
                <input type="hidden" name="onego_<?php echo $field ?>" value="" />
                <input type="checkbox" name="onego_<?php echo $field ?>" id="cfgField_<?php echo $field ?>" value="Y" <?php echo $row['value'] == 'Y' ? ' checked="checked"' : '' ?> />
                
                <?php } else { ?>
                <input type="text" name="onego_<?php echo $field ?>" id="cfgField_<?php echo $field ?>" value="<?php echo $row['value']; ?>" />
                <?php } ?>
                <div style="display: inline; color: gray; margin-left: 10px;"><?php echo $row['help'] ?></div>
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
              </select></td>
          </tr>
          <tr>
            <td><?php echo $entry_sort_order; ?></td>
            <td><input type="text" name="onego_sort_order" value="<?php echo $onego_sort_order; ?>" size="1" /></td>
          </tr>
        </table>
      </form>
    </div>
  </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    hideCfgParams();
    $('#cfgField_widgetShow').click(hideCfgParams);
})
function hideCfgParams()
{
    var dependableParams = $('#cfgRow_widgetCode, #cfgRow_widgetPosition, #cfgRow_widgetTopOffset, #cfgRow_widgetFrozen');
    if (!$('#cfgField_widgetShow').is(':checked')) {
        dependableParams.hide();
    } else {
        dependableParams.show();
    }
}
</script>

<?php echo $footer; ?>