CREATE TABLE IF NOT EXISTS tbl_login_attempt (
    Attempt_ID INT NOT NULL AUTO_INCREMENT,
    User_ID INT NULL,
    Scope VARCHAR(20) NOT NULL,
    Scope_Value VARCHAR(128) NOT NULL,
    Failed_Count INT NOT NULL DEFAULT 0,
    Locked_Until DATETIME DEFAULT NULL,
    Last_Failed_At DATETIME DEFAULT NULL,
    PRIMARY KEY (Attempt_ID),
    UNIQUE KEY uq_login_attempt_scope (Scope, Scope_Value),
    KEY idx_login_attempt_locked (Locked_Until),
    KEY idx_login_attempt_user (User_ID),
    CONSTRAINT fk_login_attempt_user
        FOREIGN KEY (User_ID) REFERENCES tbl_user (User_ID)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
