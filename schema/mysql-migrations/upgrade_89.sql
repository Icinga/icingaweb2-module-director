-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_command_argument
  ADD required ENUM('y', 'n') DEFAULT NULL AFTER repeat_key;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 89;
