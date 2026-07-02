<?php
$files = glob(__DIR__ . '/../bootstrap/cache/*.php');
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
        echo "Deleted " . basename($file) . "<br>";
    }
}
echo "Done clearing bootstrap/cache";
