<?php
$dir = new RecursiveDirectoryIterator(__DIR__ . '/../app');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (str_contains($content, "Storage::disk('public')") || str_contains($content, 'storage_path')) {
            echo "STORAGE PATH IN: " . $file->getPathname() . "\n";
            preg_match_all('/(Storage::disk\([^)]+\)->put[^\n]+|storage_path\([^)]+\))/i', $content, $matches);
            foreach ($matches[0] as $m) {
                echo "   --> " . trim($m) . "\n";
            }
        }
    }
}
