<?php

namespace App\Exceptions;

use Exception;

/**
 * Clase TwitchApiException
 *
 * Excepción personalizada para manejar errores específicos de la API de Twitch.
 * Esta excepción se utiliza para encapsular errores que ocurren durante la comunicación con la API de Twitch,
 * permitiendo un manejo de errores más claro y una respuesta JSON estandarizada.
 */
class TwitchApiException extends Exception
{
    /** @var int El código de estado HTTP asociado con la excepción. */
    protected $code;

    /**
     * Constructor de TwitchApiException.
     *
     * @param string $message El mensaje de la excepción.
     * @param int $code El código de estado HTTP.
     */
    public function __construct(string $message = "", int $code = 500)
    {
        parent::__construct($message, $code);
        $this->code = $code;
    }

    /**
     * Renderiza la excepción en una respuesta HTTP.
     *
     * Este método es llamado por el manejador de excepciones de Laravel para convertir
     * la excepción en una respuesta HTTP que se enviará al cliente.
     *
     * @param \Illuminate\Http\Request $request La petición HTTP entrante.
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request)
    {
        return response()->json([
            'error' => $this->getMessage()
        ], $this->code);
    }
} 