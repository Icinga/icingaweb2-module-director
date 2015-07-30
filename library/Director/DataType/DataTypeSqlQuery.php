<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Web\Hook\DataTypeHook;
use Icinga\Module\Director\Util;

class DataTypeSqlQuery extends DataTypeHook
{
    protected $db;

    protected static $cachedResult;

    protected static $cacheTime = 0;

    public function getFormElement($name, QuickForm $form)
    {
        $element = $form->createElement('select', $name, array(
            'multiOptions' => array(null => '- please choose -') +
                $this->fetchData(),
        ));

        return $element;
    }

    protected function fetchData()
    {
        if (self::$cachedResult === null || (time() - self::$cacheTime > 3)) {
            self::$cachedResult = $this->db()->fetchPairs($this->settings['query']);
            self::$cacheTime = time();
        }

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

        return $form;
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = DbConnection::fromResourceName($this->settings['resource'])->getDbAdapter();
        }

        return $this->db;
    }
}
