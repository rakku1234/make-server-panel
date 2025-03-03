<?php

use App\Jobs\ServerStatus;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ServerStatus)->everyThreeMinutes();
