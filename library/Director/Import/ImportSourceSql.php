<?php

namespace Icinga\Module\Director\Import;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Web\Hook\ImportSourceHook;
use Icinga\Data\Db\DbConnection;
use Icinga\Web\Form;

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
        $form->addElement('text', 'resource', array(
            'label'    => 'Resouce name',
            'required' => true,
        ));
        $form->addElement('textarea', 'query', array(
            'label'    => 'DB Query',
            'required' => true,
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
