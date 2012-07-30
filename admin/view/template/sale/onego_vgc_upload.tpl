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
          <a onclick="alert('not yet!'); $('#vgcform').submit();" class="button"><?php echo $lang->get('button_save'); ?></a>
          <?php } ?>
          <a onclick="location.href='<?php echo $url_cancel; ?>'" class="button"><?php echo $lang->get('button_cancel'); ?></a>
      </div>
    </div>
    <div class="content" id="onego_vgc_upload">

        <div class="onego_description">
            <?php echo $lang->get('vgc_upload_description') ?>
        </div>

        <fieldset id="vgcUpload">
            <legend><?php echo $lang->get('vgc_upload_csv') ?></legend>

            <?php if (!empty($vgc_nominal) && !empty($vgc_count)) { ?>
            <strong>
                <?php echo $lang->get('vgc_to_be_added').': '.$vgc_count ?><br />
                <?php echo $lang->get('vgc_nominal').': '.$vgc_nominal ?><br />
            </strong>
            <br />
            <?php } ?>

            <form action="<?php echo $url_self ?>" method="POST" enctype="multipart/form-data">
                <input type="file" name="csv_list" />
                <input type="submit" name="btn_upload" value="<?php echo $lang->get('button_upload') ?>" />
            </form>
            
        </fieldset>

    </div>
  </div>
</div>
<?php echo $footer; ?>