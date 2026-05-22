<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación de Cambio de Registro</title>
</head>
<body>
    <h1>Notificación de Cambio en Registro</h1>
    <p>Se ha realizado un cambio en el registro del sistema.</p>

    <p><strong>Acción Realizada:</strong> {{ $action }}</p>
    <p><strong>Registro Afectado:</strong> {{ $registro }}</p>
    <p><strong>Usuario:</strong> {{ $usuario }}</p>
    <p><strong>Motivo de la modificación:</strong> {{ $motivo }}</p>

    <p>Saludos,</p>
    <p>El equipo de sistema.</p>
    
</body>
</html>
