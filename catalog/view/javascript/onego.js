if (typeof 'OneGo' == 'undefined') {
    if (console) console.error('OneGo library not loaded');
}

OneGoOpencart = {
    config: {
        debug: true,
        loginUri: $('base').attr('href') + 'index.php?route=total/onego/logindialog',
        autologinUri: $('base').attr('href') + 'index.php?route=total/onego/autologin',
        logoffUri: $('base').attr('href') + 'index.php?route=total/onego/cancel',
        agreeRegisterUri: $('base').attr('href') + 'index.php?route=total/onego/agree',
        transactionRefreshUri: $('base').attr('href') + 'index.php?route=total/onego/refreshtransaction'
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
    reloadCheckoutOrderInfo: function(warnCartChange){
        if ($('#confirm .checkout-content').length && $('#confirm .checkout-content').is(':visible')) {
            $.ajax({
                url: $('base').attr('href') + 'index.php?route=checkout/confirm',
                data: {
                    'warn_change': warnCartChange || 0,
                    'cart_hash': $('#onego_cart_hash').val()
                },
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
    promptAnonymousGiftCardRedeem: function(){
        $.fancybox({
            'width': 800,
            'height': 500,
            'autoScale': true,
            'autoDimensions': true,
            'transitionIn': 'none',
            'transitionOut': 'none',
            'type': 'ajax',
            'href': 'http://opencart/index.php?route=total/onego/confirmredeemgiftcard',
            'onClosed': function() {

            }
        });
    },
    redeemGiftCard: function(cardNumber, onSuccess, onError) {
        $.ajax({
            url: $('base').attr('href') + 'index.php?route=total/onego/redeemgiftcard', 
            type: 'post',
            data: {cardnumber: cardNumber},
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                if (data.error) {
                    onError(data.message);
                } else {
                    if (onSuccess) {
                        onSuccess(data);
                    }
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                if (onError) {
                    onError();
                }
            }
        })
    },
    spendPrepaid: function(doSpend, onSuccess, onError){
        $.ajax({
            url: $('base').attr('href') + 'index.php?route=total/onego/spendprepaid', 
            type: 'post',
            data: {'use_funds': doSpend},
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                if (data.error) {
                    if (onError) {
                        onError(data.message, data.error);
                    }
                } else {
                    if (onSuccess) {
                        onSuccess(data);
                    }
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                if (onError) {
                    onError();
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
        OneGoOpencart.loginPromptSuccess = false;
        OneGoOpencart.openPopup(500, 380, 'onego_login', OneGoOpencart.config.loginUri,
            function() {
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
        if (!element.parent().hasClass('onego_loading_container')) {
            var curtain = $('<span class="onego_loading_container"></span>')
            element.before(curtain);
            curtain.append(element);
            element.css('visibility', 'hidden');
        }
    },
    unsetAsLoading: function(element) {
        var container = element.parent();
        if (container.hasClass('onego_loading_container')) {
            container.after(element);
            container.remove();
        }
        element.css('visibility', 'visible');
    },
    openPopup: function(width, height, name, uri, onClose)
    {
        var centerWidth = (window.screen.width - width) / 2;
        var centerHeight = (window.screen.height - height) / 2;

        var popup = window.open(uri, name, 'resizable=0,width=' + width + 
            ',height=' + height + 
            ',left=' + centerWidth + 
            ',top=' + centerHeight + ',modal=yes'+
            'toolbar=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=no');
        popup.focus();
        
        if (onClose) {
            var timer = setInterval(function(){
                if (popup.closed) {
                    clearInterval(timer);  
                    onClose();
                }
            }, 100);
        }
    },
    catchOrderConfirmAction: function()
    {
        if ($('#confirm .payment').length) {
            var originalConfirmButton = $('#confirm .payment .buttons a');
            if (originalConfirmButton.length == 1) {
                var onegoButton = originalConfirmButton.clone(false);
                originalConfirmButton.hide().after(onegoButton);
                onegoButton.bind('click', function(){
                    OneGoOpencart.setAsLoading(onegoButton);
                    OneGoOpencart.refreshTransaction(
                        function() {
                            originalConfirmButton.trigger('click');
                        },
                        function() {
                            OneGoOpencart.reloadCheckoutOrderInfo(true);
                        }
                    );
                })
            } else {
                OneGoOpencart.warnExtensionIncompatible();
            }
        }
    },
    refreshTransaction: function(onSuccess, onError)
    {
        function error() {
            if (onError) onError();
        }
        function ok() {
            if (onSuccess) onSuccess();
        }
        $.ajax({
            url: OneGoOpencart.config.transactionRefreshUri,
            type: 'post',
            data: null,
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    ok();
                } else {
                    error();
                }
            },
            error: function() {
                error();
            }
        });
    },
    transactionAutorefreshTimeout: false,
    setTransactionAutorefresh: function(timeoutFirst, timeoutNext)
    {
        OneGoOpencart.resetTransactionAutorefresh();
        function refreshTransactionSilent()
        {
            OneGoOpencart.refreshTransaction();
            if (OneGoOpencart.transactionAutorefreshTimeout) {
                OneGoOpencart.transactionAutorefreshTimeout = setTimeout(refreshTransactionSilent, timeoutNext);
            }
        }
        OneGoOpencart.transactionAutorefreshTimeout = setTimeout(refreshTransactionSilent, timeoutFirst);
    },
    resetTransactionAutorefresh: function()
    {
        if (OneGoOpencart.transactionAutorefreshTimeout) {
            clearTimeout(OneGoOpencart.transactionAutorefreshTimeout);
        }
    },
    warnExtensionIncompatible: function()
    {
        if (console.error) {
            console.error('OneGo extension is not compatible with selected payment type and may not function correctly!');
        } else {
            alert('OneGo extension is not compatible with selected payment type and may not function correctly!');
        }
    }
}