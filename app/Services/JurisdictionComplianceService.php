<?php

namespace App\Services;

class JurisdictionComplianceService
{
    /**
     * Return a conservative compliance gate. This is an operational review
     * signal, not legal advice or a substitute for local counsel.
     */
    public function check(array $input): array
    {
        $state = strtoupper(trim((string) ($input['state'] ?? '')));
        $activity = strtolower(trim((string) ($input['activity'] ?? 'acquisition')));

        $profiles = [
            'NC' => [
                'status' => 'REVIEW',
                'license_review' => true,
                'basis' => 'North Carolina General Statutes Chapter 93A requires a license to act as a real estate broker; broker activity includes negotiating or buying/selling real estate for others.',
                'source' => 'NCGS 93A-1 and 93A-2',
                'notes' => [
                    'Do not assume that calling a transaction a wholesale deal removes broker-law requirements.',
                    'Principal-investor activity can involve different facts from acting for another party; route the exact transaction structure to qualified North Carolina counsel/NCREC guidance.',
                    'Do not treat proposed or failed legislation as current law.',
                ],
            ],
        ];

        if (!isset($profiles[$state])) {
            return [
                'status' => 'REVIEW',
                'license_review' => null,
                'state' => $state ?: null,
                'activity' => $activity,
                'basis' => 'No curated jurisdiction profile is available for this state.',
                'source' => null,
                'notes' => [
                    'Obtain state-specific legal review before automated seller solicitation, contract marketing, assignment, or negotiation.',
                    'The system will not infer that an unlisted state permits or prohibits wholesaling.',
                ],
                'automation_allowed' => false,
            ];
        }

        return [
            'status' => $profiles[$state]['status'],
            'license_review' => $profiles[$state]['license_review'],
            'state' => $state,
            'activity' => $activity,
            'basis' => $profiles[$state]['basis'],
            'source' => $profiles[$state]['source'],
            'notes' => $profiles[$state]['notes'],
            'automation_allowed' => false,
        ];
    }
}
