<?php

namespace App\Http\Integrations\ActiveCampaign\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCampaignReportLinksRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $campaignId,
        protected int $page = 1,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/admin/api.php';
    }

    protected function defaultQuery(): array
    {
        return [
            'api_action' => 'campaign_report_link_list',
            'api_output' => 'json',
            'campaignid' => $this->campaignId,
            'page' => $this->page,
        ];
    }
}
