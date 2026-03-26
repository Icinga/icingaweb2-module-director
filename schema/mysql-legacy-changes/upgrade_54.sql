-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE director_generated_config_file MODIFY file_path VARCHAR(128) NOT NULL COMMENT 'e.g. zones/nafta/hosts.conf';

