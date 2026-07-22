-- Remove Username column from tbl_user (login uses Academic Number or Email).
ALTER TABLE `tbl_user` DROP INDEX `uq_user_username`;
ALTER TABLE `tbl_user` DROP COLUMN `Username`;
