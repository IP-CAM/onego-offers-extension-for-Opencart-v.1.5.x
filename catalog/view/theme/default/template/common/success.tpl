<?php echo $header; ?><?php echo $column_left; ?><?php echo $column_right; ?>
<div id="content"><?php echo $content_top; ?>
  <div class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
    <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
    <?php } ?>
  </div>
  <h1><?php echo $heading_title; ?></h1>
  <?php echo $text_message; ?>
  
  <?php if (!empty($onego_benefits_applyable)) { ?>
  <div class="buttons"><strong>Claim OneGo benefits:</strong><br />
      If you'd like to retain your 
      <img title="OneGo monetary points" alt="OneGo monetary points" src="catalog/view/theme/default/image/onego_monetary_points.png"> 22.5 and
      <img title="OneGo coupon points" alt="OneGo coupon points" src="catalog/view/theme/default/image/onego_coupon_points.png"> 90,
      please confirm that you accept to expose your e-mail to OneGo      
      &nbsp;&nbsp;
      <a href="<?php echo $continue; ?>" class="button"><span>I agree</span></a>
  </div>
  <?php } ?>
  
  <div class="buttons">
    <div class="right"><a href="<?php echo $continue; ?>" class="button"><span><?php echo $button_continue; ?></span></a></div>
  </div>
  <?php echo $content_bottom; ?></div>
<?php echo $footer; ?>