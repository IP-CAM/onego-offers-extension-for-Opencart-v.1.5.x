<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
<link rel="stylesheet" type="text/css" href="catalog/view/theme/default/stylesheet/stylesheet.css" />
<script type="text/javascript" src="catalog/view/javascript/jquery/jquery-1.6.1.min.js"></script>
<script type="text/javascript">
function closeFancybox()
{
    parent.$.fancybox.close();
}
</script>
<style>
html {
    overflow: auto;
}
</style>
</head>
<body>
        
<?php if (!empty($error)) { ?>
<div class="error">
    <?php echo $error ?>
</div>
<?php } ?>


<?php if (!empty($onego_enabled)) { ?>

OneGo benefits applied

<script type="text/javascript">
closeFancybox();
</script>

<?php } else { ?>

OneGo disabled

<?php } ?>

<div style="text-align: center;">
    <a href="#" class="button" onclick="closeFancybox();"><span>Close</span></a>
</div>



</body>
</html>