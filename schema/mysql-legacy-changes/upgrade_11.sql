-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_zone ADD is_global ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;
