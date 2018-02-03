(function ($cms, $util, $dom) {
    'use strict';

    var $IMG_checklist_checklist_done = '{$IMG;,icons/checklist/checklist_done}',
        $IMG_checklist_checklist_todo = '{$IMG;,icons/checklist/checklist_todo}';

    var $SCRIPT_comcode_convert = '{$FIND_SCRIPT_NOHTTP;,comcode_convert}';

    /**
     * @memberof $cms.views
     * @class BlockMainStaffChecklistCustomTask
     * @extends $cms.View
     */
    function BlockMainStaffChecklistCustomTask() {
        BlockMainStaffChecklistCustomTask.base(this, 'constructor', arguments);

        this.imgChecklistStatus = this.$('.js-img-checklist-status');
    }

    $util.inherits(BlockMainStaffChecklistCustomTask, $cms.View, /**@lends BlockMainStaffChecklistCustomTask#*/{
        events: function () {
            return {
                'mouseover': 'mouseover',
                'mouseout': 'mouseout',

                'click .js-click-confirm-delete': 'confirmDelete',
                'click .js-click-mark-task': 'markTask',
                'keypress .js-keypress-mark-task': 'markTask'
            };
        },

        markTask: function (e) {
            var data = this.el.dataset,
                id = encodeURIComponent(this.params.id);

            // Prevent events on the delete link from triggering this function
            if (this.$closest(e.target, '.js-click-confirm-delete')) {
                return;
            }

            if (data.vwTaskDone === 'checklist_todo') {
                $cms.loadSnippet('checklist_task_manage', 'type=mark_done&id=' + id);
                this.imgChecklistStatus.src = $IMG_checklist_checklist_done;
                data.vwTaskDone = 'checklist_done';
            } else {
                $cms.loadSnippet('checklist_task_manage', 'type=mark_undone&id=' + id);
                this.imgChecklistStatus.src = $IMG_checklist_checklist_todo;
                data.vwTaskDone = 'checklist_todo';
            }
        },

        confirmDelete: function () {
            var viewEl = this.el,
                params = this.params,
                message = params.confirmDeleteMessage,
                id = encodeURIComponent(params.id);

            $cms.ui.confirm(message, function (result) {
                if (result) {
                    $cms.loadSnippet('checklist_task_manage', 'type=delete&id=' + id);
                    $dom.hide(viewEl);
                }
            });
        }
    });

    /**
     * @memberof $cms.views
     * @class BlockMainStaffLinks
     * @extends $cms.View
     */
    function BlockMainStaffLinks() {
        BlockMainStaffLinks.base(this, 'constructor', arguments);
    }

    $util.inherits(BlockMainStaffLinks, $cms.View, /**@lends BlockMainStaffLinks#*/{
        events: function () {
            return {
                'click .js-click-staff-block-flip': 'staffBlockFlip',
                'click .js-click-form-submit-headless': 'submitHeadless'
            };
        },
        staffBlockFlip: function () {
            var rand = this.params.randStaffLinks,
                show = this.$('#staff-links-list-' + rand + '-form'),
                hide = this.$('#staff-links-list-' + rand),
                isHideDisplayed = $dom.isDisplayed(hide);

            $dom.toggleWithAria(show, isHideDisplayed);
            $dom.toggleWithAria(hide, !isHideDisplayed);
        },
        submitHeadless: function (e, btn) {
            var form = btn.form,
                blockName = $cms.filter.nl(this.params.blockName),
                map = $cms.filter.nl(this.params.map);

            e.preventDefault();

            ajaxFormSubmitAdminHeadless(form, blockName, map).then((function (submitForm) {
                if (submitForm) {
                    $dom.submit(form);
                }
            }).bind(this));
        }
    });

    /**
     * @memberof $cms.views
     * @class BlockMainStaffWebsiteMonitoring
     * @extends $cms.View
     */
    function BlockMainStaffWebsiteMonitoring() {
        BlockMainStaffWebsiteMonitoring.base(this, 'constructor', arguments);

        var rand = this.params.randWebsiteMonitoring;

        this.tableEl = this.$('#website-monitoring-list-' + rand);
        this.formEl = this.$('.js-form-site-watchlist');
    }

    $util.inherits(BlockMainStaffWebsiteMonitoring, $cms.View, /**@lends BlockMainStaffWebsiteMonitoring#*/{
        events: function () {
            return {
                'click .js-click-staff-block-flip': 'staffBlockFlip',
                'click .js-click-headless-submit': 'headlessSubmit'
            };
        },

        staffBlockFlip: function () {
            var isTableDisplayed = $dom.isDisplayed(this.tableEl);

            $dom.toggleWithAria(this.formEl, isTableDisplayed);
            $dom.toggleWithAria(this.tableEl, !isTableDisplayed);
        },

        headlessSubmit: function (e) {
            var blockName = $cms.filter.nl(this.params.blockName),
                map = $cms.filter.nl(this.params.map);

            e.preventDefault();

            ajaxFormSubmitAdminHeadless(this.formEl, blockName, map).then((function (submitForm) {
                if (submitForm) {
                    $dom.submit(this.formEl);
                }
            }).bind(this));
        }
    });

    /**
     * @memberof $cms.views
     * @class BlockMainNotes
     * @extends $cms.View
     */
    function BlockMainNotes() {
        BlockMainNotes.base(this, 'constructor', arguments);
        this.formEl = this.$('.js-form-block-main-notes');
    }

    $util.inherits(BlockMainNotes, $cms.View, /**@lends BlockMainNotes#*/{
        events: function () {
            return {
                'click .js-click-headless-submit': 'headlessSubmit',
                'focus .js-focus-textarea-expand': 'textareaExpand',
                'blur .js-blur-textarea-contract': 'textareaContract',
                'mouseover .js-hover-disable-textarea-size-change': 'disableTextareaSizeChange',
                'mouseout .js-hover-disable-textarea-size-change': 'disableTextareaSizeChange'
            };
        },

        headlessSubmit: function (e) {
            var blockName = $cms.filter.nl(this.params.blockName),
                map = $cms.filter.nl(this.params.map);

            e.preventDefault();

            ajaxFormSubmitAdminHeadless(this.formEl, blockName, map).then((function (submitForm) {
                if (submitForm) {
                    $dom.submit(this.formEl);
                }
            }).bind(this));
        },

        textareaExpand: function (e, textarea){
            textarea.rows = '23';
        },

        textareaContract: function (e, textarea){
            if (!this.formEl.disableSizeChange) {
                textarea.rows = '10';
            }
        },

        disableTextareaSizeChange: function (e) {
            this.formEl.disableSizeChange = (e.type === 'mouseover');
        }
    });

    $cms.views.BlockMainStaffChecklistCustomTask = BlockMainStaffChecklistCustomTask;
    $cms.views.BlockMainStaffLinks = BlockMainStaffLinks;
    $cms.views.BlockMainStaffWebsiteMonitoring = BlockMainStaffWebsiteMonitoring;
    $cms.views.BlockMainNotes = BlockMainNotes;

    $cms.templates.blockMainStaffChecklist = function (params, container) {
        var showAllLink = document.getElementById('checklist-show-all-link'),
            hideDoneLink = document.getElementById('checklist-hide-done-link');

        setTaskHiding(true);

        $dom.on(container, 'click', '.js-click-enable-task-hiding', function () {
            setTaskHiding(true);
        });

        $dom.on(container, 'click', '.js-click-disable-task-hiding', function () {
            setTaskHiding(false);
        });

        $dom.on(container, 'submit', '.js-submit-custom-task', function (e, form) {
            submitCustomTask(form);
        });

        function setTaskHiding(hideEnable) {
            hideEnable = !!hideEnable;

            var i, checklistRows = document.querySelectorAll('.checklist-row'), rowImgs, src;

            for (i = 0; i < checklistRows.length; i++) {
                rowImgs = checklistRows[i].querySelectorAll('img');
                if (hideEnable) {
                    src = rowImgs[rowImgs.length - 1].src;
                    if (rowImgs[rowImgs.length - 1].origsrc) {
                        src = rowImgs[rowImgs.length - 1].origsrc;
                    }
                    if (src && src.includes('checklist_done')) {
                        $dom.hide(checklistRows[i]);
                        checklistRows[i].classList.add('task-hidden');
                    } else {
                        checklistRows[i].classList.remove('task-hidden');
                    }
                } else {
                    if (!$dom.isDisplayed(checklistRows[i])) {
                        $dom.fadeIn(checklistRows[i]);
                    }
                    $dom.show(checklistRows[i]);
                    checklistRows[i].classList.remove('task-hidden');
                }
            }

            $dom.toggle(showAllLink, hideEnable);
            $dom.toggle(hideDoneLink, !hideEnable);
        }

        function submitCustomTask(form) {
            $cms.loadSnippet('checklist_task_manage', 'type=add&recur_every=' + encodeURIComponent(form.elements['recur_every'].value) + '&recur_interval=' + encodeURIComponent(form.elements['recur_interval'].value) + '&task_title=' + encodeURIComponent(form.elements['newTask'].value)).then(function (newTask) {
                form.elements['recur_every'].value = '';
                form.elements['recur_interval'].value = '';
                form.elements['new_task'].value = '';

                $dom.append('#custom-tasks-go-here', newTask);
            });
        }
    };

    $cms.templates.blockMainStaffActions = function (params) {
        $dom.internaliseAjaxBlockWrapperLinks(params.blockCallUrl, document.getElementById(params.wrapperId), ['.*'], {}, false, true);
    };

    $cms.templates.blockMainStaffTips = function (params) {
        $dom.internaliseAjaxBlockWrapperLinks(params.blockCallUrl, document.getElementById(params.wrapperId), ['^staff_tips_dismiss$', '^rand$'/*cache breaker*/], {}, false, true, false);
    };

    function ajaxFormSubmitAdminHeadless(form, blockName, map) {
        var post = '';

        if (blockName !== undefined) {
            blockName = strVal(blockName);
            map = strVal(map);

            var comcode = '[block' + map + ']' + blockName + '[/block]';
            post += 'data=' + encodeURIComponent(comcode);
        }

        for (var i = 0; i < form.elements.length; i++) {
            if (!form.elements[i].disabled && form.elements[i].name) {
                post += '&' + form.elements[i].name + '=' + encodeURIComponent($cms.form.cleverFindValue(form, form.elements[i]));
            }
        }

        return new Promise(function (resolve) {
            $cms.doAjaxRequest($cms.maintainThemeInLink($SCRIPT_comcode_convert + $cms.keep(true)), null, post).then(function (xhr) {
                if (xhr.responseText && (xhr.responseText !== 'false')) {
                    var result = xhr.responseXML && xhr.responseXML.querySelector('result');

                    if (result) {
                        var xhtml = result.textContent,
                            elementReplace = form;

                        while (elementReplace.className !== 'form-ajax-target') {
                            elementReplace = elementReplace.parentNode;
                            if (!elementReplace) {
                                // Oh dear, target not found
                                resolve(/*submitForm: */true);
                                return;
                            }
                        }

                        $dom.html(elementReplace, xhtml);
                        $cms.ui.alert('{!SUCCESS;^}');
                        resolve(/*submitForm: */false); // We've handled it internally
                        return;
                    }
                }

                resolve(/*submitForm: */true);
            });
        });
    }
}(window.$cms, window.$util, window.$dom));
