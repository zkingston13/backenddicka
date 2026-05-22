<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Exception;

class ProductoController extends Controller
{
    /**
     * 🔹 Mostrar todos los productos con el nombre del cliente.
     */
    public function index()
    {
        try {
            $productos = Producto::with('cliente:id,razonSocial')->get();

            $productos = $productos->map(function ($producto) {
                return [
                    'id' => $producto->id,
                    'sku' => $producto->sku,
                    'nombre' => $producto->nombre,
                    'cliente' => $producto->cliente ? $producto->cliente->razonSocial : 'Sin Cliente',
                    'propiedades' => $producto->propiedades ?? '',
                    'caracteristicas' => $producto->caracteristicas ?? '',
                    'usuario_id' => $producto->usuario_id,
                    'usuarioModificacion' => $producto->usuarioModificacion,
                ];
            });

            return response()->json($productos, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }


    /**
     * 🔹 Crear un nuevo producto (Permitido para todos los usuarios).
     */
    public function store(Request $request)
    {
        try {
            // ✅ Validación de datos
            $validatedData = $request->validate([
                'sku' => 'required|string|max:100|unique:productos,sku',
                'nombre' => 'required|string|max:255',
                'cliente_id' => 'required|exists:clientes,id',
                'propiedades' => 'nullable|string',
                'caracteristicas' => 'nullable|string',
            ]);

            // ✅ Crear el producto
            $producto = Producto::create([
                'sku' => $validatedData['sku'],
                'nombre' => $validatedData['nombre'],
                'cliente_id' => $validatedData['cliente_id'],
                'propiedades' => $validatedData['propiedades'] ?? '',
                'caracteristicas' => $validatedData['caracteristicas'] ?? '',
                'usuario_id' => Auth::id(),
            ]);

            return response()->json(['message' => 'Producto registrado con éxito', 'producto' => $producto], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Error de validación', 'detalles' => $e->errors()], 422);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔹 Mostrar un producto por ID con el nombre del cliente.
     */
    public function show($id)
    {
        try {
            $producto = Producto::with('cliente:id,razonSocial')->find($id);

            if (!$producto) {
                return response()->json(['error' => 'Producto no encontrado'], 404);
            }

            return response()->json([
                'id' => $producto->id,
                'sku' => $producto->sku,
                'nombre' => $producto->nombre,
                'cliente' => $producto->cliente ? $producto->cliente->razonSocial : 'Sin Cliente',
                'propiedades' => $producto->propiedades ?? '',
                'caracteristicas' => $producto->caracteristicas ?? '',
                'usuario_id' => $producto->usuario_id,
                'usuarioModificacion' => $producto->usuarioModificacion,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔹 Actualizar un producto (Permitido para todos los usuarios).
     */
    public function update(Request $request, $id)
    {
        try {
            $producto = Producto::find($id);
            if (!$producto) {
                return response()->json(['error' => 'Producto no encontrado'], 404);
            }

            // ✅ Validación de datos
            $validatedData = $request->validate([
                'sku' => 'nullable|string|max:100|unique:productos,sku,' . $id,
                'nombre' => 'nullable|string|max:255',
                'cliente_id' => 'nullable|exists:clientes,id',
                'propiedades' => 'nullable|string',
                'caracteristicas' => 'nullable|string',
            ]);

            // ✅ Actualizar producto
            $producto->update([
                'sku' => $validatedData['sku'] ?? $producto->sku,
                'nombre' => $validatedData['nombre'] ?? $producto->nombre,
                'cliente_id' => $validatedData['cliente_id'] ?? $producto->cliente_id,
                'propiedades' => $validatedData['propiedades'] ?? $producto->propiedades,
                'caracteristicas' => $validatedData['caracteristicas'] ?? $producto->caracteristicas,
                'usuarioModificacion' => Auth::id(),
            ]);

            return response()->json(['message' => 'Producto actualizado con éxito', 'producto' => $producto], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Error de validación', 'detalles' => $e->errors()], 422);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔹 Eliminar un producto (Solo permitido para Administrador y Jefe de Operaciones).
     */
    public function destroy($id)
    {
        try {
            $usuario = Auth::user();

            // 🔹 Verificar permisos (solo Administrador y Jefe de Operaciones pueden eliminar)
            if (!in_array($usuario->rol_id, [1, 2])) {
                return response()->json(['error' => 'No tienes permisos para eliminar productos'], 403);
            }

            $producto = Producto::find($id);
            if (!$producto) {
                return response()->json(['error' => 'Producto no encontrado'], 404);
            }

            $producto->delete();

            return response()->json(['message' => 'Producto eliminado con éxito'], 200);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }
}
