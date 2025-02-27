<?php

namespace perfectwebteam\spotlertransactional\models;

use Symfony\Contracts\HttpClient\ResponseInterface;

class CustomResponse implements ResponseInterface
{
    private $statusCode;
    private $headers;
    private $content;
    private $info;

    public function __construct(int $statusCode, array $headers, string $content, array $info = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->content = $content;
        $this->info = $info;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->headers;
    }

    public function getContent(bool $throw = true): string
    {
        return $this->content;
    }

    public function getInfo(?string $type = null): mixed
    {
        if ($type === null) {
            return $this->info;
        }

        return $this->info[$type] ?? null;
    }

    public function toArray(bool $throw = true): array
    {
        return json_decode($this->content, true);
    }

    public function cancel(): void {}
}
