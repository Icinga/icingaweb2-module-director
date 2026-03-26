-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE director_deployment_log
  MODIFY COLUMN startup_log TEXT DEFAULT NULL,
  ADD COLUMN stage_name VARCHAR(64) DEFAULT NULL AFTER duration_dump,
  ADD COLUMN stage_collected ENUM('y', 'n') DEFAULT NULL AFTER stage_name;

