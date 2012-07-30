<?php echo $header; ?>
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
          <?php if (empty($save)) { ?>
          <a onclick="$('input[name=save]').val(1); $('#vgcform').submit();" class="button"><?php echo $lang->get('button_save'); ?></a>
          <?php } ?>
          <a onclick="location.href='<?php echo $url_cancel; ?>'" class="button"><?php echo $lang->get('button_cancel'); ?></a>
      </div>
    </div>
    <div class="content" id="onego_vgc_upload">

        <div class="onego_description">
            <?php echo $lang->get('vgc_upload_description') ?>
        </div>

        <form action="<?php echo $url_self ?>" method="POST" enctype="multipart/form-data" id="vgcform">
        <input type="hidden" name="save" value="0" />

        <fieldset id="vgcUpload">
            <legend><?php echo $lang->get('vgc_upload_csv') ?></legend>

            <?php if (!empty($vgc_nominal) && !empty($vgc_count)) { ?>
            <strong>
                <?php echo $lang->get('vgc_to_be_added').': '.$vgc_count ?><br />
                <?php echo $lang->get('vgc_nominal').': '.$vgc_nominal ?><br />
            </strong>
            <br />
            <?php } ?>

            
            <input type="file" name="csv_list" />
            <input type="submit" name="btn_upload" value="<?php echo $lang->get('button_upload') ?>" />
            
            
        </fieldset>

        <?php if (!empty($create_product)) { ?>
        <fieldset id="vgcAssignToProduct">
            <legend><?php echo $lang->get('vgc_create_product') ?></legend>

            <h2><?php echo $lang->get('vgc_create_product_details') ?>:</h2>

            <table class="form">
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