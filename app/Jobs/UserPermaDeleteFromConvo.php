<?php

namespace App\Jobs;

use App\Models\ConversationMember;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UserPermaDeleteFromConvo implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ConversationMember::whereNotNull('left_at')
            ->whereDate('left_at', '<=', now()->subDays(10))
            ->delete();
    }
}
