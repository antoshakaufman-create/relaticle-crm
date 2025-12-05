<?php

declare(strict_types=1);

namespace App\Services\Integrations;

final class CompanySearchResult
{
    public function __construct(
        public readonly bool $isFound,
        public readonly array $data = [],
        public readonly ?string $error = null,
    ) {}

    public static function found(array $data): self
    {
        return new self(isFound: true, data: $data);
    }

    public static function notFound(?string $error = null): self
    {
        return new self(isFound: false, error: $error);
    }

    public function getData(): array
    {
        return $this->data;
    }
}



