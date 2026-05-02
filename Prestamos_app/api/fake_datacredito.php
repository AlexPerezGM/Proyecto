<?php
@ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

$cedula = $_GET['cedula'] ?? null;

if(!$cedula){
    echo json_encode(["ok"=>false,"msg"=>"Falta cédula"]);
    exit;
}

srand(intval(substr($cedula, -4)));

$score = rand(300, 900);

if     ($score >= 780) $nivel = "A"; 
elseif ($score >= 680) $nivel = "B";
elseif ($score >= 580) $nivel = "C";
else                   $nivel = "D";

if ($nivel === "A") {
    $riesgo_txt = "Riesgo muy bajo";
} elseif ($nivel === "B") {
    $riesgo_txt = "Riesgo controlado";
} elseif ($nivel === "C") {
    $riesgo_txt = "Riesgo moderado";
} else {
    $riesgo_txt = "Riesgo alto";
}

$cantidad_tarjetas = rand(0, 4);
$tarjetas = [];
$tarjetas_detalle = [];
$total_limite_tc = 0;
$total_balance_tc = 0;

for ($i=0; $i < $cantidad_tarjetas; $i++){
    $limite = rand(10000, 500000);
    $uso_maximo = ($nivel == "A")? 0.3 : 0.8;
    $balance = rand(0, $limite * $uso_maximo);

    $total_limite_tc += $limite;
    $total_balance_tc += $balance;

    $tarjetas_detalle[] = [
        "institucion" => "Banco " . chr(rand(65, 90)),
        "limite" => $limite,
        "balance" => $balance,
        "estado" => "al dia",
        "uso_porcentaje" => round(($balance / $limite) * 100, 2)
    ];
}

$utilizacion_total = ($total_limite_tc > 0) ? round(($total_balance_tc / $total_limite_tc)*100,2):0;

$cantidad_prestamos = rand(0, 5);
$prestamos = [];
$prestamos_detalle = [];
$cuota_mensual_total = 0;

for ($i=0; $i < $cantidad_prestamos; $i++){
    $monto_original = rand(50000, 1000000);
    $balance_pendiente = rand(0, $monto_original);
    $cuota = $monto_original *0.03;
    $cuota_mensual_total += $cuota;

    $prestamos_detalle[] = [
        "tipo" => (rand(0, 1) > 0.5) ? "personal" : "hipotecario",
        "monto_original" => $monto_original,
        "balance_pendiente" => $balance_pendiente,
        "cuota_mensual" => round($cuota, 2),
        "Cuotas atrasadas" => rand(0,1)> 0.8 ? rand(1,3) : 0
    ];
}

$hist24 = [];
for($i=1;$i<=24;$i++){
    $hist24[] = [
        "mes" => date("Y-m", strtotime("-$i month")),
        "dias_mora" => ($nivel == "A") ? 0 : rand(0, 45)
    ];
}

$atraso_max = 0;
foreach ($hist24 as $h) {
    if (!empty($h['dias_mora']) && $h['dias_mora'] > $atraso_max) $atraso_max = $h['dias_mora'];
}

$consultas = rand(0, 5);

 $alertas = [];
 if($atraso_max > 30) $alertas[] = "Retrasos significativos en los últimos 12 meses";
 if(($cantidad_prestamos + $cantidad_tarjetas) > 6) $alertas[] = "Alto nivel de endeudamiento";
 if($consultas > 5) $alertas[] = "Consultas frecuentes recientes";
 if(rand(0,10) > 8) $alertas[] = "Cuenta reportada en disputa";
 if($utilizacion_total > 70) $alertas[] = "Porcentaje de uso muy alto en tarjetas de credito";
 if($cuota_mensual_total > 500000) $alertas[] = "Cuota mensual total elevada en relación al ingreso estimado";

$response = [
    "ok" => true,
    "data" => [
        "identificacion" => [
            "cedula" => $cedula,
            "nombre" => "",
        ],
        "score" => [
            "valor" => $score,
            "nivel" => $nivel,
            "riesgo" => $riesgo_txt
        ],
        "resumen_crediticio" => [
            "total_cuotas_mensuales" => round($cuota_mensual_total, 2),
            "total_utilizacion_tarjetas" => $utilizacion_total . '%',
            "cantidad_productos" => $cantidad_prestamos + $cantidad_tarjetas,
            "consultas_ultimo_mes" => rand(0, 5)
        ],
        "detalle_cuentas" => [
            "tarjetas" => $tarjetas_detalle ?? [],
            "prestamos" => $prestamos_detalle ?? []
        ],
        "historial_24_meses" => $hist24,
        "alertas" => $alertas,
        "fuente" => "Simulación de Buró de Crédito"
    ]
];

echo json_encode($response);
