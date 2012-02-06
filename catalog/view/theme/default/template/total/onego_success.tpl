<?php if (!empty($onego_benefits_applied) ||
        !empty($onego_benefits_applyable) ||
        !empty($onego_funds_received)) 
{ ?>
<div id="onego_panel">
  <div id="onego_panel_label"></div>
  <div id="onego_panel_content">
    <?php if (!empty($onego_funds_received)) { ?>
    <?php echo $onego_funds_received ?><br />  
    <?php } ?>
    
    <?php if (!empty($onego_benefits_applied)) { ?>
        <?php echo $onego_buyer_created ?>
        <br />
        <br />
        <div class="right"><a href="<?php echo $onego_claim; ?>" class="button"><span><?php echo $onego_button_register ?></span></a></div>
    <?php } ?>
    
    <?php if (!empty($onego_benefits_applyable)) { ?>
        <strong><?php echo $onego_claim_benefits ?></strong><br />
        <br />
        <?php if (!empty($onego_funds_receivable)) { ?>
            <?php echo $onego_funds_receivable ?>
        <?php } else { ?>
            <?php echo $onego_suggest_disclose ?>
        <?php } ?>
        &nbsp;&nbsp;
        <a href="<?php echo $onego_claim; ?>" class="button"><span><?php echo $onego_button_agree ?></span></a>
    <?php } ?>
  </div>
</div>
<?php } ?>