# API Setup Guide

## Overview

The Lost and Found system backend uses a minimal PHP layer with SQL-based business logic. All core operations are implemented as stored procedures in MySQL.

## Architecture

```
HTTP Request
    ↓
api/index.php (Router)
    ↓
api/routes/*.php (Route Handlers)
    ↓
Stored Procedures (SQL)
    ↓
Database
```

## Files Created

### API Entry Point
- **api/index.php** - Main router that dispatches requests to appropriate route handlers

### Route Handlers
- **api/routes/auth.php** - Authentication (login, logout, verify)
- **api/routes/admin.php** - Admin endpoints (placeholder)
- **api/routes/student.php** - Student endpoints (placeholder)
- **api/routes/notifications.php** - Notification endpoints (placeholder)
- **api/routes/support.php** - Support endpoints (placeholder)

### Helpers
- **api/helpers/Response.php** - Consistent JSON response formatting

### Configuration
- **api/.htaccess** - URL rewriting for clean API URLs

### Database
- **database/stored_procedures.sql** - All stored procedures for business logic
- **database/load_procedures.php** - Script to load procedures into database

## Setup Instructions

### Step 1: Load Stored Procedures

After running migrations, load the stored procedures:

```bash
php database/load_procedures.php
```

Expected output:
```
✅ Connected to database

✅ Procedure loaded
✅ Procedure loaded
... (multiple procedures)

✅ Stored procedures loaded successfully!
   Total procedures: 20+
```

### Step 2: Test Authentication

Test the login endpoint:

```bash
curl -X POST http://localhost/api/auth/admin/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@ub.edu.ph",
    "password": "Admin"
  }'
```

Expected response:
```json
{
  "ok": true,
  "data": {
    "user_id": 1,
    "email": "admin@ub.edu.ph",
    "name": "Admin",
    "role": "Admin",
    "user_type": "admin",
    "token": "session_id"
  },
  "meta": {
    "timestamp": "2026-02-25T...",
    "version": "1.0"
  }
}
```

### Step 3: Verify Session

Test the verify endpoint:

```bash
curl -X GET http://localhost/api/auth/verify
```

Expected response:
```json
{
  "ok": true,
  "data": {
    "user_id": 1,
    "user_type": "admin",
    "email": "admin@ub.edu.ph"
  },
  "meta": {
    "timestamp": "2026-02-25T...",
    "version": "1.0"
  }
}
```

## Stored Procedures

### Dashboard Procedures

**sp_get_dashboard_stats()**
- Returns: found_count, lost_count, resolved_count
- Usage: Get real-time dashboard statistics

**sp_get_category_distribution()**
- Returns: category, count, percentage
- Usage: Get pie chart data for categories

**sp_get_activity_feed(days_ahead INT)**
- Returns: Items approaching disposal deadline
- Usage: Get items within N days of disposal

**sp_get_student_dashboard_stats(student_email VARCHAR)**
- Returns: lost_reports, available_items, submitted_claims, resolved_claims
- Usage: Get student dashboard statistics

### Found Items Procedures

**sp_create_found_item(...)**
- Creates new found item with auto-generated barcode ID (UB#####)
- Sets disposal deadline to 30 days
- Logs activity
- Returns: item_id

**sp_get_found_items(status, item_type, limit, offset)**
- Returns: List of found items with optional filters
- Supports pagination

**sp_get_unclaimed_items(limit, offset)**
- Returns: Items with status Found or Matched
- Supports pagination

### Lost Report Procedures

**sp_create_lost_report(...)**
- Creates new lost report with auto-generated ticket ID (REF-#####)
- Validates description (min 10 chars)
- Logs activity
- Returns: report_id

**sp_get_student_reports(user_id, limit, offset)**
- Returns: All reports for a student
- Supports pagination

**sp_cancel_lost_report(report_id)**
- Cancels report (only if Lost or Matched status)
- Logs activity

### Matching Procedures

**sp_find_matches_for_item(found_item_id)**
- Finds potential matches for a found item
- Returns: Matching lost reports with confidence scores

**sp_create_match(lost_report_id, found_item_id, confidence_score, criteria)**
- Creates match record
- Creates admin notification
- Logs activity

**sp_approve_match(match_id)**
- Approves match
- Updates both item and report status to Matched
- Logs activity

**sp_reject_match(match_id)**
- Rejects match

### Claim Procedures

**sp_create_claim(student_id, found_item_id, lost_report_id, proof_description)**
- Creates claim with auto-generated reference ID (REF-CLAIM-#####)
- Creates admin notification
- Logs activity
- Returns: reference_id

**sp_resolve_claim(claim_id)**
- Resolves claim (complex transaction)
- Creates archive record
- Updates found item status to Archived
- Creates student notification
- Logs activity

### Archive Procedures

**sp_search_archives(reference_id, claimant_name, date_from, date_to, category, limit, offset)**
- Searches archived records with multiple filters
- Supports pagination

## Response Format

All API responses follow this format:

### Success Response
```json
{
  "ok": true,
  "data": { /* response data */ },
  "meta": {
    "timestamp": "2026-02-25T12:00:00Z",
    "version": "1.0"
  }
}
```

### Error Response
```json
{
  "ok": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": { /* optional details */ }
}
```

### Validation Error Response
```json
{
  "ok": false,
  "error": "Validation failed",
  "code": "VALIDATION_ERROR",
  "details": [
    {
      "field": "email",
      "reason": "Email is required"
    }
  ]
}
```

## HTTP Status Codes

- `200 OK` - Successful GET, PUT
- `201 Created` - Successful POST
- `204 No Content` - Successful DELETE
- `400 Bad Request` - Validation error
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Authorization failed
- `404 Not Found` - Resource not found
- `405 Method Not Allowed` - Invalid HTTP method
- `500 Internal Server Error` - Server error
- `501 Not Implemented` - Endpoint not yet implemented

## Next Steps

1. ✅ Database schema created
2. ✅ API routing set up
3. ✅ Authentication endpoints implemented
4. ✅ Stored procedures created
5. **Next**: Implement admin dashboard endpoints
6. **Next**: Implement found items endpoints
7. **Next**: Implement matching engine endpoints
8. **Next**: Implement student endpoints
9. **Next**: Implement archive endpoints
10. **Next**: Implement support endpoints

## Testing

### Test Admin Login
```bash
curl -X POST http://localhost/api/auth/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@ub.edu.ph", "password": "Admin"}'
```

### Test Student Login
```bash
curl -X POST http://localhost/api/auth/student/login \
  -H "Content-Type: application/json" \
  -d '{"email": "student@example.edu", "password": "password"}'
```

### Test Verify
```bash
curl -X GET http://localhost/api/auth/verify
```

### Test Logout
```bash
curl -X POST http://localhost/api/auth/logout
```

## Troubleshooting

### Stored procedures not loading
- Check MySQL error log
- Verify database user has CREATE PROCEDURE privilege
- Try running procedures manually in phpMyAdmin

### API returns 404
- Check .htaccess is in api/ directory
- Verify mod_rewrite is enabled in Apache
- Check URL format: /api/endpoint

### Authentication fails
- Verify admin/student exists in database
- Check email spelling
- Ensure session is started in PHP

## Database Privileges

Ensure your MySQL user has these privileges:
- SELECT
- INSERT
- UPDATE
- DELETE
- CREATE PROCEDURE
- EXECUTE

Grant with:
```sql
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE PROCEDURE, EXECUTE ON lostandfound_db.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```
