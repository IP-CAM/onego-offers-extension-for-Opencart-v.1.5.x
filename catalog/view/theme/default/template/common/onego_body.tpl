<script id="OneGoSdkLoader">
  var initializer = function() {
    OneGo.init({ <?php echo $initParamsStr ?> });
    <?php echo $html ?>
  };

  (function(d, initCallback){
    var id = 'onego-jssdk', ref = d.getElementById('OneGoSdkLoader');
    if (d.getElementById(id)) {return;}
    var js = d.createElement('script'); js.id = id; js.async = true;
    js.src = '<?php echo $onego_jssdk_url ?>';
    ref.parentNode.insertBefore(js, ref);
    window.oneGoAsyncInit = initCallback;
  }(document, initializer));
</script>