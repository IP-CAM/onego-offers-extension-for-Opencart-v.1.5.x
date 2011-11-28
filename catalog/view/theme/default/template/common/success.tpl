<?php echo $header; ?><?php echo $column_left; ?><?php echo $column_right; ?>
<div id="content"><?php echo $content_top; ?>
  <div class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
    <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
    <?php } ?>
  </div>
  <h1><?php echo $heading_title; ?></h1>
  <?php echo $text_message; ?>
  
  
  <?php if (!empty($onego_benefits_applied)) { ?>
    <fieldset style="border: 1px solid #D8DEE1; margin: 10px 20px; border-radius: 5px;">
        <legend><img src="catalog/view/theme/default/image/onego.png" alt="OneGo benefits" title="OneGo benefits" /></legend>
        You were awarded with <img title="OneGo monetary points" alt="OneGo monetary points" src="catalog/view/theme/default/image/onego_monetary_points.png"> 22.5
        OneGo funds.<br />
        You can now create your OneGo account using your e-mail address to see your benefits and get to know other offers Opencart E-shop may have for you!
        <br />
        <br />
        <div class="right"><a href="http://www.onego.com" class="button"><span>Register with OneGo</span></a></div>
    </fieldset>
  <?php } ?>
  <?php if (!empty($onego_benefits_applyable)) { ?>
    <fieldset style="border: 1px solid #D8DEE1; margin: 10px 20px; border-radius: 5px;">
        <legend><img src="catalog/view/theme/default/image/onego.png" alt="OneGo benefits" title="OneGo benefits" /></legend>
        <strong>Claim OneGo benefits:</strong><br />
        If you'd like to retain your 
        <img title="OneGo monetary points" alt="OneGo monetary points" src="catalog/view/theme/default/image/onego_monetary_points.png"> 22.5
        please confirm that you accept to expose your e-mail to OneGo
        &nbsp;&nbsp;
        <a href="<?php echo $onego_claim; ?>" class="button"><span>I agree</span></a>
    </fieldset>
  <?php } ?>
  
  <div class="buttons">
    <div class="right"><a href="<?php echo $continue; ?>" class="button"><span><?php echo $button_continue; ?></span></a></div>
  </div>
  <?php echo $content_bottom; ?></div>
<?php echo $footer; ?>