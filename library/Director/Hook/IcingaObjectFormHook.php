<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

abstract class IcingaObjectFormHook
{
    protected $settings = [];

    abstract public function onSetup(DirectorObjectForm $form);

    public static function callOnSetup(DirectorObjectForm $form)
    {
        /** @var static[] $implementations */
        $implementations = Hook::all('director/IcingaObjectForm');
        foreach ($implementations as $implementation) {
            $implementation->onSetup($form);
        }
    }
}
