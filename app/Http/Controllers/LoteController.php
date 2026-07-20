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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

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
                'operador' => 'required|string|max:255',
                'lt' => 'required|string|max:255',
                'placas' => 'required|string|max:255',
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
            
         
            $lote = Lote::create(array_merge($validatedData, [
                
                'usuario_id' => $usuario_id,
                'piezasLote' => $piezasLote, // ✅ Se agrega antes de insertar
                'ubi' => 'No Ubicado'
            ]));

$lotePallets = [];

for ($i = 1; $i <= $validatedData['numPalets']; $i++) {

 
    do {
        $codigo =
            rand(0, 9) .
            strtoupper(Str::random(3)) .
            '-' .
            rand(0, 9) .
            strtoupper(Str::random(5));

    } while (LotePallet::where('codigo', $codigo)->exists());

    $lotePallets[] = [
        'lote_id' => $lote->id,
        'codigo' => $codigo,
        'num_pallet' => $i,
        'cantidad' => $validatedData['piezasPalet'],
        'etiqueta_numero' => $i,
        'etiqueta_total' => $validatedData['numPalets'],
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

LotePallet::insert($lotePallets);

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
                 'operador' => 'required|string|max:255',
                'lt' => 'required|string|max:255',
                'placas' => 'required|string|max:255',
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
           
           
        $lote = Lote::with([
    'producto.cliente',
    'loteUbicaciones'
])->find($lote_id);
            if (!$lote) {
                return response()->json(['error' => 'Lote no encontrado']
                , 404);
            }

            if (!$lote->producto) {
                return response()->json(['error' => 'Producto no asociado al lote']
                , 404);
            }


$pallets = LotePallet::where('lote_id', $lote_id)->get();

            if ($pallets->isEmpty()) {
                return response()->json(['error' => 'No hay pallets registrados para este lote']
                , 404);
            }

          $pdf = Pdf::loadView('pdf.etiquetas', [
            'lote' => $lote,
            'pallets' => $pallets
        ]);

        return $pdf->stream("etiquetas_lote_{$lote->folio}.pdf");

        } catch (Exception $e) {
            return response()->json(['error' => 'Error en la impresión: ' . $e->getMessage()]
            , 500);
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
