<?php

namespace App\Http\Integrations\ActiveCampaign;

use App\DTOs\Integrations\ConnectionTest;
use App\Http\Integrations\ActiveCampaign\Requests\GetAccountRequest;
use App\Http\Integrations\ActiveCampaign\Requests\GetCampaignReportLinksRequest;
use App\Http\Integrations\ActiveCampaign\Requests\GetCampaignsRequest;
use App\Http\Integrations\ActiveCampaign\Requests\GetMessagesRequest;
use App\Http\Integrations\BasePlatformConnector;
use App\Models\Integration;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class ActiveCampaignConnector extends BasePlatformConnector
{
    public function platform(): string
    {
        return 'activecampaign';
    }

    public function resolveBaseUrl(): string
    {
        return $this->validateUrl($this->credentials['api_url'] ?? '');
    }

    protected function defaultHeaders(): array
    {
        return [
            'Api-Token' => $this->credentials['api_key'] ?? '',
        ];
    }

    public function testConnection(): ConnectionTest
    {
        try {
            $response = $this->send(new GetAccountRequest);
            $data = $response->json();

            $username = $data['user']['username'] ?? 'unknown';

            return ConnectionTest::ok(
                message: "Successfully connected to ActiveCampaign as {$username}.",
                details: ['username' => $username],
            );
        } catch (FatalRequestException|RequestException $e) {
            return ConnectionTest::fail("ActiveCampaign connection failed: {$e->getMessage()}");
        }
    }

    public function fetchCampaignEmails(Integration $integration, ?Carbon $since = null): Collection
    {
        // Pass 1: Paginate campaigns with sideloaded campaignMessages
        $campaignRows = [];
        $subjectByCampaignId = [];
        $messageIdByCampaignId = [];
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->send(new GetCampaignsRequest($offset, $limit));
            $data = $response->json();

            $total = (int) ($data['meta']['total'] ?? 0);

            foreach ($data['campaigns'] ?? [] as $campaign) {
                $campaignId = (string) $campaign['id'];

                if ($since && isset($campaign['cdate'])) {
                    $createdAt = Carbon::parse($campaign['cdate']);
                    if ($createdAt->lt($since)) {
                        continue;
                    }
                }

                $campaignRows[$campaignId] = $campaign;
            }

            foreach ($data['campaignMessages'] ?? [] as $cm) {
                $cid = (string) $cm['campaignid'];
                $subjectByCampaignId[$cid] = $cm['subject'] ?? null;
                $messageIdByCampaignId[$cid] = (string) ($cm['messageid'] ?? $cm['message'] ?? '');
            }

            $offset += $limit;
        } while ($offset < $total);

        // Pass 2: Paginate /api/3/messages to build sender lookup
        $sendersByMessageId = $this->fetchSendersByMessageId();

        // Pass 3: Join all sources into complete records
        $campaigns = collect();

        foreach ($campaignRows as $campaignId => $campaign) {
            $campaignId = (string) $campaignId;
            $messageId = $messageIdByCampaignId[$campaignId] ?? null;
            $sender = $messageId ? ($sendersByMessageId[$messageId] ?? []) : [];

            $sentAt = null;
            if (! empty($campaign['sdate']) && $campaign['sdate'] !== '0000-00-00 00:00:00') {
                $sentAt = $campaign['sdate'];
            }

            $campaigns->push([
                'external_id' => $campaignId,
                'name' => $campaign['name'] ?? '',
                'subject' => $subjectByCampaignId[$campaignId] ?? $campaign['subject'] ?? '',
                'from_name' => $sender['fromname'] ?? '',
                'from_email' => $sender['fromemail'] ?? '',
                'type' => $this->resolveCampaignType($campaign),
                'status' => $this->resolveStatus($campaign),
                'sent' => (int) ($campaign['send_amt'] ?? $campaign['sent'] ?? 0),
                'delivered' => (int) ($campaign['delivered'] ?? 0),
                'opens' => (int) ($campaign['opens'] ?? 0),
                'unique_opens' => (int) ($campaign['uniqueopens'] ?? $campaign['opens'] ?? 0),
                'clicks' => (int) ($campaign['linkclicks'] ?? 0),
                'unique_clicks' => (int) ($campaign['uniquelinkclicks'] ?? 0),
                'unsubscribes' => (int) ($campaign['unsubscribes'] ?? 0),
                'bounces' => (int) ($campaign['hardbounces'] ?? 0) + (int) ($campaign['softbounces'] ?? 0),
                'sent_at' => $sentAt,
                'platform_created_at' => $campaign['cdate'] ?? null,
            ]);
        }

        return $campaigns;
    }

    /**
     * Paginate /api/3/messages and return a map of message ID → sender fields.
     */
    protected function fetchSendersByMessageId(): array
    {
        $senders = [];
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->send(new GetMessagesRequest($offset, $limit));
            $data = $response->json();

            $total = (int) ($data['meta']['total'] ?? 0);

            foreach ($data['messages'] ?? [] as $m) {
                $senders[(string) $m['id']] = [
                    'fromname' => $m['fromname'] ?? null,
                    'fromemail' => $m['fromemail'] ?? null,
                ];
            }

            $offset += $limit;
        } while ($offset < $total);

        return $senders;
    }

    public function fetchCampaignEmailClicks(Integration $integration, ?Carbon $since = null): Collection
    {
        $clicks = collect();
        $offset = 0;
        $limit = 100;

        // Get campaigns first
        do {
            $response = $this->send(new GetCampaignsRequest($offset, $limit));
            $data = $response->json();

            $campaigns = $data['campaigns'] ?? [];

            foreach ($campaigns as $campaign) {
                $campaignId = (string) $campaign['id'];

                // Paginate the V1 campaign_report_link_list endpoint per campaign
                $this->fetchClicksForCampaign($campaignId, $since, $clicks);
            }

            $offset += $limit;
            $total = (int) ($data['meta']['total'] ?? 0);
        } while ($offset < $total);

        return $clicks;
    }

    /**
     * Paginate the V1 campaign_report_link_list endpoint for a single campaign.
     */
    protected function fetchClicksForCampaign(string $campaignId, ?Carbon $since, Collection $clicks): void
    {
        $page = 1;

        do {
            $response = $this->send(new GetCampaignReportLinksRequest($campaignId, $page));
            $data = $response->json();

            // Separate link objects (numeric keys) from metadata keys
            $links = [];
            foreach ($data as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    $links[] = $value;
                }
            }

            foreach ($links as $link) {
                foreach ($link['info'] ?? [] as $info) {
                    if (empty($info['email'])) {
                        continue;
                    }

                    $clickedAt = $info['tstamp_iso'] ?? $info['tstamp'] ?? null;

                    if ($since && $clickedAt && Carbon::parse($clickedAt)->lt($since)) {
                        continue;
                    }

                    $email = strtolower(trim($info['email']));
                    $subscriberEmailHash = hash('sha256', $email);

                    $clickUrl = $link['link'] ?? '';
                    $urlParams = [];
                    $parsedUrl = parse_url($clickUrl);
                    if (isset($parsedUrl['query'])) {
                        parse_str($parsedUrl['query'], $urlParams);
                    }

                    $clicks->push([
                        'external_campaign_id' => $campaignId,
                        'subscriber_email_hash' => $subscriberEmailHash,
                        'click_url' => $clickUrl,
                        'url_params' => $urlParams,
                        'clicked_at' => $clickedAt,
                    ]);
                }
            }

            $page++;
        } while (count($links) >= 20);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    protected function resolveCampaignType(array $campaign): string
    {
        return match ($campaign['type'] ?? '') {
            'single' => 'broadcast',
            'automation' => 'automation',
            'split_test' => 'split_test',
            default => 'broadcast',
        };
    }

    protected function resolveStatus(array $campaign): ?string
    {
        return match ((string) ($campaign['status'] ?? '')) {
            '0' => 'draft',
            '1' => 'active',
            '2' => 'sent',
            '3' => 'paused',
            '5' => 'stopped',
            default => null,
        };
    }
}
