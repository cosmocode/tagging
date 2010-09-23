addInitEvent(function() {
    var form = $('tagging_edit');
    if (!form) return;

    var input = form.getElementsByTagName('input')[2];
    if (typeof addAutoCompletion !== undefined) {
        addAutoCompletion(input, 'plugin_tagging_auto', true, null, function (ul, input) {
            // Overwrite to fix the width
            ul.style.top = (input.offsetTop + input.offsetHeight - 1) + 'px';
            ul.style.left = input.offsetLeft + 'px';
            ul.style.minWidth = (input.offsetWidth - 8) + 'px';
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
