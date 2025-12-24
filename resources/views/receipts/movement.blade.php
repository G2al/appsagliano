<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ricevuta movimento #{{ $movement->id }}</title>
    <style>
        @page {
            size: 58mm auto;
            margin: 4mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: "Courier New", Courier, monospace;
            font-size: 11px;
            color: #111;
        }

        .receipt {
            width: 58mm;
            margin: 0 auto;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #666;
        }

        .line {
            border-top: 1px dashed #111;
            margin: 6px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 6px;
        }

        .row span:last-child {
            text-align: right;
            word-break: break-word;
        }

        .block {
            margin-top: 4px;
        }

        .no-print {
            margin-top: 10px;
            text-align: center;
        }

        .no-print button {
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid #111;
            background: #fff;
            cursor: pointer;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center">
            <strong>APP SAGLIANO</strong><br>
            <span class="muted">Ricevuta movimento carburante</span><br>
            <span class="muted">ID: {{ $movement->id }}</span>
        </div>

        <div class="line"></div>

        <div class="row"><span>Autore</span><span>{{ $movement->user?->full_name ?? $movement->user?->name ?? 'N/D' }}</span></div>
        <div class="row"><span>Data</span><span>{{ $movement->date?->format('d/m/Y H:i') ?? 'N/D' }}</span></div>

        <div class="line"></div>

        <div class="row"><span>Stazione</span><span>{{ $movement->station?->name ?? 'N/D' }}</span></div>
        <div class="row"><span>Indirizzo</span><span>{{ $movement->station?->address ?? 'N/D' }}</span></div>

        <div class="line"></div>

        <div class="row"><span>Veicolo</span><span>{{ $movement->vehicle?->name ?? 'N/D' }}</span></div>
        <div class="row"><span>Targa</span><span>{{ $movement->vehicle?->plate ?? 'N/D' }}</span></div>
        <div class="row"><span>Colore</span><span>{{ $movement->vehicle?->color ?? 'N/D' }}</span></div>

        <div class="line"></div>

        <div class="row"><span>Km iniziali</span><span>{{ $movement->km_start !== null ? number_format((float) $movement->km_start, 0, ',', '.') : 'N/D' }}</span></div>
        <div class="row"><span>Km finali</span><span>{{ $movement->km_end !== null ? number_format((float) $movement->km_end, 0, ',', '.') : 'N/D' }}</span></div>
        <div class="row"><span>Litri</span><span>{{ $movement->liters !== null ? number_format((float) $movement->liters, 2, ',', '.') : 'N/D' }}</span></div>
        <div class="row"><span>Prezzo</span><span>{{ $movement->price !== null ? number_format((float) $movement->price, 2, ',', '.') . ' EUR' : 'N/D' }}</span></div>
        <div class="row"><span>AdBlue</span><span>{{ $movement->adblue !== null ? number_format((float) $movement->adblue, 2, ',', '.') . ' L' : 'N/D' }}</span></div>

        <div class="line"></div>

        <div class="block">
            <div><strong>Note</strong></div>
            <div>{{ $movement->notes ?: 'N/D' }}</div>
        </div>

        <div class="line"></div>

        <div class="row"><span>Creato il</span><span>{{ $movement->created_at?->format('d/m/Y H:i') ?? 'N/D' }}</span></div>
        <div class="row"><span>Aggiornato il</span><span>{{ $movement->updated_at?->format('d/m/Y H:i') ?? 'N/D' }}</span></div>
        <div class="row"><span>Aggiornato da</span><span>{{ $movement->updatedBy?->name ?? 'N/D' }}</span></div>

        <div class="line"></div>

        <div class="center muted">Stampato il {{ now()->format('d/m/Y H:i') }}</div>

        <div class="no-print">
            <button type="button" onclick="window.print()">Stampa</button>
        </div>
    </div>
</body>
</html>
