<?php

namespace App\Console\Commands;

use App\Models\GeneratedCv;
use App\Services\CvService;
use Illuminate\Console\Command;

class ArchiveInactiveCvs extends Command
{
    protected $signature = 'cvs:archive-inactive';

    protected $description = 'Archive (supprime le fichier PDF) les CV generes dont le candidat est inactif depuis 15 jours, pour eviter un surplus de donnees stockees';

    private const INACTIVITY_DAYS = 15;

    public function handle(CvService $cvService): int
    {
        $threshold = now()->subDays(self::INACTIVITY_DAYS);

        $cvs = GeneratedCv::query()
            ->whereNull('archived_at')
            ->whereHas('candidateProfile.user', function ($query) use ($threshold) {
                $query->where(function ($query) use ($threshold) {
                    $query->where('last_login_at', '<', $threshold)
                        ->orWhereNull('last_login_at');
                });
            })
            ->get();

        foreach ($cvs as $cv) {
            $cvService->archive($cv);
        }

        $this->info("{$cvs->count()} CV archive(s) (candidat inactif depuis plus de ".self::INACTIVITY_DAYS.' jours).');

        return self::SUCCESS;
    }
}
