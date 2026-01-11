## Loan Document Management System

<img src="https://github.com/user-attachments/assets/3d0811b2-c463-43f4-bef3-b15a8b5bc74a" width="400"/>

Welcome to my **Loan Document Management System**!

### Project Description
I developed this system as part of an Enterprise Software Engineering course. It runs on an AWS EC2 Linux server and uses PHP and MySQL to automate the collection, storage, and analysis of loan document data retrieved from a remote REST API. The project focuses on backend automation, reliability, and reporting over large datasets within a defined reporting window.

### Project Features
- Automated data collection using scheduled cron jobs
- REST API integration with session management, retries, and timeout handling
- MySQL database storage for loan documents and metadata
- SQL-based reporting for document counts, sizes, and loan completeness
- Error and extended response logging for API disconnects and performance issues

### Languages
- PHP
- SQL (MySQL)
- Bash (cron jobs and server scripts)

### Project Structure
- `cron/` — scheduled scripts for API data collection, auditing, and recovery
- `report/` — PHP and SQL report scripts used to generate analytical results
- `web/` — web-facing PHP pages for searching and viewing stored documents

### Environment Configuration
This project uses environment variables for configuration.

1. Copy the example file: ```cp .env.example .env```

2. Update `.env` with your database and API credentials.

The `.env` file is intentionally excluded from version control for security reasons.

### Local Setup Notes
This system was originally deployed on an AWS EC2 instance with MySQL installed. Running the scripts locally without a configured MySQL database or API access may result in database connection or API errors, which is expected.

### Status
This project is no longer actively deployed, but the repository preserves the backend automation, database usage, and reporting logic developed for the course.
