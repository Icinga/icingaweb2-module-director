<?php

namespace Icinga\Module\Director\Job;

use Icinga\Module\Director\Deployment\ConditionalConfigRenderer;
use Icinga\Module\Director\Deployment\ConditionalDeployment;
use Icinga\Module\Director\Deployment\DeploymentGracePeriod;
use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class ConfigJob extends JobHook
{
    public function run()
    {
        $db = $this->db();
        $deployer = new ConditionalDeployment($db);
        $renderer = new ConditionalConfigRenderer($db);
        if ($grace = $this->getSetting('grace_period')) {
            $deployer->setGracePeriod(new DeploymentGracePeriod((int) $grace, $db));
        }
        if ($this->getSetting('force_generate') === 'y') {
            $renderer->forceRendering();
        }

        $deployer->deploy($renderer->getConfig());
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'force_generate', [
            'label'        => $form->translate('Force rendering'),
            'description'  => $form->translate(
                'Whether rendering should be forced. If not enforced, this'
                . ' job re-renders the configuration only when there have been'
                . ' activities since the last rendered config'
            ),
            'value'        => 'n',
            'multiOptions' => [
                'y'  => $form->translate('Yes'),
                'n'  => $form->translate('No'),
            ]
        ]);

        $form->addElement('select', 'deploy_when_changed', [
            'label'        => $form->translate('Deploy modified config'),
            'description'  => $form->translate(
                'This allows you to immediately deploy a modified configuration'
            ),
            'value'        => 'n',
            'multiOptions' => [
                'y'  => $form->translate('Yes'),
                'n'  => $form->translate('No'),
            ]
        ]);

        $form->addElement('text', 'grace_period', array(
            'label' => $form->translate('Grace period'),
            'description' => $form->translate(
                'When deploying configuration, wait at least this amount of'
                . ' seconds unless the next deployment should take place'
            ),
            'value' => 600,
        ));

        return $form;
    }

    public static function getDescription(QuickForm $form)
    {
        return $form->translate(
            'The Config job allows you to generate and eventually deploy your'
            . ' Icinga 2 configuration'
        );
    }
}
