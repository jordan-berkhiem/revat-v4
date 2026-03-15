<?php

namespace App\Http\Integrations\Voluum\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class AuthenticateRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $accessKeyId,
        protected string $accessKeySecret,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/auth/access/session';
    }

    protected function defaultBody(): array
    {
        return [
            'accessId' => $this->accessKeyId,
            'accessKey' => $this->accessKeySecret,
        ];
    }
}
