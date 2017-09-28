
(function(Icinga) {

    var Director = function(module) {
        this.module = module;

        this.initialize();

        this.openedFieldsets = {};

        this.module.icinga.logger.debug('Director module loaded');
    };

    Director.prototype = {

        initialize: function()
        {
            /**
             * Tell Icinga about our event handlers
             */
            this.module.on('rendered', this.rendered);
            this.module.on('beforerender', this.beforeRender);
            this.module.on('click', 'fieldset > legend', this.toggleFieldset);
            // Disabled
            // this.module.on('click', 'div.controls ul.tabs a', this.detailTabClick);
            this.module.on('click', 'input.related-action', this.extensibleSetAction);
            this.module.on('focus', 'form input, form textarea, form select', this.formElementFocus);
            this.module.on('keyup', '.director-suggest', this.autoSuggest);
            this.module.on('keydown', '.director-suggest', this.suggestionKeyDown);
            this.module.on('dblclick', '.director-suggest', this.suggestionDoubleClick);
            this.module.on('focus', '.director-suggest', this.enterSuggestionField);
            this.module.on('focusout', '.director-suggest', this.leaveSuggestionField);
            this.module.on('mousedown', '.director-suggestions li', this.clickSuggestion);
            this.module.on('dblclick', 'ul.tabs a', this.tabWantsFullscreen);
            this.module.on('change', 'form input.autosubmit, form select.autosubmit', this.setAutoSubmitted);
            this.module.icinga.logger.debug('Director module initialized');
        },

        tabWantsFullscreen: function(ev) {
            var icinga = this.module.icinga;
            var $a, $container, id;

            if (icinga.ui.isOneColLayout()) {
                return;
            }

            $a = $(ev.currentTarget);
            $container = $a.closest('.container');
            id = $container.attr('id');

            icinga.loader.stopPendingRequestsFor($container);
            if (id === 'col2') {
                icinga.ui.moveToLeft();
            }

            icinga.ui.layout1col();
            icinga.history.pushCurrentState();
            ev.preventDefault();
            ev.stopPropagation();
        },

        /**
         * Autocomplete/suggestion eventhandler
         *
         * Triggered when pressing a key in a form element with suggestions
         *
         * @param ev
         */
        suggestionKeyDown: function(ev) {
            var $suggestions, $active;
            var $el = $(ev.currentTarget);

            if (ev.keyCode === 13) {
                /**
                 * RETURN key pressed. In case there are any suggestions:
                 * - let's choose the active one (if set)
                 * - stop the event
                 *
                 * This let's return bubble up in case there is no suggestion list shown
                 */
                if (this.hasSuggestions($el)) {
                    this.chooseActiveSuggestion($el);
                    ev.stopPropagation();
                    ev.preventDefault();
                } else {
                    this.removeSuggestionList($el);
                    $el.trigger('change');
                }
            } else if (ev.keyCode === 27) {
                // ESC key pressed. Remove suggestions if any
                this.removeSuggestionList($el);
            } else if (ev.keyCode === 39) {
                /**
                 * RIGHT ARROW key pressed. In case there are any suggestions:
                 * - let's choose the active one (if set)
                 * - stop the event only if an element has been chosen
                 *
                 * This allows to use the right arrow key normally in all other situations
                 */
                if (this.hasSuggestions($el)) {
                    if (this.chooseActiveSuggestion($el)) {
                        ev.stopPropagation();
                        ev.preventDefault();
                    }
                }
            } else if (ev.keyCode === 38 ) {
                /**
                 * UP ARROW key pressed. In any case:
                 * - stop the event
                 * - activate the previous suggestion if any
                 */
                ev.stopPropagation();
                ev.preventDefault();
                this.activatePrevSuggestion($el);
            } else if (ev.keyCode === 40 ) { // down
                /**
                 * DOWN ARROW key pressed. In any case:
                 * - stop the event
                 * - activate the next suggestion if any
                 */
                ev.stopPropagation();
                ev.preventDefault();
                this.activateNextSuggestion($el);
            }
        },

        suggestionDoubleClick: function (ev)
        {
            var $el = $(ev.currentTarget);
            this.getSuggestionList($el);
        },

        /**
         * Autocomplete/suggestion eventhandler
         *
         * Triggered when releasing a key in a form element with suggestions
         *
         * @param ev
         */
        autoSuggest: function(ev)
        {
            // Ignore special keys, most of them have already been handled on 'keydown'
            if (ev.keyCode === 9 || // TAB
                ev.keyCode === 13 || // RETURN
                ev.keyCode === 27 || // ESC
                ev.keyCode === 37 || // LEFT ARROW
                ev.keyCode === 38 || // UP ARROW
                ev.keyCode === 39 ) { // RIGHT ARROW
                return;
            }

            var $el = $(ev.currentTarget);
            if (ev.keyCode === 40) { // DOWN ARROW
                this.getSuggestionList($el);
            } else {
                this.getSuggestionList($el, true);
            }
        },

        /**
         * Activate the next related suggestion if any
         *
         * This walks down the suggestion list, takes care about scrolling and restarts from
         * top once reached the bottom
         *
         * @param $el
         */
        activateNextSuggestion: function($el)
        {
            var $list = this.getSuggestionList($el);
            var $next;
            var $active = $list.find('li.active');
            if ($active.length) {
                $next = $active.next('li');
                if ($next.length === 0) {
                    $next = $list.find('li').first();
                }
            } else {
                $next = $list.find('li').first();
            }
            if ($next.length) {
                // Will not happen when list is empty or last element is active
                $list.find('li.active').removeClass('active');
                $next.addClass('active');
                $list.scrollTop($next.offset().top - $list.offset().top - 64 + $list.scrollTop());
            }
        },

        /**
         * Activate the previous related suggestion if any
         *
         * This walks up through the suggestion list and takes care about scrolling.
         * Puts the focus back on the input field once reached the top and restarts
         * from bottom when moving up from there
         *
         * @param $el
         */
        activatePrevSuggestion: function($el)
        {
            var $list = this.getSuggestionList($el);
            var $prev;
            var $active = $list.find('li.active');
            if ($active.length) {
                $prev = $active.prev('li');
            } else {
                $prev = $list.find('li').last();
            }
            $list.find('li.active').removeClass('active');

            if ($prev.length) {
                $prev.addClass('active');
                $list.scrollTop($prev.offset().top - $list.offset().top - 64 + $list.scrollTop());
            } else {
                $el.focus();
                $el.val($el.val());
            }
        },

        /**
         * Whether a related suggestion list element exists
         *
         * @param $input
         * @returns {boolean}
         */
        hasSuggestionList: function($input) {
            var $ul = $input.siblings('ul.director-suggestions');
            return $ul.length > 0;
        },

        /**
         * Whether any related suggestions are currently being shown
         *
         * @param $input
         * @returns {boolean}
         */
        hasSuggestions: function($input) {
            var $ul = $input.siblings('ul.director-suggestions');
            return $ul.length > 0 && $ul.is(':visible');
        },

        /**
         * Get a suggestion list. Optionally force refresh
         *
         * @param $input
         * @param $forceRefresh
         *
         * @returns {jQuery}
         */
        getSuggestionList: function($input, $forceRefresh)
        {
            var $ul = $input.siblings('ul.director-suggestions');
            if ($ul.length) {
                if ($forceRefresh) {
                    return this.refreshSuggestionList($ul, $input);
                } else {
                    return $ul;
                }
            } else {
                $ul = $('<ul class="director-suggestions"></ul>');
                $ul.insertAfter($input);
                $ul.css({width: $input.css('width')});
                return this.refreshSuggestionList($ul, $input);
            }
        },

        /**
         * Refresh a given suggestion list
         *
         * @param $suggestions
         *
         * @param $el
         * @returns {jQuery}
         */
        refreshSuggestionList: function($suggestions, $el)
        {
            $suggestions.load(this.module.icinga.config.baseUrl + '/director/suggest', {
                value: $el.val(),
                context: $el.data('suggestion-context'),
                for_host: $el.data('suggestion-for-host')
            }, function (responseText, textStatus, jqXHR) {
                var $li = $suggestions.find('li');
                if ($li.length) {
                    $suggestions.show();
                } else {
                    $suggestions.hide();
                }
            });

            return $suggestions;
        },

        /**
         * Click handler for proposed suggestions
         *
         * @param ev
         */
        clickSuggestion: function(ev) {
            this.chooseSuggestion($(ev.currentTarget));
        },

        /**
         * Choose a specific suggestion

         * @param $suggestion
         */
        chooseSuggestion: function($suggestion)
        {
            var $el = $suggestion.closest('ul').siblings('.director-suggest');
            var val = $suggestion.text();

            if (val.match(/\.$/)) {
                $el.val(val);
                this.getSuggestionList($el, true);
            } else {
                $el.focus();
                $el.val(val);
                $el.trigger('change');
                this.getSuggestionList($el).remove();
            }
        },

        /**
         * Choose the current active suggestion related to a given element
         *
         * Returns true in case there was any, false otherwise
         *
         * @param $el
         * @returns {boolean}
         */
        chooseActiveSuggestion: function($el)
        {
            var $list = this.getSuggestionList($el);
            var $active = $list.find('li.active');
            if ($active.length === 0) {
                $active = $list.find('li:hover');
            }
            if ($active.length) {
                this.chooseSuggestion($active);
                return true;
            } else {
                $list.remove();
                return false;
            }
        },

        /**
         * Remove related suggestion list if any
         *
         * @param $el
         */
        removeSuggestionList: function($el)
        {
            if (this.hasSuggestionList($el)) {
                this.getSuggestionList($el).remove();
            }
        },

        /**
         * Show suggestions when arriving to an empty auto-completion field
         *
         * @param ev
         */
        enterSuggestionField: function(ev) {
            return;
            var $el = $(ev.currentTarget);
            if ($el.val() === '' || $el.val().match(/\.$/)) {
                this.getSuggestionList($el)
            }
        },

        /**
         * Close suggestions when leaving the related form element
         *
         * @param ev
         */
        leaveSuggestionField: function(ev) {
//            return;
            var _this = this;
            setTimeout(function() {
                _this.removeSuggestionList($(ev.currentTarget));
            }, 100);
        },

        /**
         * Sets an autosubmit flag on the container related to an event
         *
         * This will be used in beforeRender to determine whether the request has been triggered by an
         * auto-submission
         *
         * @param ev
         */
        setAutoSubmitted: function(ev) {
            $(ev.currentTarget).closest('.container').data('directorAutosubmit', 'yes');
        },

        /**
         * Caused problems with differing tabs, should not be used
         *
         * @deprecated
         */
        detailTabClick: function(ev)
        {
            var $a = $(ev.currentTarget);
            if ($a.closest('#col2').length === 0) {
                return;
            }

            this.alignDetailLinks();
        },

        /**
         * Caused problems with differing tabs, should not be used
         *
         * @deprecated
         */
        alignDetailLinks: function()
        {
            var self = this;
            var $a = $('#col2').find('div.controls ul.tabs li.active a');
            if ($a.length !== 1) {
                return;
            }

            var $leftTable = $('#col1').find('> div.content').find('table.icinga-objects');
            if ($leftTable.length !== 1) {
                return;
            }

            var tabPath = self.pathFromHref($a);

            $leftTable.find('tr').each(function(idx, tr) {
                var $tr = $(tr);
                if ($tr.is('[href]')) {
                    self.setHrefPath($tr, tabPath);
                } else {
                    // Unfortunately we currently run BEFORE the  action table
                    // handler
                    var $a = $tr.find('a[href].rowaction');
                    if ($a.length === 0) {
                        $a = $tr.find('a[href]').first();
                    }

                    if ($a.length) {
                        self.setHrefPath($a, tabPath);
                    }
                }
            });

            $leftTable.find('tr[href]').each(function(idx, tr) {
                var $tr = $(tr);
                self.setHrefPath($tr, tabPath);
            });
        },

        pathFromHref: function($el)
        {
            return this.module.icinga.utils.parseUrl($el.attr('href')).path
        },

        setHrefPath: function($el, path)
        {
            var a = this.module.icinga.utils.getUrlHelper();
            a.href = $el.attr('href');
            a.pathname = path;
            $el.attr('href', a.href);
        },

        extensibleSetAction: function(ev)
        {
            var iid, $li, $prev, $next;
            var el = ev.currentTarget;
            if (el.name.match(/__MOVE_UP$/)) {
                $li = $(el).closest('li');
                $prev = $li.prev();
                // TODO: document what's going on here.
                if ($li.find('input[type=text].autosubmit')) {
                    if (iid = $prev.find('input[type=text]').attr('id')) {
                        $li.closest('.container').data('activeExtensibleEntry', iid);
                    } else {
                        return true;
                    }
                }
                if ($prev.length) {
                    $prev.before($li.detach());
                    this.fixRelatedActions($li.closest('ul'));
                }
                ev.preventDefault();
                ev.stopPropagation();
                return false;
            } else if (el.name.match(/__MOVE_DOWN$/)) {
                $li = $(el).closest('li');
                $next = $li.next();
                // TODO: document what's going on here.
                if ($li.find('input[type=text].autosubmit')) {
                    if (iid = $next.find('input[type=text]').attr('id')) {
                        $li.closest('.container').data('activeExtensibleEntry', iid);
                    } else {
                        return true;
                    }
                }
                if ($next.length && ! $next.find('.extend-set').length) {
                    $next.after($li.detach());
                    this.fixRelatedActions($li.closest('ul'));
                }
                ev.preventDefault();
                ev.stopPropagation();
                return false;
            } else if (el.name.match(/__MOVE_REMOVE$/)) {
                // TODO: skipping for now, wasn't able to prevent web2 form
                //       submission once removed
                /*
                var $li = $(el).closest('li').remove();
                this.fixRelatedActions($li.closest('ul'));
                ev.preventDefault();
                ev.stopPropagation();
                return false;
                */
            } else if (el.name.match(/__DROP_DOWN$/)) {
                ev.preventDefault();
                ev.stopPropagation();
                var $el = $(ev.currentTarget).closest('li').find('input[type=text]');
                this.getSuggestionList($el);
                return false;
            }
        },

        fixRelatedActions: function($ul)
        {
            var $uls = $ul.find('li');
            var last = $uls.length - 1;
            if ($ul.find('.extend-set').length) {
                last--;
            }

            $uls.each(function (idx, li) {
                var $li = $(li);
                if (idx === 0) {
                    $li.find('.action-move-up').attr('disabled', 'disabled');
                    if (last === 0) {
                        $li.find('.action-move-down').attr('disabled', 'disabled');
                    } else {
                        $li.find('.action-move-down').removeAttr('disabled');
                    }
                } else if (idx === last) {
                    $li.find('.action-move-up').removeAttr('disabled');
                    $li.find('.action-move-down').attr('disabled', 'disabled');
                } else {
                    $li.find('.action-move-up').removeAttr('disabled');
                    $li.find('.action-move-down').removeAttr('disabled');
                }
            });
        },

        formElementFocus: function(ev)
        {
            var $input = $(ev.currentTarget);
            if ($input.closest('form.editor').length) {
               return;
            }
            var $set = $input.closest('.extensible-set');
            if ($set.length) {
                var $textInputs = $('input[type=text]', $set);
                if ($textInputs.length > 1) {
                    $textInputs.not(':first').attr('tabIndex', '-1');
                }
            }

            var $dd = $input.closest('dd');
            $dd.find('p.description').show();
            if ($dd.attr('id') && $dd.attr('id').match(/button/)) {
                return;
            }
            var $li = $input.closest('li');
            var $dt = $dd.prev();
            var $form = $dd.closest('form');

            var $desc = $dd.find('p.description');
            if ($desc.length) {
                $form.css({ marginBottom: ($desc.height() + 48) + 'px' });
            }

            $form.find('dt, dd, li').removeClass('active');
            $li.addClass('active');
            $dt.addClass('active');
            $dd.addClass('active');

            $form.find('dd').not($dd)
                .find('p.description')
                .hide();
        },

        highlightFormErrors: function($container)
        {
            $container.find('dd ul.errors').each(function(idx, ul) {
                var $ul = $(ul);
                var $dd = $ul.closest('dd');
                var $dt = $dd.prev();

                $dt.addClass('errors');
                $dd.addClass('errors');
            });
        },

        toggleFieldset: function (ev) {
            ev.stopPropagation();
            var $fieldset = $(ev.currentTarget).closest('fieldset');
            $fieldset.toggleClass('collapsed');
            this.fixFieldsetInfo($fieldset);
            this.openedFieldsets[$fieldset.attr('id')] = ! $fieldset.hasClass('collapsed');
        },

        hideInactiveFormDescriptions: function($container) {
            $container.find('dd').not('.active').find('p.description').hide();
        },

        beforeRender: function(ev) {
            var $container = $(ev.currentTarget);
            var id = $container.attr('id');
            var requests = this.module.icinga.loader.requests;
            if (typeof requests[id] !== 'undefined' && requests[id].autorefresh) {
                $container.data('director-autorefreshed', 'yes');
            } else {
                $container.removeData('director-autorefreshed');
            }

            // Remove the temporary directorAutosubmit flag and set or remove
            // the directorAutosubmitted property accordingly
            if ($container.data('directorAutosubmit') === 'yes') {
                $container.removeData('directorAutosubmit');
                $container.data('directorAutosubmitted', 'yes');
            } else {
                $container.removeData('directorAutosubmitted');
            }
        },

        /**
         * Whether the given container has been autosubmitted
         *
         * @param $container
         * @returns {boolean}
         */
        containerIsAutoSubmitted: function($container)
        {
            return $container.data('directorAutosubmitted') === 'yes';
        },

        /**
         * Whether the given container has been autorefreshed
         *
         * @param $container
         * @returns {boolean}
         */
        containerIsAutorefreshed: function($container)
        {
            return $container.data('director-autorefreshed') === 'yes';
        },

        rendered: function(ev) {
            var iid;
            var $container = $(ev.currentTarget);
            this.restoreContainerFieldsets($container);
            this.backupAllExtensibleSetDefaultValues($container);
            this.highlightFormErrors($container);
            this.scrollHighlightIntoView($container);
            this.scrollActiveRowIntoView($container);
            this.highlightActiveDashlet($container);
            this.hideInactiveFormDescriptions($container);
            if (iid = $container.data('activeExtensibleEntry')) {
                $('#' + iid).focus();
                $container.removeData('activeExtensibleEntry');
            }
            // Disabled for now
            // this.alignDetailLinks();
            if (! this.containerIsAutorefreshed($container) && ! this.containerIsAutoSubmitted($container)) {
                this.putFocusOnFirstFormElement($container);
            }
        },

        highlightActiveDashlet: function($container)
        {
            if (this.module.icinga.ui.isOneColLayout()) {
                return;
            }

            var url, $actions, $match;
            var id = $container.attr('id');
            if (id === 'col1') {
                url = $('#col2').data('icingaUrl');
                $actions = $('.main-actions', $container);
            } else if (id === 'col2') {
                url = $container.data('icingaUrl');
                $actions = $('.main-actions', $('#col1'));
            }
            if (! $actions.length) {
                return;
            }

            $match = $('li a[href*="' + url + '"]', $actions);
            if ($match.length) {
                $('li a.active', $actions).removeClass('active');
                $match.first().addClass('active');
            }
        },

        restoreContainerFieldsets: function($container)
        {
            var self = this;
            $container.find('form').each(self.restoreFieldsets.bind(self));
        },

        putFocusOnFirstFormElement: function($container)
        {
            $container.find('form.autofocus').find('label').first().focus();
        },

        scrollHighlightIntoView: function ($container) {
            var $hl = $container.find('.highlight');
            var $content = $container.find('> div.content');

            if ($hl.length) {
              $container.animate({
                scrollTop: $hl.offset().top - $content.offset().top
              }, 700);
            }
        },

        scrollActiveRowIntoView: function ($container) {
            var $tr = $container.find('table.table-row-selectable > tbody > tr.active');
            var $content = $container.find('> div.content');
            if ($tr.length) {
                $container.animate({
                    scrollTop: $tr.offset().top - $content.offset().top
                }, 500);
            }
        },

        backupAllExtensibleSetDefaultValues: function($container) {
            var self = this;
            $container.find('.extensible-set').each(function (idx, eSet) {
                $(eSet).find('input[type=text]').each(self.backupDefaultValue);
                $(eSet).find('select').each(self.backupDefaultValue);
            });
        },

        backupDefaultValue: function(idx, el) {
            $(el).data('originalvalue', el.value);
        },

        restoreFieldsets: function(idx, form) {
            var $form = $(form);
            var self = this;
            var $sets = $('fieldset', $form);

            $sets.each(function(idx, fieldset) {
                var $fieldset = $(fieldset);
                if ($fieldset.attr('id') === 'fieldset-assign') {
                    return;
                }
                if ($fieldset.find('.required').length === 0 && (! self.fieldsetWasOpened($fieldset))) {
                    $fieldset.addClass('collapsed');
                    self.fixFieldsetInfo($fieldset);
                }
            });

            if ($sets.length === 1) {
                $sets.first().removeClass('collapsed');
            }
        },

        fieldsetWasOpened: function($fieldset) {
            var id = $fieldset.attr('id');
            if (typeof this.openedFieldsets[id] === 'undefined') {
                return false;
            }
            return this.openedFieldsets[id];
        },

        fixFieldsetInfo: function($fieldset) {
            if ($fieldset.hasClass('collapsed')) {
                if ($fieldset.find('legend span.element-count').length === 0) {
                    var cnt = $fieldset.find('dt, li').not('.extensible-set li').length;
                    if (cnt > 0) {
                        $fieldset.find('legend').append($('<span class="element-count"> (' + cnt + ')</span>'));
                    }
                }
            } else {
                $fieldset.find('legend span.element-count').remove();
            }
        }
    };

    Icinga.availableModules.director = Director;

}(Icinga));
