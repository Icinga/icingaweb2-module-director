<?php

namespace Icinga\Module\Director\Web\Form\IplElement;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\IcingaConfig\ExtensibleSet as Set;
use Icinga\Module\Director\Web\Form\IconHelper;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use gipfl\Translation\TranslationHelper;

class ExtensibleSetElement extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'ul';

    /** @var Set */
    protected $set;

    private $id;

    private $name;

    private $value;

    private $description;

    private $multiOptions;

    private $validOptions;

    private $chosenOptionCount = 0;

    private $suggestionContext;

    private $sorted = false;

    private $disabled = false;

    private $remainingAttribs;

    private $hideOptions = [];

    protected $defaultAttributes = [
        'class' => 'extensible-set'
    ];

    protected function __construct($name)
    {
        $this->name = $this->id = $name;
    }

    public function hideOptions($options)
    {
        $this->hideOptions = array_merge($this->hideOptions, $options);
        return $this;
    }

    private function setMultiOptions($options)
    {
        $this->multiOptions = $options;
        $this->validOptions = $this->flattenOptions($options);
    }

    protected function isValidOption($option)
    {
        if ($this->validOptions === null) {
            if ($this->suggestionContext === null) {
                return true;
            } else {
                // TODO: ask suggestionContext, if any
                return true;
            }
        } else {
            return in_array($option, $this->validOptions);
        }
    }

    private function disable($disable = true)
    {
        $this->disabled = (bool) $disable;
    }

    private function isDisabled()
    {
        return $this->disabled;
    }

    private function isSorted()
    {
        return $this->sorted;
    }

    public function setValue($value)
    {
        if ($value instanceof Set) {
            $value = $value->toPlainObject();
        }

        if (is_array($value)) {
            $value = array_filter($value, 'strlen');
        }

        if (null !== $value && ! is_array($value)) {
            throw new ProgrammingError(
                'Got unexpected value, no array: %s',
                var_export($value, 1)
            );
        }

        $this->value = $value;
        return $this;
    }

    protected function extractZfInfo(& $attribs = null)
    {
        if ($attribs === null) {
            return;
        }

        foreach (['id', 'name', 'descriptions'] as $key) {
            if (array_key_exists($key, $attribs)) {
                $this->$key = $attribs[$key];
                unset($attribs[$key]);
            }
        }
        if (array_key_exists('disable', $attribs)) {
            $this->disable($attribs['disable']);
            unset($attribs['disable']);
        }

        if (array_key_exists('value', $attribs)) {
            $this->setValue($attribs['value']);
            unset($attribs['value']);
        }

        if (array_key_exists('multiOptions', $attribs)) {
            $this->setMultiOptions($attribs['multiOptions']);
            unset($attribs['multiOptions']);
        }

        if (array_key_exists('hideOptions', $attribs)) {
            $this->hideOptions($attribs['hideOptions']);
            unset($attribs['hideOptions']);
        }

        if (array_key_exists('sorted', $attribs)) {
            $this->sorted = (bool) $attribs['sorted'];
            unset($attribs['sorted']);
        }

        if (array_key_exists('description', $attribs)) {
            $this->description = $attribs['description'];
            unset($attribs['description']);
        }

        if (array_key_exists('suggest', $attribs)) {
            $this->suggestionContext = $attribs['suggest'];
            unset($attribs['suggest']);
        }

        if (! empty($attribs)) {
            $this->remainingAttribs = $attribs;
        }
    }

    /**
     * Generates an 'extensible set' element.
     *
     * @codingStandardsIgnoreEnd
     *
     * @param string|array $name If a string, the element name.  If an
     * array, all other parameters are ignored, and the array elements
     * are used in place of added parameters.
     *
     * @param mixed $value The element value.
     *
     * @param array $attribs Attributes for the element tag.
     *
     * @return string The element XHTML.
     */
    public static function fromZfDingens($name, $value = null, $attribs = null)
    {
        $el = new static($name);
        $el->extractZfInfo($attribs);
        $el->setValue($value);
        return $el->render();
    }

    protected function assemble()
    {
        $this->addChosenOptions();
        $this->addAddMore();

        if ($this->isSorted()) {
            $this->getAttributes()->add('class', 'sortable');
        }
        if (null !== $this->description) {
            $this->addDescription($this->description);
        }
    }

    private function eventuallyAddAutosuggestion(BaseHtmlElement $element)
    {
        if ($this->suggestionContext !== null) {
            $attrs = $element->getAttributes();
            $attrs->add('class', 'director-suggest');
            $attrs->set([
                'data-suggestion-context' => $this->suggestionContext,
            ]);
        }

        return $element;
    }

    private function hasAvailableMultiOptions()
    {
        return count($this->multiOptions) > 1 || strlen(key($this->multiOptions));
    }

    private function addAddMore()
    {
        $cnt = $this->chosenOptionCount;

        if ($this->multiOptions) {
            if (! $this->hasAvailableMultiOptions()) {
                return;
            }
            $field = Html::tag('select', ['class' => 'autosubmit']);
            $field->add(Html::tag('option', [
                'value' => '',
                'tabindex' => '-1'
            ], $this->translate('- add more -')));

            foreach ($this->multiOptions as $key => $label) {
                if ($key === null) {
                    $key = '';
                }
                if (is_array($label)) {
                    $optGroup = Html::tag('optgroup', ['label' => $key]);
                    foreach ($label as $grpKey => $grpLabel) {
                        $optGroup->add(
                            Html::tag('option', ['value' => $grpKey], $grpLabel)
                        );
                    }
                    $field->add($optGroup);
                } else {
                    $option = Html::tag('option', ['value' => $key], $label);
                    $field->add($option);
                }
            }
        } else {
            $field = Html::tag('input', [
                'type' => 'text',
                'placeholder' => $this->translate('Add a new one...'),
            ]);
        }
        $field->addAttributes([
            'id'    => $this->id . $this->suffix($cnt),
            'name'  => $this->name . '[]',
        ]);
        $this->eventuallyAddAutosuggestion(
            $this->addRemainingAttributes(
                $this->eventuallyDisable($field)
            )
        );
        if ($cnt !== 0) { // TODO: was === 0?!
            $field->getAttributes()->add('class', 'extend-set');
        }

        if ($this->suggestionContext === null) {
            $this->add(Html::tag('li', null, [
                $this->createAddNewButton(),
                $field
            ]));
        } else {
            $this->add(Html::tag('li', null, [
                $this->newInlineButtons(
                    $this->renderDropDownButton()
                ),
                $field
            ]));
        }
    }

    private function createAddNewButton()
    {
        return $this->newInlineButtons(
            $this->eventuallyDisable($this->renderAddButton())
        );
    }

    private function addChosenOptions()
    {
        if (null === $this->value) {
            return;
        }
        $total = count($this->value);

        foreach ($this->value as $val) {
            if (in_array($val, $this->hideOptions)) {
                continue;
            }

            if ($this->multiOptions !== null) {
                if ($this->isValidOption($val)) {
                    $this->multiOptions = $this->removeOption(
                        $this->multiOptions,
                        $val
                    );
                    // TODO:
                    // $this->removeOption($val);
                }
            }

            $text = Html::tag('input', [
                'type' => 'text',
                'name' => $this->name . '[]',
                'id' => $this->id . $this->suffix($this->chosenOptionCount),
                'value' => $val
            ]);
            $text->getAttributes()->set([
                'autocomplete'   => 'off',
                'autocorrect'    => 'off',
                'autocapitalize' => 'off',
                'spellcheck'     => 'false',
            ]);

            $this->addRemainingAttributes($this->eventuallyDisable($text));
            $this->add(Html::tag('li', null, [
                $this->getOptionButtons($this->chosenOptionCount, $total),
                $text
            ]));
            $this->chosenOptionCount++;
        }
    }

    private function addRemainingAttributes(BaseHtmlElement $element)
    {
        if ($this->remainingAttribs !== null) {
            $element->getAttributes()->add($this->remainingAttribs);
        }

        return $element;
    }

    private function eventuallyDisable(BaseHtmlElement $element)
    {
        if ($this->isDisabled()) {
            $this->disableElement($element);
        }

        return $element;
    }

    private function disableElement(BaseHtmlElement $element)
    {
        $element->getAttributes()->set('disabled', 'disabled');
        return $element;
    }

    private function disableIf(BaseHtmlElement $element, $condition)
    {
        if ($condition) {
            $this->disableElement($element);
        }

        return $element;
    }

    private function getOptionButtons($cnt, $total)
    {
        if ($this->isDisabled()) {
            return [];
        }
        $first = $cnt === 0;
        $last = $cnt === $total - 1;
        $name = $this->name;
        $buttons = $this->newInlineButtons();
        if ($this->isSorted()) {
            $buttons->add([
                $this->disableIf($this->renderDownButton($name, $cnt), $last),
                $this->disableIf($this->renderUpButton($name, $cnt), $first)
            ]);
        }

        $buttons->add($this->renderDeleteButton($name, $cnt));

        return $buttons;
    }

    protected function newInlineButtons($content = null)
    {
        return Html::tag('span', ['class' => 'inline-buttons'], $content);
    }

    protected function addDescription($description)
    {
        $this->add(
            Html::tag('p', ['class' => 'description'], $description)
        );
    }

    private function flattenOptions($options)
    {
        $flat = array();

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                foreach ($option as $k => $o) {
                    $flat[] = $k;
                }
            } else {
                $flat[] = $key;
            }
        }

        return $flat;
    }

    private function removeOption($options, $option)
    {
        $unset = array();
        foreach ($options as $key => & $value) {
            if (is_array($value)) {
                $value = $this->removeOption($value, $option);
                if (empty($value)) {
                    $unset[] = $key;
                }
            } elseif ($key === $option) {
                $unset[] = $key;
            }
        }

        foreach ($unset as $key) {
            unset($options[$key]);
        }

        return $options;
    }

    private function suffix($cnt)
    {
        if ($cnt === 0) {
            return '';
        } else {
            return '_' . $cnt;
        }
    }

    private function renderDropDownButton()
    {
        return $this->createRelatedAction(
            'drop-down',
            $this->name,
            $this->translate('Show available options'),
            'down-open'
        );
    }

    private function renderAddButton()
    {
        return $this->createRelatedAction(
            'add',
            // This would interfere with how PHP resolves _POST arrays. So we
            // use a fake name for now, that way the button will be ignored and
            // behave similar to an auto-submission
            'X_' . $this->name,
            $this->translate('Add a new entry'),
            'plus'
        );
    }

    private function renderDeleteButton($name, $cnt)
    {
        return $this->createRelatedAction(
            'remove',
            $name . '_' . $cnt,
            $this->translate('Remove this entry'),
            'cancel'
        );
    }

    private function renderUpButton($name, $cnt)
    {
        return $this->createRelatedAction(
            'move-up',
            $name . '_' . $cnt,
            $this->translate('Move up'),
            'up-big'
        );
    }

    private function renderDownButton($name, $cnt)
    {
        return $this->createRelatedAction(
            'move-down',
            $name . '_' . $cnt,
            $this->translate('Move down'),
            'down-big'
        );
    }

    protected function makeActionName($name, $action)
    {
        return $name . '__' . str_replace('-', '_', strtoupper($action));
    }

    protected function createRelatedAction(
        $action,
        $name,
        $title,
        $icon
    ) {
        $input = Html::tag('input', [
            'type'  => 'submit',
            'class' => ['related-action', 'action-' . $action],
            'name'  => $this->makeActionName($name, $action),
            'value' => IconHelper::instance()->iconCharacter($icon),
            'title' => $title
        ]);

        return $input;
    }
}
