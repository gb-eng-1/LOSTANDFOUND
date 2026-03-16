<?php
/**
 * Support Routes
 * 
 * GET /api/support/contacts
 * GET /api/support/guides
 * GET /api/support/guides/:section
 */

require_once __DIR__ . '/../helpers/Response.php';

function handleSupportRoute($method, $parts, $pdo) {
    // Contacts routes: /api/support/contacts
    if (isset($parts[1]) && $parts[1] === 'contacts') {
        if ($method === 'GET') {
            getContacts($pdo);
        }
        else {
            Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
    }
    
    // Guides routes: /api/support/guides
    elseif (isset($parts[1]) && $parts[1] === 'guides') {
        if ($method === 'GET' && isset($parts[2])) {
            // GET /api/support/guides/:section
            getGuidesBySection($pdo, $parts[2]);
        }
        elseif ($method === 'GET') {
            // GET /api/support/guides
            getAllGuides($pdo);
        }
        else {
            Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
    }
    
    else {
        Response::error('Support endpoint not found', 'NOT_FOUND', 404);
    }
}

/**
 * GET /api/support/contacts
 */
function getContacts($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM support_contacts ORDER BY department, role");
        $stmt->execute();
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        Response::success($contacts, 200);
    } catch (Exception $e) {
        Response::error('Failed to get contacts: ' . $e->getMessage(), 'QUERY_ERROR', 500);
    }
}

/**
 * GET /api/support/guides
 */
function getAllGuides($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT section, 
                   COUNT(*) as step_count,
                   MIN(estimated_time_minutes) as min_time,
                   MAX(estimated_time_minutes) as max_time,
                   SUM(estimated_time_minutes) as total_time
            FROM process_guides 
            GROUP BY section 
            ORDER BY section
        ");
        $stmt->execute();
        $guides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        Response::success($guides, 200);
    } catch (Exception $e) {
        Response::error('Failed to get guides: ' . $e->getMessage(), 'QUERY_ERROR', 500);
    }
}

/**
 * GET /api/support/guides/:section
 */
function getGuidesBySection($pdo, $section) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM process_guides 
            WHERE section = ? 
            ORDER BY step_number
        ");
        $stmt->execute([$section]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($steps)) {
            Response::error('Guide section not found', 'NOT_FOUND', 404);
            return;
        }
        
        // Parse JSON fields
        foreach ($steps as &$step) {
            if ($step['faq']) {
                $step['faq'] = json_decode($step['faq'], true);
            }
            if ($step['troubleshooting']) {
                $step['troubleshooting'] = json_decode($step['troubleshooting'], true);
            }
        }
        
        $response = [
            'section' => $section,
            'steps' => $steps,
            'total_steps' => count($steps),
            'estimated_total_time' => array_sum(array_column($steps, 'estimated_time_minutes'))
        ];
        
        Response::success($response, 200);
    } catch (Exception $e) {
        Response::error('Failed to get guide: ' . $e->getMessage(), 'QUERY_ERROR', 500);
    }
}

/**
 * Populate support tables with sample data
 */
