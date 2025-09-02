<?php
require_once 'bootstrap.php';
require_once 'src/config/database.php';

$db = DatabaseConfig::getInstance()->getConnection();

echo "Debug: Testing SQL queries directly\n";
echo "====================================\n\n";

// Test 1: Without search
echo "1. Testing SQL without search:\n";
$sql1 = "SELECT * FROM part_master WHERE 1=1 AND active = 1 ORDER BY part_number ASC LIMIT :limit OFFSET :offset";
echo "   SQL: $sql1\n";
$params1 = [':limit' => 5, ':offset' => 0];
echo "   Params: " . print_r($params1, true) . "\n";

try {
    $stmt1 = $db->prepare($sql1);
    foreach ($params1 as $key => $value) {
        $stmt1->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt1->execute();
    $results1 = $stmt1->fetchAll();
    echo "   - Success: Found " . count($results1) . " results\n";
} catch (Exception $e) {
    echo "   - Error: " . $e->getMessage() . "\n";
}

echo "\n2. Testing SQL with search:\n";
$sql2 = "SELECT * FROM part_master WHERE 1=1 AND active = 1 AND (part_number LIKE :search OR customer_part_number LIKE :search OR description LIKE :search) ORDER BY part_number ASC LIMIT :limit OFFSET :offset";
echo "   SQL: $sql2\n";
$params2 = [':search' => '%test%', ':limit' => 5, ':offset' => 0];
echo "   Params: " . print_r($params2, true) . "\n";

try {
    $stmt2 = $db->prepare($sql2);
    foreach ($params2 as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt2->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt2->bindValue($key, $value);
        }
    }
    $stmt2->execute();
    $results2 = $stmt2->fetchAll();
    echo "   - Success: Found " . count($results2) . " results\n";
} catch (Exception $e) {
    echo "   - Error: " . $e->getMessage() . "\n";
}

echo "\n3. Testing COUNT query:\n";
$sql3 = "SELECT COUNT(*) as total FROM part_master WHERE 1=1 AND active = 1 AND (part_number LIKE :search OR customer_part_number LIKE :search OR description LIKE :search)";
echo "   SQL: $sql3\n";
$params3 = [':search' => '%test%'];
echo "   Params: " . print_r($params3, true) . "\n";

try {
    $stmt3 = $db->prepare($sql3);
    foreach ($params3 as $key => $value) {
        $stmt3->bindValue($key, $value);
    }
    $stmt3->execute();
    $result3 = $stmt3->fetch();
    echo "   - Success: Count = " . $result3['total'] . "\n";
} catch (Exception $e) {
    echo "   - Error: " . $e->getMessage() . "\n";
}
?>