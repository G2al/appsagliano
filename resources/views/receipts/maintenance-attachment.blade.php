<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Allegato manutenzione #{{ $maintenance->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            color: #111;
        }
        .card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 16px;
            max-width: 720px;
            margin: 0 auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        h1 {
            font-size: 18px;
            margin: 0 0 12px;
        }
        p {
            margin: 4px 0;
            font-size: 14px;
        }
        .photo {
            width: 100%;
            max-height: 900px;
            object-fit: contain;
            margin-top: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fdfdfd;
        }
        .meta {
            font-size: 13px;
            color: #555;
        }
    </style>
</head>
<body onload="window.print(); window.onafterprint = () => window.close();">
    <div class="card">
        <h1>Allegato manutenzione #{{ $maintenance->id }}</h1>
        <p class="meta">Autore: {{ $maintenance->user?->full_name ?? $maintenance->user?->name ?? 'N/D' }}</p>
        <p class="meta">Veicolo: {{ $maintenance->vehicle?->plate ?? $maintenance->vehicle?->name ?? 'N/D' }}</p>
        <p class="meta">Fornitore: {{ $maintenance->supplier?->name ?? 'N/D' }}</p>
        @if($maintenance->date)
            <p class="meta">Data: {{ $maintenance->date->format('d/m/Y H:i') }}</p>
        @endif
        <img class="photo" src="{{ $maintenance->attachment_url }}" alt="Allegato manutenzione #{{ $maintenance->id }}">
    </div>
</body>
</html>
