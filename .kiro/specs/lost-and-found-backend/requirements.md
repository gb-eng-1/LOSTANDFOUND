# Lost and Found System - Backend Requirements

## Introduction

The Lost and Found System is a backend service designed to manage lost and found items for an educational institution. The system serves two primary user roles: Administrators who manage found items, generate reports, and facilitate matching between lost and found items; and Students who report lost items, search for found items, and claim matched items. The backend provides RESTful APIs to support both admin and student interfaces with comprehensive data management, matching algorithms, and notification capabilities.

## Glossary

- **Admin**: An administrator user with permissions to manage found items, generate reports, and facilitate item matching
- **Student**: A student user who can report lost items, search for found items, and claim matched items
- **Found_Item**: A physical item that has been found and registered in the system with details like brand, color, barcode, and storage location
- **Lost_Report**: A report submitted by a student indicating they have lost an item with specific characteristics
- **Match**: An automated or manual association between a Lost_Report and a Found_Item based on category and location similarity
- **Claim**: A student's request to claim a Found_Item that matches their Lost_Report
- **Proof**: Evidence provided by a student (photo, description) to verify ownership of a Found_Item
- **Category**: A classification of items (e.g., Electronics, Clothing, Accessories, Documents)
- **Status**: The current state of an item or report (e.g., Found, Lost, Matched, Claimed, Resolved, Archived)
- **Ticket_ID**: A unique identifier assigned to each Lost_Report for tracking and reference
- **Reference_ID**: A unique identifier assigned to each Claim for tracking and reference
- **Disposal_Deadline**: The date after which an unclaimed Found_Item may be disposed of
- **Claimant_Info**: Information about the student claiming a Found_Item (name, contact, proof)

## Requirements

### ADMIN DASHBOARD

#### Requirement 1: Real-Time Dashboard Statistics

**User Story:** As an admin, I want to see real-time counts of found items, lost reports, and resolved cases on my dashboard, so that I can quickly understand the current state of the system.

#### Acceptance Criteria

1. WHEN the admin accesses the dashboard, THE Dashboard_API SHALL return the count of items with status "Found"
2. WHEN the admin accesses the dashboard, THE Dashboard_API SHALL return the count of reports with status "Lost"
3. WHEN the admin accesses the dashboard, THE Dashboard_API SHALL return the count of cases with status "Resolved"
4. WHEN a Found_Item status changes, THE Dashboard_API SHALL update the "Items Found" count within 2 seconds
5. WHEN a Lost_Report is created, THE Dashboard_API SHALL update the "Items Lost" count within 2 seconds
6. WHEN a Claim is marked as resolved, THE Dashboard_API SHALL update the "Resolved Cases" count within 2 seconds

#### Requirement 2: Category Distribution Pie Chart Data

**User Story:** As an admin, I want to see a pie chart showing the distribution of found items by category, so that I can identify which categories have the most items.

#### Acceptance Criteria

1. WHEN the admin requests category statistics, THE Dashboard_API SHALL return a breakdown of Found_Items grouped by Category
2. WHEN the admin requests category statistics, THE Dashboard_API SHALL include the count of items in each Category
3. WHEN the admin requests category statistics, THE Dashboard_API SHALL include the percentage of total items for each Category
4. WHEN a Found_Item is added or its category is changed, THE Dashboard_API SHALL update category statistics within 2 seconds

#### Requirement 3: Recent Activity Feed for Disposal Deadlines

**User Story:** As an admin, I want to see a feed of recent activity focused on items approaching their disposal deadline, so that I can take timely action on unclaimed items.

#### Acceptance Criteria

1. WHEN the admin requests the activity feed, THE Dashboard_API SHALL return Found_Items ordered by Disposal_Deadline in ascending order
2. WHEN the admin requests the activity feed, THE Dashboard_API SHALL include items with Disposal_Deadline within the next 7 days
3. WHEN the admin requests the activity feed, THE Dashboard_API SHALL include the item details (brand, color, category, current status)
4. WHEN the admin requests the activity feed, THE Dashboard_API SHALL include the days remaining until Disposal_Deadline
5. WHEN a Found_Item's Disposal_Deadline is reached, THE Dashboard_API SHALL flag the item for disposal in the activity feed

