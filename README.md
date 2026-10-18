# RTC10 - PNP Regional Training Center X Examination System

A comprehensive web-based examination system for the Philippine National Police Regional Training Center X.

## Features

- **Multi-Role System**: Admin, CCMD, and Examinee roles
- **Exam Management**: Create, publish, and manage exams with questions
- **Batch Management**: Organize students into batches
- **Timed Exams**: Countdown timer with auto-submit on timeout
- **Deadline System**: Google Classroom-style deadlines for exam availability
- **Auto-Close**: Exams automatically close when the deadline passes
- **Question Bank**: Centralized question repository with multiple choice support
- **Randomization**: Optional question randomization per student
- **Results & Reports**: Detailed exam results and performance tracking
- **Security**: Session management, CAPTCHA for admin/CCMD, auto-logout

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled
- Web browser with JavaScript enabled

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/Migurul/RTC10.git
   cd RTC10
   ```

2. Import the current database dump in phpMyAdmin or:
   ```bash
   mysql -u root -p db_rtc10 < path/to/your-dump.sql
   ```
   Do not place `.sql` dumps in the web root.

3. Configure database connection:
   - Copy `api/config/connection-pdo.php.example` to `api/config/connection-pdo.php`
   - Update database credentials

4. Access the system:
   ```
   http://localhost/RTC10
   ```

## Default Login Credentials

Check your database for user accounts or create one through the admin panel.

## Project Structure

```
RTC10/
├── api/                    # Backend API endpoints
│   ├── auth/              # Authentication
│   ├── admin/             # Admin APIs
│   ├── ccmd/              # CCMD APIs
│   ├── examinee/          # Examinee APIs
│   └── masterfiles/       # Master data management
├── html/                   # Frontend pages
├── js/                     # JavaScript files
├── assets/                 # CSS, images, etc.
└── scripts/               # CLI cron jobs (HTTP denied)
    ├── cron/              # Scheduled purge / auto-archive
    └── migrations/        # SQL reference only (import via MySQL CLI)

```

## Key Features Explained

### Deadline System
- **Schedule Date**: When exam becomes available
- **Deadline**: When students must START the exam
- **Duration**: How long to complete once started
- **Auto-Close**: Closes when deadline passes

### Exam Flow
1. Admin creates exam and assigns questions
2. Admin publishes exam with deadline
3. Students see exam in dashboard
4. Students start exam (timer begins)
5. Students must answer all questions to submit
6. Auto-submit if time expires
7. Exam auto-closes when deadline passes

## Development

### Auto-Close Setup (Optional)
See `scripts/CRON_SETUP.md` for scheduled task setup. Run those PHP files from the command line only.

## Security Notes

- Never commit `api/config/connection-pdo.php` (contains DB credentials)
- Do not keep database dumps in the web root
- Maintenance scripts under `scripts/` are CLI-only; Apache denies HTTP to that folder
- Change default passwords after installation
- Use HTTPS in production
- Regular database backups recommended

## License

Proprietary - PNP Regional Training Center X

## Support

For issues or questions, contact the development team.
