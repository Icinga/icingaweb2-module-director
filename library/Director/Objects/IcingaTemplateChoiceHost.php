<?php

namespace Icinga\Module\Director\Objects;

class IcingaTemplateChoiceHost extends IcingaTemplateChoice
{
    protected $table = 'icinga_host_template_choice';

    protected $objectTable = 'icinga_host';
}
