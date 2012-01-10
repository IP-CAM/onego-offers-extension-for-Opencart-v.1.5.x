<div style="width: 550px;" id="onego_choices">
    


<li style="margin-bottom: 15px;">
    <div style="font-size: larger; font-weight: bold; margin-bottom: 3px;">
        &bull; Already have your OneGo account?
    </div>
    <div style="margin-left: 15px; color: gray;">
        Log in to redeem your gift card and use your funds when you want and how you want.<br />
        <input type="button" value="Sign in with OneGo" style="margin-top: 3px;" />
    </div>
</li>
<li style="margin-bottom: 15px;">
    <div style="font-size: larger; font-weight: bold; margin-bottom: 3px;">
        &bull; No OneGo account yet?
    </div>
    <div style="margin-left: 15px; color: gray;">
        Let OneGo know your email and all gift card funds not used in this transaction will be kept for you.<br />
        You can register later to use those funds and receive other great benefits.<br />
        <input type="text" class="onego_watermark" value="Your e-mail address" style="margin-top: 3px; width: 300px;" />
        <input type="button" value="Share e-mail" style="margin-top: 3px;" />
    </div>
</li>
<li style="margin-bottom: 10px;">
    <div style="font-size: larger; font-weight: bold; margin-bottom: 3px;">
        &bull; Just want to use your Gift Card for this purchase?
    </div>
    <div style="margin-left: 15px; color: gray;">
        <input type="button" value="Use Gift Card" />
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