### FOUND ITEMS MANAGEMENT

#### Requirement 4: Encode Found Items with Detailed Information

**User Story:** As an admin, I want to encode found items with brand, color, barcode, and storage shelf information, so that I can maintain accurate inventory and facilitate matching.

#### Acceptance Criteria

1. WHEN an admin submits a Found_Item creation request, THE Found_Item_API SHALL accept brand, color, barcode, and storage_shelf fields
2. WHEN an admin submits a Found_Item creation request, THE Found_Item_API SHALL validate that brand and color are non-empty strings
3. WHEN an admin submits a Found_Item creation request, THE Found_Item_API SHALL validate that barcode follows the format [alphanumeric, max 50 characters]
4. WHEN an admin submits a Found_Item creation request, THE Found_Item_API SHALL validate that storage_shelf is a valid shelf identifier
5. WHEN an admin submits a Found_Item creation request, THE Found_Item_API SHALL assign a unique Found_Item_ID
6. WHEN an admin submits a Found_Item creation request, THE Found_Item_API SHALL set the initial status to "Found"
7. WHEN an admin submits a Found_Item creation request, THE Found_Item_API SHALL set the Disposal_Deadline to 30 days from creation date
8. WHEN an admin updates a Found_Item, THE Found_Item_API SHALL persist all changes to the database

#### Requirement 5: Photo Upload with Compression

**User Story:** As an admin, I want to upload photos of found items with automatic compression, so that I can maintain a visual record without excessive storage usage.

#### Acceptance Criteria

1. WHEN an admin uploads a photo for a Found_Item, THE Photo_Service SHALL accept image files in formats: JPEG, PNG, WebP
2. WHEN an admin uploads a photo, THE Photo_Service SHALL validate that file size does not exceed 10MB
3. WHEN an admin uploads a photo, THE Photo_Service SHALL compress the image to a maximum dimension of 1920x1080 pixels
4. WHEN an admin uploads a photo, THE Photo_Service SHALL reduce image quality to 80% to minimize file size
5. WHEN an admin uploads a photo, THE Photo_Service SHALL store the compressed image with a unique filename
6. WHEN an admin uploads a photo, THE Photo_Service SHALL return the storage path for the compressed image
7. WHEN an admin uploads a photo, THE Photo_Service SHALL associate the photo with the Found_Item record

#### Requirement 6: Filter Found Items by Category and Status

**User Story:** As an admin, I want to filter found items by category and status, so that I can quickly locate specific items.

#### Acceptance Criteria

1. WHEN an admin requests a list of Found_Items with category filter, THE Found_Item_API SHALL return only items matching the specified Category
2. WHEN an admin requests a list of Found_Items with status filter, THE Found_Item_API SHALL return only items matching the specified Status
3. WHEN an admin requests a list with both category and status filters, THE Found_Item_API SHALL return items matching both conditions
4. WHEN an admin requests a list with no filters, THE Found_Item_API SHALL return all Found_Items
5. WHEN an admin requests a filtered list, THE Found_Item_API SHALL support pagination with limit and offset parameters
6. WHEN an admin requests a filtered list, THE Found_Item_API SHALL return results sorted by creation date (newest first)

### REPORTS MANAGEMENT

#### Requirement 7: Searchable Lost Reports Table

**User Story:** As an admin, I want to search and view a table of lost reports, so that I can track all student reports and their status.

#### Acceptance Criteria

1. WHEN an admin requests lost reports, THE Reports_API SHALL return all Lost_Reports with their details (Ticket_ID, student name, item description, category, date reported)
2. WHEN an admin searches reports by Ticket_ID, THE Reports_API SHALL return matching Lost_Reports
3. WHEN an admin searches reports by student name, THE Reports_API SHALL return Lost_Reports submitted by that student
4. WHEN an admin searches reports by item description, THE Reports_API SHALL perform case-insensitive text search
5. WHEN an admin requests reports, THE Reports_API SHALL support pagination with limit and offset parameters
6. WHEN an admin requests reports, THE Reports_API SHALL support sorting by date reported, status, or category

