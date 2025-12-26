<?php
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

$prestamos = rand(0, 5);
$tarjetas = rand(0, 4);
$lineas = rand(0, 3);
$consultas = rand(0, 8);
$atraso_max = rand(0, 180);

$hist24 = [];
for($i=1;$i<=24;$i++){
    $hist24[] = [
        "mes" => date("Y-m", strtotime("-$i month")),
        "dias_mora" => rand(0, 90)
    ];
}

$alertas = [];
if($atraso_max > 30) $alertas[] = "Retrasos significativos en los últimos 12 meses";
if($prestamos + $tarjetas > 6) $alertas[] = "Alto nivel de endeudamiento";
if($consultas > 5) $alertas[] = "Consultas frecuentes recientes";
if(rand(0,10) > 8) $alertas[] = "Cuenta reportada en disputa";

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
            "riesgo" => match($nivel){
                "A" => "Riesgo muy bajo",
                "B" => "Riesgo controlado",
                "C" => "Riesgo moderado",
                "D" => "Riesgo alto",
            }
        ],
        "resumen_crediticio" => [
            "prestamos_activos" => $prestamos,
            "tarjetas_credito" => $tarjetas,
            "lineas_credito" => $lineas,
            "consultas_recientes" => $consultas,
            "atraso_maximo" => $atraso_max,
        ],
        "historial_24_meses" => $hist24,
        "alertas" => $alertas,
        "fuente" => "Simulación de Buró de Crédito"
    ]
];

echo json_encode($response);
