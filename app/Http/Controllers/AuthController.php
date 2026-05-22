<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\Usuario;

class AuthController extends Controller
{
    /**
     * 📌 Iniciar sesión y devolver un token para la app móvil
     */
    public function login(Request $request)
    {
        try {
            // 🔹 Validación de datos
            $request->validate([
                'nombreUsuario' => 'required|string',
                'password' => 'required|string|min:6',
            ]);

            // 🔹 Buscar usuario por nombreUsuario
            $usuario = Usuario::where('nombreUsuario', $request->nombreUsuario)->first();

            // 🔹 Verificar credenciales
            if (!$usuario || !Hash::check($request->password, $usuario->password)) {
                return response()->json(['error' => '❌ Credenciales incorrectas'], 401);
            }

            // 🔹 Verificar si la cuenta está activa
            if (!$usuario->IsActive) {
                return response()->json(['error' => '⚠️ Cuenta inactiva. Contacta al administrador'], 403);
            }

            // 🔹 Generar un token sin revocar los anteriores (ideal para móviles)
            $token = $usuario->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => '✅ Inicio de sesión exitoso',
                'usuario' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre,
                    'nombreUsuario' => $usuario->nombreUsuario,
                    'email' => $usuario->email,
                    'rol' => $usuario->rol ? $usuario->rol->puesto : 'Sin rol asignado'
                ],
                'token' => $token,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['error' => '❌ Error de validación', 'detalles' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => '❌ Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * 📌 Cerrar sesión (Eliminar solo el token actual).
     */
    public function logout(Request $request)
    {
        try {
            if (!$request->user()) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            // 🔹 Revocar el token actual
            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => '✅ Sesión cerrada correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => '❌ Error al cerrar sesión', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * 📌 Cerrar sesión en todos los dispositivos.
     */
    public function logoutAll(Request $request)
    {
        try {
            if (!$request->user()) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            // 🔹 Revocar todos los tokens del usuario
            $request->user()->tokens()->delete();

            return response()->json(['message' => '✅ Todas las sesiones cerradas correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => '❌ Error al cerrar sesiones', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * 📌 Obtener los datos del usuario autenticado
     */
    public function me(Request $request)
    {
        try {
            if (!$request->user()) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            return response()->json(['usuario' => $request->user()->load('rol')], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => '❌ Error al obtener usuario', 'detalles' => $e->getMessage()], 500);
        }
    }
}
