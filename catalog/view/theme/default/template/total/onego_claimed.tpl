<?php echo $header; ?><?php echo $column_left; ?><?php echo $column_right; ?>
<div id="content"><?php echo $content_top; ?>
  <div class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
    <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
    <?php } ?>
  </div>
    
  <?php if (!empty($onego_error)) { ?>
    <h1><?php echo $onego_claim_benefits ?></h1>
    
    <div class="warning"><?php echo $onego_error ?></div>
    
    <?php if (!empty($show_try_again)) { ?>
        <a href="<?php echo $link_reload; ?>" class="button"><span><?php echo $onego_button_try_again ?></span></a><br />
        <br />
    <?php } ?>
    
  <?php } else { ?>
  
  <h1><?php echo $onego_benefits_claimed ?></h1>
  
  <?php if ($onego_rewarded) echo $onego_rewarded.'<br />'; ?>
  
  <?php echo $onego_anonymous_buyer_created ?>
  <br />
  <br />
  <div class="right"><a href="<?php echo $onego_registration_uri ?>" class="button"><span><?php echo $onego_button_register ?></span></a></div>
  <br />
  
  <?php } ?>
    
  <div class="buttons">
    <div class="right"><a href="<?php echo $continue; ?>" class="button"><span>Continue</span></a></div>
  </div>
  <?php echo $content_bottom; ?></div>
<?php echo $footer; ?>