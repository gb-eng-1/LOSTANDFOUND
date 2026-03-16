# Lost and Found System - Backend Design Document

## Overview

The Lost and Found System backend is a RESTful API service built with PHP and MySQL that manages the complete lifecycle of lost and found items for an educational institution. The system serves two primary user roles:

- **Admins**: Manage found items, generate reports, facilitate matching between lost and found items, and archive resolved claims
- **Students**: Report lost items, search for found items, and claim matched items

The backend provides comprehensive data management, automated matching algorithms, photo compression, notification capabilities, and historical archiving. The design leverages the existing PHP/MySQL infrastructure while introducing modern API patterns, service-oriented architecture, and property-based testing for correctness verification.

### Key Design Principles

1. **Separation of Concerns**: Distinct services for matching, photo handling, notifications, and archiving
2. **RESTful API Design**: Standard HTTP methods and status codes for all endpoints
3. **Data Integrity**: Transactional operations for critical workflows (matching, claiming, archiving)
4. **Performance**: Indexed queries, pagination, and efficient filtering
5. **Extensibility**: Service-based architecture allows independent scaling and modification

---

## Architecture

### High-Level System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                     Frontend Applications                        │
│              (ADMIN Dashboard, STUDENT Dashboard)                │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    API Gateway / Router                          │
│  (Route requests to appropriate endpoints and services)          │
└────────────────────────┬────────────────────────────────────────┘
                         │
        ┌────────────────┼────────────────┬──────────────┐
        ▼                ▼                ▼              ▼
   ┌─────────┐    ┌──────────────┐  ┌──────────┐  ┌──────────┐
   │Dashboard│    │Found Items   │  │Matching  │  │Claim     │
   │API      │    │API           │  │Engine    │  │API       │
   └────┬────┘    └──────┬───────┘  └────┬─────┘  └────┬─────┘
        │                │               │             │
        │         ┌──────┴───────┐       │             │
        │         ▼              ▼       │             │
        │    ┌──────────────┐    │       │             │
        │    │Photo Service │    │       │             │
        │    └──────────────┘    │       │             │
        │                        │       │             │
        └────────────┬───────────┴───────┴─────────────┘
                     ▼
        ┌────────────────────────────────┐
        │  Notification Service          │
        │  Archive Service               │
        │  Reports API                   │
        │  Support API                   │
        └────────────┬───────────────────┘
                     ▼
        ┌────────────────────────────────┐
        │    MySQL Database              │
        │  (items, admins, activity_log, │
        │   matches, claims, archives)   │
        └────────────────────────────────┘
