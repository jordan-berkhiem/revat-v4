<?php

use App\Models\IdentityHash;
use App\Models\Organization;
use App\Models\Workspace;
use App\Services\Transformation\IdentityHashingService;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->service = new IdentityHashingService;

});

// ── Normalization ─────────────────────────────────────────────────────

it('normalizes email case and whitespace', function () {
    expect($this->service->normalize('  USER@EXAMPLE.COM  '))->toBe('user@example.com');
});

it('strips dots from gmail local part', function () {
    expect($this->service->normalize('j.o.h.n@gmail.com'))->toBe('john@gmail.com');
    expect($this->service->normalize('j.o.h.n@googlemail.com'))->toBe('john@googlemail.com');
});

it('preserves dots for non-gmail domains', function () {
    expect($this->service->normalize('j.o.h.n@example.com'))->toBe('j.o.h.n@example.com');
});

it('normalizes equivalent emails identically', function () {
    $a = $this->service->normalize('User@Gmail.com');
    $b = $this->service->normalize('user@gmail.com');
    $c = $this->service->normalize('  USER@GMAIL.COM ');

    expect($a)->toBe($b)->toBe($c);
});

// ── Hashing ───────────────────────────────────────────────────────────

it('produces deterministic hashes', function () {
    $hash1 = $this->service->hash('user@example.com');
    $hash2 = $this->service->hash('user@example.com');

    expect($hash1)->toBe($hash2);
    expect(strlen($hash1))->toBe(32); // raw binary SHA-256 = 32 bytes
});

it('produces different hashes for different inputs', function () {
    $hash1 = $this->service->hash('user@example.com');
    $hash2 = $this->service->hash('other@example.com');

    expect($hash1)->not->toBe($hash2);
});

// ── resolveOrCreate ───────────────────────────────────────────────────

it('creates a new identity hash on first call', function () {
    $result = $this->service->resolveOrCreate($this->workspace->id, 'user@example.com');

    expect($result)->not->toBeNull();
    expect($result)->toBeInstanceOf(IdentityHash::class);
    expect($result->workspace_id)->toBe($this->workspace->id);
    expect($result->type)->toBe('email');
    expect($result->hash_algorithm)->toBe('sha256');
    expect($result->normalized_email_domain)->toBe('example.com');

    expect(IdentityHash::count())->toBe(1);
});

it('returns existing record on second call with same input', function () {
    $first = $this->service->resolveOrCreate($this->workspace->id, 'user@example.com');
    $second = $this->service->resolveOrCreate($this->workspace->id, 'user@example.com');

    expect($first->id)->toBe($second->id);
    expect(IdentityHash::count())->toBe(1);
});

it('returns same record for equivalent emails', function () {
    $first = $this->service->resolveOrCreate($this->workspace->id, 'User@Example.com');
    $second = $this->service->resolveOrCreate($this->workspace->id, '  user@example.com  ');

    expect($first->id)->toBe($second->id);
    expect(IdentityHash::count())->toBe(1);
});

it('returns null for empty input', function () {
    expect($this->service->resolveOrCreate($this->workspace->id, ''))->toBeNull();
    expect($this->service->resolveOrCreate($this->workspace->id, '   '))->toBeNull();
    expect(IdentityHash::count())->toBe(0);
});

// ── resolveOrCreateMany ───────────────────────────────────────────────

it('bulk creates identity hashes', function () {
    $results = $this->service->resolveOrCreateMany(
        $this->workspace->id,
        ['a@example.com', 'b@example.com', 'c@example.com']
    );

    expect($results)->toHaveCount(3);
    expect(IdentityHash::count())->toBe(3);
});

it('skips empty values in bulk create', function () {
    $results = $this->service->resolveOrCreateMany(
        $this->workspace->id,
        ['a@example.com', '', '  ', 'b@example.com']
    );

    expect($results)->toHaveCount(2);
    expect(IdentityHash::count())->toBe(2);
});

// ── Domain Extraction ─────────────────────────────────────────────────

it('extracts domain from email', function () {
    expect($this->service->extractDomain('user@example.com'))->toBe('example.com');
    expect($this->service->extractDomain('user@Sub.Domain.COM'))->toBe('sub.domain.com');
});

it('returns null for invalid email', function () {
    expect($this->service->extractDomain('no-at-sign'))->toBeNull();
});

// ── Migration / Table ─────────────────────────────────────────────────

it('has identity_hashes table with correct columns', function () {
    $columns = Schema::getColumnListing('identity_hashes');

    expect($columns)->toContain('id');
    expect($columns)->toContain('workspace_id');
    expect($columns)->toContain('hash');
    expect($columns)->toContain('type');
    expect($columns)->toContain('hash_algorithm');
    expect($columns)->toContain('normalized_email_domain');
    expect($columns)->toContain('created_at');
});
