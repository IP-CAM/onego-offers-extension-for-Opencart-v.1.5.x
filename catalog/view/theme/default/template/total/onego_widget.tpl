<div class="onego_widget_handle">
    <a href="javascript:OneGo.widget.show();" class="show"><img src="catalog/view/theme/default/image/onego_handle_show.png" alt="<?php echo $widget_show ?>" title="<?php echo $widget_show ?>" /></a>
    <a href="javascript:OneGo.widget.hide();" class="hide"><img src="catalog/view/theme/default/image/onego_handle_hide.png" alt="<?php echo $widget_hide ?>" title="<?php echo $widget_hide ?>" /></a>
</div>
<div class="onego_widget">
    <?php echo $widgetCode; ?>
</div>

<script type="text/javascript">
OneGo.widget.setTopOffset(<?php echo $widgetTopOffset ?>);
<?php if (!empty($widgetFrozen)) { ?>
OneGo.widget.freeze();
<?php } ?>
</script>