_createCookie = function (cookieName, value, minutes) {
    if (minutes) {
        var date = new Date();
        date.setTime(date.getTime() + minutes * 60 * 1000);
        var expires = "; expires = " + date.toGMTString();
    } else {
        var expires = "";
    }

    document.cookie = cookieName + "=" + value + expires + "; path=/";
};

_accessCookie = function (cookieName) {
    var name = cookieName + '=';
    var allCookieArray = document.cookie.split(';');

    for (var i = 0; i < allCookieArray.length; i++) {
        var temp = allCookieArray[i].trim();
        if (temp.indexOf(name) == 0) return temp.substring(name.length, temp.length);
    }

    return '';
};

_deleteCookie = function (cookieName) {
    document.cookie = cookieName + '=; expires = Thu, 01 Jan 1970 00:00:01 GMT; path=/';
};

var minAmtLimit = parseFloat(_accessCookie('minAllowedAmt'));

jQuery( function($) {

    $(document).on('change', '#aprilInstallmentSwitch', function() {
        if ($(this).closest('.april-toggle-container').hasClass('payplan-disabled')) {
            $(this).prop('checked', false);
            var parentLabelElem = $(this).closest('.switch');
            parentLabelElem.addClass("disabled-swt");

            // Reset Toggle Animations;
            setTimeout(function() {
                parentLabelElem.removeClass("disabled-swt");
            }, 610);

            return false;
        }


        if ($(this).prop('checked')) {
            _createCookie('april-preferred-bnpl-option', '1', 120); /* Set cookie for 2 hours */
            $('.april-toggle-container .payment-type').removeClass('active');
            $('.april-toggle-container .payment-type.april-split-payment').addClass('active');
            $('.april-installment-offer__shortcode .april-installment-price').addClass('active');
        } else {
            _deleteCookie('april-preferred-bnpl-option');
            $('.april-toggle-container .payment-type').removeClass('active');
            $('.april-installment-offer__shortcode .april-installment-price').removeClass('active')
            $('.april-toggle-container .payment-type.april-one-time').addClass('active');
        }
    });
});
