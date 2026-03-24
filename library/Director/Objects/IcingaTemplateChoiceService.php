<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

class IcingaTemplateChoiceService extends IcingaTemplateChoice
{
    protected $table = 'icinga_service_template_choice';

    protected $objectTable = 'icinga_service';

    protected $relations = array(
        'required_template' => 'IcingaService',
    );
}
