<?php

declare(strict_types=1);

namespace App\Services\LeadValidation;

final class ValidationResult
{
    public function __construct(
        public readonly string $status,
        public readonly int $score,
        public readonly array $details = [],
        public readonly ?string $error = null,
        public readonly bool $isMock = false,
    ) {}

    public static function valid(string $message = '', array $details = []): self
    {
        return new self(
            status: 'valid',
            score: 100,
            details: array_merge(['message' => $message], $details),
        );
    }

    public static function invalid(string $error, array $details = []): self
    {
        return new self(
            status: 'invalid',
            score: 0,
            error: $error,
            details: $details,
        );
    }

    public static function suspicious(string $reason, int $score = 50, array $details = []): self
    {
        return new self(
            status: 'suspicious',
            score: $score,
            error: $reason,
            details: $details,
        );
    }

    public static function mock(string $reason, array $details = []): self
    {
        return new self(
            status: 'mock',
            score: 0,
            error: $reason,
            details: $details,
            isMock: true,
        );
    }

    public static function neutral(string $message = '', array $details = []): self
    {
        return new self(
            status: 'neutral',
            score: 50,
            details: array_merge(['message' => $message], $details),
        );
    }

    public static function score(int $score, array $details = []): self
    {
        if ($score >= 80) {
            return new self('valid', $score, $details);
        }

        if ($score >= 50) {
            return new self('suspicious', $score, $details);
        }

        return new self('invalid', $score, $details);
    }

    public static function combined(self ...$results): self
    {
        $totalScore = 0;
        $count = 0;
        $details = [];
        $errors = [];
        $hasMock = false;

        foreach ($results as $result) {
            if ($result->isMock) {
                $hasMock = true;
            }
            $totalScore += $result->score;
            $count++;
            $details = array_merge($details, $result->details);
            if ($result->error) {
                $errors[] = $result->error;
            }
        }

        $avgScore = $count > 0 ? (int) round($totalScore / $count) : 0;

        if ($hasMock) {
            return self::mock('Обнаружены mock данные', $details);
        }

        if ($avgScore >= 80) {
            return new self('valid', $avgScore, $details);
        }

        if ($avgScore >= 50) {
            return new self('suspicious', $avgScore, $details, implode('; ', $errors));
        }

        return new self('invalid', $avgScore, $details, implode('; ', $errors));
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getErrors(): array
    {
        return $this->error ? [$this->error] : [];
    }
}

