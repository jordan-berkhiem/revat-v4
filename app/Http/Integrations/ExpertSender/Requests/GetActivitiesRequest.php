<?php

namespace App\Http\Integrations\ExpertSender\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetActivitiesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $type,
        protected string $date,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/Api/Activities';
    }

    protected function defaultQuery(): array
    {
        return [
            'type' => $this->type,
            'date' => $this->date,
        ];
    }
}
