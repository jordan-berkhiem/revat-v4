<?php

namespace App\Http\Integrations\ExpertSender\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetSummaryStatisticsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $startDate,
        protected string $endDate,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v2/Api/SummaryStatistics';
    }

    protected function defaultQuery(): array
    {
        return [
            'grouping' => 'Message',
            'returnMessageTags' => 'false',
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ];
    }
}
