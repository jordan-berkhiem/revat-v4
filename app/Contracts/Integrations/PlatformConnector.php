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
}
