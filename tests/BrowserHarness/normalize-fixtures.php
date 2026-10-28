<?php
// Repository fixtures carry byte hashes. Normalize checkout CRLF only inside the test image.
foreach (['agent-kit/examples', 'fixtures'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && (in_array($file->getExtension(), ['json', 'js', 'twig', 'css', 'txt', 'md', 'yaml', 'yml'], true) || $file->getBasename() === 'LICENSE')) {
            $bytes = file_get_contents($file->getPathname());
            if (str_contains($bytes, "\r\n")) file_put_contents($file->getPathname(), str_replace("\r\n", "\n", $bytes));
        }
    }
}
