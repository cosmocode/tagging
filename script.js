/* DOKUWIKI:include script/editable.js */

jQuery(function () {

    /**
     * Add JavaScript confirmation to the User Delete button
     */
    jQuery('#tagging__del').click(function () {
        return confirm(LANG.del_confirm);
    });

    var $form = jQuery('#tagging__edit');
    if (!$form.length) return;

    var $btn = jQuery('form.btn_tagging_edit');
    var $btns = jQuery('#tagging__edit_buttons_group');

    $btn.submit(function (e) {
        $btns.hide();
        $form.show();
        var $input = $form.find('textarea');
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

    var $admin_toggle_btn = jQuery('#tagging__edit_toggle_admin')
            .checkboxradio()
            .click(function () {
                jQuery('div.plugin_tagging_edit ul.tagging_cloud a').editable('toggleDisabled');
            }),
        add_editable = function () {
            //no editable button - we are not the admin
            if ($admin_toggle_btn.length === 0) return;

            jQuery('div.plugin_tagging_edit ul.tagging_cloud a')
                .editable({
                    disabled: !$admin_toggle_btn[0].checked,
                    label:    LANG.plugins.tagging.admin_change_tag,
                    url:      DOKU_BASE + 'lib/exe/ajax.php?call=plugin_tagging_admin_change',
                    params:   {
                        'call':   'plugin_tagging_admin_change',
                        'id':     JSINFO.id,
                        'sectok': JSINFO.sectok
                    },
                    success:
                              function (response) {
                                  jQuery('div.plugin_tagging_edit ul.tagging_cloud').html(response.html_cloud);
                                  $form.find('textarea').val(response.tags_edit_value);
                                  add_editable();
                              }
                });
        };

    add_editable();

    jQuery('#tagging__edit_save').click(function (e) {
        jQuery('div.plugin_tagging_edit ul.tagging_cloud').load(
            DOKU_BASE + 'lib/exe/ajax.php',
            $form.serialize(),
            add_editable
        );
        $btns.show();
        $form.hide();

        e.preventDefault();
        e.stopPropagation();
        return false;
    });

    jQuery('#tagging__edit_cancel').click(function (e) {
        $btns.show();
        $form.hide();

        e.preventDefault();
        e.stopPropagation();
        return false;
    });

    jQuery('.btn_tagging_edit button, #tagging__edit_save, #tagging__edit_cancel').button();

    /**
     * below follows auto completion as described on  http://jqueryui.com/autocomplete/#multiple-remote
     */

    function split(val) {
        return val.split(/,\s*/);
    }

    function extractLast(term) {
        return split(term).pop();
    }

    $form.find('textarea')
    // don't navigate away from the field on tab when selecting an item
        .bind('keydown', function (event) {
            if (event.keyCode === jQuery.ui.keyCode.TAB &&
                jQuery(this).data('ui-autocomplete').menu.active) {
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
            focus:  function () {
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
                terms.push('');
                this.value = terms.join(', ');
                return false;
            }
        });
});
