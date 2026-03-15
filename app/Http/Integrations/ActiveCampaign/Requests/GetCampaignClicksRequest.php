<?php

namespace App\Http\Integrations\ActiveCampaign\Requests;

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
        return "/api/3/campaigns/{$this->campaignId}/links";
    }
}
