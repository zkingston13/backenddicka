<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

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
            
        }

        .logo {
            display: block;
            margin: 0 auto;
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
            text-align: right;
            padding: 2%;
        }

        .numero-pallet {
            position: absolute;
            top: 5.2cm;
            right: 0.8cm;
            font-size: 30px;
            font-weight: bold;
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
                    'lote_id' => $lote->id,
                    'pallet_numero' => $pallet->etiqueta_numero,
                    'cantidad' => $pallet->cantidad,
                ]);
            @endphp
            <div class="header">
                <div class="info">



                    <p><strong>{{ $lote->producto->cliente->razonSocial ?? 'Desconocido' }} DES {{ $lote->folio }}
                        </strong></p>


                    <p><strong>SKU: {{ $lote->producto->sku }}</strong></p>
                </div>

                <div class="container-qr">
                    <img
                        src="data:image/svg+xml;base64,{{ base64_encode(QrCode::size(100)->generate($qr)) }}">

                </div>


            </div>
            <div class="numero-pallet">
                {{ $pallet->etiqueta_numero }}
            </div>

            <div style="clear: both;"></div>

            <p class="producto"><strong>{{ $lote->producto->nombre }}</strong></p>
            <p><strong>{{ $lote->lote }}</strong> </p>
            <p><strong>CANTIDAD:</strong></p>
            <p><strong>{{ $pallet->cantidad }}</strong></p>
            <p ><strong>FECHA DE INGRESO: {{ $lote->fechaRecibido }}</strong></p>
    @endforeach



</body>

</html>
