<?php
/**
 * CRE8.pw Audit Event Repository
 * 
 * Data access for audit_events table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Audit Event Repository
 */
class AuditEventRepository extends BaseRepository
{
    /**
     * Create a new audit event
     * 
     * @param array<string, mixed> $data Event data
     * @return void
     */
    public function create(array $data): void
    {
        $sql = "INSERT INTO audit_events (
            id, actor_type, actor_id, action, subject_type, subject_id,
            metadata_json, ip, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            $data['actor_type'],
            Ids::hex32ToBinary($data['actor_id']),
            $data['action'],
            $data['subject_type'] ?? null,
            isset($data['subject_id']) ? Ids::hex32ToBinary($data['subject_id']) : null,
            isset($data['metadata']) ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null,
            $data['ip'] ?? null,
            $data['user_agent'] ?? null,
        ]);
    }

    /**
     * Find events by actor
     * 
     * @param string $actorType Actor type ('owner' or 'key')
     * @param string $actorIdHex32 Actor ID (hex32)
     * @param int $limit Limit
     * @return array<array> List of audit events
     */
    public function findByActor(string $actorType, string $actorIdHex32, int $limit = 100): array
    {
        $sql = "SELECT * FROM audit_events 
                WHERE actor_type = ? AND actor_id = ? 
                ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $actorType,
            Ids::hex32ToBinary($actorIdHex32),
            $limit,
        ]);

        $events = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $events[] = $this->mapRowToArray($row);
        }

        return $events;
    }

    /**
     * Find events by subject
     * 
     * @param string $subjectType Subject type
     * @param string $subjectIdHex32 Subject ID (hex32)
     * @param int $limit Limit
     * @return array<array> List of audit events
     */
    public function findBySubject(string $subjectType, string $subjectIdHex32, int $limit = 100): array
    {
        $sql = "SELECT * FROM audit_events 
                WHERE subject_type = ? AND subject_id = ? 
                ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $subjectType,
            Ids::hex32ToBinary($subjectIdHex32),
            $limit,
        ]);

        $events = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $events[] = $this->mapRowToArray($row);
        }

        return $events;
    }

    /**
     * Map database row to array with hex32 IDs
     * 
     * @param array<string, mixed> $row Database row
     * @return array<string, mixed> Mapped array
     */
    private function mapRowToArray(array $row): array
    {
        $result = [
            'event_id' => Ids::binaryToHex32($row['id']),
            'actor_type' => $row['actor_type'],
            'actor_id' => Ids::binaryToHex32($row['actor_id']),
            'action' => $row['action'],
            'created_at' => $row['created_at'],
            'ip' => $row['ip'],
            'user_agent' => $row['user_agent'],
        ];

        if ($row['subject_type']) {
            $result['subject_type'] = $row['subject_type'];
        }
        if ($row['subject_id']) {
            $result['subject_id'] = Ids::binaryToHex32($row['subject_id']);
        }
        if ($row['metadata_json']) {
            $result['metadata'] = json_decode($row['metadata_json'], true);
        }

        return $result;
    }
}
