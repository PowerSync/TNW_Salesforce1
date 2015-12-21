document.observe('dom:loaded', function () {
    $$('#nav > li > a > span').each(function(s){
        if(s.innerHTML == 'Salesforce'){
		    s.addClassName('tnw-salesforce-primary-menu-icon');
            s.up().addClassName('tnw-salesforce-primary-menu-item');
	    }
    });
    $$('#nav > li > ul > li > a > span').each(function(s){
        if(s.innerHTML == 'Salesforce'){
            s.addClassName('tnw-salesforce-secondary-menu-icon');
        }
    });
    $$('#product_info_tabs > li > a[title="Salesforce"] > span').each(function(s){
        s.addClassName('tnw-salesforce-secondary-menu-icon');
    });
    $$('#import_form > .fileUpload > .upload').each(function(s){
        s.observe('change', function(event) {
            var element = Event.element(event);
            element.up('span').previous('input').value = element.value;
        });
    });
});
