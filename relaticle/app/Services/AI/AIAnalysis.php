<?php

declare(strict_types=1);

namespace App\Services\AI;

final class AIAnalysis
{
    public function __construct(
        public readonly int $credibilityScore,
        public readonly array $riskFactors = [],
        public readonly array $recommendations = [],
        public readonly float $confidence = 0.0,
    ) {}

    public function toArray(): array
    {
        return [
            'credibility_score' => $this->credibilityScore,
            'risk_factors' => $this->riskFactors,
            'recommendations' => $this->recommendations,
            'confidence' => $this->confidence,
        ];
    }
}



