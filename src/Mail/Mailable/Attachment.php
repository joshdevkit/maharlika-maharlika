<?php

namespace Maharlika\Mail\Mailable;

class Attachment
{
    protected ?string $file = null;
    protected ?string $data = null;
    protected ?string $name = null;
    protected ?string $mime = null;

    public static function fromPath(string $path, ?string $name = null, ?string $mime = null): self
    {
        $instance = new self();
        $instance->file = $path;
        $instance->name = $name;
        $instance->mime = $mime;
        return $instance;
    }

    public static function fromData(callable|string $data, string $name, ?string $mime = null): self
    {
        $instance = new self();
        $instance->data = is_callable($data) ? $data() : $data;
        $instance->name = $name;
        $instance->mime = $mime;
        return $instance;
    }

    public function as(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function withMime(string $mime): self
    {
        $this->mime = $mime;
        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function isFromPath(): bool
    {
        return $this->file !== null;
    }
}
