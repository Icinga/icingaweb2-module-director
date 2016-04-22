<?php

namespace Icinga\Module\Director\Job;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class SyncJob extends JobHook
{
    public function run()
    {
        if ($this->getSetting('apply_changes') === 'y') {
            $this->syncRule()->applyChanges();
        } else{
            $this->syncRule()->checkForChanges();
        }
    }

    public static function getDescription(QuickForm $form)
    {
        return $form->translate(
            'The "Sync" job allows to run sync actions at regular intervals'
        );
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $rules = self::enumSyncRules($form);

        $form->addElement('select', 'rule_id', array(
            'label'        => $form->translate('Synchronization rule'),
            'description'  => $form->translate(
                'Please choose your synchronization rule that should be executed.'
                . ' You could create different schedules for different rules or also'
                . ' opt for running all of them at once.'
            ),
            'required'     => true,
            'class'        => 'autosubmit',
            'multiOptions' => $rules
        ));

        $form->addElement('select', 'apply_changes', array(
            'label'        => $form->translate('Apply changes'),
            'description'  => $form->translate(
                'You could immediately apply eventual changes or just learn about them.'
                . ' In case you do not want them to be applied immediately, defining a'
                . ' job still makes sense. You will be made aware of available changes'
                . ' in your Director GUI.'
            ),
            'value'        => 'n',
            'multiOptions' => array(
                'y'  => $form->translate('Yes'),
                'n'  => $form->translate('No'),
            )
        ));

        if (! strlen($form->getSentOrObjectValue('job_name'))) {
            if (($ruleId = $form->getSentValue('rule_id')) && array_key_exists($ruleId, $rules)) {
                $name = sprintf('Sync job: %s', $rules[$ruleId]);
                $form->getElement('job_name')->setValue($name);
                ///$form->getObject()->set('job_name', $name);
            }
        }

        return $form;
    }

    protected static function enumSyncRules(QuickForm $form)
    {
        $db = $form->getDb();
        $query = $db->select()->from('sync_rule', array('id', 'rule_name'))->order('rule_name');
        $res = $db->fetchPairs($query);
        return array(
            null      => $form->translate('- please choose -'),
            '__ALL__' => $form->translate('Run all rules at once')
        ) + $res;
    }

    public function isPending()
    {
    }
}
