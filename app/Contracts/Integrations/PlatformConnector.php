<?php

namespace App\Contracts\Integrations;

use App\DTOs\Integrations\ConnectionTest;
use App\Models\Integration;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface PlatformConnector
{
    public function testConnection(): ConnectionTest;

    public function fetchCampaignEmails(Integration $integration, ?Carbon $since = null): Collection;

    public function fetchCampaignEmailClicks(Integration $integration, ?Carbon $since = null): Collection;

    public function fetchConversionSales(Integration $integration, ?Carbon $since = null): Collection;

    public function supportsDataType(string $dataType): bool;

    public function platform(): string;

    /**
     * Return matchable field names for attribution, keyed by data type.
     *
     * @return array<string, array<array{value: string, label: string}>>
     */
    public function getMatchableFields(Integration $integration): array;
}
