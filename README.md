# Online Attendance System (PHP + MySQL)

A web-based attendance system with:
- Faculty login and dashboard
- Student login and dashboard
- Student Excel import
- Attendance marking (default Present)
- Bulk status update (Absent / OD / Present)
- Attendance history and analytics
- Export attendance report to CSV
- Separate **Profile** and **Attendance** pages

## 1) Requirements
- PHP 8.1+
- MySQL 8+
- Composer
- Apache (XAMPP/WAMP/Laragon)

## 2) Setup
1. Create DB and tables:
   - Import [schema.sql](schema.sql)
2. Update DB credentials in [config/database.php](config/database.php)
3. Install dependencies:
   - `composer install`
4. Point web server root to this project folder.

## 3) Default Faculty Login
- Username: `faculty1`
- Password: `Faculty@123`

## 4) Student Login
- Auto-created during import:
  - Username = `roll_no`
  - Password = `application_no`

## 5) Excel Import Format
Header row should contain:
- Application Number
- Student Name
- Roll No
- Reg No
- Department
- Year of Study
- Batch
- Category
- Dayscholar/Hosteller

Supports `.xlsx`, `.xls`, `.csv`.

## 6) Main Pages
- Login: [/index.php](index.php)
- Faculty Profile: [faculty/profile.php](faculty/profile.php)
- Mark Attendance: [faculty/attendance.php](faculty/attendance.php)
- Import Students: [faculty/import_students.php](faculty/import_students.php)
- Student Dashboard: [/student/dashboard.php](student/dashboard.php)
- Student Profile: [/student/profile.php](student/profile.php)
