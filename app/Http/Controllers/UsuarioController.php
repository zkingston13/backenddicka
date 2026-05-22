<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UsuarioController extends Controller
{
    /**
     * Obtener todos los usuarios (Solo Jefe de Operaciones y Administrador).
     */
    public function index()
    {
        try {
            $usuarioAutenticado = Auth::user();
            if (!in_array($usuarioAutenticado->rol_id, [1, 2])) {
                return response()->json(['error' => 'Acceso no autorizado'], 403);
            }

            $usuarios = Usuario::with('rol')->get(); // 🔹 Cargar la relación con el rol
            return response()->json($usuarios);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener los usuarios',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear un nuevo usuario.
     */
    public function store(Request $request)
    {
        try {
            // Validación de los datos
            $validatedData = $request->validate([
                'nombre' => 'required|string|max:255',
                'nombreUsuario' => 'required|string|max:255|unique:usuarios',
                'numEmpleado' => 'required|integer|unique:usuarios',
                // Cambiamos "required" por "nullable" para que el email sea opcional
                'email' => 'nullable|email|unique:usuarios',
                'password' => 'required|string|min:6',
                'IsActive' => 'required|boolean',
                'rol_id' => 'required|exists:rols,id',
            ]);

            // Crear el usuario
            $usuario = Usuario::create([
                'nombre' => $validatedData['nombre'],
                'nombreUsuario' => $validatedData['nombreUsuario'],
                'numEmpleado' => $validatedData['numEmpleado'],
                // Si email no se envía o está vacío, se asigna null
                'email' => $validatedData['email'] ?? null,
                'password' => Hash::make($validatedData['password']),
                'IsActive' => $validatedData['IsActive'],
                'rol_id' => $validatedData['rol_id'],
            ]);

            return response()->json(['message' => 'Usuario creado exitosamente', 'usuario' => $usuario], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'detalles' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error inesperado al crear usuario',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Obtener un usuario por ID.
     */
    public function show($id)
    {
        try {
            $usuario = Usuario::find($id);

            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            return response()->json($usuario);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error inesperado al obtener el usuario',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un usuario.
     */
    public function update(Request $request, $id)
    {
        try {
            // Validación de los datos
            $validatedData = $request->validate([
                'nombre' => 'nullable|string|max:255',
                'nombreUsuario' => 'nullable|string|max:255|unique:usuarios,nombreUsuario,' . $id,
                'numEmpleado' => 'nullable|integer|unique:usuarios,numEmpleado,' . $id,
                // Email opcional
                'email' => 'nullable|email|max:255|unique:usuarios,email,' . $id,
                'password' => 'nullable|string|min:6',
                'IsActive' => 'nullable|boolean',
                'rol_id' => 'required|exists:rols,id',
            ]);

            // Buscar usuario
            $usuario = Usuario::find($id);
            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            // Obtener usuario autenticado
            $usuarioModificador = Auth::id();

            // Actualizar usuario
            $usuario->update([
                'nombre' => $validatedData['nombre'] ?? $usuario->nombre,
                'nombreUsuario' => $validatedData['nombreUsuario'] ?? $usuario->nombreUsuario,
                'numEmpleado' => $validatedData['numEmpleado'] ?? $usuario->numEmpleado,
                // Si no se envía email, se mantiene el actual
                'email' => $validatedData['email'] ?? $usuario->email,
                'password' => isset($validatedData['password']) ? Hash::make($validatedData['password']) : $usuario->password,
                'IsActive' => $validatedData['IsActive'] ?? $usuario->IsActive,
                'rol_id' => $validatedData['rol_id'],
                'usuarioModificacion' => $usuarioModificador,
            ]);

            return response()->json(['message' => 'Usuario actualizado exitosamente', 'usuario' => $usuario]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'detalles' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error inesperado al actualizar usuario',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Eliminar un usuario.
     */
    public function destroy($id)
    {
        try {
            $usuario = Usuario::find($id);

            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            // No permitir eliminar usuarios administradores
            if ($usuario->rol_id == 1) {
                return response()->json(['error' => 'No puedes eliminar un Administrador'], 403);
            }

            $usuario->delete();

            return response()->json(['message' => 'Usuario eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error inesperado al eliminar usuario',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Método para login y generación de token con Sanctum.
     */
    public function login(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:6'
            ]);

            $usuario = Usuario::where('email', $validatedData['email'])->first();

            if (!$usuario || !Hash::check($validatedData['password'], $usuario->password)) {
                return response()->json(['error' => 'Credenciales incorrectas'], 401);
            }

            $token = $usuario->createToken('auth_token')->plainTextToken;

            return response()->json(['message' => 'Inicio de sesión exitoso', 'token' => $token, 'usuario' => $usuario]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'detalles' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error inesperado en el inicio de sesión',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Método para cerrar sesión.
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();
            return response()->json(['message' => 'Sesión cerrada correctamente']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error inesperado al cerrar sesión',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }
}
