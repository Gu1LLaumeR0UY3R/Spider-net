<?php

namespace App\Model;

final readonly class Incident
{
    public function __construct(
        public int $id,
        public int $typeId,
        public int $reporterId,
        public int $severity,
        public IncidentStatus $status,
        public float $latitude,
        public float $longitude,
        public ?float $altitude,
        public ?float $accuracy,
        public ?string $description,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}