<?php

namespace App\Http\Controllers;

use App\Services\TwitchService;
use App\Exceptions\TwitchApiException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations\Get;
use OpenApi\Annotations\Info;
use OpenApi\Annotations\JsonContent;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;

/**
 * @OA\Info(
 *     title="Twitch Analytics API",
 *     version="1.0.0",
 *     description="API para obtener datos de usuarios y streams desde Twitch.",
 *     @OA\Contact(email="mariahaddad@hotmail.fr")
 * )
 */


/**
 * Class AnalyticsController
 *
 * Controlador para gestionar las peticiones de la API relacionadas con Twitch.
 */
class AnalyticsController extends Controller
{
    /** @var TwitchService La instancia del servicio de Twitch. */
    private TwitchService $twitchService;

    /**
     * Constructor de AnalyticsController.
     *
     * @param TwitchService $twitchService Inyección de dependencia del servicio de Twitch.
     */
    public function __construct(TwitchService $twitchService)
    {
        $this->twitchService = $twitchService;
    }

    /**
     * @OA\Get(
     *      path="/api/analytics/user",
     *      operationId="getUserById",
     *      tags={"Usuarios"},
     *      summary="Obtener información de un usuario por su ID",
     *      description="Devuelve la información completa de un usuario de Twitch basado en su ID.",
     *      @OA\Parameter(
     *          name="id",
     *          in="query",
     *          required=true,
     *          description="El ID del usuario de Twitch.",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Operación exitosa",
     *          @OA\JsonContent(ref="#/components/schemas/User")
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Invalid or missing 'id' parameter."
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized. Twitch access token is invalid or has expired."
     *      ), 
     *      @OA\Response(
     *          response=404,
     *          description="User not found."
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error."
     *      )
     * )
     *
     * @param Request $request La petición HTTP.
     * @return JsonResponse La información del usuario o un mensaje de error.
     */
    public function getUser(Request $request): JsonResponse
    {
        // Validar que el parámetro 'id' esté presente y sea un string no vacío.
        $validator = Validator::make($request->all(), [
            'id' => 'required|string|min:1'
        ]);

        // Si la validación falla, devolver una respuesta de error 400.
        if ($validator->fails()) {
            return response()->json([
                'error' => "Invalid or missing 'id' parameter."
            ], 400);
        }

        // Intentar obtener los datos del usuario desde el servicio de Twitch.
        try {
            $userId = $request->query('id');
            $user = $this->twitchService->getUserById($userId);

            // Si el servicio no devuelve ningún usuario, significa que no se encontró.
            if (!$user) {
                return response()->json([
                    'error' => 'User not found.'
                ], 404);
            }

            // Devolver la información del usuario con un formato específico y estado 200.
            return response()->json([
                //'user' => $user,
                'id' => $user['id'],
                'login' => $user['login'],
                'display_name' => $user['display_name'],
                'type' => $user['type'],
                'broadcaster_type' => $user['broadcaster_type'],
                'description' => $user['description'],
                'profile_image_url' => $user['profile_image_url'],
                'offline_image_url' => $user['offline_image_url'],
                'view_count' => $user['view_count'],
                'created_at' => $user['created_at']
            ], 200);

        } catch (TwitchApiException $e) {
            // Si ocurre un error específico de la API de Twitch, se relanza la excepción
            // para que el manejador de excepciones global de Laravel la procese.
            throw $e;
        } catch (\Exception $e) {
            return response()->json([ // Capturar cualquier otra excepción inesperada y devolver un error 500.
                'error' => 'Internal server error.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/analytics/streams",
     *      operationId="getLiveStreams",
     *      tags={"Streams"},
     *      summary="Obtener streams en vivo",
     *      description="Devuelve una lista de los streams más populares actualmente en vivo en Twitch.",
     *      @OA\Response(
     *          response=200,
     *          description="Operación exitosa",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(ref="#/components/schemas/Stream")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Error interno del servidor."
     *      )
     * )
     *
     * @param Request $request La petición HTTP.
     * @return JsonResponse Una lista de streams.
     */
    public function getStreams(Request $request): JsonResponse
    {
        // Intentar obtener la lista de streams desde el servicio de Twitch.
        try {
            $streams = $this->twitchService->getLiveStreams();
            // Asegurarse de que $streams sea siempre un array para evitar errores en array_map.
            $streams = is_array($streams) ? $streams : [];

            // Formatear cada stream para devolver solo el título y el nombre de usuario.
            $formattedStreams = array_map(function ($stream) {
                return [
                    'title' => $stream['title'] ?? 'No title',
                    // Asegurarse de que user_name no esté vacío, recurrir a user_login si es necesario.
                    'user_name' => !empty($stream['user_name']) ? $stream['user_name'] : ($stream['user_login'] ?? 'Unknown')
                ];
            }, $streams);

            // Devolver la lista de streams formateada con estado 200.
            return response()->json($formattedStreams, 200);

        } catch (TwitchApiException $e) {
            // Relanzar excepciones de la API de Twitch para el manejador global.
            throw $e;
        } catch (\Exception $e) {
            return response()->json([ // Capturar cualquier otra excepción y devolver un error 500.
                'error' => 'Internal server error.'
            ], 500);
        }
    }

}

/**
 * @OA\Schema(
 *     schema="User",
 *     title="User",
 *     description="Modelo de un usuario de Twitch",
 *     @OA\Property(property="id", type="string", description="ID del usuario"),
 *     @OA\Property(property="login", type="string", description="Nombre de usuario (login)"),
 *     @OA\Property(property="display_name", type="string", description="Nombre para mostrar"),
 *     @OA\Property(property="type", type="string", description="Tipo de usuario (ej. 'staff')"),
 *     @OA.Property(property="broadcaster_type", type="string", description="Tipo de broadcaster (ej. 'partner', 'affiliate')"),
 *     @OA\Property(property="description", type="string", description="Descripción del perfil"),
 *     @OA\Property(property="profile_image_url", type="string", format="url", description="URL de la imagen de perfil"),
 *     @OA\Property(property="offline_image_url", type="string", format="url", description="URL de la imagen offline"),
 *     @OA\Property(property="view_count", type="integer", description="Número total de vistas"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Fecha de creación de la cuenta")
 * )
 */

/**
 * @OA\Schema(
 *     schema="Stream",
 *     title="Stream",
 *     description="Modelo de un stream en vivo",
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         description="Título del stream"
 *     ),
 *     @OA\Property(
 *         property="user_name",
 *         type="string",
 *         description="Nombre de usuario del streamer"
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="Error",
 *     title="Error",
 *     description="Modelo de respuesta de error",
 *     @OA\Property(property="error", type="string", description="Mensaje de error")
 * )
 */