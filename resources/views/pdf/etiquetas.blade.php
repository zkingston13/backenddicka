<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    <title>Imprimir etiqueta</title>

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        @page {
            size: 10cm 10cm;
            margin: 0;
        }

        .etiqueta {
            width: 10cm;
            height: 10cm;
            page-break-after: always;
            position: relative;
            text-align: center
        }

        .logo {
            display: block;
            margin: 2% auto;
            width: 4.3cm;
            height: 1cm;

        }

        .logo-container {
            text-align: center
        }

        .header {
            width: 100%;
            overflow: hidden;
        }

        .info {
            float: left;
            width: 70%;
        }

        .container-qr {
            position: absolute;
            top: 0.1cm;
            left: 7cm;

        }

        .numero-pallet {
            position: absolute;
            top: 5.2cm;
            right: 0.8cm;
            font-size: 30px;
            font-weight: bold;
        }

        .cont-cod {
            position: absolute;
            top: 0.5cm;
            left: 0.3cm;
            text-align: center;
            font-size: 19px;
            font-weight: bold;
            line-height: 1.2;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>

<body>

    @foreach ($pallets as $pallet)
        <div class="logo-container">
            <img class="logo" src="{{ public_path('img/logo.png') }}" alt="Logo">
        </div>
        <div class="etiqueta">
            @php
                $qr = json_encode([
                    'lot'=> $lote->lote,
                    'codigo_pallet' => $pallet->codigo,
                    'pallet_numero' => $pallet->etiqueta_numero,
                    'cantidad' => $pallet->cantidad,
                    'producto' =>  $lote->producto->nombre,
                    'fecha_ingreso' => $lote->fechaRecibido
                ]);
            @endphp
            <div class="header">
                <div class="container-qr">
                    <img class="qr"
                        src="data:image/svg+xml;base64,{{ base64_encode(QrCode::size(100)->generate($qr)) }}">

                </div>
                <div class="numero-pallet">
                    {{ $pallet->etiqueta_numero }}
                </div>

                <br>
                <p><strong>{{ $lote->producto->cliente->razonSocial ?? 'Desconocido' }} DES {{ $lote->folio }}
                    </strong></p>


                <p><strong>SKU: {{ $lote->producto->sku }}</strong></p>

                <p class="producto"><strong>{{ $lote->producto->nombre }}</strong></p>
                <p><strong>{{ $lote->lote }}</strong> </p>
                <p><strong>CANTIDAD:</strong></p>
                <p><strong>{{ $pallet->cantidad }}</strong></p>
                <p><strong>FECHA DE INGRESO: {{ $lote->fechaRecibido }}</strong></p>
                <div class="cont-cod">
                    @foreach(str_split($pallet->codigo) as $char)
                        {{ $char }}<br>
                    @endforeach
                </div>
                <br>
    @endforeach



</body>

</html>
