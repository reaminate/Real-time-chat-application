<?php

use App\Jobs\UserPermaDeleteFromConvo;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new UserPermaDeleteFromConvo())->daily();
