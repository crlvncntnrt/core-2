<?php
require_once(__DIR__ . '/initialize_coreT2.php');
header('Content-Type: text/plain');

echo "--- Database Connection ---\n";
if (isset($conn) && !$conn->connect_error) {
    echo "Connected successfully to " . DB_NAME . "\n";
} else {
    echo "Connection failed: " . ($conn->connect_error ?? 'Unknown error') . "\n";
    exit;
}

echo "\n--- Tables Check ---\n";
$tables = ['audit_trail', 'audit_trial', 'compliance_logs', 'users'];
foreach ($tables as $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    if ($res->num_rows > 0) {
        echo "Table '$table' exists.\n";
        
        // Explain columns
        echo "Columns for '$table':\n";
        $columns = $conn->query("DESCRIBE $table");
        while ($col = $columns->fetch_assoc()) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "Table '$table' DOES NOT exist.\n";
    }
    echo "\n";
}

echo "--- TCPDF Check ---\n";
$tcpdfPaths = [
    __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
    __DIR__ . '/libs/tcpdf/tcpdf.php',
    __DIR__ . '/admin/libs/tcpdf/tcpdf.php',
    __DIR__ . '/admin/Compliance-Audith-Trail-System/libs/tcpdf/tcpdf.php',
];

foreach ($tcpdfPaths as $path) {
    if (file_exists($path)) {
        echo "TCPDF found at: $path\n";
        require_once($path);
        if (class_exists('TCPDF')) {
            echo "TCPDF class loaded successfully.\n";
        } else {
            echo "TCPDF class NOT found after include.\n";
        }
    } else {
        echo "TCPDF NOT found at: $path\n";
    }
}

if (!class_exists('TCPDF')) {
    echo "TCPDF is still NOT loaded.\n";
}
?>
