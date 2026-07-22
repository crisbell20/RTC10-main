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

2. Import the database:
   ```bash
   mysql -u root -p < db_rtc.sql
   ```

3. Configure database connection:
   - Copy `api/config/connection-pdo.php.example` to `api/config/connection-pdo.php`
   - Update database credentials

4. Run migrations:
   ```bash
   php scripts/migrations/run_deadline_migration.php
   ```

5. Access the system:
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
├── scripts/               # Utility scripts and migrations
└── db_rtc.sql            # Database schema

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

### Running Migrations
```bash
php scripts/migrations/run_deadline_migration.php
```

### Auto-Close Setup (Optional)
See `scripts/CRON_SETUP.md` for scheduled task setup.

## Security Notes

- Never commit `api/config/connection-pdo.php` (contains DB credentials)
- Change default passwords after installation
- Use HTTPS in production
- Regular database backups recommended

## License

Proprietary - PNP Regional Training Center X

## Support

For issues or questions, contact the development team.
