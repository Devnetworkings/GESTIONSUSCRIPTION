<?php
class Pago
{
    private $idCliente;
    private $reference;
    private $monto;
    private $coin;
    private $idOperador;
    private $metodoPago;
    private $idPlan;
    private $duracionMeses;
    private $montoUsd;
    private $tasaBcv;
    private $fechaValorBcv;
    private $planNombre;

    public function __construct($idCliente, $reference, $monto, $coin, $idOperador, $metodoPago, $idPlan, $duracionMeses, $montoUsd, $tasaBcv, $fechaValorBcv, $planNombre)
    {
        $this->idCliente = $idCliente;
        $this->reference = $reference;
        $this->monto = $monto;
        $this->coin = $coin;
        $this->idOperador = $idOperador;
        $this->metodoPago = $metodoPago;
        $this->idPlan = $idPlan;
        $this->duracionMeses = $duracionMeses;
        $this->montoUsd = $montoUsd;
        $this->tasaBcv = $tasaBcv;
        $this->fechaValorBcv = $fechaValorBcv;
        $this->planNombre = $planNombre;
    }

    public function guardar($pdo)
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO pagos (id, id_cliente, reference, monto, coin, id_operador, metodo_pago, id_plan, monto_usd, tasa_bcv, fecha_valor_bcv, plan_nombre_snapshot, plan_duracion_meses_snapshot) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $this->idCliente,
                $this->reference,
                $this->monto,
                $this->coin,
                $this->idOperador,
                $this->metodoPago,
                $this->idPlan,
                $this->montoUsd,
                $this->tasaBcv,
                $this->fechaValorBcv,
                $this->planNombre,
                $this->duracionMeses
            ]);

            $stmt = $pdo->prepare("
                UPDATE clientes
                SET estatus = 'Activo',
                    fecha_vencimiento = DATE_ADD(
                        GREATEST(COALESCE(fecha_vencimiento, CURDATE()), CURDATE()),
                        INTERVAL ? MONTH
                    )
                WHERE id = ?
            ");
            $stmt->execute([$this->duracionMeses, $this->idCliente]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('No se pudo actualizar el vencimiento del cliente.');
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
?>
