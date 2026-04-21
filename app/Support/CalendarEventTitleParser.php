<?php

namespace App\Support;

final class CalendarEventTitleParser
{
    /**
     * @return array{client_name: string, project_name: ?string, feature_description: string}|null
     */
    public static function parse(string $title): ?array
    {
        $matches = [];

        if (! preg_match('/^\s*(?<client>.+?)\s*(?\/<project>.+?)\s*:\s*(?<feature>.+?)\s*$/', $title, $matches)) {
            return null;
        }

        $projectName = trim($matches['project']);

        if (blank($projectName)) {
            $projectName = null;
        }

        return [
            'client_name' => trim($matches['client']),
            'project_name' => $projectName,
            'feature_description' => trim($matches['feature']),
        ];
    }
}
