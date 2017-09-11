<?php

namespace Icinga\Module\Director\Web\Form\Filter;

use Exception;
use Icinga\Module\Director\Forms\ImportSourceForm;
use Zend_Filter_Interface;

class QueryColumnsFromSql implements Zend_Filter_Interface
{
    /** @var ImportSourceForm */
    private $form;

    public function __construct(ImportSourceForm $form)
    {
        $this->form = $form;
    }

    public function filter($value)
    {
        $form = $this->form;
        if (empty($value) || $form->hasChangedSetting('query')) {
            try {
                return implode(
                    ', ',
                    $this->getQueryColumns($form->getSentOrObjectSetting('query'))
                );
            } catch (Exception $e) {
                $this->form->addUniqueException($e);
                return '';
            }
        } else {
            return $value;
        }
    }

    protected function getQueryColumns($query)
    {
        return array_keys((array) current(
            $this->form->getDb()->getDbAdapter()->fetchAll($query)
        ));
    }
}
