jQuery(function () {

    var $form = jQuery('#tagging__edit').hide();
    if (!$form.length) return;

    var $btn = jQuery('form.btn_tagging_edit');

    $btn.submit(function (e) {
        $btn.hide();
        $form.show();
        var $input = $form.find('input[type="text"]');
        var len = $input.val().length;
        $input.focus();
        try {
            $input[0].setSelectionRange(len, len);
        } catch (ex) {
            // ignore stupid IE
        }

        e.preventDefault();
        e.stopPropagation();
        return false;
    });

    jQuery('#tagging__edit_save').click(function (e) {
        jQuery('div.plugin_tagging_edit ul.tagging_cloud').load(
            DOKU_BASE + 'lib/exe/ajax.php',
            $form.serialize()
        );
        $btn.show();
        $form.hide();

        e.preventDefault();
        e.stopPropagation();
        return false;
    });

    jQuery('#tagging__edit_cancel').click(function (e) {
        $btn.show();
        $form.hide();

        e.preventDefault();
        e.stopPropagation();
        return false;
    });


    /**
     * below follows auto completion as described on  http://jqueryui.com/autocomplete/#multiple-remote
     */

    function split(val) {
        return val.split(/,\s*/);
    }

    function extractLast(term) {
        return split(term).pop();
    }

    $form.find('input[type="text"]')
        // don't navigate away from the field on tab when selecting an item
        .bind("keydown", function (event) {
            if (event.keyCode === jQuery.ui.keyCode.TAB &&
                jQuery(this).data("ui-autocomplete").menu.active) {
                event.preventDefault();
            }
        })
        .autocomplete({
            source: function (request, response) {
                jQuery.getJSON(DOKU_BASE + 'lib/exe/ajax.php?call=plugin_tagging_autocomplete', {
                    term: extractLast(request.term)
                }, response);
            },
            search: function () {
                // custom minLength
                var term = extractLast(this.value);
                if (term.length < 2) {
                    return false;
                }
                return true;
            },
            focus: function () {
                // prevent value inserted on focus
                return false;
            },
            select: function (event, ui) {
                var terms = split(this.value);
                // remove the current input
                terms.pop();
                // add the selected item
                terms.push(ui.item.value);
                // add placeholder to get the comma-and-space at the end
                terms.push("");
                this.value = terms.join(", ");
                return false;
            }
        });
});
