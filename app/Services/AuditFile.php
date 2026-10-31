<?php

namespace App\Services;

use TightenCo\Jigsaw\Jigsaw;

/**
 * The register a Random Audit deals its run from (site.js): every work's
 * title, address, division, date and lead image, written as /audit.json.
 */
class AuditFile
{
    public function handle(Jigsaw $jigsaw): void
    {
        $register = array_map(fn (array $w) => [
            't' => $w['title'],
            'a' => $w['address'],
            'd' => $w['division_name'] ?? null,
            'y' => $w['date'],
            'i' => $w['image'] ?? null,
        ], $jigsaw->getConfig('works')->toArray());

        file_put_contents($jigsaw->getDestinationPath().'/audit.json', json_encode($register, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