function populateSupportData($pdo) {
    try {
        // Check if data already exists
        $contactCount = $pdo->query("SELECT COUNT(*) as count FROM support_contacts")->fetch()['count'];
        $guideCount = $pdo->query("SELECT COUNT(*) as count FROM process_guides")->fetch()['count'];
        
        if ($contactCount == 0) {
            // Insert sample contacts
            $contacts = [
                [
                    'name' => 'Maria Santos',
                    'email' => 'maria.santos@ub.edu.ph',
                    'phone' => '555-LOST (5678)',
                    'office_location' => 'Student Services Building, Room 101',
                    'department' => 'Lost & Found',
                    'role' => 'Manager',
                    'office_hours' => 'Monday-Friday 8:00 AM - 5:00 PM'
                ],
                [
                    'name' => 'John Cruz',
                    'email' => 'john.cruz@ub.edu.ph',
                    'phone' => '555-HELP (4357)',
                    'office_location' => 'Student Services Building, Room 102',
                    'department' => 'Lost & Found',
                    'role' => 'Assistant',
                    'office_hours' => 'Monday-Friday 9:00 AM - 4:00 PM'
                ],
                [
                    'name' => 'Anna Reyes',
                    'email' => 'anna.reyes@ub.edu.ph',
                    'phone' => '555-INFO (4636)',
                    'office_location' => 'Library Information Desk',
                    'department' => 'Library Services',
                    'role' => 'Information Specialist',
                    'office_hours' => 'Monday-Saturday 7:00 AM - 9:00 PM'
                ],
                [
                    'name' => 'Security Office',
                    'email' => 'security@ub.edu.ph',
                    'phone' => '555-SAFE (7233)',
                    'office_location' => 'Main Gate Security Office',
                    'department' => 'Campus Security',
                    'role' => 'Security Team',
                    'office_hours' => '24/7'
                ]
            ];
            
            $insertContact = $pdo->prepare("
                INSERT INTO support_contacts (name, email, phone, office_location, department, role, office_hours)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($contacts as $contact) {
                $insertContact->execute([
                    $contact['name'],
                    $contact['email'],
                    $contact['phone'],
                    $contact['office_location'],
                    $contact['department'],
                    $contact['role'],
                    $contact['office_hours']
                ]);
            }
        }
        
        if ($guideCount == 0) {
            // Insert sample process guides
            $guides = [
                // Report Lost Item Guide
                [
                    'title' => 'How to Report a Lost Item',
                    'section' => 'report_lost',
                    'step_number' => 1,
                    'instruction' => 'Log in to your student account on the Lost & Found system.',
                    'estimated_time_minutes' => 2,
                    'faq' => json_encode([
                        ['question' => 'What if I forgot my password?', 'answer' => 'Use the password reset link on the login page or contact IT support.'],
                        ['question' => 'Can I report without logging in?', 'answer' => 'No, you need to log in to track your reports and receive notifications.']
                    ]),
                    'troubleshooting' => json_encode([
                        ['issue' => 'Cannot access login page', 'solution' => 'Clear your browser cache or try a different browser.'],
                        ['issue' => 'Account locked', 'solution' => 'Contact the registrar office to unlock your account.']
                    ])
                ],
                [
                    'title' => 'Fill Out Lost Item Report',
                    'section' => 'report_lost',
                    'step_number' => 2,
                    'instruction' => 'Click "Report Lost Item" and fill out the form with detailed information about your lost item including description, category, location where lost, and any identifying features.',
                    'estimated_time_minutes' => 5,
                    'faq' => json_encode([
                        ['question' => 'How detailed should my description be?', 'answer' => 'Include brand, color, size, unique markings, and any contents. More details help with matching.'],
                        ['question' => 'What if I\'m not sure where I lost it?', 'answer' => 'List all possible locations where you remember having the item.']
                    ]),
                    'troubleshooting' => json_encode([
                        ['issue' => 'Form won\'t submit', 'solution' => 'Check that all required fields are filled and description is at least 10 characters.'],
                        ['issue' => 'Category not available', 'solution' => 'Choose the closest category or select "Other" and specify in the description.']
                    ])
                ],
                [
                    'title' => 'Submit Report and Get Ticket ID',
                    'section' => 'report_lost',
                    'step_number' => 3,
                    'instruction' => 'Review your information and submit the report. You will receive a unique Ticket ID (REF-#####) for tracking your report.',
                    'estimated_time_minutes' => 1,
                    'faq' => json_encode([
                        ['question' => 'What is the Ticket ID for?', 'answer' => 'Use this ID to track your report status and reference it when contacting support.'],
                        ['question' => 'Can I edit my report after submitting?', 'answer' => 'Yes, you can update your report details as long as it hasn\'t been matched yet.']
                    ]),
                    'troubleshooting' => json_encode([
                        ['issue' => 'Didn\'t receive Ticket ID', 'solution' => 'Check your dashboard or contact support with your email address.']
                    ])
                ],
                
                // Search Found Items Guide
                [
                    'title' => 'Access Found Items Search',
                    'section' => 'search_found',
                    'step_number' => 1,
                    'instruction' => 'Navigate to the "Search Found Items" section from your dashboard or main menu.',
                    'estimated_time_minutes' => 1,
                    'faq' => json_encode([
                        ['question' => 'Do I need to log in to search?', 'answer' => 'Yes, login is required to access the found items database.']
                    ]),
                    'troubleshooting' => json_encode([
                        ['issue' => 'Search page not loading', 'solution' => 'Refresh the page or check your internet connection.']
                    ])
                ],
                [
                    'title' => 'Use Search Filters',
                    'section' => 'search_found',
                    'step_number' => 2,
                    'instruction' => 'Use the search filters to narrow down results by category, keyword (brand/color), location, and date range. You can also browse all unclaimed items.',
                    'estimated_time_minutes' => 3,
                    'faq' => json_encode([
                        ['question' => 'How often are new items added?', 'answer' => 'Found items are added daily as they are processed by staff.'],
                        ['question' => 'What does "disposal deadline" mean?', 'answer' => 'Items are disposed of after 30 days if unclaimed.']
                    ]),
                    'troubleshooting' => json_encode([
                        ['issue' => 'No results found', 'solution' => 'Try broader search terms or remove some filters.'],
                        ['issue' => 'Images not loading', 'solution' => 'Some items may not have photos. Contact staff for visual confirmation.']
                    ])
                ],
                [
                    'title' => 'Review Item Details',
                    'section' => 'search_found',
                    'step_number' => 3,
                    'instruction' => 'Click on items that might be yours to view detailed information, photos, and location where found.',
                    'estimated_time_minutes' => 2,
                    'faq' => json_encode([
                        ['question' => 'Can I see all item details?', 'answer' => 'Some details may be hidden to prevent false claims. Contact staff if you need more information.']
                    ]),
                    'troubleshooting' => json_encode([
                        ['issue' => 'Item details won\'t open', 'solution' => 'Try refreshing the page or use a different browser.']
                    ])
                ],
                
                // Claim Item Guide
                [
                    'title' => 'Identify Your Item',
                    'section' => 'claim_item',
                    'step_number' => 1,
                    'instruction' => 'When you find an item that might be yours, carefully review all details and photos to confirm it matches your lost item.',
                    'estimated_time_minutes' => 2,
                    'faq' => json_encode([
                        ['question' => 'What if I\'m not 100% sure?', 'answer' => 'Only claim items you are certain belong to you. False claims may result in account restrictions.'],
                        ['question' => 'Can I claim multiple items?', 'answer' => 'Yes, but each claim must be for a different lost item report.']
                    ]),
                    'troubleshooting' => json_encode([
                        ['issue' => 'Item looks similar but not identical', 'solution' => 'Contact staff for additional verification before claiming.']
                    ])
                ],
                [
                    'title' => 'Submit Claim with Proof',
                    'section' => 'claim_item',
                    'step_number' => 2,
                    'instruction' => 'Click "Claim This Item" and provide detailed proof of ownership including description of unique features, photos if available, and any other identifying information.',
                    'estimated_time_minutes' => 5,
                    'faq' => json_encode([
                        ['question' => 'What kind of proof is needed?', 'answer' => 'Describe unique features, provide photos, receipts, or any other evidence of ownership.'],
                        ['question' => 'How long does verification take?', 'answer' => 'Claims are typically reviewed within 1-2 business days.']
                    ]),
                    'troubleshooting' => json_encode([
                        ['issue' => 'Cannot upload proof photo', 'solution' => 'Ensure image is under 10MB and in JPEG, PNG, or WebP format.'],
                        ['issue' => 'Claim form won\'t submit', 'solution' => 'Check that proof description is at least 10 characters long.']
                    ])
                ],
                [
                    'title' => 'Wait for Approval and Pickup',
                    'section' => 'claim_item',
                    'step_number' => 3,
                    'instruction' => 'After submitting your claim, wait for staff approval. Once approved, you will receive pickup instructions with your claim reference ID.',
                    'estimated_time_minutes' => 1,
                    'faq' => json_encode([
                        ['question' => 'How will I know if my claim is approved?', 'answer' => 'You will receive a notification and can check your claim status in your dashboard.'],
                        ['question' => 'What do I need to bring for pickup?', 'answer' => 'Bring your student ID, claim reference ID, and any additional proof requested.']
                    ]),
                    'troubleshooting' => json_encode([
                        ['issue' => 'Claim was rejected', 'solution' => 'Review the rejection reason and contact staff if you believe there was an error.'],
                        ['issue' => 'Pickup instructions unclear', 'solution' => 'Contact the Lost & Found office for clarification.']
                    ])
                ]
            ];
            
            $insertGuide = $pdo->prepare("
                INSERT INTO process_guides (title, section, step_number, instruction, estimated_time_minutes, faq, troubleshooting)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($guides as $guide) {
                $insertGuide->execute([
                    $guide['title'],
                    $guide['section'],
                    $guide['step_number'],
                    $guide['instruction'],
                    $guide['estimated_time_minutes'],
                    $guide['faq'],
                    $guide['troubleshooting']
                ]);
            }
        }
        
        return true;
    } catch (Exception $e) {
        throw new Exception("Failed to populate support data: " . $e->getMessage());
    }
}
?>
