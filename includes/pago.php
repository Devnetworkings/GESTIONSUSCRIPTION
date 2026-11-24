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
    private $fechaPago;

    public function __construct($idCliente, $reference, $monto, $coin, $idOperador, $metodoPago, $idPlan, $duracionMeses, $montoUsd, $tasaBcv, $fechaValorBcv, $planNombre, $fechaPago)
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
        $this->fechaPago = $fechaPago;
    }

    public function guardar($pdo)
    {
        require_once __DIR__ . '/auditoria.php';
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT fecha_vencimiento, estatus FROM clientes WHERE id = ? FOR UPDATE');
            $stmt->execute([$this->idCliente]);
            $clienteAntes = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$clienteAntes) {
                throw new RuntimeException('No se encontró el cliente del pago.');
            }
            $fechaRegistro = (new DateTimeImmutable('now', new DateTimeZone('America/Caracas')))
                ->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare(
                'INSERT INTO pagos (id, id_cliente, reference, monto, coin, id_operador, fecha_pago, fecha_registro, metodo_pago, id_plan, monto_usd, tasa_bcv, fecha_valor_bcv, plan_nombre_snapshot, plan_duracion_meses_snapshot) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $this->idCliente,
                $this->reference,
                $this->monto,
                $this->coin,
                $this->idOperador,
                $this->fechaPago,
                $fechaRegistro,
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
                        GREATEST(COALESCE(fecha_vencimiento, ?), ?),
                        INTERVAL ? MONTH
                    )
                WHERE id = ?
            ");
            $stmt->execute([$this->fechaPago, $this->fechaPago, $this->duracionMeses, $this->idCliente]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('No se pudo actualizar el vencimiento del cliente.');
            }
            $stmt = $pdo->prepare('SELECT id FROM pagos WHERE reference = ? LIMIT 1');
            $stmt->execute([$this->reference]);
            $idPago = $stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT fecha_vencimiento, estatus FROM clientes WHERE id = ? LIMIT 1');
            $stmt->execute([$this->idCliente]);
            $clienteDespues = $stmt->fetch(PDO::FETCH_ASSOC);
            registrarAuditoria($pdo, 'pago_registrado', 'pago', $idPago ?: null, [
                'cliente_id' => $this->idCliente,
                'reference' => $this->reference,
                'monto' => $this->monto,
                'coin' => $this->coin,
                'metodo_pago' => $this->metodoPago,
                'id_plan' => $this->idPlan,
                'monto_usd' => $this->montoUsd,
                'tasa_bcv' => $this->tasaBcv,
                'fecha_valor_bcv' => $this->fechaValorBcv
            ]);
            registrarAuditoria($pdo, 'membresia_renovada', 'cliente', $this->idCliente, [
                'fecha_vencimiento' => [
                    'anterior' => $clienteAntes['fecha_vencimiento'],
                    'nuevo' => $clienteDespues['fecha_vencimiento']
                ],
                'estatus' => ['anterior' => $clienteAntes['estatus'], 'nuevo' => 'Activo']
            ]);
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
