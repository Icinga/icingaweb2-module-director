-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE TABLE director_setting (
  setting_name VARCHAR(64) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  PRIMARY KEY(setting_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 64;

