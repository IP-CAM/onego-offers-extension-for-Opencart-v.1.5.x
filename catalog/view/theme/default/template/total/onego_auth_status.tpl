<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
<link rel="stylesheet" type="text/css" href="catalog/view/theme/default/stylesheet/stylesheet.css" />
<script type="text/javascript" src="catalog/view/javascript/jquery/jquery-1.6.1.min.js"></script>
<!--<script type="text/javascript" src="catalog/view/javascript/onego.js"></script>-->
<script type="text/javascript">
function closeFancybox()
{
    window.parent.$.fancybox.close();
}

var status = {
    authenticated: <?php echo (!empty($onego_authenticated) ? 'true' : 'false') ?>
}
<?php if (!empty($onego_authenticated)) { ?>
window.parent.OneGo.opencart.loginPromptSuccess = true;
<?php } ?>    

</script>
<style>
html {
    overflow: auto;
}
</style>
</head>
<body>
        
<?php if (!empty($onego_error)) { ?>
<div class="error">
    <?php echo $onego_error ?>
</div>
<script type="text/javascript">
window.parent.OneGo.opencart.flashWarningBefore(window.parent.$('#onego_panel'), '<?php echo str_replace('\'', '\\\'', $onego_error) ?>');
</script>
<?php } else { ?>
Authorization successful.
<?php } ?>

<div style="text-align: center;">
    <a href="#" class="button" onclick="closeFancybox();"><span>Close</span></a>
</div>

<script type="text/javascript">
closeFancybox();
</script>

</body>
</html>