<?php

namespace App\Http\Integrations\ActiveCampaign\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCampaignsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected int $offset = 0,
        protected int $limit = 100,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/3/campaigns';
    }

    protected function defaultQuery(): array
    {
        return [
            'offset' => $this->offset,
            'limit' => $this->limit,
            'orders[sdate]' => 'DESC',
            'include' => 'campaignMessages',
        ];
    }
}
