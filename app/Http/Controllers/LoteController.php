<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\LotePallet;
use App\Models\LoteUbicacion;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoteController extends Controller
{
    /**
     * Obtener todos los lotes que todavía no están en salida.
     */
    public function index()
    {
        try {
            $lotes = Lote::with('producto')
                ->where('en_salida', 0)
                ->get();

            if ($lotes->isEmpty()) {
                return response()->json([
                    'message' => 'No hay lotes registrados'
                ], 200);
            }

            return response()->json($lotes, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar un lote y generar sus pallets.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            Log::info('Datos recibidos en store de lotes:', $request->all());

            $validatedData = $request->validate([
                'folio' => 'required|integer',
                'producto_id' => 'required|integer|exists:productos,id',
                'lote' => 'required|string|max:255|unique:lotes,lote',
                'caducidad' => 'required|date',
                'fechaRecibido' => 'nullable|date',
                'numPalets' => 'required|integer|min:1',
                'piezasPalet' => 'required|integer|min:1',
                'unidadMedida' => 'required|string|max:50',
                'operador' => 'required|string|max:50',
                'lt' => 'required|string|max:50',
                'placas' => 'required|string|max:50',
                'observaciones' => 'nullable|string',
            ]);

            $producto = Producto::where(
                'id',
                (int) $validatedData['producto_id']
            )
                ->select(
                    'id',
                    'sku',
                    'nombre',
                    'cliente_id'
                )
                ->firstOrFail();

            if (
                !$producto->sku ||
                !$producto->nombre ||
                !$producto->cliente_id
            ) {
                DB::rollBack();

                return response()->json([
                    'error' => 'El producto seleccionado no tiene todos los campos obligatorios',
                    'detalles' => [
                        'sku' => $producto->sku
                            ? 'OK'
                            : 'Falta SKU',

                        'nombre' => $producto->nombre
                            ? 'OK'
                            : 'Falta nombre',

                        'cliente_id' => $producto->cliente_id
                            ? 'OK'
                            : 'Falta cliente ID',
                    ]
                ], 422);
            }

            $usuarioId = Auth::id();

            if (!$usuarioId) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $piezasLote =
                $validatedData['numPalets'] *
                $validatedData['piezasPalet'];

            $lote = Lote::create(array_merge(
                $validatedData,
                [
                    'usuario_id' => $usuarioId,
                    'piezasLote' => $piezasLote,
                    'ubi' => 'No Ubicado',
                ]
            ));

            $lotePallets = [];

            for (
                $numeroPallet = 1;
                $numeroPallet <= $validatedData['numPalets'];
                $numeroPallet++
            ) {
                $lotePallets[] = [
                    'lote_id' => $lote->id,

                    /*
                     * Código único para identificar el pallet.
                     * Ejemplo: PLT-A7K9X2M4
                     */
                    'codigo' => $this->generarCodigoPallet(),

                    'num_pallet' => $numeroPallet,
                    'cantidad' => $validatedData['piezasPalet'],
                    'etiqueta_numero' => $numeroPallet,
                    'etiqueta_total' => $validatedData['numPalets'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            LotePallet::insert($lotePallets);

            DB::commit();

            return response()->json([
                'message' => 'Lote registrado con éxito y pallets generados',
                'lote' => $lote->load([
                    'producto',
                ]),
                'pallets' => LotePallet::where(
                    'lote_id',
                    $lote->id
                )
                    ->orderBy('num_pallet')
                    ->get()
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error de validación',
                'detalles' => $e->errors()
            ], 422);
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Error de base de datos al crear lote', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error inesperado al crear lote', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un lote.
     */
    public function show($id)
    {
        try {
            $lote = Lote::with([
                'producto.cliente',
                'loteUbicaciones',
            ])->find($id);

            if (!$lote) {
                return response()->json([
                    'error' => 'Lote no encontrado'
                ], 404);
            }

            return response()->json($lote, 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un lote y regenerar sus pallets cuando sea necesario.
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $lote = Lote::find($id);

            if (!$lote) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Lote no encontrado'
                ], 404);
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
                'operador' => 'nullable|string|max:50',
                'lt' => 'nullable|string|max:50',
                'placas' => 'nullable|string|max:50',
                'observaciones' => 'nullable|string',
            ]);

            $usuarioModificador = Auth::id();

            if (!$usuarioModificador) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            /*
             * Si no vienen estos valores en la petición,
             * usamos los que ya tiene el lote.
             */
            $numPalets = $validatedData['numPalets']
                ?? $lote->numPalets;

            $piezasPalet = $validatedData['piezasPalet']
                ?? $lote->piezasPalet;

            $piezasLote = $numPalets * $piezasPalet;

            $datosActualizar = array_merge(
                $validatedData,
                [
                    'numPalets' => $numPalets,
                    'piezasPalet' => $piezasPalet,
                    'usuarioModificacion' => $usuarioModificador,
                    'piezasLote' => $piezasLote,
                    'ubi' => 'No Ubicado',
                ]
            );

            $lote->update($datosActualizar);

            /*
             * Se eliminan los pallets anteriores y se crean nuevamente.
             */
            LotePallet::where(
                'lote_id',
                $lote->id
            )->delete();

            $nuevosPallets = [];

            for (
                $numeroPallet = 1;
                $numeroPallet <= $numPalets;
                $numeroPallet++
            ) {
                $nuevosPallets[] = [
                    'lote_id' => $lote->id,
                    'codigo' => $this->generarCodigoPallet(),
                    'num_pallet' => $numeroPallet,
                    'cantidad' => $piezasPalet,
                    'etiqueta_numero' => $numeroPallet,
                    'etiqueta_total' => $numPalets,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            LotePallet::insert($nuevosPallets);

            DB::commit();

            return response()->json([
                'message' => 'Lote y pallets actualizados con éxito',
                'lote' => $lote->fresh(),
                'pallets' => LotePallet::where(
                    'lote_id',
                    $lote->id
                )
                    ->orderBy('num_pallet')
                    ->get()
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error de validación',
                'detalles' => $e->errors()
            ], 422);
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Error de base de datos al actualizar lote', [
                'lote_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error inesperado al actualizar lote', [
                'lote_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un lote.
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $usuario = Auth::user();

            if (!$usuario) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $rolesPermitidos = [
                'Administrador',
                'Jefe de Operaciones',
                'Supervisor'
            ];

            if (!in_array($usuario->rol, $rolesPermitidos)) {
                DB::rollBack();

                return response()->json([
                    'error' => 'No tienes permisos para eliminar lotes'
                ], 403);
            }

            $lote = Lote::find($id);

            if (!$lote) {
                DB::rollBack();

                return response()->json([
                    'error' => 'Lote no encontrado'
                ], 404);
            }

            LotePallet::where(
                'lote_id',
                $lote->id
            )->delete();

            $lote->delete();

            DB::commit();

            return response()->json([
                'message' => 'Lote eliminado con éxito'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar el PDF de etiquetas de un lote.
     */
    public function imprimirEtiquetas($loteId)
    {
        try {
            $lote = Lote::with([
                'producto.cliente',
                'loteUbicaciones',
            ])->find($loteId);

            if (!$lote) {
                return response()->json([
                    'error' => 'Lote no encontrado'
                ], 404);
            }

            if (!$lote->producto) {
                return response()->json([
                    'error' => 'Producto no asociado al lote'
                ], 404);
            }

            $pallets = LotePallet::where(
                'lote_id',
                $loteId
            )
                ->orderBy('etiqueta_numero')
                ->get();

            if ($pallets->isEmpty()) {
                return response()->json([
                    'error' => 'No hay pallets registrados para este lote'
                ], 404);
            }

            /*
             * Generar código para pallets antiguos que todavía
             * tengan el campo codigo vacío o en null.
             */
            foreach ($pallets as $pallet) {
                if (empty($pallet->codigo)) {
                    $pallet->codigo = $this->generarCodigoPallet();
                    $pallet->save();
                }
            }

            /*
             * 10 centímetros equivalen aproximadamente
             * a 283.46 puntos en DomPDF.
             */
            $pdf = Pdf::loadView('pdf.etiquetas', [
                'lote' => $lote,
                'pallets' => $pallets,
            ])->setPaper([
                0,
                0,
                283.46,
                283.46
            ]);

            return $pdf->stream(
                "etiquetas_lote_{$lote->folio}.pdf",
                [
                    'Attachment' => false
                ]
            );
        } catch (Exception $e) {
            Log::error('Error al generar etiquetas', [
                'lote_id' => $loteId,
                'error' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Error al generar las etiquetas',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar lotes por su número de lote.
     */
    public function buscarPorLote($lote)
    {
        try {
            $resultado = Lote::where(
                'lote',
                'LIKE',
                "%{$lote}%"
            )
                ->with([
                    'loteUbicaciones:lote_id,qr_ubicacion,pallet_numero',
                    'producto:id,nombre'
                ])
                ->get();

            return response()->json($resultado, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lotes ubicados.
     */
    public function lotesUbicados()
    {
        try {
            $lotes = Lote::where(
                'ubi',
                'Ubicado'
            )
                ->with([
                    'loteUbicaciones:lote_id,qr_ubicacion,pallet_numero',
                    'producto:id,nombre'
                ])
                ->paginate(10);

            return response()->json($lotes, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lotes no ubicados.
     */
    public function lotesNoUbicados()
    {
        try {
            $lotes = Lote::where(
                'ubi',
                'No Ubicado'
            )
                ->with('producto:id,nombre')
                ->paginate(100);

            return response()->json($lotes, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el detalle completo de un lote.
     */
    public function detalleLote($id)
    {
        try {
            $lote = Lote::with([
                'loteUbicaciones:lote_id,qr_ubicacion,pallet_numero',
                'producto.cliente',
            ])->find($id);

            if (!$lote) {
                return response()->json([
                    'error' => 'Lote no encontrado'
                ], 404);
            }

            /*
             * También devolvemos los pallets para que React pueda
             * conocer código, cantidad y número de etiqueta.
             */
            $lote->setRelation(
                'pallets',
                LotePallet::where('lote_id', $id)
                    ->orderBy('num_pallet')
                    ->get()
            );

            return response()->json($lote, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener inventario general agrupado por SKU.
     */
    public function inventarioGeneral()
    {
        try {
            $inventario = DB::table('lotes')
                ->join(
                    'productos',
                    'lotes.producto_id',
                    '=',
                    'productos.id'
                )
                ->join(
                    'clientes',
                    'productos.cliente_id',
                    '=',
                    'clientes.id'
                )
                ->select(
                    'productos.sku',
                    'productos.nombre as nombre',
                    'clientes.razonSocial as cliente',

                    DB::raw(
                        "IF(
                            COUNT(DISTINCT lotes.ubi) > 1,
                            'Múltiples',
                            MIN(lotes.ubi)
                        ) as ubicacion"
                    ),

                    DB::raw(
                        "CONCAT(
                            'Total: ',
                            SUM(lotes.piezasLote),
                            ' ',
                            MIN(lotes.unidadMedida)
                        ) as totalPiezas"
                    )
                )
                ->groupBy(
                    'productos.sku',
                    'productos.nombre',
                    'clientes.razonSocial'
                )
                ->get();

            if ($inventario->isEmpty()) {
                return response()->json([
                    'message' => 'No hay existencias en el inventario'
                ], 200);
            }

            return response()->json($inventario, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Error en la base de datos al consolidar inventario',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error inesperado al consolidar inventario',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el desglose de lotes por SKU.
     */
    public function desglosePorSku($sku)
    {
        try {
            $lotes = DB::table('lotes')
                ->join(
                    'productos',
                    'lotes.producto_id',
                    '=',
                    'productos.id'
                )
                ->select(
                    'lotes.id',
                    'lotes.folio',
                    'lotes.lote',
                    'lotes.caducidad',
                    'lotes.numPalets',
                    'lotes.piezasPalet',
                    'lotes.piezasLote as cantidad',
                    'lotes.ubi as ubicacion',
                    'lotes.unidadMedida'
                )
                ->where(
                    'productos.sku',
                    '=',
                    $sku
                )
                ->get();

            if ($lotes->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron lotes para el SKU especificado'
                ], 404);
            }

            return response()->json($lotes, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Error en la base de datos al obtener desglose',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Filtrar lotes por folio o número de lote.
     */
    public function filtrarPorFolioYLote(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'lote' => 'nullable|string|max:255',
                'folio' => 'nullable|integer',
            ]);

            $queryUbicados = LoteUbicacion::with([
                'lote'
            ])
                ->whereHas('lote', function ($query) {
                    $query->where(
                        'piezasPalet',
                        '>',
                        0
                    );
                });

            if (!empty($validatedData['lote'])) {
                $queryUbicados->whereHas(
                    'lote',
                    function ($query) use ($validatedData) {
                        $query->where(
                            'lote',
                            'LIKE',
                            '%' . $validatedData['lote'] . '%'
                        );
                    }
                );
            } elseif (!empty($validatedData['folio'])) {
                $queryUbicados->whereHas(
                    'lote',
                    function ($query) use ($validatedData) {
                        $query->where(
                            'folio',
                            $validatedData['folio']
                        );
                    }
                );
            }

            $ubicados = $queryUbicados->get();

            $lotesUbicadosIds = $ubicados
                ->pluck('lote_id')
                ->unique()
                ->toArray();

            $queryNoUbicados = Lote::whereNotIn(
                'id',
                $lotesUbicadosIds
            )
                ->where(
                    'piezasPalet',
                    '>',
                    0
                );

            if (!empty($validatedData['lote'])) {
                $queryNoUbicados->where(
                    'lote',
                    'LIKE',
                    '%' . $validatedData['lote'] . '%'
                );
            } elseif (!empty($validatedData['folio'])) {
                $queryNoUbicados->where(
                    'folio',
                    $validatedData['folio']
                );
            }

            $noUbicados = $queryNoUbicados
                ->get()
                ->map(function ($lote) {
                    return [
                        'id' => $lote->id,
                        'lote_id' => $lote->id,
                        'qr_ubicacion' => null,
                        'pallet_numero' => 1,
                        'lote' => $lote,
                    ];
                });

            $todosLosLotes = $ubicados->concat(
                $noUbicados
            );

            return response()->json([
                'data' => $todosLosLotes
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'detalles' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar un código único para cada pallet.
     *
     * Ejemplo:
     * PLT-A7K9X2M4
     */
    private function generarCodigoPallet(): string
    {
        do {
            $codigo = 'PLT-' . strtoupper(
                Str::random(8)
            );
        } while (
            LotePallet::where(
                'codigo',
                $codigo
            )->exists()
        );

        return $codigo;
    }

/**
 * Generar la etiqueta PDF de un solo pallet.
 */
public function imprimirEtiquetaPallet($loteId, $palletNumero)
{
    try {
        $lote = Lote::with([
            'producto.cliente',
            'loteUbicaciones',
        ])->find($loteId);

        if (!$lote) {
            return response()->json([
                'error' => 'Lote no encontrado',
            ], 404);
        }

        if (!$lote->producto) {
            return response()->json([
                'error' => 'Producto no asociado al lote',
            ], 404);
        }

        $pallet = LotePallet::where('lote_id', $loteId)
            ->where('etiqueta_numero', $palletNumero)
            ->first();

        if (!$pallet) {
            return response()->json([
                'error' => 'Pallet no encontrado',
            ], 404);
        }

        if (empty($pallet->codigo)) {
            $pallet->codigo = $this->generarCodigoPallet();
            $pallet->save();
        }

        /*
         * La vista utiliza un foreach, por eso se manda
         * una colección con un solo pallet.
         */
        $pallets = collect([$pallet]);

        $pdf = Pdf::loadView('pdf.etiquetas', [
            'lote' => $lote,
            'pallets' => $pallets,
        ])->setPaper([
            0,
            0,
            283.46,
            283.46,
        ]);

        return $pdf->stream(
            "etiqueta_{$lote->lote}_pallet_{$palletNumero}.pdf",
            [
                'Attachment' => false,
            ]
        );
    } catch (Exception $e) {
        Log::error('Error al generar etiqueta del pallet', [
            'lote_id' => $loteId,
            'pallet_numero' => $palletNumero,
            'error' => $e->getMessage(),
            'archivo' => $e->getFile(),
            'linea' => $e->getLine(),
        ]);

        return response()->json([
            'error' => 'Error al generar la etiqueta',
            'detalles' => $e->getMessage(),
        ], 500);
    }
}
}
