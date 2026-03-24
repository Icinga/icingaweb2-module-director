-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later


ALTER TABLE icinga_service
  DROP FOREIGN KEY icinga_host;

ALTER TABLE icinga_service
  ADD CONSTRAINT icinga_service_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 74;
