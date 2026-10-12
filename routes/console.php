<?php

use App\Jobs\UserPermaDeleteFromConvo;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new UserPermaDeleteFromConvo())->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();