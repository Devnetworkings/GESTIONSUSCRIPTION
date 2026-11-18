<?php
// Archivo: Cliente.php

class Cliente
{
    private $ci;
    private $nombres;
    private $apellidos;
    private $tlf;
    private $email;
    private $direccion;

    public function __construct($ci, $nombres, $apellidos, $tlf, $email, $direccion)
    {
        $this->ci = $ci;
        $this->nombres = $nombres;
        $this->apellidos = $apellidos;
        $this->tlf = $tlf;
        $this->email = $email;
        $this->direccion = $direccion;
    }

    // El método estrella para guardar en la base de datos
    public function guardar($pdo)
    {
        // Usamos UUID() nativo de MySQL para generar el ID aleatorio
        $sql = "INSERT INTO clientes (id, ci, nombres, apellidos, tlf, email, direccion) 
                VALUES (UUID(), ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $this->ci,
            $this->nombres,
            $this->apellidos,
            $this->tlf,
            $this->email,
            $this->direccion
        ]);
    }
}
?>  