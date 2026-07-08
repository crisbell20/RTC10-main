-- Extend audit log for structured tracking (Phase 2+)
ALTER TABLE `tbl_audit_log`
  MODIFY `User_ID` int(11) DEFAULT NULL,
  MODIFY `Outcome` text DEFAULT NULL,
  ADD COLUMN `Module` varchar(50) DEFAULT NULL AFTER `Action`,
  ADD COLUMN `Status` varchar(20) NOT NULL DEFAULT 'SUCCESS' AFTER `Outcome`,
  ADD COLUMN `Entity_Type` varchar(50) DEFAULT NULL AFTER `Status`,
  ADD COLUMN `Entity_ID` int(11) DEFAULT NULL AFTER `Entity_Type`,
  ADD COLUMN `IP_Address` varchar(45) DEFAULT NULL AFTER `Entity_ID`,
  ADD COLUMN `User_Role` varchar(50) DEFAULT NULL AFTER `IP_Address`;

ALTER TABLE `tbl_audit_log`
  ADD KEY `idx_audit_timestamp` (`Timestamp`),
  ADD KEY `idx_audit_module` (`Module`),
  ADD KEY `idx_audit_action` (`Action`),
  ADD KEY `idx_audit_status` (`Status`);
