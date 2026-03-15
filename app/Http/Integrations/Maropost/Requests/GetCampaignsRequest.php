<?php

namespace App\Http\Integrations\Maropost\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCampaignsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected int $page = 1,
        protected int $perPage = 500,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/campaigns.json';
    }

    protected function defaultQuery(): array
    {
        return [
            'page' => $this->page,
            'per' => $this->perPage,
        ];
    }
}
