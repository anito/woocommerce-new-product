(function ($) {

    var rewrite = function () {
        $('a[href$="neu-im-shop/"]').each(function ($i) {
            $(this).attr('href', '/shop/?new-products=1');
        })
    }

    rewrite();

})(jQuery)
