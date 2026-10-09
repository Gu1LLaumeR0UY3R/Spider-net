<?php

namespace App\Dao;

use App\Model\Incident;
use App\Model\IncidentStatus;

interface IncidentDaoInterface
{
    public function create(
        int $typeId,
        int $reporterId,
        int $severity,
        float $latitude,
        float $longitude,
        ?float $altitude = null,
        ?float $accuracy = null,
        ?string $description = null,
    ): int;

    public function findById(int $id): ?Incident;

    /** @return Incident[] */
    public function findNearby(float $latitude, float $longitude, int $radiusMeters, int $limit = 100): array;

    public function changeStatus(int $incidentId, IncidentStatus $newStatus, int $userId, ?string $reason = null): void;
}