#### Requirement 8: Filter Lost Reports by Category

**User Story:** As an admin, I want to filter lost reports by category, so that I can focus on specific types of items.

#### Acceptance Criteria

1. WHEN an admin applies a category filter to lost reports, THE Reports_API SHALL return only reports with the specified Category
2. WHEN an admin applies multiple category filters, THE Reports_API SHALL return reports matching any of the selected categories
3. WHEN an admin clears category filters, THE Reports_API SHALL return all lost reports

### MATCHING ENGINE

#### Requirement 9: Automated Matching by Category and Location

**User Story:** As an admin, I want the system to automatically match lost reports with found items based on category and location, so that I can efficiently facilitate item recovery.

#### Acceptance Criteria

1. WHEN a new Found_Item is created, THE Matching_Engine SHALL search for Lost_Reports with matching Category
2. WHEN a new Found_Item is created, THE Matching_Engine SHALL search for Lost_Reports with matching or nearby Location
3. WHEN the Matching_Engine finds potential matches, THE Matching_Engine SHALL create Match records with a confidence score
4. WHEN the Matching_Engine creates a Match, THE Matching_Engine SHALL set the Match status to "Pending_Review"
5. WHEN a new Lost_Report is created, THE Matching_Engine SHALL search for Found_Items with matching Category
6. WHEN a new Lost_Report is created, THE Matching_Engine SHALL search for Found_Items with matching or nearby Location
7. WHEN the Matching_Engine finds potential matches, THE Matching_Engine SHALL create Match records with a confidence score
8. WHEN the Matching_Engine creates a Match, THE Matching_Engine SHALL notify the admin of potential matches

#### Requirement 10: Side-by-Side Comparison of Proof vs Photo

**User Story:** As an admin, I want to compare student proof photos with found item photos side-by-side, so that I can verify matches before approval.

#### Acceptance Criteria

1. WHEN an admin views a Match, THE Matching_API SHALL return the Found_Item photo and the Lost_Report proof photo
2. WHEN an admin views a Match, THE Matching_API SHALL display both photos in a comparable format
3. WHEN an admin views a Match, THE Matching_API SHALL include item details from both the Found_Item and Lost_Report
4. WHEN an admin views a Match, THE Matching_API SHALL include the confidence score and matching criteria
5. WHEN an admin approves a Match, THE Matching_API SHALL update the Match status to "Approved"
6. WHEN an admin rejects a Match, THE Matching_API SHALL update the Match status to "Rejected"

#### Requirement 11: Match Notifications

**User Story:** As an admin, I want to receive notifications when potential matches are found, so that I can review them promptly.

#### Acceptance Criteria

1. WHEN the Matching_Engine creates a new Match with status "Pending_Review", THE Notification_Service SHALL send a notification to the admin
2. WHEN a Match is created, THE Notification_Service SHALL include the Ticket_ID, Found_Item_ID, and confidence score in the notification
3. WHEN a Match is created, THE Notification_Service SHALL store the notification in the admin's notification queue
4. WHEN an admin retrieves notifications, THE Notification_API SHALL return unread notifications ordered by creation date (newest first)

### HISTORY & ARCHIVE

#### Requirement 12: Archive Claimed Items with Claimant Information

**User Story:** As an admin, I want to archive claimed items with complete claimant information and proof, so that I can maintain a historical record.

#### Acceptance Criteria

1. WHEN a Claim is marked as "Resolved", THE Archive_Service SHALL create an archive record containing the Found_Item details
2. WHEN a Claim is marked as "Resolved", THE Archive_Service SHALL include the Claimant_Info (name, contact, student ID)
3. WHEN a Claim is marked as "Resolved", THE Archive_Service SHALL include the proof photo provided by the student
4. WHEN a Claim is marked as "Resolved", THE Archive_Service SHALL include the claim date and resolution date
5. WHEN a Claim is marked as "Resolved", THE Archive_Service SHALL include the Reference_ID for tracking
6. WHEN a Claim is marked as "Resolved", THE Archive_Service SHALL move the Found_Item status to "Archived"

