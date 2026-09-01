# Files to Remove from Attendance Management System

## Unlinked Pages (No Longer Used)

### Student Pages

1. **`student/attendance.php`** - Remove this file
   - Reason: Functionality merged into `student/dashboard.php`
   - No longer linked in navigation
   - All attendance data now shows in dashboard

2. **`student/student_dashboard.php`** - Remove this file
   - Reason: Compatibility redirect only (redirects to dashboard.php)
   - No longer needed

## How to Remove

Use PowerShell commands to remove these files:

```powershell
# Navigate to the student directory
cd "e:\Attedence Management System\student"

# Remove unlinked files
Remove-Item -Path attendance.php
Remove-Item -Path student_dashboard.php
```

Or delete manually:
- Right-click each file
- Select Delete

## Remaining Student Pages

After removal, only these files should remain in `student/`:
- `dashboard.php` - Main student page (linked in navigation)
- `profile.php` - Student profile (linked in navigation)

## Remaining Faculty Pages

All faculty pages remain (all are linked):
- `dashboard.php` - Faculty dashboard
- `profile.php` - Faculty profile
- `attendance.php` - Mark attendance
- `add_student.php` - Manually add students
- `import_students.php` - Import students from CSV/Excel
- `export_attendance.php` - Export attendance reports

## Summary

Total files to remove: **2 unlinked pages**
- `student/attendance.php`
- `student/student_dashboard.php`

This will result in a cleaner, more organized codebase with no dead pages.
