<link rel="stylesheet" type="text/css" href="catalog/view/theme/<?php echo $theme ?>/stylesheet/onego.css" />
<script type="text/javascript" src="<?php echo $onego_jssdk_url ?>"></script>
<script type="text/javascript" src="catalog/view/javascript/onego.js"></script>
<script type="text/javascript">
OneGo.init({ <?php echo $initParamsStr ?> });
<?php echo $html ?>
</script>

<?php echo $debuggingCode ?>