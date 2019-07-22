jQuery(function () {

    const url = DOKU_BASE + 'lib/exe/ajax.php';
    const requestParams = {
        'id':     JSINFO.id,
        'sectok': JSINFO.sectok
    };

    const $actionButtons = jQuery('button.action_button');

    /**
     * Trigger a backend action via AJAX and refresh page on success
     *
     * @param params
     * @returns {*}
     */
    const callBackend = function(params) {
        const mergedParams = jQuery.extend(
            {},
            requestParams,
            params
        );

        return jQuery.ajax({
            url     : url,
            data    : mergedParams,
            type    : 'POST'
        })
            .done(jQuery.proxy(function(response) {
                location.reload();
            }, this))
            .fail(jQuery.proxy(function(xhr) {
                var msg = typeof xhr === 'string' ? xhr : xhr.responseText || xhr.statusText || 'Unknown error';
                alert(msg);
            }, this));
    };

    /**
     * Translated modal buttons
     *
     * @type {*[]}
     */
    const dialogButtons = [
        {
            text: LANG.plugins.tagging.admin_confirm,
            click: function () {
                const actionData = dialog.dialog('option', 'data');
                if (actionData.action === 'delete') {
                    callBackend({call: 'plugin_tagging_delete', tagging: {tid: [actionData.tid]}});
                } else if (actionData.action === 'rename') {
                    const $renameForm = jQuery('#tagging__rename');
                    $renameForm.submit();
                }
            }
        },
        {
            text: LANG.plugins.tagging.admin_cancel,
            click: function () {
                dialog.dialog('close');
            }
        },
    ];

    /**
     * Modal for further user interaction
     *
     * @type {jQuery}
     */
    const dialog = jQuery("#tagging__action-dialog").dialog({
        autoOpen: false,
        height: 400,
        width: 300,
        modal: true,
        buttons: dialogButtons,
        close: function () {
            dialog.html('');
        },
        open: function( event, ui ) {
            dialogHtml();
        }
    });

    /**
     * Injects dialog contents that match the triggered action
     */
    const dialogHtml = function() {

        const actionData = dialog.dialog('option', 'data');
        let $renameForm;

        if (actionData.action === 'delete') {
            dialog.append('<h1>' + LANG.plugins.tagging.admin_delete + ' ' + actionData.tid + '</h1>');
            dialog.append('<p>' + LANG.plugins.tagging.admin_sure + '</p>');
        } else if (actionData.action === 'rename') {
            dialog.append('<h1>' + LANG.plugins.tagging.admin_rename + ' ' + actionData.tid + '</h1>');
            dialog.append('<p>' + LANG.plugins.tagging.admin_newtags + ' </p>');
            dialog.append('<form id="tagging__rename"><input type="text" name="newtags" id="tagging__newtags"></form>');

            $renameForm = jQuery('#tagging__rename');
            $renameForm.on('submit', function( event ) {
                event.preventDefault();
                const newValue = jQuery(this).find('#tagging__newtags').val();
                callBackend({call: 'plugin_tagging_rename', tagging: {oldValue: actionData.tid, newValue: newValue} });
            });
        }
        dialog.append('<p class="warning">' + LANG.plugins.tagging.admin_warning_all + '</p>');
    };

    /**
     * Action buttons open a dialog window
     */
    $actionButtons.click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        dialog.dialog('option', { data: jQuery(this).data() });
        dialog.dialog('open');
    });
});
