<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

abstract class ChoicesDashlet extends Dashlet
{
    protected $icon = 'flapping';

    public function getTitle()
    {
        return $this->translate('Choices');
    }

    public function getSummary()
    {
        return $this->translate(
            'Combine multiple templates into meaningful Choices, making live'
            . ' easier for your users'
        );
    }

    protected function getType()
    {
        return strtolower(substr(
            substr(get_called_class(), strlen(__NAMESPACE__) + 1),
            0,
            - strlen('ChoicesDashlet')
        ));
    }

    public function getUrl()
    {

        return 'director/templatechoices/' . $this->getType();
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
