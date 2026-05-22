<?php

namespace App\Http\Controllers;

use App\Models\LoteUbicacion;
use Illuminate\Http\Request;
use App\Models\Lote;
use App\Models\LotePallet;
use App\Models\Producto;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\EscposImage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Exception;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

class LoteController extends Controller
{

    public function index()
    {
        try {
            $lotes = Lote::with('producto')->get();

            if ($lotes->isEmpty()) {
                return response()->json(['message' => 'No hay lotes registrados'], 200);
            }

            return response()->json($lotes, 200);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            // 🔹 Registrar en el log los datos recibidos
            \Log::info('📥 Datos recibidos en store:', $request->all());

            // 🔹 Validación de datos
            $validatedData = $request->validate([
                'folio' => 'required|integer',
                'producto_id' => 'required|integer|exists:productos,id',
                'lote' => 'required|string|max:255|unique:lotes,lote',
                'caducidad' => 'required|date',
                'fechaRecibido' => 'nullable|date',
                'numPalets' => 'required|integer|min:1',
                'piezasPalet' => 'required|integer|min:1',
                'unidadMedida' => 'required|string|max:50',
                'observaciones' => 'nullable|string',
                
            ]);

            // 🔹 Obtener el producto con los campos necesarios
            $producto = Producto::where('id', (int) $validatedData['producto_id'])
                ->select('id', 'sku', 'nombre', 'cliente_id')
                ->firstOrFail();

            // 🔹 Verificar que el producto tenga todos los campos requeridos
            if (!$producto->sku || !$producto->nombre || !$producto->cliente_id) {
                return response()->json([
                    'error' => '❌ El producto seleccionado no tiene todos los campos obligatorios',
                    'detalles' => [
                        'sku' => $producto->sku ? '✔️ OK' : '❌ Falta SKU',
                        'nombre' => $producto->nombre ? '✔️ OK' : '❌ Falta Nombre',
                        'cliente_id' => $producto->cliente_id ? '✔️ OK' : '❌ Falta Cliente ID'
                    ]
                ], 422);
            }

            // 🔹 Obtener usuario autenticado
            $usuario_id = Auth::id();
            if (!$usuario_id) {
                return response()->json(['error' => '❌ Usuario no autenticado'], 401);
            }

            // 🔹 Calcular piezasLote antes de la creación
            $piezasLote = $validatedData['numPalets'] * $validatedData['piezasPalet'];

            // 🔹 Crear lote con `piezasLote`
            $lote = Lote::create(array_merge($validatedData, [
                'usuario_id' => $usuario_id,
                'piezasLote' => $piezasLote, // ✅ Se agrega antes de insertar
                'ubi' => 'No Ubicado'
            ]));

            // 🔹 Crear registros en `lote_pallets`
            $lotePallets = [];
            for ($i = 1; $i <= $validatedData['numPalets']; $i++) {
                $lotePallets[] = [
                    'lote_id' => $lote->id,
                    'num_pallet' => $i,
                    'cantidad' => $validatedData['piezasPalet'],
                    'etiqueta_numero' => $i,
                    'etiqueta_total' => $validatedData['numPalets'],
                    'created_at' => now(),
                    'updated_at' => now(),
                    
                ];
            }
            LotePallet::insert($lotePallets); // ✅ Inserción masiva para mejor rendimiento

            return response()->json([
                'message' => '✅ Lote registrado con éxito y pallets generados',
                'lote' => $lote
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => '❌ Error de validación', 'detalles' => $e->errors()], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => '❌ Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => '❌ Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $lote = Lote::find($id);
            if (!$lote) {
                return response()->json(['error' => 'Lote no encontrado'], 404);
            }
            return response()->json($lote, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }
    public function update(Request $request, $id)
    {
        try {
            $lote = Lote::find($id);
            if (!$lote) {
                return response()->json(['error' => 'Lote no encontrado'], 404);
            }

            $validatedData = $request->validate([
                'folio' => 'nullable|integer',
                'producto_id' => 'nullable|integer|exists:productos,id',
                'lote' => 'nullable|string|max:255|unique:lotes,lote,' . $id,
                'caducidad' => 'nullable|date',
                'numPalets' => 'nullable|integer|min:1',
                'piezasPalet' => 'nullable|integer|min:1',
                'unidadMedida' => 'nullable|string|max:50',
                'fechaRecibido' => 'nullable|date',
                'observaciones' => 'nullable|string'
            ]);

            $usuarioModificador = Auth::id();
            if (!$usuarioModificador) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            // Calcular piezas por lote
            $piezasLote = $validatedData['numPalets'] * $validatedData['piezasPalet'];

            // Actualizar datos del lote
            $lote->update(array_merge($validatedData, [
                'usuarioModificacion' => $usuarioModificador,
                'piezasLote' => $piezasLote, // 🔹 Se actualiza el campo piezasLote
                'ubi' => 'No Ubicado'
            ]));

            // 🔹 Eliminar los pallets antiguos de este lote
            LotePallet::where('lote_id', $lote->id)->delete();

            // 🔹 Insertar los nuevos registros de pallets
            $nuevosPallets = [];
            for ($i = 1; $i <= $validatedData['numPalets']; $i++) {
                $nuevosPallets[] = [
                    'lote_id' => $lote->id,
                    'num_pallet' => $i,
                    'cantidad' => $validatedData['piezasPalet'],
                    'etiqueta_numero' => $i,
                    'etiqueta_total' => $validatedData['numPalets'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            LotePallet::insert($nuevosPallets); // ✅ Inserción masiva optimizada

            return response()->json(['message' => '✅ Lote y pallets actualizados con éxito'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => '❌ Error de validación', 'detalles' => $e->errors()], 422);
        } catch (QueryException $e) {
            return response()->json(['error' => '❌ Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => '❌ Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $usuario = Auth::user();
            if (!$usuario) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            $rolesPermitidos = ['Administrador', 'Jefe de Operaciones', 'Supervisor'];
            if (!in_array($usuario->rol, $rolesPermitidos)) {
                return response()->json(['error' => 'No tienes permisos para eliminar lotes'], 403);
            }

            $lote = Lote::find($id);
            if (!$lote) {
                return response()->json(['error' => 'Lote no encontrado'], 404);
            }

            LotePallet::where('lote_id', $lote->id)->delete();
            $lote->delete();

            return response()->json(['message' => '✅ Lote eliminado con éxito'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => '❌ Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }
    public function imprimirEtiquetas($lote_id)
    {
        try {
            // ✅ Obtener el lote con el producto y cliente asociados
            $lote = Lote::with(['producto.cliente'])->find($lote_id);
            if (!$lote) {
                return response()->json(['error' => '❌ Lote no encontrado'], 404);
            }

            if (!$lote->producto) {
                return response()->json(['error' => '❌ Producto no asociado al lote'], 404);
            }

            $pallets = LotePallet::where('lote_id', $lote_id)->get();
            if ($pallets->isEmpty()) {
                return response()->json(['error' => '❌ No hay pallets registrados para este lote'], 404);
            }

            // ✅ Obtener el nombre del cliente (razonSocial en lugar de nombre)
            $clienteNombre = $lote->producto->cliente->razonSocial ?? 'Desconocido';
            $unidadMedida = $lote->unidadMedida ?? 'No definida';

            // ✅ Conectar a la impresora Zebra por red
            $connector = new NetworkPrintConnector("172.20.12.9", 9100);
            $printer = new Printer($connector);

            foreach ($pallets as $pallet) {
                // ✅ Obtener la cantidad de piezas en este pallet
                $cantidadPiezas = $pallet->cantidad;

                // ✅ Generar los datos para el código QR incluyendo `cantidad`
                $qrContent = json_encode([
                    'lote_id' => $lote->id,
                    'pallet_numero' => $pallet->etiqueta_numero,
                    'cantidad' => $cantidadPiezas // 📌 Se agrega la cantidad de piezas en el QR
                ]);

                $zpl = "^XA\n";

                $zpl .= "^FO50,30^GFA,^FO50,50^GFA,3872,3872,32,hK0F,hK0F8,hK0FC,hK0FE,hK0FF,hK0FFC,hK07FE,hK07FF8,hK0IFC,hK0IFE,hK0JF,hJ03JF8,hJ0KFC,hI03LF,hI0MF8,hH0NF8,hG01OF8,gY07F03NF8,gY0FF803MFE,gY0FFC00MFE,gV03C0FFCI0MF8,gU01FF0FFCJ03KFE,gS03C3FF0FFCK01KF,gS0FF3FF0FFCL03JF8,gS0FF9FF0FFCM07IFC,gP07F1FF9FF0FFCN0JF,gP0FF9FF8FF8FFCO03FF,gL01FF0FFCFF8FF8FFCP03FC,gL01FF0FFDFF9FF8FFEQ07C,gJ07C1FF0FFDFF8FF8FFCQ01C,gJ0FF9FF0FFDFF8FF8FFC,gI01FF9FF0FFDFF8FF8FFET0E,gG07F8FF9FF0FFCFF8FF8FFES01F,X03F8FF8FF9FF8FFCFF8FF8FFCT0FC,X07F9FFCFF9FF8FFCFF8FF8FFCT07E,X0FFDFF8FF9FF8FFDFF8FFCFFCT07F,U03F8FFDFF8FF9FF8FFDFF8FF8FFET07F8,U03FCFFDFF8FF9FF8FFCFF8FF87FET03FE,S0F87FCFFDFF8FF9FF8FFCFF8FF87FET03FF,S0FC3FCFFCFF8FF9FF8FFCFF8FF87FET03FF8,P01F1FC3FCFFC7F8FFDFF87FCFF9FFC7FET03FFE,N0103F9FE7FCFFC7FCFFDFF87FCFF8FF87FET01IF8,N0FE3FDFC7FCFFC7FEFFDFF87FEFFC7F87FET01IFC,L070FF3FDFC7FC7FC7FEFFDFF87FEFFC7F87FET01IFE,J078F9FF3FCFE7FC7FC7FEFFDFF87FEFFC7F87FEU0JF,J0FDFCFF3F8FC3FC3FC7FEFFDFF87FEFFC7F87FEU0JFC,003EFDFC7E070380F03FC3F8FF8FF87FEFFC7FC7FEU07IFE,3CFEFC3018T01E07F87FEFFC7FE7FEU0JFE,7E7C3gH03F07FEFFC7FE7FEU07JF,7E38gJ0E07FEFFC7FE7FEU03JF8,3CgN07FCFFC7FE3FEU03JFC,gP01C0FFC7FE3FEU03JF8,gS07FC7FE3FFU01IFE,gS01F87FE3FFU01IF8,gV07FE3FFU01IF,gV07FE3FFU01FFE,gV07FE3FF8T01FF8,gV01F03FFU01FF,gY01FFU01FE,gY01FFV0FE,gY03FFV0FC,gY01FFV0F8,h0FEV07,hX02,hJ051BOF6,hI01SFC,hI07SFE,hI01SFC,hJ078,,hK01PFE,hK03QF,hK03PFE,hM07NF,hM0OF,hL01OF8,hL01OFC,R02L0FEK08K078S03L01C,01KFCI01FCI03JFI03FJ03FCJ0FF8M07LFE,01LF8001FEI0KFC007FJ07FCJ0FF8M0NF,01LFE001FE003LF007FI01FFJ01FFCL01NF8,01MF801FC007FF03FF807FI03FCJ01FFCM0NF,01FCI03FC01FC01FEI03FE03F001FFK03FFE,01F8J0FF01F803F8J0FE03F003FCK03F3FM01LF8,01FCJ07F01FC07FK07F03F007F8K07E3F8L01LF8,01F8J03F81FC07EK03E03F80FCL0FE1FCL03LF8,01F8J01F81F807EN03F03F8K01F80FCM0LF8,01F8J01F81F807CN03F8FEL01F80FCQ018,01F8J01F81F80FCN03IFEL03F007EM07KF,01F8J01FC1F80FEN03JFL03F007FM07KF,01F8J01FC1F80FCN03JF8K07E003FM07JFE,01F8J01F81F80FCN03FF1FEK0FE003F8M07IF,01F8J01F81F807EN03FC07F8I01FE003FCM07IF8,01F8J01F81F807EK03E03F803FCI01LFEM0JFC,01F8J03F81F807EK03F03F001FEI03LFEM0JFC,01FCJ07F01F807FK07F03FI0FFI03LFEM0JFC,01F8I01FE01F803F8I01FE03FI03FC007EJ03FM03IF,01FCI0FFC01F801FFI07FC07FI01FF00FEJ03F8L07IF8,01MF001FC00IF97FF007FJ07FC0FCJ01FCL07IF8,01LFE001FE003KFE007F8I03FF1FCJ01FEL07IF8,01LFI01FE001KFC003FJ01FF3FCJ01FFL03IF,01KFJ01FCI03IFCI03FK0FF3F8K07F,hP03IF,N0FFK01FCK07E01IF8J01FEI078M01IF,01CJ07FFEI01IFCI01FF01IF8J0IFC03FEN03F,01CI01F01FI03F03E0601C7801F002003E03E078EN01E,03CI078003C00FK06038J0F00200FI0407P07F8,03C001EJ0F01CK0603CI01F00701CK078O07F8,01C003CJ03038K0601FI01F007038K03E,03C003CJ03038K06007E001F007078K01FCO0C,01C007CJ0387L07003F801F0030F8L07FN07E,03C007CJ0307L06I07C01F0030FN078M0FE,03C003CJ03078I0F06J0E01F007878M01E,03C003CJ07038I0F87J0701F007038M01EM07C,03C001EJ0E01EI0F078600600F00701EJ01801EM0FE,01CI0F8003E00FI0F078E00E01F00700FJ01C01EM0FE,03CI03E00FC00FC00F078F01E01F00780FC01F1F03CM07E,01FFC00IFEI01IFC0703FF800F007001IFC03FFN03,007FJ064L0C8K038Q0F8I04O0FC,hQ0F8,:^FS,^FS^FS\n";

                $zpl .= "^FO500,50^BQN,2,6\n";
                $zpl .= "^FDQA,{$qrContent}^FS\n";

                $inicioTextoY = 300;
                $espaciado = 55;

                // ✅ Datos en el orden correcto
                $zpl .= "^FO20," . ($inicioTextoY) . "^A0N,40,40^FDCliente: {$clienteNombre}^FS\n";
                $zpl .= "^FO20," . ($inicioTextoY + 1 * $espaciado) . "^A0N,40,40^FDFolio: {$lote->folio}^FS\n";
                $zpl .= "^FO20," . ($inicioTextoY + 2 * $espaciado) . "^A0N,40,40^FDSKU: {$lote->producto->sku}^FS\n";
                $zpl .= "^FO20," . ($inicioTextoY + 3 * $espaciado) . "^A0N,40,40^FDProducto: {$lote->producto->nombre}^FS\n";
                $zpl .= "^FO20," . ($inicioTextoY + 4 * $espaciado) . "^A0N,40,40^FDUnidad: {$unidadMedida}^FS\n";
                $zpl .= "^FO20," . ($inicioTextoY + 5 * $espaciado) . "^A0N,40,40^FDLote: {$lote->lote}^FS\n";
                $zpl .= "^FO20," . ($inicioTextoY + 6 * $espaciado) . "^A0N,40,40^FDCantidad: {$cantidadPiezas}^FS\n"; // ✅ Corregido
                $zpl .= "^FO20," . ($inicioTextoY + 7 * $espaciado) . "^A0N,40,40^FDEtiqueta: {$pallet->etiqueta_numero}/{$pallet->etiqueta_total}^FS\n";
                $zpl .= "^FO20," . ($inicioTextoY + 8 * $espaciado) . "^A0N,40,40^FDFecha Ingreso: " . ($lote->fechaRecibido ?? now()->toDateString()) . "^FS\n";

                $zpl .= "^XZ\n"; // 🔹 Finalizar etiqueta

                // ✅ Enviar a la impresora
                $printer->text($zpl);
                $printer->cut();
            }

            // ✅ Cerrar conexión con la impresora
            $printer->close();

            return response()->json(['message' => '✅ Etiquetas impresas correctamente'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => '❌ Error en la impresión: ' . $e->getMessage()], 500);
        }
    }

    public function buscarPorLote($lote)
    {
        try {
            $resultado = Lote::where('lote', 'LIKE', "%{$lote}%")
                ->with('loteUbicaciones:lote_id,qr_ubicacion')
                ->with('producto:id,nombre')
                ->get();

            return response()->json($resultado, 200);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    public function lotesUbicados()
    {
        try {
            $lotes = Lote::where('ubi', 'Ubicado')
                ->with('loteUbicaciones:lote_id,qr_ubicacion')
                ->with('producto:id,nombre')
                ->paginate(10);

            return response()->json($lotes, 200);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    public function lotesNoUbicados()
    {
        try {
            $lotes = Lote::where('ubi', 'No Ubicado')
            ->with('producto:id,nombre')
            ->paginate(100);

            return response()->json($lotes, 200);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    public function detalleLote($id)
    {
        try {
            $lote = Lote::with('loteUbicaciones:lote_id,qr_ubicacion')
            ->with('producto:id,nombre')
            ->find($id);

            if (!$lote) {
                return response()->json(['error' => 'Lote no encontrado'], 404);
            }
                return response()->json($lote, 200);

        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

}
