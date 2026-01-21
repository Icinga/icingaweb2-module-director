<?php

namespace Icinga\Module\Director\Job;

use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Deployment\ConditionalConfigRenderer;
use Icinga\Module\Director\Deployment\ConditionalDeployment;

class SyncJob extends JobHook
{
    protected $rule;

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Icinga\Exception\IcingaException
     */
    public function run()
    {
        $db = $this->db();
        $id = $this->getSetting('rule_id');

        $shouldDeploy = false;
        switch ($this->getSetting('deploy', 'never')) {
            case 'side_effect_free':
                $shouldDeploy = $db->countActivitiesSinceLastDeployedConfig() == 0;
                break;
            case 'always':
                $shouldDeploy = true;
                break;
        }

        $madeChanges = false;
        if ($id === '__ALL__') {
            foreach (SyncRule::loadAll($db) as $rule) {
                if ($this->runForRule($rule)) {
                    $madeChanges = true;
                }
            }
        } else {
            $madeChanges = $this->runForRule(SyncRule::loadWithAutoIncId((int) $id, $db));
        }

        if ($madeChanges && $shouldDeploy) {
            $deployer = new ConditionalDeployment($db);
            $renderer = new ConditionalConfigRenderer($db);
            $deployer->deploy($renderer->getConfig());
        }
    }

    /**
     * @return array
     * @throws \Icinga\Exception\NotFoundError
     */
    public function exportSettings()
    {
        $settings = [
            'apply_changes' => $this->getSetting('apply_changes') === 'y',
            'deploy' => in_array($this->getSetting('deploy'), array('side_effect_free','always'), true)
        ];
        $id = $this->getSetting('rule_id');
        if ($id !== '__ALL__') {
            $settings['rule'] = SyncRule::loadWithAutoIncId((int) $id, $this->db())
                ->get('rule_name');
        }

        return $settings;
    }

    /**
     * @param SyncRule $rule
     * @return bool
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function runForRule(SyncRule $rule)
    {
        if ($this->getSetting('apply_changes') === 'y') {
            return $rule->applyChanges();
        } else {
            $rule->checkForChanges();
            return false;
        }
    }

    public static function getDescription(QuickForm $form)
    {
        return $form->translate(
            'The "Sync" job allows to run sync actions at regular intervals'
        );
    }

    /**
     * @param QuickForm $form
     * @return DirectorObjectForm|QuickForm
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        /** @var DirectorObjectForm $form */
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
            'class'        => 'autosubmit',
            'value'        => 'n',
            'multiOptions' => array(
                'y'  => $form->translate('Yes'),
                'n'  => $form->translate('No'),
            )
        ));

        if ($form->getSentOrObjectSetting('apply_changes') === 'y') {
            $form->addElement('select', 'deploy', array(
                'label'        => $form->translate('Deploy'),
                'description'  => $form->translate(
                    'In case you also want the configuration to be automatically deployed'
                    . ' when changes are made by this Sync job. For safety, the deploy is'
                    . ' normally only made if no other pending changes already exist, but'
                    . ' you can choose "Yes (always)" to override that.'
                ),
                'value'        => 'never',
                'multiOptions' => array(
                    'side_effect_free'  => $form->translate('Yes (side effect free)'),
                    'always'  => $form->translate('Yes (always)'),
                    'never'  => $form->translate('Never'),
                )
            ));
        }

        if ((string) $form->getSentOrObjectValue('job_name') !== '') {
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
        /** @var DirectorObjectForm $form */
        $db = $form->getDb();
        $query = $db->select()->from('sync_rule', array('id', 'rule_name'))->order('rule_name');
        $res = $db->fetchPairs($query);
        return array(
            null      => $form->translate('- please choose -'),
            '__ALL__' => $form->translate('Run all rules at once')
        ) + $res;
    }
}
