<?php

namespace App\Http\Integrations\ExpertSender\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetMessagesRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/v2/Api/Messages';
    }
}
