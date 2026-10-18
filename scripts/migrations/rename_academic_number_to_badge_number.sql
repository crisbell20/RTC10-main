-- Rename the trainee identifier from Academic Number to Badge Number.
-- Values are unchanged. tbl_academic_section is not part of this rename.

ALTER TABLE tbl_user
  CHANGE COLUMN Academic_Number Badge_Number varchar(50) NULL DEFAULT NULL;

ALTER TABLE tbl_user
  DROP INDEX uq_user_academic_number,
  ADD UNIQUE INDEX uq_user_badge_number (Badge_Number);
