<div style="width: 550px;" id="onego_choices">
    


<li style="margin-bottom: 15px;">
    <div style="font-size: larger; font-weight: bold; margin-bottom: 3px;">
        &bull; Already have your OneGo account?
    </div>
    <div style="margin-left: 15px; color: gray;">
        Log in to redeem your gift card and use your funds now or on next purchase.<br />
        <input type="button" value="Sign in with OneGo" style="margin-top: 3px;" />
    </div>
</li>
<li style="margin-bottom: 10px;">
    <div style="font-size: larger; font-weight: bold; margin-bottom: 3px;">
        &bull; Just want to use your Gift Card for this purchase?
    </div>
    <div style="margin-left: 15px; color: gray;">
        <input type="button" value="Redeem my Gift Card" />
    </div>
</li>

</div>

<script type="text/javascript">
$('#onego_choices input[type=button]').click(function(e){
    $.fancybox.close();
})
$('input.watermark').focus(function(){
    $(this).addClass('focused');
    $(this).val('');
})
$('input.watermark').blur(function(){
    $(this).removeClass('focused');
})
</script>