```

### Service Architecture

The backend is organized into the following services:

1. **Dashboard Service**: Aggregates statistics and activity feeds
2. **Found Items Service**: Manages found item lifecycle (create, update, filter, search)
3. **Lost Reports Service**: Manages lost report lifecycle (create, cancel, view)
4. **Matching Engine**: Automated matching algorithm and manual match management
5. **Photo Service**: Image upload, compression, and storage
6. **Claim Service**: Manages claim lifecycle and release confirmations
7. **Archive Service**: Historical record management for resolved claims
8. **Notification Service**: Sends notifications for matches and system events
9. **Support Service**: Contact directory and process guides

---

## Database Schema

### Core Tables

#### 1. `items` (Enhanced from existing)

Stores both found items and lost reports with unified structure.

```sql
CREATE TABLE `items` (
  `id` varchar(50) NOT NULL COMMENT 'Barcode ID (UB#####) or Reference ID (REF-#####)',
  `user_id` varchar(100) DEFAULT NULL COMMENT 'User who reported (email)',
  `item_type` varchar(100) DEFAULT NULL COMMENT 'Category: Electronics, Documents, Apparel, etc.',
  `color` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `found_at` varchar(200) DEFAULT NULL COMMENT 'Location where item was found',
  `found_by` varchar(200) DEFAULT NULL COMMENT 'Person who found (email)',
  `date_encoded` date DEFAULT NULL COMMENT 'Date found/encoded',
  `date_lost` date DEFAULT NULL COMMENT 'Date lost (if reported as lost)',
  `item_description` text,
  `storage_location` varchar(200) DEFAULT NULL COMMENT 'Shelf identifier',
  `image_data` longtext COMMENT 'Photo path or base64 data URL',
  `status` enum('Found','Lost','Matched','Claimed','Resolved','Archived','Cancelled') NOT NULL DEFAULT 'Found',
  `disposal_deadline` date DEFAULT NULL COMMENT 'Date after which unclaimed item may be disposed',
  `matched_barcode_id` varchar(50) DEFAULT NULL COMMENT 'Reference to matched found item',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_item_type` (`item_type`),
  KEY `idx_date_encoded` (`date_encoded`),
  KEY `idx_disposal_deadline` (`disposal_deadline`),
  KEY `idx_matched_barcode_id` (`matched_barcode_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. `admins` (Existing, enhanced)

```sql
CREATE TABLE `admins` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'Admin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. `students` (New)

```sql
CREATE TABLE `students` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 4. `matches` (New)

Tracks automated and manual matches between lost reports and found items.

```sql
CREATE TABLE `matches` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `lost_report_id` varchar(50) NOT NULL COMMENT 'Reference ID of lost report',
  `found_item_id` varchar(50) NOT NULL COMMENT 'Barcode ID of found item',
  `confidence_score` decimal(5,2) DEFAULT 0 COMMENT 'Match confidence 0-100',
  `matching_criteria` json DEFAULT NULL COMMENT 'Which fields matched',
  `status` enum('Pending_Review','Approved','Rejected') NOT NULL DEFAULT 'Pending_Review',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_match` (`lost_report_id`, `found_item_id`),
  KEY `idx_status` (`status`),
  KEY `idx_lost_report_id` (`lost_report_id`),
  KEY `idx_found_item_id` (`found_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 5. `claims` (New)

Tracks student claims on found items.

```sql
CREATE TABLE `claims` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `reference_id` varchar(50) NOT NULL UNIQUE COMMENT 'Unique claim identifier',
  `student_id` int(11) unsigned NOT NULL,
  `found_item_id` varchar(50) NOT NULL,
  `lost_report_id` varchar(50) DEFAULT NULL,
  `proof_photo` longtext COMMENT 'Photo path or base64 data URL',
  `proof_description` text COMMENT 'Student description of item',
  `status` enum('Pending','Approved','Rejected','Resolved') NOT NULL DEFAULT 'Pending',
  `claim_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolution_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_id` (`reference_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_found_item_id` (`found_item_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 6. `archives` (New)

Historical records of resolved claims.

```sql
CREATE TABLE `archives` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `reference_id` varchar(50) NOT NULL UNIQUE,
  `found_item_id` varchar(50) NOT NULL,
  `student_id` int(11) unsigned NOT NULL,
  `claimant_name` varchar(100) NOT NULL,
  `claimant_email` varchar(255) NOT NULL,
  `claimant_phone` varchar(20) DEFAULT NULL,
  `item_details` json NOT NULL COMMENT 'Snapshot of found item',
  `proof_photo` longtext,
  `claim_date` datetime NOT NULL,
  `resolution_date` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_id` (`reference_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_resolution_date` (`resolution_date`),
  KEY `idx_claimant_name` (`claimant_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. `notifications` (New)

Notification queue for admins and students.

```sql
CREATE TABLE `notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_id` int(11) unsigned NOT NULL COMMENT 'Admin or Student ID',
  `recipient_type` enum('admin','student') NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'match_found, claim_approved, etc.',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` varchar(50) DEFAULT NULL COMMENT 'Match ID, Claim ID, etc.',
  `is_read` boolean DEFAULT false,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient_id`, `recipient_type`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 8. `activity_log` (Existing, enhanced)

```sql
CREATE TABLE `activity_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` varchar(50) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'encoded, matched, claimed, archived, etc.',
  `actor_id` int(11) unsigned DEFAULT NULL,
  `actor_type` enum('admin','student','system') DEFAULT 'system',
  `details` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 9. `support_contacts` (New)

```sql
CREATE TABLE `support_contacts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `office_location` varchar(200) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `office_hours` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 10. `process_guides` (New)

```sql
CREATE TABLE `process_guides` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `section` varchar(100) NOT NULL COMMENT 'report_lost, search_found, claim_item',
  `step_number` int(11) NOT NULL,
  `instruction` text NOT NULL,
  `estimated_time_minutes` int(11) DEFAULT NULL,
  `faq` json DEFAULT NULL COMMENT 'Array of {question, answer}',
  `troubleshooting` json DEFAULT NULL COMMENT 'Array of {issue, solution}',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_section` (`section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## API Endpoints

### Authentication Endpoints

```
POST   /api/auth/admin/login          - Admin login
POST   /api/auth/student/login        - Student login
POST   /api/auth/logout               - Logout (both roles)
GET    /api/auth/verify               - Verify current session
```

### Admin Dashboard Endpoints

```
GET    /api/admin/dashboard/stats     - Real-time statistics (found, lost, resolved counts)
GET    /api/admin/dashboard/categories - Category distribution with percentages
GET    /api/admin/dashboard/activity  - Activity feed with disposal deadlines
```

### Found Items Management Endpoints

```
POST   /api/admin/items               - Create found item
GET    /api/admin/items               - List found items (with filters)
GET    /api/admin/items/:id           - Get found item details
PUT    /api/admin/items/:id           - Update found item
DELETE /api/admin/items/:id           - Delete found item
GET    /api/student/items             - Search found items (student view)
GET    /api/student/items/unclaimed   - List unclaimed items
```

### Photo Management Endpoints

```
POST   /api/items/:id/photo           - Upload photo for item
GET    /api/items/:id/photo           - Get photo for item
DELETE /api/items/:id/photo           - Delete photo
```

### Lost Reports Endpoints

```
POST   /api/student/reports           - Create lost report
GET    /api/student/reports           - Get student's lost reports
GET    /api/student/reports/:id       - Get lost report details
PUT    /api/student/reports/:id       - Update lost report
DELETE /api/student/reports/:id       - Cancel lost report
GET    /api/admin/reports             - Search all lost reports (admin)
GET    /api/admin/reports/matched     - Get matched reports
```

### Matching Engine Endpoints

```
GET    /api/admin/matches             - List all matches
GET    /api/admin/matches/:id         - Get match details with photos
POST   /api/admin/matches/:id/approve - Approve match
POST   /api/admin/matches/:id/reject  - Reject match
GET    /api/admin/matches/report/:id  - Get matches for a report
```

### Claims Endpoints

```
POST   /api/student/claims            - Submit claim for found item
GET    /api/student/claims            - Get student's claims
GET    /api/student/claims/:id        - Get claim details
GET    /api/student/claims/:id/confirmation - Get release confirmation
GET    /api/admin/claims              - List all claims
PUT    /api/admin/claims/:id/status   - Update claim status
```

### Archive Endpoints

```
GET    /api/admin/archives            - Search archived items
GET    /api/admin/archives/:id        - Get archive record details
```

### Notifications Endpoints

```
GET    /api/notifications             - Get user's notifications
PUT    /api/notifications/:id/read    - Mark notification as read
DELETE /api/notifications/:id         - Delete notification
```

### Support Endpoints

```
GET    /api/support/contacts          - Get contact directory
GET    /api/support/guides            - Get process guides
GET    /api/support/guides/:section   - Get guide for specific section
```

---

## Components and Interfaces

### 1. Dashboard Service

**Responsibilities**: Aggregate statistics, calculate metrics, provide activity feeds

**Key Methods**:
- `getStats()` - Returns counts of found, lost, resolved items
- `getCategoryDistribution()` - Returns category breakdown with percentages
- `getActivityFeed()` - Returns items approaching disposal deadline
- `getStudentDashboard()` - Returns student-specific statistics

**Data Flow**:
```
Request → Dashboard Service → Query items table → Aggregate → Response
```

### 2. Found Items Service

**Responsibilities**: CRUD operations, filtering, searching, status management

**Key Methods**:
- `createItem(data)` - Create new found item with validation
- `getItems(filters, pagination)` - List items with category/status filters
- `updateItem(id, data)` - Update item details
- `searchItems(query, filters)` - Full-text search for students
- `getUnclaimedItems()` - Get items available for claiming

**Validation Rules**:
- Brand and color: non-empty strings
- Barcode: alphanumeric, max 50 characters
- Storage shelf: valid identifier format
- Disposal deadline: auto-set to 30 days from creation

### 3. Lost Reports Service

**Responsibilities**: Report creation, cancellation, status tracking

**Key Methods**:
- `createReport(data)` - Create lost report with validation
- `getReports(filters)` - List reports with search/filter
- `cancelReport(id)` - Cancel report (only if Lost or Matched status)
- `getMatchedReports()` - Get reports with approved matches

**Validation Rules**:
- Description: non-empty, minimum 10 characters
- Category: must be valid category
- Status transitions: Lost → Matched → Claimed → Resolved

### 4. Matching Engine

**Responsibilities**: Automated matching algorithm, match management

**Key Methods**:
- `findMatches(item)` - Find potential matches for new item
- `calculateConfidence(report, item)` - Calculate match confidence score
- `createMatch(reportId, itemId, confidence)` - Create match record
- `approveMatch(matchId)` - Approve match (admin)
- `rejectMatch(matchId)` - Reject match (admin)

**Matching Algorithm**:
```
For each new found item:
  1. Find all lost reports with matching category
  2. For each report, check:
     - Category match (required)
     - Location proximity (nearby locations)
     - Color match (if both specified)
     - Brand match (if both specified)
  3. Calculate confidence score (0-100)
  4. Create match record with status "Pending_Review"
  5. Notify admin
```

**Confidence Scoring**:
- Category match: +40 points
- Location match: +30 points
- Color match: +15 points
- Brand match: +15 points
- Total: 0-100

### 5. Photo Service

**Responsibilities**: Image upload, compression, storage, retrieval

**Key Methods**:
- `uploadPhoto(file, itemId)` - Upload and compress photo
- `compressImage(file)` - Compress to 1920x1080, 80% quality
- `validateFile(file)` - Validate format and size
- `getPhoto(itemId)` - Retrieve photo
- `deletePhoto(itemId)` - Delete photo

**Specifications**:
- Supported formats: JPEG, PNG, WebP
- Max file size: 10MB
- Output dimensions: 1920x1080 pixels
- Output quality: 80%
- Storage: `/uploads/items/` directory

### 6. Claim Service

**Responsibilities**: Claim lifecycle management, release confirmations

**Key Methods**:
- `submitClaim(studentId, itemId, proof)` - Submit claim with proof
- `getClaims(studentId)` - Get student's claims
- `updateClaimStatus(claimId, status)` - Update claim status
- `generateReleaseConfirmation(claimId)` - Generate confirmation
- `resolveClaim(claimId)` - Mark claim as resolved

**Claim Workflow**:
```
Student submits claim → Pending → Admin reviews → Approved/Rejected
If Approved → Resolved → Archive created
```

### 7. Archive Service

**Responsibilities**: Historical record management, archival operations

**Key Methods**:
- `archiveClaim(claimId)` - Create archive record from resolved claim
- `searchArchives(criteria)` - Search archived records
- `getArchiveRecord(referenceId)` - Get specific archive

**Archive Data**:
- Complete item details snapshot
- Claimant information
- Proof photo
- Claim and resolution dates
- Reference ID for tracking

### 8. Notification Service

**Responsibilities**: Notification creation, delivery, management

**Key Methods**:
- `sendNotification(recipientId, type, message)` - Send notification
- `getNotifications(userId, type)` - Get user's notifications
- `markAsRead(notificationId)` - Mark notification as read
- `deleteNotification(notificationId)` - Delete notification

**Notification Types**:
- `match_found` - New match found for report
- `claim_approved` - Claim approved by admin
- `claim_rejected` - Claim rejected by admin
- `item_disposal_warning` - Item approaching disposal deadline

### 9. Support Service

**Responsibilities**: Contact directory, process guides

**Key Methods**:
- `getContacts()` - Get all support contacts
- `getGuides(section)` - Get process guides for section
- `getGuideDetails(section, step)` - Get detailed guide

---

## Data Models

### Found Item Model

```php
class FoundItem {
    public string $id;              // Barcode ID (UB#####)
    public string $brand;
    public string $color;
    public string $barcode;
    public string $storageShelf;
    public string $category;
    public string $description;
    public string $foundAt;         // Location
    public string $foundBy;         // Email
    public DateTime $dateEncoded;
    public string $photoPath;
    public DateTime $disposalDeadline;
    public string $status;          // Found, Matched, Claimed, Resolved, Archived
    public DateTime $createdAt;
    public DateTime $updatedAt;
}
```

### Lost Report Model

```php
class LostReport {
    public string $id;              // Reference ID (REF-#####)
    public int $studentId;
    public string $description;
    public string $category;
    public string $location;
    public string $photoPath;
    public DateTime $dateLost;
    public string $status;          // Lost, Matched, Claimed, Resolved, Cancelled
    public DateTime $createdAt;
    public DateTime $updatedAt;
}
```

### Match Model

```php
class Match {
    public int $id;
    public string $lostReportId;
    public string $foundItemId;
    public float $confidenceScore;  // 0-100
    public array $matchingCriteria; // Which fields matched
    public string $status;          // Pending_Review, Approved, Rejected
    public DateTime $createdAt;
    public DateTime $updatedAt;
}
```

### Claim Model

```php
class Claim {
    public int $id;
    public string $referenceId;     // Unique claim ID
    public int $studentId;
    public string $foundItemId;
    public string $lostReportId;
    public string $proofPhoto;
    public string $proofDescription;
    public string $status;          // Pending, Approved, Rejected, Resolved
    public DateTime $claimDate;
    public DateTime $resolutionDate;
    public DateTime $createdAt;
    public DateTime $updatedAt;
}
```

### Archive Model

```php
class Archive {
    public int $id;
    public string $referenceId;
    public string $foundItemId;
    public int $studentId;
    public string $claimantName;
    public string $claimantEmail;
    public string $claimantPhone;
    public array $itemDetails;      // Snapshot of found item
    public string $proofPhoto;
    public DateTime $claimDate;
    public DateTime $resolutionDate;
    public DateTime $createdAt;
}
```

---

## Integration Points

### Existing PHP Files Integration

The following existing PHP files will be refactored into the new API structure:

1. **login.php** → `/api/auth/admin/login` and `/api/auth/student/login`
   - Existing authentication logic reused
   - Session management enhanced with token-based auth

2. **save_encoded_item.php** → `POST /api/admin/items`
   - Barcode generation logic preserved
   - Enhanced with validation and error handling

3. **get_matching_found_items.php** → `GET /api/admin/matches/report/:id`
   - Matching logic extracted into Matching Engine service
   - Enhanced with confidence scoring

4. **get_encoded_items.php** → `GET /api/admin/items`
   - Filtering and pagination added
   - Status filtering enhanced

5. **save_lost_report.php** → `POST /api/student/reports`
   - Report creation logic preserved
   - Validation enhanced

6. **claim_item.php** → `POST /api/student/claims`
   - Claim submission logic preserved
   - Proof photo handling integrated with Photo Service

### Frontend Integration

- **ADMIN folder**: Updated to call new API endpoints instead of direct PHP files
- **STUDENT folder**: Updated to call new API endpoints
- **Authentication**: Session tokens passed in Authorization header

### Database Integration

- Existing `items` table enhanced with new columns
- New tables created for matches, claims, archives, notifications
- Migrations provided for schema updates
- Backward compatibility maintained with existing data

---

## Implementation Approach

### Technology Stack

- **Language**: PHP 7.4+
- **Database**: MySQL 5.7+
- **API Pattern**: RESTful with JSON
- **Authentication**: Session-based + JWT tokens
- **Image Processing**: GD Library or ImageMagick
- **Testing**: PHPUnit for unit tests, Hypothesis (via Python bridge) for property-based tests

### Project Structure

```
/api
  /routes
    - admin.php
    - student.php
    - auth.php
    - support.php
  /services
    - DashboardService.php
    - FoundItemService.php
    - LostReportService.php
    - MatchingEngine.php
    - PhotoService.php
    - ClaimService.php
    - ArchiveService.php
    - NotificationService.php
    - SupportService.php
  /models
    - FoundItem.php
    - LostReport.php
    - Match.php
    - Claim.php
    - Archive.php
  /middleware
    - AuthMiddleware.php
    - ValidationMiddleware.php
    - ErrorHandler.php
  /utils
    - Database.php
    - Logger.php
    - Validator.php
    - ImageProcessor.php
  /tests
    - unit/
    - property/
/config
  - database.php
  - categories.php
  - settings.php
/uploads
  - items/
  - barcodes/
```

### Key Implementation Patterns

1. **Service Layer Pattern**: Business logic separated from HTTP handling
2. **Repository Pattern**: Data access abstraction
3. **Dependency Injection**: Services receive dependencies via constructor
4. **Middleware Pipeline**: Request/response processing
5. **Error Handling**: Consistent error responses with proper HTTP status codes
6. **Logging**: Activity logging for audit trail

### Libraries and Dependencies

- **PDO**: Database abstraction
- **GD Library**: Image compression
- **PHPUnit**: Unit testing
- **Composer**: Dependency management

### API Response Format

All endpoints return JSON with consistent structure:

```json
{
  "ok": true,
  "data": { /* response data */ },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "version": "1.0"
  }
}
```

Error responses:

```json
{
  "ok": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": { /* additional details */ }
}
```

### HTTP Status Codes

- `200 OK` - Successful GET, PUT
- `201 Created` - Successful POST
- `204 No Content` - Successful DELETE
- `400 Bad Request` - Validation error
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Authorization failed
- `404 Not Found` - Resource not found
- `409 Conflict` - Duplicate resource
- `500 Internal Server Error` - Server error

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

Before writing the correctness properties, I need to analyze the acceptance criteria for testability. Let me use the prework tool to formalize this analysis.

### Property Reflection and Consolidation

After analyzing all acceptance criteria, I've identified several redundant or overlapping properties that can be consolidated:

**Consolidation Examples**:
- Dashboard count properties (1.1, 1.2, 1.3) can be combined into one property about dashboard statistics
- Filter properties (6.1, 6.2, 6.3) can be combined into one comprehensive filtering property
- Search properties (7.2, 7.3, 7.4) can be combined into one search property
- Pagination properties across multiple endpoints can be consolidated
- Status update properties (10.5, 10.6, 17.7) can be combined into one status update property
- Archive inclusion properties (12.1-12.5) can be combined into one comprehensive archive property

This consolidation reduces redundancy while maintaining comprehensive coverage.

### Correctness Properties

#### Property 1: Dashboard Statistics Accuracy

*For any* set of items with various statuses, the dashboard statistics endpoint should return counts that exactly match the number of items with each status in the database.

**Validates: Requirements 1.1, 1.2, 1.3**

#### Property 2: Category Distribution Completeness and Correctness

*For any* set of found items with various categories, the category distribution endpoint should return all categories present in the database, with counts that sum to the total number of items, and percentages that sum to 100%.

**Validates: Requirements 2.1, 2.2, 2.3**

#### Property 3: Activity Feed Ordering and Filtering

*For any* set of found items with disposal deadlines, the activity feed should return only items with deadlines within the next 7 days, ordered by deadline in ascending order, with all required fields present.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4**

#### Property 4: Found Item Creation Validation and Persistence

*For any* valid found item data (non-empty brand/color, valid barcode format, valid shelf), creating the item should result in a unique ID assignment, initial status of "Found", disposal deadline exactly 30 days from creation, and all data persisted to the database.

**Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8**

#### Property 5: Photo Upload Compression and Storage

*For any* valid image file (JPEG, PNG, WebP, under 10MB), uploading should result in a compressed image with dimensions not exceeding 1920x1080, quality of 80%, unique filename, and association with the item record.

**Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 5.7**

#### Property 6: Item Filtering by Category and Status

*For any* set of items with various categories and statuses, filtering by category should return only items matching that category, filtering by status should return only items matching that status, and filtering by both should return items matching both conditions (conjunction).

**Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5, 6.6**

#### Property 7: Lost Report Search and Filtering

*For any* set of lost reports, searching by ticket ID should return matching reports, searching by student name should return reports from that student, and searching by description should perform case-insensitive matching. All searches should support pagination and sorting.

**Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 8.1, 8.2, 8.3**

#### Property 8: Automated Matching Algorithm

*For any* new found item or lost report, the matching engine should search for potential matches with the same category, create match records with confidence scores between 0-100, and set initial status to "Pending_Review". Notifications should be created for admins.

**Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 11.1, 11.2, 11.3**

#### Property 9: Match Comparison and Status Updates

*For any* match, the API should return both found item and lost report photos, all item details, confidence score, and matching criteria. Approving a match should update status to "Approved", rejecting should update to "Rejected".

**Validates: Requirements 10.1, 10.3, 10.4, 10.5, 10.6**

#### Property 10: Notification Retrieval and Ordering

*For any* admin user, retrieving notifications should return all unread notifications ordered by creation date (newest first), with all required fields present.

**Validates: Requirements 11.4**

#### Property 11: Archive Creation and Completeness

*For any* resolved claim, archiving should create a record containing all found item details, claimant information, proof photo, claim and resolution dates, reference ID, and update the found item status to "Archived".

**Validates: Requirements 12.1, 12.2, 12.3, 12.4, 12.5, 12.6**

#### Property 12: Archive Search and Filtering

*For any* set of archived records, searching by reference ID should return the matching record, searching by claimant name should return records for that student, searching by date range should return records within those dates, and searching by category should return matching records. All searches should support pagination.

**Validates: Requirements 13.1, 13.2, 13.3, 13.4, 13.5**

#### Property 13: Student Dashboard Statistics

*For any* student user, the dashboard should return accurate counts of their lost reports, available found items, submitted claims, and resolved claims.

**Validates: Requirements 14.1, 14.2, 14.3, 14.4**

#### Property 14: Lost Report Creation and Validation

*For any* lost report submission with valid data (description at least 10 characters, valid category), the report should be created with a unique ticket ID, initial status "Lost", and all data persisted.

**Validates: Requirements 15.1, 15.2, 15.3, 15.4, 15.5, 17.1, 17.2, 17.3, 17.4**

#### Property 15: Found Item Search for Students

*For any* student search for found items, the API should return only items with status "Found" or "Matched", support category filtering, keyword search, location filtering, and pagination.

**Validates: Requirements 15.6, 15.7, 15.8, 16.1, 16.2, 16.3, 16.4, 16.5, 16.6**

#### Property 16: Student Report Management

*For any* student's lost reports, retrieving them should return all reports submitted by that student with all required fields. Cancelling a report should only succeed if status is "Lost" or "Matched", and should update status to "Cancelled".

**Validates: Requirements 17.5, 17.6, 17.7, 17.8**

#### Property 17: Matched Reports Retrieval

*For any* student, requesting matched reports should return only reports with status "Matched", including associated found item details, match confidence score, and a link to claim the item.

**Validates: Requirements 18.1, 18.2, 18.3, 18.5**

#### Property 18: Claim History and Details

*For any* student, retrieving claim history should return all claims submitted by that student with reference ID, claim date, status, found item details, and lost report ticket ID. Results should support sorting and pagination.

**Validates: Requirements 19.1, 19.2, 19.3, 19.4, 19.5, 19.6**

#### Property 19: Release Confirmation Generation

*For any* resolved claim, generating a release confirmation should include reference ID, claim date, resolution date, found item details, photo, and pickup/delivery instructions.

**Validates: Requirements 20.1, 20.2, 20.3, 20.4**

#### Property 20: Contact Directory Completeness

*For any* support contact in the directory, the API should return name, email, phone, office location, department/role, and office hours (if applicable) for all contacts.

**Validates: Requirements 21.1, 21.2, 21.3, 21.4**

#### Property 21: Process Guide Structure and Content

*For any* process guide request, the API should return a structured guide with all sections (report lost items, search found items, claim items), detailed instructions for each step, estimated time for each process, and FAQs/troubleshooting tips.

**Validates: Requirements 22.1, 22.2, 22.3, 22.4, 22.5**

---

## Error Handling

### Error Response Format

All errors follow a consistent JSON format:

```json
{
  "ok": false,
  "error": "Human-readable error message",
  "code": "ERROR_CODE",
  "details": {
    "field": "field_name",
    "reason": "Specific validation reason"
  }
}
```

### Common Error Codes

- `VALIDATION_ERROR` - Input validation failed
- `AUTHENTICATION_REQUIRED` - User not authenticated
- `AUTHORIZATION_FAILED` - User lacks permission
- `RESOURCE_NOT_FOUND` - Requested resource doesn't exist
- `DUPLICATE_RESOURCE` - Resource already exists
- `INVALID_STATUS_TRANSITION` - Status change not allowed
- `DATABASE_ERROR` - Database operation failed
- `FILE_UPLOAD_ERROR` - Photo upload failed
- `MATCHING_ERROR` - Matching algorithm error

### Validation Error Handling

For validation errors, the response includes specific field-level errors:

```json
{
  "ok": false,
  "error": "Validation failed",
  "code": "VALIDATION_ERROR",
  "details": [
    {
      "field": "brand",
      "reason": "Brand cannot be empty"
    },
    {
      "field": "barcode",
      "reason": "Barcode must be alphanumeric and max 50 characters"
    }
  ]
}
```

### Status Code Mapping

- `400 Bad Request` - Validation error, malformed request
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Authorization failed
- `404 Not Found` - Resource not found
- `409 Conflict` - Duplicate resource or invalid state transition
- `413 Payload Too Large` - File upload exceeds size limit
- `415 Unsupported Media Type` - Invalid file format
- `500 Internal Server Error` - Unexpected server error
- `503 Service Unavailable` - Database or service unavailable

---

## Testing Strategy

### Dual Testing Approach

The backend will use both unit testing and property-based testing to ensure comprehensive correctness:

**Unit Testing**:
- Specific examples and edge cases
- Integration points between services
- Error conditions and boundary cases
- Database transaction handling
- Authentication and authorization

**Property-Based Testing**:
- Universal properties across all inputs
- Comprehensive input coverage through randomization
- Invariant preservation
- Round-trip properties (e.g., create → read → verify)
- State transition correctness

### Property-Based Testing Configuration

- **Framework**: Hypothesis (via Python bridge for PHP compatibility)
- **Minimum Iterations**: 100 per property test
- **Seed Management**: Reproducible test runs with seed tracking
- **Shrinking**: Automatic minimization of failing examples

### Test Organization

```
/tests
  /unit
    - DashboardServiceTest.php
    - FoundItemServiceTest.php
    - LostReportServiceTest.php
    - MatchingEngineTest.php
    - PhotoServiceTest.php
    - ClaimServiceTest.php
    - ArchiveServiceTest.php
    - NotificationServiceTest.php
  /property
    - DashboardPropertiesTest.php
    - FoundItemPropertiesTest.php
    - LostReportPropertiesTest.php
    - MatchingPropertiesTest.php
    - PhotoPropertiesTest.php
    - ClaimPropertiesTest.php
    - ArchivePropertiesTest.php
  /integration
    - ApiEndpointTest.php
    - WorkflowTest.php
```

### Property Test Tagging

Each property-based test includes a comment referencing the design property:

```php
/**
 * Feature: lost-and-found-backend, Property 1: Dashboard Statistics Accuracy
 * 
 * For any set of items with various statuses, the dashboard statistics endpoint 
 * should return counts that exactly match the number of items with each status.
 */
public function testDashboardStatisticsAccuracy() {
    // Test implementation
}
```

### Test Coverage Goals

- **Unit Tests**: 80%+ code coverage
- **Property Tests**: All 21 correctness properties implemented
- **Integration Tests**: All critical workflows (create → match → claim → archive)
- **Edge Cases**: Empty results, boundary values, concurrent operations

### Continuous Testing

- Unit tests run on every commit
- Property tests run nightly with extended iterations (1000+)
- Integration tests run on staging environment
- Performance tests run weekly

---

## Performance Considerations

### Database Optimization

- Indexes on frequently queried columns (status, category, dates)
- Composite indexes for common filter combinations
- Query optimization for matching algorithm
- Connection pooling for concurrent requests

### Caching Strategy

- Cache category list (rarely changes)
- Cache support contacts and guides
- Cache dashboard statistics (5-minute TTL)
- Cache search results (1-minute TTL)

### Pagination

- Default limit: 20 items per page
- Maximum limit: 100 items per page
- Offset-based pagination for simplicity
- Cursor-based pagination for large datasets (future enhancement)

### Photo Optimization

- Lazy loading of photos in list views
- Thumbnail generation for previews
- CDN delivery for photo files (future enhancement)

---

## Security Considerations

### Authentication

- Session-based authentication for existing PHP integration
- JWT tokens for API clients
- Password hashing with bcrypt
- Session timeout: 30 minutes of inactivity

### Authorization

- Role-based access control (Admin, Student)
- Resource-level authorization (students can only access their own data)
- Admin-only endpoints for sensitive operations

### Input Validation

- All inputs validated on server side
- SQL injection prevention via prepared statements
- XSS prevention via output encoding
- File upload validation (type, size, content)

### Data Protection

- HTTPS for all API communications
- Sensitive data encrypted at rest (passwords, personal info)
- Audit logging for all administrative actions
- GDPR compliance for student data

---

## Deployment and Maintenance

### Deployment Process

1. Database migrations applied
2. API services deployed
3. Configuration updated
4. Tests executed
5. Smoke tests on staging
6. Production deployment

### Monitoring

- API response time monitoring
- Database query performance
- Error rate tracking
- Photo storage usage
- Notification delivery success rate

### Maintenance Windows

- Database backups: Daily at 2 AM
- Log rotation: Weekly
- Cache clearing: As needed
- Schema updates: During maintenance windows

---

## Future Enhancements

1. **Advanced Matching**: Machine learning-based matching with image recognition
2. **Real-time Notifications**: WebSocket-based push notifications
3. **Mobile App**: Native mobile applications for iOS/Android
4. **Analytics Dashboard**: Detailed analytics and reporting
5. **Bulk Operations**: Bulk item import/export
6. **Integration**: Integration with campus systems (student directory, email)
7. **Internationalization**: Multi-language support
8. **Accessibility**: WCAG 2.1 AA compliance

