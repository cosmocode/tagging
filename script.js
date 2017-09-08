(function ($) {
    "use strict";
    var TextInput = function (div, options) {
        this.$div = $(div);
        this.options = options;
        
        this.init();
    };

    TextInput.prototype = {
        init: function() {
            this.$input = $('<input type="text">').val(this.options.defaultValue).appendTo(this.$div);
            this.renderClear();
        },
        
        activate: function() {
            if(this.$input.is(':visible')) {
                this.$input.focus();
                
                var pos = this.$input.val().length;
                this.$input.get(0).setSelectionRange(pos, pos);
                this.toggleClear();
            }
        },
        
        //render clear button
        renderClear:  function() {
           this.$clear = $('<span class="editable-clear-x"></span>');
           this.$input.after(this.$clear)
                      .css('padding-right', 24)
                      .keyup($.proxy(function(e) {
                          //arrows, enter, tab, etc
                          if(~$.inArray(e.keyCode, [40,38,9,13,27])) {
                            return;
                          }                            

                          clearTimeout(this.t);
                          var that = this;
                          this.t = setTimeout(function() {
                            that.toggleClear(e);
                          }, 100);
                          
                      }, this))
                      .parent().css('position', 'relative');
                      
           this.$clear.click($.proxy(this.clear, this));
           
        },
        
        //show / hide clear button
        toggleClear: function(e) {
            var len = this.$input.val().length,
                visible = this.$clear.is(':visible');
            
            if(len && !visible) {
                this.$clear.show();
            } 
            
            if(!len && visible) {
                this.$clear.hide();
            } 
        },
        
        clear: function() {
           this.$clear.hide();
           this.$input.val('').focus();
        },
    };
    
    var EditableForm = function (div, options) {
        this.$div = $(div); //div, containing form. Not form tag. Not editable-element.
        this.options = options;
        this.init();
    };

    EditableForm.prototype = {
        template : '<form class="form-inline editableform">'+
                        '<div class="control-group">' + 
                        '<div><div class="editable-input"></div><div class="editable-buttons"></div></div>'+
                        '<div class="editable-error-block"></div>' + 
                        '</div>' + 
                    '</form>',
        //loading div
        loading: '<div class="editableform-loading"></div>',

        //buttons
        buttons: '<button type="submit" class="editable-submit">ok</button>'+
                 '<button type="button" class="editable-cancel">cancel</button>', 
   
        /**
        Renders editableform
        @method render
        **/        
        init: function() {
            //init loader
            this.$loading = $(this.loading);
            this.$div.empty().append(this.$loading);        
            
            //init form template and buttons
            this.$form = $(this.template); 
    
            var $btn = this.$form.find('.editable-buttons');
            $btn.append(this.buttons);

            this.$form.find('.editable-submit').button({
                icons: { primary: "ui-icon-check" },
                text: false
            });
            this.$form.find('.editable-cancel').button({
                icons: { primary: "ui-icon-closethick" },
                text: false
            });           

            //render: get input.$input
            //append input to form
            this.input = new TextInput(this.$form.find('div.editable-input'), {defaultValue: this.options.value});
                      
            //flag showing is form now saving value to server. 
            //It is needed to wait when closing form.
            this.isSaving = false;
            
            //append form to container
            this.$div.append(this.$form);
            
            this.$form.find('.editable-cancel').click($.proxy(this.cancel, this));
            
            //attach submit handler
            this.$form.submit($.proxy(this.submit, this));
            
            //show form
            this.showForm(); 
        },
        cancel: function() {   
            /**        
            Fired when form was cancelled by user
            @event cancel 
            @param {Object} event event object
            **/              
            this.$div.triggerHandler('cancel');
        },
        showLoading: function() {
            var w, h;
            //set loading size equal to form
            w = this.$form.outerWidth();
            h = this.$form.outerHeight(); 
            
            if(w) {
                this.$loading.width(w);
            }
            if(h) {
                this.$loading.height(h);
            }
            this.$form.hide();
            
            this.$loading.show(); 
        },

        showForm: function() {
            this.$loading.hide();
            this.$form.show();
     
            this.input.activate(); 
        },

        error: function(msg) {
            if(msg === false) {
                this.$form.find('.editable-error-block').removeClass('ui-state-error').empty().hide(); 
            } else {
                this.$form.find('.editable-error-block').addClass('ui-state-error').html(msg).show();
            }
        },

        submit: function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            //get new value from input
            var newValue = this.input.$input.val();
            
            if (newValue === this.options.value) {
                this.$div.triggerHandler('nochange');            
                return;
            }
            
            this.showLoading();

            this.isSaving = true;
            
             //sending data to server
            var params = {oldValue: this.options.value, newValue: newValue};
            return $.ajax({
                        url     : this.options.url,
                        data    : params,
                        type    : 'POST',
                    })
                .done($.proxy(function(response) {
                    this.isSaving = false;
                    console.log(response);
                    if (response.status === 'error') {
                        this.error(response.msg);
                        this.showForm();
                        return;
                    }
                    this.error(false);   
                    this.value = newValue;
                    
                    this.$div.triggerHandler('save', {newValue: newValue, response: response});
                }, this))
                .fail($.proxy(function(xhr) {
                    this.isSaving = false;

                    var msg = typeof xhr === 'string' ? xhr : xhr.responseText || xhr.statusText || 'Unknown error';

                    this.error(msg);
                    this.showForm();
                    
                }, this));
        },           
    };

    
     var Editable = function (element, options) {
        this.$element = jQuery(element);
        this.options = jQuery.extend({}, jQuery.fn.editable.defaults, options);  
        this.init();
    };

    Editable.prototype = { 
        isVisible: function() {
            return this.$element.hasClass('editable-open');
        },
                
        init: function () {
            this.value = this.$element.text();
            //add 'editable' class to every editable element
            this.$element.addClass('editable');
             
            this.$element.tooltip({
                items: '*',
                content: ' ',
                track:  false,
                open: $.proxy(function() {
                        this.$element.off('mouseleave focusout');
                 }, this)
            });
            //disable standart triggering tooltip events
            this.$element.off('mouseover focusin');
            
            this.$element.on('click.editable', $.proxy(function(e){
                //prevent following link if editable enabled
                if(!this.options.disabled) {
                    e.preventDefault();
                }
                
                this.toggle();
            }, this));
            
            if(!$(document).data('editable-handlers-attached')) {
                //close all on escape
                $(document).on('keyup.editable', $.proxy(function (e) {
                    if (e.keyCode === jQuery.ui.keyCode.ESCAPE) {
                        this.closeOthers(null);
                    }
                }, this));

                //close containers when click outside 
                //(mousedown could be better than click, it closes everything also on drag drop)
                $(document).on('click.editable', function(e) {
                    var $target = $(e.target);
                    
                    if($target.is('.ui-tooltip') || $target.parents('.ui-tooltip').length) {
                        return;
                    }
                    //close all open containers (except one - target)
                    Editable.prototype.closeOthers(e.target);
                });
                
                $(document).data('editable-handlers-attached', true);
            }   
        },
        
        /*
        Closes other containers except one related to passed element. 
        Other containers are canceled
        */
        closeOthers: function(element) {
            $('.editable-open').each(function(i, el){
                //do nothing with passed element and it's children
                if(el === element || $(el).find(element).length) {
                    return;
                }
                
                 $(el).data('editable').hide();
            });

        },
        
        /**
        Enables editable
        @method enable()
        **/          
        enable: function() {
            this.options.disabled = false;
            this.$element.removeClass('editable-disabled');
            if(this.$element.attr('tabindex') === '-1') {    
                this.$element.removeAttr('tabindex');                                
            }
        },
        
        /**
        Disables editable
        @method disable()
        **/         
        disable: function() {
            this.options.disabled = true; 
            this.hide();           
            this.$element.addClass('editable-disabled');
            //do not stop focus on this element
            this.$element.attr('tabindex', -1);                
        },
        
        /**
        Toggles enabled / disabled state of editable element
        @method toggleDisabled()
        **/         
        toggleDisabled: function() {
            if(this.options.disabled) {
                this.enable();
            } else { 
                this.disable(); 
            }
        },
                
        /**
        Shows container with form
        @method show()
        **/  
        show: function () {
            if(this.options.disabled) {
                return;
            }
            this.$element.addClass('editable-open');
            
            //redraw element
            var $content = $('<div>');
            
            //append elements to dom so they are visible
            this.$element.tooltip('option', 'content', $content);
            
            //open tooltip
            this.$element.tooltip('open');
            
            $content.append($('<label>').text(this.options.label));
                        
            this.$form_container = $('<div>');
            $content.append(this.$form_container);
            
            //render form
            this.form = new EditableForm(this.$form_container, {
                value: this.value,
                url: this.options.url
            });
            
            this.$form_container.on({
                save: $.proxy(function(){ this.hide(); }, this), //click on submit button (value changed)
                nochange: $.proxy(function(){ this.hide(); }, this), //click on submit button (value NOT changed)                
                cancel: $.proxy(function(){ this.hide(); }, this), //click on cancel button
            });
                        
            
            
        },
        
        /**
        Hides container with form
        @method hide()
        **/       
        hide: function () {   
            if (!this.isVisible()) {
                return;
            }
            
            //if form is saving value, schedule hide
            if(this.form.isSaving) {
                return;    
            }
            
            this.$element.removeClass('editable-open');   
            this.$element.tooltip('close');
        },
        /**
        Toggles container visibility (show / hide)
        @method toggle()
        **/  
        toggle: function() {
            if(this.isVisible()) {
                this.hide();
            } else {
                this.show();
            }
        },
    };
    
    $.fn.editable = function (option) {
        var datakey = 'editable';
        //return jquery object
        return this.each(function () {
            var $this = $(this), 
                data = $this.data(datakey),
                options = typeof option === 'object' && option;
                
            if (!data) {
                $this.data(datakey, (data = new Editable(this, options)));
            }
            
            if (typeof option === 'string') { //call method 
                data[option].apply(data, Array.prototype.slice.call(arguments, 1));
            } 
        });
    };
    
    $.fn.editable.defaults = {
        disabled: false,
        label   : 'Enter value'
    };
}(window.jQuery));   

jQuery(function () {
    
    /**
     * Add JavaScript confirmation to the User Delete button
     */
    jQuery('#tagging__del').click(function(){
        return confirm(LANG.del_confirm);
    });

    var $form = jQuery('#tagging__edit').hide();
    if (!$form.length) return;

    var $btn = jQuery('form.btn_tagging_edit');
    var $btns = jQuery('#tagging__edit_buttons_group');

    $btn.submit(function (e) {
        $btns.hide();
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
    
    var $admin_toggle_btn = jQuery('#tagging__edit_toggle_admin')
                                .checkboxradio()
                                .click(function(){
                                    jQuery('div.plugin_tagging_edit ul.tagging_cloud a').editable('toggleDisabled');
                                }),
        add_editable = function() {
            jQuery('div.plugin_tagging_edit ul.tagging_cloud a')
                .editable({
                    disabled  : !$admin_toggle_btn[0].checked,
                    label     : LANG.plugins.tagging.admin_change_tag,
                    url       : DOKU_BASE + 'lib/exe/ajax.php?call=plugin_tagging_admin_change'
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
                    term: extractLast(request.term),
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
            },
        });
});
