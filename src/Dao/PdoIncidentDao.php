<?php

namespace App\Dao;

use App\Model\Incident;
use App\Model\IncidentStatus;

final class PdoIncidentDao implements IncidentDaoInterface
{
    private const COLUMNS = <<<'SQL'
        i.id, i.type_id, i.reporter_id, i.severity, i.status, i.description,
        i.altitude_m, i.accuracy_m, i.created_at,
        ST_Y(i.location::geometry) AS lat, ST_X(i.location::geometry) AS lng
        SQL;

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function create(
        int $typeId,
        int $reporterId,
        int $severity,
        float $latitude,
        float $longitude,
        ?float $altitude = null,
        ?float $accuracy = null,
        ?string $description = null,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO incident (type_id, reporter_id, severity, description, location, altitude_m, accuracy_m)
             VALUES (:type_id, :reporter_id, :severity, :description,
                     ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography, :altitude, :accuracy)
             RETURNING id'
        );
        $stmt->execute([
            'type_id' => $typeId,
            'reporter_id' => $reporterId,
            'severity' => $severity,
            'description' => $description,
            'lng' => $longitude,
            'lat' => $latitude,
            'altitude' => $altitude,
            'accuracy' => $accuracy,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?Incident
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM incident i WHERE i.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findNearby(float $latitude, float $longitude, int $radiusMeters, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'WITH p AS (SELECT ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography AS g)
             SELECT ' . self::COLUMNS . '
             FROM incident i, p
             WHERE ST_DWithin(i.location, p.g, :radius)
               AND i.status <> \'false_alert\'
             ORDER BY ST_Distance(i.location, p.g)
             LIMIT :max'
        );
        $stmt->execute([
            'lng' => $longitude,
            'lat' => $latitude,
            'radius' => $radiusMeters,
            'max' => $limit,
        ]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function changeStatus(int $incidentId, IncidentStatus $newStatus, int $userId, ?string $reason = null): void
    {
        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare('SELECT status FROM incident WHERE id = :id FOR UPDATE');
            $select->execute(['id' => $incidentId]);
            $old = $select->fetchColumn();
            if ($old === false) {
                throw new \RuntimeException(sprintf('Incident %d introuvable', $incidentId));
            }

            $update = $this->pdo->prepare(
                'UPDATE incident
                 SET status = :status, reviewed_by = :user_id, reviewed_at = now(),
                     closed_at = CASE WHEN CAST(:is_closed AS boolean) THEN now() ELSE closed_at END
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $newStatus->value,
                'user_id' => $userId,
                'is_closed' => $newStatus === IncidentStatus::Resolved ? 'true' : 'false',
                'id' => $incidentId,
            ]);

            $history = $this->pdo->prepare(
                'INSERT INTO incident_status_history (incident_id, old_status, new_status, changed_by, reason)
                 VALUES (:incident_id, :old_status, :new_status, :changed_by, :reason)'
            );
            $history->execute([
                'incident_id' => $incidentId,
                'old_status' => $old,
                'new_status' => $newStatus->value,
                'changed_by' => $userId,
                'reason' => $reason,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Incident
    {
        return new Incident(
            id: (int) $row['id'],
            typeId: (int) $row['type_id'],
            reporterId: (int) $row['reporter_id'],
            severity: (int) $row['severity'],
            status: IncidentStatus::from($row['status']),
            latitude: (float) $row['lat'],
            longitude: (float) $row['lng'],
            altitude: $row['altitude_m'] === null ? null : (float) $row['altitude_m'],
            accuracy: $row['accuracy_m'] === null ? null : (float) $row['accuracy_m'],
            description: $row['description'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }
}