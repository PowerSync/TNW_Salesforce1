document.observe('dom:loaded', function () {
    $$('.chosen-select').each(function (element) {
        element.removeAttribute('disabled');
        new Chosen(element, {width: '280px'});
    });
});