<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionCambioRegistro extends Mailable
{
    use Queueable, SerializesModels;

    public $action;
    public $registro;
    public $usuario;
    public $motivo;

    public function __construct($action, $registro, $usuario, $motivo)
    {
        $this->action = $action;
        $this->registro = $registro;
        $this->usuario = $usuario;
        $this->motivo = $motivo;
    }

    public function build()
    {
        return $this->subject('Notificación de Cambio de Registro')
            ->view('emails.notificacion_cambio_registro');
    }
}
