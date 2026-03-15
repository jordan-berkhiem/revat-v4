<?php

namespace App\Http\Integrations\ExpertSender\Requests;

use Carbon\Carbon;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetClicksRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected ?Carbon $since = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/Activities/Clicks';
    }

    protected function defaultQuery(): array
    {
        $query = [];

        if ($this->since) {
            $query['startDate'] = $this->since->toDateString();
            $query['endDate'] = now()->toDateString();
        }

        return $query;
    }
}
