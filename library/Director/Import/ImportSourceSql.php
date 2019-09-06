<?php

namespace Icinga\Module\Director\Import;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Director\Forms\ImportSourceForm;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Form\Filter\QueryColumnsFromSql;
use Icinga\Module\Director\Web\Form\QuickForm;
use ipl\Html\Html;

class ImportSourceSql extends ImportSourceHook
{
    protected $db;

    public function fetchData()
    {
        return $this->db()->fetchAll($this->settings['query']);
    }

    public function listColumns()
    {
        if ($columns = $this->getSetting('column_cache')) {
            return explode(', ', $columns);
        } else {
            return array_keys((array) current($this->fetchData()));
        }
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        /** @var ImportSourceForm $form */
        Util::addDbResourceFormElement($form, 'resource');
        /** @var ImportSource $current */
        $current = $form->getObject();

        $form->addElement('textarea', 'query', [
            'label'    => $form->translate('DB Query'),
            'required' => true,
            'rows'     => 15,
        ]);
        $form->addElement('hidden', 'column_cache', [
            'value'    => '',
            'filters'  => [new QueryColumnsFromSql($form)],
            'required' => true
        ]);
        if ($current) {
            if ($columns = $current->getSetting('column_cache')) {
                $form->addHtmlHint('Columns: ' . $columns);
            } else {
                $form->addHtmlHint(Html::tag(
                    'p',
                    ['class' => 'warning'],
                    $form->translate(
                        'Please click "Store" once again to determine query columns'
                    )
                ));
            }
        }
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
