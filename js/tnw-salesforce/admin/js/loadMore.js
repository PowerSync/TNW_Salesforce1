var tnwSalesforceLoadMore = (function() {
    var config = new Hash({
        page        : 1,
        url         : '',
        container   : '',
        insertion   : 'top'
    });

    return {
        setConfig: function(_config) {
            config.update(_config);
        },

        loadMode: function() {
            var page = config.get('page');
            config.set('page', ++page);

            new Ajax.Updater(config.get('container'), config.get('url'), {
                parameters: { page: page },
                insertion: config.get('insertion'),
                onComplete: function (response, json) {
                    if (response.responseText.empty()) {
                        alert('End of file');
                    }
                }
            });
        }
    };
})();