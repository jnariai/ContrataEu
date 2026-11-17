<?php

use App\Models\CvImport;
use Illuminate\Support\Facades\Schedule;

/*
| Housekeeping on the small VPS (database-backed queue, password resets, CV imports): keep tables from growing forever.
*/

Schedule::command('queue:prune-failed', ['--hours' => 24 * 14])->daily();
Schedule::command('auth:clear-resets')->daily();
// CV imports nobody saved or discarded, with their PDF (CvImport::prunable).
Schedule::command('model:prune', ['--model' => [CvImport::class]])->hourly();
