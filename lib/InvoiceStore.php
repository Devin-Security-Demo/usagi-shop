<?php

declare(strict_types=1);

namespace Usagi;

final class InvoiceStore
{
    public function __construct(private readonly string $directory)
    {
    }

    public function path(?string $filename): ?string
    {
        if ($filename === null || $filename === '' || basename($filename) !== $filename
            || !preg_match('/\A[A-Za-z0-9_-]+\.pdf\z/', $filename)) {
            return null;
        }

        $base = realpath($this->directory);
        $path = realpath($this->directory . '/' . $filename);
        if ($base === false || $path === false || !is_file($path)
            || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }
}
