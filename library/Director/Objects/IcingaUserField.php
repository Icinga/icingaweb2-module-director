<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

class IcingaUserField extends IcingaObjectField
{
    protected $keyName = array('user_id', 'datafield_id');

    protected $table = 'icinga_user_field';

    protected $defaultProperties = array(
        'user_id'      => null,
        'datafield_id' => null,
        'is_required'  => null,
        'var_filter'   => null,
    );
}
