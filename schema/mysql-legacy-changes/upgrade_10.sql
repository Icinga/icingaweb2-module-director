ALTER TABLE icinga_hostgroup_parent DROP FOREIGN KEY icinga_hostgroup_parent_parent;
ALTER TABLE icinga_hostgroup_parent ADD CONSTRAINT icinga_hostgroup_parent_parent
    FOREIGN KEY parent (parent_hostgroup_id)
    REFERENCES icinga_hostgroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE icinga_usergroup_parent DROP FOREIGN KEY icinga_usergroup_parent_parent;
ALTER TABLE icinga_usergroup_parent ADD CONSTRAINT icinga_usergroup_parent_parent
    FOREIGN KEY parent (parent_usergroup_id)
    REFERENCES icinga_usergroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

