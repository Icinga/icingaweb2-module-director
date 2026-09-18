-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_host
  ADD COLUMN has_agent ENUM('y', 'n') DEFAULT NULL,
  ADD COLUMN master_should_connect ENUM('y', 'n') DEFAULT NULL,
  ADD COLUMN accept_config ENUM('y', 'n') DEFAULT NULL;

