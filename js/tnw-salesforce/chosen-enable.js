document.observe('dom:loaded', function () {
    $$('.chosen-select').each(function (element) {
        /**
         * Why ?..
         */
        element.removeAttribute('disabled');
        new Chosen(element, {width: '280px', allow_single_deselect: true });
    });
});