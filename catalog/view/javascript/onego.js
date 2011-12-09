var OneGo = {
    config: {
        debug: true
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
                        if ((typeof source_origin === 'string' && e.origin !== source_origin)
                        || (Object.prototype.toString.call(source_origin) === "[object Function]" && source_origin(e.origin) === !1)) {
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
        OneGo.authAgent.initAuthWidget();
        OneGo.authAgent.initListeners();
        if (OneGo.authAgent.isAutologinAttemptExpected) {
            OneGo.authAgent.attemptAutologin();
        }
    },
    initAuthWidget: function() {
        if ($('#onego_authwidget_container').length) {
            $('#onego_authwidget_container').html('<div id="onego_authwidget_loading">'+$('#onego_authwidget_container').html()+'</div>');
            $('#onego_authwidget_container').append('<iframe id="onego_authwidget" name="onego_hidden_iframe" src="'+OneGo.authAgent.url_full+'" width="100%" height="100%" frameborder="0" allowtransparency="true" style="display: none;"></iframe>');
            $('#onego_authwidget').load(function(e){$('#onego_authwidget_loading').fadeOut('fast', function(){$('#onego_authwidget').fadeIn('fast');});})
        } else {
            var url = OneGo.authAgent.url_full + '&h=1';
            $('body').append('<iframe id="onego_authwidget" name="onego_authwidget" src="'+url+'" width="0" height="0" frameborder="0"></iframe>');
        }
    },
    initListeners: function(){
        OneGo.XDC.receiveMessage(function(authWidgetMessage){
            OneGo.log('Message received from OneGo authWidget: '+authWidgetMessage.data);
            var str = [];
            for (var msg in OneGo.authAgent.handlers) {
                str.push(msg);
            }
            OneGo.log('Listening for messages: '+str.join('; '));
            
            for (var msg in OneGo.authAgent.handlers) {
                if ((msg == authWidgetMessage.data) && OneGo.authAgent.handlers[msg]) {
                    OneGo.authAgent.handlers[msg]();
                }
            }
        }, OneGo.authAgent.url);
    },
    login_url: false,
    logoff_url: false,
    autologin: function(callback) {
        if (OneGo.authAgent.login_url && OneGo.authAgent.isAutologinAllowed()) {
            $('iframe#onego_autologin').remove();
            //$(document.body).append('<iframe id="onego_autologin" name="onego_autologin" width="100%" height="30" frameborder="1"></iframe>');
            $(document.body).append('<iframe id="onego_autologin" name="onego_autologin" width="0" height="0" frameborder="0"></iframe>');
            $('iframe#onego_autologin').attr('src', OneGo.authAgent.login_url);
            $('iframe#onego_autologin').load(function(){
                callback();
                $('iframe#onego_autologin').remove();
            });
        }
    },
    logoff: function(callback){
        if (OneGo.authAgent.logoff_url) {
            $.ajax({
                url: OneGo.authAgent.logoff_url, 
                type: 'post',
                data: null,
                dataType: 'json',
                complete: function() {
                    callback();
                }
            });
        }
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

OneGo.opencart = {
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
                url: 'index.php?route=checkout/confirm',
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

// initialize on load
$(document).ready(function(){
    OneGo.authAgent.init();
    OneGo.decorator.init();
    
    $('input.watermark').focus(function(){
        $(this).addClass('focused');
        $(this).val('');
    })
    $('input.watermark').blur(function(){
        $(this).removeClass('focused');
    })
})
