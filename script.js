
//~ (function ($) {
    //~ "use strict";
    
    //~ //types
    //~ $.fn.editableTypes = {};
    
   

    //~ $.fn.editableTypes.text = Text;

//~ }(window.jQuery));

/**
Form with single input element, two buttons and two states: normal/loading.
Applied as jQuery method to DIV tag (not to form tag!). This is because form can be in loading state when spinner shown.
Editableform is linked with one of input types, e.g. 'text', 'select' etc.
@class editableform
@uses text
@uses textarea
**/
(function ($) {
    "use strict";
    
     var TextInput = function (options) {
         this.options = options;
        //this.init('text', options, Text.defaults);
    };

    TextInput.prototype = {
        prerender: function() {
            this.$input = $('<input type="text">');
        },
        
        render: function() {
           this.renderClear();
           //~ this.setClass();
           //~ this.setAttr('placeholder');
        },
        
        activate: function() {
            if(this.$input.is(':visible')) {
                this.$input.focus();
                //~ if (this.$input.is('input,textarea') && !this.$input.is('[type="checkbox"],[type="range"]')) {
                    $.fn.editableutils.setCursorPosition(this.$input.get(0), this.$input.val().length);
                //~ }
                //~ if(this.toggleClear) {
                    this.toggleClear();
                //~ }
            }
        },
        
        //render clear button
        renderClear:  function() {
           //~ if (this.options.clear) {
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
           //~ }            
        },
        
        //show / hide clear button
        toggleClear: function(e) {
            if(!this.$clear) {
                return;
            }
            
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
        //~ this.options = $.extend({}, $.fn.editableform.defaults, options);
        this.options = options;
        this.$div = $(div); //div, containing form. Not form tag. Not editable-element.
        //~ if(!this.options.scope) {
            //~ this.options.scope = this;
        //~ }
        //nothing shown after init
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
        //~ constructor: EditableForm,
        initInput: function() {  //called once
            //take input from options (as it is created in editable-element)
            this.input = this.options.input;
            
            //set initial value
            //todo: may be add check: typeof str === 'string' ? 
            //~ this.value = this.input.str2value(this.options.value); 
            
            //prerender: get input.$input
            this.input.prerender();
        },
        initTemplate: function() {
            this.$form = $(this.template); 
        },
        initButtons: function() {
            var $btn = this.$form.find('.editable-buttons');
            $btn.append(this.buttons);
            //~ if(this.options.showbuttons === 'bottom') {
                //~ $btn.addClass('editable-buttons-bottom');
            //~ }
                          
            this.$form.find('.editable-submit').button({
                icons: { primary: "ui-icon-check" },
                text: false
            }).removeAttr('title');
            this.$form.find('.editable-cancel').button({
                icons: { primary: "ui-icon-closethick" },
                text: false
            }).removeAttr('title');
        },
        /**
        Renders editableform
        @method render
        **/        
        render: function() {
            //init loader
            this.$loading = $(this.loading);        
            this.$div.empty().append(this.$loading);
            
            //init form template and buttons
            this.initTemplate();
            //~ if(this.options.showbuttons) {
                this.initButtons();
            //~ } else {
                //~ this.$form.find('.editable-buttons').remove();
            //~ }

            //show loading state
            this.showLoading();            
            
            //flag showing is form now saving value to server. 
            //It is needed to wait when closing form.
            this.isSaving = false;
            
            /**        
            Fired when rendering starts
            @event rendering 
            @param {Object} event event object
            **/            
            this.$div.triggerHandler('rendering');
            
            //init input
            this.initInput();
            
            //append input to form
            this.$form.find('div.editable-input').append(this.input.$input);            
            
            //append form to container
            this.$div.append(this.$form);
            
            //render input
            $.when(this.input.render())
            .then($.proxy(function () {
                //setup input to submit automatically when no buttons shown
                //~ if(!this.options.showbuttons) {
                    //~ this.input.autosubmit(); 
                //~ }
                 
                //attach 'cancel' handler
                this.$form.find('.editable-cancel').click($.proxy(this.cancel, this));
                
                if(this.input.error) {
                    this.error(this.input.error);
                    this.$form.find('.editable-submit').attr('disabled', true);
                    this.input.$input.attr('disabled', true);
                    //prevent form from submitting
                    this.$form.submit(function(e){ e.preventDefault(); });
                } else {
                    this.error(false);
                    this.input.$input.removeAttr('disabled');
                    this.$form.find('.editable-submit').removeAttr('disabled');
                    var value = (this.value === null || this.value === undefined || this.value === '') ? this.options.defaultValue : this.value;
                    this.input.value2input(value);
                    //attach submit handler
                    this.$form.submit($.proxy(this.submit, this));
                }

                /**        
                Fired when form is rendered
                @event rendered
                @param {Object} event event object
                **/            
                this.$div.triggerHandler('rendered');                

                this.showForm();
                
                //call postrender method to perform actions required visibility of form
                if(this.input.postrender) {
                    this.input.postrender();
                }                
            }, this));
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
            if(this.$form) {
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
            } else {
                //stretch loading to fill container width
                w = this.$loading.parent().width();
                if(w) {
                    this.$loading.width(w);
                }
            }
            this.$loading.show(); 
        },

        showForm: function(activate) {
            this.$loading.hide();
            this.$form.show();
            if(activate !== false) {
                this.input.activate(); 
            }
            /**        
            Fired when form is shown
            @event show 
            @param {Object} event event object
            **/                    
            this.$div.triggerHandler('show');
        },

        //~ error: function(msg) {
            //~ var $group = this.$form.find('.control-group'),
                //~ $block = this.$form.find('.editable-error-block'),
                //~ lines;

            //~ if(msg === false) {
                //~ $group.removeClass($.fn.editableform.errorGroupClass);
                //~ $block.removeClass($.fn.editableform.errorBlockClass).empty().hide(); 
            //~ } else {
                //~ //convert newline to <br> for more pretty error display
                //~ if(msg) {
                    //~ lines = (''+msg).split('\n');
                    //~ for (var i = 0; i < lines.length; i++) {
                        //~ lines[i] = $('<div>').text(lines[i]).html();
                    //~ }
                    //~ msg = lines.join('<br>');
                //~ }
                //~ $group.addClass($.fn.editableform.errorGroupClass);
                //~ $block.addClass($.fn.editableform.errorBlockClass).html(msg).show();
            //~ }
        //~ },

        submit: function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            //get new value from input
            var newValue = this.input.input2value(); 

            //validation: if validate returns string or truthy value - means error
            //if returns object like {newValue: '...'} => submitted value is reassigned to it
            var error = this.validate(newValue);
            if ($.type(error) === 'object' && error.newValue !== undefined) {
                newValue = error.newValue;
                this.input.value2input(newValue);
                if(typeof error.msg === 'string') {
                    this.error(error.msg);
                    this.showForm();
                    return;
                }
            } else if (error) {
                this.error(error);
                this.showForm();
                return;
            } 
            
            //if value not changed --> trigger 'nochange' event and return
            /*jslint eqeq: true*/
            if (!this.options.savenochange && this.input.value2str(newValue) === this.input.value2str(this.value)) {
            /*jslint eqeq: false*/                
                /**        
                Fired when value not changed but form is submitted. Requires savenochange = false.
                @event nochange 
                @param {Object} event event object
                **/                    
                this.$div.triggerHandler('nochange');            
                return;
            } 

            //convert value for submitting to server
            var submitValue = this.input.value2submit(newValue);
            
            this.isSaving = true;
            
            //sending data to server
            $.when(this.save(submitValue))
            .done($.proxy(function(response) {
                this.isSaving = false;

                //run success callback
                var res = typeof this.options.success === 'function' ? this.options.success.call(this.options.scope, response, newValue) : null;

                //if success callback returns false --> keep form open and do not activate input
                if(res === false) {
                    this.error(false);
                    this.showForm(false);
                    return;
                }

                //if success callback returns string -->  keep form open, show error and activate input               
                if(typeof res === 'string') {
                    this.error(res);
                    this.showForm();
                    return;
                }

                //if success callback returns object like {newValue: <something>} --> use that value instead of submitted
                //it is useful if you want to chnage value in url-function
                if(res && typeof res === 'object' && res.hasOwnProperty('newValue')) {
                    newValue = res.newValue;
                }

                //clear error message
                this.error(false);   
                this.value = newValue;
                /**        
                Fired when form is submitted
                @event save 
                @param {Object} event event object
                @param {Object} params additional params
                @param {mixed} params.newValue raw new value
                @param {mixed} params.submitValue submitted value as string
                @param {Object} params.response ajax response
                @example
                $('#form-div').on('save'), function(e, params){
                    if(params.newValue === 'username') {...}
                });
                **/
                this.$div.triggerHandler('save', {newValue: newValue, submitValue: submitValue, response: response});
            }, this))
            .fail($.proxy(function(xhr) {
                this.isSaving = false;

                var msg;
                if(typeof this.options.error === 'function') {
                    msg = this.options.error.call(this.options.scope, xhr, newValue);
                } else {
                    msg = typeof xhr === 'string' ? xhr : xhr.responseText || xhr.statusText || 'Unknown error!';
                }

                this.error(msg);
                this.showForm();
            }, this));
        },

        save: function(submitValue) {
            //try parse composite pk defined as json string in data-pk 
            this.options.pk = $.fn.editableutils.tryParseJson(this.options.pk, true); 
            
            var pk = (typeof this.options.pk === 'function') ? this.options.pk.call(this.options.scope) : this.options.pk,
            /*
              send on server in following cases:
              1. url is function
              2. url is string AND (pk defined OR send option = always) 
            */
            send = !!(typeof this.options.url === 'function' || (this.options.url && ((this.options.send === 'always') || (this.options.send === 'auto' && pk !== null && pk !== undefined)))),
            params;

            if (send) { //send to server
                this.showLoading();

                //standard params
                params = {
                    name: this.options.name || '',
                    value: submitValue,
                    pk: pk 
                };

                //additional params
                if(typeof this.options.params === 'function') {
                    params = this.options.params.call(this.options.scope, params);  
                } else {
                    //try parse json in single quotes (from data-params attribute)
                    this.options.params = $.fn.editableutils.tryParseJson(this.options.params, true);   
                    $.extend(params, this.options.params);
                }

                if(typeof this.options.url === 'function') { //user's function
                    return this.options.url.call(this.options.scope, params);
                } else {  
                    //send ajax to server and return deferred object
                    return $.ajax($.extend({
                        url     : this.options.url,
                        data    : params,
                        type    : 'POST'
                    }, this.options.ajaxOptions));
                }
            }
        }, 

        //~ validate: function (value) {
            //~ if (value === undefined) {
                //~ value = this.value;
            //~ }
            //~ if (typeof this.options.validate === 'function') {
                //~ return this.options.validate.call(this.options.scope, value);
            //~ }
        //~ },

        //~ option: function(key, value) {
            //~ if(key in this.options) {
                //~ this.options[key] = value;
            //~ }
            
            //~ if(key === 'value') {
                //~ this.setValue(value);
            //~ }
            
            //~ //do not pass option to input as it is passed in editable-element
        //~ },

        setValue: function(value, convertStr) {
            //~ if(convertStr) {
                //~ this.value = this.input.str2value(value);
            //~ } else {
                //~ this.value = value;
            //~ }
            this.value = value;
            //if form is visible, update input
            if(this.$form && this.$form.is(':visible')) {
                this.input.value = this.value;
            }            
        }               
    };

    /*
    Initialize editableform. Applied to jQuery object.
    @method $().editableform(options)
    @params {Object} options
    @example
    var $form = $('&lt;div&gt;').editableform({
        type: 'text',
        name: 'username',
        url: '/post',
        value: 'vitaliy'
    });
    //to display form you should call 'render' method
    $form.editableform('render');     
    */
    //~ $.fn.editableform = function (option) {
        //~ var args = arguments;
        //~ return this.each(function () {
            //~ var $this = $(this), 
            //~ data = $this.data('editableform'), 
            //~ options = typeof option === 'object' && option; 
            //~ if (!data) {
                //~ $this.data('editableform', (data = new EditableForm(this, options)));
            //~ }

            //~ if (typeof option === 'string') { //call method 
                //~ data[option].apply(data, Array.prototype.slice.call(args, 1));
            //~ } 
        //~ });
    //~ };

    //keep link to constructor to allow inheritance
    //~ $.fn.editableform.Constructor = EditableForm;    

    //defaults
    //~ $.fn.editableform.defaults = {
        //~ /* see also defaults for input */

        //~ /**
        //~ Type of input. Can be <code>text|textarea|select|date|checklist</code>
        //~ @property type 
        //~ @type string
        //~ @default 'text'
        //~ **/
        //~ type: 'text',
        //~ /**
        //~ Url for submit, e.g. <code>'/post'</code>  
        //~ If function - it will be called instead of ajax. Function should return deferred object to run fail/done callbacks.
        //~ @property url 
        //~ @type string|function
        //~ @default null
        //~ @example
        //~ url: function(params) {
            //~ var d = new $.Deferred;
            //~ if(params.value === 'abc') {
                //~ return d.reject('error message'); //returning error via deferred object
            //~ } else {
                //~ //async saving data in js model
                //~ someModel.asyncSaveMethod({
                   //~ ..., 
                   //~ success: function(){
                      //~ d.resolve();
                   //~ }
                //~ }); 
                //~ return d.promise();
            //~ }
        //~ } 
        //~ **/        
        //~ url:null,
        //~ /**
        //~ Additional params for submit. If defined as <code>object</code> - it is **appended** to original ajax data (pk, name and value).  
        //~ If defined as <code>function</code> - returned object **overwrites** original ajax data.
        //~ @example
        //~ params: function(params) {
            //~ //originally params contain pk, name and value
            //~ params.a = 1;
            //~ return params;
        //~ }
        //~ @property params 
        //~ @type object|function
        //~ @default null
        //~ **/          
        //~ params:null,
        //~ /**
        //~ Name of field. Will be submitted on server. Can be taken from <code>id</code> attribute
        //~ @property name 
        //~ @type string
        //~ @default null
        //~ **/         
        //~ name: null,
        //~ /**
        //~ Primary key of editable object (e.g. record id in database). For composite keys use object, e.g. <code>{id: 1, lang: 'en'}</code>.
        //~ Can be calculated dynamically via function.
        //~ @property pk 
        //~ @type string|object|function
        //~ @default null
        //~ **/         
        //~ pk: null,
        //~ /**
        //~ Initial value. If not defined - will be taken from element's content.
        //~ For __select__ type should be defined (as it is ID of shown text).
        //~ @property value 
        //~ @type string|object
        //~ @default null
        //~ **/        
        //~ value: null,
        //~ /**
        //~ Value that will be displayed in input if original field value is empty (`null|undefined|''`).
        //~ @property defaultValue 
        //~ @type string|object
        //~ @default null
        //~ @since 1.4.6
        //~ **/        
        //~ defaultValue: null,
        //~ /**
        //~ Strategy for sending data on server. Can be `auto|always|never`.
        //~ When 'auto' data will be sent on server **only if pk and url defined**, otherwise new value will be stored locally.
        //~ @property send 
        //~ @type string
        //~ @default 'auto'
        //~ **/          
        //~ send: 'auto', 
        //~ /**
        //~ Function for client-side validation. If returns string - means validation not passed and string showed as error.
        //~ Since 1.5.1 you can modify submitted value by returning object from `validate`: 
        //~ `{newValue: '...'}` or `{newValue: '...', msg: '...'}`
        //~ @property validate 
        //~ @type function
        //~ @default null
        //~ @example
        //~ validate: function(value) {
            //~ if($.trim(value) == '') {
                //~ return 'This field is required';
            //~ }
        //~ }
        //~ **/         
        //~ validate: null,
        //~ /**
        //~ Success callback. Called when value successfully sent on server and **response status = 200**.  
        //~ Usefull to work with json response. For example, if your backend response can be <code>{success: true}</code>
        //~ or `{success: false, msg: "server error"}` you can check it inside this callback.  
        //~ If it returns **string** - means error occured and string is shown as error message.  
        //~ If it returns **object like** `{newValue: &lt;something&gt;}` - it overwrites value, submitted by user
        //~ (useful when server changes value).  
        //~ Otherwise newValue simply rendered into element.
        
        //~ @property success 
        //~ @type function
        //~ @default null
        //~ @example
        //~ success: function(response, newValue) {
            //~ if(!response.success) return response.msg;
        //~ }
        //~ **/          
        //~ success: null,
        //~ /**
        //~ Error callback. Called when request failed (response status != 200).  
        //~ Usefull when you want to parse error response and display a custom message.
        //~ Must return **string** - the message to be displayed in the error block.
                
        //~ @property error 
        //~ @type function
        //~ @default null
        //~ @since 1.4.4
        //~ @example
        //~ error: function(response, newValue) {
            //~ if(response.status === 500) {
                //~ return 'Service unavailable. Please try later.';
            //~ } else {
                //~ return response.responseText;
            //~ }
        //~ }
        //~ **/          
        //~ error: null,
        //~ /**
        //~ Additional options for submit ajax request.
        //~ List of values: http://api.jquery.com/jQuery.ajax
        
        //~ @property ajaxOptions 
        //~ @type object
        //~ @default null
        //~ @since 1.1.1        
        //~ @example 
        //~ ajaxOptions: {
            //~ type: 'put',
            //~ dataType: 'json'
        //~ }        
        //~ **/        
        //~ ajaxOptions: null,
        //~ /**
        //~ Where to show buttons: left(true)|bottom|false  
        //~ Form without buttons is auto-submitted.
        //~ @property showbuttons 
        //~ @type boolean|string
        //~ @default true
        //~ @since 1.1.1
        //~ **/         
        //~ showbuttons: true,
        //~ /**
        //~ Scope for callback methods (success, validate).  
        //~ If <code>null</code> means editableform instance itself. 
        //~ @property scope 
        //~ @type DOMElement|object
        //~ @default null
        //~ @since 1.2.0
        //~ @private
        //~ **/            
        //~ scope: null,
        //~ /**
        //~ Whether to save or cancel value when it was not changed but form was submitted
        //~ @property savenochange 
        //~ @type boolean
        //~ @default false
        //~ @since 1.2.0
        //~ **/
        //~ savenochange: false
    //~ };   

    /*
    Note: following params could redefined in engine: bootstrap or jqueryui:
    Classes 'control-group' and 'editable-error-block' must always present!
    */      
    //~ $.fn.editableform.template = '<form class="form-inline editableform">'+
    //~ '<div class="control-group">' + 
    //~ '<div><div class="editable-input"></div><div class="editable-buttons"></div></div>'+
    //~ '<div class="editable-error-block"></div>' + 
    //~ '</div>' + 
    //~ '</form>';

    //~ //loading div
    //~ $.fn.editableform.loading = '<div class="editableform-loading"></div>';

    //~ //buttons
    //~ $.fn.editableform.buttons = '<button type="submit" class="editable-submit">ok</button>'+
    //~ '<button type="button" class="editable-cancel">cancel</button>';      

    //error class attached to control-group
    //~ $.fn.editableform.errorGroupClass = null;  

    //~ //error class attached to editable-error-block
    //~ $.fn.editableform.errorBlockClass = 'ui-state-error';
    
    //~ //engine
    //~ $.fn.editableform.engine = 'jquery-ui';

/**
Attaches stand-alone container with editable-form to HTML element. Element is used only for positioning, value is not stored anywhere.<br>
This method applied internally in <code>$().editable()</code>. You should subscribe on it's events (save / cancel) to get profit of it.<br>
Final realization can be different: bootstrap-popover, jqueryui-tooltip, poshytip, inline-div. It depends on which js file you include.<br>
Applied as jQuery method.
@class editableContainer
@uses editableform
**/

    var EditableContainer = function (element, options) {
        this.init(element, options);
    };
    
    //~ var Inline = function (element, options) {
        //~ this.init(element, options);
    //~ };    

    //methods
    EditableContainer.prototype = {
        //~ containerName: 'tooltip', //method to call container on element
        //~ containerDataName: 'ui-tooltip', //object name in element's .data()
        innerCss: '.ui-tooltip-content', //tbd in child class
        //~ containerClass: 'editable-container editable-popup', //css class applied to container element
        formOptions: {}, //container itself defaults
        
        init: function(element, options) {
            this.$element = $(element);
            //since 1.4.1 container do not use data-* directly as they already merged into options.
            //~ this.options = $.extend({}, $.fn.editableContainer.defaults, options);         
            this.options = options//$.fn.editableContainer.defaults; //Gandhis HACK
            //~ this.splitOptions();
            
            //set scope of form callbacks to element
            //~ this.formOptions.scope = this.$element[0]; 
            this.formOptions.input = this.options.input;
            
            this.initContainer();
            
            //flag to hide container, when saving value will finish
            //~ this.delayedHide = false;

            //bind 'destroyed' listener to destroy container when element is removed from dom
            this.$element.on('destroyed', $.proxy(function(){
                this.destroy();
            }, this)); 
            
            //attach document handler to close containers on click / escape
            if(!$(document).data('editable-handlers-attached')) {
                //close all on escape
                $(document).on('keyup.editable', function (e) {
                    if (e.which === 27) {
                        $('.editable-open').editableContainer('hide');
                        //todo: return focus on element 
                    }
                });

                //close containers when click outside 
                //(mousedown could be better than click, it closes everything also on drag drop)
                $(document).on('click.editable', function(e) {
                    var $target = $(e.target), i,
                        exclude_classes = ['.editable-container', 
                                           '.ui-datepicker-header', 
                                           '.datepicker', //in inline mode datepicker is rendered into body
                                           '.modal-backdrop', 
                                           '.bootstrap-wysihtml5-insert-image-modal', 
                                           '.bootstrap-wysihtml5-insert-link-modal'
                                           ];

                    // select2 has extra body click in IE
                    // see: https://github.com/ivaynberg/select2/issues/1058 
                    //~ if ($('.select2-drop-mask').is(':visible')) {
                        //~ return;
                    //~ }

                    //check if element is detached. It occurs when clicking in bootstrap datepicker
                    //~ if (!$.contains(document.documentElement, e.target)) {
                        //~ return;
                    //~ }

                    //for some reason FF 20 generates extra event (click) in select2 widget with e.target = document
                    //we need to filter it via construction below. See https://github.com/vitalets/x-editable/issues/199
                    //Possibly related to http://stackoverflow.com/questions/10119793/why-does-firefox-react-differently-from-webkit-and-ie-to-click-event-on-selec
                    //~ if($target.is(document)) {
                        //~ return;
                    //~ }
                    
                    //if click inside one of exclude classes --> no nothing
                    for(i=0; i<exclude_classes.length; i++) {
                         if($target.is(exclude_classes[i]) || $target.parents(exclude_classes[i]).length) {
                             return;
                         }
                    }
                      
                    //close all open containers (except one - target)
                    EditableContainer.prototype.closeOthers(e.target);
                });
                
                $(document).data('editable-handlers-attached', true);
            }                        
        },

        //split options on containerOptions and formOptions
        //~ splitOptions: function() {
            //~ this.containerOptions = {};
            //~ this.formOptions = {};
            
            //~ //check that jQueryUI build contains tooltip widget
            //~ if(!$.ui[this.containerName]) {
                //~ $.error('Please use jQueryUI with "tooltip" widget! http://jqueryui.com/download');
                //~ return;
            //~ }
            
            //~ //defaults for tooltip
            //~ for(var k in this.options) {
              //~ if(k in this.defaults) {
                 //~ this.containerOptions[k] = this.options[k];
              //~ } else {
                 //~ this.formOptions[k] = this.options[k];
              //~ } 
            //~ }
        //~ },   
        
        /*
        Returns jquery object of container
        @method tip()
        */         
        tip: function() {
            //~ console.log(this.container()._find(this.container().element));
            //~ return this.container() ? this.container()._find(this.container().element) : null;
            //~ console.log(this.container()._find(this.container().element).tooltip);
            //~ return this.container().tooltip;
            return this.container() ? this.container()._find(this.container().element).tooltip : null;
        },

        /* returns container object */
        container: function() {
            return this.$element.data('ui-tooltip');
        },
            //~ var container;
            //~ //first, try get it by `containerDataName`
            //~ if(this.containerDataName) {
                //~ if(container = this.$element.data(this.containerDataName)) {
                    //~ return container;
                //~ }
            //~ }
            //~ //second, try `containerName`
            //~ container = this.$element.data(this.containerName);
            //~ return container;
            //~ return this.$element.data(this.containerDataName)
        //~ },

        /* call native method of underlying container, e.g. this.$element.popover('method') */ 
        call: function() {
            this.$element.tooltip.apply(this.$element, arguments); 
        },        
        
        initContainer: function(){
            //~ this.handlePlacement();
            //~ $.extend(this.containerOptions, {
                //~ items: '*',
                //~ content: ' ',
                //~ track:  false,
                //~ open: $.proxy(function() {
                        //~ //disable events hiding tooltip by default
                        //~ this.container()._on(this.container().element, {
                            //~ mouseleave: function(e){ e.stopImmediatePropagation(); },
                            //~ focusout: function(e){ e.stopImmediatePropagation(); }
                        //~ });  
                    //~ }, this)
            //~ });
            
            this.call({
                items: '*',
                content: ' ',
                track:  false,
                open: $.proxy(function() {
                        //disable events hiding tooltip by default
                        this.container()._on(this.container().element, {
                            mouseleave: function(e){ e.stopImmediatePropagation(); },
                            focusout: function(e){ e.stopImmediatePropagation(); }
                        });  
                    }, this)
            });
          
            //disable standart triggering tooltip events
            this.container()._off(this.container().element, 'mouseover focusin');
        }, 

        renderForm: function() {
            //~ this.$form
            //~ .editableform(this.formOptions)
            var form = new EditableForm(this.$form, this.formOptions);
            this.$form.on({
                save: $.proxy(this.save, this), //click on submit button (value changed)
                nochange: $.proxy(function(){ this.hide('nochange'); }, this), //click on submit button (value NOT changed)                
                cancel: $.proxy(function(){ this.hide('cancel'); }, this), //click on cancel button
                show: $.proxy(function() {
                    if(this.delayedHide) {
                        this.hide(this.delayedHide.reason);
                        this.delayedHide = false;
                    } else {
                        this.setPosition();
                    }
                }, this), //re-position container every time form is shown (occurs each time after loading state)
                rendering: $.proxy(this.setPosition, this), //this allows to place container correctly when loading shown
                resize: $.proxy(this.setPosition, this), //this allows to re-position container when form size is changed 
                rendered: $.proxy(function(){
                    /**        
                    Fired when container is shown and form is rendered (for select will wait for loading dropdown options).  
                    **Note:** Bootstrap popover has own `shown` event that now cannot be separated from x-editable's one.
                    The workaround is to check `arguments.length` that is always `2` for x-editable.                     
                    
                    @event shown 
                    @param {Object} event event object
                    @example
                    $('#username').on('shown', function(e, editable) {
                        editable.input.$input.val('overwriting value of input..');
                    });                     
                    **/                      
                    /*
                     TODO: added second param mainly to distinguish from bootstrap's shown event. It's a hotfix that will be solved in future versions via namespaced events.  
                    */
                    this.$element.triggerHandler('shown', $(this.options.scope).data('editable')); 
                }, this) 
            });
            //~ .editableform('render');
            form.render();
        },        

        /**
        Shows container with form
        @method show()
        @param {boolean} closeAll Whether to close all other editable containers when showing this one. Default true.
        **/
        /* Note: poshytip owerwrites this method totally! */          
        show: function () {
            this.$element.addClass('editable-open');
            //~ if(closeAll !== false) {
                //close all open containers (except this)
                this.closeOthers(this.$element[0]);  
            //~ }
            
            //show container itself
            this.call('open');
            //~ var label = this.options.title || this.$element.data( "ui-tooltip-title") || this.$element.data( "originalTitle");
            var label = 'Hello world!'; 
            this.tip().find(this.innerCss).empty().append($('<label>').text(label));
            
            
            //~ this.tip().addClass(this.containerClass);

            /*
            Currently, form is re-rendered on every show. 
            The main reason is that we dont know, what will container do with content when closed:
            remove(), detach() or just hide() - it depends on container.
            
            Detaching form itself before hide and re-insert before show is good solution, 
            but visually it looks ugly --> container changes size before hide.  
            */             
            
            //if form already exist - delete previous data 
            //~ if(this.$form) {
                //todo: destroy prev data!
                //this.$form.destroy();
            //~ }

            this.$form = $('<div>');
            
            //insert form into container body
            //~ if(this.tip().is(this.innerCss)) {
                //for inline container
                //~ this.tip().append(this.$form); 
            //~ } else {
                this.tip().find(this.innerCss).append(this.$form);
            //~ } 
            
            //render form
            this.renderForm();
        },

        /**
        Hides container with form
        @method hide()
        @param {string} reason Reason caused hiding. Can be <code>save|cancel|onblur|nochange|undefined (=manual)</code>
        **/         
        hide: function(reason) {  
            if(!this.tip() || !this.tip().is(':visible') || !this.$element.hasClass('editable-open')) {
                return;
            }
            
            //if form is saving value, schedule hide
            if(this.$form.data('editableform').isSaving) {
                this.delayedHide = {reason: reason};
                return;    
            } else {
                this.delayedHide = false;
            }

            this.$element.removeClass('editable-open');   
            this.call('close'); 

            /**
            Fired when container was hidden. It occurs on both save or cancel.  
            **Note:** Bootstrap popover has own `hidden` event that now cannot be separated from x-editable's one.
            The workaround is to check `arguments.length` that is always `2` for x-editable. 
            @event hidden 
            @param {object} event event object
            @param {string} reason Reason caused hiding. Can be <code>save|cancel|onblur|nochange|manual</code>
            @example
            $('#username').on('hidden', function(e, reason) {
                if(reason === 'save' || reason === 'cancel') {
                    //auto-open next editable
                    $(this).closest('tr').next().find('.editable').editable('show');
                } 
            });
            **/
            this.$element.triggerHandler('hidden', reason || 'manual');   
        },

        /* internal show method. To be overwritten in child classes */
        //~ innerShow: function() {
            //~ this.call('open');
            //~ var label = this.options.title || this.$element.data( "ui-tooltip-title") || this.$element.data( "originalTitle"); 
            //~ this.tip().find(this.innerCss).empty().append($('<label>').text(label));
        //~ },        

        /* internal hide method. To be overwritten in child classes */
        //~ innerHide: function() {
            //~ this.call('close'); 
        //~ },
        
        /**
        Toggles container visibility (show / hide)
        @method toggle()
        @param {boolean} closeAll Whether to close all other editable containers when showing this one. Default true.
        **/          
        toggle: function(closeAll) {
            if(this.container() && this.tip() && this.tip().is(':visible')) {
                this.hide();
            } else {
                this.show(closeAll);
            } 
        },

        /*
        Updates the position of container when content changed.
        @method setPosition()
        */       
        setPosition: function() {
            this.tip().position({
              of: this.$element,
              my: "center bottom-5", 
              at: "center top", 
              collision: 'flipfit'
            }); 
            
        },

        save: function(e, params) {
            /**        
            Fired when new value was submitted. You can use <code>$(this).data('editableContainer')</code> inside handler to access to editableContainer instance
            
            @event save 
            @param {Object} event event object
            @param {Object} params additional params
            @param {mixed} params.newValue submitted value
            @param {Object} params.response ajax response
            @example
            $('#username').on('save', function(e, params) {
                //assuming server response: '{success: true}'
                var pk = $(this).data('editableContainer').options.pk;
                if(params.response && params.response.success) {
                    alert('value: ' + params.newValue + ' with pk: ' + pk + ' saved!');
                } else {
                    alert('error!'); 
                } 
            });
            **/             
            this.$element.triggerHandler('save', params);
            
            //hide must be after trigger, as saving value may require methods of plugin, applied to input
            this.hide('save');
        },

        /**
        Sets new option
        
        @method option(key, value)
        @param {string} key 
        @param {mixed} value 
        **/         
        //~ option: function(key, value) {
            //~ this.options[key] = value;
            //~ if(key in this.containerOptions) {
                //~ this.containerOptions[key] = value;
                //~ this.setContainerOption(key, value); 
            //~ } else {
                //~ this.formOptions[key] = value;
                //~ if(this.$form) {
                    //~ this.$form.editableform('option', key, value);  
                //~ }
            //~ }
        //~ },
        
        //~ setContainerOption: function(key, value) {
            //~ this.call('option', key, value);
        //~ },

        /**
        Destroys the container instance
        @method destroy()
        **/        
        destroy: function() {
            this.hide();
            //~ this.innerDestroy();
            this.$element.off('destroyed');
            this.$element.removeData('editableContainer');
        },
        
        /* to be overwritten in child classes */
        //~ innerDestroy: function() {
            //~ /* tooltip destroys itself on hide */
        //~ }, 
        
        /*
        Closes other containers except one related to passed element. 
        Other containers can be cancelled or submitted (depends on onblur option)
        */
        closeOthers: function(element) {
            $('.editable-open').each(function(i, el){
                //do nothing with passed element and it's children
                if(el === element || $(el).find(element).length) {
                    return;
                }

                //otherwise cancel or submit all open containers 
                var $el = $(el),
                ec = $el.data('editableContainer');

                if(!ec) {
                    return;  
                }
                
                if(ec.options.onblur === 'cancel') {
                    $el.data('editableContainer').hide('onblur');
                } else if(ec.options.onblur === 'submit') {
                    $el.data('editableContainer').tip().find('form').submit();
                }
            });

        },
        
        /**
        Activates input of visible container (e.g. set focus)
        @method activate()
        **/         
        activate: function() {
            if(this.tip && this.tip().is(':visible') && this.$form) {
               this.$form.data('editableform').input.activate(); 
            }
        }
    };

    /**
    jQuery method to initialize editableContainer.
    
    @method $().editableContainer(options)
    @params {Object} options
    @example
    $('#edit').editableContainer({
        type: 'text',
        url: '/post',
        pk: 1,
        value: 'hello'
    });
    **/  
    //~ $.fn.editableContainer = function (option) {
        //~ var args = arguments;
        //~ return this.each(function () {
            //~ var $this = $(this),
            //~ data = $this.data('editableContainer'),            
            //~ options = typeof option === 'object' && option;
            //~ Constructor = (options.mode === 'inline') ? Inline : Popup;             

            //~ if (!data) {
                //~ $this.data(dataKey, (data = new EditableContainer(this, options)));
            //~ }

            //~ if (typeof option === 'string') { //call method 
                //~ data[option].apply(data, Array.prototype.slice.call(args, 1));
            //~ }            
        //~ });
    //~ };     

    //store constructors
    //~ $.fn.editableContainer.Popup = Popup;
    //~ $.fn.editableContainer.Inline = Inline;

    //defaults
    //~ $.fn.editableContainer.defaults = {
        //~ /**
        //~ Initial value of form input
        //~ @property value 
        //~ @type mixed
        //~ @default null
        //~ @private
        //~ **/        
        //~ value: null,
        //~ /**
        //~ Placement of container relative to element. Can be <code>top|right|bottom|left</code>. Not used for inline container.
        //~ @property placement 
        //~ @type string
        //~ @default 'top'
        //~ **/        
        //~ placement: 'top',
        //~ /**
        //~ Whether to hide container on save/cancel.
        //~ @property autohide 
        //~ @type boolean
        //~ @default true
        //~ @private 
        //~ **/        
        //~ autohide: true,
        //~ /**
        //~ Action when user clicks outside the container. Can be <code>cancel|submit|ignore</code>.  
        //~ Setting <code>ignore</code> allows to have several containers open. 
        //~ @property onblur 
        //~ @type string
        //~ @default 'cancel'
        //~ @since 1.1.1
        //~ **/        
        //~ onblur: 'cancel',
        
        //~ /**
        //~ Animation speed (inline mode only)
        //~ @property anim 
        //~ @type string
        //~ @default false
        //~ **/        
        //~ anim: false,
        
        //~ /**
        //~ Mode of editable, can be `popup` or `inline` 
        
        //~ @property mode 
        //~ @type string         
        //~ @default 'popup'
        //~ @since 1.4.0        
        //~ **/        
        //~ mode: 'popup'        
    //~ };

    /* 
    * workaround to have 'destroyed' event to destroy popover when element is destroyed
    * see http://stackoverflow.com/questions/2200494/jquery-trigger-event-when-an-element-is-removed-from-the-dom
    */
    //~ jQuery.event.special.destroyed = {
        //~ remove: function(o) {
            //~ if (o.handler) {
                //~ o.handler();
            //~ }
        //~ }
    //~ };    

//~ }(window.jQuery));

//~ (function ($) {
    //~ "use strict";
    var Editable = function (element, options) {
        this.$element = jQuery(element);
        this.options = jQuery.extend({}, jQuery.fn.editable.defaults, options);  
        this.init();
    };

    Editable.prototype = {
        init: function () {
            this.input = new TextInput();
            this.value = this.$element.text();
            //add 'editable' class to every editable element
            this.$element.addClass('editable');
            this.$element.on('click.editable', $.proxy(function(e){
                //prevent following link if editable enabled
                if(!this.options.disabled) {
                    e.preventDefault();
                }
                
                //stop propagation not required because in document click handler it checks event target
                //e.stopPropagation();
                
                //~ if(this.options.toggle === 'mouseenter') {
                    //~ //for hover only show container
                    //~ this.show();
                //~ } else {
                    //~ //when toggle='click' we should not close all other containers as they will be closed automatically in document click listener
                    //~ var closeAll = (this.options.toggle !== 'click');
                    //~ this.toggle(closeAll);
                //~ }
                this.toggle();
            }, this));
        },
        /**
        Enables editable
        @method enable()
        **/          
        enable: function() {
            this.options.disabled = false;
            this.$element.removeClass('editable-disabled');
            //~ this.handleEmpty(this.isEmpty);
            //~ if(this.options.toggle !== 'manual') {
                if(this.$element.attr('tabindex') === '-1') {    
                    this.$element.removeAttr('tabindex');                                
                }
            //~ }
        },
        
        /**
        Disables editable
        @method disable()
        **/         
        disable: function() {
            this.options.disabled = true; 
            this.hide();           
            this.$element.addClass('editable-disabled');
            //~ this.handleEmpty(this.isEmpty);
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
            
            //init editableContainer: popover, tooltip, inline, etc..
            if(!this.container) {
                //~ var containerOptions = $.extend({}, this.options, );
                //~ this.$element.editableContainer({
                    //~ value: this.value,
                    //~ input: this.input //pass input to form (as it is already created)
                //~ });
                
                var editableContainer = new EditableContainer(this.$element, {
                    value: this.value,
                    input: this.input //pass input to form (as it is already created)
                });
                //listen `save` event 
                this.$element.on("save.internal", $.proxy(this.save, this));
                //~ this.container = this.$element.data('editableContainer');
                this.container = editableContainer;
            } else if(this.container.tip().is(':visible')) {
                return;
            }      
            
            //show container
            this.container.show();
        },
        
        /**
        Hides container with form
        @method hide()
        **/       
        hide: function () {   
            if(this.container) {  
                this.container.hide();
            }
        },
        /**
        Toggles container visibility (show / hide)
        @method toggle()
        **/  
        toggle: function() {
            if(this.container && this.container.tip().is(':visible')) {
                this.hide();
            } else {
                this.show();
            }
        },
        
        /*
        * called when form was submitted
        */          
        save: function(e, params) {
            //mark element with unsaved class if needed
            //~ if(this.options.unsavedclass) {
                //~ /*
                 //~ Add unsaved css to element if:
                  //~ - url is not user's function 
                  //~ - value was not sent to server
                  //~ - params.response === undefined, that means data was not sent
                  //~ - value changed 
                //~ */
                //~ var sent = false;
                //~ sent = sent || typeof this.options.url === 'function';
                //~ sent = sent || this.options.display === false; 
                //~ sent = sent || params.response !== undefined; 
                //~ sent = sent || (this.options.savenochange && this.input.value2str(this.value) !== this.input.value2str(params.newValue)); 
                
                //~ if(sent) {
                    //~ this.$element.removeClass(this.options.unsavedclass); 
                //~ } else {
                    //~ this.$element.addClass(this.options.unsavedclass);                    
                //~ }
            //~ }
            
            //~ //highlight when saving
            //~ if(this.options.highlight) {
                //~ var $e = this.$element,
                    //~ bgColor = $e.css('background-color');
                    
                //~ $e.css('background-color', this.options.highlight);
                //~ setTimeout(function(){
                    //~ if(bgColor === 'transparent') {
                        //~ bgColor = ''; 
                    //~ }
                    //~ $e.css('background-color', bgColor);
                    //~ $e.addClass('editable-bg-transition');
                    //~ setTimeout(function(){
                       //~ $e.removeClass('editable-bg-transition');  
                    //~ }, 1700);
                //~ }, 10);
            //~ }
            
            //~ //set new value
            //~ this.setValue(params.newValue, false, params.response);
            
            /**        
            Fired when new value was submitted. You can use <code>$(this).data('editable')</code> to access to editable instance
            
            @event save 
            @param {Object} event event object
            @param {Object} params additional params
            @param {mixed} params.newValue submitted value
            @param {Object} params.response ajax response
            @example
            $('#username').on('save', function(e, params) {
                alert('Saved value: ' + params.newValue);
            });
            **/
            //event itself is triggered by editableContainer. Description here is only for documentation              
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
        disabled: false
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

    jQuery('#tagging__edit_save').click(function (e) {
        jQuery('div.plugin_tagging_edit ul.tagging_cloud').load(
            DOKU_BASE + 'lib/exe/ajax.php',
            $form.serialize()
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
    
    var $toggle_btn = jQuery('#tagging__edit_toggle_admin').checkboxradio();
    jQuery('.btn_tagging_edit button, #tagging__edit_save, #tagging__edit_cancel').button();
    
    var $tags_cloud = jQuery('.plugin_tagging_edit .tagging_cloud a');
    
    $toggle_btn.click(function(){ $tags_cloud.editable('toggleDisabled'); });
    
    $tags_cloud.editable({disabled: true});
        //~ .click(function(e) {
            //~ var admin = $toggle_btn[0].checked;
            //~ if (admin) {
                //~ e.preventDefault();
            //~ }
        //~ });

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
