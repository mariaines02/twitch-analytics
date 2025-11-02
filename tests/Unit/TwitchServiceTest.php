<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TwitchService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TwitchServiceTest extends TestCase
{
    /**
     * Se ejecuta antes de cada prueba.
     * Limpia la caché para asegurar que las pruebas sean independientes.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Limpiar la caché de Laravel.
    }

    /**
     * Prueba que el método getUserById devuelve los datos de un usuario cuando la API de Twitch responde correctamente.
     */
    public function test_get_user_by_id_returns_user_data()
    {
        Http::fake([ // Simular las respuestas HTTP de la API de Twitch.
            'id.twitch.tv/*' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600
            ], 200),
            'api.twitch.tv/*' => Http::response([
                'data' => [
                    [
                        'id' => '12345',
                        'login' => 'testuser',
                        'display_name' => 'Test User',
                        'type' => '',
                        'broadcaster_type' => 'partner',
                        'description' => 'Test description',
                        'profile_image_url' => 'https://example.com/profile.jpg',
                        'offline_image_url' => 'https://example.com/offline.jpg',
                        'view_count' => 1000,
                        'created_at' => '2020-01-01T00:00:00Z'
                    ]
                ]
            ], 200)
        ]);

        // Crear una instancia del servicio y llamar al método a probar.
        $service = new TwitchService();
        $user = $service->getUserById('12345');

        // Verificar que el usuario no es nulo y que los datos son correctos.
        $this->assertNotNull($user);
        $this->assertEquals('12345', $user['id']);
        $this->assertEquals('testuser', $user['login']);
    }

    /**
     * Prueba que el método getUserById devuelve null si la API de Twitch no encuentra al usuario.
     */
    public function test_get_user_by_id_returns_null_when_not_found()
    {
        Http::fake([ // Simular una respuesta de token y una respuesta de API con 'data' vacío.
            'id.twitch.tv/*' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600
            ], 200),
            'api.twitch.tv/*' => Http::response([
                'data' => []
            ], 200)
        ]);

        // Llamar al método con un ID que supuestamente no existe.
        $service = new TwitchService();
        $user = $service->getUserById('99999');

        // Verificar que el resultado es null.
        $this->assertNull($user);
    }

    /**
     * Prueba que el método getLiveStreams devuelve un array de streams.
     */
    public function test_get_live_streams_returns_streams_array()
    {
        Http::fake([ // Simular una respuesta de la API con una lista de streams.
            'id.twitch.tv/*' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600
            ], 200),
            'api.twitch.tv/*' => Http::response([
                'data' => [
                    [
                        'title' => 'Stream 1',
                        'user_name' => 'User1',
                        'user_login' => 'user1',
                        'viewer_count' => 100
                    ],
                    [
                        'title' => 'Stream 2',
                        'user_name' => 'User2',
                        'user_login' => 'user2',
                        'viewer_count' => 200
                    ]
                ]
            ], 200)
        ]);

        // Llamar al método a probar.
        $service = new TwitchService();
        $streams = $service->getLiveStreams();

        // Verificar que el resultado es un array, que tiene 2 elementos y que el contenido es correcto.
        $this->assertIsArray($streams);
        $this->assertCount(2, $streams);
        $this->assertEquals('Stream 1', $streams[0]['title']);
    }

    /**
     * Prueba que el token de acceso se guarda en la caché después de la primera petición.
     */
    public function test_token_is_cached()
    {
        Http::fake([ // Simular las respuestas de la API.
            'id.twitch.tv/*' => Http::response([
                'access_token' => 'cached_token',
                'expires_in' => 3600
            ], 200),
            'api.twitch.tv/*' => Http::response([
                'data' => [['id' => '123', 'login' => 'test']]
            ], 200)
        ]);

        // Llamar a un método que requiera un token.
        $service = new TwitchService();
        $service->getUserById('123');

        // Verificar que el token ahora existe en la caché y tiene el valor esperado.
        $this->assertTrue(Cache::has('twitch_access_token'));
        $this->assertEquals('cached_token', Cache::get('twitch_access_token'));
    }

    /**
     * Prueba que el servicio solicita un nuevo token si el actual ha expirado (recibe un 401).
     */
    public function test_token_regenerates_on_401()
    {
        // Poner un token "expirado" en la caché.
        Cache::put('twitch_access_token', 'expired_token', now()->addHour());

        // Simular una secuencia de respuestas:
        Http::fake([
            'api.twitch.tv/*' => Http::sequence()
                ->push([], 401)  // El primer intento de llamada a la API falla con 401 (Unauthorized).
                ->push(['data' => [['id' => '123', 'login' => 'test']]], 200), // El segundo intento (después de obtener nuevo token) tiene éxito.
            'id.twitch.tv/*' => Http::response([
                'access_token' => 'new_token',
                'expires_in' => 3600
            ], 200)
        ]);

        $service = new TwitchService();
        // Llamar al método. El servicio debería manejar el 401 internamente y reintentar.
        $user = $service->getUserById('123');

        // Verificar que, a pesar del error inicial, la operación finalizó con éxito.
        $this->assertNotNull($user);
        $this->assertEquals('123', $user['id']);
    }
}