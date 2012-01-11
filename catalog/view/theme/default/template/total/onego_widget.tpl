<div class="onego_widget_handle">
    <div class="onego_widget_loading"><img src="catalog/view/theme/default/image/onego_loading.gif" alt="" title="" /></div>
    <div class="onego_widget_show"><a href="javascript:OneGo.widget.show();"><img src="catalog/view/theme/default/image/onego_arrow_right.png" alt="<?php echo $widget_show ?>" title="<?php echo $widget_show ?>" /></a></div>
    <div class="onego_widget_hide"><a href="javascript:OneGo.widget.hide();"><img src="catalog/view/theme/default/image/onego_arrow_left.png" alt="<?php echo $widget_hide ?>" title="<?php echo $widget_hide ?>" /></a></div>
</div>
<div class="onego_widget">
    <?php echo $widgetCode; ?>
</div>

<script type="text/javascript">
OneGo.plugins.widget.onLoadComplete(function(container){
    if ($('.onego_widget_handle .onego_widget_loading', container).is(':visible')) {
        $('.onego_widget_handle .onego_widget_loading', container).hide();
        $('.onego_widget_handle .onego_widget_show', container).show();
    }
});
OneGo.plugins.widget.onShow(function(container){
    $('.onego_widget_handle .onego_widget_show', container).hide();
    $('.onego_widget_handle .onego_widget_hide', container).show();
});
OneGo.plugins.widget.onHide(function(container){
    $('.onego_widget_handle .onego_widget_show', container).show();
    $('.onego_widget_handle .onego_widget_hide', container).hide();
});
</script>