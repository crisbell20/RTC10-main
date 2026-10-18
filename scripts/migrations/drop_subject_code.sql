-- Remove Subject_Code. Clients identify subjects by name only.
-- The old unique index (Module_ID, Subject_Code) also supported fk_subject_module,
-- so add a Module_ID index first.

ALTER TABLE tbl_subject ADD INDEX idx_subject_module (Module_ID);
ALTER TABLE tbl_subject DROP INDEX uq_subject_code_module;
ALTER TABLE tbl_subject DROP COLUMN Subject_Code;
