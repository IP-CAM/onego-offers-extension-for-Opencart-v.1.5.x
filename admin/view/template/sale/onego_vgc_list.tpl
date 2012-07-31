<?php echo $header; ?>

<script type="text/javascript">
function setStatus(enabled) {
    $('input[name=action]').val(enabled ? 'enable' : 'disable');
    $('#vgc_list').submit();
}
function toggleSingle(elem, enabled)
{
    $('#vgc_list input[type=checkbox]').attr('checked', false);
    elem.parent().parent().find('input[type=checkbox]').attr('checked', true);
    setStatus(enabled);
}
function deleteCards(elem)
{
    if (confirm('<?php echo $lang->get('vgc_confirm_cards_delete') ?>')) {
        $('#vgc_list input[type=checkbox]').attr('checked', false);
        elem.parent().parent().find('input[type=checkbox]').attr('checked', true);
        $('input[name=action]').val('delete');
        $('#vgc_list').submit();
    }
}
</script>

<div id="content">
  <div class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
    <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
    <?php } ?>
  </div>
  <?php if (!empty($error_warning)) { ?>
  <div class="warning"><?php echo $error_warning; ?></div>
  <?php } ?>
  <?php if (!empty($success)) { ?>
  <div class="success"><?php echo $success; ?></div>
  <?php } ?>

  <?php if (empty($extension_disabled)) { ?>
  <div class="box">
    <div class="heading">
      <h1><img src="view/image/onego_icon.png" alt="" /> <?php echo $heading_title; ?></h1>

      <?php if (empty($extension_disabled)) { ?>
      <div class="buttons">
          <a onclick="location = '<?php echo $upload_url; ?>'" class="button"><?php echo $lang->get('vgc_upload'); ?></a>
          <?php if (!empty($list)) { ?>
          <a onclick="setStatus(true);" class="button"><?php echo $lang->get('button_enable'); ?></a>
          <a onclick="setStatus(false);" class="button"><?php echo $lang->get('button_disable'); ?></a>
          <?php } ?>
      </div>
      <?php } ?>
    </div>
    <div class="content">

        <form action="<?php echo $url_self ?>" method="post" id="vgc_list">
            <input type="hidden" name="action" value="" />
        <table class="list">
          <thead>
            <tr>
              <td width="1" style="text-align: center;"><input type="checkbox" onclick="$('input[name*=\'selected\']').attr('checked', this.checked);" /></td>
              <td class="left"><?php echo $lang->get('column_name'); ?></td>
              <td class="right"><?php echo $lang->get('column_status'); ?></td>
              <td class="right"><?php echo $lang->get('vgc_nominal'); ?></td>
              <td class="right"><?php echo $lang->get('vgc_sold'); ?></td>
              <td class="right"><?php echo $lang->get('column_action'); ?></td>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($list)) { ?>
            <?php foreach ($list as $row) { ?>
            <tr>
                <td style="text-align: center;">
                    <?php $st = ''; ?>
                    <input type="checkbox" name="selected[]" value="<?php echo $row['product_id']; ?>" <?php echo $st ?> />
                </td>
                <td class="left"><a href="<?php echo $row['product_url'] ?>"><?php echo $row['name']; ?></a></td>
                <td class="right"><?php echo $row['status_text']; ?></td>
                <td class="right"><?php echo $row['nominal']; ?></td>
                <td class="right"><?php echo sprintf($lang->get('vgc_sold_numbers'), $row['cards_sold'], $row['cards_sold']+$row['cards_available']); ?></td>
                <td class="right" width="200">
                    <?php if ($row['status']) { ?>
                    [ <a onclick="toggleSingle($(this), false);"><?php echo $lang->get('button_disable'); ?></a> ]
                    <?php } else { ?>
                    [ <a onclick="toggleSingle($(this), true);"><?php echo $lang->get('button_enable'); ?></a> ]
                    <?php } ?>
                    <?php if ($row['cards_available']) { ?>
                    [ <a onclick="deleteCards($(this));"><?php echo $lang->get('vgc_button_delete_cards'); ?></a> ]
                    <?php } ?>

                </td>
            </tr>
            <?php } ?>
            <?php } else { ?>
            <tr>
              <td class="center" colspan="6"><?php echo $lang->get('vgc_no_batches_available'); ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </form>

    </div>
  </div>
  <?php } ?>
</div>
<?php echo $footer; ?>