<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../api/resultado.php';

$id_prestamo =(int)($_GET['id_prestamo'] ?? 0);
if ($id_prestamo <=0) {
    die('ID de préstamo no válido');
}

$data = obtener_resultado_evaluacion($conn, $id_prestamo);
if (!$data){
    die('No se encontró la informacion del préstamo');
}
$ev = $data['ev'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato_<?=$ev['numero_contrato']?></title>
    <style>
        body {
            font-family: 'Times New Roman', serif; margin: 0; padding: 20px;color :#000;
        }
        .contrato-container{
                max-width: 800px; margin: auto; padding: 50px; border: 1px solid #000; background-color: #fff;
            }
        @media print {
            body{
                padding: 0;
            }
            .contrato-container{
                border: none; width: 100%; max-width: none;
            }
            .no-print{display: none;}
        }

        .header{
            text-align:center; text-transform: uppercase; margin-bottom: 30px;
        }
        .header h1 {
            margin: 0; font-size: 24px;
        }
        .section {
            margin-top: 25px; text-decoration: underline; margin-bottom: 10px; display: block;
        }
        .info-table {
            width: 100%; border-collapse: collapse; margin-top: 10px;
        }
        .info-table td {
            padding: 5px; border: 1px solid #000;
        }
        .signatures {
            margin-top: 80px; display: flex; justify-content: space-between;
        }
        .sig-box {
            width: 40%; border-top: 1px solid #000; text-align: center; padding-top: 5px;
        }
        .btn-flotante{
            position: fixed; top: 20px; right: 20px; background: #000; color: #fff; padding: 10px 20px; cursor: pointer; border: none; border-radius: 5px;
        }
 
    </style>
</head>
<body>
    <button class="btn-flotante no-print" onclick="window.print()">Imprimir Contrato</button>

    <div class="contrato-container">
        <div class="header">
            <h1>Contrato de prestamo</h1>
            <p>Contrato No. <?=$ev['numero_contrato']?></p>
        </div>

        <div class="section">
            <span class="section-title">I. Las partes</span>
            <p>Entre una parte, la entidad financiera y de otra parte el sr./sra. <strong><?= htmlspecialchars($ev['nombre_cliente']. ' '.$ev['apellido_cliente'])?></strong>, Portador del documento de identidad correspondiente al contrato <strong><?= $ev['numero_contrato']?></strong>.</p>
        </div>

        <div class="section">
            <span class="section-title">II. Condiciones del prestamo</span>
            <table class="info-table">
                <tr>
                    <td><strong>Monto Aprobado</strong></td>
                    <td>RD$<?= number_format($ev['monto_solicitado'], 2)?></td>
                </tr>
                <tr>
                    <td><strong>Plazo:</strong></td>
                    <td><?=$ev['plazo_meses']?> meses</td>
                </tr>
                <tr>
                    <td><strong>Tasa de interes:</strong></td>
                    <td><?=$ev['tasa_aplicada']?>% anual</td>
                </tr>
                <tr>
                    <td><strong>Cuota mensual:</strong></td>
                    <td>RD$ <?= number_format($data['cuota'],2)?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <span class="section-title">III. Aceptacion</span>
            <p>El prestatario declara haber recibido la suma indicada y se compromete a pagarla segun los plazos acordados. El incumplimiento de los pagos generara morosidad segun las politicas vigentes.</p>
        </div>

        <div class="signatures">
            <div class="sig-box">
                __________________________<br>
                Firma del cliente
            </div>
            <div class="sig-box">
                __________________________<br>
                Firma del representante de la institucion
            </div>
        </div>
        <div style="margin-top: 50px; font-size: 10px; text-align: center; color: #666;">
            Este documento fue generado electronicamente el <?= date('d/m/Y')?> a las <?= date('H:i:s')?>. Documento Valido emitido por el sistema de la institucion ...
        </div>
    </div>
</body>
</html>