<?php

namespace App\Http\Integrations\Maropost\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetClickReportRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected int $page = 1,
        protected int $per = 500,
        protected ?string $from = null,
        protected ?string $to = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/reports/clicks.json';
    }

    protected function defaultQuery(): array
    {
        $query = [
            'page' => $this->page,
            'per' => $this->per,
            'unique' => 'true',
        ];

        if ($this->from !== null) {
            $query['from'] = $this->from;
        }

        if ($this->to !== null) {
            $query['to'] = $this->to;
        }

        return $query;
    }
}
