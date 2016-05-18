document.observe('dom:loaded', function () {
    $$('.chosen-select').each(function (element) {
        new Chosen(element, {width: '280px', allow_single_deselect: true, disable_search_threshold: 5 });
    });
});

FormElementDependenceController.prototype.oldTrackChange
    = FormElementDependenceController.prototype.trackChange;

FormElementDependenceController.prototype.trackChange = function(e, idTo, valuesFrom){
    this.oldTrackChange(e, idTo, valuesFrom);
    $(idTo).fire("chosen:updated");
};

Element.prototype.oldToggleValueElements = Element.prototype.toggleValueElements;

Element.prototype.toggleValueElements = function (checkbox, container, excludedElements, checked){
    if(container && checkbox){
        var ignoredElements = [checkbox];
        if (typeof excludedElements != 'undefined') {
            if (Object.prototype.toString.call(excludedElements) != '[object Array]') {
                excludedElements = [excludedElements];
            }
            for (var i = 0; i < excludedElements.length; i++) {
                ignoredElements.push(excludedElements[i]);
            }
        }
        //var elems = container.select('select', 'input');
        var elems = Element.select(container, ['select', 'input', 'textarea', 'button', 'img']);
        var isDisabled = (checked != undefined ? checked : checkbox.checked);
        elems.each(function (elem) {
            if (checkByProductPriceType(elem)) {
                var i = ignoredElements.length;
                while (i-- && elem != ignoredElements[i]);
                if (i != -1) {
                    return;
                }
                elem.disabled = isDisabled;
                if (isDisabled) {
                    elem.addClassName('disabled');
                } else {
                    elem.removeClassName('disabled');
                }
                /**
                 * update chosen widget too
                 * code below is necessary because 'fire' not works with disabled elements
                 */
                if (elem.hasClassName('chosen-select')) {
                    var registry = Element.retrieve(elem, 'prototype_event_registry', $H());
                    var eventObj = registry.get('chosen:updated');

                    if (Object.isUndefined(eventObj)) {
                        return false;
                    }

                    eventObj.pluck('handler').each(function(eventHandler){
                        eventHandler(null);
                    });
                }

                if (elem.nodeName.toLowerCase() == 'img') {
                    isDisabled ? elem.hide() : elem.show();
                }
            }
        });
    }
}