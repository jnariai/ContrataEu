<?php

use Illuminate\Support\Facades\Schedule;

/*
| Housekeeping on the small VPS (database-backed queue and password resets): keep these tables from growing forever.
*/

Schedule::command('queue:prune-failed', ['--hours' => 24 * 14])->daily();
Schedule::command('auth:clear-resets')->daily();
