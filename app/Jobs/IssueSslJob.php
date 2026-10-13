<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\SslManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IssueSslJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public Website $website,
        public ?string $domain = null
    ) {}

    public function handle(SslManager $sslManager): void
    {
        $sslManager->issueLetsEncrypt($this->website, $this->domain);
    }
}
