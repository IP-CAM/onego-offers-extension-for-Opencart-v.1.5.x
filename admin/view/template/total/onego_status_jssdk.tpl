<html>
<head>

</head>
<body>
...
<script id="OneGoSdkLoader">
  var initializer = function() {
    parent.OneGoAccountStatusError(false);
  };
  var onError = function(error) {
    parent.OneGoAccountStatusError(error.message);
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
</body>
</html>