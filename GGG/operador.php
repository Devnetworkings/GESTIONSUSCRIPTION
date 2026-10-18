
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
    private $role;

    public function __construct($ci, $nombres, $apellidos, $contacto, $direccion, $tlf, $username, $pw, $role = 'operador')
    {
        $this->ci = $ci;
        $this->nombres = $nombres;
        $this->apellidos = $apellidos;
        $this->contacto = $contacto;
        $this->direccion = $direccion;
        $this->tlf = $tlf;
        $this->username = $username;
        $this->role = $role;
        // Encriptamos la contrase�a en camerinos, antes de salir a escena
        $this->pw = password_hash($pw, PASSWORD_DEFAULT);
    }

    public function guardar($pdo)
    {
        // �Aqu� est� la magia del UUID() para el nuevo operador!
        $sql = "INSERT INTO operator (id, ci, nombres, apellidos, contacto, direccion, PW, TLF, username, operator_role) 
                VALUES (UUID(), :ci, :nombres, :apellidos, :contacto, :direccion, :pw, :tlf, :username, :role)";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            'ci' => $this->ci,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'contacto' => $this->contacto,
            'direccion' => $this->direccion,
            'pw' => $this->pw,
            'tlf' => $this->tlf,
            'username' => $this->username,
            'role' => $this->role
        ]);
    }
}
?>