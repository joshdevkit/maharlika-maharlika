<?php

namespace Maharlika\Http\Concerns;

use Symfony\Component\HttpFoundation\File\UploadedFile;

trait InteractsWithFiles
{
    /**
     * Get all uploaded files from the request.
     *
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files->all();
    }


    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->file($key);

        if (!$file || is_array($file)) {
            return null;
        }

        return file_get_contents($file->getRealPath());
    }
    /**
     * Get a specific uploaded file by name.
     *
     * @param  string  $key
     * @return UploadedFile|UploadedFile[]|array|null
     */
    public function file(string $key): UploadedFile|array|null
    {
        return $this->files->get($key);
    }

    /**
     * Determine if the uploaded data contains a file.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        if (!$this->files->has($key)) {
            return false;
        }

        $file = $this->file($key);

        if (is_array($file)) {
            return !empty(array_filter($file, fn($f) => $this->isValidFile($f)));
        }

        return $this->isValidFile($file);
    }

    /**
     * Check that the given file is a valid file instance.
     *
     * @param  mixed  $file
     * @return bool
     */
    protected function isValidFile(mixed $file): bool
    {
        return $file instanceof UploadedFile && $file->isValid();
    }

    /**
     * Retrieve a file from the request and validate it.
     *
     * @param  string  $key
     * @param  array   $rules  Simple validation rules
     * @return UploadedFile|array|null
     */
    public function validatedFile(string $key, array $rules = []): UploadedFile|array|null
    {
        if (!$this->hasFile($key)) {
            return null;
        }

        $file = $this->file($key);

        if (is_array($file)) {
            return array_filter($file, fn($f) => $this->validateFile($f, $rules));
        }

        return $this->validateFile($file, $rules) ? $file : null;
    }

    /**
     * Validate a single file against rules.
     *
     * @param  UploadedFile  $file
     * @param  array  $rules
     * @return bool
     */
    protected function validateFile(UploadedFile $file, array $rules): bool
    {
        if (!$this->isValidFile($file)) {
            return false;
        }

        // Max size validation (in kilobytes)
        if (isset($rules['max'])) {
            if ($file->getSize() > $rules['max'] * 1024) {
                return false;
            }
        }

        // MIME type validation
        if (isset($rules['mimes'])) {
            $mimes = is_array($rules['mimes']) ? $rules['mimes'] : [$rules['mimes']];
            if (!in_array($file->getMimeType(), $mimes)) {
                return false;
            }
        }

        // Extension validation
        if (isset($rules['extensions'])) {
            $extensions = is_array($rules['extensions']) ? $rules['extensions'] : [$rules['extensions']];
            if (!in_array($file->getClientOriginalExtension(), $extensions)) {
                return false;
            }
        }

        // Image validation
        if (isset($rules['image']) && $rules['image']) {
            $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/svg+xml', 'image/webp'];
            if (!in_array($file->getMimeType(), $imageMimes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the file's size in kilobytes.
     *
     * @param  string  $key
     * @return int|null
     */
    public function fileSize(string $key): ?int
    {
        if (!$this->hasFile($key)) {
            return null;
        }

        $file = $this->file($key);

        if (is_array($file)) {
            return null;
        }

        return (int) ceil($file->getSize() / 1024);
    }

    /**
     * Get the file's MIME type.
     *
     * @param  string  $key
     * @return string|null
     */
    public function fileMimeType(string $key): ?string
    {
        if (!$this->hasFile($key)) {
            return null;
        }

        $file = $this->file($key);

        if (is_array($file)) {
            return null;
        }

        return $file->getMimeType();
    }

    /**
     * Get the file's extension.
     *
     * @param  string  $key
     * @return string|null
     */
    public function fileExtension(string $key): ?string
    {
        if (!$this->hasFile($key)) {
            return null;
        }

        $file = $this->file($key);

        if (is_array($file)) {
            return null;
        }

        return $file->getClientOriginalExtension();
    }
}