#### Requirement 13: Search Archived Items

**User Story:** As an admin, I want to search archived items by various criteria, so that I can retrieve historical information.

#### Acceptance Criteria

1. WHEN an admin searches archived items by Reference_ID, THE Archive_API SHALL return the matching archived record
2. WHEN an admin searches archived items by claimant name, THE Archive_API SHALL return archived records for that student
3. WHEN an admin searches archived items by date range, THE Archive_API SHALL return archived records within the specified dates
4. WHEN an admin searches archived items by category, THE Archive_API SHALL return archived records matching the category
5. WHEN an admin requests archived items, THE Archive_API SHALL support pagination with limit and offset parameters

### STUDENT DASHBOARD

#### Requirement 14: Student Dashboard Summary

**User Story:** As a student, I want to see a summary of my reports and found items on my dashboard, so that I can quickly understand my activity.

#### Acceptance Criteria

1. WHEN a student accesses their dashboard, THE Dashboard_API SHALL return the count of Lost_Reports submitted by the student
2. WHEN a student accesses their dashboard, THE Dashboard_API SHALL return the count of Found_Items available for claiming
3. WHEN a student accesses their dashboard, THE Dashboard_API SHALL return the count of Claims submitted by the student
4. WHEN a student accesses their dashboard, THE Dashboard_API SHALL return the count of resolved Claims
5. WHEN a student accesses their dashboard, THE Dashboard_API SHALL include a quick link to report a new lost item

#### Requirement 15: Quick Report Button and Search Functionality

**User Story:** As a student, I want to quickly report a lost item and search for found items, so that I can efficiently manage my lost items.

#### Acceptance Criteria

1. WHEN a student submits a lost item report, THE Lost_Report_API SHALL accept item description, category, and location
2. WHEN a student submits a lost item report, THE Lost_Report_API SHALL validate that description is non-empty and at least 10 characters
3. WHEN a student submits a lost item report, THE Lost_Report_API SHALL validate that category is a valid Category
4. WHEN a student submits a lost item report, THE Lost_Report_API SHALL assign a unique Ticket_ID
5. WHEN a student submits a lost item report, THE Lost_Report_API SHALL set the initial status to "Lost"
6. WHEN a student searches for found items, THE Found_Item_API SHALL accept search parameters (category, keyword, location)
7. WHEN a student searches for found items, THE Found_Item_API SHALL return Found_Items with status "Found" or "Matched"
8. WHEN a student searches for found items, THE Found_Item_API SHALL support pagination

#### Requirement 16: View Unclaimed Items with Ticket ID

**User Story:** As a student, I want to view unclaimed found items and see their ticket IDs, so that I can track and claim items.

#### Acceptance Criteria

1. WHEN a student requests unclaimed items, THE Found_Item_API SHALL return Found_Items with status "Found"
2. WHEN a student requests unclaimed items, THE Found_Item_API SHALL include the Ticket_ID for each item
3. WHEN a student requests unclaimed items, THE Found_Item_API SHALL include item details (brand, color, category, photo)
4. WHEN a student requests unclaimed items, THE Found_Item_API SHALL include the Disposal_Deadline for each item
5. WHEN a student requests unclaimed items, THE Found_Item_API SHALL support filtering by category
6. WHEN a student requests unclaimed items, THE Found_Item_API SHALL support pagination

### STUDENT MY REPORTS

#### Requirement 17: Report, Cancel, and View Lost Items

**User Story:** As a student, I want to report lost items, cancel reports, and view my lost item reports, so that I can manage my lost items.

#### Acceptance Criteria

