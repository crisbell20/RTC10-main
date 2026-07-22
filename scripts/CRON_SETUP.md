# Scheduled Tasks Setup

## 1. Auto-Close Exams (existing)

Closes Published exams when their deadline passes and **finalizes attempts**:
- In-progress sessions are **auto-submitted** (scored from saved answers).
- Assigned examinees who never submitted are marked **Failed** (0%).

### Windows Task Scheduler
- Program: `C:\xampp\php\php.exe`
- Arguments: `C:\xampp\htdocs\RTC10-main\scripts\auto-close-exams.php`
- Trigger: Daily, repeat every 5 minutes

### Manual test
```bash
php C:\xampp\htdocs\RTC10-main\scripts\auto-close-exams.php
```

---

## 2. Archive & Audit Auto-Purge (2-year retention)

Permanently removes archived records older than **730 days** (2 years):
- Exams (only if no student sessions)
- Courses, Batches, Sections
- Questions (only if not assigned to exams)
- Users (only if no exam sessions)

Also purges **audit log entries** older than **730 days** (same scheduled task).

### Windows Task Scheduler (recommended)

1. Open **Task Scheduler**
2. Create Basic Task
3. Name: `PNP RTC - Archive Purge`
4. Trigger: **Daily** at 2:00 AM
5. Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\PNP-RTC10\RTC10-main\scripts\cron\purge-archived-records.php`
   - Start in: `C:\xampp\htdocs\PNP-RTC10\RTC10-main`
6. Save

### Manual test
```bash
cd C:\xampp\htdocs\PNP-RTC10\RTC10-main
C:\xampp\php\php.exe scripts\cron\purge-archived-records.php
```

Expected output:
```
[2026-07-12 02:00:00] Purged archived records (retention 730 days):
  - exams: 0
  - courses: 0
  - batches: 0
  - sections: 0
  - questions: 0
  - users: 0
  Total: 0
[2026-07-12 02:00:00] Purged audit logs (retention 730 days): 0
```

---

## Required Migrations

Run these once in phpMyAdmin or via PHP CLI:

1. **Exam archive columns** (if not done):
   ```bash
   php scripts/migrations/run_exam_code_migration.php
   ```

2. **Curriculum archive columns** (courses, modules, subjects, batches, sections):
   ```bash
   php scripts/migrations/run_curriculum_archive_migration.php
   ```

3. **User & question archive columns**:
   ```bash
   php scripts/migrations/run_user_question_archive_migration.php
   ```

Or import the SQL files from `scripts/migrations/` in phpMyAdmin.

---

## 3. Auto-Archive Expired Batches

Archives active batches when their **Date Ended** is reached (`Date_Ended <= today`). Archived batches move to the Archive page and are hidden from Manage Courses.

Runs automatically when Manage Courses loads, and via this scheduled task for batches that expire overnight.

### Windows Task Scheduler (recommended)

1. Open **Task Scheduler**
2. Create Basic Task
3. Name: `PNP RTC - Auto-Archive Batches`
4. Trigger: **Daily** at 12:05 AM
5. Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\PNP-RTC10\RTC10-main\scripts\cron\auto-archive-expired-batches.php`
   - Start in: `C:\xampp\htdocs\PNP-RTC10\RTC10-main`
6. Save

### Manual test
```bash
cd C:\xampp\htdocs\PNP-RTC10\RTC10-main
C:\xampp\php\php.exe scripts\cron\auto-archive-expired-batches.php
```

Expected output:
```
[2026-07-12 00:05:00] Auto-archived 0 expired batch(es)
```

---

## Archive Policy Summary

| Action | Behavior |
|--------|----------|
| **Archive** | Soft-hide record (`Is_Archived = 1`) |
| **Restore** | Bring back to active lists |
| **Delete (UI)** | Disabled — archive only |
| **Permanent delete** | Automatic after 2 years via scheduled purge |

Records with linked data (exam sessions, assigned questions) are **protected** from purge until dependencies are cleared.

Audit logs follow the same **2-year retention** and are purged by the same daily scheduled task.
