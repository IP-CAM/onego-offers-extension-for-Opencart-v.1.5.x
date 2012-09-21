<?php echo $header; ?>

<script type="text/javascript">
$(document).ready(function(){
    $('#create_product_form input').click(function(){
        $('#assign_to_product_form input[type=radio]').attr('checked', false);
    })
    $('#assign_to_product_form input[type=radio]').click(function(){
        $('#create_product_form input[type=text]').val('');
        $('#create_product_form input[type=checkbox]').attr('checked', false);
    })
})
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
  <div class="box">
    <div class="heading">
      <h1><img src="view/image/onego_icon.png" alt="<?php echo $heading_title; ?>" /> <?php echo $heading_title; ?></h1>
      <div class="buttons">
          <?php if (!empty($create_product)) { ?>
          <a onclick="$('input[name=save]').val(1); $('#rcform').submit();" class="button"><?php echo $lang->get('button_save'); ?></a>
          <?php } ?>
          <a onclick="location.href='<?php echo $url_cancel; ?>'" class="button"><?php echo $lang->get('button_cancel'); ?></a>
      </div>
    </div>
    <div class="content" id="onego_rc_upload">

        <form action="<?php echo $url_self ?>" method="POST" enctype="multipart/form-data" id="rcform">
        <input type="hidden" name="save" value="0" />

        <?php if (!empty($rc_nominal) && !empty($rc_count)) { ?>
        <div class="rc_uploaded">
            <div class="onego_rc_added"><?php echo $lang->get('rc_to_be_added').': '.$rc_count ?></div>
            <div class="onego_rc_nominal"><?php echo $lang->get('rc_nominal').': '.$rc_nominal ?></div>
        </div>
        <?php } ?>

        <fieldset id="rcUpload">
            <legend><?php echo $lang->get('rc_upload_csv') ?></legend>

            <div class="help">
                <?php echo $lang->get('rc_upload_description') ?>
            </div>
            
            <input type="file" name="csv_list" />
            <input type="submit" name="btn_upload" value="<?php echo $lang->get('button_upload') ?>" />
            
        </fieldset>

        <?php if (!empty($create_product)) { ?>
        <fieldset id="rcAssignToProduct">
            <legend><?php echo $lang->get('rc_create_product') ?></legend>

            <?php if (!empty($products)) { ?>

            <h2><?php echo $lang->get('rc_select_product') ?>:</h2>

            <table class="list" id="assign_to_product_form">
            <thead>
            <tr>
                <td width="1" style="text-align: center;"></td>
                <td class="left"><?php echo $lang->get('column_name'); ?></td>
                <td class="right"><?php echo $lang->get('column_status'); ?></td>
                <td class="right"><?php echo $lang->get('rc_nominal'); ?></td>
                <td class="right"><?php echo $lang->get('rc_sold'); ?></td>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $row) { ?>
            <tr>
                <td style="text-align: center;">
                    <?php $st = ''; ?>
                    <input type="radio" name="product_id" value="<?php echo $row['product_id']; ?>" <?php echo $st ?> />
                </td>
                <td class="left"><a href="<?php echo $row['product_url'] ?>"><?php echo $row['name']; ?></a></td>
                <td class="right"><?php echo $row['status_text']; ?></td>
                <td class="right"><?php echo $row['nominal']; ?></td>
                <td class="right"><?php echo sprintf($lang->get('rc_sold_numbers'), $row['codes_sold'], $row['codes_sold']+$row['codes_available']); ?></td>
            </tr>
            <?php } ?>
            </tbody>
            </table>

            <?php } ?>

            <h2><?php echo $lang->get('rc_create_product_details') ?>:</h2>

            <table class="form" id="create_product_form">
            <?php foreach ($languages as $language) { ?>
            <tr>
              <td>
                  <span class="required">*</span>
                  <?php echo $lang->get('entry_name').' ('.$language['name'].')'; ?>
              </td>
              <td>
                  <input type="text" name="product[name][<?php echo $language['language_id']; ?>]" size="80" value="<?php echo isset($product['name'][$language['language_id']]) ? $product['name'][$language['language_id']] : ''; ?>" />
                    <?php if (!empty($errors['name'][$language['language_id']])) { ?>
                    <span class="error"><?php echo $errors['name'][$language['language_id']]; ?></span>
                    <?php } ?></td>
              </td>
            </tr>
            <?php } ?>
            <tr>
              <td>
                  <span class="required">*</span>
                  <?php echo $lang->get('entry_model'); ?>
              </td>
              <td>
                  <input type="text" name="product[model]" value="<?php echo isset($product['model']) ? $product['model'] : ''; ?>" />
                    <?php if (!empty($errors['model'])) { ?>
                    <span class="error"><?php echo $errors['model']; ?></span>
                    <?php } ?></td>
              </td>
            </tr>
            <tr>
              <td>
                  <span class="required">*</span>
                  <?php echo $lang->get('entry_price'); ?>
              </td>
              <td>
                  <input type="text" name="product[price]" value="<?php echo isset($product['price']) ? $product['price'] : ''; ?>" />
                  <?php if (!empty($errors['price'])) { ?>
                    <span class="error"><?php echo $errors['price']; ?></span>
                    <?php } ?></td>
              </td>
            </tr>
            <tr>
              <td><?php echo $lang->get('entry_category'); ?></td>
              <td><div class="scrollbox">
                  <?php $class = 'odd'; ?>
                  <?php foreach ($categories as $category) { ?>
                  <?php $class = ($class == 'even' ? 'odd' : 'even'); ?>
                  <div class="<?php echo $class; ?>">
                    <?php $st = isset($product['category']) && in_array($category['category_id'], $product['category']) ? 'checked="checked"' : '' ?>
                    <input type="checkbox" name="product[category][]" value="<?php echo $category['category_id']; ?>" <?php echo $st ?> />
                    <?php echo $category['name']; ?>
                  </div>
                  <?php } ?>
                </div>
                <a onclick="$(this).parent().find(':checkbox').attr('checked', true);"><?php echo $lang->get('text_select_all'); ?></a> / <a onclick="$(this).parent().find(':checkbox').attr('checked', false);"><?php echo $lang->get('text_unselect_all'); ?></a></td>
            </tr>
            
            </table>
            
        </fieldset>
        <?php } ?>

        </form>

    </div>
  </div>
</div>
<?php echo $footer; ?>