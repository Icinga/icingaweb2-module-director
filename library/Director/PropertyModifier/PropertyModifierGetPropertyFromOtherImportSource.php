<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Objects\ImportRowModifier;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierGetPropertyFromOtherImportSource extends PropertyModifierHook
{
    protected $importSource;

    private $importedData;

    public function getName()
    {
        return 'Get a property from another Import Source';
    }

    /**
     * @inheritdoc
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        if (! $form instanceof DirectorObjectForm) {
            throw new \RuntimeException('This property modifier works only with a DirectorObjectForm');
        }
        $db = $form->getDb();
        $form->addElement('select', 'import_source_id', [
            'label'       => $form->translate('Import Source'),
            'description' => $form->translate(
                'Another Import Source. We\'re going to look up the row with the'
                . ' key matching the value in the chosen column'
            ),
            'required'    => true,
            'multiOptions' => $form->optionalEnum($db->enumImportSource()),
            'class' => 'autosubmit',
        ]);

        if ($form->hasBeenSent()) {
            $sourceId = $form->getSentValue('import_source_id');
        } else {
            $object = $form->getObject();
            if ($object instanceof ImportRowModifier) {
                $sourceId = $object->getSetting('import_source_id');
            } else {
                $sourceId = null;
            }
        }
        $extra = [];
        if ($sourceId) {
            $extra = [
                'class' => 'director-suggest',
                'data-suggestion-context' => 'importsourceproperties!' . (int) $sourceId,
            ];
        }
        $form->addElement('text', 'foreign_property', [
            'label'       => $form->translate('Property'),
            'required'    => true,
            'description' => $form->translate(
                'The property to get from the row we found in the chosen Import Source'
            ),
        ] + $extra);
    }

    /**
     * @param $settings
     * @return PropertyModifierHook
     * @throws \Icinga\Exception\NotFoundError
     */
    public function setSettings(array $settings)
    {
        if (isset($settings['import_source'])) {
            $settings['import_source_id'] = ImportSource::load(
                $settings['import_source'],
                $this->getDb()
            )->get('id');
            unset($settings['import_source']);
        }

        return parent::setSettings($settings);
    }

    public function transform($value)
    {
        $data = $this->getImportedData();

        if (isset($data[$value])) {
            return $data[$value]->{$this->getSetting('foreign_property')};
        } else {
            return null;
        }
    }

    public function exportSettings()
    {
        $settings = parent::exportSettings();
        $settings->import_source = $this->getImportSource()->getObjectName();
        unset($settings->import_source_id);

        return $settings;
    }

    protected function & getImportedData()
    {
        if ($this->importedData === null) {
            $this->importedData = $this->getImportSource()
                ->fetchLastRun(true)
                ->fetchRows([$this->getSetting('foreign_property')]);
        }

        return $this->importedData;
    }

    protected function getImportSource()
    {
        if ($this->importSource === null) {
            $this->importSource = ImportSource::loadWithAutoIncId(
                $this->getSetting('import_source_id'),
                $this->getDb()
            );
        }

        return $this->importSource;
    }
}
