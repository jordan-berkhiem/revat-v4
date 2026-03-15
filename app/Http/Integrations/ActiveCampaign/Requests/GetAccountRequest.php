<?php

namespace App\Http\Integrations\ActiveCampaign\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetAccountRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/api/3/users/me';
    }
}
