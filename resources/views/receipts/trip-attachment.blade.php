<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Allegato viaggio #{{ $trip->id }}</title>
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
        .attachment {
            margin-top: 16px;
        }
        .attachment a {
            display: inline-block;
            margin-bottom: 4px;
            font-size: 13px;
            color: #1A2A9C;
        }
    </style>
</head>
<body onload="window.print(); window.onafterprint = () => window.close();">
    <div class="card">
        <h1>Allegati viaggio #{{ $trip->id }}</h1>
        <p class="meta">Autista: {{ $trip->user?->full_name ?? $trip->user?->name ?? 'N/D' }}</p>
        <p class="meta">Veicolo: {{ $trip->vehicle?->plate ?? $trip->vehicle?->name ?? 'N/D' }}</p>
        <p class="meta">Piattaforma: {{ $trip->platform?->name ?? 'N/D' }}</p>
        <p class="meta">Destinazioni: {{ $trip->destinations_label ?: 'N/D' }}</p>
        <p class="meta">Bolla: {{ $trip->delivery_note_number }}</p>
        @if($trip->date)
            <p class="meta">Data: {{ $trip->date->format('d/m/Y H:i') }}</p>
        @endif
        @foreach ($trip->attachments as $attachment)
            <div class="attachment">
                <a href="{{ route('trips.attachment.download', [$trip, $attachment]) }}" target="_blank">Scarica allegato {{ $loop->iteration }}</a>
                @if (in_array(strtolower(pathinfo($attachment->path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                    <img class="photo" src="{{ $attachment->url }}" alt="Allegato {{ $loop->iteration }} viaggio #{{ $trip->id }}">
                @endif
            </div>
        @endforeach
    </div>
</body>
</html>
