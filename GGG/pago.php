<?php
class Pago
{
    private $idCliente;
    private $reference;
    private $monto;
    private $coin;
    private $idOperador;

    public function __construct($idCliente, $reference, $monto, $coin, $idOperador)
    {
        $this->idCliente = $idCliente;
        $this->reference = $reference;
        $this->monto = $monto;
        $this->coin = $coin;
        $this->idOperador = $idOperador;
    }

    public function guardar($pdo)
    {
        // 1. Guardamos el recibo
        $sqlPago = "INSERT INTO pagos (id, id_cliente, reference, monto, coin, id_operador) 
                    VALUES (UUID(), ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sqlPago);
        $pagoExitoso = $stmt->execute([$this->idCliente, $this->reference, $this->monto, $this->coin, $this->idOperador]);

        // 2. ¡La magia del tiempo! Si el pago es exitoso, renovamos el mes del cliente
        if ($pagoExitoso) {
            $sqlRenovacion = "UPDATE clientes 
                              SET estatus = 'Activo', 
                                  fecha_vencimiento = DATE_ADD(CURDATE(), INTERVAL 1 MONTH) 
                              WHERE id = ?";
            $stmtRenovacion = $pdo->prepare($sqlRenovacion);
            $stmtRenovacion->execute([$this->idCliente]);
        }

        return $pagoExitoso;
    }
}
?>