1. WHEN a student creates a Lost_Report, THE Lost_Report_API SHALL accept description, category, location, and optional photo
2. WHEN a student creates a Lost_Report, THE Lost_Report_API SHALL validate all required fields
3. WHEN a student creates a Lost_Report, THE Lost_Report_API SHALL assign a unique Ticket_ID
4. WHEN a student creates a Lost_Report, THE Lost_Report_API SHALL set the initial status to "Lost"
5. WHEN a student requests their Lost_Reports, THE Lost_Report_API SHALL return all reports submitted by that student
6. WHEN a student requests their Lost_Reports, THE Lost_Report_API SHALL include Ticket_ID, description, category, status, and creation date
7. WHEN a student cancels a Lost_Report, THE Lost_Report_API SHALL update the status to "Cancelled"
8. WHEN a student cancels a Lost_Report, THE Lost_Report_API SHALL only allow cancellation if status is "Lost" or "Matched"

#### Requirement 18: View Matched Reports for Claiming

**User Story:** As a student, I want to view reports that have been matched with found items, so that I can claim my items.

#### Acceptance Criteria

1. WHEN a student requests matched reports, THE Lost_Report_API SHALL return Lost_Reports with status "Matched"
2. WHEN a student requests matched reports, THE Lost_Report_API SHALL include the associated Found_Item details
3. WHEN a student requests matched reports, THE Lost_Report_API SHALL include the Match confidence score
4. WHEN a student requests matched reports, THE Lost_Report_API SHALL include a link to claim the item
5. WHEN a student views a matched report, THE Lost_Report_API SHALL display the Found_Item photo and details

### STUDENT CLAIM HISTORY

#### Requirement 19: List Claimed Items with Reference ID and Claim Date

**User Story:** As a student, I want to view my claim history with reference IDs and claim dates, so that I can track my claimed items.

#### Acceptance Criteria

1. WHEN a student requests their claim history, THE Claim_API SHALL return all Claims submitted by that student
2. WHEN a student requests their claim history, THE Claim_API SHALL include Reference_ID, claim date, and status for each claim
3. WHEN a student requests their claim history, THE Claim_API SHALL include the Found_Item details (brand, color, category)
4. WHEN a student requests their claim history, THE Claim_API SHALL include the Lost_Report Ticket_ID
5. WHEN a student requests their claim history, THE Claim_API SHALL support sorting by claim date or status
6. WHEN a student requests their claim history, THE Claim_API SHALL support pagination

#### Requirement 20: View Release Confirmation

**User Story:** As a student, I want to view release confirmation for claimed items, so that I can verify that my item has been released.

#### Acceptance Criteria

1. WHEN a Claim is marked as "Resolved", THE Claim_API SHALL generate a release confirmation record
2. WHEN a Claim is marked as "Resolved", THE Claim_API SHALL include the Reference_ID, claim date, and resolution date
3. WHEN a Claim is marked as "Resolved", THE Claim_API SHALL include the Found_Item details and photo
4. WHEN a student requests a release confirmation, THE Claim_API SHALL return the confirmation details
5. WHEN a student requests a release confirmation, THE Claim_API SHALL include instructions for item pickup or delivery

### STUDENT HELP & SUPPORT

#### Requirement 21: Contact Directory API

**User Story:** As a student, I want to access a contact directory, so that I can reach out for help with lost and found items.

#### Acceptance Criteria

1. WHEN a student requests the contact directory, THE Support_API SHALL return contact information for support staff
2. WHEN a student requests the contact directory, THE Support_API SHALL include name, email, phone, and office location for each contact
3. WHEN a student requests the contact directory, THE Support_API SHALL include department or role information
4. WHEN a student requests the contact directory, THE Support_API SHALL include office hours if applicable

#### Requirement 22: Process Guide Data

**User Story:** As a student, I want to access a step-by-step process guide, so that I can understand how to use the lost and found system.

#### Acceptance Criteria

1. WHEN a student requests the process guide, THE Support_API SHALL return a structured guide with steps
2. WHEN a student requests the process guide, THE Support_API SHALL include sections for reporting lost items, searching found items, and claiming items
3. WHEN a student requests the process guide, THE Support_API SHALL include detailed instructions for each step
4. WHEN a student requests the process guide, THE Support_API SHALL include estimated time for each process
5. WHEN a student requests the process guide, THE Support_API SHALL include FAQs and troubleshooting tips
