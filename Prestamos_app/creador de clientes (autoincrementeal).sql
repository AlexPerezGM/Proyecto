USE p_test2;

DELIMITER $$

DROP PROCEDURE IF EXISTS poblar_clientes_auto$$
CREATE PROCEDURE poblar_clientes_auto(IN p_cantidad INT)
BEGIN
    DECLARE v_i INT DEFAULT 1;

    -- ===== Catálogos / helpers generales =====
    DECLARE v_tp_personal INT;
    DECLARE v_tp_hipotecario INT;
    DECLARE v_est_activo INT;
    DECLARE v_est_mora INT;
    DECLARE v_est_cerrado INT;
    DECLARE v_pago_efectivo INT;
    DECLARE v_pago_transferencia INT;
    DECLARE v_moneda_dop INT;
    DECLARE v_doc_ident INT;
    DECLARE v_doc_ing INT;
    DECLARE v_doc_ref INT;
    DECLARE v_gen_m INT;
    DECLARE v_gen_f INT;
    DECLARE v_td_cedula INT;
    DECLARE v_ent_cliente INT;
    DECLARE v_riesgo_bajo INT;
    DECLARE v_riesgo_medio INT;
    DECLARE v_riesgo_alto INT;
    DECLARE v_user_default INT;

    -- Rango de fechas deseado (Oct 2025 – Dic 2026)
    DECLARE v_fecha_min DATE DEFAULT DATE '2025-10-01';
    DECLARE v_fecha_max DATE DEFAULT DATE '2026-12-31';
    DECLARE v_today DATE DEFAULT CURDATE();

    -- Por cliente
    DECLARE v_id_dp INT;
    DECLARE v_id_cliente INT;
    DECLARE v_id_ing_eg INT;
    DECLARE v_id_dir INT;
    DECLARE v_genero_id INT;
    DECLARE v_ingresos DECIMAL(14,2);
    DECLARE v_egresos DECIMAL(14,2);
    DECLARE v_capacidad DECIMAL(14,2);
    DECLARE v_tipo_ingreso INT;      -- 1=bajo,2=medio,3=alto
    DECLARE v_puntaje INT;
    DECLARE v_nivel_riesgo_id INT;

    -- Por préstamo
    DECLARE v_id_tipo_prest INT;
    DECLARE v_id_condicion INT;
    DECLARE v_tasa DECIMAL(5,2);
    DECLARE v_monto_minimo DECIMAL(14,2);
    DECLARE v_plazo_min INT;
    DECLARE v_plazo_max INT;
    DECLARE v_plazo INT;
    DECLARE v_monto_prestamo DECIMAL(14,2);
    DECLARE v_id_prestamo INT;
    DECLARE v_scenario INT;
    DECLARE v_fecha_solicitud DATE;
    DECLARE v_fecha_desembolso DATE;
    DECLARE v_rand INT;
    DECLARE v_cuota_estimada DECIMAL(14,2);
    DECLARE v_cuota_max DECIMAL(14,2);
    DECLARE v_int_mensual DECIMAL(10,6);
    DECLARE v_int_anual DECIMAL(10,6);
    DECLARE v_int_parte DECIMAL(10,6);
    DECLARE v_int_factor DECIMAL(10,6);
    DECLARE v_int_retries INT;

    -- ==== Cargar IDs de catálogos básicos ====
    SELECT id_tipo_prestamo INTO v_tp_personal
    FROM tipo_prestamo WHERE nombre = 'Personal' LIMIT 1;

    SELECT id_tipo_prestamo INTO v_tp_hipotecario
    FROM tipo_prestamo WHERE nombre = 'Hipotecario' LIMIT 1;

    SELECT id_estado_prestamo INTO v_est_activo
    FROM cat_estado_prestamo WHERE estado = 'Activo' LIMIT 1;

    SELECT id_estado_prestamo INTO v_est_mora
    FROM cat_estado_prestamo WHERE estado = 'En mora' LIMIT 1;

    SELECT id_estado_prestamo INTO v_est_cerrado
    FROM cat_estado_prestamo WHERE estado = 'Cerrado' LIMIT 1;

    SELECT id_tipo_pago INTO v_pago_efectivo
    FROM cat_tipo_pago WHERE tipo_pago = 'Efectivo' LIMIT 1;

    SELECT id_tipo_pago INTO v_pago_transferencia
    FROM cat_tipo_pago WHERE tipo_pago = 'Transferencia' LIMIT 1;

    SELECT id_tipo_moneda INTO v_moneda_dop
    FROM cat_tipo_moneda WHERE tipo_moneda = 'DOP' LIMIT 1;

    SELECT id_documentacion_cliente INTO v_doc_ident
    FROM cat_documentacion_cliente
    WHERE tipo_documentacion = 'Identificacion oficial' LIMIT 1;

    SELECT id_documentacion_cliente INTO v_doc_ing
    FROM cat_documentacion_cliente
    WHERE tipo_documentacion = 'Comprobante de ingresos' LIMIT 1;

    SELECT id_documentacion_cliente INTO v_doc_ref
    FROM cat_documentacion_cliente
    WHERE tipo_documentacion = 'Referencias personales' LIMIT 1;

    SELECT id_genero INTO v_gen_m
    FROM cat_genero WHERE genero = 'Masculino' LIMIT 1;

    SELECT id_genero INTO v_gen_f
    FROM cat_genero WHERE genero = 'Femenino' LIMIT 1;

    SELECT id_tipo_documento INTO v_td_cedula
    FROM cat_tipo_documento WHERE tipo_documento = 'Cedula' LIMIT 1;

    SELECT id_tipo_entidad INTO v_ent_cliente
    FROM cat_tipo_entidad WHERE tipo_entidad = 'Cliente' LIMIT 1;

    SELECT id_nivel_riesgo INTO v_riesgo_bajo
    FROM cat_nivel_riesgo WHERE nivel = 'Bajo' LIMIT 1;

    SELECT id_nivel_riesgo INTO v_riesgo_medio
    FROM cat_nivel_riesgo WHERE nivel = 'Medio' LIMIT 1;

    SELECT id_nivel_riesgo INTO v_riesgo_alto
    FROM cat_nivel_riesgo WHERE nivel = 'Alto' LIMIT 1;

    -- Usuario por defecto (para creado_por)
    SELECT id_usuario INTO v_user_default
    FROM usuario ORDER BY id_usuario LIMIT 1;

    -- ============================
    -- Bucle principal de clientes
    -- ============================
    WHILE v_i <= p_cantidad DO

        -- =========================
        -- 1) Datos personales
        -- =========================
        SET v_genero_id = IF(RAND() < 0.5, v_gen_m, v_gen_f);

        -- Nombre y apellido pseudo-realistas usando v_i
        INSERT INTO datos_persona (nombre, apellido, fecha_nacimiento, genero)
        VALUES (
            CONCAT(
                CASE (v_i % 10)
                    WHEN 0 THEN 'Carlos'
                    WHEN 1 THEN 'María'
                    WHEN 2 THEN 'Juan'
                    WHEN 3 THEN 'Ana'
                    WHEN 4 THEN 'Luis'
                    WHEN 5 THEN 'Lucia'
                    WHEN 6 THEN 'Pedro'
                    WHEN 7 THEN 'Sofia'
                    WHEN 8 THEN 'Miguel'
                    ELSE 'Elena'
                END,
                LPAD(v_i, 2, '0')
            ),
            CONCAT(
                CASE (v_i % 8)
                    WHEN 0 THEN 'Pérez'
                    WHEN 1 THEN 'Gomez'
                    WHEN 2 THEN 'Rodriguez'
                    WHEN 3 THEN 'Torres'
                    WHEN 4 THEN 'Ruiz'
                    WHEN 5 THEN 'Fernandez'
                    WHEN 6 THEN 'Castillo'
                    ELSE 'Santos'
                END
            ),
            -- edad entre 20 y 70
            DATE_SUB(v_today, INTERVAL (20 + FLOOR(RAND()*50)) YEAR),
            v_genero_id
        );
        SET v_id_dp = LAST_INSERT_ID();

        INSERT INTO email(id_datos_persona, email, es_principal)
        VALUES (
            v_id_dp,
            CONCAT('cliente', LPAD(v_i,4,'0'), '@demo.local'),
            TRUE
        );

        INSERT INTO telefono(id_datos_persona, telefono, es_principal)
        VALUES (
            v_id_dp,
            CONCAT('+1-809-555-', LPAD(1000 + v_i,4,'0')),
            TRUE
        );

        INSERT INTO documento_identidad(
            id_datos_persona,
            id_tipo_documento,
            numero_documento,
            fecha_emision
        )
        VALUES (
            v_id_dp,
            v_td_cedula,
            CONCAT(LPAD(100 + v_i,3,'0'), '-', LPAD(1000000 + v_i,7,'0'), '-', (v_i % 10)),
            DATE_SUB(v_today, INTERVAL (3 + FLOOR(RAND()*13)) YEAR) -- 3 a 15 años
        );

        INSERT INTO direccion(id_datos_persona, ciudad, sector, calle, numero_casa)
        VALUES (
            v_id_dp,
            'Ciudad Demo',
            CONCAT('Sector ', FLOOR(1 + RAND()*10)),
            CONCAT('Calle ', FLOOR(1 + RAND()*25)),
            FLOOR(1 + RAND()*200)
        );
        SET v_id_dir = LAST_INSERT_ID();

        INSERT INTO direccion_entidad(id_direccion, id_tipo_entidad, tipo_direccion)
        VALUES (v_id_dir, v_ent_cliente, 'Residencial');

        INSERT INTO ocupacion(id_datos_persona, ocupacion, empresa)
        VALUES (
            v_id_dp,
            CONCAT('Ocupacion ', FLOOR(1 + RAND()*5)),
            'Empresa Demo SRL'
        );

        INSERT INTO cliente(id_datos_persona)
        VALUES (v_id_dp);
        SET v_id_cliente = LAST_INSERT_ID();

        -- =================================
        -- 2) Perfil económico y riesgo
        -- =================================

        -- Tipo de ingresos (1=bajo,2=medio,3=alto)
        SET v_rand = FLOOR(RAND()*100);
        IF v_rand < 30 THEN
            SET v_tipo_ingreso = 1; -- bajos
            SET v_ingresos = 15000 + (RAND()*25000);   -- 15,000–40,000
        ELSEIF v_rand < 75 THEN
            SET v_tipo_ingreso = 2; -- medios
            SET v_ingresos = 40000 + (RAND()*60000);   -- 40,000–100,000
        ELSE
            SET v_tipo_ingreso = 3; -- altos
            SET v_ingresos = 100000 + (RAND()*500000); -- 100,000–600,000
        END IF;
        SET v_ingresos = ROUND(v_ingresos, 2);

        -- Egresos 40–70 % de los ingresos
        SET v_egresos = ROUND(v_ingresos * (0.4 + (RAND()*0.3)), 2);

        INSERT INTO ingresos_egresos(id_cliente, ingresos_mensuales, egresos_mensuales)
        VALUES (v_id_cliente, v_ingresos, v_egresos);
        SET v_id_ing_eg = LAST_INSERT_ID();

        -- Fuentes de ingreso
        INSERT INTO fuente_ingreso(id_ingresos_egresos, fuente)
        VALUES (v_id_ing_eg, 'Salario');

        IF RAND() < 0.4 THEN
            INSERT INTO fuente_ingreso(id_ingresos_egresos, fuente)
            VALUES (v_id_ing_eg, 'Freelance');
        END IF;
        IF RAND() < 0.2 THEN
            INSERT INTO fuente_ingreso(id_ingresos_egresos, fuente)
            VALUES (v_id_ing_eg, 'Negocio propio');
        END IF;

        INSERT INTO documentacion_cliente(id_cliente, id_documentacion_cliente, ruta_documento)
        VALUES
            (v_id_cliente, v_doc_ident, CONCAT('/docs/auto/', v_id_cliente, '/cedula.pdf')),
            (v_id_cliente, v_doc_ing,   CONCAT('/docs/auto/', v_id_cliente, '/ingresos.pdf')),
            (v_id_cliente, v_doc_ref,   CONCAT('/docs/auto/', v_id_cliente, '/referencias.pdf'));

        -- Puntaje y nivel de riesgo coherente
        SET v_puntaje = 500 + FLOOR(RAND()*351); -- 500–850

        IF v_puntaje >= 720 THEN
            SET v_nivel_riesgo_id = v_riesgo_bajo;
        ELSEIF v_puntaje >= 620 THEN
            SET v_nivel_riesgo_id = v_riesgo_medio;
        ELSE
            SET v_nivel_riesgo_id = v_riesgo_alto;
        END IF;

        INSERT INTO puntaje_crediticio(id_cliente, id_nivel_riesgo, puntaje, total_transacciones)
        VALUES (
            v_id_cliente,
            v_nivel_riesgo_id,
            v_puntaje,
            5 + FLOOR(RAND()*30)
        );

        SET v_capacidad = v_ingresos - v_egresos;

        -- ==============================
        -- 3) Escenario de préstamos
        -- Distribución:
        --  0–24  : sin préstamo (25 %)
        --  25–59 : personal activo al día (35 %)
        --  60–74 : personal en mora (15 %)
        --  75–89 : hipotecario activo (15 %)
        --  90–99 : préstamo cerrado (10 %)
        -- ==============================
        SET v_rand = FLOOR(RAND()*100);

        IF v_capacidad <= 0 THEN
            SET v_scenario = 0;
        ELSEIF v_rand < 25 THEN
            SET v_scenario = 0;
        ELSEIF v_rand < 60 THEN
            SET v_scenario = 1; -- personal activo
        ELSEIF v_rand < 75 THEN
            SET v_scenario = 2; -- personal en mora
        ELSEIF v_rand < 90 THEN
            SET v_scenario = 3; -- hipotecario activo
        ELSE
            SET v_scenario = 4; -- cerrado (pagado)
        END IF;

        -- =========================================
        -- 4) Creación de préstamo según escenario
        -- =========================================
        IF v_scenario > 0 THEN

            -- Tipo de préstamo según escenario
            IF v_scenario IN (1,2,4) THEN
                -- personales predominan
                SET v_id_tipo_prest = v_tp_personal;
            ELSE
                -- hipotecario activo
                SET v_id_tipo_prest = v_tp_hipotecario;
            END IF;

            -- Condición activa según tipo (personal: tasa ≥ 10, hipotecario: < 10)
            IF v_id_tipo_prest = v_tp_personal THEN
                SELECT cp.id_condicion_prestamo, cp.tasa_interes
                INTO v_id_condicion, v_tasa
                FROM condicion_prestamo cp
                WHERE cp.esta_activo = TRUE
                  AND cp.tasa_interes >= 10
                ORDER BY cp.vigente_desde DESC
                LIMIT 1;
            ELSE
                SELECT cp.id_condicion_prestamo, cp.tasa_interes
                INTO v_id_condicion, v_tasa
                FROM condicion_prestamo cp
                WHERE cp.esta_activo = TRUE
                  AND cp.tasa_interes < 10
                ORDER BY cp.vigente_desde DESC
                LIMIT 1;
            END IF;

            -- Si no encontró por rango, buscar cualquier condición activa
            IF v_id_condicion IS NULL THEN
                SELECT cp.id_condicion_prestamo, cp.tasa_interes
                INTO v_id_condicion, v_tasa
                FROM condicion_prestamo cp
                WHERE cp.esta_activo = TRUE
                ORDER BY cp.vigente_desde DESC
                LIMIT 1;
            END IF;

            -- Si aun así no hay condición o la tasa es nula/0, no creamos préstamo
            IF v_id_condicion IS NOT NULL AND v_tasa IS NOT NULL AND v_tasa > 0 THEN

                -- Parámetros del producto (mínimos y plazos)
                SELECT
                    COALESCE(monto_minimo, CASE WHEN v_id_tipo_prest = v_tp_personal THEN 30000 ELSE 500000 END),
                    COALESCE(plazo_minimo_meses,
                             CASE WHEN v_id_tipo_prest = v_tp_personal THEN 6 ELSE 60 END),
                    COALESCE(plazo_maximo_meses,
                             CASE WHEN v_id_tipo_prest = v_tp_personal THEN 36 ELSE 240 END)
                INTO
                    v_monto_minimo,
                    v_plazo_min,
                    v_plazo_max
                FROM tipo_prestamo
                WHERE id_tipo_prestamo = v_id_tipo_prest
                LIMIT 1;

                IF v_plazo_min IS NULL OR v_plazo_min <= 0 THEN
                    SET v_plazo_min = CASE WHEN v_id_tipo_prest = v_tp_personal THEN 6 ELSE 60 END;
                END IF;

                IF v_plazo_max IS NULL OR v_plazo_max < v_plazo_min THEN
                    SET v_plazo_max = v_plazo_min;
                END IF;

                -- Plazo aleatorio dentro del rango definido para el tipo
                SET v_plazo = v_plazo_min + FLOOR(RAND() * (v_plazo_max - v_plazo_min + 1));

                -- Cuota máxima permitida (40 % de capacidad) pero usando 35 % como margen
                SET v_cuota_max = v_capacidad * 0.40;

                -- Intentos para ajustar el monto al límite de capacidad
                SET v_int_retries = 0;

                retry_monto: WHILE v_int_retries < 3 DO

                    -- Monto candidato según tipo de préstamo
                    IF v_id_tipo_prest = v_tp_personal THEN
                        SET v_monto_prestamo = 30000 + (RAND() * 270000);  -- 30k – 300k
                    ELSE
                        SET v_monto_prestamo = 500000 + (RAND() * 4500000); -- 500k – 5M
                    END IF;
                    SET v_monto_prestamo = ROUND(v_monto_prestamo, 2);

                    -- Estimación de cuota usando aproximación método francés
                    SET v_int_anual   = v_tasa / 100.0;
                    SET v_int_mensual = v_int_anual / 12.0;

                    IF v_int_mensual > 0 THEN
                        SET v_int_factor = POW(1 + v_int_mensual, v_plazo);
                        SET v_int_parte  = (v_int_mensual * v_int_factor) / (v_int_factor - 1);
                        SET v_cuota_estimada = v_monto_prestamo * v_int_parte;
                    ELSE
                        -- Por seguridad, tratamos como préstamo sin interés
                        SET v_cuota_estimada = v_monto_prestamo / v_plazo;
                    END IF;

                    IF v_cuota_estimada <= (v_cuota_max * 0.95) THEN
                        LEAVE retry_monto;
                    ELSE
                        -- Reducir monto y reintentar
                        SET v_monto_prestamo = v_monto_prestamo * 0.6;
                        SET v_int_retries = v_int_retries + 1;
                    END IF;

                END WHILE retry_monto;

                -- Si aún sigue siendo demasiado alto, no creamos préstamo
                IF v_cuota_estimada <= v_cuota_max THEN

                    -- Fechas según escenario, dentro de Oct 2025 – Dic 2026
                    IF v_scenario = 1 THEN
                        -- Personal activo al día: fecha en 2026
                        SET v_fecha_solicitud = DATE_ADD(
                            GREATEST(v_fecha_min, DATE '2026-01-01'),
                            INTERVAL FLOOR(RAND() * (DATEDIFF(v_fecha_max, DATE '2026-01-01') + 1)) DAY
                        );
                    ELSEIF v_scenario = 2 THEN
                        -- En mora: más antiguos, 2025-10 a 2026-06
                        SET v_fecha_solicitud = DATE_ADD(
                            v_fecha_min,
                            INTERVAL FLOOR(RAND() * (DATEDIFF(DATE '2026-06-30', v_fecha_min) + 1)) DAY
                        );
                    ELSEIF v_scenario = 3 THEN
                        -- Hipotecario activo: rango completo, pero tendiendo a inicio 2026
                        SET v_fecha_solicitud = DATE_ADD(
                            GREATEST(v_fecha_min, DATE '2025-12-01'),
                            INTERVAL FLOOR(RAND() * (DATEDIFF(v_fecha_max, DATE '2025-12-01') + 1)) DAY
                        );
                    ELSE
                        -- Cerrados: más viejos dentro del rango
                        SET v_fecha_solicitud = DATE_ADD(
                            v_fecha_min,
                            INTERVAL FLOOR(RAND() * (DATEDIFF(DATE '2026-03-31', v_fecha_min) + 1)) DAY
                        );
                    END IF;

                    SET v_fecha_desembolso = DATE_ADD(v_fecha_solicitud, INTERVAL 1 + FLOOR(RAND()*4) DAY);

                    -- Insertar préstamo (el sistema generará cronograma en tu SP/trigger)
                    INSERT INTO prestamo(
                        id_cliente,
                        id_tipo_prestamo,
                        numero_contrato,
                        monto_solicitado,
                        fecha_solicitud,
                        plazo_meses,
                        id_estado_prestamo,
                        id_condicion_actual,
                        creado_por
                    )
                    VALUES(
                        v_id_cliente,
                        v_id_tipo_prest,
                        CONCAT('AUTO-', v_id_cliente, '-', v_i),
                        v_monto_prestamo,
                        v_fecha_solicitud,
                        v_plazo,
                        v_est_activo,
                        v_id_condicion,
                        v_user_default
                    );

                    SET v_id_prestamo = LAST_INSERT_ID();

                    -- Detalle por tipo
                    IF v_id_tipo_prest = v_tp_personal THEN
                        INSERT INTO prestamo_personal(id_prestamo, motivo)
                        VALUES (v_id_prestamo, 'Prestamo personal auto generado');
                    ELSE
                        INSERT INTO prestamo_hipotecario(
                            id_prestamo,
                            valor_propiedad,
                            porcentaje_financiamiento,
                            direccion_propiedad
                        )
                        VALUES (
                            v_id_prestamo,
                            v_monto_prestamo * 1.5,
                            ROUND(100 * (v_monto_prestamo / (v_monto_prestamo * 1.5)), 2),
                            CONCAT('Propiedad auto ', v_id_prestamo)
                        );
                    END IF;

                    -- Desembolso
                    INSERT INTO desembolso(
                        id_prestamo,
                        monto_desembolsado,
                        fecha_desembolso,
                        metodo_entrega,
                        creado_por
                    )
                    VALUES(
                        v_id_prestamo,
                        v_monto_prestamo,
                        v_fecha_desembolso,
                        v_pago_transferencia,
                        v_user_default
                    );

                    -- Generar cronograma (usa tu SP existente)
                    CALL generar_cronograma(v_id_prestamo, v_fecha_desembolso);

                    -- Ajustes por estado
                    IF v_scenario = 2 THEN
                        -- EN MORA: marcar cuotas vencidas + cargos
                        UPDATE cronograma_cuota
                        SET estado_cuota = 'Vencida',
                            cargos_cuota = ROUND(cargos_cuota + (saldo_cuota * 0.02), 2)
                        WHERE id_prestamo = v_id_prestamo
                          AND fecha_vencimiento < v_today
                          AND estado_cuota = 'Pendiente';

                        UPDATE prestamo
                        SET id_estado_prestamo = v_est_mora
                        WHERE id_prestamo = v_id_prestamo;

                    ELSEIF v_scenario = 4 THEN
                        -- CERRADO: todas las cuotas pagadas y saldo 0
                        UPDATE cronograma_cuota
                        SET estado_cuota = 'Pagada',
                            saldo_cuota = 0
                        WHERE id_prestamo = v_id_prestamo;

                        UPDATE prestamo
                        SET id_estado_prestamo = v_est_cerrado
                        WHERE id_prestamo = v_id_prestamo;
                    END IF;

                    -- Evaluación básica del préstamo
                    INSERT INTO evaluacion_prestamo (
                        id_cliente,
                        id_prestamo,
                        capacidad_pago,
                        nivel_riesgo,
                        estado_evaluacion,
                        fecha_evaluacion
                    ) VALUES (
                        v_id_cliente,
                        v_id_prestamo,
                        v_capacidad,
                        v_nivel_riesgo_id,
                        'Aprobado',
                        DATE_ADD(v_fecha_solicitud, INTERVAL 7 DAY)
                    );

                    INSERT INTO detalle_evaluacion (
                        id_evaluacion_prestamo,
                        fecha,
                        observacion,
                        evaluado_por
                    ) VALUES (
                        LAST_INSERT_ID(),
                        DATE_ADD(v_fecha_solicitud, INTERVAL 7 DAY),
                        CASE
                            WHEN v_nivel_riesgo_id = v_riesgo_bajo
                                THEN 'Alta capacidad de pago y buen historial.'
                            WHEN v_nivel_riesgo_id = v_riesgo_medio
                                THEN 'Capacidad ajustada, se recomienda monitoreo.'
                            ELSE 'Riesgo alto, otorgado con condiciones especiales.'
                        END,
                        v_user_default
                    );

                END IF; -- v_cuota_estimada <= v_cuota_max

            END IF; -- condición y tasa válidas

        END IF; -- v_scenario > 0

        SET v_i = v_i + 1;
    END WHILE;
END$$

DELIMITER ;
