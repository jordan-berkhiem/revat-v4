<?php

namespace App\Http\Integrations\Maropost\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class GraphqlCampaignsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected int $page = 1,
        protected int $per = 200,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/graphql.json';
    }

    protected function defaultBody(): array
    {
        return [
            'query' => "{
                campaigns(per: {$this->per}, page: {$this->page}) {
                    id name status subject from_name from_email
                    sent_at send_at campaign_type parent_id one_time
                    total_sent total_opens total_unique_opens
                    total_clicks total_unique_clicks total_bounces
                    total_complaints total_unsubscribes total_revenue
                }
            }",
        ];
    }
}
