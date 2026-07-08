-- Personnel rank for trainees (PNP ranks: Pat, SSg, Lt, etc.)
ALTER TABLE `tbl_user`
  ADD COLUMN `Personnel_Rank` varchar(50) DEFAULT NULL AFTER `Academic_Number`;
