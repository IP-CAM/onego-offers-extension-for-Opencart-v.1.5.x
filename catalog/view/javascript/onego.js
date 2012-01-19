var OneGo = {
    config: {
        debug: false,
        loginUri: $('base').attr('href') + 'index.php?route=total/onego/loginDialog',
        autologinUri: $('base').attr('href') + 'index.php?route=total/onego/autologin',
        logoffUri: $('base').attr('href') + 'index.php?route=total/onego/cancel',
        widgetUri: $('base').attr('href') + 'index.php?route=total/onego/widget'        
    }
}

OneGo.XDC = function(){

    var interval_id,
    last_hash,
    cache_bust = 1,
    attached_callback,
    window = this;

    return {
        postMessage : function(message, target_url, target) {
            if (!target_url) {
                return;
            }
            target = target || parent;
            if (window['postMessage']) {
                target['postMessage'](message, target_url.replace( /([^:]+:\/\/[^\/]+).*/, '$1'));
            } else if (target_url) {
                target.location = target_url.replace(/#.*$/, '') + '#' + (+new Date) + (cache_bust++) + '&' + message;
            }
        },
        receiveMessage : function(callback, source_origin) {
            if (window['postMessage']) {
                if (callback) {
                    attached_callback = function(e) {
                        OneGo.log('received msg "'+e.data+'" from '+e.origin);
                        if ((typeof source_origin === 'string' && e.origin !== source_origin)
                            || (Object.prototype.toString.call(source_origin) === "[object Function]" && source_origin(e.origin) === !1)) 
                        {
                            return !1;
                        }
                        callback(e);
                     };
                 }
                 if (window['addEventListener']) {
                     window[callback ? 'addEventListener' : 'removeEventListener']('message', attached_callback, !1);
                 } else {
                     window[callback ? 'attachEvent' : 'detachEvent']('onmessage', attached_callback);
                 }
            } else {
                interval_id && clearInterval(interval_id);
                interval_id = null;
                if (callback) {
                    interval_id = setInterval(function() {
                        var hash = document.location.hash,
                        re = /^#?\d+&/;
                        if (hash !== last_hash && re.test(hash)) {
                            last_hash = hash;
                            callback({data: hash.replace(re, '')});
                            document.location = document.location.href.replace(/#.*$/, '') + '#';
                        }
                    }, 100);
                }
            }
        }        
    };
}();

OneGo.plugins = (function() {
    var URI = '';
    var initQueue = [];
    var initialized = false;
    
    $(document).ready(initialize);
    
    function initialize()
    {
        for (var k in initQueue) {
            initQueue[k]();
            delete initQueue[k];
        }
        initialized = true;
    }
    
    return {
        setURI: function(uri){
            URI = uri.replace(/\/$/, '');
        },
        getURI: function(){
            return URI;
        },
        addToInitQueue: function(callback) {
            if (initialized) {
                callback();
            } else {
                initQueue.push(callback);
            }
        }
    }
})();

OneGo.plugins.authAgent = (function()
{
    var handlers = {};
    
    function initialize()
    {
        $('body').append('<iframe id="onego_plugin_authagent" name="onego_plugin_authagent" src="'+getURI()+'" width="0" height="0" frameborder="0" class="onego_plugin"></iframe>');
        initListeners();
    }
    OneGo.plugins.addToInitQueue(initialize);
    
    function initListeners()
    {
        OneGo.XDC.receiveMessage(function(authWidgetMessage){
            var str = [];
            for (var msg in handlers) {
                str.push(msg);
            }
            OneGo.log('Message received from OneGo authWidget: '+authWidgetMessage.data+' / listening for: '+str.join('; '));
            
            for (var msg in handlers) {
                if ((msg == authWidgetMessage.data) && handlers[msg]) {
                    handlers[msg]();
                }
            }
        }, getHost());
    }
    
    function getHost()
    {
        return getURI().replace(/^([a-z]+:\/\/[^\/]+)(.*)$/i, "\$1");
    }
    
    function getURI()
    {
        return OneGo.plugins.getURI()+'/authagent?ref='+encodeURIComponent(document.location.href);
    }
    
    return {
        setListener: function(message, callback) {
            handlers[message] = callback;
        },
        unsetListener: function(message) {
            delete handlers[message];
        }
    }
})();

OneGo.plugins.authWidget = function(elementId, initParams) 
{
    
    initParams = initParams || {};
    var params = {
        'tc':   initParams['text-color'] || false,
        'lc':   initParams['link-color'] || false,
        'fo':   initParams['font'] || false,
        'ts':   initParams['font-size'] || false,
        'wi':   initParams['width'] || false,
        'he':   initParams['height'] || false,
        'te':   initParams['text'] || false
    }
    
    var w = params.wi ? params.wi : $('#'+elementId).width();
    var h = params.he ? params.he : $('#'+elementId).height();
    var id = generateId();
    var plugin = $('<div class="onego_plugin" id="'+id+'" style="width: '+w+'px; height: '+h+'px;"></div>');
    if ($('#'+elementId).length) {
        $('#'+elementId).append(plugin);
        OneGo.plugins.addToInitQueue(initialize);
    }
    
    function getURI() 
    {
        var uri = OneGo.plugins.getURI()+'/authwidget';
        var query = [];
        for (var k in params) {
            if (params[k]) {
                query.push(k+'='+escape(params[k]));
            }
        }        
        return uri + (query.length ? '?' + query.join('&') : '');
    }
    
    function initialize()
    {
        var iframe = $('<iframe src="'+getURI()
            +'" width="'+w+'" height="'+h
            +'" frameborder="0" allowtransparency="true"></iframe>'
        );
        plugin.append(iframe);
        iframe.load(function(e){
            $('#'+elementId).html(plugin);
            plugin.show();
        });
    }
    
    function generateId()
    {
        var cnt = 0;
        $('.onego_plugin').each(function(){
            if (/^onego_plugin_authwidget_[0-9]+$/i.test($(this).attr('id'))) {
                cnt++;
            }
        })
        return 'onego_plugin_authwidget_' + (cnt + 1);
    }
    
    return {
        getId: function() {
            return id;
        }
    }
}

OneGo.plugins.widget = (function(){
    var isLoaded = false,
        isShown = false,
        topOffset = 0,
        isFrozen = false,
        
        loads = 0,
        timeoutId;
    var container = $('<div id="onego_widget_container"></div>');
    
    var customOnShowCallback, customOnHideCallback, customOnLoadCompleteCallback;
    
    function initialize() 
    {
        container.css('top', topOffset);
        if (isFrozen) {
            container.css('position', 'fixed');
        }
        $('body').append(container);
        container.load(OneGo.config.widgetUri, onLoadCallback);
    }
    
    function onLoadCallback()
    {
        if (!$('iframe', container).length) {
            setTimeout(onLoadCallback, 50);
            return false;
        }
        updateWidth();
        $('iframe', container).load(onCompleteCallback);
        $('.onego_widget, .onego_widget_handle div', container)
            .mouseover(hoverOn)
            .mouseout(hoverOff);
    }
    
    function onCompleteCallback() 
    {
        loads++;
        if (loads == 2 && !isLoaded) {
            isLoaded = true;
            if (customOnLoadCompleteCallback) {
                customOnLoadCompleteCallback(container);
            }
        }
    }
    
    function getIframeWidthDiff()
    {
        return $('iframe', container).outerWidth() - $('.onego_widget', container).width();
    }
    
    function updateWidth() 
    {
        var diff = getIframeWidthDiff();
        container.css('width', container.outerWidth() + diff);
        $('.onego_widget', container).css('width', $('.onego_widget', container).width() + diff);
        container.css('left', 0 - $('.onego_widget', container).outerWidth());
    }
    
    function hoverOn()
    {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        show();
    }
    
    function hoverOff()
    {
        timeoutId = setTimeout(hide, 500);
    }
    
    function show() 
    {
        if (isLoaded && !isShown) {
            container.animate(
                { left: 0 },
                200,
                function() {
                    if (customOnShowCallback) {
                        customOnShowCallback(container);
                    }
                    isShown = true;
                }
            );
        }
    }
    
    function hide() 
    {
        if (isShown) {
            container.animate(
                { left: 0 - $('.onego_widget', container).outerWidth() },
                200,
                function() {
                    if (customOnHideCallback) {
                        customOnHideCallback(container);
                    }
                    isShown = false;
                }
            );
        }
    }
    
    return {
        init: function(top_offset, is_frozen) {
            topOffset = top_offset || 0;
            isFrozen = is_frozen || false;
            OneGo.plugins.addToInitQueue(initialize);
        },
        onShow: function(callback) {
            customOnShowCallback = callback;
        },
        onHide: function(callback) {
            customOnHideCallback = callback;
        },
        onLoadComplete: function(callback) {
            customOnLoadCompleteCallback = callback;
        }
    }
    
})();

OneGo.opencart = {
    loginPromptSuccess: false,
    processLoginDynamic: function(){
        OneGo.log('authAgent.processLoginDynamic', 1);
        OneGo.opencart.autologin(OneGo.opencart.reloadCheckoutOrderInfo);
        // listen for widget logoff
        OneGo.plugins.authAgent.setListener('onego.widget.user.authenticated', false);
        OneGo.plugins.authAgent.setListener('onego.widget.user.anonymous', OneGo.opencart.processLogoffDynamic);
    },
    processLogoffDynamic: function(){
        OneGo.log('authAgent.processLogoffDynamic', 1);
        OneGo.opencart.logoff(OneGo.opencart.reloadCheckoutOrderInfo);
        // listen for widget login
        OneGo.plugins.authAgent.setListener('onego.widget.user.anonymous', false);
        OneGo.plugins.authAgent.setListener('onego.widget.user.authenticated', OneGo.opencart.processLoginDynamic);
    },
    processAutoLogin: function(){
        if (OneGo.opencart.isAutologinAllowed()) {
            window.location.href = OneGo.config.autologinUri;
        }
    },
    processLogoff: function(){
        window.location.href = OneGo.config.logoffUri;
    },
    reloadCheckoutOrderInfo: function(){
        if ($('#confirm .checkout-content').length && $('#confirm .checkout-content').is(':visible')) {
            $.ajax({
                url: $('base').attr('href') + 'index.php?route=checkout/confirm',
                dataType: 'json',
                success: function(json) {
                    if (json['redirect']) {
                        location = json['redirect'];
                    }	

                    if (json['output']) {
                        $('#confirm .checkout-content').html(json['output']);
                        $('#confirm .checkout-content').slideDown('slow');
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    alert(thrownError);
                }
            });
        }
    },
    reloadPage: function(){
        window.location.href = window.location.href;
    },
    redeemGiftCardAnonymous: function(){
        $.fancybox({
            'width': 800,
            'height': 500,
            'autoScale': true,
            'autoDimensions': true,
            'transitionIn': 'none',
            'transitionOut': 'none',
            'type': 'ajax',
            'href': 'http://opencart/index.php?route=total/onego/redeemgiftcard',
            'onClosed': function() {

            }
        });
    },
    processFundUsage: function(checkboxElement, onSuccess, onError){
        var isChecked = checkboxElement.is(':checked');
        $.ajax({
            url: $('base').attr('href') + 'index.php?route=total/onego/useFunds', 
            type: 'post',
            data: {'use_funds': isChecked},
            dataType: 'json',
            beforeSend: function(jqXHR, settings) {
                OneGo.lib.setAsLoading(checkboxElement);
            },
            success: function(data, textStatus, jqXHR) {
                if (!onSuccess) {
                    OneGo.lib.unsetAsLoading(checkboxElement);
                }
                if (typeof data.error != 'undefined') {
                    checkboxElement.attr('checked', !checkboxElement.is(':checked'));
                } else {
                    checkboxElement.attr('checked', data.status == '1');
                }
                if (onSuccess) {
                    onSuccess(data, textStatus, jqXHR);
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                OneGo.lib.unsetAsLoading(checkboxElement);
                checkboxElement.attr('checked', !isChecked);
                if (onError) {
                    onError(xhr, ajaxOptions, thrownError);
                }
            }
        })
    },
    flashWarningBefore: function(element, message, duration) {
        $('.onego_warning').remove();
        if (typeof duration == 'undefined') {
            duration = 3000;
        }
        var elemId = 'onegowarning' + Math.floor(Math.random() * 100000000);
        var warning = '<div id="'+elemId+'" class="warning onego_warning">'+message+'</div>';
        element.before(warning);
        if (duration) {
            setTimeout("$('#"+elemId+"').fadeOut()", duration);
        }
    },
    promptLogin: function(onSuccess, onCancel, onClose){
        OneGo.opencart.loginPromptSuccess = false;
        $.fancybox({
            'width': 500,
            'height': 380,
            'autoScale': true,
            'autoDimensions': true,
            'transitionIn': 'none',
            'transitionOut': 'none',
            'type': 'iframe',
            'href': OneGo.config.loginUri,
            'onClosed': function() {
                if (OneGo.opencart.loginPromptSuccess) {
                    if (onSuccess) {
                        onSuccess();
                    }
                } else if (onCancel) {
                    onCancel();
                }
                if (onClose) {
                    onClose();
                }
            }
        });
    },
    reloadWidget: function(){
        $('.onego_widget iframe').attr('src', $('.onego_widget iframe').attr('src'));
    },
    autologin: function(callback) {
        if (OneGo.opencart.isAutologinAllowed()) {
            $('iframe#onego_autologin').remove();
            $(document.body).append('<iframe id="onego_autologin" name="onego_autologin" width="0" height="0" frameborder="0" src="'+OneGo.config.autologinUri+'"></iframe>');
            $('iframe#onego_autologin').load(function(){
                callback();
                $('iframe#onego_autologin').remove();
            });
        }
    },
    isAutologinAllowed: function() {
        if (!OneGo.opencart.autologinBlockedUntil) {
            return true;
        }
        var blockedTill = new Date(OneGo.opencart.autologinBlockedUntil).getTime();
        var now = new Date().getTime();
        if (blockedTill > now) {
            OneGo.log('Autologin blocked for '+((blockedTill - now)/1000)+' more seconds');
            return false;
        }
        return true;
    },
    autologinBlockedUntil: false,
    blockAutologin: function(seconds)
    {
        OneGo.opencart.autologinBlockedUntil = new Date().getTime() + seconds;
    },
    logoff: function(callback){
        $.ajax({
            url: OneGo.config.logoffUri, 
            type: 'post',
            data: null,
            dataType: 'json',
            complete: function() {
                callback();
            }
        });
    }
}

OneGo.log = function(msg, level)
{
    if (OneGo.config.debug && (typeof console != 'undefined')) {
        switch (level) {
            case 1:
                console.info(msg);
                break;
            case 2:
                console.warn(msg);
                break;
            case 3:
                console.error(msg);
                break;
            default:
                if (typeof msg == 'object' || typeof msg == 'array') {
                    console.dir(msg);
                } else {
                    console.log(msg);
                }
        }
    }
}


OneGo.lib = {
    setAsLoading: function(element) {
        var curtain = $('<span class="onego_loading_container"></span>')
        element.before(curtain);
        curtain.append(element);
        element.css('visibility', 'hidden');
    },
    unsetAsLoading: function(element) {
        var container = element.parent();
        if (container.hasClass('onego_loading_container')) {
            container.after(element);
            container.remove();
        }
        element.css('visibility', 'visible');
    }
}

// initialize on load
$(document).ready(function(){
    $('input.onego_watermark').focus(function(){
        $(this).addClass('focused');
        $(this).val('');
    })
    $('input.onego_watermark').blur(function(){
        $(this).removeClass('focused');
    })
})