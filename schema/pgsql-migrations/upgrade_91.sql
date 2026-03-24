-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_notification_assignment ADD assign_type enum_assign_type NOT NULL DEFAULT 'assign';

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (91, NOW());
