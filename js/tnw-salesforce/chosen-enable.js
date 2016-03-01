document.observe('dom:loaded', function () {
    $$('.chosen-select').each(function (element) {
        new Chosen(element, {width: '280px', allow_single_deselect: true });
    });
});

FormElementDependenceController.prototype.oldTrackChange
    = FormElementDependenceController.prototype.trackChange;

FormElementDependenceController.prototype.trackChange = function(e, idTo, valuesFrom){
    this.oldTrackChange(e, idTo, valuesFrom);

    //TODO: Fix bug disabled
};