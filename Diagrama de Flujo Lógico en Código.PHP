function evaluarYSimularContrapropuesta($id_prestamo, $db) {
    $eval = obtenerDatosEvaluacion($id_prestamo, $db);
    
    if ($eval['decision'] === 'CONTRAPROPUESTA' || $eval['cuota'] > $eval['capacidad_pago']) {
        $factorCapacidad = obtenerConfiguracion('FACTOR_CAPACIDAD_CONTRAPROPUESTA', $db) ?? 0.95;
        $factorPlazo = obtenerConfiguracion('FACTOR_EXTENSION_PLAZO_CONTRAPROPUESTA', $db) ?? 1.50;
        
        $plazoSugerido = min(round($eval['plazo_meses'] * $factorPlazo), $eval['plazo_maximo_tipo']);
        $capacidadAjustada = $eval['capacidad_pago'] * $factorCapacidad;
        $montoSugerido = calcularMontoAjustado($capacidadAjustada, $plazoSugerido, $eval['tasa_interes']);
        
        if ($montoSugerido >= $eval['monto_minimo_tipo']) {
            $stmt = $db->prepare("INSERT INTO contrapropuesta_prestamo (id_prestamo, monto_sugerido, plazo_sugerido, fecha_contrapropuesta) VALUES (?, ?, ?, CURDATE())");
            $stmt->bind_param("idi", $id_prestamo, $montoSugerido, $plazoSugerido);
            $stmt->execute();
            return ["ok" => true, "decision" => "Contrapropuesta", "monto_sugerido" => $montoSugerido, "plazo_sugerido" => $plazoSugerido];
        }
    }
    return ["ok" => false, "decision" => "Rechazado"];
}