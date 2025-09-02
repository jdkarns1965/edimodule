<?php
require_once 'src/config/database.php';
require_once 'src/config/app.php';

echo "Debugging Schedules Page\n";
echo "========================\n\n";

try {
    // Test database connection
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    echo "✓ Database connection successful\n";
    
    // Test basic query
    $result = $conn->query("SELECT COUNT(*) as count FROM delivery_schedules")->fetch();
    echo "✓ Total delivery schedules: " . $result['count'] . "\n";
    
    // Test the exact query from schedules.php
    $page = 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $search = '';
    $status = '';
    $partner = '';
    
    $whereConditions = ["1=1"];
    $params = [];
    
    if ($search) {
        $whereConditions[] = "(ds.po_number LIKE ? OR ds.supplier_item LIKE ? OR ds.item_description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%"; 
        $params[] = "%$search%";
    }
    
    if ($status) {
        $whereConditions[] = "ds.status = ?";
        $params[] = $status;
    }
    
    if ($partner) {
        $whereConditions[] = "ds.partner_id = ?";
        $params[] = $partner;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    echo "✓ Where clause: $whereClause\n";
    
    // Test count query
    $countSql = "SELECT COUNT(*) as total FROM delivery_schedules ds WHERE $whereClause";
    echo "✓ Count SQL: $countSql\n";
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    echo "✓ Total records found: $totalRecords\n";
    
    // Test main query
    $sql = "
        SELECT ds.*, tp.name as partner_name, sl.location_description, sl.location_code
        FROM delivery_schedules ds
        LEFT JOIN trading_partners tp ON ds.partner_id = tp.id
        LEFT JOIN ship_to_locations sl ON ds.ship_to_location_id = sl.id
        WHERE $whereClause
        ORDER BY ds.promised_date ASC, ds.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    echo "✓ Main SQL query:\n$sql\n\n";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
    
    echo "✓ Query executed successfully\n";
    echo "✓ Records returned: " . count($schedules) . "\n";
    
    if (count($schedules) > 0) {
        $first = $schedules[0];
        echo "✓ First record:\n";
        echo "  - PO: " . $first['po_line'] . "\n";
        echo "  - Item: " . $first['supplier_item'] . "\n";
        echo "  - Partner: " . $first['partner_name'] . "\n";
        echo "  - Location: " . $first['location_description'] . "\n";
    }
    
    // Test partners query
    $partners = $conn->query("SELECT id, name FROM trading_partners WHERE status = 'active' ORDER BY name")->fetchAll();
    echo "✓ Active partners found: " . count($partners) . "\n";
    
    echo "\n🎉 All database queries working correctly!\n";
    echo "The issue might be in the web interface error handling.\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>