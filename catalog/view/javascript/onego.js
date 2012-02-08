if (typeof 'OneGo' == 'undefined') {
    if (console) console.error('OneGo library not loaded');
}

OneGoOpencart = {
    config: {
        debug: true,
        loginUri: $('base').attr('href') + 'index.php?route=total/onego/loginDialog',
        autologinUri: $('base').attr('href') + 'index.php?route=total/onego/autologin',
        logoffUri: $('base').attr('href') + 'index.php?route=total/onego/cancel',
        widgetUri: $('base').attr('href') + 'index.php?route=total/onego/widget',
        agreeRegisterUri: $('base').attr('href') + 'index.php?route=total/onego/agree'
    },
    loginPromptSuccess: false,
    processLoginDynamic: function(){
        OneGoOpencart.autologin(function(){
            OneGoOpencart.reloadCheckoutOrderInfo();
            // listen for widget logoff
            OneGo.events.reset('UserIsSignedIn');
            OneGo.events.on('UserIsSignedOut', OneGoOpencart.processLogoffDynamic);
        });
    },
    processLogoffDynamic: function(){
        OneGoOpencart.logoff(function(){
            OneGoOpencart.reloadCheckoutOrderInfo();
            // listen for widget login
            OneGo.events.reset('UserIsSignedOut');
            OneGo.events.on('UserIsSignedIn', OneGoOpencart.processLoginDynamic);
        });
    },
    processAutoLogin: function(){
        if (OneGoOpencart.isAutologinAllowed()) {
            window.location.href = OneGoOpencart.config.autologinUri;
        }
    },
    processLogoff: function(){
        window.location.href = OneGoOpencart.config.logoffUri;
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
    redeemGiftCard: function(cardNumber, onSuccess, onError) {
        var redeemButton = $('#onego_giftcard_redeem');
        $.ajax({
            url: $('base').attr('href') + 'index.php?route=total/onego/redeemgiftcard', 
            type: 'post',
            data: { cardnumber: cardNumber },
            dataType: 'json',
            beforeSend: function(jqXHR, settings) {
                OneGoOpencart.setAsLoading(redeemButton);
            },
            success: function(data, textStatus, jqXHR) {
                if (data.error) {
                    onError(data.message);
                } else {
                    if (onSuccess) {
                        onSuccess(data);
                    }
                }
                OneGoOpencart.unsetAsLoading(redeemButton);
            },
            error: function(xhr, ajaxOptions, thrownError) {
                if (onError) {
                    onError();
                }
                OneGoOpencart.unsetAsLoading(redeemButton);
            }
        })
    },
    spendPrepaid: function(checkboxElement, onSuccess, onError){
        var isChecked = checkboxElement.is(':checked');
        $.ajax({
            url: $('base').attr('href') + 'index.php?route=total/onego/spendprepaid', 
            type: 'post',
            data: {'use_funds': isChecked},
            dataType: 'json',
            beforeSend: function(jqXHR, settings) {
                OneGoOpencart.setAsLoading(checkboxElement);
            },
            success: function(data, textStatus, jqXHR) {
                if (data.error) {
                    if (onError) {
                        onError(data.message, data.error);
                    }
                    checkboxElement.attr('checked', !isChecked);
                } else {
                    if (onSuccess) {
                        onSuccess(data);
                    }
                }
                OneGoOpencart.unsetAsLoading(checkboxElement);
            },
            error: function(xhr, ajaxOptions, thrownError) {
                if (onError) {
                    onError();
                }
                OneGoOpencart.unsetAsLoading(checkboxElement);
                checkboxElement.attr('checked', !isChecked);
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
        OneGoOpencart.loginPromptSuccess = false;
        $.fancybox({
            'width': 500,
            'height': 380,
            'autoScale': true,
            'autoDimensions': true,
            'transitionIn': 'none',
            'transitionOut': 'none',
            'type': 'iframe',
            'href': OneGoOpencart.config.loginUri,
            'onClosed': function() {
                if (OneGoOpencart.loginPromptSuccess) {
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
    promptLogin2: function(onSuccess, onCancel, onClose)
    {
        OneGo.user.authorizeFull(
            function() { alert('autorization success') },
            function() { alert('autorization canceled') },
            function() { alert('autorization error') }
        );
    },
    reloadWidget: function(){
        $('.onego_widget iframe').attr('src', $('.onego_widget iframe').attr('src'));
    },
    autologin: function(callback) {
        if (OneGoOpencart.isAutologinAllowed()) {
            $('iframe#onego_autologin').remove();
            $(document.body).append('<iframe id="onego_autologin" name="onego_autologin" width="0" height="0" frameborder="0" src="'+OneGoOpencart.config.autologinUri+'"></iframe>');
            $('iframe#onego_autologin').load(function(){
                callback();
                $('iframe#onego_autologin').remove();
            });
        }
    },
    isAutologinAllowed: function() {
        if (!OneGoOpencart.autologinBlockedUntil) {
            return true;
        }
        var blockedTill = new Date(OneGoOpencart.autologinBlockedUntil).getTime();
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
        OneGoOpencart.autologinBlockedUntil = new Date().getTime() + seconds;
    },
    logoff: function(callback){
        $.ajax({
            url: OneGoOpencart.config.logoffUri, 
            type: 'post',
            data: null,
            dataType: 'json',
            complete: function() {
                callback();
            }
        });
    },
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