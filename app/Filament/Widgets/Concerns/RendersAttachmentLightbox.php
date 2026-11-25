<?php

namespace App\Filament\Widgets\Concerns;

trait RendersAttachmentLightbox
{
    /**
     * Link "Vedi allegato" che apre un popup (lightbox) con l'immagine o il
     * PDF direttamente sopra la pagina, senza ricaricare o aprire schede
     * nuove. Pensato per essere incollato dentro tabelle HTML costruite a
     * mano (modali di dettaglio annidati).
     */
    private function renderAttachmentLightbox(string $url, string $label = 'Vedi allegato'): string
    {
        $url = e($url);
        $label = e($label);
        $isPdf = str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.pdf');

        $media = $isPdf
            ? "<iframe src=\"{$url}\" style=\"width:85vw;max-width:900px;height:80vh;border:0;border-radius:0.5rem;background:#fff;\"></iframe>"
            : <<<IMG
                <div :style="zoomed ? 'max-width:95vw;max-height:90vh;overflow:auto;' : 'max-width:85vw;max-height:80vh;overflow:hidden;'">
                    <img
                        src="{$url}"
                        @click.stop="zoomed = ! zoomed"
                        :style="zoomed ? 'max-width:none;max-height:none;cursor:zoom-out;border-radius:0.5rem;display:block;' : 'max-width:85vw;max-height:80vh;cursor:zoom-in;border-radius:0.5rem;display:block;'"
                    />
                </div>
            IMG;

        return <<<HTML
            <span x-data="{ open: false, zoomed: false }">
                <button type="button" @click="open = true" class="text-primary-500 underline">{$label}</button>
                <div
                    x-show="open"
                    x-cloak
                    @click.self="open = false; zoomed = false"
                    @keydown.escape.window="open = false; zoomed = false"
                    style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.8);display:flex;align-items:center;justify-content:center;padding:1rem;"
                >
                    <div @click.self="open = false; zoomed = false" style="display:flex;flex-direction:column;gap:0.5rem;align-items:center;">
                        {$media}
                        <div style="display:flex;gap:1rem;" @click.stop>
                            <a href="{$url}" target="_blank" style="color:#fff;text-decoration:underline;font-size:0.875rem;">Apri in una scheda</a>
                            <button type="button" @click="open = false; zoomed = false" style="color:#fff;text-decoration:underline;font-size:0.875rem;">Chiudi</button>
                        </div>
                    </div>
                </div>
            </span>
        HTML;
    }
}
