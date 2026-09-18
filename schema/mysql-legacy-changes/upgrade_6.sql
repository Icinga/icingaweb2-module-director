-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_endpoint
  MODIFY zone_id INT(10) UNSIGNED DEFAULT NULL,
  MODIFY port SMALLINT UNSIGNED DEFAULT NULL COMMENT '5665 if not set',
  MODIFY log_duration VARCHAR(32) DEFAULT NULL COMMENT '1d if not set';

