document.observe('dom:loaded', function () {
    $$('.chosen-select').each(function (element) {
        new Chosen(element);
    });
});