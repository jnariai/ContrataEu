<?php

namespace App\Actions;

use App\Datas\ApplicationDetails;
use App\Models\Application;
use Illuminate\Database\DatabaseManager;

class UpdateApplication
{
    public function __construct(
        private DatabaseManager $db,
        private SyncScreeningQuestions $syncScreeningQuestions,
    ) {}

    public function handle(Application $application, ApplicationDetails $details): Application
    {
        return $this->db->transaction(function () use ($application, $details): Application {
            $application->revise(
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
