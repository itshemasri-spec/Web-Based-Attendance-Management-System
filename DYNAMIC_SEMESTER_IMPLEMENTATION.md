# Dynamic Semester Selection Implementation

## Overview
This document describes the implementation of dynamic semester selection based on year of study in the PHP Attendance Management System.

## Year → Semester Mapping

The system enforces the following mapping:
- **Year 1** → Semesters 1, 2
- **Year 2** → Semesters 3, 4
- **Year 3** → Semesters 5, 6
- **Year 4** → Semesters 7, 8

## Changes Made

### 1. Backend Validation (`faculty/attendance.php`)

Added year-to-semester mapping array:
```php
$yearSemesterMap = [
    1 => [1, 2],
    2 => [3, 4],
    3 => [5, 6],
    4 => [7, 8],
];
```

Added validation in POST handler:
```php
if (!isset($yearSemesterMap[$yearOfStudy]) || !in_array($semester, $yearSemesterMap[$yearOfStudy], true)) {
    $error = 'Invalid semester for the selected year.';
}
```

This ensures:
- Cannot manually select invalid semester combinations
- Data cannot be tampered via form manipulation
- Server-side validation is enforced

### 2. Form Structure

Reorganized form fields:
1. Date
2. Period
3. Department (readonly)
4. **Year** (id="year_of_study") - triggers change event
5. **Semester** (id="semester") - dynamically populated
6. Batch
7. Section
8. Subject

### 3. JavaScript Implementation

Added client-side dynamic population:
- Listens to `year_of_study` change event
- Updates semester dropdown based on year
- Only shows valid semester options
- Triggers on page load if year is pre-selected
- Restores previously selected semester if available

```javascript
const yearSemesterMap = {
    1: [1, 2],
    2: [3, 4],
    3: [5, 6],
    4: [7, 8]
};

document.getElementById("year_of_study").addEventListener("change", function() {
    // Updates semester dropdown with valid options
});
```

## User Experience

1. Faculty selects a Year from the dropdown
2. JavaScript automatically updates the Semester dropdown with valid options
3. Faculty selects a semester from the filtered list
4. Form is submitted with both year and semester
5. Backend validates that the combination is valid

## Security

- Backend validation ensures no invalid combinations are processed
- JavaScript provides UX enhancement only (not security)
- Server-side checks prevent data tampering
- Invalid submissions result in error message

## Example Flows

### Valid Flow
- Year = 2, Semester = 3 ✓ (allowed)
- Year = 2, Semester = 4 ✓ (allowed)

### Invalid Flow (will be rejected by backend)
- Year = 2, Semester = 1 ✗ (not allowed)
- Year = 2, Semester = 5 ✗ (not allowed)
