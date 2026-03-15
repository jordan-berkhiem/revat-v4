<?php

namespace App\Services\Transformation;

use App\Models\IdentityHash;
use Illuminate\Support\Collection;

class IdentityHashingService
{
    /**
     * Gmail-style providers where dots in the local part are insignificant.
     */
    protected const DOT_INSIGNIFICANT_DOMAINS = [
        'gmail.com',
        'googlemail.com',
    ];

    /**
     * Normalize a value for hashing.
     */
    public function normalize(string $value, string $type = 'email'): string
    {
        if ($type === 'email') {
            return $this->normalizeEmail($value);
        }

        return strtolower(trim($value));
    }

    /**
     * Produce a raw binary SHA-256 hash (compatible with Meta/Google custom audiences).
     */
    public function hash(string $normalizedValue): string
    {
        return hash('sha256', $normalizedValue, true);
    }

    /**
     * Resolve or create an identity hash record.
     */
    public function resolveOrCreate(int $workspaceId, string $rawValue, string $type = 'email'): ?IdentityHash
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return null;
        }

        $normalized = $this->normalize($rawValue, $type);
        if ($normalized === '') {
            return null;
        }

        $hashBinary = $this->hash($normalized);
        $domain = $type === 'email' ? $this->extractDomain($normalized) : null;

        // Manual lookup using raw binary to avoid cast mismatch
        $existing = IdentityHash::where('workspace_id', $workspaceId)
            ->whereRaw('hash = ?', [$hashBinary])
            ->where('type', $type)
            ->first();

        if ($existing) {
            return $existing;
        }

        $record = new IdentityHash;
        $record->workspace_id = $workspaceId;
        $record->hash = bin2hex($hashBinary);
        $record->type = $type;
        $record->hash_algorithm = 'sha256';
        $record->normalized_email_domain = $domain;
        $record->save();

        return $record;
    }

    /**
     * Bulk resolve or create identity hashes.
     *
     * Uses upsert with an empty update array for INSERT IGNORE behavior.
     */
    public function resolveOrCreateMany(int $workspaceId, array $rawValues, string $type = 'email'): Collection
    {
        $rows = [];
        $hashHexes = [];

        foreach ($rawValues as $rawValue) {
            $rawValue = trim($rawValue);
            if ($rawValue === '') {
                continue;
            }

            $normalized = $this->normalize($rawValue, $type);
            if ($normalized === '') {
                continue;
            }

            $hashBinary = $this->hash($normalized);
            $hashHex = bin2hex($hashBinary);
            $domain = $type === 'email' ? $this->extractDomain($normalized) : null;

            $rows[] = [
                'workspace_id' => $workspaceId,
                'hash' => $hashBinary,
                'type' => $type,
                'hash_algorithm' => 'sha256',
                'normalized_email_domain' => $domain,
                'created_at' => now(),
            ];

            $hashHexes[] = $hashHex;
        }

        if (empty($rows)) {
            return collect();
        }

        // Upsert with empty update array for INSERT IGNORE behavior
        IdentityHash::upsert(
            $rows,
            ['workspace_id', 'hash', 'type'],
            ['hash_algorithm']
        );

        // Fetch all the records
        return IdentityHash::where('workspace_id', $workspaceId)
            ->where('type', $type)
            ->whereIn('hash', array_map(fn ($hex) => hex2bin($hex), $hashHexes))
            ->get();
    }

    /**
     * Extract domain from an email address.
     */
    public function extractDomain(string $email): ?string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return null;
        }

        return strtolower(trim($parts[1]));
    }

    /**
     * Normalize an email address.
     */
    protected function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        [$local, $domain] = $parts;

        // Strip dots from local part for known dot-insignificant providers
        if (in_array($domain, self::DOT_INSIGNIFICANT_DOMAINS, true)) {
            $local = str_replace('.', '', $local);
        }

        return "{$local}@{$domain}";
    }
}
