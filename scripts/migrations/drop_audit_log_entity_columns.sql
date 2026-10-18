-- Remove unused audit entity pointer columns. Details stay in Outcome.
ALTER TABLE tbl_audit_log
  DROP COLUMN Entity_Type,
  DROP COLUMN Entity_ID;
