<?php

namespace App\Services;

use App\Models\DocumentFolderTemplate;
use App\Models\User;
use App\Models\UserDocumentFolder;

class UserDocumentFolderTemplateSyncService
{
    public function syncForUser(User $user): void
    {
        $templates = DocumentFolderTemplate::query()
            ->select('id', 'title')
            ->orderBy('id')
            ->get();

        foreach ($templates as $template) {
            $this->ensureTemplateFolder((int) $user->id, $template);
        }
    }

    public function syncTemplateForAllUsers(DocumentFolderTemplate $template): void
    {
        User::query()
            ->select('id')
            ->chunkById(200, function ($users) use ($template): void {
                foreach ($users as $user) {
                    $this->ensureTemplateFolder((int) $user->id, $template);
                }
            });
    }

    public function syncTemplateTitle(DocumentFolderTemplate $template): void
    {
        UserDocumentFolder::query()
            ->where('document_folder_template_id', $template->id)
            ->update(['title' => $template->title]);
    }

    private function ensureTemplateFolder(int $userId, DocumentFolderTemplate $template): void
    {
        $byTemplate = UserDocumentFolder::query()
            ->where('user_id', $userId)
            ->where('document_folder_template_id', $template->id)
            ->first();

        if ($byTemplate) {
            return;
        }

        $existingByTitle = UserDocumentFolder::query()
            ->where('user_id', $userId)
            ->whereNull('document_folder_template_id')
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($template->title)])
            ->first();

        if ($existingByTitle) {
            $existingByTitle->update([
                'document_folder_template_id' => $template->id,
                'title' => $template->title,
            ]);
            return;
        }

        UserDocumentFolder::query()->create([
            'user_id' => $userId,
            'document_folder_template_id' => $template->id,
            'title' => $template->title,
        ]);
    }
}
