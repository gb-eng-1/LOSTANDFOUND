# Implementation Plan: Lost and Found System Backend

## Overview

This implementation plan breaks down the Lost and Found System backend into discrete, actionable coding tasks organized by feature area. Each task builds incrementally on previous work, with clear acceptance criteria and concrete examples of what the user will see at each step. The implementation follows a service-oriented architecture with PHP and MySQL, integrating with existing infrastructure while introducing modern API patterns.

## Implementation Approach

The backend will be built in phases:
1. **Foundation**: Database setup, core models, and authentication
2. **Admin Features**: Dashboard, found items management, photo handling
3. **Matching & Notifications**: Automated matching engine and notification system
4. **Student Features**: Lost reports, claims, and student dashboard
5. **Archive & Support**: Historical records and support endpoints
6. **Testing & Validation**: Property-based tests and integration tests

---

## Phase 1: Foundation and Database Setup

- [x] 1. Set up database schema and migrations
  - Create migration files for all new tables (matches, claims, archives, notifications, students, support_contacts, process_guides)
  - Enhance existing `items` table with new columns (item_type, status, disposal_deadline, matched_barcode_id)
  - Enhance existing `admins` table with role column
  - Create indexes on frequently queried columns (status, item_type, disposal_deadline, created_at)
  - Run migrations to create all tables
  - _Requirements: 4.1, 4.5, 4.6, 4.7, 12.1, 12.2, 12.3_
  - **What you'll see**: All database tables created with proper schema, ready for data insertion

- [x] 2. Create core model classes and database abstraction layer
  - Create `Database.php` utility class with PDO connection management
  - Create model classes: `FoundItem.php`, `LostReport.php`, `Match.php`, `Claim.php`, `Archive.php`
  - Implement basic CRUD methods in each model
  - Add validation methods for each model
  - _Requirements: 4.1, 4.2, 4.3, 4.4_
  - **What you'll see**: Model classes with methods like `create()`, `getById()`, `update()`, `delete()`, and validation methods

- [x] 3. Set up API routing and middleware infrastructure
  - Create router configuration to map HTTP requests to endpoints
  - Create `AuthMiddleware.php` for authentication checks
  - Create `ValidationMiddleware.php` for input validation
  - Create `ErrorHandler.php` for consistent error responses
  - Create base response formatter for JSON responses
  - _Requirements: All_
  - **What you'll see**: API requests properly routed, errors returned in consistent JSON format with proper HTTP status codes

- [x] 4. Implement authentication endpoints
  - Create `POST /api/auth/admin/login` endpoint using existing login logic
  - Create `POST /api/auth/student/login` endpoint
  - Create `POST /api/auth/logout` endpoint
  - Create `GET /api/auth/verify` endpoint to check current session
  - Implement session token generation and validation
  - _Requirements: All_
  - **What you'll see**: Login endpoints return JSON with session token, verify endpoint confirms authentication status

- [x] 5. Checkpoint - Verify database and authentication
  - Ensure all database tables exist with correct schema
  - Test authentication endpoints with sample credentials
  - Verify error responses are properly formatted
  - Ask the user if questions arise.


---

## Phase 2: Admin Dashboard and Found Items Management

