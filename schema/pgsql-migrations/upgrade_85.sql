-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE TABLE icinga_notification_assignment (
  id bigserial,
  notification_id integer NOT NULL,
  filter_string TEXT NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT icinga_notification_assignment
    FOREIGN KEY (notification_id)
    REFERENCES icinga_notification (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (85, NOW());
