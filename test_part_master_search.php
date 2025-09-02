<?php
require_once 'bootstrap.php';
require_once 'src/config/database.php';
require_once 'src/classes/PartMaster.php';

use Greenfield\EDI\PartMaster;

echo "Testing Part Master Search Functionality\n";
echo "=======================================\n\n";

try {
    $db = DatabaseConfig::getInstance();
    $partMaster = new PartMaster($db);
    
    echo "1. Testing without search (should work):\n";
    try {
        $parts = $partMaster->getAllParts('', true, 5, 0);
        echo "   - Found " . count($parts) . " parts without search\n";
    } catch (Exception $e) {
        echo "   - Error without search: " . $e->getMessage() . "\n";
        echo "   - Error code: " . $e->getCode() . "\n";
    }
    
    echo "\n2. Testing with search term 'test':\n";
    try {
        $parts = $partMaster->getAllParts('test', true, 5, 0);
        echo "   - Found " . count($parts) . " parts with search 'test'\n";
    } catch (Exception $e) {
        echo "   - Error in search: " . $e->getMessage() . "\n";
        echo "   - Error code: " . $e->getCode() . "\n";
        echo "   - Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
    echo "\n3. Testing with part count search:\n";
    try {
        $count = $partMaster->getPartCount('test');
        echo "   - Part count with search 'test': " . $count . "\n";
    } catch (Exception $e) {
        echo "   - Error in count: " . $e->getMessage() . "\n";
        echo "   - Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
    echo "\n4. Testing with real part number:\n";
    try {
        $parts = $partMaster->getAllParts('20638', true, 5, 0);
        echo "   - Found " . count($parts) . " parts with search '20638'\n";
        if (count($parts) > 0) {
            echo "   - First result: " . $parts[0]['part_number'] . " - " . $parts[0]['description'] . "\n";
        }
    } catch (Exception $e) {
        echo "   - Error searching for '20638': " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Setup error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>