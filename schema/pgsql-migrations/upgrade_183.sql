-- SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_notification
    ADD COLUMN users_var character varying(255) DEFAULT NULL;

ALTER TABLE icinga_notification
    ADD COLUMN user_groups_var character varying(255) DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (183, NOW());
