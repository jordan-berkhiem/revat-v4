<?php

namespace App\Http\Integrations\ExpertSender\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetMessageStatisticsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected int|string $messageId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v2/Api/MessageStatistics/{$this->messageId}";
    }
}
