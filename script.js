addInitEvent(function() {
    var form = $('tagging_edit');
    if (!form) return;

    var input = form.getElementsByTagName('input')[2];
    if (typeof addAutoCompletion !== undefined && typeof addAutoCompletion !== "undefined") {
        addAutoCompletion(input, 'plugin_tagging_auto', true, null, function (ul, input) {
            // Hack the styling. This only looks nice in ICKE template
            if (navigator.userAgent.indexOf('MSIE') != -1 &&
                {6:1,7:1}[parseFloat(navigator.userAgent.substring(navigator.userAgent.indexOf('MSIE')+5))] === 1) {
                /*IE 6 & 7*/
                ul.style.top = (input.offsetHeight + 5) + 'px';
                ul.style.left = -input.offsetWidth + 'px';
            } else {
                ul.style.top = '-7px';
            }
            ul.style.minWidth = (input.offsetWidth - 9) + 'px';
        });
    }

    var buttons = getElementsByClass('btn_tagging_edit', document, 'form');

    for (var i = 0; i < buttons.length ; ++i) {
        addEvent(buttons[i], 'submit', function () {
            this.style.display = 'none';
            form.style.display = 'inline';
            input.focus();
            if (!input.value.match(/(^|, )$/)) {
                input.value += ', ';
            }
            return false;
        });
    }

    addEvent($('tagging_edit_save'), 'click', function () {
        form.previousSibling.style.display = 'inline';
        form.style.display = 'none';
        var ajax = doku_ajax('plugin_tagging_save', serialize_form(form));
        ajax.elementObj = form.previousSibling.previousSibling;
        ajax.runAJAX();
        return false;
    });

    addEvent($('tagging_edit_cancel'), 'click', function () {
        form.previousSibling.style.display = 'inline';
        form.style.display = 'none';
        return false;
    });
});

jQuery(function() {
    var availableTags = [];

    jQuery(".tagslist").each(function(i, selected){
        availableTags[i] = jQuery(selected).text();

    });

    jQuery("#tags").autocomplete({
        source: availableTags
    });
});