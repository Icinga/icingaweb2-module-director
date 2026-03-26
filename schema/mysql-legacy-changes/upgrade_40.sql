-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later


ALTER TABLE director_deployment_log ADD COLUMN config_checksum VARBINARY(20) DEFAULT NULL AFTER config_id;
ALTER TABLE director_deployment_log DROP COLUMN config_id;
ALTER TABLE director_deployment_log ADD CONSTRAINT config_checksum
    FOREIGN KEY config_checksum (config_checksum)
    REFERENCES director_generated_config (checksum)
    ON DELETE SET NULL
    ON UPDATE RESTRICT;

