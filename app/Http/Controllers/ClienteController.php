<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;

class ClienteController extends Controller
{
    /**
     * Mostrar todos los clientes con información de quién los creó y modificó.
     */
    public function index()
    {
        try {
            $clientes = Cliente::with('usuario', 'usuarioModificador')->get();
            return response()->json($clientes);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener clientes', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear un nuevo cliente.
     */
    public function store(Request $request)
    {
        try {
            // Validación de los datos
            $validatedData = $request->validate([
                'razonSocial' => 'required|string|max:255',
                'domicilio' => 'required|string|max:255',
                'usuario_id' => 'required|exists:usuarios,id',
            ]);

            // Crear cliente
            $cliente = Cliente::create($validatedData);

            return response()->json(['message' => 'Cliente registrado correctamente', 'cliente' => $cliente], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Error de validación', 'detalles' => $e->errors()], 422);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * Mostrar un cliente por ID con información del usuario creador y modificador.
     */
    public function show($id)
    {
        try {
            $cliente = Cliente::with('usuario', 'usuarioModificador')->find($id);
            if (!$cliente) {
                return response()->json(['error' => 'Cliente no registrado'], 404);
            }
            return response()->json($cliente);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener cliente', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar un cliente.
     */
    public function update(Request $request, $id)
    {
        try {
            // Validación de datos
            $validatedData = $request->validate([
                'razonSocial' => 'nullable|string|max:255',
                'domicilio' => 'nullable|string|max:255',
            ]);

            // Buscar el cliente
            $cliente = Cliente::find($id);
            if (!$cliente) {
                return response()->json(['error' => 'Cliente no registrado'], 404);
            }

            // Obtener usuario autenticado
            $usuarioModificador = Auth::id();

            // Actualizar solo los campos enviados en la solicitud
            $cliente->update(array_filter([
                'razonSocial' => $validatedData['razonSocial'] ?? $cliente->razonSocial,
                'domicilio' => $validatedData['domicilio'] ?? $cliente->domicilio,
                'usuarioModificacion' => $usuarioModificador,
            ], fn($value) => !is_null($value)));

            return response()->json(['message' => 'Cliente actualizado correctamente', 'cliente' => $cliente]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Error de validación', 'detalles' => $e->errors()], 422);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar un cliente.
     */
    public function destroy($id)
    {
        try {
            $cliente = Cliente::find($id);
            if (!$cliente) {
                return response()->json(['error' => 'Cliente no registrado'], 404);
            }

            $razonSocial = $cliente->razonSocial;
            $cliente->delete();

            return response()->json(['message' => 'Cliente eliminado correctamente']);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }
}
