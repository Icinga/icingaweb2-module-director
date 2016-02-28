<?php

namespace Icinga\Module\Director\Import;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Form\QuickForm;

class ImportSourceSql extends ImportSourceHook
{
    protected $db;

    public function fetchData()
    {
        return $this->db()->fetchAll($this->settings['query']);
    }

    public function listColumns()
    {
        return array_keys((array) current($this->fetchData()));
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        Util::addDbResourceFormElement($form, 'resource');
        $form->addElement('textarea', 'query', array(
            'label'    => 'DB Query',
            'required' => true,
            'rows'     => 15,
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
