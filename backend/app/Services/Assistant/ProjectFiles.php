<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * Resolves and checks every path the assistant touches. Paths are always
 * relative to the project root, use forward slashes, and must stay inside the
 * configured readable / editable folders.
 */
class ProjectFiles
{
    private string $root;

    public function __construct()
    {
        $this->root = rtrim(str_replace('\\', '/', config('assistant.project_root')), '/');
    }

    public function root(): string
    {
        return $this->root;
    }

    /** Normalise a model-supplied path to "backend/app/Foo.php" form, rejecting traversal. */
    public function normalize(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = preg_replace('#^\./#', '', $path);
        if ($path === '' || str_starts_with($path, '/') || preg_match('#^[a-zA-Z]:#', $path)) {
            throw new RuntimeException('Use a path relative to the project root, e.g. backend/app/Models/Store.php');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new RuntimeException('Path may not contain "." or ".." segments.');
            }
        }

        return rtrim($path, '/');
    }

    public function absolute(string $relative): string
    {
        return $this->root.'/'.$relative;
    }

    public function assertReadable(string $relative): void
    {
        $this->assertNotBlocked($relative);
        if (! $this->within($relative, config('assistant.readable'))) {
            throw new RuntimeException("Not readable by the assistant: {$relative}");
        }
        $this->assertRealPathInside($relative);
    }

    public function assertEditable(string $relative): void
    {
        if (! config('assistant.allow_code_edits')) {
            throw new RuntimeException('Code edits are disabled. Set ASSISTANT_ALLOW_CODE_EDITS=true in backend/.env (local development only).');
        }
        $this->assertNotBlocked($relative);
        if ($this->within($relative, config('assistant.protected'))) {
            throw new RuntimeException("This file controls the assistant's own safeguards and can only be changed by hand: {$relative}");
        }
        if (! $this->within($relative, config('assistant.editable'))) {
            throw new RuntimeException("Not editable by the assistant: {$relative}. Editable folders: ".implode(', ', config('assistant.editable')));
        }
        $this->assertRealPathInside($relative);
    }

    /** Whether a path is visible at all (used to filter listings and search). */
    public function isReadable(string $relative): bool
    {
        try {
            $this->assertNotBlocked($relative);

            return $this->within($relative, config('assistant.readable'))
                || $this->isAncestorOfReadable($relative);
        } catch (RuntimeException) {
            return false;
        }
    }

    private function isAncestorOfReadable(string $relative): bool
    {
        foreach (config('assistant.readable') as $allowed) {
            if (str_starts_with($allowed.'/', $relative.'/')) {
                return true;
            }
        }

        return false;
    }

    private function within(string $relative, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($relative === $prefix || str_starts_with($relative, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function assertNotBlocked(string $relative): void
    {
        foreach (explode('/', $relative) as $segment) {
            foreach (config('assistant.blocked') as $blocked) {
                if ($segment === $blocked || ($blocked === '.env' && str_starts_with($segment, '.env'))) {
                    throw new RuntimeException("Access to {$relative} is blocked.");
                }
            }
        }
    }

    /** Guard against symlinks/junctions that point outside the project. */
    private function assertRealPathInside(string $relative): void
    {
        $probe = $this->absolute($relative);
        while (! file_exists($probe) && $probe !== $this->root) {
            $probe = dirname($probe);
        }
        $real = str_replace('\\', '/', (string) realpath($probe));
        if ($real === '' || ($real !== $this->root && ! str_starts_with(strtolower($real), strtolower($this->root).'/'))) {
            throw new RuntimeException("Path resolves outside the project: {$relative}");
        }
    }
}
