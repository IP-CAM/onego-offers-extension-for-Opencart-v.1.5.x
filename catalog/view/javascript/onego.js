var OneGo = {
    config: {
        debug: true,
        loginUri: $('base').attr('href') + 'index.php?route=total/onego/auth2',
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

OneGo.authAgent = {
    url: '',
    url_full: '',
    handlers: {},
    autologinBlockedUntil: false,
    init: function() {
        $('body').append('<iframe id="onego_authagent" name="onego_authagent" src="'+OneGo.authAgent.url_full+'" width="0" height="0" frameborder="0"></iframe>');
        OneGo.authAgent.initListeners();
    },
    initListeners: function(){
        var agent_host = OneGo.authAgent.url.replace(/^([a-z]+:\/\/[^\/]+)(.*)$/i, "\$1");
        OneGo.XDC.receiveMessage(function(authWidgetMessage){
            var str = [];
            for (var msg in OneGo.authAgent.handlers) {
                str.push(msg);
            }
            OneGo.log('Message received from OneGo authWidget: '+authWidgetMessage.data+' / listening for: '+str.join('; '));
            
            for (var msg in OneGo.authAgent.handlers) {
                if ((msg == authWidgetMessage.data) && OneGo.authAgent.handlers[msg]) {
                    OneGo.authAgent.handlers[msg]();
                }
            }
        }, agent_host);
    },
    login_url: $('base').attr('href') + 'index.php?route=total/onego/autologin',
    logoff_url: false,
    autologin: function(callback) {
        if (OneGo.authAgent.isAutologinAllowed()) {
            $('iframe#onego_autologin').remove();
            //$(document.body).append('<iframe id="onego_autologin" name="onego_autologin" width="100%" height="30" frameborder="1"></iframe>');
            $(document.body).append('<iframe id="onego_autologin" name="onego_autologin" width="0" height="0" frameborder="0" src="'+OneGo.config.autologinUri+'"></iframe>');
            $('iframe#onego_autologin').load(function(){
                callback();
                $('iframe#onego_autologin').remove();
            });
        }
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
    },
    setListener: function(message, callback) {
        if (typeof callback == 'undefined' || !callback) {
            delete OneGo.authAgent.handlers[message];
        } else {
            OneGo.authAgent.handlers[message] = callback;
        }
    },
    isAutologinAllowed: function() {
        var blockedTill = new Date(OneGo.authAgent.autologinBlockedUntil).getTime();
        var now = new Date().getTime();
        if (blockedTill > now) {
            OneGo.log('Autologin blocked for '+((blockedTill - now)/1000)+' more seconds');
            return false;
        }
        return true;
    }
}
OneGo.authWidget = {
    url: '',
    init: function() {
        if ($('#onego_authwidget_container').length) {
            $('#onego_authwidget_container').html('<div id="onego_authwidget_loading">'+$('#onego_authwidget_container').html()+'</div>');
            $('#onego_authwidget_container').append('<iframe id="onego_authwidget" name="onego_hidden_iframe" src="'+OneGo.authWidget.url+'" width="100%" height="100%" frameborder="0" allowtransparency="true" style="display: none;"></iframe>');
            $('#onego_authwidget').load(function(e){$('#onego_authwidget_loading').fadeOut('fast', function(){$('#onego_authwidget').fadeIn('fast');});})
        }
    }
}

OneGo.widget = {
    load: function() {
        $('body').append('<div id="onego_widget_container"></div>');
        $('#onego_widget_container').load(OneGo.config.widgetUri, OneGo.widget.onLoad);
    },
    getWidth: function() {
        return $('#onego_widget_container iframe').outerWidth();
        return $('#onego_widget_container .onego_widget>div').outerWidth();
    },
    show: function() {
        $('#onego_widget_container').animate(
            {left: 0},
            200,
            function() {
                $('#onego_widget_container .onego_widget_handle .show').hide();
                $('#onego_widget_container .onego_widget_handle .hide').show();
            }
        );
    },
    hide: function() {
        $('#onego_widget_container').animate(
            {left: 0 - OneGo.widget.getWidth()},
            200,
            function() {
                $('#onego_widget_container .onego_widget_handle .show').show();
                $('#onego_widget_container .onego_widget_handle .hide').hide();
            }
        );
    },
    setTopOffset: function(offset) {
        $('#onego_widget_container').css('top', offset);
    },
    freeze: function() {
        $('#onego_widget_container').css('position', 'fixed');
    },
    onLoad: function() {
        if (!$('#onego_widget_container iframe').length) {
            setTimeout(OneGo.widget.onLoad, 50);
            return false;
        }
        OneGo.widget.updateWidth();
        $('#onego_widget_container iframe').load(OneGo.widget.onComplete);
    },
    onComplete: function() { },
    updateWidth: function() {
        var w = OneGo.widget.getWidth();
        $('#onego_widget_container').css('width', w + $('#onego_widget_container .onego_widget_handle').outerWidth());
        $('#onego_widget_container .onego_widget').css('width', w);
        $('#onego_widget_container').css('left', 0-w);
    }
}

OneGo.opencart = {
    loginPromptSuccess: false,
    processLoginDynamic: function(){
        console.info('OneGo.authAgent.processLoginDynamic');
        OneGo.authAgent.autologin(OneGo.opencart.reloadCheckoutOrderInfo);
        // listen for widget logoff
        OneGo.authAgent.setListener('onego.widget.user.authenticated', false);
        OneGo.authAgent.setListener('onego.widget.user.anonymous', OneGo.opencart.processLogoffDynamic);
    },
    processLogoffDynamic: function(){
        console.info('OneGo.authAgent.processLogoffDynamic');
        OneGo.authAgent.logoff(OneGo.opencart.reloadCheckoutOrderInfo);
        // listen for widget login
        OneGo.authAgent.setListener('onego.widget.user.anonymous', false);
        OneGo.authAgent.setListener('onego.widget.user.authenticated', OneGo.opencart.processLoginDynamic);
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
            url: $('base').attr('href') + 'index.php?route=total/onego/usefunds', 
            type: 'post',
            data: {'use_funds': isChecked},
            dataType: 'json',
            beforeSend: function(jqXHR, settings) {
                OneGo.lib.setAsLoading(checkboxElement);
            },
            complete: function(jqXHR, textStatus) {
                OneGo.lib.unsetAsLoading(checkboxElement);
            },
            success: function(data, textStatus, jqXHR) {
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
                checkboxElement.attr('checked', !isChecked);
                if (onError) {
                    onError(xhr, ajaxOptions, thrownError);
                }
            }
        })
    },
    flashWarningBefore: function(element, message, duration) {
        if (typeof duration == 'undefined') {
            duration = 3000;
        }
        var elemId = 'onegowarning' + Math.floor(Math.random() * 100000000);
        var warning = '<div id="'+elemId+'" class="warning">'+message+'</div>';
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
                    OneGo.opencart.reloadWidget();
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
    }
}

OneGo.decorator = {
    placeholders: {},
    init: function() {
        OneGo.decorator.apply();
    },
    apply: function() {
        OneGo.decorator.replacePlaceholdersIn('div.cart-total td, #confirm .checkout-product td');
    },
    replacePlaceholdersIn: function(element) {
        $(element).each(function(){
            for (var k in OneGo.decorator.placeholders) {
                if ($(this).html() == k) {
                    $(this).html(OneGo.decorator.placeholders[k]);
                }
            }
        });
    }
}

OneGo.log = function(msg)
{
    if (OneGo.config.debug && (typeof console != 'undefined')) {
        console.log(msg);
    }
}


OneGo.lib = {
    setAsLoading: function(element) {
        element.attr('disabled', true);
    },
    unsetAsLoading: function(element) {
        element.attr('disabled', false);
    }
}

// initialize on load
$(document).ready(function(){
    OneGo.authAgent.init();
    OneGo.authWidget.init();
    OneGo.decorator.init();
    
    $('input.watermark').focus(function(){
        $(this).addClass('focused');
        $(this).val('');
    })
    $('input.watermark').blur(function(){
        $(this).removeClass('focused');
    })
})
