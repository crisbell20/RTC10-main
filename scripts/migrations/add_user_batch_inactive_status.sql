-- Soft-unassign status for cascade archive/restore of courses and batches.
-- Safe to run multiple times. Also applied automatically by ensureUserBatchInactiveStatus().

ALTER TABLE `tbl_user_batch`
  MODIFY COLUMN `Status` enum('Active','Completed','Inactive') DEFAULT 'Active';
