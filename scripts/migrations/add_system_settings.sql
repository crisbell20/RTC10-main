CREATE TABLE IF NOT EXISTS tbl_system_setting (
    Setting_Key VARCHAR(64) NOT NULL PRIMARY KEY,
    Setting_Value VARCHAR(255) NOT NULL,
    Updated_At DATETIME DEFAULT NULL,
    Updated_By INT DEFAULT NULL,
    CONSTRAINT fk_setting_updated_by
        FOREIGN KEY (Updated_By) REFERENCES tbl_user (User_ID)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_system_setting (Setting_Key, Setting_Value) VALUES
('archive_retention_days', '730'),
('audit_log_retention_days', '730'),
('login_max_attempts', '5'),
('login_lockout_minutes', '15'),
('idle_timeout_minutes', '5')
ON DUPLICATE KEY UPDATE Setting_Value = Setting_Value;
