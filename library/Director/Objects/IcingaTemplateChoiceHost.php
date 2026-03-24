<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

class IcingaTemplateChoiceHost extends IcingaTemplateChoice
{
    protected $table = 'icinga_host_template_choice';

    protected $objectTable = 'icinga_host';

    protected $relations = array(
        'required_template' => 'IcingaHost',
    );
}
