# 🧪 Lost and Found System - Testing Guide

## 🔐 Login Credentials

### Admin Account
- **Email:** `admin@ub.edu.ph`
- **Password:** `admin123` (or `Admin` for legacy)
- **Access:** Full system administration

### Student Account
- **Email:** `student@ub.edu.ph` (or `students@ub.edu.ph` for legacy)
- **Password:** `student123` (or `Students` for legacy)
- **Access:** Student features only

## 🌐 API Base URL
```
http://localhost/LOSTANDFOUND/api
```

## 📋 Testing Checklist

### 1. Authentication Testing

#### Admin Login
```bash
curl -X POST http://localhost/LOSTANDFOUND/api/auth/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@ub.edu.ph", "password": "admin123"}'
```

#### Student Login
```bash
curl -X POST http://localhost/LOSTANDFOUND/api/auth/student/login \
  -H "Content-Type: application/json" \
  -d '{"email": "student@ub.edu.ph", "password": "student123"}'
```

### 2. Admin Dashboard Testing

#### Dashboard Statistics
```bash
curl -X GET http://localhost/LOSTANDFOUND/api/admin/dashboard/stats \
  -H "Cookie: PHPSESSID=your_admin_session_id"
```

#### Category Distribution
```bash
curl -X GET http://localhost/LOSTANDFOUND/api/admin/dashboard/categories \
  -H "Cookie: PHPSESSID=your_admin_session_id"
```

#### Activity Feed
```bash
curl -X GET http://localhost/LOSTANDFOUND/api/admin/dashboard/activity \
  -H "Cookie: PHPSESSID=your_admin_session_id"
```

### 3. Found Items Management

#### Create Found Item
```bash
curl -X POST http://localhost/LOSTANDFOUND/api/admin/items \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_admin_session_id" \
  -d '{
    "brand": "Apple",
    "color": "Silver",
    "item_type": "Electronics",
    "storage_location": "A1",
    "item_description": "MacBook Pro with stickers",
    "found_at": "Library",
    "found_by": "admin@ub.edu.ph"
  }'
```

#### List Found Items
```bash
curl -X GET "http://localhost/LOSTANDFOUND/api/admin/items?limit=10&offset=0" \
  -H "Cookie: PHPSESSID=your_admin_session_id"
```

### 4. Student Features Testing

#### Student Dashboard
```bash
curl -X GET http://localhost/LOSTANDFOUND/api/student/dashboard \
  -H "Cookie: PHPSESSID=your_student_session_id"
```

#### Create Lost Report
```bash
curl -X POST http://localhost/LOSTANDFOUND/api/student/reports \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_student_session_id" \
  -d '{
    "description": "Lost my silver MacBook Pro in the library",
    "category": "Electronics",
    "location": "Library",
    "color": "Silver",
    "brand": "Apple"
  }'
```

#### Search Unclaimed Items
```bash
curl -X GET "http://localhost/LOSTANDFOUND/api/student/items/unclaimed?limit=10" \
  -H "Cookie: PHPSESSID=your_student_session_id"
```

### 5. Support System Testing

#### Get Support Contacts (Public)
```bash
curl -X GET http://localhost/LOSTANDFOUND/api/support/contacts
```

#### Get Process Guides (Public)
```bash
curl -X GET http://localhost/LOSTANDFOUND/api/support/guides
```

## ⚠️ Important Testing Notes

### Input Validation Rules

#### Found Items
- **Brand:** Required, non-empty string
- **Color:** Required, non-empty string
- **Item Type:** Must be valid category (Electronics, Clothing, Accessories, Documents, Books, Sports, Personal)
- **Storage Location:** Required, non-empty string
- **Description:** Optional but recommended

#### Lost Reports
- **Description:** Required, minimum 10 characters
- **Category:** Must be valid category
- **Location:** Required, non-empty string
- **Color:** Optional but helps with matching
- **Brand:** Optional but helps with matching

#### Photo Uploads
- **Supported Formats:** JPEG, PNG, WebP
- **Maximum Size:** 10MB
- **Compression:** Automatic to 1920x1080, 80% quality

### Status Transitions

#### Item Status Flow
1. **Found** → **Matched** (when match approved)
2. **Matched** → **Claimed** (when claim submitted)
3. **Claimed** → **Resolved** (when claim approved)
4. **Resolved** → **Archived** (automatic)

#### Report Status Flow
1. **Lost** → **Matched** (when match found)
2. **Matched** → **Claimed** (when claim submitted)
3. **Lost/Matched** → **Cancelled** (student cancellation)

### Expected Response Format

All API responses follow this format:
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
  "details": [ /* validation errors if applicable */ ]
}
```

## 🚫 What NOT to Do

### Security Restrictions
- Don't try to access admin endpoints with student credentials
- Don't try to access other students' data
- Don't upload files larger than 10MB
- Don't use invalid file formats for photos

### Data Constraints
- Don't create items without required fields
- Don't use invalid categories
- Don't submit reports with descriptions shorter than 10 characters
- Don't try to claim items that are already claimed

### System Limitations
- Maximum 1000 items per page in listings
- Photo compression is automatic (cannot be disabled)
- Disposal deadline is automatically set to 30 days
- Barcode IDs are auto-generated (UB##### format)
- Reference IDs are auto-generated (REF-##### format)

## 🔍 Testing Scenarios

### Complete Workflow Test
1. **Admin:** Create a found item (e.g., silver MacBook)
2. **Student:** Create a lost report (matching description)
3. **System:** Automatic matching should occur
4. **Admin:** Check matches, approve the match
5. **Student:** Submit claim on matched item
6. **Admin:** Approve claim
7. **Admin:** Mark claim as resolved
8. **System:** Item should be automatically archived

### Error Testing
1. Try invalid login credentials
2. Try accessing admin endpoints as student
3. Try creating items with missing required fields
4. Try uploading oversized files
5. Try invalid category names

### Performance Testing
- Create multiple items and test pagination
- Test search with various filters
- Test concurrent claims on same item

## 📊 Sample Data

The system comes with:
- **4 Support Contacts** with complete information
- **3 Process Guides** (report_lost, search_found, claim_item)
- **Sample admin and student accounts**
- **Database optimized with 30+ indexes**

## 🛠️ Troubleshooting

### Common Issues
1. **Session Issues:** Make sure to save cookies from login response
2. **Path Issues:** Ensure correct API base URL
3. **Validation Errors:** Check required fields and formats
4. **Permission Errors:** Verify you're using correct user type

### Debug Endpoints
- `GET /api/auth/verify` - Check current session status
- All endpoints return detailed error messages for debugging

## 📈 Performance Expectations
- **Average Response Time:** < 50ms for most endpoints
- **Database Queries:** Optimized with indexes (< 13ms average)
- **File Uploads:** Automatic compression for photos
- **Concurrent Users:** System handles multiple simultaneous users

Happy testing! 🎉