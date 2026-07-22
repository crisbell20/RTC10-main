-- Academic number as unique login identifier for examinees.
-- Run after ensuring no duplicate non-null Academic_Number values exist.

UPDATE tbl_user
SET Academic_Number = UPPER(TRIM(Academic_Number))
WHERE Academic_Number IS NOT NULL AND TRIM(Academic_Number) != '';

-- Optional: assign placeholder emails for examinees missing email (adjust Role_ID if needed)
UPDATE tbl_user u
INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
SET u.Email = CONCAT(LOWER(u.Academic_Number), '@rtc.examinee.local')
WHERE r.Role_Name = 'Examinee'
  AND u.Academic_Number IS NOT NULL
  AND TRIM(u.Academic_Number) != ''
  AND (u.Email IS NULL OR TRIM(u.Email) = '');

ALTER TABLE `tbl_user`
  ADD UNIQUE INDEX `uq_user_academic_number` (`Academic_Number`);
