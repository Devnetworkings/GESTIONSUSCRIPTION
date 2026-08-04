
<?php
// Archivo: Operador.php

class Operador
{
    private $ci;
    private $nombres;
    private $apellidos;
    private $contacto;
    private $direccion;
    private $tlf;
    private $username;
    private $pw;

    public function __construct($ci, $nombres, $apellidos, $contacto, $direccion, $tlf, $username, $pw)
    {
        $this->ci = $ci;
        $this->nombres = $nombres;
        $this->apellidos = $apellidos;
        $this->contacto = $contacto;
        $this->direccion = $direccion;
        $this->tlf = $tlf;
        $this->username = $username;
        // Encriptamos la contraseña en camerinos, antes de salir a escena
        $this->pw = password_hash($pw, PASSWORD_DEFAULT);
    }

    public function guardar($pdo)
    {
        // ¡Aquí está la magia del UUID() para el nuevo operador!
        $sql = "INSERT INTO operator (id, ci, nombres, apellidos, contacto, direccion, PW, TLF, username) 
                VALUES (UUID(), :ci, :nombres, :apellidos, :contacto, :direccion, :pw, :tlf, :username)";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            'ci' => $this->ci,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'contacto' => $this->contacto,
            'direccion' => $this->direccion,
            'pw' => $this->pw,
            'tlf' => $this->tlf,
            'username' => $this->username
        ]);
    }
}
?>