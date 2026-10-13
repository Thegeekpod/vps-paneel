<?php

namespace App\Models;

use App\Enums\OperationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'status',
        'payload',
        'result',
        'error_message',
        'ip_address',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'status' => OperationStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Redact sensitive keys from array before storing or displaying.
     */
    public static function redactSensitive(array $data): array
    {
        $sensitiveKeys = [
            'password', 'db_password', 'root_password', 'secret',
            'private_key', 'dkim_private_key', 'token', 'credentials',
            'restic_password', 'api_key', 'auth',
        ];

        $redacted = [];
        foreach ($data as $key => $value) {
            $isSensitive = false;
            foreach ($sensitiveKeys as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $redacted[$key] = '******** (redacted)';
            } elseif (is_array($value)) {
                $redacted[$key] = self::redactSensitive($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }
}
