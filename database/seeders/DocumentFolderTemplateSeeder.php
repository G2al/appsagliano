<?php

namespace Database\Seeders;

use App\Models\DocumentFolderTemplate;
use App\Services\UserDocumentFolderTemplateSyncService;
use Illuminate\Database\Seeder;

class DocumentFolderTemplateSeeder extends Seeder
{
    /**
     * Seed base demo document folder templates.
     */
    public function run(): void
    {
        $titles = [
            'Patente e CQC',
            'Carta tachigrafica',
            'Documenti personali',
            'Buste paga',
            'Contratto di lavoro',
            'Certificati medici',
        ];

        $templates = [];

        DocumentFolderTemplate::withoutEvents(function () use ($titles, &$templates): void {
            foreach ($titles as $title) {
                $templates[] = DocumentFolderTemplate::query()->updateOrCreate(
                    ['title' => $title],
                    ['title' => $title]
                );
            }
        });

        $syncService = app(UserDocumentFolderTemplateSyncService::class);

        foreach ($templates as $template) {
            $syncService->syncTemplateForAllUsers($template);
        }
    }
}
