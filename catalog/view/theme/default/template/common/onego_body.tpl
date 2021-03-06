<script id="OneGoSdkLoader">
  var initializer = function() {
    OneGo.init({ <?php echo $initParamsStr ?> });
    <?php echo $html ?>
  };
  var onError = function(error) {
    if (console.warn && typeof console.warn == 'function') {
      console.warn('OneGo SDK error: ' + error.message);
    }
  };

  (function(d, successCallback, errorCallback){
    var id = 'onego-jssdk', ref = d.getElementById('OneGoSdkLoader');
    if (d.getElementById(id)) {return;}
    var js = d.createElement('script'); js.id = id; js.async = true;
    js.src = '<?php echo $onego_jssdk_url ?>';
    ref.parentNode.insertBefore(js, ref);
    window.oneGoAsyncInit = successCallback;
    window.oneGoAsyncOnError = errorCallback;
  }(document, initializer, onError));
</script>
