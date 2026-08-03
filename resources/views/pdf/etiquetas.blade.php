<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 0;
            size: 10cm 10cm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        .etiqueta {
            width: 10cm;
            height: 10cm;
            position: relative;
            page-break-after: always;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .codigo {
            width: 8%;
            text-align: center;
            vertical-align: top;
            font-family: Courier New;
            font-size: 17px;
            font-weight: bold;
            line-height: 18px;
        }

        .centro {
            width: 60%;
            text-align: center;
            vertical-align: top;
        }

        .qr {
            width: 32%;
            text-align: center;
            vertical-align: top;
        }

        .logo {
            width: 170px;
            margin-top: 5px;
            margin-bottom: 10px;
        }

        .cliente {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }

        .sku {
            font-size: 16px;
            font-weight: bold;
            margin-top: 8px;
        }

        .producto {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }

        .lote {
            font-size: 22px;
            font-weight: bold;
            margin-top: 8px;
        }

        .cantidadTitulo {
            margin-top: 8px;
            font-size: 18px;
            font-weight: bold;
        }

        .cantidad {
            font-size: 40px;
            font-weight: bold;
        }

        .fecha {
            margin-top: 8px;
            font-size: 14px;
            font-weight: bold;
        }

        .ubicacion {
            margin-top: 8px;
            font-size: 18px;
            font-weight: bold;
        }

        .numeroPallet {
            font-size: 40px;
            font-weight: bold;
            margin-top: 10px;
        }

        .qr img {
            width: 110px;
            height: 110px;
        }

    </style>
</head>

<body>

@foreach($pallets as $pallet)

@php

$ubicacion = $lote->loteUbicaciones
->firstWhere('pallet_numero',$pallet->etiqueta_numero);

$qr=json_encode([
'lot'=>$lote->lote,
'codigo_pallet'=>$pallet->codigo,
'pallet_numero'=>$pallet->etiqueta_numero,
'cantidad'=>$pallet->cantidad,
'producto'=>$lote->producto->nombre,
'fecha_ingreso'=>$lote->fechaRecibido,
'ubicacion'=>$ubicacion->qr_ubicacion ?? ''
]);

@endphp

<div class="etiqueta">

<table>

<tr>

<td class="codigo">

@foreach(str_split($pallet->codigo) as $c)

{{$c}}<br>

@endforeach

</td>

<td class="centro">

<img class="logo" src="{{ public_path('img/logo.png') }}">

<div class="cliente">
{{ $lote->producto->cliente->razonSocial ?? '' }}
DES
{{ $lote->folio }}
</div>

<div class="sku">
SKU:
{{ $lote->producto->sku }}
</div>

<div class="producto">
{{ $lote->producto->nombre }}
</div>

<div class="lote">
{{ $lote->lote }}
</div>

<div class="cantidadTitulo">
CANTIDAD:
</div>

<div class="cantidad">
{{ $pallet->cantidad }}
</div>

<div class="fecha">
FECHA DE INGRESO:
{{ $lote->fechaRecibido }}
</div>

<div class="ubicacion">
UBICACIÓN

<br>

{{ $ubicacion->qr_ubicacion ?? 'SIN UBICACIÓN' }}

</div>

</td>

<td class="qr">

<img src="data:image/svg+xml;base64,{{ base64_encode(QrCode::size(130)->generate($qr)) }}">

<div class="numeroPallet">

{{ $pallet->etiqueta_numero }}

</div>

</td>

</tr>

</table>

</div>

@endforeach

</body>

</html>
