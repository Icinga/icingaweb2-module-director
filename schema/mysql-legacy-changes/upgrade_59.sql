-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_service
  ADD COLUMN use_agent ENUM('y', 'n') NOT NULL DEFAULT 'n';

