# Database Migration Guide

## Overview

This guide explains how to set up the Lost and Found system database schema. The system uses 10 migration files that enhance the existing schema and add new tables.

## Migration Files

### 001_enhance_items_table.sql
**Purpose**: Enhance the existing `items` table with new columns for the matching and disposal system.

**Changes**:
- Add `disposal_deadline` column (date) - tracks when unclaimed items can be disposed
- Add `matched_barcode_id` column (varchar) - tracks matched item relationships
- Update `status` enum to include: Found, Lost, Matched, Claimed, Resolved, Archived, Cancelled
- Add indexes on `disposal_deadline` and `matched_barcode_id`

**What you'll see**: Items table now supports the new status workflow and disposal tracking.

### 002_enhance_admins_table.sql
**Purpose**: Add role-based access control support to the admins table.

**Changes**:
- Add `role` column (varchar) - for future RBAC implementation

**What you'll see**: Admins table now has role column for access control.

### 003_create_students_table.sql
**Purpose**: Create a new table to store student user accounts.

**Changes**:
- Create `students` table with: id, email, password_hash, name, student_id, phone
- Add unique indexes on email and student_id

**What you'll see**: New `students` table ready to store student accounts.

### 004_create_matches_table.sql
**Purpose**: Create a table to track matches between lost reports and found items.

**Changes**:
- Create `matches` table with: id, lost_report_id, found_item_id, confidence_score, matching_criteria, status
- Add unique constraint on (lost_report_id, found_item_id) to prevent duplicate matches
- Add indexes on status, lost_report_id, found_item_id

**What you'll see**: New `matches` table ready to store automated and manual matches.

### 005_create_claims_table.sql
**Purpose**: Create a table to track student claims on found items.

**Changes**:
- Create `claims` table with: id, reference_id, student_id, found_item_id, lost_report_id, proof_photo, proof_description, status, claim_date, resolution_date
- Add unique constraint on reference_id
- Add foreign key constraint on student_id
- Add indexes on student_id, found_item_id, status

**What you'll see**: New `claims` table ready to store student claims with proof.

### 006_create_archives_table.sql
**Purpose**: Create a table to store historical records of resolved claims.

**Changes**:
- Create `archives` table with: id, reference_id, found_item_id, student_id, claimant_name, claimant_email, claimant_phone, item_details (JSON), proof_photo, claim_date, resolution_date
- Add unique constraint on reference_id
- Add indexes on student_id, resolution_date, claimant_name

**What you'll see**: New `archives` table ready to store historical records for audits.

### 007_create_notifications_table.sql
**Purpose**: Create a table to store notifications for admins and students.

**Changes**:
- Create `notifications` table with: id, recipient_id, recipient_type, type, title, message, related_id, is_read
- Add indexes on (recipient_id, recipient_type), is_read, created_at

**What you'll see**: New `notifications` table ready to store system notifications.

### 008_create_support_contacts_table.sql
**Purpose**: Create a table to store support staff contact information.

**Changes**:
- Create `support_contacts` table with: id, name, email, phone, office_location, department, role, office_hours

**What you'll see**: New `support_contacts` table ready to store contact directory.

### 009_create_process_guides_table.sql
**Purpose**: Create a table to store step-by-step process guides for students.

**Changes**:
- Create `process_guides` table with: id, title, section, step_number, instruction, estimated_time_minutes, faq (JSON), troubleshooting (JSON)
- Add index on section

**What you'll see**: New `process_guides` table ready to store help documentation.

### 010_enhance_activity_log_table.sql
**Purpose**: Enhance the existing activity_log table with better tracking capabilities.

**Changes**:
- Add `actor_id` column (int) - tracks who performed the action
- Add `actor_type` column (enum) - tracks if actor is admin, student, or system
- Add `details` column (JSON) - stores additional operation details
- Add index on action

**What you'll see**: Activity log table now tracks who performed each action.

## How to Run Migrations

### Option 1: Using PHP Script (Recommended)

```bash
php database/run_migrations.php
```

