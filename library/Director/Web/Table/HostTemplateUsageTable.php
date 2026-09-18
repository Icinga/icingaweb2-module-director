<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Table;

class HostTemplateUsageTable extends TemplateUsageTable
{
    public function getTypes()
    {
        return [
            'templates'  => $this->translate('Templates'),
            'objects'    => $this->translate('Objects'),
        ];
    }
}
