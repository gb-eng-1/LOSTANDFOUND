<?php
/**
 * seed_student_reports.php
 * This script inserts dummy student reports into the database for testing.
 * Run this from your browser: http://localhost/LOSTANDFOUND/database/seed_student_reports.php
 */

require_once dirname(__DIR__) . '/config/database.php';

echo "<pre style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Helvetica,Arial,sans-serif,\"Apple Color Emoji\",\"Segoe UI Emoji\",\"Segoe UI Symbol\";line-height:1.6;'>";
echo "<h1>Seeding Student Reports...</h1>";

$dummyReports = [
    ['id' => 'REF-1001', 'ticket_id' => 'TIC-9982213434', 'category' => 'Miscellaneous', 'department' => 'CICT',  'id_num' => '2200931', 'contact_number' => '0998787698',  'date_lost' => '2024-03-23', 'matched' => true],
    ['id' => 'REF-1002', 'ticket_id' => 'TIC-9945647136', 'category' => 'Electronics',   'department' => 'CAS',   'id_num' => '2208756', 'contact_number' => '0998789869',  'date_lost' => '2024-02-11', 'matched' => true],
    ['id' => 'REF-1003', 'ticket_id' => 'TIC-9982213455', 'category' => 'Personal',      'department' => 'CAS',   'id_num' => '2200931', 'contact_number' => '09119003457', 'date_lost' => '2024-02-11', 'matched' => false],
    ['id' => 'REF-1004', 'ticket_id' => 'TIC-9986785321', 'category' => 'Personal',      'department' => 'CBAHM', 'id_num' => '1108752', 'contact_number' => '0998789869',  'date_lost' => '2023-08-01', 'matched' => false],
    ['id' => 'REF-1005', 'ticket_id' => 'TIC-998763567',  'category' => 'Apparels',      'department' => 'CENG',  'id_num' => '1167812', 'contact_number' => '09119003457', 'date_lost' => '2024-02-11', 'matched' => true],
    ['id' => 'REF-1006', 'ticket_id' => 'TIC-9986785329', 'category' => 'Personal',      'department' => 'CENG',  'id_num' => '1108752', 'contact_number' => '0998789869',  'date_lost' => '2023-08-01', 'matched' => false],
    ['id' => 'REF-1007', 'ticket_id' => 'TIC-1122334455', 'category' => 'Electronics',   'department' => 'CICT',  'id_num' => '2100123', 'contact_number' => '09123456789', 'date_lost' => '2024-03-15', 'matched' => true],
    ['id' => 'REF-1008', 'ticket_id' => 'TIC-2233445566', 'category' => 'Personal',      'department' => 'CNAHS', 'id_num' => '2000456', 'contact_number' => '09234567890', 'date_lost' => '2024-03-14', 'matched' => false],
    ['id' => 'REF-1009', 'ticket_id' => 'TIC-3344556677', 'category' => 'Document',      'department' => 'LAW',   'id_num' => '1900789', 'contact_number' => '09345678901', 'date_lost' => '2024-03-12', 'matched' => true],
    ['id' => 'REF-1010', 'ticket_id' => 'TIC-4455667788', 'category' => 'Apparels',      'department' => 'BED',   'id_num' => '2300999', 'contact_number' => '09456789012', 'date_lost' => '2024-03-10', 'matched' => false],
    ['id' => 'REF-1011', 'ticket_id' => 'TIC-5566778899', 'category' => 'Miscellaneous', 'department' => 'CBAHM', 'id_num' => '2201111', 'contact_number' => '09567890123', 'date_lost' => '2024-03-05', 'matched' => true],
    ['id' => 'REF-1012', 'ticket_id' => 'TIC-6677889900', 'category' => 'Electronics',   'department' => 'CENG',  'id_num' => '2102222', 'contact_number' => '09678901234', 'date_lost' => '2024-03-01', 'matched' => false],
    ['id' => 'REF-1013', 'ticket_id' => 'TIC-7788990011', 'category' => 'Personal',      'department' => 'CICT',  'id_num' => '2003333', 'contact_number' => '09789012345', 'date_lost' => '2024-02-28', 'matched' => true],
    ['id' => 'REF-1014', 'ticket_id' => 'TIC-8899001122', 'category' => 'Document',      'department' => 'CAS',   'id_num' => '1904444', 'contact_number' => '09890123456', 'date_lost' => '2024-02-25', 'matched' => false],
    ['id' => 'REF-1015', 'ticket_id' => 'TIC-9900112233', 'category' => 'Apparels',      'department' => 'CNAHS', 'id_num' => '2205555', 'contact_number' => '09901234567', 'date_lost' => '2024-02-20', 'matched' => true],
    ['id' => 'REF-1016', 'ticket_id' => 'TIC-0011223344', 'category' => 'Miscellaneous', 'department' => 'LAW',   'id_num' => '2106666', 'contact_number' => '09012345678', 'date_lost' => '2024-02-15', 'matched' => false],
];

$student_user_id = 'student@ub.edu.ph'; // The user_id for the test student

try {
    $pdo->beginTransaction();

    $delete_stmt = $pdo->prepare("DELETE FROM items WHERE id LIKE 'REF-10%' OR id LIKE 'UB-10%'");
    $delete_stmt->execute();
    echo "Cleared existing dummy reports (REF-10xx, UB-10xx).\n\n";

    $insert_item_stmt = $pdo->prepare(
        "INSERT INTO items (id, user_id, item_type, date_lost, item_description, status, matched_barcode_id, color, brand, date_encoded, created_at, updated_at) 
         VALUES (:id, :user_id, :item_type, :date_lost, :item_description, :status, :matched_barcode_id, :color, :brand, :date_encoded, NOW(), NOW())"
    );

    foreach ($dummyReports as $report) {
        $item_description = "Student Number: {$report['id_num']}\n" .
                            "Contact: {$report['contact_number']}\n" .
                            "Department: {$report['department']}";

        $matched_barcode_id = null;
        $status = 'Lost';

        if ($report['matched']) {
            // Create a corresponding found item
            $matched_barcode_id = 'UB-' . substr($report['id'], 4); // e.g., REF-1001 -> UB-1001
            $status = 'Matched';

            $found_item_params = [
                ':id' => $matched_barcode_id,
                ':user_id' => null,
                ':item_type' => $report['category'],
                ':date_lost' => null,
                ':item_description' => "A found {$report['category']}.",
                ':status' => 'Matched',
                ':matched_barcode_id' => null,
                ':color' => 'Various',
                ':brand' => 'Unknown',
                ':date_encoded' => $report['date_lost'],
            ];
            $insert_item_stmt->execute($found_item_params);
            echo "  -> Inserted matched found item: {$matched_barcode_id}\n";
        }

        $lost_report_params = [
            ':id' => $report['id'],
            ':user_id' => $student_user_id,
            ':item_type' => $report['category'],
            ':date_lost' => $report['date_lost'],
            ':item_description' => $item_description,
            ':status' => $status,
            ':matched_barcode_id' => $matched_barcode_id,
            ':color' => 'Various',
            ':brand' => 'Unknown',
            ':date_encoded' => null,
        ];
        $insert_item_stmt->execute($lost_report_params);
        echo "Inserted lost report: {$report['id']}\n";
    }

    $pdo->commit();
    echo "\n<h2>✅ Seeding complete!</h2>";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2>❌ An error occurred:</h2>";
    echo $e->getMessage();
}

echo "</pre>";

?>