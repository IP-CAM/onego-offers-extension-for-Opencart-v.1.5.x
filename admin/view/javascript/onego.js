OneGoOpencart = {}
OneGoOpencart.flashWarningBefore = function(element, message, duration) {
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
}
OneGoOpencart.setAsLoading = function(element) {
    if (!element.parent().hasClass('onego_loading_container')) {
        var curtain = $('<span class="onego_loading_container"></span>')
        element.before(curtain);
        curtain.append(element);
        element.css('visibility', 'hidden');
    }
}
OneGoOpencart.unsetAsLoading = function(element) {
    var container = element.parent();
    if (container.hasClass('onego_loading_container')) {
        container.after(element);
        container.remove();
    }
    element.css('visibility', 'visible');
}
OneGoOpencart.showOrderStatusContainer = function() {
    if (!$('#onego_transaction_status_container').length) {
        if ($('#tab-history').length) {
            var onego_transaction_status_container = $('<tr id="onego_transaction_status_container"></tr>');
            $('select[name=order_status_id]').parent().parent().after(onego_transaction_status_container);
            return onego_transaction_status_container;
        }
    }
}
OneGoOpencart.loadOrderStatus = function(token, orderId){
    if (!$('#onego_transaction_status_container').length) {
        var container = OneGoOpencart.showOrderStatusContainer();
    } else {
        var container = $('#onego_transaction_status_container');
    }
    if (container.length) {
        container.load('index.php?route=total/onego/status&token='+token+'&order_id='+orderId);
    }
}

// ==========

$(document).ready(function(){
    
});