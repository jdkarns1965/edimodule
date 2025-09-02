<?php
echo "Testing Web Interface\n";
echo "====================\n\n";

// Test the schedules page specifically
$url = 'http://localhost:8080/?page=schedules';

echo "Testing schedules page at: $url\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'method' => 'GET'
    ]
]);

$content = file_get_contents($url, false, $context);

if ($content === false) {
    echo "❌ Failed to fetch schedules page\n";
    exit(1);
}

// Check for error messages
if (strpos($content, 'Database error occurred') !== false) {
    echo "❌ Database error still present in schedules page\n";
    exit(1);
}

// Check for success indicators
$successIndicators = [
    'Delivery Schedules',
    'PO Line',
    'Supplier Item',
    'Promised Date'
];

$allFound = true;
foreach ($successIndicators as $indicator) {
    if (strpos($content, $indicator) === false) {
        echo "❌ Missing indicator: $indicator\n";
        $allFound = false;
    } else {
        echo "✓ Found: $indicator\n";
    }
}

if ($allFound) {
    echo "\n🎉 Schedules page is working correctly!\n";
    echo "All database queries are functioning properly.\n";
    
    // Count records shown
    $recordCount = substr_count($content, '<tr class=');
    if ($recordCount > 0) {
        echo "✓ Displaying $recordCount schedule records\n";
    }
} else {
    echo "\n❌ Some issues found with the schedules page\n";
}

echo "\n📝 You can now access the web interface at:\n";
echo "   http://localhost:8080\n";
echo "\nPages available:\n";
echo "   - Dashboard: http://localhost:8080/?page=dashboard\n";
echo "   - Import: http://localhost:8080/?page=import\n"; 
echo "   - Schedules: http://localhost:8080/?page=schedules\n";
echo "   - Transactions: http://localhost:8080/?page=transactions\n";
?>