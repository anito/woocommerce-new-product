(function ($) {

    var add_cat_new_badge = function () {
        var templ = $('<span class="wc-new-badge badge">' + new_badge_params.label + '</span>');
        $('.product_cat-neu-im-shop').each(function () {
            if($(this).is('li')) {
                $(this).find('img').after(templ.addClass('left'));
            } else {
                if( $('.product-top').length) { // wptouch
                    $('.product-top').prepend(templ.addClass('right'));
                } else {
                    $(this).find('.summary').before(templ.addClass('right'));
                }
            }
        })
    }

    add_cat_new_badge();

})(jQuery)