- [x] 6. Implement Dashboard Service and statistics endpoints
  - Create `DashboardService.php` with methods: `getStats()`, `getCategoryDistribution()`, `getActivityFeed()`, `getStudentDashboard()`
  - Implement `GET /api/admin/dashboard/stats` endpoint returning found/lost/resolved counts
  - Implement `GET /api/admin/dashboard/categories` endpoint returning category breakdown with percentages
  - Implement `GET /api/admin/dashboard/activity` endpoint returning items approaching disposal deadline
  - Add caching with 5-minute TTL for dashboard statistics
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2, 2.3, 2.4_
  - **What you'll see**: 
    - `GET /api/admin/dashboard/stats` returns: `{"ok": true, "data": {"found": 45, "lost": 23, "resolved": 18}}`
    - `GET /api/admin/dashboard/categories` returns: `{"ok": true, "data": [{"category": "Electronics", "count": 15, "percentage": 33.3}, ...]}`
    - `GET /api/admin/dashboard/activity` returns items with disposal_deadline within 7 days, ordered by deadline

  - [ ]* 6.1 Write property test for dashboard statistics accuracy
    - **Property 1: Dashboard Statistics Accuracy**
    - **Validates: Requirements 1.1, 1.2, 1.3**

  - [ ]* 6.2 Write property test for category distribution completeness
    - **Property 2: Category Distribution Completeness and Correctness**
    - **Validates: Requirements 2.1, 2.2, 2.3**

  - [ ]* 6.3 Write property test for activity feed ordering
    - **Property 3: Activity Feed Ordering and Filtering**
    - **Validates: Requirements 3.1, 3.2, 3.3, 3.4**

