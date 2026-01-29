<?php

namespace Icinga\Module\Director\DataType;

use Exception;
use Icinga\Data\Db\DbConnection;
use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Util;

class DataTypeSqlQuery extends DataTypeHook
{
    /** @var  \Zend_Db_Adapter_Pdo_Abstract */
    protected $db;

    protected static $cachedResult;

    protected static $cacheTime = 0;

    public function getFormElement($name, QuickForm $form)
    {
        try {
            $data = $this->fetchData();
            $error = false;
        } catch (Exception $e) {
            $data = array();
            $error = sprintf($form->translate('Unable to fetch data: %s'), $e->getMessage());
        }

        $params = [];
        if ($this->getSetting('data_type') === 'array') {
            $type = 'extensibleSet';
            $params['sorted'] = true;
            $params = ['multiOptions' => $data];
        } else {
            $params = ['multiOptions' => [
                    '' => $form->translate('- please choose -')
                ] + $data];
            $type = 'select';
        }

        $element = $form->createElement($type, $name, $params);

        if ($error) {
            $element->addError($error);
        }

        return $element;
    }

    protected function fetchData()
    {
        // TODO: Hash _:)
        //if (self::$cachedResult === null || (time() - self::$cacheTime > 3)) {
            self::$cachedResult = $this->db()->fetchPairs($this->settings['query']);
            self::$cacheTime = time();
        // }

        return self::$cachedResult;
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        Util::addDbResourceFormElement($form, 'resource');

        $form->addElement('textarea', 'query', array(
            'label'       => 'DB Query',
            'description' => 'This query should return exactly two columns, value and label',
            'required'    => true,
            'rows'        => 10,
        ));

        $form->addElement('select', 'data_type', [
            'label' => $form->translate('Target data type'),
            'multiOptions' => $form->optionalEnum([
                'string' => $form->translate('String'),
                'array'  => $form->translate('Array'),
            ]),
            'value'    => 'string',
            'required' => true,
        ]);

        return $form;
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = DbConnection::fromResourceName($this->settings['resource'])->getDbAdapter();
            // TODO: should be handled by resources:
            $this->db->exec("SET NAMES 'utf8'");
        }

        return $this->db;
    }
}
