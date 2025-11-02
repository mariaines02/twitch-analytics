<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\TwitchService;
use App\Exceptions\TwitchApiException;
use Mockery;

/**
 * Pruebas de feature para AnalyticsController.
 *
 * Estas pruebas verifican el comportamiento de los endpoints de la API
 * definidos en AnalyticsController, simulando el servicio de Twitch
 * para aislar el controlador.
 */
class AnalyticsControllerTest extends TestCase
{
    /**
     * Limpia los mocks de Mockery después de cada prueba.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Prueba que el endpoint de usuario devuelve los datos correctamente cuando se encuentra un usuario.
     */
    public function test_get_user_returns_user_data()
    {
        // Simular el servicio de Twitch para que devuelva datos de un usuario.
        $mock = Mockery::mock(TwitchService::class);
        $mock->shouldReceive('getUserById')
             ->once()
             ->andReturn([
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
             ]);

        // Inyectar el mock en el contenedor de servicios de la aplicación.
        $this->app->instance(TwitchService::class, $mock);

        $response = $this->getJson('/api/analytics/user?id=12345');

        // Verificar que la respuesta es exitosa (200) y tiene la estructura JSON esperada.
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'login',
            'display_name',
            'type',
            'broadcaster_type',
            'description',
            'profile_image_url',
            'offline_image_url',
            'view_count',
            'created_at'
        ]);
    }

    /**
     * Prueba que el endpoint de usuario devuelve un error 400 si falta el parámetro 'id'.
     */
    public function test_get_user_returns_400_when_id_missing()
    {
        $response = $this->getJson('/api/analytics/user?id=');

        // Verificar que la respuesta es un 400 y contiene el mensaje de error correcto.
        $response->assertStatus(400);
        $response->assertJson([
            'error' => "Invalid or missing 'id' parameter."
        ]);
    }

    /**
     * Prueba que el endpoint de usuario devuelve un error 404 cuando no se encuentra el usuario.
     */
    public function test_get_user_returns_404_when_user_not_found()
    {
        // Simular el servicio de Twitch para que no encuentre un usuario.
        $mock = Mockery::mock(TwitchService::class);
        $mock->shouldReceive('getUserById')
             ->once()
             ->andReturn(null);

        $this->app->instance(TwitchService::class, $mock);

        $response = $this->getJson('/api/analytics/user?id=111111');

        // Verificar que la respuesta es un 404 y contiene el mensaje de error correcto.
        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'User not found.'
        ]);
    }

    /**
     * Prueba que el endpoint de streams devuelve una lista de streams correctamente.
     */
    public function test_get_streams_returns_streams_list()
    {
        // Simular el servicio de Twitch para que devuelva una lista de streams.
        $mock = Mockery::mock(TwitchService::class);
        $mock->shouldReceive('getLiveStreams')
             ->once()
             ->andReturn([
                 [
                     'title' => 'Stream 1',
                     'user_name' => 'User1'
                 ],
                 [
                     'title' => 'Stream 2',
                     'user_name' => 'User2'
                 ]
             ]);

        $this->app->instance(TwitchService::class, $mock);

        $response = $this->getJson('/api/analytics/streams');

        // Verificar que la respuesta es exitosa (200), contiene 2 elementos y tiene la estructura correcta.
        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonStructure([
            '*' => ['title', 'user_name']
        ]);
    }

    /**
     * Prueba que el endpoint de streams devuelve un array vacío cuando no hay streams en vivo.
     */
    public function test_get_streams_returns_empty_array_when_no_streams()
    {
        // Simular el servicio de Twitch para que devuelva un array vacío.
        $mock = Mockery::mock(TwitchService::class);
        $mock->shouldReceive('getLiveStreams')
             ->once()
             ->andReturn([]);

        $this->app->instance(TwitchService::class, $mock);

        $response = $this->getJson('/api/analytics/streams');

        // Verificar que la respuesta es exitosa (200) y el cuerpo es un array JSON vacío.
        $response->assertStatus(200);
        $response->assertJson([]);
    }
}