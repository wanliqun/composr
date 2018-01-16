(function ($cms, $util, $dom) {
    'use strict';

    /*
     Naming conventions...

     t_ Tab header
     g_ Tab body
     b_ Toolbar
     e_ Editor textbox
     */

    // INIT CODE
    window.templateEditorOpenFiles || (window.templateEditorOpenFiles = {});
    window.doneCleanupTemplateMarkers = Boolean(window.doneCleanupTemplateMarkers);

    if ($cms.pageUrl().searchParams.get('keep_template_magic_markers') === '1') {
        $dom.ready.then(function () {
            cleanupTemplateMarkers(window);
        });
    }

    $cms.views.ThemeManageScreen = ThemeManageScreen;
    /**
     * @memberof $cms.views
     * @class
     * @extends $cms.View
     */
    function ThemeManageScreen() {
        ThemeManageScreen.base(this, 'constructor', arguments);
    }

    $util.inherits(ThemeManageScreen, $cms.View);

    $cms.views.ThemeTemplateEditorTab = ThemeTemplateEditorTab;
    /**
     * @memberof $cms.views
     * @class
     * @extends $cms.View
     */
    function ThemeTemplateEditorTab(params) {
        ThemeTemplateEditorTab.base(this, 'constructor', arguments);

        // Allow searching via URL hash
        if (window.location.hash) {
            setTimeout(function () {
                var hash = window.location.hash.substr(1, window.location.hash.length - 1);
                editareaDoSearch('e_' + params.fileId, hash);
            }, 2000);
        }

        if ($cms.configOption('editarea')) {
            aceComposrLoader('e_' + params.fileId, params.highlighterType, false);
        }

        if (params.includeCssEditing && window.opener && window.opener.document) {
            loadContextualCssEditor(params.file, params.fileId);
        }
    }

    $util.inherits(ThemeTemplateEditorTab, $cms.View, /**@lends ThemeTemplateEditorTab#*/{
        events: function () {
            return {
                'keydown .js-ta-tpl-editor': 'editorKeyPress',
                'click .js-btn-save-content': 'saveContent',
                'click .js-btn-live-preview': 'livePreview',
                'click .js-btn-screen-preview': 'screenPreview',
                'click .js-a-editarea-search': 'editareaSearch',
                'click .js-a-insert-guid': 'insertGuid',
                'click .js-a-tpl-editor-add-tab': 'addEditorTab',
                'click .js-btn-equation-helper': 'cssEquationHelper'
            };
        },

        editorKeyPress: function (e, textarea) {
            if ($dom.keyPressed(e, 'Tab')) {
                e.preventDefault();
                window.$editing.insertTextbox(textarea, "\t");
            }
        },

        saveContent: function (e) {
            e.preventDefault();
            templateEditorTabSaveContent(this.params.file);

            function templateEditorTabSaveContent(file) {
                var url = 'template_editor_save';
                url += '&file=' + encodeURIComponent(file);
                url += '&theme=' + encodeURIComponent(window.templateEditorTheme);

                editareaReverseRefresh('e_' + fileToFileId(file));

                var post = 'contents=' + encodeURIComponent(getFileTextbox(file).value);
                $cms.loadSnippet(url, post, true).then(function (ajaxResult) {
                    $cms.ui.alert(ajaxResult, null, true);
                    templateEditorTabMarkNonchangedContent(file);
                });
            }

            function templateEditorTabMarkNonchangedContent(file) {
                window.templateEditorOpenFiles[file].unsavedChanges = false;

                var fileId = fileToFileId(file);
                var ob = document.getElementById('t_' + fileId);
                ob.classList.remove('file-changed');
                ob.classList.add('file_nonchanged');
            }
        },

        livePreview: function (e, target) {
            var params = this.params;
            if (!templateEditorPreview(params.fileId, params.livePreviewUrl, target, true)) {
                e.preventDefault();
            }
        },

        screenPreview: function (e, target) {
            var opts = this.params;
            if (!templateEditorPreview(opts.fileId, opts.screenPreviewUrl, target)) {
                e.preventDefault();
            }
        },

        editareaSearch: function (e, target) {
            var regexp = target.dataset.eaSearch;

            editareaDoSearch('e_' + this.params.fileId, regexp);
        },

        insertGuid: function (e, target) {
            var guid = target.dataset.insertGuid;

            insertGuid(this.params.file, guid);

            function insertGuid(file, guid) {
                var textbox = getFileTextbox(file);

                var hasEditarea = editareaIsLoaded(textbox.name);

                editareaReverseRefresh('e_' + fileToFileId(file));

                window.$editing.insertTextbox(textbox, '{' + '+START,IF,{' + '$EQ,{' + '_GUID},' + guid + '}}\n{' + '+END}').then(function () {
                    if (hasEditarea) {
                        editareaRefresh(textbox.id);
                    }
                });
            }
        },

        addEditorTab: function (e, target) {
            var file = target.dataset.templateFile;

            templateEditorAddTab(file);
        },

        cssEquationHelper: function (e) {
            var params = this.params,
                url = 'themewizard_equation';

            e.preventDefault();

            url += '&theme=' + encodeURIComponent(params.theme);
            url += '&css_equation=' + encodeURIComponent(document.getElementById('css_equation_' + params.fileId).value);

            $cms.loadSnippet(url).then(function (result) {
                if (!result || result.includes('<html')) {
                    $cms.ui.alert('{!ERROR_OCCURRED;^}');
                } else {
                    document.getElementById('css_result_' + params.fileId).value = result;
                }
            });
        }
    });

    function templateEditorPreview(fileId, url, button, askForUrl) {
        if (askForUrl === undefined) {
            askForUrl = false;
        }

        var hasEditarea = editareaIsLoaded('e_' + fileId);
        if (hasEditarea) {
            editareaReverseRefresh('e_' + fileId);
        }

        if (document.getElementById('mobile_preview_' + fileId).checked) {
            url += (url.indexOf('?') === -1) ? '?' : '&';
            url += 'keep_mobile=1';
        }

        if (askForUrl) {
            $cms.ui.prompt(
                '{!themes:URL_TO_PREVIEW_WITH;^}',
                url,
                function (url) {
                    if (url !== null) {
                        button.form.action = url;
                        $dom.submit(button.form);
                    }
                },
                '{!PREVIEW;^}'
            );

            return false;
        }

        button.form.action = url;

        return true;
    }

    function loadContextualCssEditor(file, fileId) {
        var ui = document.getElementById('selectors_' + fileId);
        ui.style.display = 'block'; // Un-hide it
        var list = document.createElement('ul');
        list.id = 'selector_list_' + fileId;
        document.getElementById('selectors_inner_' + fileId).appendChild(list);

        setUpParentPageHighlighting(file, fileId);

        // Set up background compiles
        var textareaId = 'e_' + fileId;
        if (editareaIsLoaded(textareaId)) {
            var editor = window.aceEditors[textareaId];

            var lastCss = editareaGetValue(textareaId);

            editor.cssRecompilerTimer = setInterval(function () {
                if ((window.opener) && (window.opener.document)) {
                    if (editor.lastChange === undefined) { // No change made at all
                        return;
                    } 

                    var millisecondsAgo = (new Date()).getTime() - editor.lastChange;
                    if (millisecondsAgo > 3 * 1000) { // Not changed recently enough (within last 3 seconds)
                        return;
                    } 

                    if (window.opener.haveSetUpParentPageHighlighting === undefined) {
                        setUpParentPageHighlighting(file, fileId);
                        lastCss = '';
                        /*force new CSS to apply*/
                    }

                    var newCss = editareaGetValue(textareaId);
                    if (newCss == lastCss) {// Not changed
                        return;
                    } 

                    var url = $util.url('data/snippet.php?snippet=css_compile__text' + $cms.keep()),
                        post = 'css=' + encodeURIComponent(newCss);
                    if ($cms.form.isModSecurityWorkaroundEnabled()) {
                        post = $cms.form.modSecurityWorkaroundAjax(post);
                    }
                    $cms.doAjaxRequest(url, null, post).then(function (xhr) {
                        receiveCompiledCss(xhr, file);
                    });

                    lastCss = newCss;
                }
            }, 2000);
        }

        function receiveCompiledCss(ajaxResultFrame, file, win) {
            var doingCssFor = file.replace(/^css\//, '').replace('.css', '');

            win || (win = window.opener);

            if (win) {
                try {
                    var css = ajaxResultFrame.responseText;

                    // Remove old link tag
                    var e;
                    if (doingCssFor === 'no_cache') {
                        e = win.document.getElementById('inline_css');
                        if (e) {
                            e.parentNode.removeChild(e);
                        }
                    } else {
                        var links = win.document.getElementsByTagName('link');
                        for (var i = 0; i < links.length; i++) {
                            e = links[i];
                            if ((e.type === 'text/css') && (e.href.indexOf('/templates_cached/' + window.opener.$cms.userLang() + '/' + doingCssFor) !== -1)) {
                                e.parentNode.removeChild(e);
                            }
                        }
                    }

                    // Create style tag for this
                    var style = win.document.getElementById('style_for_' + doingCssFor);
                    if (!style) {
                        style = win.document.createElement('style');
                    }
                    style.type = 'text/css';
                    style.id = 'style_for_' + doingCssFor;
                    if (style.styleSheet) {
                        style.styleSheet.cssText = css;
                    } else {
                        if (style.childNodes[0] !== undefined) {
                            style.removeChild(style.childNodes[0]);
                        }
                        var tn = win.document.createTextNode(css);
                        style.appendChild(tn);
                    }
                    win.document.querySelector('head').appendChild(style);

                    for (var j = 0; j < win.frames.length; j++) {
                        if (win.frames[j]) {// If test needed for some browsers, as window.frames can get out-of-date
                            receiveCompiledCss(ajaxResultFrame, file, win.frames[j]);
                        }
                    }
                } catch (ex) {}
            }
        }

        function setUpParentPageHighlighting(file, fileId) {
            window.opener.haveSetUpParentPageHighlighting = true;

            var doingCssFor = file.replace(/^css\//, '').replace('.css', '');

            var li, a, selector, elements, element, j, css_text;

            var selectors = findActiveSelectors(doingCssFor, window.opener);

            var list = document.getElementById('selector_list_' + fileId);
            $dom.html(list, '');

            for (var i = 0; i < selectors.length; i++) {
                selector = selectors[i].selectorText;

                // Add to list of selectors
                li = document.createElement('li');
                a = document.createElement('a');
                li.appendChild(a);
                a.href = '#!';
                a.id = 'selector_' + i;
                $dom.html(a, $cms.filter.html(selector));
                list.appendChild(li);

                // Add tooltip so we can see what the CSS text is in when hovering the selector
                css_text = (selectors[i].cssText === undefined) ? selectors[i].style.cssText : selectors[i].cssText;
                if (css_text.indexOf('{') !== -1) {
                    css_text = css_text.replace(/ \{ /g, ' {<br />\n&nbsp;&nbsp;&nbsp;').replace(/; \}/g, '<br />\n}').replace(/; /g, ';<br />\n&nbsp;&nbsp;&nbsp;');
                } else  { // IE
                    css_text = css_text.toLowerCase().replace(/; /, ';<br />\n');
                }
                li.addEventListener('mouseout', function (event) {
                    $cms.ui.deactivateTooltip(this);
                });
                li.addEventListener('mousemove', function (event) {
                    $cms.ui.repositionTooltip(this, event);
                });
                li.addEventListener('mouseover', (function (cssText) {
                    return function (event) {
                        $cms.ui.activateTooltip(this, event, cssText, 'auto');
                    };
                }(css_text)));

                // Jump-to
                a.addEventListener('click', (function (selector) {
                    return function () {
                        editareaDoSearch(
                            'e_' + fileId,
                            '^[ \t]*' + selector.replace(/\./g, '\\.').replace(/\[/g, '\\[').replace(/\]/g, '\\]').replace(/\{/g, '\\{').replace(/\}/g, '\\}').replace(/\+/g, '\\+').replace(/\*/g, '\\*').replace(/\s/g, '[ \t]+') + '\\s*\\{'
                        );
                        return false;
                    };
                }(selector)));

                // Highlighting on parent page
                a.addEventListener('onmouseover', (function (selector) {
                    return function (event) {
                        if ((window.opener) && (!event.ctrlKey) && (!event.metaKey)) {
                            var elements = findSelectorsFor(window.opener, selector);
                            for (var i = 0; i < elements.length; i++) {
                                elements[i].style.outline = '3px dotted green';
                                elements[i].style.backgroundColor = 'green';
                            }
                        }
                    };
                }(selector)));
                a.addEventListener('mouseout', (function (selector) {
                    return function (event) {
                        if ((window.opener) && (!event.ctrlKey) && (!event.metaKey)) {
                            var elements = findSelectorsFor(window.opener, selector);
                            for (var i = 0; i < elements.length; i++) {
                                elements[i].style.outline = '';
                                elements[i].style.backgroundColor = '';
                            }
                        }
                    };
                }(selector)));

                // Highlighting from parent page
                elements = findSelectorsFor(window.opener, selector);
                for (j = 0; j < elements.length; j++) {
                    element = elements[j];

                    element.addEventListener('mouseover', function (a, element) {
                        return function (event) {
                            if (window && !event.ctrlKey && !event.metaKey) {
                                var target = event.target;
                                var targetDistance = 0;
                                var elementRecurse = element;
                                do {
                                    if (elementRecurse == target) {
                                        break;
                                    }
                                    elementRecurse = elementRecurse.parentNode;
                                    targetDistance++;
                                } while (elementRecurse);
                                if (targetDistance > 10) { // Max range
                                    targetDistance = 10;
                                } 

                                a.style.outline = '1px dotted green';
                                a.style.background = '#00' + ($util.decToHex(255 - targetDistance * 25)) + '00';
                                if (targetDistance > 4) {
                                    a.style.color = 'white';
                                } else {
                                    a.style.color = 'black';
                                }
                            }
                        };
                    }(a, element));
                    element.addEventListener('mouseout', function (a) {
                        return function (event) {
                            if ((window) && (!event.ctrlKey) && (!event.metaKey)) {
                                a.style.outline = '';
                                a.style.background = '';
                                a.style.color = '';
                            }
                        };
                    }(a));
                }
            }

            function findSelectorsFor(opener, selector) {
                var result = [], result2;
                try {
                    result2 = opener.document.querySelectorAll(selector);
                    for (var j = 0; j < result2.length; j++) {
                        result.push(result2[j]);
                    }
                } catch (e) {}
                
                for (var i = 0; i < opener.frames.length; i++) {
                    if (opener.frames[i]) // If test needed for some browsers, as window.frames can get out-of-date
                    {
                        result2 = findSelectorsFor(opener.frames[i], selector);
                        for (var j = 0; j < result2.length; j++) {
                            result.push(result2[j]);
                        }
                    }
                }
                return result;
            }

            function findActiveSelectors(match, win) {
                var test, selector, selectors = [], classes, i, j, result2;
                try {
                    for (i = 0; i < win.document.styleSheets.length; i++) {
                        try {
                            if (
                                (!match) ||
                                (!win.document.styleSheets[i].href && ((win.document.styleSheets[i].ownerNode && win.document.styleSheets[i].ownerNode.id === 'style_for_' + match) ||
                                (!win.document.styleSheets[i].ownerNode && win.document.styleSheets[i].id === 'style_for_' + match))) ||
                                (win.document.styleSheets[i].href && win.document.styleSheets[i].href.indexOf('/' + match) !== -1) ||
                                (win.document.styleSheets[i].href && win.document.styleSheets[i].href.indexOf('sheet=' + match) !== -1)
                            ) {
                                classes = win.document.styleSheets[i].rules || win.document.styleSheets[i].cssRules;
                                for (j = 0; j < classes.length; j++) {
                                    selector = classes[j].selectorText;
                                    test = win.document.querySelectorAll(selector);
                                    if (test.length !== 0) {
                                        selectors.push(classes[j]);
                                    }
                                }
                            }
                        } catch (e) {}
                    }
                } catch (e) {}

                for (i = 0; i < win.frames.length; i++) {
                    if (win.frames[i]) {// If test needed for some browsers, as window.frames can get out-of-date
                        result2 = findActiveSelectors(match, win.frames[i]);
                        for (j = 0; j < result2.length; j++) {
                            selectors.push(result2[j]);
                        }
                    }
                }

                return selectors;
            }
        }
    }

    $cms.functions.adminThemesEditTheme = function () {
        var themee = document.getElementById('theme'),
            themet = document.getElementById('title'),
            copy = document.getElementById('copy');

        if (copy) {
            copy.addEventListener('change', function () {
                if (copy.checked && !themee.value.includes('-copy')) {
                    themee.value += '-copy';
                    themet.value += ' copy';
                }
            });
        }
    };

    $cms.functions.adminThemesAddTheme = function () {
        var title = document.getElementById('title');
        title.addEventListener('change', function () {
            var codename = document.getElementById('theme');
            if (codename.value === '') {
                codename.value = title.value.replace(/[^{$URL_CONTENT_REGEXP_JS}]/g, '');
            }
        });
        var form = document.getElementById('main_form'),
            submitBtn = document.getElementById('submit-button'),
            validValue;
        
        form.addEventListener('submit', function submitCheck(e) {
            var value = form.elements['theme'].value,
                url = '{$FIND_SCRIPT_NOHTTP;,snippet}?snippet=exists_theme&name=' + encodeURIComponent(value);

            if (value === validValue) {
                return;
            }
            
            submitBtn.disabled = true;
            e.preventDefault();
            $cms.form.doAjaxFieldTest(url).then(function (valid) {
                if (valid) {
                    validValue = value;
                    $dom.submit(form);
                } else {
                    submitBtn.disabled = false;
                }
            });
        });
    };

    $cms.templates.tempcodeTesterScreen = function tempcodeTesterScreen(params, container) {
        $dom.on(container, 'click', '.js-click-btn-tempcode-tester-do-preview', function (e, btn) {
            var request = '';

            for (var i = 0; i < btn.form.elements.length; i++) {
                request += encodeURIComponent(btn.form.elements[i].name) + '=' + encodeURIComponent(btn.form.elements[i].value) + '&';
            }

            $cms.doAjaxRequest('{$FIND_SCRIPT;,tempcode_tester}' + $cms.keep(true), null, request).then(function (xhr) {
                $dom.html('#preview_raw', $cms.filter.html(xhr.responseText));
                $dom.html('#preview_html', xhr.responseText);
            });

            $cms.doAjaxRequest('{$FIND_SCRIPT;,tempcode_tester}?comcode=1' + $cms.keep(), null, request).then(function (xhr) {
                $dom.html('#preview_comcode', xhr.responseText);
            });
        });
    };

    $cms.templates.themeTemplateEditorTempcodeDropdown = function themeTemplateEditorTempcodeDropdown(params, container) {
        var fileId = strVal(params.fileId),
            stub = strVal(params.stub);

        $dom.on(container, 'click', '.js-click-template-insert-parameter', function () {
            templateInsertParameter('b_' + fileId + '_' + stub, fileId);
        });

        function templateInsertParameter(dropdownName, fileId) {
            var textbox = document.getElementById('e_' + fileId);

            editareaReverseRefresh('e_' + fileId);

            var dropdown = document.getElementById(dropdownName);
            var value = dropdown.options[dropdown.selectedIndex].value;
            var valueParts = value.split('__');
            value = valueParts[0];
            if (value === '---') {
                return false;
            }

            var hasEditarea = editareaIsLoaded(textbox.name);

            if ((value === 'BLOCK') && (($cms.ui.showModalDialog !== undefined) || $cms.configOption('js_overlays'))) {
                var url = '{$FIND_SCRIPT_NOHTTP;,block_helper}?field_name=' + textbox.name + '&block_type=template' + $cms.keep();
                $cms.ui.showModalDialog(
                    $cms.maintainThemeInLink(url),
                    null,
                    'dialogWidth=750;dialogHeight=600;status=no;resizable=yes;scrollbars=yes;unadorned=yes',
                    function () {
                        if (hasEditarea) {
                            editareaRefresh(textbox.name);
                        }
                    }
                );
                return;
            }

            var arity = valueParts[1];
            var definiteGets = 0;
            if (arity === '1') {
                definiteGets = 1;
            } else if (arity === '2') {
                definiteGets = 2;
            } else if (arity === '3') {
                definiteGets = 3;
            } else if (arity === '4') {
                definiteGets = 4;
            } else if (arity === '5') {
                definiteGets = 5;
            } else if (arity === '0-1') {
                definiteGets = 0;
            } else if (arity === '3-4') {
                definiteGets = 3;
            } else if (arity === '0+') {
                definiteGets = 0;
            } else if (arity === '1+') {
                definiteGets = 1;
            }
            var parameter = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];

            _getParameterParameters(
                definiteGets, parameter, arity, textbox,
                dropdownName, value, 0, '',
                function (textbox, name, value, params) {
                    if (name.indexOf('ppdirective') !== -1) {
                        window.$editing.insertTextboxWrapping(textbox, '{' + '+START,' + value + params + '}', '{' + '+END}').then(function () {
                            if (hasEditarea) {
                                editareaRefresh(textbox.name);
                            }
                        });
                    } else {
                        var stValue;
                        if (name.indexOf('ppparameter') === -1) {
                            stValue = '{' + '$';
                        } else {
                            stValue = '{';
                        }

                        value = stValue + value + '*' + params + '}';

                        window.$editing.insertTextbox(textbox, value).then(function () {
                            if (hasEditarea) {
                                editareaRefresh(textbox.name);
                            }
                        });
                    }
                }
            );

            return false;

            function _getParameterParameters(definiteGets, parameter, arity, box, name, value, numDone, params, callback) {
                if (numDone < definiteGets) {
                    $cms.ui.prompt(
                        '{!themes:INPUT_NECESSARY_PARAMETER;^}' + ', ' + parameter[numDone],
                        '',
                        function (v) {
                            if (v !== null) {
                                params = params + ',' + v;
                                _getParameterParameters(definiteGets, parameter, arity, box, name, value, numDone + 1, params, callback);
                            }
                        },
                        '{!themes:INSERT_PARAMETER;^}'
                    );
                } else {
                    if ((arity === '0+') || (arity === '1+')) {
                        $cms.ui.prompt(
                            '{!themes:INPUT_OPTIONAL_PARAMETER;^}',
                            '',
                            function (v) {
                                if (v !== null) {
                                    params = params + ',' + v;
                                    _getParameterParameters(definiteGets, parameter, arity, box, name, value, numDone + 1, params, callback);
                                } else {
                                    callback(box, name, value, params);
                                }
                            },
                            '{!themes:INSERT_PARAMETER;^}'
                        );
                    }
                    else if ((arity === '0-1') || (arity === '3-4')) {
                        $cms.ui.prompt(
                            '{!themes:INPUT_OPTIONAL_PARAMETER;^}',
                            '',
                            function (v) {
                                if (v != null) {
                                    params = params + ',' + v;
                                }
                                
                                callback(box, name, value, params);
                            },
                            '{!themes:INSERT_PARAMETER;^}'
                        );
                    } else {
                        callback(box, name, value, params);
                    }
                }
            }
        }
    };

    $cms.templates.templateEditLink = function templateEditLink(params, container) {
        var editUrl = strVal(params.editUrl);

        $dom.on(container, 'click', '.js-click-open-edit-url', function () {
            window.open(editUrl);
        });

        $dom.on(container, 'click', '.js-keypress-open-edit-url', function () {
            window.open(editUrl);
        });
    };

    $cms.templates.themeTemplateEditorScreen = function themeTemplateEditorScreen(params, container) {
        window.templateEditorTheme = params.theme;

        if (params.activeGuid !== undefined) {
            window.templateEditorActiveGuid = params.activeGuid;
        }

        if (params.livePreviewUrl !== undefined) {
            window.templateEditorLivePreviewUrl = params.livePreviewUrl;
        }

        templateEditorCleanTabs();

        $cms.requireJavascript('tree_list').then(function () {
            window.sitemap = $cms.ui.createTreeList('theme_files', 'data/ajax_tree.php?hook=choose_theme_files&theme=' + params.theme + $cms.keep(), null, '', false, null, false, true);
        });

        setTimeout(function () {
            for (var i = 0, len = params.filesToLoad.length; i < len; i++) {
                templateEditorAddTab(params.filesToLoad[i]);
            }
        }, 1000);

        window.jQuery && window.jQuery.fn.resizable && window.jQuery('.template-editor-file-selector').resizable();

        templateEditorAssignUnloadEvent();

        $dom.on(container, 'change', '.js-change-template-editor-add-tab-wrap', function () {
            templateEditorAddTab(document.getElementById('theme_files').value);
        });

        $dom.on(container, 'click', '.js-click-btn-add-template', function () {
            addTemplate();
        });

        function addTemplate() {
            $cms.ui.prompt(
                '{!themes:INPUT_TEMPLATE_TYPE;^}',
                'templates',
                function (subdir) {
                    if (subdir !== null) {
                        if (subdir !== 'templates' && subdir !== 'css' && subdir !== 'javascript' && subdir !== 'text' && subdir !== 'xml') {
                            $cms.ui.alert('{!themes:BAD_TEMPLATE_TYPE;^}');
                            return;
                        }

                        $cms.ui.prompt(
                            '{!themes:INPUT_TEMPLATE_NAME;^}',
                            'example',
                            function (file) {
                                if (file !== null) {
                                    file = file.replace(/\..*$/, '');
                                    switch (subdir) {
                                        case 'templates':
                                            file += '.tpl';
                                            break;

                                        case 'css':
                                            file += '.css';
                                            break;

                                        case 'javascript':
                                            file += '.js';
                                            break;

                                        case 'text':
                                            file += '.txt';
                                            break;

                                        case 'xml':
                                            file += '.xml';
                                            break;
                                    }

                                    templateEditorAddTab(file);
                                }
                            },
                            '{!themes:ADD_TEMPLATE;^}'
                        );
                    }
                },
                '{!themes:ADD_TEMPLATE;^}'
            );
        }

        function templateEditorAssignUnloadEvent() {
            window.addEventListener('beforeunload', function (event) {
                if (document.querySelector('.file-changed')) {
                    $cms.undoStaffUnloadAction();
                    window.unloaded = false;

                    var ret = '{!themes:UNSAVED_TEMPLATE_CHANGES;^}';
                    event.returnValue = ret; // Workaround Chrome issue (explained on https://developer.mozilla.org/en-US/docs/Web/Events/beforeunload)
                    return ret;
                }
                return null;
            });
        }
    };

    $cms.templates.themeImageManageScreen = function () {
        window.mainFormVerySimple = true;
    };

    $cms.templates.themeTemplateEditorRestoreRevision = function (params, container) {
        var file = strVal(params.file),
            revisionId = strVal(params.revisionId);

        $dom.on(container, 'click', function () {
            templateEditorRestoreRevision(file, revisionId);
        });

        function templateEditorRestoreRevision(file, revisionId) {
            var fileId = fileToFileId(file);

            // Set content from revision
            var url = templateEditorLoadingUrl(file, revisionId);
            $cms.loadSnippet(url).then(function (html) {
                document.getElementById('t_' + fileId).className = 'tab tab-active';

                templateEditorTabLoadedContent(html, file);
            });

            return false;
        }
    };

    $cms.templates.templateTreeItem = function templateTreeItem(params, container) {
        $dom.on(container, 'click', '.js-click-checkbox-toggle-guid-input', function (e, checkbox) {
            var el = $dom.$('#f' + checkbox.id.replace(/file/, 'guid'));
            if (el) {
                el.disabled = !checkbox.checked;
            }
        });
    };

    $cms.templates.themeScreenPreview = function (params, container) {
        var template = strVal(params.template);

        $dom.on(container, 'click', '.js-link-click-open-template-preview-window', function (e, link) {
            window.open(link.href, 'template_preview_' + template, 'width=800,height=600,status=no,resizable=yes,scrollbars=yes');
        });

        $dom.on(container, 'click', '.js-link-click-open-mobile-template-preview-window', function (e, link) {
            window.open(link.href, 'template_preview_' + template, 'width=320,height=480,status=no,resizable=yes,scrollbars=yes');
        });
    };

    function cleanupTemplateMarkers(win) {
        if (window.doneCleanupTemplateMarkers) {
            return;
        }

        _cleanupTemplateMarkers(win.document.body, 0);

        window.doneCleanupTemplateMarkers = true;

        function _cleanupTemplateMarkers(node, depth) {
            var inside = [];

            node = node.firstChild;
            while (node) {
                if (node.nodeType === 3) { // Text node
                    var matches = node.data.match(/[\u200B\uFEFF]+/g);
                    if (matches) {
                        var allDecoded = [];
                        for (var i = 0; i < matches.length; i++) {
                            var decoded = invisibleOutputDecode(matches[i]);
                            var _decoded = decoded.match(/<\/?templates\/[^<>]*>/g);
                            for (var j = 0; j < _decoded.length; j++) {
                                allDecoded.push(_decoded[j]);
                            }
                        }
                        for (var i = 0; i < allDecoded.length; i++) {
                            var decoded = allDecoded[i];
                            var openerMatch = decoded.match('<(templates/.*)>');
                            if (openerMatch != null) {
                                inside.push(openerMatch[1]);
                            }
                            var closerMatch = decoded.match('</(templates/.*)>');
                            if (closerMatch != null) {
                                var at = inside.indexOf(closerMatch[1]);
                                if (at !== -1) {
                                    inside.splice(at, 1);
                                }
                            }

                            node.data = node.data.replace(matches[i], ''); // Strip it, to clean document
                        }
                    }
                } else if (node.nodeType === 1) { // Element node
                    var before = node.getAttribute('data-template');
                    if (!before) {
                        before = '';
                    }
                    node.setAttribute('data-template', before + ' ' + inside.join(' ') + ' ');
                }

                // Continue...

                _cleanupTemplateMarkers(node, depth + 1);

                node = node.nextSibling;
            }

            function invisibleOutputDecode(string) {
                var ret = '';
                var i, j, character, _bitsRep, bitsRep, _bit, bit;
                var len = string.length;
                for (i = 0; i < len / 8; i++) {
                    _bitsRep = '';
                    for (_bit = 0; _bit < 8; _bit++) {
                        character = string.substr(i * 8 + _bit, 1);
                        bit = (character === "\u200B") ? "1" : "0";
                        _bitsRep += bit;
                    }
                    bitsRep = parseInt(_bitsRep, 2);
                    ret += String.fromCharCode(bitsRep);
                }

                return ret;
            }
        }
    }

    function templateEditorAddTab(file) {
        var tabTitle = file.replace(/^.*\//, ''),
            fileId = fileToFileId(file);

        // Switch to tab if exists
        if (document.getElementById('t_' + fileId)) {
            $cms.ui.selectTab('g', fileId);

            templateEditorShowTab(fileId);

            return;
        }

        // Create new tab header
        var headers = document.getElementById('template_editor_tab_headers');

        var header = document.createElement('a');
        header.setAttribute('aria-controls', 'g_' + fileId);
        header.setAttribute('role', 'tab');
        header.setAttribute('href', '#');
        header.id = 't_' + fileId;
        header.className = 'tab file_nonchanged';
        header.addEventListener('click', function (event) {
            $cms.ui.selectTab('g', fileId);
            templateEditorShowTab(fileId);
            return false;
        });

        var ext = (tabTitle.indexOf('.') !== -1) ? tabTitle.substring(tabTitle.indexOf('.') + 1, tabTitle.length) : '';
        if (ext !== '') {
            tabTitle = tabTitle.substr(0, tabTitle.length - 4);
        }
        var iconImg = document.createElement('img');
        if (ext === 'tpl') {
            iconImg.src = $util.srl('{$IMG;,icons/16x16/filetypes/tpl}');
            iconImg.setAttribute('srcset', $util.srl('{$IMG;,icons/32x32/filetypes/tpl}'));
        }
        if (ext === 'css') {
            iconImg.src = $util.srl('{$IMG;,icons/16x16/filetypes/css}');
            iconImg.setAttribute('srcset', $util.srl('{$IMG;,icons/32x32/filetypes/css}'));
        }
        if (ext === 'js') {
            iconImg.src = $util.srl('{$IMG;,icons/16x16/filetypes/js}');
            iconImg.setAttribute('srcset', $util.srl('{$IMG;,icons/32x32/filetypes/js}'));
        }
        if (ext === 'xml') {
            iconImg.src = $util.srl('{$IMG;,icons/16x16/filetypes/xml}');
            iconImg.setAttribute('srcset', $util.srl('{$IMG;,icons/32x32/filetypes/xml}'));
        }
        if (ext === 'txt' || ext === '') {
            iconImg.src = $util.srl('{$IMG;,icons/16x16/filetypes/page_txt}');
            iconImg.setAttribute('srcset', $util.srl('{$IMG;,icons/32x32/filetypes/page_txt}'));
        }
        iconImg.style.width = '16px';
        header.appendChild(iconImg);
        header.appendChild(document.createTextNode(' '));
        var span = document.createElement('span');
        span.textContent = tabTitle;
        header.appendChild(span);
        var closeButton = document.createElement('img');
        closeButton.src = $util.srl('{$IMG;,icons/16x16/close}');
        if (closeButton.srcset !== undefined) {
            closeButton.srcset = $util.srl('{$IMG;,icons/32x32/close}') + ' 2x';
        }
        closeButton.alt = '{!CLOSE;^}';
        closeButton.style.paddingLeft = '5px';
        closeButton.style.width = '16px';
        closeButton.style.height = '16px';
        closeButton.style.verticalAlign = 'middle';
        closeButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (window.templateEditorOpenFiles[file].unsavedChanges) {
                $cms.ui.confirm('{!themes:UNSAVED_CHANGES;^}'.replace('\{1\}', file), function (result) {
                    if (result) {
                        templateEditorTabUnloadContent(file);
                    }
                }, '{!Q_SURE;^}', true);
            } else {
                templateEditorTabUnloadContent(file);
            }
        });
        header.appendChild(closeButton);
        headers.appendChild(header);

        // Create new tab body
        var bodies = document.getElementById('template_editor_tab_bodies');
        var body = document.createElement('div');
        body.setAttribute('aria-labeledby', 't_' + fileId);
        body.setAttribute('role', 'tabpanel');
        body.id = 'g_' + fileId;
        body.style.display = 'none';
        var loadingImage = document.createElement('img');
        loadingImage.className = 'ajax-loading';
        loadingImage.src = $util.srl('{$IMG;,loading}');
        loadingImage.style.height = '12px';
        body.appendChild(loadingImage);
        bodies.appendChild(body);

        // Set content
        var url = templateEditorLoadingUrl(file);
        $cms.loadSnippet(url, null, true).then(function (html) {
            templateEditorTabLoadedContent(html, file);
        });

        // Cleanup
        templateEditorCleanTabs();

        // Select tab
        $cms.ui.selectTab('g', fileId);

        templateEditorShowTab(fileId);

        function templateEditorTabUnloadContent(file) {
            var fileId = fileToFileId(file),
                wasActive = templateEditorRemoveTab(fileId);

            delete window.templateEditorOpenFiles[file];

            if (wasActive) {
                // Select tab
                var c = document.getElementById('template_editor_tab_headers').firstElementChild;
                if (c != null) {
                    var nextFileId = c.id.substr(2);

                    $cms.ui.selectTab('g', nextFileId);

                    templateEditorShowTab(nextFileId);
                }
            }

            function templateEditorRemoveTab(fileId) {
                var header = document.getElementById('t_' + fileId);
                if (header) {
                    var isActive = (header.classList.contains('tab-active'));

                    header.parentNode.removeChild(header);
                    var body = document.getElementById('g_' + fileId);
                    if (body) {
                        body.parentNode.removeChild(body);
                    }

                    templateEditorCleanTabs();

                    return isActive;
                }

                return false;
            }
        }
    }

    function templateEditorLoadingUrl(file, revisionId) {
        var url = 'template_editor_load';
        url += '&file=' + encodeURIComponent(file);
        url += '&theme=' + encodeURIComponent(window.templateEditorTheme);
        if (window.templateEditorActiveGuid !== undefined) {
            url += '&active_guid=' + encodeURIComponent(window.templateEditorActiveGuid);
        }
        if (window.templateEditorLivePreviewUrl !== undefined) {
            url += '&live_preview_url=' + encodeURIComponent($cms.protectURLParameter(window.templateEditorLivePreviewUrl));
        }
        if (revisionId !== undefined) {
            url += '&undo_revision=' + encodeURIComponent(revisionId);
        }
        return url;
    }

    function templateEditorCleanTabs() {
        var headers = document.getElementById('template_editor_tab_headers');
        var bodies = document.getElementById('template_editor_tab_bodies');
        var numTabs = headers.childNodes.length;

        var header = document.getElementById('t_default');
        var body = document.getElementById('g_default');

        if (header && numTabs > 1) {
            header.parentNode.removeChild(header);
            body.parentNode.removeChild(body);
        }

        if (numTabs === 0) {
            $dom.html(headers, '<a href="#!" id="t_default" class="tab"><span>&mdash;</span></a>');
            $dom.html(bodies, '<div id="g_default"><p class="nothing-here">{!NA}</p></div>');
        }
    }

    function templateEditorTabLoadedContent(html, file) {
        var fileId = fileToFileId(file);

        $dom.html('#g_' + fileId, html);

        setTimeout(function () {
            var textareaId = 'e_' + fileId;
            if (editareaIsLoaded(textareaId)) {
                var editor = window.aceEditors[textareaId];
                var editorSession = editor.getSession();
                editorSession.on('change', function () {
                    templateEditorTabMarkChangedContent(file);
                    editor.lastChange = (new Date()).getTime();
                });
            } else {
                getFileTextbox(file).addEventListener('change', function () {
                    templateEditorTabMarkChangedContent(file);
                });
            }
        }, 1000);

        window.templateEditorOpenFiles[file] = {
            unsavedChanges: false
        };

        function templateEditorTabMarkChangedContent(file) {
            window.templateEditorOpenFiles[file].unsavedChanges = true;

            var fileId = fileToFileId(file);
            var ob = document.getElementById('t_' + fileId);
            ob.classList.remove('file_nonchanged');
            ob.classList.add('file-changed');
        }
    }

    function templateEditorShowTab(fileId) {
        setTimeout(function () {
            if (!document.getElementById('t_' + fileId) || !document.getElementById('t_' + fileId).classList.contains('tab-active')) {
                // No longer visible
                return;
            }

            if (window.opener) {// If anchored
                highlightTemplate(window.opener, fileIdToFile(fileId));
            }

            window.jQuery('#e_' + fileId.replace(/\./g, '\\.') + '_wrap').resizable({
                resize: function (event, ui) {
                    var editor = window.aceEditors['e_' + fileId];
                    if (editor !== undefined) {
                        $dom.$('#e_' + fileId.replace(/\./g, '\\.') + '__ace').style.height = '100%';
                        $dom.$('#e_' + fileId.replace(/\./g, '\\.') + '__ace').parentNode.style.height = '100%';
                        editor.resize();
                    }
                },
                handles: 's'
            });
        }, 1000);

        function fileIdToFile(fileId) {
            for (var file in window.templateEditorOpenFiles) {
                if (fileToFileId(file) == fileId) {
                    return file;
                }
            }
            return null;
        }

        function highlightTemplate(win, templatePath) {
            _highlightTemplate(win.document.body, templatePath, 0);


            function _highlightTemplate(node, templatePath, depth) {
                node = node.firstChild;
                while (node) {
                    if (node.nodeType === 1) { // Element node
                        var template = node.getAttribute('data-template');
                        var dataMatch = (template && template.includes(' ' + templatePath + ' '));
                        if (dataMatch) {
                            node.classList.add('glowing-node');
                        } else {
                            node.classList.remove('glowing-node');
                        }
                    }

                    // Continue...

                    _highlightTemplate(node, templatePath, depth + 1);

                    node = node.nextSibling;
                }
            }
        }
    }

    function getFileTextbox(file) {
        return document.getElementById('e_' + fileToFileId(file));
    }

    function fileToFileId(file) {
        return file.replace(/\//, '__').replace(/:/, '__').replace(/\./, '__');
    }
    
}(window.$cms, window.$util, window.$dom));
