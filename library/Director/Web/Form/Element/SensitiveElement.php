<?php

namespace Icinga\Module\Director\Web\Form\Element;

use ipl\Html\Attributes;
use ipl\Html\FormElement\PasswordElement;

/**
 * A password field that never returns null. An empty or untouched field
 * returns '', just like the other field types used for custom variables.
 */
class SensitiveElement extends PasswordElement
{
    /**
     * Check whether this field was left unchanged
     *
     * When a user leaves this field as it is, the browser sends back the
     * DUMMYPASSWORD placeholder instead of a real value. That is how we know
     * the field was not cleared and not given a new value.
     *
     * We can't use PasswordElement's own getValue() for this. It relies on
     * value candidates that get lost inside our nested forms (see
     * DictionaryItem::getItem() for where and why). Reading the raw value here
     * avoids that problem.
     *
     * @return bool
     */
    public function wasSubmittedUnchanged(): bool
    {
        return $this->value === self::DUMMYPASSWORD;
    }

    public function getValue()
    {
        return parent::getValue() ?? '';
    }

    /**
     * Decide what to show in the rendered 'value' attribute
     *
     * PasswordElement's own masking logic doesn't work here. It needs more
     * than one value candidate, or a flag saying the form was submitted, and
     * our nested forms (Dictionary, DictionaryItem, NestedDictionary) never
     * give it either one.
     *
     * DictionaryItem::prepare() already replaces a stored secret with the
     * DUMMYPASSWORD placeholder before it gets here. So we only need one
     * check: if the value is still that placeholder, show the placeholder.
     * Otherwise show the real value, since it can only be empty or something
     * the user just typed, never the old secret.
     *
     * We must not mask a freshly typed value either, or saving again would
     * treat it as "unchanged" and quietly bring back the old secret.
     */
    protected function registerAttributeCallbacks(Attributes $attributes)
    {
        parent::registerAttributeCallbacks($attributes);

        $attributes->registerAttributeCallback('value', function () {
            if ($this->wasSubmittedUnchanged()) {
                return self::DUMMYPASSWORD;
            }

            return $this->getValue();
        });
    }
}
