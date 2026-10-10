<x-filament::page>
    <style>
        /* Tiene il bottone "Filtro" sempre visibile mentre si scorre la pagina,
           agganciato subito sotto il topbar fisso (alto 4rem). Vale solo per
           questa pagina: lo stile viene caricato solo quando questa view e'
           renderizzata. */
        .fi-header {
            position: sticky;
            top: 4rem;
            z-index: 10;
            background-color: rgb(255 255 255);
            padding: 1rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        html.dark .fi-header {
            background-color: rgb(24 24 27);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }
    </style>
</x-filament::page>
