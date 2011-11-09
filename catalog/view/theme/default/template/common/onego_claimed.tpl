<?php echo $header; ?><?php echo $column_left; ?><?php echo $column_right; ?>
<div id="content"><?php echo $content_top; ?>
  <div class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
    <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
    <?php } ?>
  </div>
  <h1>You have claimed your OneGo benefits!</h1>
  
  You were awarded with <img title="OneGo monetary points" alt="OneGo monetary points" src="catalog/view/theme/default/image/onego_monetary_points.png"> 22.5
  OneGo funds.<br />
  You can now create your OneGo account using your e-mail address to see your benefits and get to know other offers Opencart E-shop may have for you!
  <br />
  <br />
  <div class="right"><a href="http://www.onego.com" class="button"><span>Register with OneGo</span></a></div>
  <br />
    
  <div class="buttons">
    <div class="right"><a href="<?php echo $continue; ?>" class="button"><span>Continue</span></a></div>
  </div>
  <?php echo $content_bottom; ?></div>
<?php echo $footer; ?>