<?php
/**
 * CRE8.pw Comment Repository
 * 
 * Data access for comments table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Comment Repository
 */
class CommentRepository extends BaseRepository
{
    /**
     * Create a new comment
     * 
     * @param array<string, mixed> $data Comment data
     * @return void
     */
    public function create(array $data): void
    {
        $sql = "INSERT INTO comments (id, post_id, created_by_key_id, body) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            Ids::hex32ToBinary($data['post_id']),
            Ids::hex32ToBinary($data['created_by_key_id']),
            $data['body'],
        ]);
    }

    /**
     * Find comment by ID
     * 
     * @param string $commentIdHex32 Comment ID (hex32)
     * @return array|null Comment data or null if not found
     */
    public function findById(string $commentIdHex32): ?array
    {
        $sql = "SELECT * FROM comments WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($commentIdHex32)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'comment_id' => Ids::binaryToHex32($row['id']),
            'post_id' => Ids::binaryToHex32($row['post_id']),
            'created_by_key_id' => Ids::binaryToHex32($row['created_by_key_id']),
            'body' => $row['body'],
            'created_at' => $row['created_at'],
        ];
    }

    /**
     * Find comments by post
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param int $limit Limit
     * @param string|null $beforeIdHex32 Cursor (comment ID before this)
     * @return array<array> List of comments
     */
    public function findByPost(string $postIdHex32, int $limit = 20, ?string $beforeIdHex32 = null): array
    {
        $sql = "SELECT * FROM comments WHERE post_id = ?";
        $params = [Ids::hex32ToBinary($postIdHex32)];

        if ($beforeIdHex32 !== null) {
            $sql .= " AND created_at < (SELECT created_at FROM comments WHERE id = ?)";
            $params[] = Ids::hex32ToBinary($beforeIdHex32);
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $comments = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $comments[] = [
                'comment_id' => Ids::binaryToHex32($row['id']),
                'post_id' => Ids::binaryToHex32($row['post_id']),
                'created_by_key_id' => Ids::binaryToHex32($row['created_by_key_id']),
                'body' => $row['body'],
                'created_at' => $row['created_at'],
            ];
        }

        return $comments;
    }
}
