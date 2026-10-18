-- Cleanup unused enum values / columns.
-- Convert leftover values first so ALTER ENUM does not fail.

UPDATE tbl_cheating_incident SET Status = 'Open' WHERE Status <> 'Open';
UPDATE tbl_user_batch SET Status = 'Inactive' WHERE Status = 'Dropped';
UPDATE tbl_user SET Status = 'Inactive' WHERE Status = 'Suspended';

ALTER TABLE tbl_cheating_incident
  MODIFY COLUMN Status ENUM('Open') NOT NULL DEFAULT 'Open';

ALTER TABLE tbl_exam
  DROP COLUMN Exam_Type;

ALTER TABLE tbl_user_batch
  MODIFY COLUMN Status ENUM('Active','Completed','Inactive') DEFAULT 'Active';

ALTER TABLE tbl_user
  MODIFY COLUMN Status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active';
