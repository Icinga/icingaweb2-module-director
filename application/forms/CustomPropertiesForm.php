<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use ipl\Html\FormElement\SubmitElement;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;

class CustomPropertiesForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    public function __construct(
        public readonly IcingaObject $object,
        protected array $objectProperties = []
    ) {
        $this->addAttributes(['class' => ['custom-properties-form']]);
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $this->addElement(new Dictionary(
            'properties',
            $this->objectProperties,
            ['class' => 'no-border']
        ));

        $this->addElement('submit', 'save', [
            'label' => $this->translate('Save')
        ]);
    }

    /**
     * Load form with object properties
     *
     * @param array $objectProperties
     *
     * @return void
     */
    public function load(array $objectProperties): void
    {
        $this->populate([
            'properties' => Dictionary::prepare($objectProperties)
        ]);
    }

    /**
     * Filter empty values from array
     *
     * @param array $array
     *
     * @return array
     */
    private function filterEmpty(array $array): array
    {
        return array_filter(
            array_map(function ($item) {
                if (! is_array($item)) {
                    // Recursively clean nested arrays
                    return $item;
                }

                return $this->filterEmpty($item);
            }, $array),
            function ($item) {
                return is_bool($item) || ! empty($item);
            }
        );
    }

    protected function onSuccess(): void
    {
        $vars = $this->object->vars();

        $modified = false;
        foreach ($this->getElement('properties')->getDictionary() as $key => $value) {
            if (is_array($value)) {
                $value = $this->filterEmpty($value);
            }

            if (! is_bool($value) && empty($value)) {
                $vars->set($key, null);
            } else {
                $vars->set($key, $value);
            }

            if ($modified === false && $vars->hasBeenModified()) {
                $modified = true;
            }
        }

        $vars->storeToDb($this->object);

        if ($modified) {
            Notification::success(
                sprintf(
                    $this->translate('Custom variables have been successfully modified for %s'),
                    $this->object->getObjectName(),
                )
            );
        } else {
            Notification::success($this->translate('There is nothing to change.'));
        }
    }
}
