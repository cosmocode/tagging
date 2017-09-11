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
        toggleClear: function() {
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
        }
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

            /**
             Fired when form is shown
             **/
            this.$div.triggerHandler('show');
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
            var params = $.extend({}, this.options.params, {oldValue: this.options.value, newValue: newValue});
            return $.ajax({
                url     : this.options.url,
                data    : params,
                type    : 'POST'
            })
                .done($.proxy(function(response) {
                    this.isSaving = false;

                    if (response.status === 'error') {
                        this.showForm();
                        return;
                    }
                    this.error(false);
                    this.options.value = newValue;
                    if (typeof this.options.success === 'function') {
                        this.options.success.call(this.options.scope, response, newValue);
                    }

                    this.$div.triggerHandler('save', {newValue: newValue, response: response});
                }, this))
                .fail($.proxy(function(xhr) {
                    this.isSaving = false;

                    var msg = typeof xhr === 'string' ? xhr : xhr.responseText || xhr.statusText || 'Unknown error';

                    this.error(msg);
                    this.showForm();

                }, this));
        }
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

            if (!this.options.disabled) {
                this.$element.addClass('editable-click');
            }

            this.$popup = null;

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

                    if($target.is('.editable-popup') || $target.parents('.editable-popup').length) {
                        return;
                    }

                    //close all open containers
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
            this.$element.addClass('editable-click');
        },

        /**
         Disables editable
         @method disable()
         **/
        disable: function() {
            this.options.disabled = true;
            this.hide();
            this.$element.removeClass('editable-click');
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
            this.$popup = $('<div>')
                .addClass('ui-tooltip ui-corner-all ui-widget-shadow ui-widget ui-widget-content editable-popup')
                .css('max-width', 'none') //remove ui-tooltip max-width property
                .prependTo('body')
                .hide()
                .fadeIn();

            this.$popup.append($('<label>').text(this.options.label));

            this.$form_container = $('<div>');
            this.$popup.append(this.$form_container);

            //firstly bind the events
            this.$form_container.on({
                save: $.proxy(function(){ this.hide(); }, this), //click on submit button (value changed)
                nochange: $.proxy(function(){ this.hide(); }, this), //click on submit button (value NOT changed)
                cancel: $.proxy(function(){ this.hide(); }, this), //click on cancel button
                show: $.proxy(function() {
                    this.$popup.position({
                        of: this.$element,
                        my: 'center bottom-5',
                        at: 'center top',
                        collision: 'flipfit'
                    });
                }, this)
            });

            //render form
            this.form = new EditableForm(this.$form_container, {
                value   : this.value,
                url     : this.options.url,
                success : this.options.success,
                scope   : this.options.scope,
                params  : this.options.params
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

            this.$popup.fadeOut({
                complete: $.proxy(function() {
                    this.$popup.remove();
                    this.$popup = null;
                    this.$element.removeClass('editable-open');
                }, this)
            });
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
        }
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
        disabled : false,
        label    : 'Enter value',
        success  : null, //success callback
        scope    : null, //success calback scope
        params   : {}    //additional params passed to ajax post request
    };
}(window.jQuery));