This script will:
1. Connect to your database using credentials from `config/database.php`
2. Run all 10 migrations in order
3. Display success/warning messages for each migration
4. Show final status

**Expected Output**:
```
Connected to database: lostandfound_db
Running migrations...

✅ 001_enhance_items_table.sql
✅ 002_enhance_admins_table.sql
✅ 003_create_students_table.sql
✅ 004_create_matches_table.sql
✅ 005_create_claims_table.sql
✅ 006_create_archives_table.sql
✅ 007_create_notifications_table.sql
✅ 008_create_support_contacts_table.sql
✅ 009_create_process_guides_table.sql
✅ 010_enhance_activity_log_table.sql

✅ All migrations completed!

Database schema is now ready for the Lost and Found system.
```

### Option 2: Using phpMyAdmin

1. Open phpMyAdmin
2. Select your `lostandfound_db` database
3. Click "SQL" tab
4. Copy and paste the contents of each migration file in order
5. Click "Go" to execute

### Option 3: Using MySQL Command Line

```bash
mysql -u root -p lostandfound_db < database/001_enhance_items_table.sql
mysql -u root -p lostandfound_db < database/002_enhance_admins_table.sql
mysql -u root -p lostandfound_db < database/003_create_students_table.sql
mysql -u root -p lostandfound_db < database/004_create_matches_table.sql
mysql -u root -p lostandfound_db < database/005_create_claims_table.sql
mysql -u root -p lostandfound_db < database/006_create_archives_table.sql
mysql -u root -p lostandfound_db < database/007_create_notifications_table.sql
mysql -u root -p lostandfound_db < database/008_create_support_contacts_table.sql
mysql -u root -p lostandfound_db < database/009_create_process_guides_table.sql
mysql -u root -p lostandfound_db < database/010_enhance_activity_log_table.sql
```

## Verifying the Schema

After running migrations, verify the schema was created correctly:

### Check Tables Exist

```sql
SHOW TABLES;
```

You should see:
- items (enhanced)
- admins (enhanced)
- students (new)
- matches (new)
- claims (new)
- archives (new)
- notifications (new)
- support_contacts (new)
- process_guides (new)
- activity_log (enhanced)

### Check Items Table Structure

```sql
DESCRIBE items;
```

You should see new columns:
- disposal_deadline
- matched_barcode_id

And updated status enum with new values.

### Check Students Table Structure

```sql
DESCRIBE students;
```

You should see all columns created.

### Check Indexes

```sql
SHOW INDEX FROM items;
SHOW INDEX FROM matches;
SHOW INDEX FROM claims;
SHOW INDEX FROM archives;
SHOW INDEX FROM notifications;
```

All indexes should be present.

## Rollback (If Needed)

If you need to rollback migrations, you can manually drop tables or columns:

```sql
-- Drop new tables
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `process_guides`;
DROP TABLE IF EXISTS `support_contacts`;
DROP TABLE IF EXISTS `archives`;
DROP TABLE IF EXISTS `claims`;
DROP TABLE IF EXISTS `matches`;
DROP TABLE IF EXISTS `students`;

-- Remove added columns from existing tables
ALTER TABLE `items` DROP COLUMN `disposal_deadline`;
ALTER TABLE `items` DROP COLUMN `matched_barcode_id`;
ALTER TABLE `admins` DROP COLUMN `role`;
ALTER TABLE `activity_log` DROP COLUMN `actor_id`;
ALTER TABLE `activity_log` DROP COLUMN `actor_type`;
ALTER TABLE `activity_log` DROP COLUMN `details`;
```

