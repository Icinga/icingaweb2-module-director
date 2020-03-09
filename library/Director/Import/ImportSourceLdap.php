<?php

namespace Icinga\Module\Director\Import;

use Icinga\Data\ResourceFactory;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Form\QuickForm;

class ImportSourceLdap extends ImportSourceHook
{
    protected $connection;

    public function fetchData()
    {
        $columns = $this->listColumns();
        $query = $this->connection()
            ->select()
            ->setUsePagedResults()
            ->from($this->settings['objectclass'], $columns);

        if ($base = $this->settings['base']) {
            $query->setBase($base);
        }
        if ($filter = $this->settings['filter']) {
            $query->setNativeFilter($filter);
        }

        if (in_array('dn', $columns)) {
            $result = $query->fetchAll();
            foreach ($result as $dn => $row) {
                $row->dn = $dn;
            }

            return $result;
        } else {
            return $query->fetchAll();
        }
    }

    public function listColumns()
    {
        return preg_split('/,\s*/', $this->settings['query'], -1, PREG_SPLIT_NO_EMPTY);
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        Util::addLDAPResourceFormElement($form, 'resource');
        $form->addElement('text', 'base', array(
            'label'       => $form->translate('LDAP Search Base'),
            'description' => $form->translate(
                'Your LDAP search base. Often something like OU=Users,OU=HQ,DC=your,DC=company,DC=tld'
            )
        ));
        $form->addElement('text', 'objectclass', array(
            'label'       => $form->translate('Object class'),
            'description' => $form->translate(
                'An object class to search for. Might be "user", "group", "computer" or similar'
            )
        ));
        $form->addElement('text', 'filter', array(
            'label'    => 'LDAP filter',
            'description' => $form->translate(
                'A custom LDAP filter to use in addition to the object class. This allows'
                . ' for a lot of flexibility but requires LDAP filter skills. Simple filters'
                . ' might look as follows: operatingsystem=*server*'
            )
        ));
        $form->addElement('textarea', 'query', array(
            'label'       => $form->translate('Properties'),
            'description' => $form->translate(
                'The LDAP properties that should be fetched. This is required to be a'
                . ' comma-separated list like: "cn, dnshostname, operatingsystem, sAMAccountName"'
            ),
            'spellcheck'  => 'false',
            'required'    => true,
            'rows'        => 5,
        ));
        return $form;
    }

    protected function connection()
    {
        if ($this->connection === null) {
            $this->connection = ResourceFactory::create($this->settings['resource']);
        }

        return $this->connection;
    }
}
