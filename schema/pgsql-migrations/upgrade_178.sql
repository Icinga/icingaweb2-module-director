CREATE TYPE enum_live_modification_state AS ENUM('scheduled', 'succeeded', 'failed', 'impossible', 'disabled');
CREATE TYPE enum_icinga_modified_attribute_state AS ENUM('scheduled_for_reset', 'scheduled', 'applied');

ALTER TABLE director_activity_log ADD COLUMN live_modification enum_live_modification_state NOT NULL;
UPDATE director_activity_log SET live_modification = 'disabled';

CREATE TABLE icinga_modified_attribute (
    id bigserial,
    activity_id integer DEFAULT NULL,
    state enum_icinga_modified_attribute_state NOT NULL,
    action enum_activity_action NOT NULL,
    icinga_object_type VARCHAR(64) NOT NULL,
    icinga_object_name VARCHAR(255) NOT NULL,
    modification MEDIUMTEXT NOT NULL,
    ts_scheduled bigint NOT NULL,
    ts_applied bigint DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (activity_id)
       REFERENCES director_activity_log (id)
       ON DELETE CASCADE
       ON UPDATE CASCADE
);

CREATE INDEX icinga_modified_attribute_sort_idx ON icinga_modified_attribute (ts_scheduled);

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (178, NOW());
