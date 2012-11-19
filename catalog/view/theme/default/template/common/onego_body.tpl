<script id="OneGoLoader">
  window.oneGoAsyncInit = function() {
    OneGo.init({ <?php echo $initParamsStr ?> });
    <?php echo $html ?>
  };

  (function(d){
    var id = 'onego-jssdk', ref = d.getElementById('OneGoLoader');
    if (d.getElementById(id)) {return;}
    var js = d.createElement('script'); js.id = id; js.async = true;
    js.src = '<?php echo $onego_jssdk_url ?>';
    ref.parentNode.insertBefore(js, ref);
    function loaded(){ window.oneGoAsyncDocLoaded = true }
    if (window.addEventListener) {
      window.addEventListener('load', loaded, false);
    } else if (window.attachEvent) {
      window.attachEvent('onload', loaded);
    }
  }(document));
</script>