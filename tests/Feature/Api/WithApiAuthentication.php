<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;

trait WithApiAuthentication
{
    protected ApiKey $readApiKey;
    protected ApiKey $writeApiKey;

    protected function setUpApiKeys(): void
    {
        $this->readApiKey = ApiKey::create([
            'name' => 'Test Read Key',
            'read_only' => true,
        ]);

        $this->writeApiKey = ApiKey::create([
            'name' => 'Test Write Key',
            'read_only' => false,
        ]);
    }

    protected function withReadApiKey()
    {
        return $this->withHeader('X-API-KEY', $this->readApiKey->id);
    }

    protected function withWriteApiKey()
    {
        return $this->withHeader('X-API-KEY', $this->writeApiKey->id);
    }
}
