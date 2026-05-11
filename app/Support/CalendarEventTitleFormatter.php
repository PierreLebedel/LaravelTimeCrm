<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Project;

class CalendarEventTitleFormatter
{
    public static function format(Client $client, ?Project $project, string $featureDescription): string
    {
        $title = trim($client->name);

        if ($project !== null && trim($project->name) !== trim($client->name)) {
            $title .= '/'.trim($project->name);
        }

        $title .= ' : '.trim($featureDescription);

        return $title;
    }
}
