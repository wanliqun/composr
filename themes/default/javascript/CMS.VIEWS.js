(function ($cms, $util, $dom) { 
    'use strict';

    // List of view options that can be set as properties.
    var viewOptionsList = { el: 1, id: 1, attributes: 1, className: 1, tagName: 1, events: 1 };

    $cms.View = View;
    /**
     * @memberof $cms
     * @class $cms.View
     */
    function View(params, viewOptions) {
        /** @member {number}*/
        this.uid = $util.uid(this);
        /** @member {string} */
        this.tagName = 'div';
        /** @member { HTMLElement } */
        this.el = null;

        this.initialize.apply(this, arguments);
    }

    // Cached regex to split keys for `delegate`.
    var rgxDelegateEventSplitter = /^(\S+)\s*(.*)$/;
    $util.properties(View.prototype, /**@lends $cms.View#*/{
        /**
         * @method
         */
        initialize: function (params, viewOptions) {
            this.params = objVal(params);

            if ($util.isObj(viewOptions)) {
                for (var key in viewOptionsList) {
                    if (key in viewOptions) {
                        this[key] = viewOptions[key];
                    }
                }
            }

            this._ensureElement();
        },
        /**
         * @method
         */
        $: function (selector) {
            return $dom.$(this.el, selector);
        },
        /**
         * @method
         */
        $$: function (selector) {
            return $dom.$$(this.el, selector);
        },
        /**
         * @method
         */
        $$$: function (selector) {
            return $dom.$$$(this.el, selector);
        },
        /**
         * @method
         */
        $closest: function (el, selector) {
            return $dom.closest(el, selector, this.el);
        },

        /**
         * Remove this view by taking the element out of the DOM.
         * @method
         */
        remove: function () {
            this._removeElement();
            return this;
        },

        /**
         * Remove this view's element from the document and all event listeners
         * attached to it. Exposed for subclasses using an alternative DOM
         * manipulation API.
         * @method
         */
        _removeElement: function () {
            this.el && this.el.parentNode && this.el.parentNode.removeChild(this.el);
        },

        /**
         * Change the view's element (`this.el` property) and re-delegate the
         * view's events on the new element.
         * @method
         */
        setElement: function (element) {
            this.undelegateEvents();
            this._setElement(element);
            this.delegateEvents();
            return this;
        },


        /**
         * Creates the `this.el` reference for this view using the
         * given `el`. `el` can be a CSS selector or an HTML element.
         * Subclasses can override this to utilize an
         * alternative DOM manipulation API and are only required to set the `this.el` property.
         * @method
         */
        _setElement: function (el) {
            this.el = (typeof el === 'string') ? $dom.$(el) : el;
        },

        /**
         * @method
         */
        events: function () {
            return {};
        },

        /**
         * Set callbacks, where `this.events` is a hash of
         * *{"event selector": "callback"}*
         * pairs. Callbacks will be bound to the view, with `this` set properly.
         * Uses event delegation for efficiency.
         * Omitting the selector binds the event to `this.el`.
         * @method
         */
        delegateEvents: function (events) {
            var key, method, match;

            if (typeof events === 'function') {
                events = events.call(this);
            } else if ((events == null) && (typeof this.events === 'function')) {
                events = this.events();
            }

            if (typeof events !== 'object') {
                return this;
            }

            this.undelegateEvents();
            for (key in events) {
                method = events[key];
                if (typeof method !== 'function') {
                    method = this[method];
                }
                if (!method) {
                    continue;
                }
                match = key.match(rgxDelegateEventSplitter);
                this.delegate(match[1], match[2], method.bind(this));
            }
            return this;
        },

        /**
         * Add a single event listener to the view's element (or a child element using `selector`).
         * @method
         */
        delegate: function (eventName, selector, listener) {
            //$util.inform('$cms.View#delegate(): delegating event "' + eventName + '" for selector "' + selector + '" with listener', listener, 'and view', this);
            $dom.on(this.el, (eventName + '.delegateEvents' + $util.uid(this)), selector, listener);
            return this;
        },

        /**
         * Clears all callbacks previously bound to the view by `delegateEvents`.
         * You usually don't need to use this, but may wish to if you have multiple
         * views attached to the same DOM element.
         * @method
         */
        undelegateEvents: function () {
            if (this.el) {
                $dom.off(this.el, '.delegateEvents' + $util.uid(this));
            }
            return this;
        },

        /**
         * A finer-grained `undelegateEvents` for removing a single delegated event. `selector` and `listener` are both optional.
         * @method
         */
        undelegate: function (eventName, selector, listener) {
            $dom.off(this.el, (eventName + '.delegateEvents' + $util.uid(this)), selector, listener);
            return this;
        },

        /**
         * @method
         */
        _ensureElement: function () {
            var attrs;
            if (!this.el) {
                attrs = Object.assign({}, $util.result(this, 'attributes'));
                if (this.id) {
                    attrs.id = $util.result(this, 'id');
                }
                if (this.className) {
                    attrs.className = $util.result(this, 'className');
                }
                this.setElement($dom.create($util.result(this, 'tagName') || 'div', attrs));
            } else {
                this.setElement($util.result(this, 'el'));
            }
        }
    });
    
    $cms.views.ToggleableTray = ToggleableTray;
    /**
     * @memberof $cms.views
     * @class $cms.views.ToggleableTray
     * @extends $cms.View
     */
    function ToggleableTray(params) {
        var id;

        ToggleableTray.base(this, 'constructor', arguments);

        this.contentEl = this.$('.js-tray-content');

        this.cookie = null;

        if (params.save) {
            id = $dom.id(this.el, 'tray-');
            this.cookie = id.startsWith('tray') ? id : 'tray-' + id;
        }

        if (this.cookie) {
            this.handleTrayCookie();
        }
    }

    $util.inherits(ToggleableTray, $cms.View, /**@lends $cms.views.ToggleableTray#*/{
        /**@method*/
        events: function () {
            return {
                'click .js-tray-onclick-toggle-tray': 'toggleTray',
                'click .js-tray-onclick-toggle-accordion': 'handleToggleAccordion'
            };
        },

        /**@method*/
        toggleTray: function () {
            var opened = $cms.ui.toggleableTray(this.contentEl);

            if (this.cookie) {
                $cms.setCookie(this.cookie, opened ? 'open' : 'closed');
            }
        },

        /**
         * @param toggledAccordionItem - Accordion item to be made active
         */
        toggleAccordion: function (toggledAccordionItem) {
            var accordionItems = this.$$('.js-tray-accordion-item');

            accordionItems.forEach(function (accordionItem) {
                var body = accordionItem.querySelector('.js-tray-accordion-item-body'),
                    opened;

                if ((accordionItem === toggledAccordionItem) || $dom.isDisplayed(body)) {
                    opened = $cms.ui.toggleableTray({ el: body });
                    accordionItem.classList.toggle('accordion_trayitem-active', opened);
                }
            });

        },
        /**@method*/
        handleToggleAccordion: function (e, btn) {
            var accordionItem = $dom.closest(btn, '.js-tray-accordion-item'); // Accordion item to be made active
            this.toggleAccordion(accordionItem);
        },

        /**@method*/
        handleTrayCookie: function () {
            var cookieValue = $cms.readCookie(this.cookie);

            if (($dom.notDisplayed(this.contentEl) && (cookieValue === 'open')) || ($dom.isDisplayed(this.contentEl) && (cookieValue === 'closed'))) {
                $cms.ui.toggleableTray({ el: this.contentEl, animate: false });
            }
        }
    });

    /*
     Originally...

     Script: modalwindow.js
     ModalWindow - Simple javascript popup overlay to replace builtin alert, prompt and confirm, and more.

     License:
     PHP-style license.

     Copyright:
     Copyright (c) 2009 [Kieron Wilson](http://kd3sign.co.uk/).

     Code & Documentation:
     http://kd3sign.co.uk/examples/modalwindow/

     HEAVILY Modified by ocProducts for composr.

     */

    $cms.views.ModalWindow = ModalWindow;
    /**
     * @memberof $cms.views
     * @class $cms.views.ModalWindow
     * @extends $cms.View
     */
    function ModalWindow(params) {
        // Constants
        this.WINDOW_SIDE_GAP = $cms.isMobile() ? 5 : 25;
        this.WINDOW_TOP_GAP = 25; // Will also be used for bottom gap for percentage heights
        this.BOX_EAST_PERIPHERARY = 4;
        this.BOX_WEST_PERIPHERARY = 4;
        this.BOX_NORTH_PERIPHERARY = 4;
        this.BOX_SOUTH_PERIPHERARY = 4;
        this.VCENTRE_FRACTION_SHIFT = 0.5; // Fraction of remaining top gap also removed (as overlays look better slightly higher than vertical centre)
        this.LOADING_SCREEN_HEIGHT = 100;

        // Properties
        /** @type { Element }*/
        this.el =  null;
        /** @type { Element }*/
        this.overlayEl = null;
        /** @type { Element }*/
        this.containerEl = null;
        /** @type { Element }*/
        this.buttonContainerEl = null;
        this.returnValue = null;
        this.topWindow = null;
        this.iframeRestyleTimer = null;

        // Set params
        params = $util.defaults({ // apply defaults
            type: 'alert',
            opacity: '0.5',
            width: 'auto',
            height: 'auto',
            title: '',
            text: '',
            yesButton: '{!YES;^}',
            noButton: '{!NO;^}',
            cancelButton: '{!INPUTSYSTEM_CANCEL;^}',
            yes: null,
            no: null,
            finished: null,
            cancel: null,
            href: null,
            scrollbars: null,
            defaultValue: null,
            target: '_self',
            inputType: 'text'
        }, params || {});

        for (var key in params) {
            this[key] = params[key];
        }

        this.topWindow = window.top;
        this.opened = true;

        ModalWindow.base(this, 'constructor', arguments);
    }

    $util.inherits(ModalWindow, $cms.View, /**@lends $cms.views.ModalWindow#*/ {
        events: function events() {
            return {
                'click .js-onclick-do-option-yes': 'doOptionYes',
                'click .js-onclick-do-option-no': 'doOptionNo',
                'click .js-onclick-do-option-cancel': 'doOptionCancel',
                'click .js-onclick-do-option-finished': 'doOptionFinished',
                'click .js-onclick-do-option-left': 'doOptionLeft',
                'click .js-onclick-do-option-right': 'doOptionRight',
            };
        },

        doOptionYes: function doOptionYes() {
            this.option('yes');
        },

        doOptionNo: function doOptionNo() {
            this.option('no');
        },

        doOptionCancel: function doOptionCancel() {
            this.option('cancel');
        },

        doOptionFinished: function doOptionFinished() {
            this.option('finished');
        },

        doOptionLeft: function doOptionLeft() {
            this.option('left');
        },

        doOptionRight: function doOptionRight() {
            this.option('right');
        },

        _setElement: function _setElement() {
            var button;

            this.topWindow.overlayZIndex || (this.topWindow.overlayZIndex = 999999); // Has to be higher than plupload, which is 99999

            this.el = $dom.create('div', { // Black out the background
                'className': 'js-modal-background js-modal-type-' + this.type,
                'css': {
                    'background': 'rgba(0,0,0,0.7)',
                    'zIndex': this.topWindow.overlayZIndex++,
                    'overflow': 'hidden',
                    'position': $cms.isMobile() ? 'absolute' : 'fixed',
                    'left': '0',
                    'top': '0',
                    'width': '100%',
                    'height': '100%'
                }
            });

            this.topWindow.document.body.appendChild(this.el);

            this.overlayEl = this.el.appendChild($dom.create('div', { // The main overlay
                'className': 'box overlay js-modal-overlay ' + this.type,
                'role': 'dialog',
                'css': {
                    // This will be updated immediately in resetDimensions
                    'position': $cms.isMobile() ? 'static' : 'fixed',
                    'margin': '0 auto' // Centering for iOS/Android which is statically positioned (so the container height as auto can work)
                }
            }));

            this.containerEl = this.overlayEl.appendChild($dom.create('div', {
                'className': 'box_inner js-modal-container',
                'css': {
                    'width': 'auto',
                    'height': 'auto'
                }
            }));

            var overlayHeader = null;
            if (this.title !== '' || this.type === 'iframe') {
                overlayHeader = $dom.create('h3', {
                    'html': this.title,
                    'css': {
                        'display': (this.title === '') ? 'none' : 'block'
                    }
                });
                this.containerEl.appendChild(overlayHeader);
            }

            if (this.text !== '') {
                if (this.type === 'prompt') {
                    var div = $dom.create('p');
                    div.appendChild($dom.create('label', {
                        'htmlFor': 'overlay_prompt',
                        'html': this.text
                    }));
                    this.containerEl.appendChild(div);
                } else {
                    this.containerEl.appendChild($dom.create('div', {
                        'html': this.text
                    }));
                }
            }

            this.buttonContainerEl = $dom.create('p', {
                'className': 'proceed_button js-modal-button-container'
            });

            var self = this;

            $dom.on(this.overlayEl, 'click', function (e) {
                if ($cms.isMobile() && (self.type === 'lightbox')) { // IDEA: Swipe detect would be better, but JS does not have this natively yet
                    self.option('right');
                }
            });

            switch (this.type) {
                case 'iframe':
                    var iframeWidth = (this.width.match(/^[\d\.]+$/) !== null) ? ((this.width - 14) + 'px') : this.width,
                        iframeHeight = (this.height.match(/^[\d\.]+$/) !== null) ? (this.height + 'px') : ((this.height === 'auto') ? (this.LOADING_SCREEN_HEIGHT + 'px') : this.height);

                    var iframe = $dom.create('iframe', {
                        'frameBorder': '0',
                        'scrolling': 'no',
                        'title': '',
                        'name': 'overlay_iframe',
                        'id': 'overlay_iframe',
                        'className': 'js-modal-overlay-iframe',
                        'allowTransparency': 'true',
                        //'seamless': 'seamless',// Not supported, and therefore testable yet. Would be great for mobile browsing.
                        'css': {
                            'width': iframeWidth,
                            'height': iframeHeight,
                            'background': 'transparent'
                        }
                    });

                    this.containerEl.appendChild(iframe);

                    $dom.animateFrameLoad(iframe, 'overlay_iframe', 50, true);

                    setTimeout(function () {
                        if (self.el) {
                            $dom.on(self.el, 'click', function (e) {
                                if (!self.containerEl.contains(e.target)) {
                                    // Background overlay clicked
                                    self.option('finished');
                                }
                            });
                        }
                    }, 1000);

                    $dom.on(iframe, 'load', function () {
                        if ($dom.hasIframeAccess(iframe) && (!iframe.contentDocument.querySelector('h1')) && (!iframe.contentDocument.querySelector('h2'))) {
                            if (iframe.contentDocument.title) {
                                $dom.html(overlayHeader, $cms.filter.html(iframe.contentDocument.title));
                                $dom.show(overlayHeader);
                            }
                        }
                    });

                    // Fiddle it, to behave like a popup would
                    setTimeout(function () {
                        $dom.illustrateFrameLoad('overlay_iframe');
                        iframe.src = self.href;
                        self.makeFrameLikePopup(iframe);

                        if (self.iframeRestyleTimer == null) { // In case internal nav changes
                            self.iframeRestyleTimer = setInterval(function () {
                                self.makeFrameLikePopup(iframe);
                            }, 300);
                        }
                    }, 0);
                    break;

                case 'lightbox':
                case 'alert':
                    if (this.yes) {
                        button = $dom.create('button', {
                            'type': 'button',
                            'html': this.yesButton,
                            'className': 'buttons__proceed button_screen_item js-onclick-do-option-yes'
                        });

                        this.buttonContainerEl.appendChild(button);
                    }
                    setTimeout(function () {
                        if (self.el) {
                            $dom.on(self.el, 'click', function (e) {
                                if (!self.containerEl.contains(e.target)) {
                                    // Background overlay clicked
                                    if (self.yes) {
                                        self.option('yes');
                                    } else {
                                        self.option('cancel');
                                    }
                                }
                            });
                        }
                    }, 1000);
                    break;

                case 'confirm':
                    button = $dom.create('button', {
                        'type': 'button',
                        'html': this.yesButton,
                        'className': 'buttons__yes button_screen_item js-onclick-do-option-yes',
                        'style': 'font-weight: bold;'
                    });
                    this.buttonContainerEl.appendChild(button);
                    button = $dom.create('button', {
                        'type': 'button',
                        'html': this.noButton,
                        'className': 'buttons__no button_screen_item js-onclick-do-option-no'
                    });
                    this.buttonContainerEl.appendChild(button);
                    break;

                case 'prompt':
                    this.input = $dom.create('input', {
                        'name': 'prompt',
                        'id': 'overlay_prompt',
                        'type': this.inputType,
                        'size': '40',
                        'className': 'wide_field',
                        'value': (this.defaultValue === null) ? '' : this.defaultValue
                    });
                    var inputWrap = $dom.create('div');
                    inputWrap.appendChild(this.input);
                    this.containerEl.appendChild(inputWrap);

                    if (this.yes) {
                        button = $dom.create('button', {
                            'type': 'button',
                            'html': this.yesButton,
                            'className': 'buttons__yes button_screen_item js-onclick-do-option-yes',
                            'css': {
                                'font-weight': 'bold'
                            }
                        });
                        this.buttonContainerEl.appendChild(button);
                    }

                    setTimeout(function () {
                        if (self.el) {
                            $dom.on(self.el, 'click', function (e) {
                                if (!self.containerEl.contains(e.target)) {
                                    // Background overlay clicked
                                    self.option('cancel');
                                }
                            });
                        }
                    }, 1000);
                    break;
            }

            // Cancel button handled either via button in corner (if there's no other buttons) or another button in the panel (if there's other buttons)
            if (this.cancelButton) {
                if (this.buttonContainerEl.firstElementChild) {
                    button = $dom.create('button', {
                        'type': 'button',
                        'html': this.cancelButton,
                        'className': 'button_screen_item buttons__cancel ' + (this.cancel ? 'js-onclick-do-option-cancel' : 'js-onclick-do-option-finished')
                    });
                    this.buttonContainerEl.appendChild(button);
                } else {
                    button = $dom.create('img', {
                        'src': $cms.img('{$IMG;,button_lightbox_close}'),
                        'alt': this.cancelButton,
                        'className': 'overlay_close_button ' + (this.cancel ? 'js-onclick-do-option-cancel' : 'js-onclick-do-option-finished')
                    });
                    this.containerEl.appendChild(button);
                }
            }

            // Put together
            if (this.buttonContainerEl.firstElementChild) {
                if (this.type === 'iframe') {
                    this.containerEl.appendChild($dom.create('hr', {'className': 'spaced_rule'}));
                }
                this.containerEl.appendChild(this.buttonContainerEl);
            }

            // Handle dimensions
            this.resetDimensions(this.width, this.height, true);
            $dom.on(window, 'resize', function () {
                self.resetDimensions(self.width, self.height, false);
            });

            // Focus first button by default
            if (this.input) {
                setTimeout(function () {
                    self.input.focus();
                });
            } else if (this.el.querySelector('button')) {
                this.el.querySelector('button').focus();
            }

            setTimeout(function () { // Timeout needed else keyboard activation of overlay opener may cause instant shutdown also
                $dom.on(document, 'keyup.modalWindow' + this.uid, self.keyup.bind(self));
                $dom.on(document, 'mousemove.modalWindow' + this.uid, self.mousemove.bind(self));
            }, 100);
        },

        keyup: function keyup(e) {
            if (e.key === 'ArrowLeft') {
                this.option('left');
            } else if (e.key === 'ArrowRight') {
                this.option('right');
            } else if ((e.key === 'Enter') && (this.yes)) {
                this.option('yes');
            } else if ((e.key === 'Enter') && (this.finished)) {
                this.option('finished');
            } else if ((e.key === 'Escape') && (this.cancelButton) && /^(prompt|confirm|lightbox|alert)$/.test(this.type)) {
                this.option('cancel');
            }
        },

        mousemove: function mousemove() {
            var self = this;
            if (!this.overlayEl.classList.contains('mousemove')) {
                this.overlayEl.classList.add('mousemove');
                setTimeout(function () {
                    if (self.overlayEl) {
                        self.overlayEl.classList.remove('mousemove');
                    }
                }, 2000);
            }
        },
        // Methods...
        close: function () {
            if (this.el) {
                this.topWindow.document.body.style.overflow = '';

                this.el.remove();
                this.el = null;

                $dom.off(document, 'keyup.modalWindow' + this.uid);
                $dom.off(document, 'mousemove.modalWindow' + this.uid);
            }
            this.opened = false;
        },

        option: function (method) {
            if (this[method]) {
                if (this.type === 'prompt') {
                    this[method](this.input.value);
                } else if (this.type === 'iframe') {
                    this[method](this.returnValue);
                } else {
                    this[method]();
                }
            }
            if ((method !== 'left') && (method !== 'right')) {
                this.close();
            }
        },

        /**
         * @param {string} width
         * @param {string} height
         * @param {boolean} [init]
         * @param {boolean} [forceHeight]
         */
        resetDimensions: function (width, height, init, forceHeight) {
            width = strVal(width);
            height = strVal(height);
            init = Boolean(init);
            forceHeight = Boolean(forceHeight);

            if (!this.el) {
                return;
            }

            var topPageHeight = this.topWindow.$dom.getWindowScrollHeight(),
                topWindowWidth = this.topWindow.$dom.getWindowWidth(),
                topWindowHeight = this.topWindow.$dom.getWindowHeight();

            var bottomGap = this.WINDOW_TOP_GAP;
            if (this.buttonContainerEl.firstElementChild) {
                bottomGap += this.buttonContainerEl.offsetHeight;
            }

            if (!forceHeight) {
                height = 'auto'; // Actually we always want auto heights, no reason to not for overlays
            }

            // Store for later (when browser resizes for example)
            this.width = width;
            this.height = height;

            // Normalise parameters (we don't have px on the end of pixel units, and these units refer to internal space size [% ones are relative to window though])
            width = width.replace(/px$/, '');
            height = height.replace(/px$/, '');

            // Constrain to window width
            if (width.match(/^\d+$/) !== null) {
                if ((parseInt(width) > topWindowWidth - this.WINDOW_SIDE_GAP * 2 - this.BOX_EAST_PERIPHERARY - this.BOX_WEST_PERIPHERARY) || (width === 'auto')) {
                    width = '' + (topWindowWidth - this.WINDOW_SIDE_GAP * 2 - this.BOX_EAST_PERIPHERARY - this.BOX_WEST_PERIPHERARY);
                }
            }

            // Auto width means full width
            if (width === 'auto') {
                width = '' + (topWindowWidth - this.WINDOW_SIDE_GAP * 2 - this.BOX_EAST_PERIPHERARY - this.BOX_WEST_PERIPHERARY);
            }
            // NB: auto height feeds through without a constraint (due to infinite growth space), with dynamic adjustment for iframes

            // Calculate percentage sizes
            var match;
            match = width.match(/^([\d\.]+)%$/);
            if (match !== null) {
                width = '' + (parseFloat(match[1]) * (topWindowWidth - this.WINDOW_SIDE_GAP * 2 - this.BOX_EAST_PERIPHERARY - this.BOX_WEST_PERIPHERARY));
            }
            match = height.match(/^([\d\.]+)%$/);
            if (match !== null) {
                height = '' + (parseFloat(match[1]) * (topPageHeight - this.WINDOW_TOP_GAP - bottomGap - this.BOX_NORTH_PERIPHERARY - this.BOX_SOUTH_PERIPHERARY));
            }

            // Work out box dimensions
            var boxWidth, boxHeight;
            if (width.match(/^\d+$/) !== null) {
                boxWidth = width + 'px';
            } else {
                boxWidth = width;
            }
            if (height.match(/^\d+$/) !== null) {
                boxHeight = height + 'px';
            } else {
                boxHeight = height;
            }

            // Save into HTML
            var detectedBoxHeight;
            this.overlayEl.style.width = boxWidth;
            this.overlayEl.style.height = boxHeight;
            var iframe = this.el.querySelector('iframe');

            if ($dom.hasIframeAccess(iframe) && (iframe.contentDocument.body)) { // Balance iframe height
                iframe.style.width = '100%';
                if (height === 'auto') {
                    if (!init) {
                        detectedBoxHeight = $dom.getWindowScrollHeight(iframe.contentWindow);
                        iframe.style.height = detectedBoxHeight + 'px';
                    }
                } else {
                    iframe.style.height = '100%';
                }
            }

            // Work out box position
            if (!detectedBoxHeight) {
                detectedBoxHeight = this.overlayEl.offsetHeight;
            }
            var boxPosTop, boxPosLeft;

            if (boxHeight === 'auto') {
                if (init) {
                    boxPosTop = (topWindowHeight / (2 + (this.VCENTRE_FRACTION_SHIFT * 2))) - (this.LOADING_SCREEN_HEIGHT / 2) + this.WINDOW_TOP_GAP; // This is just temporary
                } else {
                    boxPosTop = (topWindowHeight / (2 + (this.VCENTRE_FRACTION_SHIFT * 2))) - (detectedBoxHeight / 2) + this.WINDOW_TOP_GAP;
                }

                if (iframe) { // Actually, for frames we'll put at top so things don't bounce about during loading and if the frame size changes
                    boxPosTop = this.WINDOW_TOP_GAP;
                }
            } else {
                boxPosTop = (topWindowHeight / (2 + (this.VCENTRE_FRACTION_SHIFT * 2))) - (parseInt(boxHeight) / 2) + this.WINDOW_TOP_GAP;
            }
            if (boxPosTop < this.WINDOW_TOP_GAP) {
                boxPosTop = this.WINDOW_TOP_GAP;
            }
            boxPosLeft = ((topWindowWidth / 2) - (parseInt(boxWidth) / 2));

            // Save into HTML
            this.overlayEl.style.top = boxPosTop + 'px';
            this.overlayEl.style.left = boxPosLeft + 'px';

            var doScroll = false;

            // Absolute positioning instead of fixed positioning
            if ($cms.isMobile() || (detectedBoxHeight > topWindowHeight) || (this.el.style.position === 'absolute'/*don't switch back to fixed*/)) {
                var wasFixed = (this.el.style.position === 'fixed');

                this.el.style.position = 'absolute';
                this.el.style.height = ((topPageHeight > (detectedBoxHeight + bottomGap + boxPosLeft)) ? topPageHeight : (detectedBoxHeight + bottomGap + boxPosLeft)) + 'px';
                this.topWindow.document.body.style.overflow = '';

                if (!$cms.isMobile()) {
                    this.overlayEl.style.position = 'absolute';
                    this.overlayEl.style.top = this.WINDOW_TOP_GAP + 'px';
                }

                if (init || wasFixed) {
                    doScroll = true;
                }

                if (iframe && ($dom.hasIframeAccess(iframe)) && (iframe.contentWindow.scrolledUpFor === undefined)) { /*maybe a navigation has happened and we need to scroll back up*/
                    doScroll = true;
                }
            } else { // Fixed positioning, with scrolling turned off until the overlay is closed
                this.el.style.position = 'fixed';
                this.overlayEl.style.position = 'fixed';
                this.topWindow.document.body.style.overflow = 'hidden';
            }

            if (doScroll) {
                try { // Scroll to top to see
                    this.topWindow.scrollTo(0, 0);
                    if (iframe && ($dom.hasIframeAccess(iframe))) {
                        iframe.contentWindow.scrolledUpFor = true;
                    }
                } catch (ignore) {}
            }
        },
        /**
         * Fiddle it, to behave like a popup would
         * @param { HTMLIFrameElement } iframe
         */
        makeFrameLikePopup: function makeFrameLikePopup(iframe) {
            var mainWebsiteInner, mainWebsite, popupSpacer, baseElement;

            if ((iframe.parentNode.parentNode.parentNode.parentNode == null) && (this.iframeRestyleTimer != null)) {
                clearInterval(this.iframeRestyleTimer);
                this.iframeRestyleTimer = null;
                return;
            }

            var iDoc = iframe.contentDocument;

            if (!$dom.hasIframeAccess(iframe) || !iDoc.body || (iDoc.body.donePopupTrans !== undefined)) {
                if (hasIframeLoaded(iframe) && !hasIframeOwnership(iframe)) {
                    iframe.scrolling = 'yes';
                    iframe.style.height = '500px';
                }

                // Handle iframe sizing
                if (this.height === 'auto') {
                    this.resetDimensions(this.width, this.height, false);
                }
                return;
            }

            iDoc.body.style.background = 'transparent';
            iDoc.body.classList.add('overlay');
            iDoc.body.classList.add('lightbox');

            // Allow scrolling, if we want it
            //iframe.scrolling = (_this.scrollbars === false) ? 'no' : 'auto';  Actually, not wanting this now

            // Remove fixed width
            mainWebsiteInner = iDoc.getElementById('main_website_inner');

            if (mainWebsiteInner) {
                mainWebsiteInner.id = '';
            }

            // Remove main_website marker
            mainWebsite = iDoc.getElementById('main_website');
            if (mainWebsite) {
                mainWebsite.id = '';
            }

            // Remove popup spacing
            popupSpacer = iDoc.getElementById('popup_spacer');
            if (popupSpacer) {
                popupSpacer.id = '';
            }

            // Set linking scheme
            baseElement = iDoc.querySelector('base');

            if (!baseElement) {
                baseElement = iDoc.createElement('base');
                if (iDoc.head) {
                    iDoc.head.appendChild(baseElement);
                }
            }

            baseElement.target = this.target;

            // Set frame name
            if (this.name && (iframe.contentWindow.name !== this.name)) {
                iframe.contentWindow.name = this.name;
            }

            var self = this;
            // Create close function
            if (iframe.contentWindow.fauxClose === undefined) {
                iframe.contentWindow.fauxClose = function fauxClose() {
                    if (iframe && iframe.contentWindow && (iframe.contentWindow.returnValue !== undefined)) {
                        self.returnValue = iframe.contentWindow.returnValue;
                    }
                    self.option('finished');
                };
            }

            if ($dom.html(iDoc.body).length > 300) { // Loaded now
                iDoc.body.donePopupTrans = true;
            }

            // Handle iframe sizing
            if (this.height === 'auto') {
                this.resetDimensions(this.width, this.height, false);
            }

            function hasIframeLoaded(iframe) {
                try {
                    return (iframe != null) && (iframe.contentWindow.location.host !== '');
                } catch (ignore) {}

                return false;
            }

            function hasIframeOwnership(iframe) {
                try {
                    return (iframe != null) && (iframe.contentWindow.location.host === window.location.host) && (iframe.contentWindow.document != null);
                } catch (ignore) {}

                return false;
            }
        }
    });


    /**
     * @memberof $cms.views
     * @class $cms.views.Global
     * @extends $cms.View
     * */
    $cms.views.Global = function Global() {
        Global.base(this, 'constructor', arguments);

        /*START JS from HTML_HEAD.tpl*/
        // Google Analytics account, if one set up
        if ($cms.configOption('google_analytics').trim() && !$cms.isStaff() && !$cms.isAdmin()) {
            (function (i, s, o, g, r, a, m) {
                i['GoogleAnalyticsObject'] = r;
                i[r] = i[r] || function () {
                        (i[r].q = i[r].q || []).push(arguments);
                    };
                i[r].l = 1 * new Date();
                a = s.createElement(o);
                m = s.getElementsByTagName(o)[0];
                a.async = 1;
                a.src = g;
                m.parentNode.insertBefore(a, m);
            })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

            var aConfig = {};

            if ($cms.getCookieDomain() !== '') {
                aConfig.cookieDomain = $cms.getCookieDomain();
            }
            if (!$cms.configOption('long_google_cookies')) {
                aConfig.cookieExpires = 0;
            }

            window.ga('create', $cms.configOption('google_analytics').trim(), aConfig);

            if (!$cms.isGuest()) {
                window.ga('set', 'userId', strVal($cms.getMember()));
            }

            if ($cms.pageSearchParams().has('_t')) {
                window.ga('send', 'event', 'tracking__' + strVal($cms.pageSearchParams().get('_t')), window.location.href);
            }

            window.ga('send', 'pageview');
        }

        // Cookie Consent plugin by Silktide - http://silktide.com/cookieconsent
        if ($cms.configOption('cookie_notice') && ($cms.runningScript() === 'index')) {
            window.cookieconsent_options = {
                message: $util.format('{!COOKIE_NOTICE;}', [$cms.getSiteName()]),
                dismiss: '{!INPUTSYSTEM_OK;}',
                learnMore: '{!READ_MORE;}',
                link: '{$PAGE_LINK;,:privacy}',
                theme: 'dark-top'
            };
            $cms.requireJavascript('https://cdnjs.cloudflare.com/ajax/libs/cookieconsent2/1.0.9/cookieconsent.min.js');
        }

        if ($cms.configOption('detect_javascript')) {
            this.detectJavascript();
        }
        /*END JS from HTML_HEAD.tpl*/

        $dom.registerMouseListener();

        if ($dom.$('#global_messages_2')) {
            var m1 = $dom.$('#global_messages');
            if (!m1) {
                return;
            }
            var m2 = $dom.$('#global_messages_2');
            $dom.append(m1, $dom.html(m2));
            m2.parentNode.removeChild(m2);
        }

        if (boolVal($cms.pageSearchParams().get('wide_print'))) {
            try {
                window.print();
            } catch (ignore) {}
        }

        if (($cms.getZoneName() === 'adminzone') && $cms.configOption('background_template_compilation')) {
            var page = $cms.filter.url($cms.getPageName());
            $cms.loadSnippet('background_template_compilation&page=' + page, '', true);
        }

        if (((window === window.top) && !window.opener) || (window.name === '')) {
            window.name = '_site_opener';
        }

        // Are we dealing with a touch device?
        if ($cms.browserMatches('touch_enabled')) {
            document.body.classList.add('touch_enabled');
        }

        if ($cms.seesJavascriptErrorAlerts()) {
            this.initialiseErrorMechanism();
        }

        // Dynamic images need preloading
        var preloader = new Image();
        preloader.src = $cms.img('{$IMG;,loading}');

        // Tell the server we have JavaScript, so do not degrade things for reasons of compatibility - plus also set other things the server would like to know
        if ($cms.configOption('detect_javascript')) {
            $cms.setCookie('js_on', 1, 120);
        }

        if ($cms.configOption('is_on_timezone_detection')) {
            if (!window.parent || (window.parent === window)) {
                $cms.setCookie('client_time', (new Date()).toString(), 120);
                $cms.setCookie('client_time_ref', (Date.now() / 1000), 120);
            }
        }

        // Mouse/keyboard listening
        window.currentMouseX = 0;
        window.currentMouseY = 0;

        this.stuckNavs();

        // If back button pressed back from an AJAX-generated page variant we need to refresh page because we aren't doing full JS state management
        window.addEventListener('popstate', function () {
            setTimeout(function () {
                if (!window.location.hash && window.hasJsState) {
                    window.location.reload();
                }
            });
        });

        // Monitor pasting, for anti-spam reasons
        window.addEventListener('paste', function (event) {
            var clipboardData = event.clipboardData || window.clipboardData;
            var pastedData = clipboardData.getData('Text');
            if (pastedData && (pastedData.length > $cms.configOption('spam_heuristic_pasting'))) {
                $cms.setPostDataFlag('paste');
            }
        });

        if ($cms.isStaff()) {
            this.loadStuffStaff();
        }
    };

    $util.inherits($cms.views.Global, $cms.View, /**@lends $cms.views.Global#*/{
        events: function () {
            return {
                // Show a confirmation dialog for clicks on a link (is higher up for priority)
                'click [data-cms-confirm-click]': 'confirmClick',

                'click [data-click-eval]': 'clickEval',

                'click [data-click-alert]': 'showModalAlert',
                'keypress [data-keypress-alert]': 'showModalAlert',

                'keypress [data-submit-on-enter]': 'submitOnEnter',

                // Prevent url change for clicks on anchor tags with a placeholder href
                'click a[href$="#!"]': 'preventDefault',
                // Prevent form submission for forms with a placeholder action
                'submit form[action$="#!"]': 'preventDefault',
                // Prevent-default for JS-activated elements (which may have noscript fallbacks as default actions)
                'click [data-click-pd]': 'clickPreventDefault',
                'submit [data-submit-pd]': 'submitPreventDefault',

                // Simulated href for non <a> elements
                'click [data-cms-href]': 'cmsHref',

                'click [data-click-forward]': 'clickForward',

                // Toggle classes on mouseover/out
                'mouseover [data-mouseover-class]': 'mouseoverClass',
                'mouseout [data-mouseout-class]': 'mouseoutClass',

                'click [data-click-toggle-checked]': 'clickToggleChecked',

                // Disable button after click
                'click [data-disable-on-click]': 'disableButton',

                // Submit form when the change event is fired on an input element
                'change [data-change-submit-form]': 'changeSubmitForm',

                // Disable form buttons
                'submit form[data-disable-buttons-on-submit]': 'disableFormButtons',

                // mod_security workaround
                'submit form[data-submit-modsecurity-workaround]': 'submitModSecurityWorkaround',

                // Prevents input of matching characters
                'input input[data-cms-invalid-pattern]': 'invalidPattern',
                'keydown input[data-cms-invalid-pattern]': 'invalidPattern',
                'keypress input[data-cms-invalid-pattern]': 'invalidPattern',

                'click textarea[data-textarea-auto-height]': 'doTextareaAutoHeight',
                'input textarea[data-textarea-auto-height]': 'doTextareaAutoHeight',
                'change textarea[data-textarea-auto-height]': 'doTextareaAutoHeight',
                'keyup textarea[data-textarea-auto-height]': 'doTextareaAutoHeight',
                'keydown textarea[data-textarea-auto-height]': 'doTextareaAutoHeight',

                // Open page in overlay
                'click [data-open-as-overlay]': 'openOverlay',

                'click [data-click-faux-open]': 'clickFauxOpen',

                // Lightboxes
                'click a[rel*="lightbox"]': 'lightBoxes',

                // Go back in browser history
                'click [data-cms-btn-go-back]': 'goBackInHistory',

                'mouseover [data-mouseover-activate-tooltip]': 'mouseoverActivateTooltip',
                'focus [data-focus-activate-tooltip]': 'focusActivateTooltip',
                'blur [data-blur-deactivate-tooltip]': 'blurDeactivateTooltip',

                // "Rich semantic tooltips"
                'click [data-cms-rich-tooltip]': 'activateRichTooltip',
                'mouseover [data-cms-rich-tooltip]': 'activateRichTooltip',
                'keypress [data-cms-rich-tooltip]': 'activateRichTooltip',

                'change input[data-cms-unchecked-is-indeterminate]': 'uncheckedIsIndeterminate',

                'click [data-click-ga-track]': 'gaTrackClick',

                // Toggle tray
                'click [data-click-tray-toggle]': 'clickTrayToggle',

                'click [data-click-ui-open]': 'clickUiOpen',

                'click [data-click-do-input]': 'clickDoInput',

                /* Footer links */
                'click .js-global-click-load-software-chat': 'loadSoftwareChat',

                'submit .js-global-submit-staff-actions-select': 'staffActionsSelect',

                'keypress .js-global-input-su-keypress-enter-submit-form': 'inputSuKeypress',

                'click .js-global-click-load-realtime-rain': 'loadRealtimeRain',

                'click .js-global-click-load-commandr': 'loadCommandr'
            };
        },

        stuckNavs: function () {
            // Pinning to top if scroll out (LEGACY: CSS is going to have a better solution to this soon)
            var stuckNavs = $dom.$$('.stuck_nav');

            if (!stuckNavs.length) {
                return;
            }

            $dom.on(window, 'scroll', function () {
                for (var i = 0; i < stuckNavs.length; i++) {
                    var stuckNav = stuckNavs[i],
                        stuckNavHeight = (stuckNav.realHeight === undefined) ? $dom.contentHeight(stuckNav) : stuckNav.realHeight;

                    stuckNav.realHeight = stuckNavHeight;
                    var posY = $dom.findPosY(stuckNav.parentNode, true),
                        footerHeight = document.querySelector('footer') ? document.querySelector('footer').offsetHeight : 0,
                        panelBottom = $dom.$id('panel_bottom');

                    if (panelBottom) {
                        footerHeight += panelBottom.offsetHeight;
                    }
                    panelBottom = $dom.$id('global_messages_2');
                    if (panelBottom) {
                        footerHeight += panelBottom.offsetHeight;
                    }
                    if (stuckNavHeight < $dom.getWindowHeight() - footerHeight) { // If there's space in the window to make it "float" between header/footer
                        var extraHeight = (window.pageYOffset - posY);
                        if (extraHeight > 0) {
                            var width = $dom.contentWidth(stuckNav);
                            var height = $dom.contentHeight(stuckNav);
                            var stuckNavWidth = $dom.contentWidth(stuckNav);
                            if (!window.getComputedStyle(stuckNav).getPropertyValue('width')) { // May be centered or something, we should be careful
                                stuckNav.parentNode.style.width = width + 'px';
                            }
                            stuckNav.parentNode.style.height = height + 'px';
                            stuckNav.style.position = 'fixed';
                            stuckNav.style.top = '0px';
                            stuckNav.style.zIndex = '1000';
                            stuckNav.style.width = stuckNavWidth + 'px';
                        } else {
                            stuckNav.parentNode.style.width = '';
                            stuckNav.parentNode.style.height = '';
                            stuckNav.style.position = '';
                            stuckNav.style.top = '';
                            stuckNav.style.width = '';
                        }
                    } else {
                        stuckNav.parentNode.style.width = '';
                        stuckNav.parentNode.style.height = '';
                        stuckNav.style.position = '';
                        stuckNav.style.top = '';
                        stuckNav.style.width = '';
                    }
                }
            });
        },

        // Implementation for [data-cms-confirm-click="<Message>"]
        confirmClick: function (e, clicked) {
            var view = this, message,
                uid = $util.uid(clicked);

            // Stores an element's `uid`
            this._confirmedClick || (this._confirmedClick = null);

            if (uid === this._confirmedClick) {
                // Confirmed, let it through
                this._confirmedClick = null;
                return;
            }

            e.preventDefault();
            message = clicked.dataset.cmsConfirmClick;
            $cms.ui.confirm(message, function (result) {
                if (result) {
                    view._confirmedClick = uid;
                    clicked.click();
                }
            });
        },

        // Implementation for [data-click-eval="<code to eval>"]
        clickEval: function (e, target) {
            var code = strVal(target.dataset.clickEval);

            if (code) {
                window.eval.call(target, code);
            }
        },

        // Implementation for [data-click-alert] and [data-keypress-alert]
        showModalAlert: function (e, target) {
            var options = objVal($dom.data(target, e.type + 'Alert'), {}, 'notice');
            $cms.ui.alert(options.notice);
        },

        // Implementation for [data-submit-on-enter]
        submitOnEnter: function submitOnEnter(e, input) {
            if ($dom.keyPressed(e, 'Enter')) {
                $dom.submit(input.form);
                e.preventDefault();
            }
        },

        preventDefault: function (e) {
            e.preventDefault();
        },

        // Implementation for [data-click-pd]
        clickPreventDefault: function (e, el) {
            if (el.dataset.clickPd !== '0') {
                e.preventDefault();
            }
        },

        // Implementation for [data-submit-pd]
        submitPreventDefault: function (e, form) {
            if (form.dataset.submitPd !== '0') {
                e.preventDefault();
            }
        },

        // Implementation for [data-cms-href="<URL>"]
        cmsHref: function (e, el) {
            var anchorClicked = !!$dom.closest(e.target, 'a', el);

            // Make sure a child <a> element wasn't clicked and default wasn't prevented
            if (!anchorClicked && !e.defaultPrevented) {
                $util.navigate(el);
            }
        },

        // Implementation for [data-click-forward="{ child: '.some-selector' }"]
        clickForward: function (e, el) {
            var options = objVal($dom.data(el, 'clickForward'), {}, 'child'),
                child = strVal(options.child), // Selector for target child element
                except = strVal(options.except), // Optional selector for excluded elements to let pass-through
                childEl = $dom.$(el, child);

            if (!childEl) {
                // Nothing to do
                return;
            }

            if (!childEl.contains(e.target) && (!except || !$dom.closest(e.target, except, el.parentElement))) {
                // ^ Make sure the child isn't the current event's target already, and check for excluded elements to let pass-through
                e.preventDefault();
                $dom.trigger(childEl, 'click');
            }
        },

        // Implementation for [data-mouseover-class="{ 'some-class' : 1|0 }"]
        mouseoverClass: function (e, target) {
            var classes = objVal($dom.data(target, 'mouseoverClass')), key, bool;

            if (!e.relatedTarget || !target.contains(e.relatedTarget)) {
                for (key in classes) {
                    bool = !!classes[key] && (classes[key] !== '0');
                    target.classList.toggle(key, bool);
                }
            }
        },

        // Implementation for [data-mouseout-class="{ 'some-class' : 1|0 }"]
        mouseoutClass: function (e, target) {
            var classes = objVal($dom.data(target, 'mouseoutClass')), key, bool;

            if (!e.relatedTarget || !target.contains(e.relatedTarget)) {
                for (key in classes) {
                    bool = !!classes[key] && (classes[key] !== '0');
                    target.classList.toggle(key, bool);
                }
            }
        },

        // Implementation for [data-click-toggle-checked]
        clickToggleChecked: function (e, target) {
            var selector = strVal(target.dataset.clickToggleChecked),
                checkboxes  = [];

            if (selector === '') {
                checkboxes.push(target);
            } else {
                checkboxes = $dom.$$$(selector);
            }

            checkboxes.forEach(function (checkbox) {
                $dom.toggleChecked(checkbox);
            });
        },

        // Implementation for [data-disable-on-click]
        disableButton: function (e, target) {
            $cms.ui.disableButton(target);
        },

        // Implementation for [data-change-submit-form]
        changeSubmitForm: function (e, input) {
            if (input.form != null) {
                $dom.submit(input.form);
            }
        },

        // Implementation for form[data-disable-buttons-on-submit]
        disableFormButtons: function (e, target) {
            $cms.ui.disableFormButtons(target);
        },

        // Implementation for form[data-submit-modsecurity-workaround]
        submitModSecurityWorkaround: function (e, form) {
            if ($cms.form.isModSecurityWorkaroundEnabled()) {
                e.preventDefault();
                $cms.form.modSecurityWorkaround(form);
            }
        },

        // Implementation for input[data-cms-invalid-pattern]
        invalidPattern: function (e, input) {
            var pattern = input.dataset.cmsInvalidPattern, regex;

            this._invalidPatternCache || (this._invalidPatternCache = {});

            regex = this._invalidPatternCache[pattern] || (this._invalidPatternCache[pattern] = new RegExp(pattern, 'g'));

            if (e.type === 'input') {
                if (input.value.length === 0) {
                    input.value = ''; // value.length is also 0 if invalid value is entered for input[type=number] et al., clear that
                } else if (input.value.search(regex) !== -1) {
                    input.value = input.value.replace(regex, '');
                }
            } else if ($dom.keyOutput(e, regex)) { // keydown/keypress event
                // pattern matched, prevent input
                e.preventDefault();
            }
        },

        // Implementation for textarea[data-textarea-auto-height]
        doTextareaAutoHeight: function (e, textarea) {
            if ($cms.isMobile()) {
                return;
            }

            $cms.manageScrollHeight(textarea);
        },

        // Implementation for [data-open-as-overlay]
        openOverlay: function (e, el) {
            var options, url = (el.href === undefined) ? el.action : el.href;

            if (!($cms.configOption('js_overlays'))) {
                return;
            }

            if (/:\/\/(.[^\/]+)/.exec(url)[1] !== window.location.hostname) {
                return; // Cannot overlay, different domain
            }

            e.preventDefault();

            options = objVal($dom.data(el, 'openAsOverlay'));
            options.el = el;

            openLinkAsOverlay(options);
        },

        // Implementation for [data-click-faux-open]
        clickFauxOpen: function (e, el) {
            var args = arrVal($dom.data(el, 'clickFauxOpen'));
            $cms.ui.open.apply(undefined, args);
        },

        // Implementation for `click a[rel*="lightbox"]`
        lightBoxes: function (e, el) {
            if (!($cms.configOption('js_overlays'))) {
                return;
            }

            e.preventDefault();

            if (el.querySelector('img, video')) {
                openImageIntoLightbox(el);
            } else {
                openLinkAsOverlay({ el: el });
            }

            function openImageIntoLightbox(el) {
                var hasFullButton = (el.firstElementChild === null) || (el.href !== el.firstElementChild.src);
                $cms.ui.openImageIntoLightbox(el.href, ((el.cmsTooltipTitle !== undefined) ? el.cmsTooltipTitle : el.title), null, null, hasFullButton);
            }
        },

        // Implementation for [data-cms-btn-go-back]
        goBackInHistory: function () {
            window.history.back();
        },

        // Implementation for [data-mouseover-activate-tooltip]
        mouseoverActivateTooltip: function (e, el) {
            var args = arrVal($dom.data(el, 'mouseoverActivateTooltip'));

            args.unshift(el, e);

            try {
                //arguments: el, event, tooltip, width, pic, height, bottom, no_delay, lights_off, force_width, win, haveLinks
                $cms.ui.activateTooltip.apply(undefined, args);
            } catch (ex) {
                $util.fatal('$cms.views.Global#mouseoverActivateTooltip(): Exception thrown by $cms.ui.activateTooltip()', ex, 'called with args:', args);
            }
        },

        // Implementation for [data-focus-activate-tooltip]
        focusActivateTooltip: function (e, el) {
            var args = arrVal($dom.data(el, 'focusActivateTooltip'));

            args.unshift(el, e);

            try {
                //arguments: el, event, tooltip, width, pic, height, bottom, no_delay, lights_off, force_width, win, haveLinks
                $cms.ui.activateTooltip.apply(undefined, args);
            } catch (ex) {
                $util.fatal('$cms.views.Global#focusActivateTooltip(): Exception thrown by $cms.ui.activateTooltip()', ex, 'called with args:', args);
            }
        },

        // Implementation for [data-blur-deactivate-tooltip]
        blurDeactivateTooltip: function (e, el) {
            $cms.ui.deactivateTooltip(el);
        },

        // Implementation for [data-cms-rich-tooltip]
        activateRichTooltip: function (e, el) {
            var options = objVal($dom.data(el, 'cmsRichTooltip'));

            if (el.ttitle === undefined) {
                el.ttitle = (el.attributes['data-title'] ? el.getAttribute('data-title') : el.title);
                el.title = '';
            }

            if ((e.type === 'mouseover') && options.haveLinks) {
                return;
            }

            //arguments: el, event, tooltip, width, pic, height, bottom, no_delay, lights_off, force_width, win, haveLinks
            var args = [el, e, el.ttitle, 'auto', null, null, false, true, false, false, window, true/*!!el.haveLinks*/];

            try {
                $cms.ui.activateTooltip.apply(undefined, args);
            } catch (ex) {
                $util.fatal('$cms.views.Global#activateRichTooltip(): Exception thrown by $cms.ui.activateTooltip()', ex, 'called with args:', args);
            }
        },

        // Implementatioin for input[data-cms-unchecked-is-indeterminate]
        uncheckedIsIndeterminate: function (e, input) {
            if (!input.checked) {
                input.indeterminate = true;
            }
        },

        // Implementation for [data-click-ga-track]
        gaTrackClick: function (e, clicked) {
            var options = objVal($dom.data(clicked, 'clickGaTrack'));

            e.preventDefault();
            $cms.gaTrack(clicked, options.category, options.action);
        },

        // Implementation for [data-click-tray-toggle="<TRAY ID>"]
        clickTrayToggle: function (e, clicked) {
            var trayId = strVal(clicked.dataset.clickTrayToggle),
                trayEl = $dom.$(trayId);

            if (!trayEl) {
                return;
            }

            var ttObj = $dom.data(trayEl).toggleableTrayObject;
            if (ttObj) {
                ttObj.toggleTray();
            }
        },

        // Implementation for [data-click-ui-open]
        clickUiOpen: function (e, clicked) {
            var args = arrVal($dom.data(clicked, 'clickUiOpen'));
            args[0] = $cms.maintainThemeInLink(args[0]);
            $cms.ui.open.apply(undefined, args);
        },

        // Implementation for [data-click-do-input]
        clickDoInput: function (e, clicked) {
            var args = arrVal($dom.data(clicked, 'clickDoInput')),
                type = strVal(args[0]),
                fieldName = strVal(args[1]),
                tag = strVal(args[2]),
                fnName = 'doInput' + $util.ucFirst($util.camelCase(type));

            if (typeof window[fnName] === 'function') {
                window[fnName](fieldName, tag);
            } else {
                $util.fatal('$cms.views.Global#clickDoInput(): Function not found "window.' + fnName + '()"');
            }
        },

        // Detecting of JavaScript support
        detectJavascript: function () {
            var url = window.location.href,
                append = '?';

            if ($cms.isJsOn() || boolVal($cms.pageSearchParams().get('keep_has_js')) || url.includes('upgrader.php') || url.includes('webdav.php')) {
                return;
            }

            if (window.location.search.length === 0) {
                if (!url.includes('.htm') && !url.includes('.php')) {
                    append = 'index.php?';

                    if (!url.endsWith('/')) {
                        append = '/' + append;
                    }
                }
            } else {
                append = '&';
            }

            append += 'keep_has_js=1';

            if ($cms.isDevMode()) {
                append += '&keep_devtest=1';
            }

            // Redirect with JS on, and then hopefully we can remove keep_has_js after one click. This code only happens if JS is marked off, no infinite loops can happen.
            window.location = url + append;
        },

        /* Software Chat */
        loadSoftwareChat: function () {
            var url = 'https://kiwiirc.com/client/irc.kiwiirc.com/?nick=';
            if ($cms.getUsername() !== 'admin') {
                url += encodeURIComponent($cms.getUsername().replace(/[^a-zA-Z0-9\_\-\\\[\]\{\}\^`|]/g, ''));
            } else {
                url += encodeURIComponent($cms.getSiteName().replace(/[^a-zA-Z0-9\_\-\\\[\]\{\}\^`|]/g, ''));
            }
            url += '#composrcms';

            var SOFTWARE_CHAT_EXTRA = $util.format('{!SOFTWARE_CHAT_EXTRA;^}', [$cms.filter.html(window.location.href.replace($cms.baseUrl(), 'http://baseurl'))]);
            var html = /** @lang HTML */'' +
                '<div class="software_chat">' +
                '   <h2>{!CMS_COMMUNITY_HELP}</h2>' +
                '   <ul class="spaced_list">' + SOFTWARE_CHAT_EXTRA + '</ul>' +
                '   <p class="associated_link associated_links_block_group">' +
                '       <a title="{!SOFTWARE_CHAT_STANDALONE} {!LINK_NEW_WINDOW;^}" target="_blank" href="' + $cms.filter.html(url) + '">{!SOFTWARE_CHAT_STANDALONE}</a>' +
                '       <a href="#!" class="js-global-click-load-software-chat">{!HIDE}</a>' +
                '   </p>' +
                '</div>' +
                '<iframe class="software_chat_iframe" style="border: 0" src="' + $cms.filter.html(url) + '"></iframe>';

            var box = $dom.$('#software_chat_box'), img;
            if (box) {
                box.parentNode.removeChild(box);

                img = $dom.$('#software_chat_img');
                img.style.opacity = 1;
            } else {
                var width = 950,
                    height = 550;

                box = $dom.create('div', {
                    id: 'software_chat_box',
                    css: {
                        width: width + 'px',
                        height: height + 'px',
                        background: '#EEE',
                        color: '#000',
                        padding: '5px',
                        border: '3px solid #AAA',
                        position: 'absolute',
                        zIndex: 2000,
                        left: ($dom.getWindowWidth() - width) / 2 + 'px',
                        top: 100 + 'px'
                    },
                    html: html
                });

                document.body.appendChild(box);

                $dom.smoothScroll(0);

                img = $dom.$('#software_chat_img');
                img.style.opacity = 0.5;
            }
        },

        /* Staff Actions links */
        staffActionsSelect: function (e, form) {
            var ob = form.elements['special_page_type'],
                val = ob.options[ob.selectedIndex].value;

            if (val !== 'view') {
                if (form.elements.cache !== undefined) {
                    form.elements.cache.value = (val.substring(val.length - 4, val.length) === '.css') ? '1' : '0';
                }

                var windowName = 'cms_dev_tools' + Math.floor(Math.random() * 10000),
                    windowOptions;

                if (val === 'templates') {
                    windowOptions = 'width=' + window.screen.availWidth + ',height=' + window.screen.availHeight + ',scrollbars=yes';

                    setTimeout(function () { // Do a refresh with magic markers, in a comfortable few seconds
                        var oldUrl = window.location.href;
                        if (!oldUrl.includes('keep_template_magic_markers=1')) {
                            window.location.href = oldUrl + (oldUrl.includes('?') ? '&' : '?') + 'keep_template_magic_markers=1&cache_blocks=0&cache_comcode_pages=0';
                        }
                    }, 10000);
                } else {
                    windowOptions = 'width=1020,height=700,scrollbars=yes';
                }

                var test = window.open('', windowName, windowOptions);

                if (test) {
                    form.setAttribute('target', test.name);
                }
            }
        },

        inputSuKeypress: function (e, input) {
            if ($dom.keyPressed(e, 'Enter')) {
                $dom.submit(input.form);
            }
        },

        loadRealtimeRain: function () {
            $cms.requireJavascript('button_realtime_rain').then(function () {
                window.loadRealtimeRain();
            });
        },

        loadCommandr: function () {
            if (window.loadCommandr) {
                window.loadCommandr();
            }
        },

        loadStuffStaff: function () {
            var loc = window.location.href;

            // Navigation loading screen
            if ($cms.configOption('enable_animations')) {
                if ((window.parent === window) && !loc.includes('js_cache=1') && (loc.includes('/cms/') || loc.includes('/adminzone/'))) {
                    window.addEventListener('beforeunload', function () {
                        staffUnloadAction();
                    });
                }
            }

            // Theme image editing hovers
            var els = $dom.$$('*:not(.no_theme_img_click)'), i, el, isImage;
            for (i = 0; i < els.length; i++) {
                el = els[i];
                isImage = (el.localName === 'img') || ((el.localName === 'input') && (el.type === 'image')) || $dom.css(el, 'background-image').includes('url');

                if (isImage) {
                    $dom.on(el, {
                        mouseover: handleImageMouseOver,
                        mouseout: handleImageMouseOut,
                        click: handleImageClick
                    });
                }
            }

            /* Thumbnail tooltips */
            if ($cms.isDevMode() || loc.replace($cms.getBaseUrlNohttp(), '').includes('/cms/')) {
                var urlPatterns = $cms.staffTooltipsUrlPatterns(),
                    links, pattern, hook, patternRgx;

                links = $dom.$$('td a');
                for (pattern in urlPatterns) {
                    hook = urlPatterns[pattern];
                    patternRgx = new RegExp(pattern);

                    links.forEach(function (link) {
                        if (link.href && !link.onmouseover) {
                            var id = link.href.match(patternRgx);
                            if (id) {
                                applyComcodeTooltip(hook, id, link);
                            }
                        }
                    });
                }
            }

            /* Screen transition, for staff */
            function staffUnloadAction() {
                $cms.undoStaffUnloadAction();

                // If clicking a download link then don't show the animation
                if (document.activeElement && document.activeElement.href !== undefined && document.activeElement.href != null) {
                    var url = document.activeElement.href.replace(/.*:\/\/[^\/:]+/, '');
                    if (url.includes('download') || url.includes('export')) {
                        return;
                    }
                }

                // If doing a meta refresh then don't show the animation
                if (document.querySelector('meta[http-equiv="Refresh"]')) {
                    return;
                }

                // Show the animation
                var bi = $dom.$id('main_website_inner');
                if (bi) {
                    bi.classList.add('site_unloading');
                    $dom.fadeTo(bi, null, 0.2);
                }
                var div = document.createElement('div');
                div.className = 'unload_action';
                div.style.width = '100%';
                div.style.top = ($dom.getWindowHeight() / 2 - 160) + 'px';
                div.style.position = 'fixed';
                div.style.zIndex = 10000;
                div.style.textAlign = 'center';
                $dom.html(div, '<div aria-busy="true" class="loading_box box"><h2>{!LOADING;^}</h2><img id="loading_image" alt="" src="{$IMG_INLINE*;,loading}" /></div>');
                setTimeout(function () {
                    // Stupid workaround for Google Chrome not loading an image on unload even if in cache
                    if ($dom.$('#loading_image')) {
                        $dom.$('#loading_image').src += '';
                    }
                }, 100);
                document.body.appendChild(div);

                // Allow unloading of the animation
                $dom.on(window, 'pageshow keydown click', $cms.undoStaffUnloadAction);
            }

            /*
             TOOLTIPS FOR THUMBNAILS TO CONTENT, AS DISPLAYED IN CMS ZONE
             */
            function applyComcodeTooltip(hook, id, link) {
                link.addEventListener('mouseout', function () {
                    $cms.ui.deactivateTooltip(link);
                });
                link.addEventListener('mousemove', function (event) {
                    $cms.ui.repositionTooltip(link, event, false, false, null, true);
                });
                link.addEventListener('mouseover', function (event) {
                    var idChopped = id[1];
                    if (id[2] !== undefined) {
                        idChopped += ':' + id[2];
                    }
                    var comcode = '[block="' + hook + '" id="' + decodeURIComponent(idChopped) + '" no_links="1"]main_content[/block]';
                    if (link.renderedTooltip === undefined) {
                        link.isOver = true;

                        $cms.doAjaxRequest($cms.maintainThemeInLink('{$FIND_SCRIPT_NOHTTP;,comcode_convert}?css=1&javascript=1&raw_output=1&box_title={!PREVIEW;&}' + $cms.keep()), null, 'data=' + encodeURIComponent(comcode)).then(function (xhr) {
                            if (xhr && xhr.responseText) {
                                link.renderedTooltip = xhr.responseText;
                            }
                            if (link.renderedTooltip !== undefined) {
                                if (link.isOver) {
                                    $cms.ui.activateTooltip(link, event, link.renderedTooltip, '400px', null, null, false, false, false, true);
                                }
                            }
                        });
                    } else {
                        $cms.ui.activateTooltip(link, event, link.renderedTooltip, '400px', null, null, false, false, false, true);
                    }
                });
            }

            /*
             THEME IMAGE CLICKING
             */
            function handleImageMouseOver(event) {
                var target = event.target;
                if (target.previousElementSibling && (target.previousElementSibling.classList.contains('magic_image_edit_link'))) {
                    return;
                }
                if (target.offsetWidth < 130) {
                    return;
                }

                var src = (target.src === undefined) ? $dom.css(target, 'background-image') : target.src;

                if ((target.src === undefined) && (!event.ctrlKey) && (!event.metaKey) && (!event.altKey)) {
                    return;  // Needs ctrl key for background images
                }
                if (!src.includes('/themes/') || window.location.href.includes('admin_themes')) {
                    return;
                }

                if ($cms.configOption('enable_theme_img_buttons')) {
                    // Remove other edit links
                    var old = document.querySelectorAll('.magic_image_edit_link');
                    for (var i = old.length - 1; i >= 0; i--) {
                        old[i].parentNode.removeChild(old[i]);
                    }

                    // Add edit button
                    var ml = document.createElement('input');
                    ml.onclick = function (event) {
                        handleImageClick(event, target, true);
                    };
                    ml.type = 'button';
                    ml.id = 'editimg_' + target.id;
                    ml.value = '{!themes:EDIT_THEME_IMAGE;^}';
                    ml.className = 'magic_image_edit_link button_micro';
                    ml.style.position = 'absolute';
                    ml.style.left = $dom.findPosX(target) + 'px';
                    ml.style.top = $dom.findPosY(target) + 'px';
                    ml.style.zIndex = 3000;
                    ml.style.display = 'none';
                    target.parentNode.insertBefore(ml, target);

                    if (target.moLink) {
                        clearTimeout(target.moLink);
                    }
                    target.moLink = setTimeout(function () {
                        if (ml) {
                            ml.style.display = 'block';
                        }
                    }, 2000);
                }

                window.oldStatusImg = window.status;
                window.status = '{!SPECIAL_CLICK_TO_EDIT;^}';
            }

            function handleImageMouseOut(event) {
                var target = event.target;

                if ($cms.configOption('enable_theme_img_buttons')) {
                    if (target.previousElementSibling && (target.previousElementSibling.classList.contains('magic_image_edit_link'))) {
                        if ((target.moLink !== undefined) && (target.moLink)) {// Clear timed display of new edit button
                            clearTimeout(target.moLink);
                            target.moLink = null;
                        }

                        // Time removal of edit button
                        if (target.moLink) {
                            clearTimeout(target.moLink);
                        }

                        target.moLink = setTimeout(function () {
                            if ((target.editWindow === undefined) || (!target.editWindow) || (target.editWindow.closed)) {
                                if (target.previousElementSibling && (target.previousElementSibling.classList.contains('magic_image_edit_link'))) {
                                    target.parentNode.removeChild(target.previousElementSibling);
                                }
                            }
                        }, 3000);
                    }
                }

                if (window.oldStatusImg === undefined) {
                    window.oldStatusImg = '';
                }
                window.status = window.oldStatusImg;
            }

            function handleImageClick(event, ob, force) {
                ob || (ob = this);

                var src = ob.origsrc ? ob.origsrc : ((ob.src == null) ? $dom.css(ob, 'background-image').replace(/.*url\(['"]?(.*)['"]?\).*/, '$1') : ob.src);
                if (src && (force || ($cms.magicKeypress(event)))) {
                    // Bubbling needs to be stopped because shift+click will open a new window on some lower event handler (in Firefox anyway)
                    event.preventDefault();

                    if (src.includes($cms.getBaseUrlNohttp() + '/themes/')) {
                        ob.editWindow = window.open('{$BASE_URL;,0}/adminzone/index.php?page=admin_themes&type=edit_image&lang=' + encodeURIComponent($cms.userLang()) + '&theme=' + encodeURIComponent($cms.getTheme()) + '&url=' + encodeURIComponent($cms.protectURLParameter(src.replace('{$BASE_URL;,0}/', ''))) + $cms.keep(), 'edit_theme_image_' + ob.id);
                    } else {
                        $cms.ui.alert('{!NOT_THEME_IMAGE;^}');
                    }

                    return false;
                }

                return true;
            }
        },

        /* Staff JS error display */
        initialiseErrorMechanism: function () {
            window.onerror = function (msg, file, code) {
                if (document.readyState !== 'complete') {
                    // Probably not loaded yet
                    return null;
                }

                msg = strVal(msg);

                if (
                    (msg.includes('AJAX_REQUESTS is not defined')) || // Intermittent during page out-clicks

                    // LEGACY

                    // Internet Explorer false positives
                    (((msg.includes("'null' is not an object")) || (msg.includes("'undefined' is not a function"))) && ((file === undefined) || (file === 'undefined'))) || // Weird errors coming from outside
                    (((code === 0) || (code === '0')) && (msg.includes('Script error.'))) || // Too generic, can be caused by user's connection error

                    // Firefox false positives
                    (msg.includes("attempt to run compile-and-go script on a cleared scope")) || // Intermittent buggyness
                    (msg.includes('UnnamedClass.toString')) || // Weirdness
                    (msg.includes('ASSERT: ')) || // Something too generic
                    ((file) && (file.includes('TODO: FIXME'))) || // Something too generic / Can be caused by extensions
                    (msg.includes('TODO: FIXME')) || // Something too generic / Can be caused by extensions
                    (msg.includes('Location.toString')) || // Buggy extensions may generate
                    (msg.includes('Error loading script')) || // User's connection error
                    (msg.includes('NS_ERROR_FAILURE')) || // Usually an internal error

                    // Google Chrome false positives
                    (msg.includes('can only be used in extension processes')) || // Can come up with MeasureIt
                    (msg.includes('extension.')) || // E.g. "Uncaught Error: Invocation of form extension.getURL() doesn't match definition extension.getURL(string path) schema_generated_bindings"

                    false // Just to allow above lines to be reordered
                ) {
                    // Comes up on due to various Firefox/extension/etc bugs
                    return null;
                }

                if (!window.doneOneError) {
                    window.doneOneError = true;
                    var alert = '{!JAVASCRIPT_ERROR;^}\n\n' + code + ': ' + msg + '\n' + file;
                    if (document.body) { // i.e. if loaded
                        $cms.ui.alert(alert, '{!ERROR_OCCURRED;^}');
                    }
                }
                return false;
            };

            window.addEventListener('beforeunload', function () {
                window.onerror = null;
            });
        }
    });

    $cms.views.GlobalHelperPanel = GlobalHelperPanel;
    /**
     * @memberof $cms.views
     * @class GlobalHelperPanel
     * @extends $cms.View
     */
    function GlobalHelperPanel(params) {
        GlobalHelperPanel.base(this, 'constructor', arguments);
        this.contentsEl = this.$('.js-helper-panel-contents');
    }

    $util.inherits(GlobalHelperPanel, $cms.View, /**@lends GlobalHelperPanel#*/{
        events: function () {
            return {
                'click .js-click-toggle-helper-panel': 'toggleHelperPanel'
            };
        },
        toggleHelperPanel: function () {
            var show = $dom.notDisplayed(this.contentsEl),
                panelRight = $dom.$('#panel_right'),
                helperPanelContents = $dom.$('#helper_panel_contents'),
                helperPanelToggle = $dom.$('#helper_panel_toggle');

            if (show) {
                panelRight.classList.remove('helper_panel_hidden');
                panelRight.classList.add('helper_panel_visible');
                helperPanelContents.setAttribute('aria-expanded', 'true');
                helperPanelContents.style.display = 'block';
                $dom.fadeIn(helperPanelContents);

                if ($cms.readCookie('hide_helper_panel') === '1') {
                    $cms.setCookie('hide_helper_panel', '0', 100);
                }

                helperPanelToggle.firstElementChild.src = $cms.img('{$IMG;,icons/14x14/helper_panel_hide}');
                if (helperPanelToggle.firstElementChild.srcset !== undefined) {
                    helperPanelToggle.firstElementChild.srcset = $cms.img('{$IMG;,icons/28x28/helper_panel_hide} 2x');
                }
            } else {
                if ($cms.readCookie('hide_helper_panel') === '') {
                    $cms.ui.confirm('{!CLOSING_HELP_PANEL_CONFIRM;^}', function (answer) {
                        if (answer) {
                            _hideHelperPanel(panelRight, helperPanelContents, helperPanelToggle);
                        }
                    });
                } else {
                    _hideHelperPanel(panelRight, helperPanelContents, helperPanelToggle);
                }
            }

            function _hideHelperPanel(panelRight, helperPanelContents, helperPanelToggle) {
                panelRight.classList.remove('helper_panel_visible');
                panelRight.classList.add('helper_panel_hidden');
                helperPanelContents.setAttribute('aria-expanded', 'false');
                helperPanelContents.style.display = 'none';
                $cms.setCookie('hide_helper_panel', '1', 100);
                helperPanelToggle.firstElementChild.src = $cms.img('{$IMG;,icons/14x14/helper_panel_show}');

                if (helperPanelToggle.firstElementChild.srcset !== undefined) {
                    helperPanelToggle.firstElementChild.srcset = $cms.img('{$IMG;,icons/28x28/helper_panel_show} 2x');
                }
            }
        }
    });


    $cms.views.Menu = Menu;
    /**
     * @memberof $cms.views
     * @class
     * @extends $cms.View
     */
    function Menu(params) {
        Menu.base(this, 'constructor', arguments);

        /** @var {string} */
        this.menu = strVal(params.menu);
        /** @var {string} */
        this.menuId = strVal(params.menuId);

        if (params.javascriptHighlighting) {
            menuActiveSelection(this.menuId);
        }
    }

    $util.inherits(Menu, $cms.View);

    // Templates:
    // MENU_dropdown.tpl
    // - MENU_BRANCH_dropdown.tpl
    $cms.views.DropdownMenu = DropdownMenu;
    /**
     * @memberof $cms.views
     * @class
     * @extends Menu
     */
    function DropdownMenu(params) {
        DropdownMenu.base(this, 'constructor', arguments);
    }

    $util.inherits(DropdownMenu, Menu, /**@lends DropdownMenu#*/{
        events: function () {
            return {
                'mousemove .js-mousemove-timer-pop-up-menu': 'timerPopUpMenu',
                'mouseout .js-mouseout-clear-pop-up-timer': 'clearPopUpTimer',
                'focus .js-focus-pop-up-menu': 'focusPopUpMenu',
                'mousemove .js-mousemove-pop-up-menu': 'popUpMenu',
                'mouseover .js-mouseover-set-active-menu': 'setActiveMenu',
                'click .js-click-unset-active-menu': 'unsetActiveMenu',
                'mouseout .js-mouseout-unset-active-menu': 'unsetActiveMenu',
                // For admin/templates/MENU_dropdown.tpl:
                'mousemove .js-mousemove-admin-timer-pop-up-menu': 'adminTimerPopUpMenu',
                'mouseout .js-mouseout-admin-clear-pop-up-timer': 'adminClearPopUpTimer'
            };
        },

        timerPopUpMenu: function (e, target) {
            var menu = $cms.filter.id(this.menu),
                rand = strVal(target.dataset.vwRand);

            if (!target.timer) {
                target.timer = setTimeout(function () {
                    popUpMenu(menu + '_dexpand_' + rand, 'below', menu + '_d');
                }, 200);
            }
        },

        clearPopUpTimer: function (e, target) {
            if (target.timer) {
                clearTimeout(target.timer);
                target.timer = null;
            }
        },

        focusPopUpMenu: function (e, target) {
            var menu = $cms.filter.id(this.menu),
                rand = strVal(target.dataset.vwRand);

            popUpMenu(menu + '_dexpand_' + rand, 'below', menu + '_d', true);
        },

        popUpMenu: function (e, target) {
            var menu = $cms.filter.id(this.menu),
                rand = strVal(target.dataset.vwRand);

            popUpMenu(menu + '_dexpand_' + rand, null, menu + '_d');
        },

        setActiveMenu: function (e, target) {
            if (!target.contains(e.relatedTarget)) {
                var menu = $cms.filter.id(this.menu);

                if (window.activeMenu == null) {
                    setActiveMenu(target.id, menu + '_d');
                }
            }
        },

        unsetActiveMenu: function (e, target) {
            if (!target.contains(e.relatedTarget)) {
                window.activeMenu = null;
                recreateCleanTimeout();
            }
        },

        /* For admin/templates/MENU_dropdown.tpl */
        adminTimerPopUpMenu: function (e, target) {
            var menu = $cms.filter.id(this.menu),
                rand = strVal(target.dataset.vwRand);

            window.menuHoldTime = 3000;
            if (!target.dataset.timer) {
                target.dataset.timer = setTimeout(function () {
                    var ret = popUpMenu(menu + '_dexpand_' + rand, 'below', menu + '_d', true);
                    try {
                        document.getElementById('search_content').focus();
                    } catch (ignore) {}
                    return ret;
                }, 200);
            }
        },
        adminClearPopUpTimer: function (e, target) {
            if (target.dataset.timer) {
                clearTimeout(target.dataset.timer);
                target.dataset.timer = null;
            }
        }
    });

    $cms.views.PopupMenu = PopupMenu;
    /**
     * @memberof $cms.views
     * @class
     * @extends Menu
     */
    function PopupMenu(params) {
        PopupMenu.base(this, 'constructor', arguments);
    }

    $util.inherits(PopupMenu, Menu, /**@lends PopupMenu#*/{
        events: function () {
            return {
                'click .js-click-unset-active-menu': 'unsetActiveMenu',
                'mouseout .js-mouseout-unset-active-menu': 'unsetActiveMenu'
            };
        },

        unsetActiveMenu: function (e, target) {
            if (!target.contains(e.relatedTarget)) {
                window.activeMenu = null;
                recreateCleanTimeout();
            }
        }
    });

    $cms.views.PopupMenuBranch = PopupMenuBranch;
    /**
     * @memberof $cms.views
     * @class
     * @extends Menu
     */
    function PopupMenuBranch() {
        PopupMenuBranch.base(this, 'constructor', arguments);

        this.rand = this.params.rand;
        this.menu = $cms.filter.id(this.params.menu);
        this.popup = this.menu + '_pexpand_' + this.rand;
    }

    $util.inherits(PopupMenuBranch, $cms.View, /**@lends PopupMenuBranch#*/{
        events: function () {
            return {
                'focus .js-focus-pop-up-menu': 'popUpMenu',
                'mousemove .js-mousemove-pop-up-menu': 'popUpMenu',
                'mouseover .js-mouseover-set-active-menu': 'setActiveMenu'
            };
        },
        popUpMenu: function () {
            popUpMenu(this.popup, null, this.menu + '_p');
        },
        setActiveMenu: function () {
            if (!window.activeMenu) {
                setActiveMenu(this.popup, this.menu + '_p');
            }
        }
    });

    $cms.views.TreeMenu = TreeMenu;
    /**
     * @memberof $cms.views
     * @class
     * @extends Menu
     */
    function TreeMenu() {
        TreeMenu.base(this, 'constructor', arguments);
    }

    $util.inherits(TreeMenu, Menu, /**@lends TreeMenu#*/{
        events: function () {
            return {
                'click [data-menu-tree-toggle]': 'toggleMenu'
            };
        },

        toggleMenu: function (e, target) {
            var menuId = target.dataset.menuTreeToggle;

            $cms.ui.toggleableTray($dom.$('#' + menuId));
        }
    });

    // Templates:
    // MENU_mobile.tpl
    // - MENU_BRANCH_mobile.tpl
    $cms.views.MobileMenu = MobileMenu;
    /**
     * @memberof $cms.views
     * @class $cms.views.MobileMenu
     * @extends Menu
     */
    function MobileMenu() {
        MobileMenu.base(this, 'constructor', arguments);
        this.menuContentEl = this.$('.js-el-menu-content');
    }

    $util.inherits(MobileMenu, Menu, /**@lends $cms.views.MobileMenu#*/{
        events: function () {
            return {
                'click .js-click-toggle-content': 'toggleContent',
                'click .js-click-toggle-sub-menu': 'toggleSubMenu'
            };
        },
        toggleContent: function (e) {
            e.preventDefault();
            $dom.toggle(this.menuContentEl);
        },
        toggleSubMenu: function (e, link) {
            var rand = link.dataset.vwRand,
                subEl = this.$('#' + this.menuId + '_pexpand_' + rand),
                href;

            if ($dom.notDisplayed(subEl)) {
                $dom.show(subEl);
            } else {
                href = link.getAttribute('href');
                // Second click goes to it
                if (href && !href.startsWith('#')) {
                    return;
                }
                $dom.hide(subEl);
            }

            e.preventDefault();
        }
    });

    // For admin/templates/MENU_mobile.tpl
    /**
     * @param params
     */
    $cms.templates.menuMobile = function menuMobile(params) {
        var menuId = strVal(params.menuId);
        $dom.on(document.body, 'click', '.js-click-toggle-' + menuId + '-content', function (e) {
            var branch = document.getElementById(menuId);

            if (branch) {
                $dom.toggle(branch.parentElement);
                e.preventDefault();
            }
        });
    };

    $cms.views.SelectMenu = SelectMenu;
    /**
     * @memberof $cms.views
     * @class
     * @extends Menu
     */
    function SelectMenu() {
        SelectMenu.base(this, 'constructor', arguments);
    }

    $util.inherits(SelectMenu, Menu, /**@lends SelectMenu#*/{
        events: function () {
            return {
                'change .js-change-redirect-to-value': 'redirect'
            };
        },
        redirect: function (e, changed) {
            if (changed.value) {
                window.location.href = changed.value;
            }
        }
    });

    function menuActiveSelection(menuId) {
        var menuElement = $dom.$('#' + menuId),
            possibilities = [], isSelected, url, minScore, i;

        if (menuElement.localName === 'select') {
            for (i = 0; i < menuElement.options.length; i++) {
                url = menuElement.options[i].value;
                isSelected = menuItemIsSelected(url);
                if (isSelected !== null) {
                    possibilities.push({
                        url: url,
                        score: isSelected,
                        element: menuElement.options[i]
                    });
                }
            }

            if (possibilities.length > 0) {
                possibilities.sort(function (a, b) {
                    return a.score - b.score;
                });

                minScore = possibilities[0].score;
                for (i = 0; i < possibilities.length; i++) {
                    if (possibilities[i].score != minScore) {
                        break;
                    }
                    possibilities[i].element.selected = true;
                }
            }
        } else {
            var menuItems = menuElement.querySelectorAll('.non_current'), a;
            for (i = 0; i < menuItems.length; i++) {
                a = null;
                for (var j = 0; j < menuItems[i].children.length; j++) {
                    if (menuItems[i].children[j].localName === 'a') {
                        a = menuItems[i].children[j];
                    }
                }
                if (!a) {
                    continue;
                }

                url = (a.getAttribute('href') === '') ? '' : a.href;
                isSelected = menuItemIsSelected(url);
                if (isSelected !== null) {
                    possibilities.push({
                        url: url,
                        score: isSelected,
                        element: menuItems[i]
                    });
                }
            }

            if (possibilities.length > 0) {
                possibilities.sort(function (a, b) {
                    return a.score - b.score;
                });

                minScore = possibilities[0].score;
                for (i = 0; i < possibilities.length; i++) {
                    if (possibilities[i].score != minScore) {
                        break;
                    }
                    possibilities[i].element.classList.remove('non_current');
                    possibilities[i].element.classList.add('current');
                }
            }
        }

        function menuItemIsSelected(url) {
            url = strVal(url);

            if (url === '') {
                return null;
            }

            var currentUrl = window.location.href;
            if (currentUrl === url) {
                return 0;
            }
            var globalBreadcrumbs = document.getElementById('global_breadcrumbs');

            if (globalBreadcrumbs) {
                var links = globalBreadcrumbs.querySelectorAll('a');
                for (var i = 0; i < links.length; i++) {
                    if (url === links[links.length - 1 - i].href) {
                        return i + 1;
                    }
                }
            }

            return null;
        }
    }

    window.menuHoldTime = 500;
    window.activeMenu = null;
    window.lastActiveMenu = null;

    var cleanMenusTimeout,
        lastActiveMenu;

    function setActiveMenu(id, menu) {
        window.activeMenu = id;
        if (menu != null) {
            lastActiveMenu = menu;
        }
    }

    function recreateCleanTimeout() {
        if (cleanMenusTimeout) {
            clearTimeout(cleanMenusTimeout);
        }
        cleanMenusTimeout = setTimeout(cleanMenus, window.menuHoldTime);
    }

    function popUpMenu(id, place, menu, outsideFixedWidth) {
        place = strVal(place) || 'right';
        outsideFixedWidth = !!outsideFixedWidth;

        var el = $dom.$('#' + id);

        if (!el) {
            return;
        }

        if (cleanMenusTimeout) {
            clearTimeout(cleanMenusTimeout);
        }

        if ($dom.isDisplayed(el)) {
            return false;
        }

        window.activeMenu = id;
        lastActiveMenu = menu;
        cleanMenus();

        var l = 0;
        var t = 0;
        var p = el.parentNode;

        // Our own position computation as we are positioning relatively, as things expand out
        if ($dom.isCss(p.parentElement, 'position', 'absolute')) {
            l += p.offsetLeft;
            t += p.offsetTop;
        } else {
            while (p) {
                if (p && $dom.isCss(p, 'position', 'relative')) {
                    break;
                }

                l += p.offsetLeft;
                t += p.offsetTop - (parseInt(p.style.borderTop) || 0);
                p = p.offsetParent;

                if (p && $dom.isCss(p, 'position', 'absolute')) {
                    break;
                }
            }
        }
        if (place === 'below') {
            t += el.parentNode.offsetHeight;
        } else {
            l += el.parentNode.offsetWidth;
        }

        var fullHeight = $dom.getWindowScrollHeight(); // Has to be got before e is visible, else results skewed
        el.style.position = 'absolute';
        el.style.left = '0'; // Setting this lets the browser calculate a more appropriate (larger) width, before we set the correct left for that width will fit
        el.style.display = 'block';
        $dom.fadeIn(el);

        var fullWidth = (window.scrollX === 0) ? $dom.getWindowWidth() : window.document.body.scrollWidth;

        if ($cms.configOption('fixed_width') && !outsideFixedWidth) {
            var mainWebsiteInner = document.getElementById('main_website_inner');
            if (mainWebsiteInner) {
                fullWidth = mainWebsiteInner.offsetWidth;
            }
        }

        var eParentWidth = el.parentNode.offsetWidth;
        el.style.minWidth = eParentWidth + 'px';
        var eParentHeight = el.parentNode.offsetHeight;
        var eWidth = el.offsetWidth;
        function positionL() {
            var posLeft = l;
            if (place === 'below') { // Top-level of drop-down
                if (posLeft + eWidth > fullWidth) {
                    posLeft += eParentWidth - eWidth;
                }
            } else { // NB: For non-below, we can't assume 'left' is absolute, as it is actually relative to parent node which is itself positioned
                if ($dom.findPosX(el.parentNode, true) + eWidth + eParentWidth + 10 > fullWidth) {
                    posLeft -= eWidth + eParentWidth;
                }
            }
            el.style.left = posLeft + 'px';
        }
        positionL();
        setTimeout(positionL, 0);
        function positionT() {
            var posTop = t;
            if (posTop + el.offsetHeight + 10 > fullHeight) {
                var abovePosTop = posTop - $dom.contentHeight(el) + eParentHeight - 10;
                if (abovePosTop > 0) {
                    posTop = abovePosTop;
                }
            }
            el.style.top = posTop + 'px';
        }
        positionT();
        setTimeout(positionT, 0);
        el.style.zIndex = 200;

        recreateCleanTimeout();

        return false;
    }

    function cleanMenus() {
        cleanMenusTimeout = null;

        var m = $dom.$('#r_' + lastActiveMenu);
        if (!m) {
            return;
        }
        var tags = m.querySelectorAll('.nlevel');
        var e = (window.activeMenu == null) ? null : document.getElementById(window.activeMenu), t;
        var i, hideable;
        for (i = tags.length - 1; i >= 0; i--) {
            if (tags[i].localName !== 'ul' && tags[i].localName !== 'div') {
                continue;
            }

            hideable = true;
            if (e) {
                t = e;
                do {
                    if (tags[i].id === t.id) {
                        hideable = false;
                    }
                    t = t.parentNode.parentNode;
                } while (t.id !== 'r_' + lastActiveMenu);
            }
            if (hideable) {
                tags[i].style.left = '-999px';
                tags[i].style.display = 'none';
            }
        }
    }

    function openLinkAsOverlay(options) {
        options = $util.defaults({
            width: '800',
            height: 'auto',
            target: '_top',
            el: null
        }, options);

        var width = strVal(options.width);

        if (width.match(/^\d+$/)) { // Restrain width to viewport width
            width = Math.min(parseInt(width), $dom.getWindowWidth() - 60) + '';
        }

        var el = options.el,
            url = (el.href === undefined) ? el.action : el.href,
            urlStripped = url.replace(/#.*/, ''),
            newUrl = urlStripped + (!urlStripped.includes('?') ? '?' : '&') + 'wide_high=1' + url.replace(/^[^\#]+/, '');

        $cms.ui.open(newUrl, null, 'width=' + width + ';height=' + options.height, options.target);
    }
}(window.$cms, window.$util, window.$dom));