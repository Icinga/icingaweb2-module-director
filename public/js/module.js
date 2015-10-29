
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
            this.module.on('rendered',     this.rendered);
            this.module.on('click', 'fieldset > legend', this.toggleFieldset);
            this.module.icinga.logger.debug('Director module initialized');
        },

        toggleFieldset: function (ev) {
            ev.stopPropagation();
            var $fieldset = $(ev.currentTarget).closest('fieldset');
            $fieldset.toggleClass('collapsed');
            this.fixFieldsetInfo($fieldset);
            this.openedFieldsets[$fieldset.attr('id')] = ! $fieldset.hasClass('collapsed');
        },

        rendered: function(ev) {
            this.module.icinga.logger.info('rendered');
            var $container = $(ev.currentTarget);
            var self = this;
            $('form', $container).each(self.restoreFieldsets.bind(self));
        },

        restoreFieldsets: function(idx, form) {
            var $form = $(form);
            var formId = $form.attr('id');
            var self = this;

            $('fieldset', $form).each(function(idx, fieldset) {
                var $fieldset = $(fieldset);
                if ($('.required', $fieldset).length == 0 && (! self.fieldsetWasOpened($fieldset))) {
                    $fieldset.addClass('collapsed');
                    self.fixFieldsetInfo($fieldset);
                }
            });
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
                var cnt = $('dt', $fieldset).length;
                $('legend', $fieldset).append($('<span class="element-count"> (' + cnt + ')</span>'));
            } else {
                $('legend span.element-count', $fieldset).remove();
            }
        }
    };

    Icinga.availableModules.director = Director;

}(Icinga));

