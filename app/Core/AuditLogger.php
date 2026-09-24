<?php

namespace App\Core;

use PDO;
use Throwable;

class AuditLogger
{
    public function __construct(
        private PDO $db
    ) {}

    /**
     * Record an audit trail entry.
     */
    public function recordAuditTrail(
        string $setting,
        mixed $oldValue = 'None',
        mixed $newValue = 'None',
        string $changedBy = 'System',
        string $reason = 'System event'
    ): string {
        $id = 'audit_' . bin2hex(random_bytes(8));

        $now = date('c');

        $stmt = $this->db->prepare("
        INSERT INTO configuration_audit_trails (
            id,
            setting,
            old_value,
            new_value,
            changed_by,
            created_at,
            reason
        ) VALUES (
            :id,
            :setting,
            :old_value,
            :new_value,
            :changed_by,
            :created_at,
            :reason
        )
    ");

        $stmt->execute([
            ':id' =>
            $id,

            ':setting' =>
            $setting,

            ':old_value' =>
            $this->auditValue($oldValue),

            ':new_value' =>
            $this->auditValue($newValue),

            ':changed_by' =>
            $changedBy,

            ':created_at' =>
            $now,

            ':reason' =>
            $reason
        ]);

        return $id;
    }


    /**
     * Convert audit values to a storable string.
     */
    private function auditValue(mixed $value): string
    {
        if ($value === null) {
            return 'None';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            ) ?: 'None';
        }

        return (string) $value;
    }
}
