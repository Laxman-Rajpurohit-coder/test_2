<?php
$dir = new RecursiveDirectoryIterator(__DIR__ . '/../resources/js');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'jsx') {
        $content = file_get_contents($file->getPathname());
        if (str_contains($content, 'Filter Date Range') || str_contains($content, '30 Days')) {
            echo "FOUND IN FILE: " . $file->getPathname() . "\n";
        }
    }
}
