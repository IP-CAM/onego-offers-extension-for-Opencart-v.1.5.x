<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
<script type="text/javascript">
function closeWindow()
{
    self.close();
}

var status = {
    authenticated: <?php echo (!empty($onego_authenticated) ? 'true' : 'false') ?>
}
<?php if (!empty($onego_authenticated)) { ?>
window.opener.OneGoOpencart.loginPromptSuccess = true;
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
window.opener.OneGoOpencart.flashWarningBefore(window.opener.$('#onego_panel'), '<?php echo str_replace('\'', '\\\'', $onego_error) ?>');
</script>
<?php } else { ?>
Authorization successful.
<?php } ?>

<div style="text-align: center;">
    <a href="#" class="button" onclick="closeWindow();"><span>Close</span></a>
</div>

<script type="text/javascript">
closeWindow();
</script>

</body>
</html>