<?php
require_once '../config/database.php';
require_once '../config/app.php';

echo "<h1>EDI Application Database Test</h1>";

try {
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    
    echo "<p><strong>✅ Database Connection:</strong> Success</p>";
    
    // Test schedules query
    $totalSchedules = $conn->query("SELECT COUNT(*) as count FROM delivery_schedules WHERE status = 'active'")->fetch()['count'];
    echo "<p><strong>✅ Active Schedules:</strong> $totalSchedules</p>";
    
    // Test the complex join query from schedules page
    $sql = "
        SELECT ds.po_line, ds.supplier_item, ds.quantity_ordered, ds.promised_date, 
               tp.name as partner_name, sl.location_code
        FROM delivery_schedules ds
        LEFT JOIN trading_partners tp ON ds.partner_id = tp.id
        LEFT JOIN ship_to_locations sl ON ds.ship_to_location_id = sl.id
        WHERE ds.status = 'active'
        ORDER BY ds.promised_date ASC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $schedules = $stmt->fetchAll();
    
    echo "<p><strong>✅ Complex Query:</strong> " . count($schedules) . " records</p>";
    
    echo "<h2>Sample Records:</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>PO</th><th>Item</th><th>Qty</th><th>Date</th><th>Location</th></tr>";
    
    foreach (array_slice($schedules, 0, 10) as $s) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($s['po_line']) . "</td>";
        echo "<td>" . htmlspecialchars($s['supplier_item']) . "</td>";
        echo "<td>" . number_format($s['quantity_ordered']) . "</td>";
        echo "<td>" . htmlspecialchars($s['promised_date']) . "</td>";
        echo "<td>" . htmlspecialchars($s['location_code'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Navigation Links:</h2>";
    echo "<ul>";
    echo "<li><a href='index.php?page=dashboard'>Dashboard</a></li>";
    echo "<li><a href='index.php?page=import'>Import Data</a></li>";
    echo "<li><a href='index.php?page=schedules'>View All Schedules</a></li>";
    echo "<li><a href='index.php?page=transactions'>Transactions</a></li>";
    echo "</ul>";
    
    echo "<p><strong>🎉 Database fix successful!</strong> The schedules page should now work correctly.</p>";
    
} catch (Exception $e) {
    echo "<p><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>