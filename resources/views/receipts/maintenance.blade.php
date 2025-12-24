<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Manutenzione #{{ $maintenance->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 10px;
            width: 240px;
            background: #fff;
            color: #000;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { margin: 6px 0; border-top: 1px dashed #000; }
        .row { display: flex; justify-content: space-between; margin: 4px 0; }
        .wrap { word-break: break-all; }
    </style>
</head>
<body onload="window.print(); window.onafterprint = () => window.close();">
    <div class="center bold">App Sagliano</div>
    <div class="center">Manutenzione #{{ $maintenance->id }}</div>
    <div class="line"></div>

    <div class="row"><span>Data</span><span>{{ $maintenance->date?->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Veicolo</span><span class="wrap">{{ ($maintenance->vehicle?->plate ? $maintenance->vehicle->plate.' - ' : '') . ($maintenance->vehicle?->name ?? 'N/D') }}</span></div>
    <div class="row"><span>Fornitore</span><span class="wrap">{{ $maintenance->supplier?->name ?? 'N/D' }}</span></div>
    <div class="row"><span>Km manutenzione</span><span>{{ number_format((int) $maintenance->km_current, 0, ',', '.') }}</span></div>
    <div class="row"><span>Prezzo</span><span>{{ number_format((float) $maintenance->price, 2, ',', '.') }} €</span></div>
    <div class="row"><span>Bolla</span><span class="wrap">{{ $maintenance->invoice_number }}</span></div>

    <div class="line"></div>
    <div class="bold">Dettagli</div>
    <div class="wrap">{{ $maintenance->notes }}</div>

    <div class="line"></div>
    <div class="center">-- Fine --</div>
</body>
</html>
