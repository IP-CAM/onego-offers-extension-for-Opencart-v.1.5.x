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
      <h1><img src="view/image/onego_icon.png" alt="" /> <?php echo $heading_title; ?></h1>
      <div class="buttons"><a onclick="location = '<?php echo $upload_url; ?>'" class="button"><?php echo $lang->get('vgc_upload'); ?></a></div>
    </div>
    <div class="content">

        <?php if (empty($batches)) { ?>
            <?php echo $lang->get('vgc_no_batches_available'); ?>
        <?php } else { ?>
            <?php var_dump($batches) ?>
        <?php } ?>

    </div>
  </div>
</div>
<?php echo $footer; ?>