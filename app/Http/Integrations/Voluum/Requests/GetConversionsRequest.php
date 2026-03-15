<?php

namespace App\Http\Integrations\Voluum\Requests;

use Carbon\Carbon;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetConversionsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected Carbon $from,
        protected Carbon $to,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/report/conversions';
    }

    protected function defaultQuery(): array
    {
        return [
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
            'tz' => 'UTC',
        ];
    }
}
