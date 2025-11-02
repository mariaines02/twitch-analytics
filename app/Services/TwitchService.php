<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Exceptions\TwitchApiException;

/**
 * Clase TwitchService
 *
 * Este servicio se encarga de toda la comunicación con la API de Twitch.
 * Gestiona la autenticación, la renovación de tokens y la realización de peticiones a los endpoints de la API.
 */
class TwitchService
{
    /** @var string El ID de cliente de la aplicación de Twitch. */
    private string $clientId;

    /** @var string El secreto de cliente de la aplicación de Twitch. */
    private string $clientSecret;

    /** @var string La URL para obtener el token de autenticación de Twitch. */
    private string $tokenUrl;

    /** @var string La URL base de la API de Twitch (Helix). */
    private string $apiUrl;

    /** @var string La clave utilizada para almacenar el token de acceso en la caché. */
    private const TOKEN_CACHE_KEY = 'twitch_access_token';

    /**
     * Constructor de TwitchService.
     * Inicializa las propiedades del servicio con los valores del archivo de configuración.
     */
    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
        $this->clientSecret = config('services.twitch.client_secret');
        $this->tokenUrl = config('services.twitch.token_url');
        $this->apiUrl = config('services.twitch.api_url');
    }

    /**
     * Obtiene el token de acceso desde la caché o genera uno nuevo si no existe o ha expirado.
     *
     * @return string El token de acceso de la aplicación.
     * @throws TwitchApiException Si falla la generación de un nuevo token.
     */
    private function getAccessToken(): string
    {
        $token = Cache::get(self::TOKEN_CACHE_KEY);

        if ($token) {
            return $token;
        }

        // Si no existe o expiró, generar uno nuevo
        return $this->generateNewToken();
    }

    /**
     * Genera un nuevo token de acceso de aplicación contactando con la API de Twitch.
     *
     * @return string El nuevo token de acceso.
     * @throws TwitchApiException Si la petición para generar el token falla.
     */
    private function generateNewToken(): string
    {
        try {
            $response = Http::post($this->tokenUrl, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials'
            ]);

            if (!$response->successful()) {
                Log::error('Failed to generate Twitch token', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new TwitchApiException('Failed to authenticate with Twitch');
            }

            $data = $response->json();
            $token = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 3600;

            // Guardar en caché con un margen de 5 minutos para evitar usar un token a punto de expirar.
            Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds($expiresIn - 300));

            return $token;
        } catch (\Exception $e) {
            Log::error('Exception generating Twitch token', ['error' => $e->getMessage()]);
            throw new TwitchApiException('Failed to authenticate with Twitch');
        }
    }

    /**
     * Realiza una petición GET a un endpoint de la API de Twitch.
     * Incluye el manejo automático de la renovación del token en caso de que haya expirado (error 401).
     *
     * @param string $endpoint El endpoint de la API al que se va a llamar (ej. '/users').
     * @param array $params Los parámetros de la query string.
     * @param int $retryCount El número de reintentos (usado internamente para evitar bucles infinitos).
     * @return array La respuesta de la API decodificada como un array.
     * @throws TwitchApiException Si la petición a la API falla por cualquier motivo.
     */
    private function makeRequest(string $endpoint, array $params = [], int $retryCount = 0): array
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])->get($this->apiUrl . $endpoint, $params);

        // Si el token es inválido (401), se regenera y se reintenta la petición una sola vez.
        if ($response->status() === 401 && $retryCount === 0) {
            Log::info('Token expired or invalid, regenerating...');
            Cache::forget(self::TOKEN_CACHE_KEY);
            return $this->makeRequest($endpoint, $params, 1);
        }

        if ($response->status() === 401) {
            throw new TwitchApiException('Unauthorized. Twitch access token is invalid or has expired.', 401);
        }

        if ($response->status() === 404) {
            throw new TwitchApiException('Resource not found.', 404);
        }

        if (!$response->successful()) {
            Log::error('Twitch API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new TwitchApiException('Twitch API error', $response->status());
        }

        return $response->json();
    }

    /**
     * Obtiene la información de un usuario de Twitch por su ID.
     *
     * @param string $userId El ID del usuario a buscar.
     * @return array|null Los datos del usuario si se encuentra, o null si no.
     * @throws TwitchApiException Si la petición a la API falla.
     */
    public function getUserById(string $userId): ?array
    {
        $response = $this->makeRequest('/users', ['id' => $userId]);
        
        $data = $response['data'] ?? [];
        
        if (empty($data)) {
            return null;
        }

        return $data[0];
    }

    /**
     * Obtiene una lista de los streams que están actualmente en vivo.
     *
     * @param int $first El número máximo de streams a devolver (por defecto 20).
     * @return array Un array con los datos de los streams en vivo.
     * @throws TwitchApiException Si la petición a la API falla.
     */
    public function getLiveStreams(int $first = 20): array
    {
        $response = $this->makeRequest('/streams', ['first' => $first]);
        
        return $response['data'] ?? [];
    }
}