- [x] 7. Implement Found Items Service and CRUD endpoints
  - Create `FoundItemService.php` with methods: `createItem()`, `getItems()`, `getItemById()`, `updateItem()`, `deleteItem()`, `searchItems()`, `getUnclaimedItems()`
  - Implement `POST /api/admin/items` endpoint for creating found items with validation
  - Implement `GET /api/admin/items` endpoint with category/status filtering and pagination
  - Implement `GET /api/admin/items/:id` endpoint for item details
  - Implement `PUT /api/admin/items/:id` endpoint for updating items
  - Implement `DELETE /api/admin/items/:id` endpoint for deleting items
  - Implement `GET /api/student/items` endpoint for student search
  - Implement `GET /api/student/items/unclaimed` endpoint for unclaimed items
  - Add barcode generation logic (UB##### format)
  - Add disposal deadline calculation (30 days from creation)
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 15.6, 15.7, 15.8, 16.1, 16.2, 16.3, 16.4, 16.5, 16.6_
  - **What you'll see**:
    - `POST /api/admin/items` with `{"brand": "Apple", "color": "Silver", "barcode": "ABC123", "storage_shelf": "A1"}` returns: `{"ok": true, "data": {"id": "UB00001", "status": "Found", "disposal_deadline": "2024-02-15", ...}}`
    - `GET /api/admin/items?category=Electronics&status=Found&limit=20&offset=0` returns paginated list with filters applied
    - `GET /api/student/items/unclaimed` returns only items with status "Found" or "Matched"

  - [ ]* 7.1 Write property test for found item creation validation
    - **Property 4: Found Item Creation Validation and Persistence**
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8**

  - [ ]* 7.2 Write property test for item filtering
    - **Property 6: Item Filtering by Category and Status**
    - **Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5, 6.6**

- [x] 8. Implement Photo Service and upload endpoints
  - Create `PhotoService.php` with methods: `uploadPhoto()`, `compressImage()`, `validateFile()`, `getPhoto()`, `deletePhoto()`
  - Implement `POST /api/items/:id/photo` endpoint for photo upload
  - Implement `GET /api/items/:id/photo` endpoint for photo retrieval
  - Implement `DELETE /api/items/:id/photo` endpoint for photo deletion
  - Add image compression logic (max 1920x1080, 80% quality)
  - Add file validation (JPEG, PNG, WebP, max 10MB)
  - Create `/uploads/items/` directory structure
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7_
  - **What you'll see**:
    - `POST /api/items/UB00001/photo` with multipart form data returns: `{"ok": true, "data": {"photo_path": "/uploads/items/UB00001_compressed.jpg"}}`
    - Photo file stored in `/uploads/items/` with compressed dimensions and quality
    - Invalid file types or oversized files return validation error

  - [ ]* 8.1 Write property test for photo compression
    - **Property 5: Photo Upload Compression and Storage**
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 5.7**

- [x] 9. Checkpoint - Verify admin dashboard and found items
  - Ensure dashboard endpoints return correct statistics
  - Test found item creation with valid and invalid data
  - Verify photo upload and compression
  - Test filtering and pagination
  - Ask the user if questions arise.


---

## Phase 3: Lost Reports and Matching Engine

- [x] 10. Implement Lost Reports Service and endpoints
  - Create `LostReportService.php` with methods: `createReport()`, `getReports()`, `getReportById()`, `updateReport()`, `cancelReport()`, `getMatchedReports()`
  - Implement `POST /api/student/reports` endpoint for creating lost reports
  - Implement `GET /api/student/reports` endpoint for student's reports
  - Implement `GET /api/student/reports/:id` endpoint for report details
  - Implement `PUT /api/student/reports/:id` endpoint for updating reports
  - Implement `DELETE /api/student/reports/:id` endpoint for cancelling reports
  - Implement `GET /api/admin/reports` endpoint for admin search with Ticket_ID, name, description filters
  - Implement `GET /api/admin/reports/matched` endpoint for matched reports
  - Add ticket ID generation (REF-##### format)
  - Add validation for description (min 10 characters), category, location
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 8.1, 8.2, 8.3, 15.1, 15.2, 15.3, 15.4, 15.5, 17.1, 17.2, 17.3, 17.4, 17.5, 17.6, 17.7, 17.8, 18.1, 18.2, 18.3, 18.5_
  - **What you'll see**:
    - `POST /api/student/reports` with `{"description": "Lost my silver MacBook Pro", "category": "Electronics", "location": "Library"}` returns: `{"ok": true, "data": {"id": "REF-00001", "ticket_id": "REF-00001", "status": "Lost", ...}}`
    - `GET /api/student/reports` returns all reports for authenticated student
    - `GET /api/admin/reports?search=REF-00001` returns matching reports
    - `DELETE /api/student/reports/REF-00001` updates status to "Cancelled" (only if Lost or Matched)

  - [ ]* 10.1 Write property test for lost report creation
    - **Property 14: Lost Report Creation and Validation**
    - **Validates: Requirements 15.1, 15.2, 15.3, 15.4, 15.5, 17.1, 17.2, 17.3, 17.4**

  - [ ]* 10.2 Write property test for report search and filtering
    - **Property 7: Lost Report Search and Filtering**
    - **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 8.1, 8.2, 8.3**

  - [ ]* 10.3 Write property test for report management
    - **Property 16: Student Report Management**
    - **Validates: Requirements 17.5, 17.6, 17.7, 17.8**

- [x] 11. Implement Matching Engine and match endpoints
  - Create `MatchingEngine.php` with methods: `findMatches()`, `calculateConfidence()`, `createMatch()`, `approveMatch()`, `rejectMatch()`
  - Implement matching algorithm: category match (40 pts) + location proximity (30 pts) + color match (15 pts) + brand match (15 pts)
  - Implement `GET /api/admin/matches` endpoint listing all matches
  - Implement `GET /api/admin/matches/:id` endpoint with side-by-side photo comparison
  - Implement `POST /api/admin/matches/:id/approve` endpoint to approve match
  - Implement `POST /api/admin/matches/:id/reject` endpoint to reject match
  - Implement `GET /api/admin/matches/report/:id` endpoint for matches of specific report
  - Trigger matching when new found item is created
  - Trigger matching when new lost report is created
  - Create notifications for new matches
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 10.1, 10.3, 10.4, 10.5, 10.6, 11.1, 11.2, 11.3_
  - **What you'll see**:
    - `GET /api/admin/matches` returns: `{"ok": true, "data": [{"id": 1, "lost_report_id": "REF-00001", "found_item_id": "UB00001", "confidence_score": 85, "status": "Pending_Review", ...}]}`
    - `GET /api/admin/matches/1` returns both photos, item details, confidence score, and matching criteria
    - `POST /api/admin/matches/1/approve` updates match status to "Approved" and updates found item status to "Matched"
    - Matching triggered automatically when new items created

  - [ ]* 11.1 Write property test for matching algorithm
    - **Property 8: Automated Matching Algorithm**
    - **Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 11.1, 11.2, 11.3**

  - [ ]* 11.2 Write property test for match comparison and updates
    - **Property 9: Match Comparison and Status Updates**
    - **Validates: Requirements 10.1, 10.3, 10.4, 10.5, 10.6**

- [x] 12. Implement Notification Service and endpoints
  - Create `NotificationService.php` with methods: `sendNotification()`, `getNotifications()`, `markAsRead()`, `deleteNotification()`
  - Implement `GET /api/notifications` endpoint returning unread notifications ordered by creation date
  - Implement `PUT /api/notifications/:id/read` endpoint to mark as read
  - Implement `DELETE /api/notifications/:id` endpoint to delete notification
  - Create notifications for: match_found, claim_approved, claim_rejected, item_disposal_warning
  - Integrate with matching engine to send notifications when matches created
  - _Requirements: 11.1, 11.2, 11.3, 11.4_
  - **What you'll see**:
    - `GET /api/notifications` returns: `{"ok": true, "data": [{"id": 1, "type": "match_found", "title": "New Match Found", "message": "...", "is_read": false, ...}]}`
    - Notifications ordered by creation date (newest first)
    - `PUT /api/notifications/1/read` marks notification as read

  - [ ]* 12.1 Write property test for notification retrieval
    - **Property 10: Notification Retrieval and Ordering**
    - **Validates: Requirements 11.4**

- [x] 13. Checkpoint - Verify matching and notifications
  - Create test lost report and found item
  - Verify matching engine creates matches with correct confidence scores
  - Verify admin receives notification for new match
  - Test match approval and rejection
  - Ask the user if questions arise.


---

## Phase 4: Student Dashboard and Claims

- [x] 14. Implement Student Dashboard endpoints
  - Create student-specific dashboard endpoints in `DashboardService.php`
  - Implement `GET /api/student/dashboard` endpoint returning student statistics
  - Return counts: lost reports, available found items, submitted claims, resolved claims
  - Include quick link to report new lost item
  - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_
  - **What you'll see**:
    - `GET /api/student/dashboard` returns: `{"ok": true, "data": {"lost_reports": 3, "available_items": 12, "submitted_claims": 2, "resolved_claims": 1, "quick_report_link": "/api/student/reports"}}`

  - [ ]* 14.1 Write property test for student dashboard statistics
    - **Property 13: Student Dashboard Statistics**
    - **Validates: Requirements 14.1, 14.2, 14.3, 14.4**

- [x] 15. Implement Claim Service and endpoints
  - Create `ClaimService.php` with methods: `submitClaim()`, `getClaims()`, `getClaimById()`, `updateClaimStatus()`, `generateReleaseConfirmation()`, `resolveClaim()`
  - Implement `POST /api/student/claims` endpoint for submitting claims with proof photo and description
  - Implement `GET /api/student/claims` endpoint for student's claim history
  - Implement `GET /api/student/claims/:id` endpoint for claim details
  - Implement `GET /api/student/claims/:id/confirmation` endpoint for release confirmation
  - Implement `GET /api/admin/claims` endpoint for admin to view all claims
  - Implement `PUT /api/admin/claims/:id/status` endpoint for admin to update claim status
  - Add reference ID generation (REF-##### format for claims)
  - Add claim status workflow: Pending → Approved/Rejected → Resolved
  - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6, 20.1, 20.2, 20.3, 20.4, 20.5_
  - **What you'll see**:
    - `POST /api/student/claims` with `{"found_item_id": "UB00001", "proof_description": "Silver MacBook with sticker"}` returns: `{"ok": true, "data": {"id": 1, "reference_id": "REF-CLAIM-001", "status": "Pending", "claim_date": "2024-01-15T10:30:00Z", ...}}`
    - `GET /api/student/claims` returns all claims with reference ID, claim date, status, found item details
    - `GET /api/student/claims/1/confirmation` returns release confirmation with pickup/delivery instructions
    - Admin can update claim status to Approved/Rejected/Resolved

  - [ ]* 15.1 Write property test for claim history
    - **Property 18: Claim History and Details**
    - **Validates: Requirements 19.1, 19.2, 19.3, 19.4, 19.5, 19.6**

  - [ ]* 15.2 Write property test for release confirmation
    - **Property 19: Release Confirmation Generation**
    - **Validates: Requirements 20.1, 20.2, 20.3, 20.4**

- [x] 16. Implement matched reports retrieval for students
  - Add `getMatchedReports()` method to `LostReportService.php`
  - Implement `GET /api/student/reports/matched` endpoint
  - Return reports with status "Matched" including associated found item details
  - Include match confidence score and claim link
  - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5_
  - **What you'll see**:
    - `GET /api/student/reports/matched` returns: `{"ok": true, "data": [{"id": "REF-00001", "status": "Matched", "found_item": {...}, "confidence_score": 85, "claim_link": "/api/student/claims", ...}]}`

  - [ ]* 16.1 Write property test for matched reports
    - **Property 17: Matched Reports Retrieval**
    - **Validates: Requirements 18.1, 18.2, 18.3, 18.5**

- [x] 17. Checkpoint - Verify student features
  - Create student account and test dashboard
  - Submit lost report and verify it appears in dashboard
  - Create found item and verify matching occurs
  - Submit claim on matched item
  - Verify claim appears in claim history
  - Ask the user if questions arise.


---

## Phase 5: Archive and Support

- [x] 18. Implement Archive Service and endpoints
  - Create `ArchiveService.php` with methods: `archiveClaim()`, `searchArchives()`, `getArchiveRecord()`
  - Implement `GET /api/admin/archives` endpoint for searching archived items
  - Implement `GET /api/admin/archives/:id` endpoint for archive record details
  - Add archive creation when claim is resolved
  - Support search by: reference ID, claimant name, date range, category
  - Support pagination for archive results
  - Include all required fields: item details snapshot, claimant info, proof photo, dates
  - Update found item status to "Archived" when claim resolved
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 13.1, 13.2, 13.3, 13.4, 13.5_
  - **What you'll see**:
    - `GET /api/admin/archives?reference_id=REF-CLAIM-001` returns: `{"ok": true, "data": [{"id": 1, "reference_id": "REF-CLAIM-001", "claimant_name": "John Doe", "claimant_email": "john@example.com", "item_details": {...}, "proof_photo": "...", "claim_date": "2024-01-15T10:30:00Z", "resolution_date": "2024-01-16T14:00:00Z", ...}]}`
    - `GET /api/admin/archives?claimant_name=John&date_from=2024-01-01&date_to=2024-01-31` returns matching archived records
    - Archive records include complete snapshot of found item at time of archival

  - [ ]* 18.1 Write property test for archive creation
    - **Property 11: Archive Creation and Completeness**
    - **Validates: Requirements 12.1, 12.2, 12.3, 12.4, 12.5, 12.6**

  - [ ]* 18.2 Write property test for archive search
    - **Property 12: Archive Search and Filtering**
    - **Validates: Requirements 13.1, 13.2, 13.3, 13.4, 13.5**

- [x] 19. Implement Support Service and endpoints
  - Create `SupportService.php` with methods: `getContacts()`, `getGuides()`, `getGuideDetails()`
  - Implement `GET /api/support/contacts` endpoint returning contact directory
  - Implement `GET /api/support/guides` endpoint returning all process guides
  - Implement `GET /api/support/guides/:section` endpoint for specific section guides
  - Populate `support_contacts` table with sample contacts
  - Populate `process_guides` table with guides for: report_lost, search_found, claim_item
  - Include FAQs and troubleshooting tips in guides
  - _Requirements: 21.1, 21.2, 21.3, 21.4, 22.1, 22.2, 22.3, 22.4, 22.5_
  - **What you'll see**:
    - `GET /api/support/contacts` returns: `{"ok": true, "data": [{"id": 1, "name": "John Smith", "email": "john@support.edu", "phone": "555-1234", "office_location": "Building A, Room 101", "department": "Lost & Found", "role": "Manager", "office_hours": "Mon-Fri 9AM-5PM", ...}]}`
    - `GET /api/support/guides/report_lost` returns: `{"ok": true, "data": {"section": "report_lost", "steps": [{"step_number": 1, "instruction": "...", "estimated_time_minutes": 5}, ...], "faq": [...], "troubleshooting": [...]}}`

  - [ ]* 19.1 Write property test for contact directory
    - **Property 20: Contact Directory Completeness**
    - **Validates: Requirements 21.1, 21.2, 21.3, 21.4**

  - [ ]* 19.2 Write property test for process guides
    - **Property 21: Process Guide Structure and Content**
    - **Validates: Requirements 22.1, 22.2, 22.3, 22.4, 22.5**

- [x] 20. Implement Found Item search for students
  - Add `searchItems()` method to `FoundItemService.php` for student-specific search
  - Implement `GET /api/student/items` endpoint with search parameters
  - Support filtering by: category, keyword (brand/color/description), location
  - Return only items with status "Found" or "Matched"
  - Support pagination
  - Include disposal deadline for each item
  - _Requirements: 15.6, 15.7, 15.8, 16.1, 16.2, 16.3, 16.4, 16.5, 16.6_
  - **What you'll see**:
    - `GET /api/student/items?category=Electronics&keyword=MacBook&limit=20&offset=0` returns: `{"ok": true, "data": [{"id": "UB00001", "brand": "Apple", "color": "Silver", "category": "Electronics", "photo": "...", "disposal_deadline": "2024-02-15", "status": "Found", ...}]}`

  - [ ]* 20.1 Write property test for student item search
    - **Property 15: Found Item Search for Students**
    - **Validates: Requirements 15.6, 15.7, 15.8, 16.1, 16.2, 16.3, 16.4, 16.5, 16.6**

- [x] 21. Checkpoint - Verify archive and support features
  - Resolve a claim and verify archive record is created
  - Search archived items by various criteria
  - Verify found item status changed to "Archived"
  - Test support endpoints for contacts and guides
  - Ask the user if questions arise.


---

## Phase 6: Integration and Testing

- [x] 22. Integrate all services and verify API completeness
  - Verify all endpoints are properly routed and accessible
  - Test authentication flow for both admin and student roles
  - Verify authorization checks (students can only access their own data)
  - Test error handling for all endpoints
  - Verify response formats are consistent across all endpoints
  - Test pagination across all list endpoints
  - _Requirements: All_
  - **What you'll see**: All endpoints accessible, proper error responses for invalid requests, consistent JSON response format

- [x] 23. Implement activity logging
  - Create activity logging in `ActivityLog` table for all major operations
  - Log actions: item_encoded, item_matched, claim_submitted, claim_approved, claim_resolved, item_archived
  - Include actor information (admin/student ID and type)
  - Include operation details in JSON format
  - _Requirements: All_
  - **What you'll see**: Activity log entries created for each operation, queryable by item_id, action, or date

- [x] 24. Implement comprehensive unit tests
  - Create unit tests for `DashboardService.php`
  - Create unit tests for `FoundItemService.php`
  - Create unit tests for `LostReportService.php`
  - Create unit tests for `MatchingEngine.php`
  - Create unit tests for `PhotoService.php`
  - Create unit tests for `ClaimService.php`
  - Create unit tests for `ArchiveService.php`
  - Create unit tests for `NotificationService.php`
  - Create unit tests for `SupportService.php`
  - Test edge cases: empty results, boundary values, invalid inputs
  - Test error conditions: database failures, file upload errors, validation failures
  - _Requirements: All_
  - **What you'll see**: Unit test files created in `/tests/unit/` directory, all tests passing

- [x] 25. Implement property-based tests for all correctness properties
  - Create property test file for Dashboard properties (Properties 1, 2, 3)
  - Create property test file for Found Items properties (Properties 4, 6, 15)
  - Create property test file for Lost Reports properties (Properties 7, 14, 16)
  - Create property test file for Matching properties (Properties 8, 9)
  - Create property test file for Photo properties (Property 5)
  - Create property test file for Claims properties (Properties 18, 19)
  - Create property test file for Archive properties (Properties 11, 12)
  - Create property test file for Notifications properties (Property 10)
  - Create property test file for Support properties (Properties 20, 21)
  - Each property test generates 100+ random test cases
  - _Requirements: All_
  - **What you'll see**: Property test files created in `/tests/property/` directory, all properties validated with random inputs

- [x] 26. Implement integration tests for critical workflows
  - Create test for complete workflow: create found item → create lost report → match → claim → resolve → archive
  - Create test for photo upload and compression workflow
  - Create test for notification delivery workflow
  - Create test for concurrent operations (multiple claims on same item)
  - Create test for status transition validation
  - _Requirements: All_
  - **What you'll see**: Integration test files created in `/tests/integration/` directory, all workflows validated end-to-end

- [x] 27. Checkpoint - Run all tests
  - Run all unit tests and verify 80%+ code coverage
  - Run all property-based tests with 100+ iterations each
  - Run all integration tests
  - Verify no test failures
  - Ask the user if questions arise.


---

## Phase 7: Final Validation and Documentation

- [x] 28. Verify all requirements are implemented
  - Create checklist of all 22 requirements
  - Verify each requirement has corresponding API endpoint(s)
  - Verify each requirement has corresponding test(s)
  - Verify each requirement has corresponding database schema
  - Document any requirements that required design modifications
  - _Requirements: All_
  - **What you'll see**: Requirements traceability matrix showing all requirements mapped to implementation

- [x] 29. Test all API endpoints with sample data
  - Create comprehensive test data set (10+ found items, 5+ lost reports, 3+ matches, 2+ claims)
  - Test all admin endpoints with admin account
  - Test all student endpoints with student account
  - Test all public endpoints (support, auth)
  - Verify response data matches expected format
  - Verify pagination works correctly
  - Verify filtering and search work correctly
  - _Requirements: All_
  - **What you'll see**: All endpoints return correct data, pagination works, filters applied correctly

- [x] 30. Verify error handling and edge cases
  - Test invalid authentication (wrong password, non-existent user)
  - Test authorization failures (student accessing admin endpoints)
  - Test validation errors (missing required fields, invalid formats)
  - Test resource not found errors (accessing non-existent items)
  - Test duplicate resource errors (creating duplicate items)
  - Test file upload errors (invalid format, oversized file)
  - Test concurrent operations (multiple claims on same item)
  - Test status transition errors (invalid status changes)
  - _Requirements: All_
  - **What you'll see**: All error cases return proper HTTP status codes and error messages

- [x] 31. Verify database integrity and performance
  - Verify all indexes are created and used
  - Test query performance with large datasets (1000+ items)
  - Verify foreign key constraints work correctly
  - Verify transactions work for critical operations
  - Verify data consistency after concurrent operations
  - _Requirements: All_
  - **What you'll see**: Queries execute efficiently, no N+1 query problems, data remains consistent

- [x] 32. Final checkpoint - System ready for deployment
  - All tests passing (unit, property, integration)
  - All requirements implemented and verified
  - All error cases handled
  - Database schema complete and optimized
  - API documentation complete
  - Ask the user if questions arise.

---

## Testing and Validation Guide

### Running Tests

**Unit Tests**:
```bash
php vendor/bin/phpunit tests/unit/ --coverage-html coverage/
```

**Property-Based Tests**:
```bash
php vendor/bin/phpunit tests/property/ --repeat=100
```

**Integration Tests**:
```bash
php vendor/bin/phpunit tests/integration/
```

**All Tests**:
```bash
php vendor/bin/phpunit tests/
```

### Sample Test Data

**Admin Account**:
- Email: admin@example.edu
- Password: admin123

**Student Accounts**:
- Email: student1@example.edu, Password: student123
- Email: student2@example.edu, Password: student123

**Sample Found Items**:
- UB00001: Apple MacBook Pro, Silver, Electronics
- UB00002: Blue Backpack, Accessories
- UB00003: Student ID Card, Documents

**Sample Lost Reports**:
- REF-00001: Lost silver MacBook Pro in library
- REF-00002: Lost blue backpack near cafeteria
- REF-00003: Lost student ID card in building A

### API Testing Examples

**Create Found Item**:
```bash
curl -X POST http://localhost/api/admin/items \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "brand": "Apple",
    "color": "Silver",
    "barcode": "ABC123",
    "storage_shelf": "A1",
    "category": "Electronics"
  }'
```

**Upload Photo**:
```bash
curl -X POST http://localhost/api/items/UB00001/photo \
  -H "Authorization: Bearer {token}" \
  -F "photo=@/path/to/photo.jpg"
```

**Create Lost Report**:
```bash
curl -X POST http://localhost/api/student/reports \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "description": "Lost my silver MacBook Pro in the library",
    "category": "Electronics",
    "location": "Library"
  }'
```

**Submit Claim**:
```bash
curl -X POST http://localhost/api/student/claims \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "found_item_id": "UB00001",
    "proof_description": "Silver MacBook with Apple sticker"
  }'
```

**Get Dashboard Stats**:
```bash
curl -X GET http://localhost/api/admin/dashboard/stats \
  -H "Authorization: Bearer {token}"
```

### Expected Responses

**Dashboard Stats Response**:
```json
{
  "ok": true,
  "data": {
    "found": 45,
    "lost": 23,
    "resolved": 18
  },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "version": "1.0"
  }
}
```

**Found Item Response**:
```json
{
  "ok": true,
  "data": {
    "id": "UB00001",
    "brand": "Apple",
    "color": "Silver",
    "category": "Electronics",
    "status": "Found",
    "disposal_deadline": "2024-02-15",
    "photo": "/uploads/items/UB00001_compressed.jpg",
    "created_at": "2024-01-15T10:30:00Z"
  },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "version": "1.0"
  }
}
```

**Match Response**:
```json
{
  "ok": true,
  "data": {
    "id": 1,
    "lost_report_id": "REF-00001",
    "found_item_id": "UB00001",
    "confidence_score": 85,
    "status": "Pending_Review",
    "matching_criteria": {
      "category": true,
      "location": true,
      "color": true,
      "brand": false
    },
    "found_item": { /* full item details */ },
    "lost_report": { /* full report details */ },
    "created_at": "2024-01-15T10:30:00Z"
  },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "version": "1.0"
  }
}
```

**Error Response**:
```json
{
  "ok": false,
  "error": "Validation failed",
  "code": "VALIDATION_ERROR",
  "details": [
    {
      "field": "brand",
      "reason": "Brand cannot be empty"
    }
  ]
}
```

---

## Notes

- Tasks marked with `*` are optional property-based tests and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation of functionality
- Property tests validate universal correctness properties across all inputs
- Unit tests validate specific examples and edge cases
- All code should follow PSR-12 coding standards
- All database queries should use prepared statements to prevent SQL injection
- All file uploads should be validated for type and size
- All sensitive data should be encrypted at rest
- All API responses should include proper HTTP status codes and error messages

