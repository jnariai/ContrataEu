<?php

namespace App\Actions;

use App\Datas\ApplicationDetails;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class CreateApplication
{
    public function __construct(
        private DatabaseManager $db,
        private SyncScreeningQuestions $syncScreeningQuestions,
    ) {}

    public function handle(User $user, ApplicationDetails $details): Application
    {
        return $this->db->transaction(function () use ($user, $details): Application {
            $application = Application::open(
                $user,
                $details->companyName,
                $details->roleTitle,
                $details->jobUrl,
                $details->location,
                $details->workMode,
                $details->language,
                $details->companyNotes,
                $details->jobDescription,
                $details->wantsCoverLetter,
                $details->wantsRecruiterMessage,
            );

            $this->syncScreeningQuestions->handle($application, $details->questions);

            return $application;
        });
    }
}
