<?php

namespace Maharlika\Contracts\Storage;

interface Disk
{
    public function exists(string $path): bool;
    public function get(string $path): ?string;
    public function put(string $path, string $contents, array $options = []): bool;
    public function delete(string $path): bool;
    public function copy(string $from, string $to): bool;
    public function move(string $from, string $to): bool;
    public function size(string $path): int;
    public function lastModified(string $path): int;
    public function files(?string $directory = null): array;
    public function allFiles(?string $directory = null): array;
    public function directories(?string $directory = null): array;
    public function allDirectories(?string $directory = null): array;
    public function makeDirectory(string $path): bool;
    public function deleteDirectory(string $directory): bool;
    public function url(string $path): string;
    public function path(string $path): string;
}