## Database Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    CORE ENTITIES                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  items (Enhanced)                                           │
│  ├─ id (PK) - Barcode or Reference ID                      │
│  ├─ user_id - Who reported                                 │
│  ├─ item_type - Category                                   │
│  ├─ status - Found/Lost/Matched/Claimed/Resolved/Archived  │
│  ├─ disposal_deadline - When to dispose                    │
│  ├─ matched_barcode_id - Link to matched item              │
│  └─ image_data - Photo                                     │
│                                                              │
│  admins (Enhanced)                                          │
│  ├─ id (PK)                                                │
│  ├─ email (UNIQUE)                                         │
│  ├─ password_hash                                          │
│  ├─ name                                                   │
│  └─ role - For RBAC                                        │
│                                                              │
│  students (New)                                            │
│  ├─ id (PK)                                                │
│  ├─ email (UNIQUE)                                         │
│  ├─ password_hash                                          │
│  ├─ name                                                   │
│  ├─ student_id (UNIQUE)                                    │
│  └─ phone                                                  │
│                                                              │
├─────────────────────────────────────────────────────────────┤
│                    RELATIONSHIPS                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  matches (New)                                             │
│  ├─ id (PK)                                                │
│  ├─ lost_report_id (FK → items)                            │
│  ├─ found_item_id (FK → items)                             │
│  ├─ confidence_score - 0-100                               │
│  ├─ matching_criteria - JSON                               │
│  └─ status - Pending_Review/Approved/Rejected              │
│                                                              │
│  claims (New)                                              │
│  ├─ id (PK)                                                │
│  ├─ reference_id (UNIQUE)                                  │
│  ├─ student_id (FK → students)                             │
│  ├─ found_item_id (FK → items)                             │
│  ├─ lost_report_id (FK → items)                            │
│  ├─ proof_photo                                            │
│  ├─ proof_description                                      │
│  └─ status - Pending/Approved/Rejected/Resolved            │
│                                                              │
├─────────────────────────────────────────────────────────────┤
│                    HISTORY & SUPPORT                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  archives (New)                                            │
│  ├─ id (PK)                                                │
│  ├─ reference_id (UNIQUE)                                  │
│  ├─ found_item_id                                          │
│  ├─ student_id (FK → students)                             │
│  ├─ claimant_name                                          │
│  ├─ item_details - JSON snapshot                           │
│  ├─ proof_photo                                            │
│  └─ claim_date, resolution_date                            │
│                                                              │
│  notifications (New)                                       │
│  ├─ id (PK)                                                │
│  ├─ recipient_id                                           │
│  ├─ recipient_type - admin/student                         │
│  ├─ type - match_found/claim_approved/etc                  │
│  ├─ title, message                                         │
│  └─ is_read                                                │
│                                                              │
│  support_contacts (New)                                    │
│  ├─ id (PK)                                                │
│  ├─ name, email, phone                                     │
│  ├─ office_location                                        │
│  ├─ department, role                                       │
│  └─ office_hours                                           │
│                                                              │
│  process_guides (New)                                      │
│  ├─ id (PK)                                                │
│  ├─ section - report_lost/search_found/claim_item          │
│  ├─ step_number                                            │
│  ├─ instruction                                            │
│  ├─ estimated_time_minutes                                 │
│  ├─ faq - JSON                                             │
│  └─ troubleshooting - JSON                                 │
│                                                              │
│  activity_log (Enhanced)                                   │
│  ├─ id (PK)                                                │
│  ├─ item_id                                                │
│  ├─ action - encoded/matched/claimed/archived              │
│  ├─ actor_id                                               │
│  ├─ actor_type - admin/student/system                      │
│  └─ details - JSON                                         │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## Next Steps

After running migrations:

1. Verify all tables and columns exist using the verification queries above
2. Proceed to Task 2: Create core model classes and database abstraction layer
3. Test database connectivity from PHP using the Database utility class

## Troubleshooting

### Migration fails with "Table already exists"
This is normal if you're re-running migrations. The SQL uses `CREATE TABLE IF NOT EXISTS` to handle this.

### Migration fails with "Unknown column"
This might happen if you're running migrations out of order. Ensure you run them in numerical order (001-010).

### Foreign key constraint errors
Ensure the `students` table is created before `claims` table. The migrations are ordered correctly to handle this.

### Permission denied errors
Ensure your database user has permissions to:
- CREATE TABLE
- ALTER TABLE
- CREATE INDEX
- MODIFY columns

## Database Configuration

Ensure your `config/database.php` has correct credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'lostandfound_db');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```
