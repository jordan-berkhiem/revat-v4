<?php

namespace App\Http\Integrations\Maropost\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCampaignClicksRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $campaignId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/campaigns/{$this->campaignId}/clicks.json";
    }
}
