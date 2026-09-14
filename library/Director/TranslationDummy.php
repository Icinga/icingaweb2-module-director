<?php

namespace Icinga\Module\Director;

use ipl\I18n\Translation;

class TranslationDummy
{
    use Translation;

    protected function dummyForTranslation()
    {
        $this->translate('Host');
        $this->translate('Service');
        $this->translate('Zone');
        $this->translate('Command');
        $this->translate('User');
        $this->translate('Notification');
    }
}
