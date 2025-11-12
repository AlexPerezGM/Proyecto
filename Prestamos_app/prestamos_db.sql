-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 10-11-2025 a las 15:44:55
-- Versión del servidor: 9.1.0
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `prestamos_db`
--

DELIMITER $$
--
-- Procedimientos
--
DROP PROCEDURE IF EXISTS `generar_cronograma`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `generar_cronograma` (IN `p_id_prestamo` INT)   BEGIN
    DECLARE v_monto_total DECIMAL(14,2);
    DECLARE v_plazo INT;
    DECLARE v_tasa_interes DECIMAL(5,2);
    DECLARE v_id_tipo_amortizacion INT;
    DECLARE v_fecha_inicial DATE;
    DECLARE v_cuota DECIMAL (14,2);
    DECLARE v_interes_cuota DECIMAL(14,2);
    DECLARE v_capital_cuota DECIMAL(14,2);
    DECLARE v_saldo DECIMAL(14,2);
    DECLARE v_r DECIMAL(14,6);
    DECLARE v_pow DECIMAL(14,6);
    DECLARE i INT DEFAULT 1;

    SELECT p.monto_solicitado, 
    p.plazo_meses, 
    tp.tasa_interes, 
    tp.id_tipo_amortizacion,
    p.fecha_solicitud
    INTO v_monto_total, 
    v_plazo, 
    v_tasa_interes, 
    v_id_tipo_amortizacion, 
    v_fecha_inicial
    FROM prestamo p
    JOIN tipo_prestamo tp ON p.id_tipo_prestamo = tp.id_tipo_prestamo
    WHERE p.id_prestamo = p_id_prestamo;

    SET v_saldo = v_monto_total;

    DELETE FROM cronograma_cuota WHERE id_prestamo = p_id_prestamo;

    IF v_monto_total IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Prestamo no encontrado';
    END IF;

    IF v_plazo IS NULL OR v_plazo <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Plazo no válido para el préstamo.';
    END IF;

    IF v_id_tipo_amortizacion = 1 THEN
        
        SET v_r = (v_tasa_interes / 100) / 12;
        SET v_pow = POW(1 + v_r, v_plazo);
        SET v_cuota = v_monto_total * ((v_r*v_pow)/(v_pow - 1));

        WHILE i <= v_plazo DO
            SET v_interes_cuota = v_saldo * v_r;
            SET v_capital_cuota = v_cuota - v_interes_cuota;
            SET v_saldo = v_saldo - v_capital_cuota;

            INSERT INTO cronograma_cuota (
                id_prestamo,
                numero_cuota,
                fecha_vencimiento,
                capital_cuota,
                interes_cuota,
                total_monto,
                saldo_cuota,
                estado_cuota
            )
            VALUES (
                p_id_prestamo, 
                i,
                DATE_ADD(v_fecha_inicial, INTERVAL i MONTH),
                ROUND(v_capital_cuota,2),
                ROUND(v_interes_cuota,2),
                ROUND(v_cuota, 2),
                ROUND(v_saldo,2),
                'Pendiente'
            );
            SET i = i + 1;
        END WHILE;

    ELSEIF v_id_tipo_amortizacion = 2 THEN 

        SET v_capital_cuota = v_monto_total / v_plazo;
        WHILE i <= v_plazo DO
            SET v_interes_cuota = v_saldo * (v_tasa_interes / 100) / 12;
            SET v_cuota = v_capital_cuota + v_interes_cuota;
            SET v_saldo = v_saldo - v_capital_cuota;

            INSERT INTO cronograma_cuota (
                id_prestamo,
                numero_cuota,
                fecha_vencimiento,
                capital_cuota,
                interes_cuota,
                total_monto,
                saldo_cuota,
                estado_cuota
            )
            VALUES (
                p_id_prestamo,
                i,
                DATE_ADD(v_fecha_inicial, INTERVAL i MONTH),
                ROUND(v_capital_cuota, 2),
                ROUND(v_interes_cuota, 2),
                ROUND(v_cuota, 2),
                ROUND(v_saldo, 2),
                'Pendiente'
            );
            SET i = i + 1;
        END WHILE;

    ELSEIF v_id_tipo_amortizacion = 3 THEN
        SET v_interes_cuota = (v_monto_total * (v_tasa_interes / 100)/ 12);
        WHILE  i <= v_plazo DO
            IF i < v_plazo THEN
                SET v_capital_cuota = 0;
                SET v_cuota = v_interes_cuota;
            ELSE 
                SET v_capital_cuota = v_monto_total;
                SET v_cuota = v_monto_total + v_interes_cuota;
                SET v_saldo = 0;
            END IF;

            INSERT INTO cronograma_cuota (
                id_prestamo,
                numero_cuota,
                fecha_vencimiento,
                capital_cuota,
                interes_cuota,
                total_monto,
                saldo_cuota,
                estado_cuota
            )
            VALUES (
                p_id_prestamo,
                i,
                DATE_ADD(v_fecha_inicial, INTERVAL i MONTH),
                ROUND(v_capital_cuota, 2),
                ROUND(v_interes_cuota, 2),
                ROUND(v_cuota, 2),
                ROUND(v_saldo, 2),
                'Pendiente'
            );
            SET i = i + 1;
        END WHILE;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `generar_pago`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `generar_pago` (IN `p_id_prestamo` INT, IN `p_monto` DECIMAL(14,2), IN `p_metodo` INT, IN `p_tipo_moneda` INT, IN `p_creado_por` INT, IN `p_fecha_pago` DATE, OUT `p_id_pago` INT)   BEGIN
    DECLARE v_estado_prestamo VARCHAR(50);
    DECLARE v_restante DECIMAL(14,2) DEFAULT 0.00;
    DECLARE v_id_cuota INT DEFAULT 0;
    DECLARE v_monto_cargo DECIMAL(14,2);
    DECLARE v_monto_interes DECIMAL(14,2);
    DECLARE v_monto_capital DECIMAL(14,2);
    DECLARE v_saldo DECIMAL(14,2);
    DECLARE v_tmp DECIMAL(14,2);

    IF p_monto <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto del pago es invalido.';
    END IF;

    SELECT ca.estado INTO v_estado_prestamo
    FROM prestamo pr 
    JOIN cat_estado_prestamo ca ON pr.id_estado_prestamo = ca.id_estado_prestamo
    WHERE pr.id_prestamo = p_id_prestamo;

    IF v_estado_prestamo IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Prestamo no encontrado.';
    END IF;

    INSERT INTO pago (
        id_prestamo,
        fecha_pago,
        monto_pagado,
        metodo_pago,
        id_tipo_moneda,
        creado_por
    ) VALUES (
        p_id_prestamo,
        p_fecha_pago,
        p_monto,
        p_metodo,
        p_tipo_moneda,
        p_creado_por
    );

    SET p_id_pago = LAST_INSERT_ID();
    SET v_restante = p_monto;

    pago_loop: LOOP

        SELECT id_cronograma_cuota, cargos_cuota, interes_cuota, capital_cuota, saldo_cuota
        INTO v_id_cuota, v_monto_cargo, v_monto_interes, v_monto_capital, v_saldo
        FROM cronograma_cuota
        WHERE id_prestamo = p_id_prestamo AND estado_cuota != 'Pagada'
        ORDER BY fecha_vencimiento ASC
        LIMIT 1;

        IF v_id_cuota IS NULL THEN
            LEAVE pago_loop;
        END IF;

        IF v_estado_prestamo = 'En mora' THEN
            SET v_tmp = LEAST(v_restante, v_monto_cargo);
            IF v_tmp > 0 THEN
                INSERT INTO asignacion_pago (
                    id_pago,
                    id_cronograma_cuota,
                    monto_asignado,
                    tipo_asignacion
                )
                VALUES (
                    p_id_pago,
                    v_id_cuota,
                    v_tmp,
                    'Cargos',
                    TRUE
                );
            END IF;
        END IF;

        IF v_restante > 0 THEN
            SET v_tmp = LEAST(v_restante, v_monto_interes);
            IF v_tmp > 0 THEN
                INSERT INTO asignacion_pago (
                    id_pago,
                    id_cronograma_cuota,
                    monto_asignado,
                    tipo_asignacion
                )
                VALUES (
                    p_id_pago,
                    v_id_cuota,
                    v_tmp,
                    'Intereses'
                );
                SET v_restante = v_restante - v_tmp;
                SET v_monto_interes = v_monto_interes - v_tmp;
            END IF;
        END IF;

        IF v_restante > 0 THEN
            SET v_tmp = LEAST(v_restante, v_monto_capital);
            IF v_tmp > 0 THEN
                INSERT INTO asignacion_pago (
                    id_pago,
                    id_cronograma_cuota,
                    monto_asignado,
                    tipo_asignacion
                )
                VALUES (
                    p_id_pago,
                    v_id_cuota,
                    v_tmp,
                    'Capital'
                );
                SET v_restante = v_restante - v_tmp;
                SET v_monto_capital = v_monto_capital - v_tmp;
            END IF;
        END IF;

        UPDATE cronograma_cuota
        SET
            cargos_cuota = ROUND(v_monto_cargo, 2),
            interes_cuota = ROUND(v_monto_interes, 2),
            capital_cuota = ROUND(v_monto_capital, 2),
            saldo_cuota = ROUND(v_saldo - (p_monto - v_restante), 2),
            estado_cuota = CASE WHEN (v_monto_cargo + v_monto_interes + v_monto_capital) <= 0 THEN 'Pagada'
                                ELSE 'Pendiente' END
                                WHERE id_cronograma_cuota = v_id_cuota;
        IF v_restante <= 0 THEN
            LEAVE pago_loop;
        END IF;
    END LOOP pago_loop;

    IF NOT EXISTS (SELECT 1 FROM cronograma_cuota WHERE id_prestamo = p_id_prestamo AND estado_cuota != 'Pagada') THEN
        UPDATE prestamo
        SET id_estado_prestamo = (SELECT id_estado_prestamo 
        FROM cat_estado_prestamo 
        WHERE estado = 'Cerrado' 
        LIMIT 1) 
        WHERE id_prestamo = p_id_prestamo;
    ELSE
        UPDATE prestamo
        SET id_estado_prestamo = (SELECT id_estado_prestamo 
        FROM cat_estado_prestamo
        WHERE estado = 'Activo' 
        LIMIT 1)
        WHERE id_prestamo = p_id_prestamo;
    END IF;

    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `otorgar_prestamo_financiador`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `otorgar_prestamo_financiador` (IN `p_id_financiador` INT, IN `p_monto` DECIMAL(14,2), IN `p_plazo` INT, IN `p_creado_por` INT)   BEGIN
    DECLARE v_tasa_base DECIMAL(5,2);
    DECLARE v_tasa_descuento DECIMAL(5,2);
    DECLARE v_tasa_final DECIMAL(5,2);
    DECLARE v_id_cliente INT;

    SELECT id_cliente INTO v_id_cliente
    FROM relacion_financiador_cliente
    WHERE id_financiador = p_id_financiador LIMIT 1;

    IF v_id_cliente IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El financiador no esta vinculado como cliente.';
    END IF;

    SELECT tasa_interes INTO v_tasa_base
    FROM tipo_prestamo
    WHERE id_tipo_prestamo = 1 limit 1;

    SELECT descuento_tasa_interes INTO v_tasa_descuento
    FROM ajuste_tasa_financiador
    WHERE id_financiador = p_id_financiador
        AND estado_ajuste = 'Activo'
        AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
    ORDER BY monto_minimo DESC 
    LIMIT 1;

    SET v_tasa_final = v_tasa_base - COALESCE(v_tasa_descuento, 0);

    INSERT INTO prestamo (
        id_cliente,
        id_tipo_prestamo,
        monto_solicitado,
        fecha_solicitud,
        plazo_meses,
        id_estado_prestamo,
        id_condicion_actual,
        creador_por
    )
    VALUES (
        v_id_cliente,
        (SELECT id_tipo_prestamo 
        FROM tipo_prestamo
        WHERE id_tipo_prestamo = 1 LIMIT 1),
        p_monto,
        CURDATE(),
        p_plazo,
        (SELECT id_estado_prestamo
        FROM cat_estado_prestamo 
        WHERE estado = 'Activo' LIMIT 1),
        p_creado_por
    );

    UPDATE tipo_prestamo
    SET tasa_interes = v_tasa_final
    WHERE id_tipo_prestamo = (SELECT id_tipo_prestamo
    FROM tipo_prestamo
    WHERE id_tipo_prestamo = 1 LIMIT 1);
END$$

DROP PROCEDURE IF EXISTS `reestructurar_prestamo`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `reestructurar_prestamo` (IN `p_id_prestamo` INT, IN `p_nuevo_monto` DECIMAL(14,2), IN `p_nuevo_plazo` INT, IN `p_nueva_tasa` DECIMAL(5,2), IN `p_tipo_interes` VARCHAR(50), IN `p_tipo_amortizacion` INT, IN `p_id_periodo_pago` INT, IN `p_usuario` INT)   BEGIN
    DECLARE v_id_condicion_nueva INT;
    DECLARE v_fecha DATE DEFAULT CURDATE();

    INSERT INTO condicion_prestamo(
        tasa_interes, tipo_interes, tipo_amortizacion, id_periodo_pago, vigente_desde, vigente_hasta, esta_activo)
    VALUES (
        p_nueva_tasa,
        p_tipo_interes,
        p_tipo_amortizacion,
        p_id_periodo_pago,
        v_fecha,
        NULL,
        TRUE
    );
    SET v_id_condicion_nueva = LAST_INSERT_ID();

    INSERT INTO reestructuracion_prestamo(
        id_prestamo,
        id_condicion_nueva,
        fecha_reestructuracion,
        aprobado_por,
        notas
    ) VALUES (
        p_id_prestamo,
        v_id_condicion_nueva,
        v_fecha,
        p_usuario,
        'Reestructuracion solicitada por el cliente'
    );

    UPDATE prestamo
    SET monto_solicitado = p_nuevo_monto,
        plazo_meses = p_nuevo_plazo,
        id_condicion_actual = v_id_condicion_nueva,
        id_estado_prestamo = (
            SELECT id_estado_prestamo 
            FROM cat_estado_prestamo 
            WHERE estado = 'Activo' 
            LIMIT 1
        )
    WHERE id_prestamo = p_id_prestamo;

    CALL generar_cronograma(p_id_prestamo);
END$$

DROP PROCEDURE IF EXISTS `seed_demo_60`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `seed_demo_60` (IN `p_cuantos` INT)   BEGIN
    DECLARE i INT DEFAULT 1;

    -- Estados (según catálogo)
    DECLARE v_est_activo INT;
    DECLARE v_est_eval   INT;
    DECLARE v_est_mora   INT;
    DECLARE v_est_cerr   INT;

    -- Tipos de préstamo (los 3 primeros disponibles)
    DECLARE v_tp1 INT; DECLARE v_tp2 INT; DECLARE v_tp3 INT;

    -- Variables por iteración
    DECLARE v_dp INT; DECLARE v_cl INT; DECLARE v_pr INT;
    DECLARE v_ing DECIMAL(14,2); DECLARE v_egr DECIMAL(14,2);
    DECLARE v_monto DECIMAL(14,2); DECLARE v_plazo INT;
    DECLARE v_tipo INT; DECLARE v_estado INT;

    -- Pago total
    DECLARE v_total_pend DECIMAL(14,2);
    DECLARE v_id_pago INT;

    -- Handlers para no abortar si alguna inserción falla (p.ej., por el 40%)
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Si algo falla dentro de un SAVEPOINT, revertimos esa parte y continuamos.
        ROLLBACK TO SAVEPOINT sp_loop;
    END;

    -- Estados
    SELECT id_estado_prestamo INTO v_est_activo FROM cat_estado_prestamo WHERE estado='Activo' LIMIT 1;         -- :contentReference[oaicite:10]{index=10}
    SELECT id_estado_prestamo INTO v_est_eval   FROM cat_estado_prestamo WHERE estado='En evaluacion' LIMIT 1;   -- :contentReference[oaicite:11]{index=11}
    SELECT id_estado_prestamo INTO v_est_mora   FROM cat_estado_prestamo WHERE estado='En mora' LIMIT 1;         -- :contentReference[oaicite:12]{index=12}
    SELECT id_estado_prestamo INTO v_est_cerr   FROM cat_estado_prestamo WHERE estado='Cerrado' LIMIT 1;         -- :contentReference[oaicite:13]{index=13}

    -- Tipos (no asumimos IDs fijos)
    SELECT id_tipo_prestamo INTO v_tp1 FROM tipo_prestamo ORDER BY id_tipo_prestamo LIMIT 1;
    SELECT id_tipo_prestamo INTO v_tp2 FROM tipo_prestamo ORDER BY id_tipo_prestamo LIMIT 1 OFFSET 1;
    SELECT id_tipo_prestamo INTO v_tp3 FROM tipo_prestamo ORDER BY id_tipo_prestamo LIMIT 1 OFFSET 2;

    WHILE i <= p_cuantos DO
        SAVEPOINT sp_loop;

        -- ========== 1) Persona + Cliente + Contacto ==========
        INSERT INTO datos_persona (nombre, apellido, fecha_nacimiento, genero)
        VALUES (
          CONCAT('Seed', LPAD(i,3,'0')),
          ELT(1 + (i % 5), 'Prueba','Semilla','Demo','Test','Pilot'),
          DATE_SUB(CURDATE(), INTERVAL (20 + (i % 25)) YEAR),
          1 + (i % 3)
        );
        SET v_dp = LAST_INSERT_ID();

        INSERT INTO cliente (id_datos_persona) VALUES (v_dp);
        SET v_cl = LAST_INSERT_ID();

        INSERT INTO documento_identidad (id_datos_persona, id_tipo_documento, numero_documento, fecha_emision)
        VALUES (v_dp, 1, CONCAT(LPAD(i,3,'0'),'-',LPAD(i*13,7,'0'),'-', (1 + (i%9))), DATE_SUB(CURDATE(), INTERVAL (5 + (i%7)) YEAR));

        INSERT INTO email (id_datos_persona, email, es_principal)
        VALUES (v_dp, CONCAT('seed', LPAD(i,3,'0'), '@example.com'), 1);

        INSERT INTO telefono (id_datos_persona, telefono, es_principal)
        VALUES (v_dp, CONCAT('809-555-', LPAD(1000 + i, 4, '0')), 1);

        INSERT INTO direccion (id_datos_persona, ciudad, sector, calle, numero_casa)
        VALUES (v_dp, ELT(1+(i%5),'Santo Domingo','Santiago','La Vega','San Cristóbal','Baní'),
                     ELT(1+(i%5),'Centro','Naco','Piantini','Los Jardines','Gascue'),
                     CONCAT('C/ ', ELT(1+(i%4),'Primera','Central','Libertad','Duarte')), 1 + (i%120));

        INSERT INTO ocupacion (id_datos_persona, ocupacion, empresa)
        VALUES (v_dp, ELT(1+(i%6),'Ingeniero','Contador','Técnico','Diseñador','Comerciante','Docente'),
                     ELT(1+(i%6),'Empresa A','Empresa B','Empresa C','Empresa D','Empresa E','Empresa F'));

        -- Ingresos / Egresos (sin periodo_reporte)
        SET v_ing = 60000 + (i * 1500);           -- sube con i
        SET v_egr = 20000 + (i * 700);            -- egresos < ingresos
        INSERT INTO ingresos_egresos (id_cliente, ingresos_mensuales, egresos_mensuales)
        VALUES (v_cl, v_ing, v_egr);  -- :contentReference[oaicite:14]{index=14}

        -- ========== 2) Definir qué escenario crear ==========
        CASE (i % 5)
          WHEN 1 THEN SET v_estado = v_est_activo;
          WHEN 2 THEN SET v_estado = v_est_activo;  -- insertamos activo y luego lo convertimos a mora
          WHEN 3 THEN SET v_estado = v_est_activo;  -- insertamos activo y luego lo pagamos (cerrado)
          WHEN 4 THEN SET v_estado = v_est_eval;
          WHEN 0 THEN SET v_estado = NULL;          -- solo registrado
        END CASE;

        -- Elegir tipo de préstamo rotando
        SET v_tipo = ELT(1 + (i % 3), v_tp1, v_tp2, v_tp3);

        -- Monto/plazo razonables para pasar la regla del 40% (trigger)
        SET v_plazo = ELT(1 + (i % 3), 12, 24, 36);
        SET v_monto =  (v_ing - v_egr) * 6;   -- prudente; el trigger calcula cuota y compara 40% :contentReference[oaicite:15]{index=15}

        -- ========== 3) Crear préstamos según escenario ==========
        IF v_estado IS NOT NULL THEN
            -- Principal préstamo
            INSERT INTO prestamo(
                id_cliente, id_tipo_prestamo, monto_solicitado, fecha_solicitud, plazo_meses, id_estado_prestamo, creado_por
            ) VALUES (
                v_cl, v_tipo, v_monto, CURDATE(), v_plazo, v_estado, NULL
            ); -- genera cronograma por trigger :contentReference[oaicite:16]{index=16}
            SET v_pr = LAST_INSERT_ID();

            -- Si se requiere "En mora": vencemos 2 cuotas y actualizamos estado
            IF (i % 5) = 2 THEN
               UPDATE cronograma_cuota
               SET fecha_vencimiento = DATE_SUB(CURDATE(), INTERVAL 45 DAY),
                   estado_cuota = 'Vencida',
                   cargos_cuota = cargos_cuota + 1500
               WHERE id_prestamo = v_pr AND numero_cuota IN (1,2);

               UPDATE prestamo
               SET id_estado_prestamo = v_est_mora
               WHERE id_prestamo = v_pr;  -- permitido porque el estado viejo no es 4/5 :contentReference[oaicite:17]{index=17}
            END IF;

            -- Si se requiere "Cerrado": pagar todo con el procedimiento de pago
            IF (i % 5) = 3 THEN
               SELECT COALESCE(SUM(saldo_cuota),0) INTO v_total_pend
               FROM cronograma_cuota
               WHERE id_prestamo = v_pr AND estado_cuota <> 'Pagada';

               -- Pagar en una sola transacción; los triggers de cuotas cerrarán el préstamo :contentReference[oaicite:18]{index=18}
               CALL generar_pago(v_pr, v_total_pend, 1, 1, 1, CURDATE(), v_id_pago);
            END IF;

            -- Si está "En evaluación" lo dejamos así; el cronograma ya quedó generado por el trigger.
        END IF;

        -- ========== 4) Clientes con múltiples préstamos ==========
        -- Cada 6º cliente tiene un 2º préstamo activo
        IF (i % 6) = 0 THEN
            INSERT INTO prestamo(id_cliente, id_tipo_prestamo, monto_solicitado, fecha_solicitud, plazo_meses, id_estado_prestamo, creado_por)
            SELECT v_cl, v_tp2, (v_ing - v_egr) * 5, CURDATE(), 18, v_est_activo, NULL;
        END IF;

        -- Cada 10º cliente tiene un 3º préstamo que queda en evaluación
        IF (i % 10) = 0 THEN
            INSERT INTO prestamo(id_cliente, id_tipo_prestamo, monto_solicitado, fecha_solicitud, plazo_meses, id_estado_prestamo, creado_por)
            SELECT v_cl, v_tp3, (v_ing - v_egr) * 4, CURDATE(), 24, v_est_eval, NULL;
        END IF;

        -- Nota: Si deseas poblar tablas hijas (prestamo_personal/hipotecario) puedes añadir aquí INSERTs condicionales según v_tipo.
        -- Estructuras disponibles:
        --  cronograma_cuota (total_monto, saldo_cuota, estado_cuota) :contentReference[oaicite:19]{index=19}
        --  triggers de cronograma y validaciones ya creados :contentReference[oaicite:20]{index=20} :contentReference[oaicite:21]{index=21}

        SET i = i + 1;
    END WHILE;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ajuste_tasa_financiador`
--

DROP TABLE IF EXISTS `ajuste_tasa_financiador`;
CREATE TABLE IF NOT EXISTS `ajuste_tasa_financiador` (
  `id_ajueste_tasa` int NOT NULL AUTO_INCREMENT,
  `id_financiador` int DEFAULT NULL,
  `monto_minimo` decimal(14,2) NOT NULL,
  `descuento_tasa_interes` decimal(5,2) NOT NULL,
  `tasa_financiacion_base` decimal(5,2) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado_ajuste` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_ajueste_tasa`),
  KEY `fk_financiador_ajuste_tasa` (`id_financiador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignacion_pago`
--

DROP TABLE IF EXISTS `asignacion_pago`;
CREATE TABLE IF NOT EXISTS `asignacion_pago` (
  `id_pago` int NOT NULL,
  `id_cronograma_cuota` int NOT NULL,
  `monto_asignado` decimal(14,2) NOT NULL,
  `tipo_asignacion` enum('Capital','Interes','Cargos') NOT NULL DEFAULT 'Capital',
  `fecha_asignacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pago`,`id_cronograma_cuota`),
  KEY `fk_cronograma_asignacion` (`id_cronograma_cuota`),
  KEY `idx_asignacion_pago` (`id_pago`,`id_cronograma_cuota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignacion_promocion`
--

DROP TABLE IF EXISTS `asignacion_promocion`;
CREATE TABLE IF NOT EXISTS `asignacion_promocion` (
  `id_asignacion_promocion` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_promocion` int DEFAULT NULL,
  `fecha_asignacion` date NOT NULL,
  `estado_promocion` enum('Activa','Usada','Expirada') DEFAULT 'Activa',
  PRIMARY KEY (`id_asignacion_promocion`),
  KEY `fk_asignacion_promocion_promocion` (`id_promocion`),
  KEY `idx_asignacion_promocion` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia_empleado`
--

DROP TABLE IF EXISTS `asistencia_empleado`;
CREATE TABLE IF NOT EXISTS `asistencia_empleado` (
  `id_asistencia_empleado` int NOT NULL AUTO_INCREMENT,
  `id_empleado` int DEFAULT NULL,
  `hora_entrada` timestamp NOT NULL,
  `hora_salida` timestamp NULL DEFAULT NULL,
  `retrasos` int DEFAULT '0',
  PRIMARY KEY (`id_asistencia_empleado`),
  KEY `idx_asistencia_empleado` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria_cambios`
--

DROP TABLE IF EXISTS `auditoria_cambios`;
CREATE TABLE IF NOT EXISTS `auditoria_cambios` (
  `id_auditoria` int NOT NULL AUTO_INCREMENT,
  `tabla_afectada` varchar(100) NOT NULL,
  `id_registro` int NOT NULL,
  `tipo_cambio` enum('INSERT','UPDATE','DELETE') NOT NULL DEFAULT 'UPDATE',
  `fecha_cambio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `valores_anteriores` json DEFAULT NULL,
  `valores_nuevos` json DEFAULT NULL,
  `realizado_por` int DEFAULT NULL,
  PRIMARY KEY (`id_auditoria`),
  KEY `fk_usuario_auditoria` (`realizado_por`),
  KEY `idx_auditoria_tabla_fecha` (`tabla_afectada`,`fecha_cambio`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `auditoria_cambios`
--

INSERT INTO `auditoria_cambios` (`id_auditoria`, `tabla_afectada`, `id_registro`, `tipo_cambio`, `fecha_cambio`, `valores_anteriores`, `valores_nuevos`, `realizado_por`) VALUES
(1, 'Cronograma_Cuota', 1, '', '2025-11-08 04:13:55', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(2, 'Cronograma_Cuota', 2, '', '2025-11-08 04:13:55', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(3, 'Cronograma_Cuota', 191, '', '2025-11-08 04:29:47', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(4, 'Cronograma_Cuota', 192, '', '2025-11-08 04:29:47', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(5, 'prestamo', 6, 'UPDATE', '2025-11-08 04:29:47', '{\"plazo_meses\": 24, \"monto_solicitado\": 273600.00, \"id_estado_prestamo\": 1}', '{\"plazo_meses\": 24, \"monto_solicitado\": 273600.00, \"id_estado_prestamo\": 4}', NULL),
(6, 'Cronograma_Cuota', 287, '', '2025-11-08 04:29:47', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(7, 'Cronograma_Cuota', 288, '', '2025-11-08 04:29:47', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(8, 'prestamo', 10, 'UPDATE', '2025-11-08 04:29:47', '{\"plazo_meses\": 24, \"monto_solicitado\": 345600.00, \"id_estado_prestamo\": 1}', '{\"plazo_meses\": 24, \"monto_solicitado\": 345600.00, \"id_estado_prestamo\": 4}', NULL),
(9, 'Cronograma_Cuota', 401, '', '2025-11-08 04:29:47', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(10, 'Cronograma_Cuota', 402, '', '2025-11-08 04:29:47', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(11, 'prestamo', 15, 'UPDATE', '2025-11-08 04:29:47', '{\"plazo_meses\": 24, \"monto_solicitado\": 417600.00, \"id_estado_prestamo\": 1}', '{\"plazo_meses\": 24, \"monto_solicitado\": 417600.00, \"id_estado_prestamo\": 4}', NULL),
(12, 'Cronograma_Cuota', 497, '', '2025-11-08 04:29:47', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(13, 'Cronograma_Cuota', 498, '', '2025-11-08 04:29:47', '{\"estado_cuota\": \"Pendiente\"}', '{\"estado_cuota\": \"Vencida\"}', NULL),
(14, 'prestamo', 19, 'UPDATE', '2025-11-08 04:29:47', '{\"plazo_meses\": 24, \"monto_solicitado\": 489600.00, \"id_estado_prestamo\": 1}', '{\"plazo_meses\": 24, \"monto_solicitado\": 489600.00, \"id_estado_prestamo\": 4}', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `beneficios_empleado`
--

DROP TABLE IF EXISTS `beneficios_empleado`;
CREATE TABLE IF NOT EXISTS `beneficios_empleado` (
  `id_nomina_empleado` int NOT NULL,
  `id_beneficio` int NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id_nomina_empleado`,`id_beneficio`),
  KEY `fk_tipobeneficio_beneficios` (`id_beneficio`),
  KEY `idx_beneficios_nomina` (`id_nomina_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja`
--

DROP TABLE IF EXISTS `caja`;
CREATE TABLE IF NOT EXISTS `caja` (
  `id_caja` int NOT NULL AUTO_INCREMENT,
  `id_empleado` int DEFAULT NULL,
  `monto_asignado` decimal(14,2) NOT NULL,
  `fecha_apertura` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_cierre` timestamp NULL DEFAULT NULL,
  `estado_caja` enum('Abierta','Cerrada') DEFAULT 'Abierta',
  PRIMARY KEY (`id_caja`),
  KEY `idx_caja_empleado` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `campanas`
--

DROP TABLE IF EXISTS `campanas`;
CREATE TABLE IF NOT EXISTS `campanas` (
  `id_campana` int NOT NULL AUTO_INCREMENT,
  `nombre_campana` varchar(100) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado_campana` enum('Activa','Inactiva') DEFAULT 'Inactiva',
  PRIMARY KEY (`id_campana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_beneficio`
--

DROP TABLE IF EXISTS `cat_beneficio`;
CREATE TABLE IF NOT EXISTS `cat_beneficio` (
  `id_beneficio` int NOT NULL AUTO_INCREMENT,
  `tipo_beneficio` varchar(50) NOT NULL,
  PRIMARY KEY (`id_beneficio`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_beneficio`
--

INSERT INTO `cat_beneficio` (`id_beneficio`, `tipo_beneficio`) VALUES
(1, 'Seguro médico'),
(2, 'Bonificación'),
(3, 'Vacaciones pagadas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_documentacion_cliente`
--

DROP TABLE IF EXISTS `cat_documentacion_cliente`;
CREATE TABLE IF NOT EXISTS `cat_documentacion_cliente` (
  `id_documentacion_cliente` int NOT NULL AUTO_INCREMENT,
  `tipo_documentacion` varchar(100) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  PRIMARY KEY (`id_documentacion_cliente`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_documentacion_cliente`
--

INSERT INTO `cat_documentacion_cliente` (`id_documentacion_cliente`, `tipo_documentacion`, `descripcion`) VALUES
(1, 'Identificacion oficial', 'Cedula, pasaporte o licencia de conducir'),
(2, 'Comprobante de ingresos', 'Recibos de sueldo, estados de cuenta bancarios'),
(3, 'Referencias personales', 'Contactos que puedan dar referencias sobre el cliente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_estado_prestamo`
--

DROP TABLE IF EXISTS `cat_estado_prestamo`;
CREATE TABLE IF NOT EXISTS `cat_estado_prestamo` (
  `id_estado_prestamo` int NOT NULL AUTO_INCREMENT,
  `estado` varchar(50) NOT NULL,
  PRIMARY KEY (`id_estado_prestamo`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_estado_prestamo`
--

INSERT INTO `cat_estado_prestamo` (`id_estado_prestamo`, `estado`) VALUES
(1, 'Activo'),
(2, 'En evaluacion'),
(3, 'Rechazado'),
(4, 'En mora'),
(5, 'Cerrado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_genero`
--

DROP TABLE IF EXISTS `cat_genero`;
CREATE TABLE IF NOT EXISTS `cat_genero` (
  `id_genero` int NOT NULL AUTO_INCREMENT,
  `genero` varchar(50) NOT NULL,
  PRIMARY KEY (`id_genero`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_genero`
--

INSERT INTO `cat_genero` (`id_genero`, `genero`) VALUES
(1, 'Masculino'),
(2, 'Femenino'),
(3, 'Otro');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_nivel_riesgo`
--

DROP TABLE IF EXISTS `cat_nivel_riesgo`;
CREATE TABLE IF NOT EXISTS `cat_nivel_riesgo` (
  `id_nivel_riesgo` int NOT NULL AUTO_INCREMENT,
  `nivel` varchar(50) NOT NULL,
  PRIMARY KEY (`id_nivel_riesgo`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_nivel_riesgo`
--

INSERT INTO `cat_nivel_riesgo` (`id_nivel_riesgo`, `nivel`) VALUES
(1, 'Bajo'),
(2, 'Medio'),
(3, 'Alto');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_periodo_pago`
--

DROP TABLE IF EXISTS `cat_periodo_pago`;
CREATE TABLE IF NOT EXISTS `cat_periodo_pago` (
  `id_periodo_pago` int NOT NULL AUTO_INCREMENT,
  `periodo` varchar(50) NOT NULL,
  PRIMARY KEY (`id_periodo_pago`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_periodo_pago`
--

INSERT INTO `cat_periodo_pago` (`id_periodo_pago`, `periodo`) VALUES
(1, 'Semanal'),
(2, 'Quincenal'),
(3, 'Mensual');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_amortizacion`
--

DROP TABLE IF EXISTS `cat_tipo_amortizacion`;
CREATE TABLE IF NOT EXISTS `cat_tipo_amortizacion` (
  `id_tipo_amortizacion` int NOT NULL AUTO_INCREMENT,
  `tipo_amortizacion` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_amortizacion`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_amortizacion`
--

INSERT INTO `cat_tipo_amortizacion` (`id_tipo_amortizacion`, `tipo_amortizacion`) VALUES
(1, 'Metodo Francés'),
(2, 'Metodo Alemán'),
(3, 'Diferida');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_contrato`
--

DROP TABLE IF EXISTS `cat_tipo_contrato`;
CREATE TABLE IF NOT EXISTS `cat_tipo_contrato` (
  `id_tipo_contrato` int NOT NULL AUTO_INCREMENT,
  `tipo_contrato` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_contrato`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_contrato`
--

INSERT INTO `cat_tipo_contrato` (`id_tipo_contrato`, `tipo_contrato`) VALUES
(1, 'Medio tiempo'),
(2, 'Tiempo completo'),
(3, 'Pasantia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_deduccion`
--

DROP TABLE IF EXISTS `cat_tipo_deduccion`;
CREATE TABLE IF NOT EXISTS `cat_tipo_deduccion` (
  `id_tipo_deduccion` int NOT NULL AUTO_INCREMENT,
  `tipo_deduccion` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_deduccion`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_deduccion`
--

INSERT INTO `cat_tipo_deduccion` (`id_tipo_deduccion`, `tipo_deduccion`) VALUES
(1, 'ISR'),
(2, 'AFP'),
(3, 'SFS');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_documento`
--

DROP TABLE IF EXISTS `cat_tipo_documento`;
CREATE TABLE IF NOT EXISTS `cat_tipo_documento` (
  `id_tipo_documento` int NOT NULL AUTO_INCREMENT,
  `tipo_documento` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_documento`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_documento`
--

INSERT INTO `cat_tipo_documento` (`id_tipo_documento`, `tipo_documento`) VALUES
(1, 'Cedula'),
(2, 'Pasaporte'),
(3, 'Licencia de conducir');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_entidad`
--

DROP TABLE IF EXISTS `cat_tipo_entidad`;
CREATE TABLE IF NOT EXISTS `cat_tipo_entidad` (
  `id_tipo_entidad` int NOT NULL AUTO_INCREMENT,
  `tipo_entidad` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_entidad`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_entidad`
--

INSERT INTO `cat_tipo_entidad` (`id_tipo_entidad`, `tipo_entidad`) VALUES
(1, 'Cliente'),
(2, 'Empleado'),
(3, 'Propiedad'),
(4, 'Financiador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_garantia`
--

DROP TABLE IF EXISTS `cat_tipo_garantia`;
CREATE TABLE IF NOT EXISTS `cat_tipo_garantia` (
  `id_tipo_garantia` int NOT NULL AUTO_INCREMENT,
  `tipo_garantia` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_tipo_garantia`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_garantia`
--

INSERT INTO `cat_tipo_garantia` (`id_tipo_garantia`, `tipo_garantia`, `descripcion`) VALUES
(1, 'Propiedad inmueble', 'Bienes raíces como casas o terrenos'),
(2, 'Vehículo', 'Automóviles, motocicletas u otros vehículos'),
(3, 'Prendas u otros', 'Joyas, electrodomésticos u objetos de valor'),
(4, 'Garante', 'Compromiso de un tercero para pagar en caso de incumplimiento');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_moneda`
--

DROP TABLE IF EXISTS `cat_tipo_moneda`;
CREATE TABLE IF NOT EXISTS `cat_tipo_moneda` (
  `id_tipo_moneda` int NOT NULL AUTO_INCREMENT,
  `tipo_moneda` varchar(50) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_tipo_moneda`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_moneda`
--

INSERT INTO `cat_tipo_moneda` (`id_tipo_moneda`, `tipo_moneda`, `valor`) VALUES
(1, 'DOP', 1.00),
(2, 'USD', 62.97);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_pago`
--

DROP TABLE IF EXISTS `cat_tipo_pago`;
CREATE TABLE IF NOT EXISTS `cat_tipo_pago` (
  `id_tipo_pago` int NOT NULL AUTO_INCREMENT,
  `tipo_pago` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_pago`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_pago`
--

INSERT INTO `cat_tipo_pago` (`id_tipo_pago`, `tipo_pago`) VALUES
(1, 'Efectivo'),
(2, 'Transferencia'),
(3, 'Cheque'),
(4, 'Garantía');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--

DROP TABLE IF EXISTS `cliente`;
CREATE TABLE IF NOT EXISTS `cliente` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `id_datos_persona` (`id_datos_persona`),
  KEY `idx_cliente_datos` (`id_datos_persona`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cliente`
--

INSERT INTO `cliente` (`id_cliente`, `id_datos_persona`) VALUES
(10, 16),
(11, 17),
(12, 18),
(13, 19),
(14, 20),
(17, 23),
(18, 24),
(20, 26),
(28, 34),
(29, 35),
(32, 38),
(35, 41),
(38, 44),
(44, 50),
(47, 53),
(48, 54),
(50, 56),
(58, 64),
(59, 65),
(62, 68),
(65, 71),
(68, 74);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `condicion_inversion`
--

DROP TABLE IF EXISTS `condicion_inversion`;
CREATE TABLE IF NOT EXISTS `condicion_inversion` (
  `id_condicion_inversion` int NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(255) NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_condicion_inversion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `condicion_prestamo`
--

DROP TABLE IF EXISTS `condicion_prestamo`;
CREATE TABLE IF NOT EXISTS `condicion_prestamo` (
  `id_condicion_prestamo` int NOT NULL AUTO_INCREMENT,
  `tasa_interes` decimal(5,2) NOT NULL,
  `tipo_interes` varchar(50) NOT NULL,
  `id_tipo_amortizacion` int NOT NULL,
  `id_periodo_pago` int DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `esta_activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_condicion_prestamo`),
  KEY `fk_periodo_pago_condicion` (`id_periodo_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contrato_empleado`
--

DROP TABLE IF EXISTS `contrato_empleado`;
CREATE TABLE IF NOT EXISTS `contrato_empleado` (
  `id_contrato` int NOT NULL AUTO_INCREMENT,
  `id_empleado` int NOT NULL,
  `cargo` varchar(120) NOT NULL,
  `id_tipo_contrato` int NOT NULL,
  `departamento` varchar(120) DEFAULT NULL,
  `fecha_contratacion` date NOT NULL,
  `salario_base` decimal(12,2) NOT NULL,
  `id_jefe` int DEFAULT NULL,
  `vigente` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_contrato`),
  KEY `id_empleado` (`id_empleado`),
  KEY `id_tipo_contrato` (`id_tipo_contrato`),
  KEY `id_jefe` (`id_jefe`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cronograma_cuota`
--

DROP TABLE IF EXISTS `cronograma_cuota`;
CREATE TABLE IF NOT EXISTS `cronograma_cuota` (
  `id_cronograma_cuota` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `numero_cuota` int NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `capital_cuota` decimal(14,2) NOT NULL,
  `interes_cuota` decimal(14,2) NOT NULL,
  `cargos_cuota` decimal(14,2) DEFAULT '0.00',
  `total_monto` decimal(14,2) NOT NULL,
  `saldo_cuota` decimal(14,2) NOT NULL,
  `estado_cuota` enum('Pendiente','Pagada','Vencida') DEFAULT 'Pendiente',
  PRIMARY KEY (`id_cronograma_cuota`),
  UNIQUE KEY `uq_numero_cuota` (`id_prestamo`,`numero_cuota`),
  KEY `idx_cronograma_prestamo` (`id_prestamo`,`estado_cuota`)
) ENGINE=InnoDB AUTO_INCREMENT=563 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cronograma_cuota`
--

INSERT INTO `cronograma_cuota` (`id_cronograma_cuota`, `id_prestamo`, `numero_cuota`, `fecha_vencimiento`, `capital_cuota`, `interes_cuota`, `cargos_cuota`, `total_monto`, `saldo_cuota`, `estado_cuota`) VALUES
(1, 1, 1, '2025-09-09', 47199.46, 6250.20, 1500.00, 53449.66, 552800.54, 'Vencida'),
(2, 1, 2, '2025-09-09', 47691.14, 5758.52, 1500.00, 53449.66, 505109.40, 'Vencida'),
(3, 1, 3, '2026-02-08', 48187.94, 5261.72, 0.00, 53449.66, 456921.46, 'Pendiente'),
(4, 1, 4, '2026-03-08', 48689.91, 4759.75, 0.00, 53449.66, 408231.55, 'Pendiente'),
(5, 1, 5, '2026-04-08', 49197.11, 4252.55, 0.00, 53449.66, 359034.44, 'Pendiente'),
(6, 1, 6, '2026-05-08', 49709.60, 3740.06, 0.00, 53449.66, 309324.84, 'Pendiente'),
(7, 1, 7, '2026-06-08', 50227.42, 3222.24, 0.00, 53449.66, 259097.42, 'Pendiente'),
(8, 1, 8, '2026-07-08', 50750.64, 2699.02, 0.00, 53449.66, 208346.78, 'Pendiente'),
(9, 1, 9, '2026-08-08', 51279.31, 2170.35, 0.00, 53449.66, 157067.47, 'Pendiente'),
(10, 1, 10, '2026-09-08', 51813.49, 1636.17, 0.00, 53449.66, 105253.98, 'Pendiente'),
(11, 1, 11, '2026-10-08', 52353.23, 1096.43, 0.00, 53449.66, 52900.75, 'Pendiente'),
(12, 1, 12, '2026-11-08', 52898.59, 551.07, 0.00, 53449.66, 2.16, 'Pendiente'),
(13, 2, 1, '2025-12-08', 10000.00, 8500.00, 0.00, 18500.00, 1190000.00, 'Pendiente'),
(14, 2, 2, '2026-01-08', 10000.00, 8429.17, 0.00, 18429.17, 1180000.00, 'Pendiente'),
(15, 2, 3, '2026-02-08', 10000.00, 8358.33, 0.00, 18358.33, 1170000.00, 'Pendiente'),
(16, 2, 4, '2026-03-08', 10000.00, 8287.50, 0.00, 18287.50, 1160000.00, 'Pendiente'),
(17, 2, 5, '2026-04-08', 10000.00, 8216.67, 0.00, 18216.67, 1150000.00, 'Pendiente'),
(18, 2, 6, '2026-05-08', 10000.00, 8145.83, 0.00, 18145.83, 1140000.00, 'Pendiente'),
(19, 2, 7, '2026-06-08', 10000.00, 8075.00, 0.00, 18075.00, 1130000.00, 'Pendiente'),
(20, 2, 8, '2026-07-08', 10000.00, 8004.17, 0.00, 18004.17, 1120000.00, 'Pendiente'),
(21, 2, 9, '2026-08-08', 10000.00, 7933.33, 0.00, 17933.33, 1110000.00, 'Pendiente'),
(22, 2, 10, '2026-09-08', 10000.00, 7862.50, 0.00, 17862.50, 1100000.00, 'Pendiente'),
(23, 2, 11, '2026-10-08', 10000.00, 7791.67, 0.00, 17791.67, 1090000.00, 'Pendiente'),
(24, 2, 12, '2026-11-08', 10000.00, 7720.83, 0.00, 17720.83, 1080000.00, 'Pendiente'),
(25, 2, 13, '2026-12-08', 10000.00, 7650.00, 0.00, 17650.00, 1070000.00, 'Pendiente'),
(26, 2, 14, '2027-01-08', 10000.00, 7579.17, 0.00, 17579.17, 1060000.00, 'Pendiente'),
(27, 2, 15, '2027-02-08', 10000.00, 7508.33, 0.00, 17508.33, 1050000.00, 'Pendiente'),
(28, 2, 16, '2027-03-08', 10000.00, 7437.50, 0.00, 17437.50, 1040000.00, 'Pendiente'),
(29, 2, 17, '2027-04-08', 10000.00, 7366.67, 0.00, 17366.67, 1030000.00, 'Pendiente'),
(30, 2, 18, '2027-05-08', 10000.00, 7295.83, 0.00, 17295.83, 1020000.00, 'Pendiente'),
(31, 2, 19, '2027-06-08', 10000.00, 7225.00, 0.00, 17225.00, 1010000.00, 'Pendiente'),
(32, 2, 20, '2027-07-08', 10000.00, 7154.17, 0.00, 17154.17, 1000000.00, 'Pendiente'),
(33, 2, 21, '2027-08-08', 10000.00, 7083.33, 0.00, 17083.33, 990000.00, 'Pendiente'),
(34, 2, 22, '2027-09-08', 10000.00, 7012.50, 0.00, 17012.50, 980000.00, 'Pendiente'),
(35, 2, 23, '2027-10-08', 10000.00, 6941.67, 0.00, 16941.67, 970000.00, 'Pendiente'),
(36, 2, 24, '2027-11-08', 10000.00, 6870.83, 0.00, 16870.83, 960000.00, 'Pendiente'),
(37, 2, 25, '2027-12-08', 10000.00, 6800.00, 0.00, 16800.00, 950000.00, 'Pendiente'),
(38, 2, 26, '2028-01-08', 10000.00, 6729.17, 0.00, 16729.17, 940000.00, 'Pendiente'),
(39, 2, 27, '2028-02-08', 10000.00, 6658.33, 0.00, 16658.33, 930000.00, 'Pendiente'),
(40, 2, 28, '2028-03-08', 10000.00, 6587.50, 0.00, 16587.50, 920000.00, 'Pendiente'),
(41, 2, 29, '2028-04-08', 10000.00, 6516.67, 0.00, 16516.67, 910000.00, 'Pendiente'),
(42, 2, 30, '2028-05-08', 10000.00, 6445.83, 0.00, 16445.83, 900000.00, 'Pendiente'),
(43, 2, 31, '2028-06-08', 10000.00, 6375.00, 0.00, 16375.00, 890000.00, 'Pendiente'),
(44, 2, 32, '2028-07-08', 10000.00, 6304.17, 0.00, 16304.17, 880000.00, 'Pendiente'),
(45, 2, 33, '2028-08-08', 10000.00, 6233.33, 0.00, 16233.33, 870000.00, 'Pendiente'),
(46, 2, 34, '2028-09-08', 10000.00, 6162.50, 0.00, 16162.50, 860000.00, 'Pendiente'),
(47, 2, 35, '2028-10-08', 10000.00, 6091.67, 0.00, 16091.67, 850000.00, 'Pendiente'),
(48, 2, 36, '2028-11-08', 10000.00, 6020.83, 0.00, 16020.83, 840000.00, 'Pendiente'),
(49, 2, 37, '2028-12-08', 10000.00, 5950.00, 0.00, 15950.00, 830000.00, 'Pendiente'),
(50, 2, 38, '2029-01-08', 10000.00, 5879.17, 0.00, 15879.17, 820000.00, 'Pendiente'),
(51, 2, 39, '2029-02-08', 10000.00, 5808.33, 0.00, 15808.33, 810000.00, 'Pendiente'),
(52, 2, 40, '2029-03-08', 10000.00, 5737.50, 0.00, 15737.50, 800000.00, 'Pendiente'),
(53, 2, 41, '2029-04-08', 10000.00, 5666.67, 0.00, 15666.67, 790000.00, 'Pendiente'),
(54, 2, 42, '2029-05-08', 10000.00, 5595.83, 0.00, 15595.83, 780000.00, 'Pendiente'),
(55, 2, 43, '2029-06-08', 10000.00, 5525.00, 0.00, 15525.00, 770000.00, 'Pendiente'),
(56, 2, 44, '2029-07-08', 10000.00, 5454.17, 0.00, 15454.17, 760000.00, 'Pendiente'),
(57, 2, 45, '2029-08-08', 10000.00, 5383.33, 0.00, 15383.33, 750000.00, 'Pendiente'),
(58, 2, 46, '2029-09-08', 10000.00, 5312.50, 0.00, 15312.50, 740000.00, 'Pendiente'),
(59, 2, 47, '2029-10-08', 10000.00, 5241.67, 0.00, 15241.67, 730000.00, 'Pendiente'),
(60, 2, 48, '2029-11-08', 10000.00, 5170.83, 0.00, 15170.83, 720000.00, 'Pendiente'),
(61, 2, 49, '2029-12-08', 10000.00, 5100.00, 0.00, 15100.00, 710000.00, 'Pendiente'),
(62, 2, 50, '2030-01-08', 10000.00, 5029.17, 0.00, 15029.17, 700000.00, 'Pendiente'),
(63, 2, 51, '2030-02-08', 10000.00, 4958.33, 0.00, 14958.33, 690000.00, 'Pendiente'),
(64, 2, 52, '2030-03-08', 10000.00, 4887.50, 0.00, 14887.50, 680000.00, 'Pendiente'),
(65, 2, 53, '2030-04-08', 10000.00, 4816.67, 0.00, 14816.67, 670000.00, 'Pendiente'),
(66, 2, 54, '2030-05-08', 10000.00, 4745.83, 0.00, 14745.83, 660000.00, 'Pendiente'),
(67, 2, 55, '2030-06-08', 10000.00, 4675.00, 0.00, 14675.00, 650000.00, 'Pendiente'),
(68, 2, 56, '2030-07-08', 10000.00, 4604.17, 0.00, 14604.17, 640000.00, 'Pendiente'),
(69, 2, 57, '2030-08-08', 10000.00, 4533.33, 0.00, 14533.33, 630000.00, 'Pendiente'),
(70, 2, 58, '2030-09-08', 10000.00, 4462.50, 0.00, 14462.50, 620000.00, 'Pendiente'),
(71, 2, 59, '2030-10-08', 10000.00, 4391.67, 0.00, 14391.67, 610000.00, 'Pendiente'),
(72, 2, 60, '2030-11-08', 10000.00, 4320.83, 0.00, 14320.83, 600000.00, 'Pendiente'),
(73, 2, 61, '2030-12-08', 10000.00, 4250.00, 0.00, 14250.00, 590000.00, 'Pendiente'),
(74, 2, 62, '2031-01-08', 10000.00, 4179.17, 0.00, 14179.17, 580000.00, 'Pendiente'),
(75, 2, 63, '2031-02-08', 10000.00, 4108.33, 0.00, 14108.33, 570000.00, 'Pendiente'),
(76, 2, 64, '2031-03-08', 10000.00, 4037.50, 0.00, 14037.50, 560000.00, 'Pendiente'),
(77, 2, 65, '2031-04-08', 10000.00, 3966.67, 0.00, 13966.67, 550000.00, 'Pendiente'),
(78, 2, 66, '2031-05-08', 10000.00, 3895.83, 0.00, 13895.83, 540000.00, 'Pendiente'),
(79, 2, 67, '2031-06-08', 10000.00, 3825.00, 0.00, 13825.00, 530000.00, 'Pendiente'),
(80, 2, 68, '2031-07-08', 10000.00, 3754.17, 0.00, 13754.17, 520000.00, 'Pendiente'),
(81, 2, 69, '2031-08-08', 10000.00, 3683.33, 0.00, 13683.33, 510000.00, 'Pendiente'),
(82, 2, 70, '2031-09-08', 10000.00, 3612.50, 0.00, 13612.50, 500000.00, 'Pendiente'),
(83, 2, 71, '2031-10-08', 10000.00, 3541.67, 0.00, 13541.67, 490000.00, 'Pendiente'),
(84, 2, 72, '2031-11-08', 10000.00, 3470.83, 0.00, 13470.83, 480000.00, 'Pendiente'),
(85, 2, 73, '2031-12-08', 10000.00, 3400.00, 0.00, 13400.00, 470000.00, 'Pendiente'),
(86, 2, 74, '2032-01-08', 10000.00, 3329.17, 0.00, 13329.17, 460000.00, 'Pendiente'),
(87, 2, 75, '2032-02-08', 10000.00, 3258.33, 0.00, 13258.33, 450000.00, 'Pendiente'),
(88, 2, 76, '2032-03-08', 10000.00, 3187.50, 0.00, 13187.50, 440000.00, 'Pendiente'),
(89, 2, 77, '2032-04-08', 10000.00, 3116.67, 0.00, 13116.67, 430000.00, 'Pendiente'),
(90, 2, 78, '2032-05-08', 10000.00, 3045.83, 0.00, 13045.83, 420000.00, 'Pendiente'),
(91, 2, 79, '2032-06-08', 10000.00, 2975.00, 0.00, 12975.00, 410000.00, 'Pendiente'),
(92, 2, 80, '2032-07-08', 10000.00, 2904.17, 0.00, 12904.17, 400000.00, 'Pendiente'),
(93, 2, 81, '2032-08-08', 10000.00, 2833.33, 0.00, 12833.33, 390000.00, 'Pendiente'),
(94, 2, 82, '2032-09-08', 10000.00, 2762.50, 0.00, 12762.50, 380000.00, 'Pendiente'),
(95, 2, 83, '2032-10-08', 10000.00, 2691.67, 0.00, 12691.67, 370000.00, 'Pendiente'),
(96, 2, 84, '2032-11-08', 10000.00, 2620.83, 0.00, 12620.83, 360000.00, 'Pendiente'),
(97, 2, 85, '2032-12-08', 10000.00, 2550.00, 0.00, 12550.00, 350000.00, 'Pendiente'),
(98, 2, 86, '2033-01-08', 10000.00, 2479.17, 0.00, 12479.17, 340000.00, 'Pendiente'),
(99, 2, 87, '2033-02-08', 10000.00, 2408.33, 0.00, 12408.33, 330000.00, 'Pendiente'),
(100, 2, 88, '2033-03-08', 10000.00, 2337.50, 0.00, 12337.50, 320000.00, 'Pendiente'),
(101, 2, 89, '2033-04-08', 10000.00, 2266.67, 0.00, 12266.67, 310000.00, 'Pendiente'),
(102, 2, 90, '2033-05-08', 10000.00, 2195.83, 0.00, 12195.83, 300000.00, 'Pendiente'),
(103, 2, 91, '2033-06-08', 10000.00, 2125.00, 0.00, 12125.00, 290000.00, 'Pendiente'),
(104, 2, 92, '2033-07-08', 10000.00, 2054.17, 0.00, 12054.17, 280000.00, 'Pendiente'),
(105, 2, 93, '2033-08-08', 10000.00, 1983.33, 0.00, 11983.33, 270000.00, 'Pendiente'),
(106, 2, 94, '2033-09-08', 10000.00, 1912.50, 0.00, 11912.50, 260000.00, 'Pendiente'),
(107, 2, 95, '2033-10-08', 10000.00, 1841.67, 0.00, 11841.67, 250000.00, 'Pendiente'),
(108, 2, 96, '2033-11-08', 10000.00, 1770.83, 0.00, 11770.83, 240000.00, 'Pendiente'),
(109, 2, 97, '2033-12-08', 10000.00, 1700.00, 0.00, 11700.00, 230000.00, 'Pendiente'),
(110, 2, 98, '2034-01-08', 10000.00, 1629.17, 0.00, 11629.17, 220000.00, 'Pendiente'),
(111, 2, 99, '2034-02-08', 10000.00, 1558.33, 0.00, 11558.33, 210000.00, 'Pendiente'),
(112, 2, 100, '2034-03-08', 10000.00, 1487.50, 0.00, 11487.50, 200000.00, 'Pendiente'),
(113, 2, 101, '2034-04-08', 10000.00, 1416.67, 0.00, 11416.67, 190000.00, 'Pendiente'),
(114, 2, 102, '2034-05-08', 10000.00, 1345.83, 0.00, 11345.83, 180000.00, 'Pendiente'),
(115, 2, 103, '2034-06-08', 10000.00, 1275.00, 0.00, 11275.00, 170000.00, 'Pendiente'),
(116, 2, 104, '2034-07-08', 10000.00, 1204.17, 0.00, 11204.17, 160000.00, 'Pendiente'),
(117, 2, 105, '2034-08-08', 10000.00, 1133.33, 0.00, 11133.33, 150000.00, 'Pendiente'),
(118, 2, 106, '2034-09-08', 10000.00, 1062.50, 0.00, 11062.50, 140000.00, 'Pendiente'),
(119, 2, 107, '2034-10-08', 10000.00, 991.67, 0.00, 10991.67, 130000.00, 'Pendiente'),
(120, 2, 108, '2034-11-08', 10000.00, 920.83, 0.00, 10920.83, 120000.00, 'Pendiente'),
(121, 2, 109, '2034-12-08', 10000.00, 850.00, 0.00, 10850.00, 110000.00, 'Pendiente'),
(122, 2, 110, '2035-01-08', 10000.00, 779.17, 0.00, 10779.17, 100000.00, 'Pendiente'),
(123, 2, 111, '2035-02-08', 10000.00, 708.33, 0.00, 10708.33, 90000.00, 'Pendiente'),
(124, 2, 112, '2035-03-08', 10000.00, 637.50, 0.00, 10637.50, 80000.00, 'Pendiente'),
(125, 2, 113, '2035-04-08', 10000.00, 566.67, 0.00, 10566.67, 70000.00, 'Pendiente'),
(126, 2, 114, '2035-05-08', 10000.00, 495.83, 0.00, 10495.83, 60000.00, 'Pendiente'),
(127, 2, 115, '2035-06-08', 10000.00, 425.00, 0.00, 10425.00, 50000.00, 'Pendiente'),
(128, 2, 116, '2035-07-08', 10000.00, 354.17, 0.00, 10354.17, 40000.00, 'Pendiente'),
(129, 2, 117, '2035-08-08', 10000.00, 283.33, 0.00, 10283.33, 30000.00, 'Pendiente'),
(130, 2, 118, '2035-09-08', 10000.00, 212.50, 0.00, 10212.50, 20000.00, 'Pendiente'),
(131, 2, 119, '2035-10-08', 10000.00, 141.67, 0.00, 10141.67, 10000.00, 'Pendiente'),
(132, 2, 120, '2035-11-08', 10000.00, 70.83, 0.00, 10070.83, 0.00, 'Pendiente'),
(133, 3, 1, '2025-12-08', 14310.25, 1562.55, 0.00, 15872.80, 135689.75, 'Pendiente'),
(134, 3, 2, '2026-01-08', 14459.32, 1413.48, 0.00, 15872.80, 121230.43, 'Pendiente'),
(135, 3, 3, '2026-02-08', 14609.94, 1262.86, 0.00, 15872.80, 106620.49, 'Pendiente'),
(136, 3, 4, '2026-03-08', 14762.13, 1110.67, 0.00, 15872.80, 91858.36, 'Pendiente'),
(137, 3, 5, '2026-04-08', 14915.91, 956.89, 0.00, 15872.80, 76942.45, 'Pendiente'),
(138, 3, 6, '2026-05-08', 15071.29, 801.51, 0.00, 15872.80, 61871.16, 'Pendiente'),
(139, 3, 7, '2026-06-08', 15228.29, 644.51, 0.00, 15872.80, 46642.87, 'Pendiente'),
(140, 3, 8, '2026-07-08', 15386.92, 485.88, 0.00, 15872.80, 31255.95, 'Pendiente'),
(141, 3, 9, '2026-08-08', 15547.21, 325.59, 0.00, 15872.80, 15708.74, 'Pendiente'),
(142, 3, 10, '2026-09-08', 15709.16, 163.64, 0.00, 15872.80, -0.42, 'Pendiente'),
(143, 4, 1, '2025-12-08', 10200.00, 1734.00, 0.00, 11934.00, 234600.00, 'Pendiente'),
(144, 4, 2, '2026-01-08', 10200.00, 1661.75, 0.00, 11861.75, 224400.00, 'Pendiente'),
(145, 4, 3, '2026-02-08', 10200.00, 1589.50, 0.00, 11789.50, 214200.00, 'Pendiente'),
(146, 4, 4, '2026-03-08', 10200.00, 1517.25, 0.00, 11717.25, 204000.00, 'Pendiente'),
(147, 4, 5, '2026-04-08', 10200.00, 1445.00, 0.00, 11645.00, 193800.00, 'Pendiente'),
(148, 4, 6, '2026-05-08', 10200.00, 1372.75, 0.00, 11572.75, 183600.00, 'Pendiente'),
(149, 4, 7, '2026-06-08', 10200.00, 1300.50, 0.00, 11500.50, 173400.00, 'Pendiente'),
(150, 4, 8, '2026-07-08', 10200.00, 1228.25, 0.00, 11428.25, 163200.00, 'Pendiente'),
(151, 4, 9, '2026-08-08', 10200.00, 1156.00, 0.00, 11356.00, 153000.00, 'Pendiente'),
(152, 4, 10, '2026-09-08', 10200.00, 1083.75, 0.00, 11283.75, 142800.00, 'Pendiente'),
(153, 4, 11, '2026-10-08', 10200.00, 1011.50, 0.00, 11211.50, 132600.00, 'Pendiente'),
(154, 4, 12, '2026-11-08', 10200.00, 939.25, 0.00, 11139.25, 122400.00, 'Pendiente'),
(155, 4, 13, '2026-12-08', 10200.00, 867.00, 0.00, 11067.00, 112200.00, 'Pendiente'),
(156, 4, 14, '2027-01-08', 10200.00, 794.75, 0.00, 10994.75, 102000.00, 'Pendiente'),
(157, 4, 15, '2027-02-08', 10200.00, 722.50, 0.00, 10922.50, 91800.00, 'Pendiente'),
(158, 4, 16, '2027-03-08', 10200.00, 650.25, 0.00, 10850.25, 81600.00, 'Pendiente'),
(159, 4, 17, '2027-04-08', 10200.00, 578.00, 0.00, 10778.00, 71400.00, 'Pendiente'),
(160, 4, 18, '2027-05-08', 10200.00, 505.75, 0.00, 10705.75, 61200.00, 'Pendiente'),
(161, 4, 19, '2027-06-08', 10200.00, 433.50, 0.00, 10633.50, 51000.00, 'Pendiente'),
(162, 4, 20, '2027-07-08', 10200.00, 361.25, 0.00, 10561.25, 40800.00, 'Pendiente'),
(163, 4, 21, '2027-08-08', 10200.00, 289.00, 0.00, 10489.00, 30600.00, 'Pendiente'),
(164, 4, 22, '2027-09-08', 10200.00, 216.75, 0.00, 10416.75, 20400.00, 'Pendiente'),
(165, 4, 23, '2027-10-08', 10200.00, 144.50, 0.00, 10344.50, 10200.00, 'Pendiente'),
(166, 4, 24, '2027-11-08', 10200.00, 72.25, 0.00, 10272.25, 0.00, 'Pendiente'),
(167, 5, 1, '2025-12-08', 10800.00, 1836.00, 0.00, 12636.00, 248400.00, 'Pendiente'),
(168, 5, 2, '2026-01-08', 10800.00, 1759.50, 0.00, 12559.50, 237600.00, 'Pendiente'),
(169, 5, 3, '2026-02-08', 10800.00, 1683.00, 0.00, 12483.00, 226800.00, 'Pendiente'),
(170, 5, 4, '2026-03-08', 10800.00, 1606.50, 0.00, 12406.50, 216000.00, 'Pendiente'),
(171, 5, 5, '2026-04-08', 10800.00, 1530.00, 0.00, 12330.00, 205200.00, 'Pendiente'),
(172, 5, 6, '2026-05-08', 10800.00, 1453.50, 0.00, 12253.50, 194400.00, 'Pendiente'),
(173, 5, 7, '2026-06-08', 10800.00, 1377.00, 0.00, 12177.00, 183600.00, 'Pendiente'),
(174, 5, 8, '2026-07-08', 10800.00, 1300.50, 0.00, 12100.50, 172800.00, 'Pendiente'),
(175, 5, 9, '2026-08-08', 10800.00, 1224.00, 0.00, 12024.00, 162000.00, 'Pendiente'),
(176, 5, 10, '2026-09-08', 10800.00, 1147.50, 0.00, 11947.50, 151200.00, 'Pendiente'),
(177, 5, 11, '2026-10-08', 10800.00, 1071.00, 0.00, 11871.00, 140400.00, 'Pendiente'),
(178, 5, 12, '2026-11-08', 10800.00, 994.50, 0.00, 11794.50, 129600.00, 'Pendiente'),
(179, 5, 13, '2026-12-08', 10800.00, 918.00, 0.00, 11718.00, 118800.00, 'Pendiente'),
(180, 5, 14, '2027-01-08', 10800.00, 841.50, 0.00, 11641.50, 108000.00, 'Pendiente'),
(181, 5, 15, '2027-02-08', 10800.00, 765.00, 0.00, 11565.00, 97200.00, 'Pendiente'),
(182, 5, 16, '2027-03-08', 10800.00, 688.50, 0.00, 11488.50, 86400.00, 'Pendiente'),
(183, 5, 17, '2027-04-08', 10800.00, 612.00, 0.00, 11412.00, 75600.00, 'Pendiente'),
(184, 5, 18, '2027-05-08', 10800.00, 535.50, 0.00, 11335.50, 64800.00, 'Pendiente'),
(185, 5, 19, '2027-06-08', 10800.00, 459.00, 0.00, 11259.00, 54000.00, 'Pendiente'),
(186, 5, 20, '2027-07-08', 10800.00, 382.50, 0.00, 11182.50, 43200.00, 'Pendiente'),
(187, 5, 21, '2027-08-08', 10800.00, 306.00, 0.00, 11106.00, 32400.00, 'Pendiente'),
(188, 5, 22, '2027-09-08', 10800.00, 229.50, 0.00, 11029.50, 21600.00, 'Pendiente'),
(189, 5, 23, '2027-10-08', 10800.00, 153.00, 0.00, 10953.00, 10800.00, 'Pendiente'),
(190, 5, 24, '2027-11-08', 10800.00, 76.50, 0.00, 10876.50, 0.00, 'Pendiente'),
(191, 6, 1, '2025-09-24', 11400.00, 1938.00, 1500.00, 13338.00, 262200.00, 'Vencida'),
(192, 6, 2, '2025-09-24', 11400.00, 1857.25, 1500.00, 13257.25, 250800.00, 'Vencida'),
(193, 6, 3, '2026-02-08', 11400.00, 1776.50, 0.00, 13176.50, 239400.00, 'Pendiente'),
(194, 6, 4, '2026-03-08', 11400.00, 1695.75, 0.00, 13095.75, 228000.00, 'Pendiente'),
(195, 6, 5, '2026-04-08', 11400.00, 1615.00, 0.00, 13015.00, 216600.00, 'Pendiente'),
(196, 6, 6, '2026-05-08', 11400.00, 1534.25, 0.00, 12934.25, 205200.00, 'Pendiente'),
(197, 6, 7, '2026-06-08', 11400.00, 1453.50, 0.00, 12853.50, 193800.00, 'Pendiente'),
(198, 6, 8, '2026-07-08', 11400.00, 1372.75, 0.00, 12772.75, 182400.00, 'Pendiente'),
(199, 6, 9, '2026-08-08', 11400.00, 1292.00, 0.00, 12692.00, 171000.00, 'Pendiente'),
(200, 6, 10, '2026-09-08', 11400.00, 1211.25, 0.00, 12611.25, 159600.00, 'Pendiente'),
(201, 6, 11, '2026-10-08', 11400.00, 1130.50, 0.00, 12530.50, 148200.00, 'Pendiente'),
(202, 6, 12, '2026-11-08', 11400.00, 1049.75, 0.00, 12449.75, 136800.00, 'Pendiente'),
(203, 6, 13, '2026-12-08', 11400.00, 969.00, 0.00, 12369.00, 125400.00, 'Pendiente'),
(204, 6, 14, '2027-01-08', 11400.00, 888.25, 0.00, 12288.25, 114000.00, 'Pendiente'),
(205, 6, 15, '2027-02-08', 11400.00, 807.50, 0.00, 12207.50, 102600.00, 'Pendiente'),
(206, 6, 16, '2027-03-08', 11400.00, 726.75, 0.00, 12126.75, 91200.00, 'Pendiente'),
(207, 6, 17, '2027-04-08', 11400.00, 646.00, 0.00, 12046.00, 79800.00, 'Pendiente'),
(208, 6, 18, '2027-05-08', 11400.00, 565.25, 0.00, 11965.25, 68400.00, 'Pendiente'),
(209, 6, 19, '2027-06-08', 11400.00, 484.50, 0.00, 11884.50, 57000.00, 'Pendiente'),
(210, 6, 20, '2027-07-08', 11400.00, 403.75, 0.00, 11803.75, 45600.00, 'Pendiente'),
(211, 6, 21, '2027-08-08', 11400.00, 323.00, 0.00, 11723.00, 34200.00, 'Pendiente'),
(212, 6, 22, '2027-09-08', 11400.00, 242.25, 0.00, 11642.25, 22800.00, 'Pendiente'),
(213, 6, 23, '2027-10-08', 11400.00, 161.50, 0.00, 11561.50, 11400.00, 'Pendiente'),
(214, 6, 24, '2027-11-08', 11400.00, 80.75, 0.00, 11480.75, 0.00, 'Pendiente'),
(239, 8, 1, '2025-12-08', 13200.00, 2244.00, 0.00, 15444.00, 303600.00, 'Pendiente'),
(240, 8, 2, '2026-01-08', 13200.00, 2150.50, 0.00, 15350.50, 290400.00, 'Pendiente'),
(241, 8, 3, '2026-02-08', 13200.00, 2057.00, 0.00, 15257.00, 277200.00, 'Pendiente'),
(242, 8, 4, '2026-03-08', 13200.00, 1963.50, 0.00, 15163.50, 264000.00, 'Pendiente'),
(243, 8, 5, '2026-04-08', 13200.00, 1870.00, 0.00, 15070.00, 250800.00, 'Pendiente'),
(244, 8, 6, '2026-05-08', 13200.00, 1776.50, 0.00, 14976.50, 237600.00, 'Pendiente'),
(245, 8, 7, '2026-06-08', 13200.00, 1683.00, 0.00, 14883.00, 224400.00, 'Pendiente'),
(246, 8, 8, '2026-07-08', 13200.00, 1589.50, 0.00, 14789.50, 211200.00, 'Pendiente'),
(247, 8, 9, '2026-08-08', 13200.00, 1496.00, 0.00, 14696.00, 198000.00, 'Pendiente'),
(248, 8, 10, '2026-09-08', 13200.00, 1402.50, 0.00, 14602.50, 184800.00, 'Pendiente'),
(249, 8, 11, '2026-10-08', 13200.00, 1309.00, 0.00, 14509.00, 171600.00, 'Pendiente'),
(250, 8, 12, '2026-11-08', 13200.00, 1215.50, 0.00, 14415.50, 158400.00, 'Pendiente'),
(251, 8, 13, '2026-12-08', 13200.00, 1122.00, 0.00, 14322.00, 145200.00, 'Pendiente'),
(252, 8, 14, '2027-01-08', 13200.00, 1028.50, 0.00, 14228.50, 132000.00, 'Pendiente'),
(253, 8, 15, '2027-02-08', 13200.00, 935.00, 0.00, 14135.00, 118800.00, 'Pendiente'),
(254, 8, 16, '2027-03-08', 13200.00, 841.50, 0.00, 14041.50, 105600.00, 'Pendiente'),
(255, 8, 17, '2027-04-08', 13200.00, 748.00, 0.00, 13948.00, 92400.00, 'Pendiente'),
(256, 8, 18, '2027-05-08', 13200.00, 654.50, 0.00, 13854.50, 79200.00, 'Pendiente'),
(257, 8, 19, '2027-06-08', 13200.00, 561.00, 0.00, 13761.00, 66000.00, 'Pendiente'),
(258, 8, 20, '2027-07-08', 13200.00, 467.50, 0.00, 13667.50, 52800.00, 'Pendiente'),
(259, 8, 21, '2027-08-08', 13200.00, 374.00, 0.00, 13574.00, 39600.00, 'Pendiente'),
(260, 8, 22, '2027-09-08', 13200.00, 280.50, 0.00, 13480.50, 26400.00, 'Pendiente'),
(261, 8, 23, '2027-10-08', 13200.00, 187.00, 0.00, 13387.00, 13200.00, 'Pendiente'),
(262, 8, 24, '2027-11-08', 13200.00, 93.50, 0.00, 13293.50, 0.00, 'Pendiente'),
(263, 9, 1, '2025-12-08', 13800.00, 2346.00, 0.00, 16146.00, 317400.00, 'Pendiente'),
(264, 9, 2, '2026-01-08', 13800.00, 2248.25, 0.00, 16048.25, 303600.00, 'Pendiente'),
(265, 9, 3, '2026-02-08', 13800.00, 2150.50, 0.00, 15950.50, 289800.00, 'Pendiente'),
(266, 9, 4, '2026-03-08', 13800.00, 2052.75, 0.00, 15852.75, 276000.00, 'Pendiente'),
(267, 9, 5, '2026-04-08', 13800.00, 1955.00, 0.00, 15755.00, 262200.00, 'Pendiente'),
(268, 9, 6, '2026-05-08', 13800.00, 1857.25, 0.00, 15657.25, 248400.00, 'Pendiente'),
(269, 9, 7, '2026-06-08', 13800.00, 1759.50, 0.00, 15559.50, 234600.00, 'Pendiente'),
(270, 9, 8, '2026-07-08', 13800.00, 1661.75, 0.00, 15461.75, 220800.00, 'Pendiente'),
(271, 9, 9, '2026-08-08', 13800.00, 1564.00, 0.00, 15364.00, 207000.00, 'Pendiente'),
(272, 9, 10, '2026-09-08', 13800.00, 1466.25, 0.00, 15266.25, 193200.00, 'Pendiente'),
(273, 9, 11, '2026-10-08', 13800.00, 1368.50, 0.00, 15168.50, 179400.00, 'Pendiente'),
(274, 9, 12, '2026-11-08', 13800.00, 1270.75, 0.00, 15070.75, 165600.00, 'Pendiente'),
(275, 9, 13, '2026-12-08', 13800.00, 1173.00, 0.00, 14973.00, 151800.00, 'Pendiente'),
(276, 9, 14, '2027-01-08', 13800.00, 1075.25, 0.00, 14875.25, 138000.00, 'Pendiente'),
(277, 9, 15, '2027-02-08', 13800.00, 977.50, 0.00, 14777.50, 124200.00, 'Pendiente'),
(278, 9, 16, '2027-03-08', 13800.00, 879.75, 0.00, 14679.75, 110400.00, 'Pendiente'),
(279, 9, 17, '2027-04-08', 13800.00, 782.00, 0.00, 14582.00, 96600.00, 'Pendiente'),
(280, 9, 18, '2027-05-08', 13800.00, 684.25, 0.00, 14484.25, 82800.00, 'Pendiente'),
(281, 9, 19, '2027-06-08', 13800.00, 586.50, 0.00, 14386.50, 69000.00, 'Pendiente'),
(282, 9, 20, '2027-07-08', 13800.00, 488.75, 0.00, 14288.75, 55200.00, 'Pendiente'),
(283, 9, 21, '2027-08-08', 13800.00, 391.00, 0.00, 14191.00, 41400.00, 'Pendiente'),
(284, 9, 22, '2027-09-08', 13800.00, 293.25, 0.00, 14093.25, 27600.00, 'Pendiente'),
(285, 9, 23, '2027-10-08', 13800.00, 195.50, 0.00, 13995.50, 13800.00, 'Pendiente'),
(286, 9, 24, '2027-11-08', 13800.00, 97.75, 0.00, 13897.75, 0.00, 'Pendiente'),
(287, 10, 1, '2025-09-24', 14400.00, 2448.00, 1500.00, 16848.00, 331200.00, 'Vencida'),
(288, 10, 2, '2025-09-24', 14400.00, 2346.00, 1500.00, 16746.00, 316800.00, 'Vencida'),
(289, 10, 3, '2026-02-08', 14400.00, 2244.00, 0.00, 16644.00, 302400.00, 'Pendiente'),
(290, 10, 4, '2026-03-08', 14400.00, 2142.00, 0.00, 16542.00, 288000.00, 'Pendiente'),
(291, 10, 5, '2026-04-08', 14400.00, 2040.00, 0.00, 16440.00, 273600.00, 'Pendiente'),
(292, 10, 6, '2026-05-08', 14400.00, 1938.00, 0.00, 16338.00, 259200.00, 'Pendiente'),
(293, 10, 7, '2026-06-08', 14400.00, 1836.00, 0.00, 16236.00, 244800.00, 'Pendiente'),
(294, 10, 8, '2026-07-08', 14400.00, 1734.00, 0.00, 16134.00, 230400.00, 'Pendiente'),
(295, 10, 9, '2026-08-08', 14400.00, 1632.00, 0.00, 16032.00, 216000.00, 'Pendiente'),
(296, 10, 10, '2026-09-08', 14400.00, 1530.00, 0.00, 15930.00, 201600.00, 'Pendiente'),
(297, 10, 11, '2026-10-08', 14400.00, 1428.00, 0.00, 15828.00, 187200.00, 'Pendiente'),
(298, 10, 12, '2026-11-08', 14400.00, 1326.00, 0.00, 15726.00, 172800.00, 'Pendiente'),
(299, 10, 13, '2026-12-08', 14400.00, 1224.00, 0.00, 15624.00, 158400.00, 'Pendiente'),
(300, 10, 14, '2027-01-08', 14400.00, 1122.00, 0.00, 15522.00, 144000.00, 'Pendiente'),
(301, 10, 15, '2027-02-08', 14400.00, 1020.00, 0.00, 15420.00, 129600.00, 'Pendiente'),
(302, 10, 16, '2027-03-08', 14400.00, 918.00, 0.00, 15318.00, 115200.00, 'Pendiente'),
(303, 10, 17, '2027-04-08', 14400.00, 816.00, 0.00, 15216.00, 100800.00, 'Pendiente'),
(304, 10, 18, '2027-05-08', 14400.00, 714.00, 0.00, 15114.00, 86400.00, 'Pendiente'),
(305, 10, 19, '2027-06-08', 14400.00, 612.00, 0.00, 15012.00, 72000.00, 'Pendiente'),
(306, 10, 20, '2027-07-08', 14400.00, 510.00, 0.00, 14910.00, 57600.00, 'Pendiente'),
(307, 10, 21, '2027-08-08', 14400.00, 408.00, 0.00, 14808.00, 43200.00, 'Pendiente'),
(308, 10, 22, '2027-09-08', 14400.00, 306.00, 0.00, 14706.00, 28800.00, 'Pendiente'),
(309, 10, 23, '2027-10-08', 14400.00, 204.00, 0.00, 14604.00, 14400.00, 'Pendiente'),
(310, 10, 24, '2027-11-08', 14400.00, 102.00, 0.00, 14502.00, 0.00, 'Pendiente'),
(353, 13, 1, '2025-12-08', 16200.00, 2754.00, 0.00, 18954.00, 372600.00, 'Pendiente'),
(354, 13, 2, '2026-01-08', 16200.00, 2639.25, 0.00, 18839.25, 356400.00, 'Pendiente'),
(355, 13, 3, '2026-02-08', 16200.00, 2524.50, 0.00, 18724.50, 340200.00, 'Pendiente'),
(356, 13, 4, '2026-03-08', 16200.00, 2409.75, 0.00, 18609.75, 324000.00, 'Pendiente'),
(357, 13, 5, '2026-04-08', 16200.00, 2295.00, 0.00, 18495.00, 307800.00, 'Pendiente'),
(358, 13, 6, '2026-05-08', 16200.00, 2180.25, 0.00, 18380.25, 291600.00, 'Pendiente'),
(359, 13, 7, '2026-06-08', 16200.00, 2065.50, 0.00, 18265.50, 275400.00, 'Pendiente'),
(360, 13, 8, '2026-07-08', 16200.00, 1950.75, 0.00, 18150.75, 259200.00, 'Pendiente'),
(361, 13, 9, '2026-08-08', 16200.00, 1836.00, 0.00, 18036.00, 243000.00, 'Pendiente'),
(362, 13, 10, '2026-09-08', 16200.00, 1721.25, 0.00, 17921.25, 226800.00, 'Pendiente'),
(363, 13, 11, '2026-10-08', 16200.00, 1606.50, 0.00, 17806.50, 210600.00, 'Pendiente'),
(364, 13, 12, '2026-11-08', 16200.00, 1491.75, 0.00, 17691.75, 194400.00, 'Pendiente'),
(365, 13, 13, '2026-12-08', 16200.00, 1377.00, 0.00, 17577.00, 178200.00, 'Pendiente'),
(366, 13, 14, '2027-01-08', 16200.00, 1262.25, 0.00, 17462.25, 162000.00, 'Pendiente'),
(367, 13, 15, '2027-02-08', 16200.00, 1147.50, 0.00, 17347.50, 145800.00, 'Pendiente'),
(368, 13, 16, '2027-03-08', 16200.00, 1032.75, 0.00, 17232.75, 129600.00, 'Pendiente'),
(369, 13, 17, '2027-04-08', 16200.00, 918.00, 0.00, 17118.00, 113400.00, 'Pendiente'),
(370, 13, 18, '2027-05-08', 16200.00, 803.25, 0.00, 17003.25, 97200.00, 'Pendiente'),
(371, 13, 19, '2027-06-08', 16200.00, 688.50, 0.00, 16888.50, 81000.00, 'Pendiente'),
(372, 13, 20, '2027-07-08', 16200.00, 573.75, 0.00, 16773.75, 64800.00, 'Pendiente'),
(373, 13, 21, '2027-08-08', 16200.00, 459.00, 0.00, 16659.00, 48600.00, 'Pendiente'),
(374, 13, 22, '2027-09-08', 16200.00, 344.25, 0.00, 16544.25, 32400.00, 'Pendiente'),
(375, 13, 23, '2027-10-08', 16200.00, 229.50, 0.00, 16429.50, 16200.00, 'Pendiente'),
(376, 13, 24, '2027-11-08', 16200.00, 114.75, 0.00, 16314.75, 0.00, 'Pendiente'),
(377, 14, 1, '2025-12-08', 16800.00, 2856.00, 0.00, 19656.00, 386400.00, 'Pendiente'),
(378, 14, 2, '2026-01-08', 16800.00, 2737.00, 0.00, 19537.00, 369600.00, 'Pendiente'),
(379, 14, 3, '2026-02-08', 16800.00, 2618.00, 0.00, 19418.00, 352800.00, 'Pendiente'),
(380, 14, 4, '2026-03-08', 16800.00, 2499.00, 0.00, 19299.00, 336000.00, 'Pendiente'),
(381, 14, 5, '2026-04-08', 16800.00, 2380.00, 0.00, 19180.00, 319200.00, 'Pendiente'),
(382, 14, 6, '2026-05-08', 16800.00, 2261.00, 0.00, 19061.00, 302400.00, 'Pendiente'),
(383, 14, 7, '2026-06-08', 16800.00, 2142.00, 0.00, 18942.00, 285600.00, 'Pendiente'),
(384, 14, 8, '2026-07-08', 16800.00, 2023.00, 0.00, 18823.00, 268800.00, 'Pendiente'),
(385, 14, 9, '2026-08-08', 16800.00, 1904.00, 0.00, 18704.00, 252000.00, 'Pendiente'),
(386, 14, 10, '2026-09-08', 16800.00, 1785.00, 0.00, 18585.00, 235200.00, 'Pendiente'),
(387, 14, 11, '2026-10-08', 16800.00, 1666.00, 0.00, 18466.00, 218400.00, 'Pendiente'),
(388, 14, 12, '2026-11-08', 16800.00, 1547.00, 0.00, 18347.00, 201600.00, 'Pendiente'),
(389, 14, 13, '2026-12-08', 16800.00, 1428.00, 0.00, 18228.00, 184800.00, 'Pendiente'),
(390, 14, 14, '2027-01-08', 16800.00, 1309.00, 0.00, 18109.00, 168000.00, 'Pendiente'),
(391, 14, 15, '2027-02-08', 16800.00, 1190.00, 0.00, 17990.00, 151200.00, 'Pendiente'),
(392, 14, 16, '2027-03-08', 16800.00, 1071.00, 0.00, 17871.00, 134400.00, 'Pendiente'),
(393, 14, 17, '2027-04-08', 16800.00, 952.00, 0.00, 17752.00, 117600.00, 'Pendiente'),
(394, 14, 18, '2027-05-08', 16800.00, 833.00, 0.00, 17633.00, 100800.00, 'Pendiente'),
(395, 14, 19, '2027-06-08', 16800.00, 714.00, 0.00, 17514.00, 84000.00, 'Pendiente'),
(396, 14, 20, '2027-07-08', 16800.00, 595.00, 0.00, 17395.00, 67200.00, 'Pendiente'),
(397, 14, 21, '2027-08-08', 16800.00, 476.00, 0.00, 17276.00, 50400.00, 'Pendiente'),
(398, 14, 22, '2027-09-08', 16800.00, 357.00, 0.00, 17157.00, 33600.00, 'Pendiente'),
(399, 14, 23, '2027-10-08', 16800.00, 238.00, 0.00, 17038.00, 16800.00, 'Pendiente'),
(400, 14, 24, '2027-11-08', 16800.00, 119.00, 0.00, 16919.00, 0.00, 'Pendiente'),
(401, 15, 1, '2025-09-24', 17400.00, 2958.00, 1500.00, 20358.00, 400200.00, 'Vencida'),
(402, 15, 2, '2025-09-24', 17400.00, 2834.75, 1500.00, 20234.75, 382800.00, 'Vencida'),
(403, 15, 3, '2026-02-08', 17400.00, 2711.50, 0.00, 20111.50, 365400.00, 'Pendiente'),
(404, 15, 4, '2026-03-08', 17400.00, 2588.25, 0.00, 19988.25, 348000.00, 'Pendiente'),
(405, 15, 5, '2026-04-08', 17400.00, 2465.00, 0.00, 19865.00, 330600.00, 'Pendiente'),
(406, 15, 6, '2026-05-08', 17400.00, 2341.75, 0.00, 19741.75, 313200.00, 'Pendiente'),
(407, 15, 7, '2026-06-08', 17400.00, 2218.50, 0.00, 19618.50, 295800.00, 'Pendiente'),
(408, 15, 8, '2026-07-08', 17400.00, 2095.25, 0.00, 19495.25, 278400.00, 'Pendiente'),
(409, 15, 9, '2026-08-08', 17400.00, 1972.00, 0.00, 19372.00, 261000.00, 'Pendiente'),
(410, 15, 10, '2026-09-08', 17400.00, 1848.75, 0.00, 19248.75, 243600.00, 'Pendiente'),
(411, 15, 11, '2026-10-08', 17400.00, 1725.50, 0.00, 19125.50, 226200.00, 'Pendiente'),
(412, 15, 12, '2026-11-08', 17400.00, 1602.25, 0.00, 19002.25, 208800.00, 'Pendiente'),
(413, 15, 13, '2026-12-08', 17400.00, 1479.00, 0.00, 18879.00, 191400.00, 'Pendiente'),
(414, 15, 14, '2027-01-08', 17400.00, 1355.75, 0.00, 18755.75, 174000.00, 'Pendiente'),
(415, 15, 15, '2027-02-08', 17400.00, 1232.50, 0.00, 18632.50, 156600.00, 'Pendiente'),
(416, 15, 16, '2027-03-08', 17400.00, 1109.25, 0.00, 18509.25, 139200.00, 'Pendiente'),
(417, 15, 17, '2027-04-08', 17400.00, 986.00, 0.00, 18386.00, 121800.00, 'Pendiente'),
(418, 15, 18, '2027-05-08', 17400.00, 862.75, 0.00, 18262.75, 104400.00, 'Pendiente'),
(419, 15, 19, '2027-06-08', 17400.00, 739.50, 0.00, 18139.50, 87000.00, 'Pendiente'),
(420, 15, 20, '2027-07-08', 17400.00, 616.25, 0.00, 18016.25, 69600.00, 'Pendiente'),
(421, 15, 21, '2027-08-08', 17400.00, 493.00, 0.00, 17893.00, 52200.00, 'Pendiente'),
(422, 15, 22, '2027-09-08', 17400.00, 369.75, 0.00, 17769.75, 34800.00, 'Pendiente'),
(423, 15, 23, '2027-10-08', 17400.00, 246.50, 0.00, 17646.50, 17400.00, 'Pendiente'),
(424, 15, 24, '2027-11-08', 17400.00, 123.25, 0.00, 17523.25, 0.00, 'Pendiente'),
(449, 17, 1, '2025-12-08', 19200.00, 3264.00, 0.00, 22464.00, 441600.00, 'Pendiente'),
(450, 17, 2, '2026-01-08', 19200.00, 3128.00, 0.00, 22328.00, 422400.00, 'Pendiente'),
(451, 17, 3, '2026-02-08', 19200.00, 2992.00, 0.00, 22192.00, 403200.00, 'Pendiente'),
(452, 17, 4, '2026-03-08', 19200.00, 2856.00, 0.00, 22056.00, 384000.00, 'Pendiente'),
(453, 17, 5, '2026-04-08', 19200.00, 2720.00, 0.00, 21920.00, 364800.00, 'Pendiente'),
(454, 17, 6, '2026-05-08', 19200.00, 2584.00, 0.00, 21784.00, 345600.00, 'Pendiente'),
(455, 17, 7, '2026-06-08', 19200.00, 2448.00, 0.00, 21648.00, 326400.00, 'Pendiente'),
(456, 17, 8, '2026-07-08', 19200.00, 2312.00, 0.00, 21512.00, 307200.00, 'Pendiente'),
(457, 17, 9, '2026-08-08', 19200.00, 2176.00, 0.00, 21376.00, 288000.00, 'Pendiente'),
(458, 17, 10, '2026-09-08', 19200.00, 2040.00, 0.00, 21240.00, 268800.00, 'Pendiente'),
(459, 17, 11, '2026-10-08', 19200.00, 1904.00, 0.00, 21104.00, 249600.00, 'Pendiente'),
(460, 17, 12, '2026-11-08', 19200.00, 1768.00, 0.00, 20968.00, 230400.00, 'Pendiente'),
(461, 17, 13, '2026-12-08', 19200.00, 1632.00, 0.00, 20832.00, 211200.00, 'Pendiente'),
(462, 17, 14, '2027-01-08', 19200.00, 1496.00, 0.00, 20696.00, 192000.00, 'Pendiente'),
(463, 17, 15, '2027-02-08', 19200.00, 1360.00, 0.00, 20560.00, 172800.00, 'Pendiente'),
(464, 17, 16, '2027-03-08', 19200.00, 1224.00, 0.00, 20424.00, 153600.00, 'Pendiente'),
(465, 17, 17, '2027-04-08', 19200.00, 1088.00, 0.00, 20288.00, 134400.00, 'Pendiente'),
(466, 17, 18, '2027-05-08', 19200.00, 952.00, 0.00, 20152.00, 115200.00, 'Pendiente'),
(467, 17, 19, '2027-06-08', 19200.00, 816.00, 0.00, 20016.00, 96000.00, 'Pendiente'),
(468, 17, 20, '2027-07-08', 19200.00, 680.00, 0.00, 19880.00, 76800.00, 'Pendiente'),
(469, 17, 21, '2027-08-08', 19200.00, 544.00, 0.00, 19744.00, 57600.00, 'Pendiente'),
(470, 17, 22, '2027-09-08', 19200.00, 408.00, 0.00, 19608.00, 38400.00, 'Pendiente'),
(471, 17, 23, '2027-10-08', 19200.00, 272.00, 0.00, 19472.00, 19200.00, 'Pendiente'),
(472, 17, 24, '2027-11-08', 19200.00, 136.00, 0.00, 19336.00, 0.00, 'Pendiente'),
(473, 18, 1, '2025-12-08', 19800.00, 3366.00, 0.00, 23166.00, 455400.00, 'Pendiente'),
(474, 18, 2, '2026-01-08', 19800.00, 3225.75, 0.00, 23025.75, 435600.00, 'Pendiente'),
(475, 18, 3, '2026-02-08', 19800.00, 3085.50, 0.00, 22885.50, 415800.00, 'Pendiente'),
(476, 18, 4, '2026-03-08', 19800.00, 2945.25, 0.00, 22745.25, 396000.00, 'Pendiente'),
(477, 18, 5, '2026-04-08', 19800.00, 2805.00, 0.00, 22605.00, 376200.00, 'Pendiente'),
(478, 18, 6, '2026-05-08', 19800.00, 2664.75, 0.00, 22464.75, 356400.00, 'Pendiente'),
(479, 18, 7, '2026-06-08', 19800.00, 2524.50, 0.00, 22324.50, 336600.00, 'Pendiente'),
(480, 18, 8, '2026-07-08', 19800.00, 2384.25, 0.00, 22184.25, 316800.00, 'Pendiente'),
(481, 18, 9, '2026-08-08', 19800.00, 2244.00, 0.00, 22044.00, 297000.00, 'Pendiente'),
(482, 18, 10, '2026-09-08', 19800.00, 2103.75, 0.00, 21903.75, 277200.00, 'Pendiente'),
(483, 18, 11, '2026-10-08', 19800.00, 1963.50, 0.00, 21763.50, 257400.00, 'Pendiente'),
(484, 18, 12, '2026-11-08', 19800.00, 1823.25, 0.00, 21623.25, 237600.00, 'Pendiente'),
(485, 18, 13, '2026-12-08', 19800.00, 1683.00, 0.00, 21483.00, 217800.00, 'Pendiente'),
(486, 18, 14, '2027-01-08', 19800.00, 1542.75, 0.00, 21342.75, 198000.00, 'Pendiente'),
(487, 18, 15, '2027-02-08', 19800.00, 1402.50, 0.00, 21202.50, 178200.00, 'Pendiente'),
(488, 18, 16, '2027-03-08', 19800.00, 1262.25, 0.00, 21062.25, 158400.00, 'Pendiente'),
(489, 18, 17, '2027-04-08', 19800.00, 1122.00, 0.00, 20922.00, 138600.00, 'Pendiente'),
(490, 18, 18, '2027-05-08', 19800.00, 981.75, 0.00, 20781.75, 118800.00, 'Pendiente'),
(491, 18, 19, '2027-06-08', 19800.00, 841.50, 0.00, 20641.50, 99000.00, 'Pendiente'),
(492, 18, 20, '2027-07-08', 19800.00, 701.25, 0.00, 20501.25, 79200.00, 'Pendiente'),
(493, 18, 21, '2027-08-08', 19800.00, 561.00, 0.00, 20361.00, 59400.00, 'Pendiente'),
(494, 18, 22, '2027-09-08', 19800.00, 420.75, 0.00, 20220.75, 39600.00, 'Pendiente'),
(495, 18, 23, '2027-10-08', 19800.00, 280.50, 0.00, 20080.50, 19800.00, 'Pendiente'),
(496, 18, 24, '2027-11-08', 19800.00, 140.25, 0.00, 19940.25, 0.00, 'Pendiente'),
(497, 19, 1, '2025-09-24', 20400.00, 3468.00, 1500.00, 23868.00, 469200.00, 'Vencida'),
(498, 19, 2, '2025-09-24', 20400.00, 3323.50, 1500.00, 23723.50, 448800.00, 'Vencida'),
(499, 19, 3, '2026-02-08', 20400.00, 3179.00, 0.00, 23579.00, 428400.00, 'Pendiente'),
(500, 19, 4, '2026-03-08', 20400.00, 3034.50, 0.00, 23434.50, 408000.00, 'Pendiente'),
(501, 19, 5, '2026-04-08', 20400.00, 2890.00, 0.00, 23290.00, 387600.00, 'Pendiente'),
(502, 19, 6, '2026-05-08', 20400.00, 2745.50, 0.00, 23145.50, 367200.00, 'Pendiente'),
(503, 19, 7, '2026-06-08', 20400.00, 2601.00, 0.00, 23001.00, 346800.00, 'Pendiente'),
(504, 19, 8, '2026-07-08', 20400.00, 2456.50, 0.00, 22856.50, 326400.00, 'Pendiente'),
(505, 19, 9, '2026-08-08', 20400.00, 2312.00, 0.00, 22712.00, 306000.00, 'Pendiente'),
(506, 19, 10, '2026-09-08', 20400.00, 2167.50, 0.00, 22567.50, 285600.00, 'Pendiente'),
(507, 19, 11, '2026-10-08', 20400.00, 2023.00, 0.00, 22423.00, 265200.00, 'Pendiente'),
(508, 19, 12, '2026-11-08', 20400.00, 1878.50, 0.00, 22278.50, 244800.00, 'Pendiente'),
(509, 19, 13, '2026-12-08', 20400.00, 1734.00, 0.00, 22134.00, 224400.00, 'Pendiente'),
(510, 19, 14, '2027-01-08', 20400.00, 1589.50, 0.00, 21989.50, 204000.00, 'Pendiente'),
(511, 19, 15, '2027-02-08', 20400.00, 1445.00, 0.00, 21845.00, 183600.00, 'Pendiente'),
(512, 19, 16, '2027-03-08', 20400.00, 1300.50, 0.00, 21700.50, 163200.00, 'Pendiente'),
(513, 19, 17, '2027-04-08', 20400.00, 1156.00, 0.00, 21556.00, 142800.00, 'Pendiente'),
(514, 19, 18, '2027-05-08', 20400.00, 1011.50, 0.00, 21411.50, 122400.00, 'Pendiente'),
(515, 19, 19, '2027-06-08', 20400.00, 867.00, 0.00, 21267.00, 102000.00, 'Pendiente'),
(516, 19, 20, '2027-07-08', 20400.00, 722.50, 0.00, 21122.50, 81600.00, 'Pendiente'),
(517, 19, 21, '2027-08-08', 20400.00, 578.00, 0.00, 20978.00, 61200.00, 'Pendiente'),
(518, 19, 22, '2027-09-08', 20400.00, 433.50, 0.00, 20833.50, 40800.00, 'Pendiente'),
(519, 19, 23, '2027-10-08', 20400.00, 289.00, 0.00, 20689.00, 20400.00, 'Pendiente'),
(520, 19, 24, '2027-11-08', 20400.00, 144.50, 0.00, 20544.50, 0.00, 'Pendiente');

--
-- Disparadores `cronograma_cuota`
--
DROP TRIGGER IF EXISTS `actualizar_estado_prestamo`;
DELIMITER $$
CREATE TRIGGER `actualizar_estado_prestamo` AFTER UPDATE ON `cronograma_cuota` FOR EACH ROW BEGIN 
    IF NEW.estado_cuota = 'Pagada' THEN
        IF NOT EXISTS (
            SELECT 1 FROM cronograma_cuota
            WHERE id_prestamo = NEW.id_prestamo AND estado_cuota != 'Pagada'
        ) THEN
            UPDATE prestamo
            SET id_estado_prestamo = (
                SELECT id_estado_prestamo FROM cat_estado_prestamo WHERE estado = 'Cerrado' LIMIT 1
            )
            WHERE id_prestamo = NEW.id_prestamo;
        END IF;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_auditoria_cuota`;
DELIMITER $$
CREATE TRIGGER `tr_auditoria_cuota` AFTER UPDATE ON `cronograma_cuota` FOR EACH ROW BEGIN 
    IF NEW.estado_cuota != OLD.estado_cuota THEN
        INSERT INTO auditoria_cambios(
            tabla_afectada, id_registro, tipo_cambio, fecha_cambio, realizado_por, valores_anteriores, valores_nuevos
        )
        VALUES (
            'Cronograma_Cuota',
            NEW.id_cronograma_cuota,
            'Cambio_estado',
            NOW(),
            NULL,
            JSON_OBJECT(
                'estado_cuota', OLD.estado_cuota
            ),
            JSON_OBJECT(
                'estado_cuota', NEW.estado_cuota
            )
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `datos_persona`
--

DROP TABLE IF EXISTS `datos_persona`;
CREATE TABLE IF NOT EXISTS `datos_persona` (
  `id_datos_persona` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `genero` int DEFAULT NULL,
  `estado_cliente` enum('Activo','Inactivo') DEFAULT 'Activo',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_datos_persona`),
  KEY `idx_datos_persona_genero` (`genero`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `datos_persona`
--

INSERT INTO `datos_persona` (`id_datos_persona`, `nombre`, `apellido`, `fecha_nacimiento`, `genero`, `estado_cliente`, `creado_en`, `actualizado_en`) VALUES
(16, 'Carlos', 'Pérez', '1990-03-12', 1, 'Activo', '2025-11-08 04:13:55', '2025-11-08 04:13:55'),
(17, 'María', 'Gómez', '1985-07-22', 2, 'Activo', '2025-11-08 04:13:55', '2025-11-08 04:13:55'),
(18, 'José', 'Ramírez', '1993-11-05', 1, 'Activo', '2025-11-08 04:13:55', '2025-11-08 04:13:55'),
(19, 'Lucía', 'Torres', '1998-01-30', 2, 'Activo', '2025-11-08 04:13:55', '2025-11-08 04:13:55'),
(20, 'Seed001', 'Semilla', '2004-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(23, 'Seed004', 'Pilot', '2001-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(24, 'Seed005', 'Prueba', '2000-11-08', 3, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(26, 'Seed007', 'Demo', '1998-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(34, 'Seed015', 'Prueba', '1990-11-08', 1, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(35, 'Seed016', 'Semilla', '1989-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(38, 'Seed019', 'Pilot', '1986-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(41, 'Seed022', 'Demo', '1983-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(44, 'Seed025', 'Prueba', '2005-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(50, 'Seed031', 'Semilla', '1999-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(53, 'Seed034', 'Pilot', '1996-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(54, 'Seed035', 'Prueba', '1995-11-08', 3, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(56, 'Seed037', 'Demo', '1993-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(64, 'Seed045', 'Prueba', '1985-11-08', 1, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(65, 'Seed046', 'Semilla', '1984-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(68, 'Seed049', 'Pilot', '1981-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(71, 'Seed052', 'Demo', '2003-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47'),
(74, 'Seed055', 'Prueba', '2000-11-08', 2, 'Activo', '2025-11-08 04:29:47', '2025-11-08 04:29:47');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `deducciones_empleado`
--

DROP TABLE IF EXISTS `deducciones_empleado`;
CREATE TABLE IF NOT EXISTS `deducciones_empleado` (
  `id_nomina_empleado` int NOT NULL,
  `id_tipo_deduccion` int NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id_nomina_empleado`,`id_tipo_deduccion`),
  KEY `fk_tipodeduccion_deducciones` (`id_tipo_deduccion`),
  KEY `idx_deducciones_nomina` (`id_nomina_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `desembolso`
--

DROP TABLE IF EXISTS `desembolso`;
CREATE TABLE IF NOT EXISTS `desembolso` (
  `id_desembolso` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `monto_desembolsado` decimal(14,2) NOT NULL,
  `fecha_desembolso` date NOT NULL,
  `metodo_entrega` int DEFAULT NULL,
  PRIMARY KEY (`id_desembolso`),
  KEY `fk_prestamo_desembolso` (`id_prestamo`),
  KEY `fk_metodo_entrega_desembolso` (`metodo_entrega`)
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_evaluacion`
--

DROP TABLE IF EXISTS `detalle_evaluacion`;
CREATE TABLE IF NOT EXISTS `detalle_evaluacion` (
  `id_detalle_evaluacion` int NOT NULL AUTO_INCREMENT,
  `id_evaluacion_prestamo` int DEFAULT NULL,
  `fecha` date NOT NULL,
  `observacion` text NOT NULL,
  `evaluado_por` int DEFAULT NULL,
  PRIMARY KEY (`id_detalle_evaluacion`),
  KEY `fk_usuario_evaluacion` (`evaluado_por`),
  KEY `idx_detalle_evaluacion` (`id_evaluacion_prestamo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_garantia`
--

DROP TABLE IF EXISTS `detalle_garantia`;
CREATE TABLE IF NOT EXISTS `detalle_garantia` (
  `id_detalle_garantia` int NOT NULL AUTO_INCREMENT,
  `id_garantia` int DEFAULT NULL,
  `id_tipo_garantia` int DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `valor_estimado` decimal(14,2) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `estado_garantia` enum('Activa','Liberada','En uso','En verificacion') DEFAULT 'Activa',
  PRIMARY KEY (`id_detalle_garantia`),
  KEY `fk_garantia_detalle_garantia` (`id_garantia`),
  KEY `fk_detalle_garantia_tipo` (`id_tipo_garantia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `direccion`
--

DROP TABLE IF EXISTS `direccion`;
CREATE TABLE IF NOT EXISTS `direccion` (
  `id_direccion` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  `ciudad` varchar(100) NOT NULL,
  `sector` varchar(100) NOT NULL,
  `calle` varchar(100) NOT NULL,
  `numero_casa` int NOT NULL,
  PRIMARY KEY (`id_direccion`),
  KEY `fk_datospersona_direccion` (`id_datos_persona`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `direccion`
--

INSERT INTO `direccion` (`id_direccion`, `id_datos_persona`, `ciudad`, `sector`, `calle`, `numero_casa`) VALUES
(11, 16, 'Santo Domingo', 'Naco', 'C/ Primera', 12),
(12, 17, 'Santiago', 'Los Jardines', 'Av. Libertad', 45),
(13, 18, 'La Vega', 'Centro', 'C/ Duarte', 28),
(14, 19, 'San Cristóbal', 'Madelaine', 'C/ Central', 7),
(15, 20, 'Santiago', 'Naco', 'C/ Central', 2),
(18, 23, 'Baní', 'Gascue', 'C/ Primera', 5),
(19, 24, 'Santo Domingo', 'Centro', 'C/ Central', 6),
(21, 26, 'La Vega', 'Piantini', 'C/ Duarte', 8),
(29, 34, 'Santo Domingo', 'Centro', 'C/ Duarte', 16),
(30, 35, 'Santiago', 'Naco', 'C/ Primera', 17),
(33, 38, 'Baní', 'Gascue', 'C/ Duarte', 20),
(36, 41, 'La Vega', 'Piantini', 'C/ Libertad', 23),
(39, 44, 'Santo Domingo', 'Centro', 'C/ Central', 26),
(45, 50, 'Santiago', 'Naco', 'C/ Duarte', 32),
(48, 53, 'Baní', 'Gascue', 'C/ Libertad', 35),
(49, 54, 'Santo Domingo', 'Centro', 'C/ Duarte', 36),
(51, 56, 'La Vega', 'Piantini', 'C/ Central', 38),
(59, 64, 'Santo Domingo', 'Centro', 'C/ Central', 46),
(60, 65, 'Santiago', 'Naco', 'C/ Libertad', 47),
(63, 68, 'Baní', 'Gascue', 'C/ Central', 50),
(66, 71, 'La Vega', 'Piantini', 'C/ Primera', 53),
(69, 74, 'Santo Domingo', 'Centro', 'C/ Duarte', 56);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `direccion_entidad`
--

DROP TABLE IF EXISTS `direccion_entidad`;
CREATE TABLE IF NOT EXISTS `direccion_entidad` (
  `id_direccion` int NOT NULL,
  `id_tipo_entidad` int NOT NULL,
  `tipo_direccion` varchar(50) NOT NULL,
  PRIMARY KEY (`id_direccion`,`id_tipo_entidad`),
  KEY `fk_tipo_entidad` (`id_tipo_entidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentacion_cliente`
--

DROP TABLE IF EXISTS `documentacion_cliente`;
CREATE TABLE IF NOT EXISTS `documentacion_cliente` (
  `id_cliente` int NOT NULL,
  `id_documentacion_cliente` int NOT NULL,
  `ruta_documento` varchar(255) NOT NULL,
  PRIMARY KEY (`id_cliente`,`id_documentacion_cliente`),
  KEY `fk_tipo_documentoC` (`id_documentacion_cliente`),
  KEY `idx_doc_cliente` (`id_cliente`,`id_documentacion_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `documentacion_cliente`
--

INSERT INTO `documentacion_cliente` (`id_cliente`, `id_documentacion_cliente`, `ruta_documento`) VALUES
(10, 1, '/docs/carlos/cedula.pdf'),
(10, 2, '/docs/carlos/ingresos.pdf'),
(10, 3, '/docs/carlos/referencias.pdf'),
(11, 1, '/docs/maria/cedula.pdf'),
(11, 2, '/docs/maria/ingresos.pdf'),
(11, 3, '/docs/maria/referencias.pdf'),
(12, 1, '/docs/jose/cedula.pdf'),
(12, 2, '/docs/jose/ingresos.pdf'),
(12, 3, '/docs/jose/referencias.pdf'),
(13, 1, '/docs/lucia/cedula.pdf'),
(13, 3, '/docs/lucia/referencias.pdf');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_garante`
--

DROP TABLE IF EXISTS `documento_garante`;
CREATE TABLE IF NOT EXISTS `documento_garante` (
  `id_documento_garante` int NOT NULL AUTO_INCREMENT,
  `id_garante` int DEFAULT NULL,
  `ruta_documento` varchar(255) NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_documento_garante`),
  KEY `fk_documento_garante` (`id_garante`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_garantia`
--

DROP TABLE IF EXISTS `documento_garantia`;
CREATE TABLE IF NOT EXISTS `documento_garantia` (
  `id_documento_garantia` int NOT NULL AUTO_INCREMENT,
  `id_detalle_garantia` int DEFAULT NULL,
  `ruta_documento` varchar(255) NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_documento_garantia`),
  KEY `fk_documento_garantia_detalle` (`id_detalle_garantia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_identidad`
--

DROP TABLE IF EXISTS `documento_identidad`;
CREATE TABLE IF NOT EXISTS `documento_identidad` (
  `id_documento_identidad` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  `id_tipo_documento` int DEFAULT NULL,
  `numero_documento` varchar(50) NOT NULL,
  `fecha_emision` date NOT NULL,
  PRIMARY KEY (`id_documento_identidad`),
  UNIQUE KEY `numero_documento` (`numero_documento`),
  KEY `fk_datospersona_documentoI` (`id_datos_persona`),
  KEY `fk_tipodocumento_documentoI` (`id_tipo_documento`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `documento_identidad`
--

INSERT INTO `documento_identidad` (`id_documento_identidad`, `id_datos_persona`, `id_tipo_documento`, `numero_documento`, `fecha_emision`) VALUES
(8, 16, 1, '001-1234567-1', '2015-01-10'),
(9, 17, 1, '002-7654321-2', '2014-06-05'),
(10, 18, 1, '003-1112223-4', '2016-02-15'),
(11, 19, 1, '004-9998887-6', '2019-09-09'),
(12, 20, 1, '001-0000013-2', '2019-11-08'),
(15, 23, 1, '004-0000052-5', '2016-11-08'),
(16, 24, 1, '005-0000065-6', '2015-11-08'),
(18, 26, 1, '007-0000091-8', '2020-11-08'),
(26, 34, 1, '015-0000195-7', '2019-11-08'),
(27, 35, 1, '016-0000208-8', '2018-11-08'),
(30, 38, 1, '019-0000247-2', '2015-11-08'),
(33, 41, 1, '022-0000286-5', '2019-11-08'),
(36, 44, 1, '025-0000325-8', '2016-11-08'),
(42, 50, 1, '031-0000403-5', '2017-11-08'),
(45, 53, 1, '034-0000442-8', '2014-11-08'),
(46, 54, 1, '035-0000455-9', '2020-11-08'),
(48, 56, 1, '037-0000481-2', '2018-11-08'),
(56, 64, 1, '045-0000585-1', '2017-11-08'),
(57, 65, 1, '046-0000598-2', '2016-11-08'),
(60, 68, 1, '049-0000637-5', '2020-11-08'),
(63, 71, 1, '052-0000676-8', '2017-11-08'),
(66, 74, 1, '055-0000715-2', '2014-11-08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `email`
--

DROP TABLE IF EXISTS `email`;
CREATE TABLE IF NOT EXISTS `email` (
  `id_datos_persona` int NOT NULL,
  `email` varchar(100) NOT NULL,
  `es_principal` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_datos_persona`,`email`),
  UNIQUE KEY `uq_email` (`id_datos_persona`,`es_principal`),
  KEY `idx_email_persona` (`id_datos_persona`,`es_principal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `email`
--

INSERT INTO `email` (`id_datos_persona`, `email`, `es_principal`) VALUES
(16, 'carlos.perez@example.com', 1),
(17, 'maria.gomez@example.com', 1),
(18, 'jose.ramirez@example.com', 1),
(19, 'lucia.torres@example.com', 1),
(20, 'seed001@example.com', 1),
(23, 'seed004@example.com', 1),
(24, 'seed005@example.com', 1),
(26, 'seed007@example.com', 1),
(34, 'seed015@example.com', 1),
(35, 'seed016@example.com', 1),
(38, 'seed019@example.com', 1),
(41, 'seed022@example.com', 1),
(44, 'seed025@example.com', 1),
(50, 'seed031@example.com', 1),
(53, 'seed034@example.com', 1),
(54, 'seed035@example.com', 1),
(56, 'seed037@example.com', 1),
(64, 'seed045@example.com', 1),
(65, 'seed046@example.com', 1),
(68, 'seed049@example.com', 1),
(71, 'seed052@example.com', 1),
(74, 'seed055@example.com', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleado`
--

DROP TABLE IF EXISTS `empleado`;
CREATE TABLE IF NOT EXISTS `empleado` (
  `id_empleado` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  `id_tipo_contrato` int DEFAULT NULL,
  `rol` varchar(100) NOT NULL,
  `salario` decimal(14,2) NOT NULL,
  `fecha_contratacion` date NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_empleado`),
  KEY `fk_tipocontrato_empleado` (`id_tipo_contrato`),
  KEY `idx_empeleado_datos` (`id_datos_persona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluacion_prestamo`
--

DROP TABLE IF EXISTS `evaluacion_prestamo`;
CREATE TABLE IF NOT EXISTS `evaluacion_prestamo` (
  `id_evaluacion_prestamo` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_prestamo` int DEFAULT NULL,
  `capacidad_pago` decimal(14,2) NOT NULL,
  `nivel_riesgo` int DEFAULT NULL,
  `estado_evaluacion` enum('Aprobado','Rechazado','Pendiente') NOT NULL DEFAULT 'Pendiente',
  `fecha_evaluacion` date NOT NULL,
  PRIMARY KEY (`id_evaluacion_prestamo`),
  KEY `fk_prestamo_evaluacion` (`id_prestamo`),
  KEY `idx_evaluacion_cliente` (`id_cliente`,`id_prestamo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturas`
--

DROP TABLE IF EXISTS `facturas`;
CREATE TABLE IF NOT EXISTS `facturas` (
  `id_factura` int NOT NULL AUTO_INCREMENT,
  `id_pago` int DEFAULT NULL,
  `numero_factura` varchar(50) NOT NULL,
  `fecha_emision` date NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  PRIMARY KEY (`id_factura`),
  UNIQUE KEY `numero_factura` (`numero_factura`),
  KEY `idx_factura_pago` (`id_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `financiador`
--

DROP TABLE IF EXISTS `financiador`;
CREATE TABLE IF NOT EXISTS `financiador` (
  `id_financiador` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  `nombre_empresa` varchar(100) NOT NULL,
  `estado_financiador` enum('Activo','Inactivo') DEFAULT 'Activo',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_financiador`),
  KEY `idx_financiador_persona` (`id_datos_persona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fondos`
--

DROP TABLE IF EXISTS `fondos`;
CREATE TABLE IF NOT EXISTS `fondos` (
  `id_fondo` int NOT NULL AUTO_INCREMENT,
  `cartera_normal` decimal(14,2) NOT NULL,
  `cartera_vencida` decimal(14,2) NOT NULL,
  `total_fondos` decimal(14,2) NOT NULL,
  `actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_fondo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fuente_ingreso`
--

DROP TABLE IF EXISTS `fuente_ingreso`;
CREATE TABLE IF NOT EXISTS `fuente_ingreso` (
  `id_fuente_ingreso` int NOT NULL AUTO_INCREMENT,
  `id_ingresos_egresos` int DEFAULT NULL,
  `fuente` varchar(100) NOT NULL,
  PRIMARY KEY (`id_fuente_ingreso`),
  KEY `idx_fuente_ingreso` (`id_ingresos_egresos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `garante`
--

DROP TABLE IF EXISTS `garante`;
CREATE TABLE IF NOT EXISTS `garante` (
  `id_garante` int NOT NULL AUTO_INCREMENT,
  `id_detalle_garantia` int DEFAULT NULL,
  `id_datos_persona` int DEFAULT NULL,
  `relacion_con_cliente` varchar(100) DEFAULT NULL,
  `estado_garante` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_garante`),
  KEY `fk_garante_detalle_garantia` (`id_detalle_garantia`),
  KEY `fk_garante_datospersona` (`id_datos_persona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `garantia`
--

DROP TABLE IF EXISTS `garantia`;
CREATE TABLE IF NOT EXISTS `garantia` (
  `id_garantia` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `id_cliente` int DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `valor` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id_garantia`),
  KEY `fk_cliente_garantia` (`id_cliente`),
  KEY `idx_garantia_prestamo_cliente` (`id_prestamo`,`id_cliente`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `garantia`
--

INSERT INTO `garantia` (`id_garantia`, `id_prestamo`, `id_cliente`, `descripcion`, `valor`) VALUES
(1, 2, 11, 'Hipoteca vivienda principal', 1800000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_acceso`
--

DROP TABLE IF EXISTS `historial_acceso`;
CREATE TABLE IF NOT EXISTS `historial_acceso` (
  `id_historial_acceso` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `fecha_acceso` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_acceso` varchar(45) DEFAULT NULL,
  `accion` varchar(255) NOT NULL,
  PRIMARY KEY (`id_historial_acceso`),
  KEY `fk_usuario_historial` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horas_extras`
--

DROP TABLE IF EXISTS `horas_extras`;
CREATE TABLE IF NOT EXISTS `horas_extras` (
  `id_horas_extras` int NOT NULL AUTO_INCREMENT,
  `id_empleado` int DEFAULT NULL,
  `cantidad_horas` int NOT NULL,
  `pago_horas_extras` decimal(14,2) NOT NULL,
  `monto_total` decimal(14,2) NOT NULL,
  `fecha` date NOT NULL,
  PRIMARY KEY (`id_horas_extras`),
  KEY `idx_horas_extra` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingresos_egresos`
--

DROP TABLE IF EXISTS `ingresos_egresos`;
CREATE TABLE IF NOT EXISTS `ingresos_egresos` (
  `id_ingresos_egresos` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `ingresos_mensuales` decimal(14,2) NOT NULL,
  `egresos_mensuales` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id_ingresos_egresos`),
  KEY `idx_ingresos_cliente` (`id_cliente`)
) ;

--
-- Volcado de datos para la tabla `ingresos_egresos`
--

INSERT INTO `ingresos_egresos` (`id_ingresos_egresos`, `id_cliente`, `ingresos_mensuales`, `egresos_mensuales`) VALUES
(10, 10, 300000.00, 120000.00),
(11, 11, 250000.00, 80000.00),
(12, 12, 80000.00, 30000.00),
(13, 13, 90000.00, 40000.00),
(14, 14, 61500.00, 20700.00),
(17, 17, 66000.00, 22800.00),
(18, 18, 67500.00, 23500.00),
(20, 20, 70500.00, 24900.00),
(28, 28, 82500.00, 30500.00),
(29, 29, 84000.00, 31200.00),
(32, 32, 88500.00, 33300.00),
(35, 35, 93000.00, 35400.00),
(38, 38, 97500.00, 37500.00),
(44, 44, 106500.00, 41700.00),
(47, 47, 111000.00, 43800.00),
(48, 48, 112500.00, 44500.00),
(50, 50, 115500.00, 45900.00),
(58, 58, 127500.00, 51500.00),
(59, 59, 129000.00, 52200.00),
(62, 62, 133500.00, 54300.00),
(65, 65, 138000.00, 56400.00),
(68, 68, 142500.00, 58500.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inversion`
--

DROP TABLE IF EXISTS `inversion`;
CREATE TABLE IF NOT EXISTS `inversion` (
  `id_inversion` int NOT NULL AUTO_INCREMENT,
  `id_financiador` int DEFAULT NULL,
  `monto_invertido` decimal(14,2) NOT NULL,
  `fecha_inversion` date NOT NULL,
  `plazo_meses` int NOT NULL,
  `id_condicion_inversion` int DEFAULT NULL,
  `tasa_interes` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id_inversion`),
  KEY `fk_condicion_inversion` (`id_condicion_inversion`),
  KEY `idx_inversion_financiador` (`id_financiador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimiento_caja`
--

DROP TABLE IF EXISTS `movimiento_caja`;
CREATE TABLE IF NOT EXISTS `movimiento_caja` (
  `id_movimiento_caja` int NOT NULL AUTO_INCREMENT,
  `id_caja` int DEFAULT NULL,
  `id_usuario` int DEFAULT NULL,
  `fecha_movimiento` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo_movimiento` enum('Ingreso','Egreso') NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `motivo` varchar(255) NOT NULL,
  PRIMARY KEY (`id_movimiento_caja`),
  KEY `fk_usuario_movimiento_caja` (`id_usuario`),
  KEY `idx_movimiento_caja` (`id_caja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimiento_fondo`
--

DROP TABLE IF EXISTS `movimiento_fondo`;
CREATE TABLE IF NOT EXISTS `movimiento_fondo` (
  `id_mov_fondo` int NOT NULL AUTO_INCREMENT,
  `id_fondo` int DEFAULT NULL,
  `id_caja` int DEFAULT NULL,
  `monto` decimal(14,2) NOT NULL,
  `fecha_movimiento` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo_movimiento` enum('aporte','desembolso') NOT NULL,
  PRIMARY KEY (`id_mov_fondo`),
  KEY `fk_caja_movimiento_fondo` (`id_caja`),
  KEY `idx_movimiento_fondo` (`id_fondo`,`id_caja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nomina`
--

DROP TABLE IF EXISTS `nomina`;
CREATE TABLE IF NOT EXISTS `nomina` (
  `id_nomina` int NOT NULL AUTO_INCREMENT,
  `periodo` char(7) NOT NULL,
  `generado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_nomina`),
  UNIQUE KEY `periodo` (`periodo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nomina_empleado`
--

DROP TABLE IF EXISTS `nomina_empleado`;
CREATE TABLE IF NOT EXISTS `nomina_empleado` (
  `id_nomina_empleado` int NOT NULL AUTO_INCREMENT,
  `id_empleado` int DEFAULT NULL,
  `sueldo_base` decimal(14,2) NOT NULL,
  `descuento` decimal(14,2) DEFAULT '0.00',
  `sueldo_neto` decimal(14,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  PRIMARY KEY (`id_nomina_empleado`),
  KEY `idx_nomina_empleado` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id_notificacion` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_plantilla_notificacion` int DEFAULT NULL,
  `canal_envio` varchar(100) NOT NULL,
  `programada_para` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `estado_envio` enum('Enviado','Fallido','Pendiente') DEFAULT 'Pendiente',
  PRIMARY KEY (`id_notificacion`),
  KEY `idx_notificacion_cliente` (`id_cliente`),
  KEY `idx_notificacion_plantilla` (`id_plantilla_notificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ocupacion`
--

DROP TABLE IF EXISTS `ocupacion`;
CREATE TABLE IF NOT EXISTS `ocupacion` (
  `id_ocupacion` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  `ocupacion` varchar(100) NOT NULL,
  `empresa` varchar(100) NOT NULL,
  PRIMARY KEY (`id_ocupacion`),
  KEY `fk_datospersona_ocupacion` (`id_datos_persona`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `ocupacion`
--

INSERT INTO `ocupacion` (`id_ocupacion`, `id_datos_persona`, `ocupacion`, `empresa`) VALUES
(13, 16, 'Ingeniero', 'TecnoCaribe SRL'),
(14, 17, 'Contadora', 'Finanzas del Cibao'),
(15, 18, 'Técnico', 'Soluciones LV'),
(16, 19, 'Diseñadora', 'Creativa RD'),
(17, 20, 'Contador', 'Empresa B'),
(20, 23, 'Comerciante', 'Empresa E'),
(21, 24, 'Docente', 'Empresa F'),
(23, 26, 'Contador', 'Empresa B'),
(31, 34, 'Diseñador', 'Empresa D'),
(32, 35, 'Comerciante', 'Empresa E'),
(35, 38, 'Contador', 'Empresa B'),
(38, 41, 'Comerciante', 'Empresa E'),
(41, 44, 'Contador', 'Empresa B'),
(47, 50, 'Contador', 'Empresa B'),
(50, 53, 'Comerciante', 'Empresa E'),
(51, 54, 'Docente', 'Empresa F'),
(53, 56, 'Contador', 'Empresa B'),
(61, 64, 'Diseñador', 'Empresa D'),
(62, 65, 'Comerciante', 'Empresa E'),
(65, 68, 'Contador', 'Empresa B'),
(68, 71, 'Comerciante', 'Empresa E'),
(71, 74, 'Contador', 'Empresa B');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago`
--

DROP TABLE IF EXISTS `pago`;
CREATE TABLE IF NOT EXISTS `pago` (
  `id_pago` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `fecha_pago` date NOT NULL,
  `monto_pagado` decimal(14,2) NOT NULL,
  `metodo_pago` int DEFAULT NULL,
  `id_tipo_moneda` int DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `observacion` text,
  PRIMARY KEY (`id_pago`),
  KEY `fk_tipo_moneda_pago` (`id_tipo_moneda`),
  KEY `fk_usuario_pago` (`creado_por`),
  KEY `idx_pago_prestamo` (`id_prestamo`),
  KEY `idx_pago_metodo` (`metodo_pago`)
) ;

--
-- Disparadores `pago`
--
DROP TRIGGER IF EXISTS `tr_auditoria_pago`;
DELIMITER $$
CREATE TRIGGER `tr_auditoria_pago` AFTER INSERT ON `pago` FOR EACH ROW BEGIN
    INSERT INTO auditoria_cambios(
        tabla_afectada, id_registro, tipo_cambio, fecha_cambio, realizado_por, valores_anteriores, valores_nuevos
    )
    VALUES (
        'Pago',
        NEW.id_pago,
        'Creacion',
        NOW(),
        NEW.creado_por,
        JSON_OBJECT(
            'id_prestamo', NEW.id_prestamo,
            'fecha_pago', NEW.fecha_pago,
            'monto_pagado', NEW.monto_pagado,
            'metodo_pago', NEW.metodo_pago,
            'id_tipo_moneda', NEW.id_tipo_moneda,
            'creado_por', NEW.creado_por,
            'creado_en', NEW.creado_en
        ),
        JSON_OBJECT(
            'id_prestamo', NEW.id_prestamo,
            'fecha_pago', NEW.fecha_pago,
            'monto_pagado', NEW.monto_pagado,
            'metodo_pago', NEW.metodo_pago,
            'id_tipo_moneda', NEW.id_tipo_moneda,
            'creado_por', NEW.creado_por,
            'creado_en', NEW.creado_en
        )
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago_efectivo`
--

DROP TABLE IF EXISTS `pago_efectivo`;
CREATE TABLE IF NOT EXISTS `pago_efectivo` (
  `id_pago` int NOT NULL,
  `recibo_numero` varchar(50) NOT NULL,
  PRIMARY KEY (`id_pago`),
  UNIQUE KEY `recibo_numero` (`recibo_numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago_transferencia`
--

DROP TABLE IF EXISTS `pago_transferencia`;
CREATE TABLE IF NOT EXISTS `pago_transferencia` (
  `id_pago` int NOT NULL,
  `codigo_transferencia` varchar(100) NOT NULL,
  `banco` varchar(100) NOT NULL,
  PRIMARY KEY (`id_pago`),
  UNIQUE KEY `codigo_transferencia` (`codigo_transferencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permiso`
--

DROP TABLE IF EXISTS `permiso`;
CREATE TABLE IF NOT EXISTS `permiso` (
  `id_permiso` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`id_permiso`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `permiso`
--

INSERT INTO `permiso` (`id_permiso`, `clave`, `nombre`) VALUES
(1, 'prestamos', 'Administrar préstamos'),
(2, 'clientes', 'Administrar clientes'),
(3, 'rrhh', 'Administrar RRHH'),
(4, 'admin', 'Administración general');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

DROP TABLE IF EXISTS `permisos`;
CREATE TABLE IF NOT EXISTS `permisos` (
  `id_permiso` int NOT NULL AUTO_INCREMENT,
  `nombre_permiso` varchar(100) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  PRIMARY KEY (`id_permiso`),
  UNIQUE KEY `nombre_permiso` (`nombre_permiso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plantilla_notificacion`
--

DROP TABLE IF EXISTS `plantilla_notificacion`;
CREATE TABLE IF NOT EXISTS `plantilla_notificacion` (
  `id_plantilla_notificacion` int NOT NULL AUTO_INCREMENT,
  `tipo_notificacion` varchar(100) NOT NULL,
  `asunto` varchar(150) NOT NULL,
  `cuerpo` text NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_plantilla_notificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamo`
--

DROP TABLE IF EXISTS `prestamo`;
CREATE TABLE IF NOT EXISTS `prestamo` (
  `id_prestamo` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_tipo_prestamo` int DEFAULT NULL,
  `monto_solicitado` decimal(14,2) NOT NULL,
  `fecha_solicitud` date NOT NULL,
  `plazo_meses` int NOT NULL,
  `id_estado_prestamo` int DEFAULT NULL,
  `id_condicion_actual` int DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `numero_contrato` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_prestamo`),
  UNIQUE KEY `numero_contrato` (`numero_contrato`),
  KEY `fk_prestamo_condicion` (`id_condicion_actual`),
  KEY `idx_prestamo_cliente` (`id_cliente`),
  KEY `idx_prestamo_estado` (`id_estado_prestamo`),
  KEY `idx_prestamo_tipo` (`id_tipo_prestamo`),
  KEY `idx_prestamo_fecha` (`fecha_solicitud`),
  KEY `idx_prestamo_creador` (`creado_por`)
) ;

--
-- Volcado de datos para la tabla `prestamo`
--

INSERT INTO `prestamo` (`id_prestamo`, `id_cliente`, `id_tipo_prestamo`, `monto_solicitado`, `fecha_solicitud`, `plazo_meses`, `id_estado_prestamo`, `id_condicion_actual`, `creado_por`, `creado_en`, `actualizado_en`, `numero_contrato`) VALUES
(1, 10, 1, 600000.00, '2025-11-08', 12, 4, NULL, NULL, '2025-11-08 04:13:55', '2025-11-08 04:13:55', NULL),
(2, 11, 2, 1200000.00, '2025-11-08', 120, 5, NULL, NULL, '2025-11-08 04:13:55', '2025-11-08 04:13:55', NULL),
(3, 12, 1, 150000.00, '2025-11-08', 10, 1, NULL, NULL, '2025-11-08 04:13:55', '2025-11-08 04:13:55', NULL),
(4, 14, 2, 244800.00, '2025-11-08', 24, 1, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(5, 17, 2, 259200.00, '2025-11-08', 24, 2, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(6, 20, 2, 273600.00, '2025-11-08', 24, 4, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(8, 29, 2, 316800.00, '2025-11-08', 24, 1, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(9, 32, 2, 331200.00, '2025-11-08', 24, 2, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(10, 35, 2, 345600.00, '2025-11-08', 24, 4, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(13, 44, 2, 388800.00, '2025-11-08', 24, 1, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(14, 47, 2, 403200.00, '2025-11-08', 24, 2, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(15, 50, 2, 417600.00, '2025-11-08', 24, 4, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(17, 59, 2, 460800.00, '2025-11-08', 24, 1, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(18, 62, 2, 475200.00, '2025-11-08', 24, 2, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL),
(19, 65, 2, 489600.00, '2025-11-08', 24, 4, NULL, NULL, '2025-11-08 04:29:47', '2025-11-08 04:29:47', NULL);

--
-- Disparadores `prestamo`
--
DROP TRIGGER IF EXISTS `actualizacion_prestamo_protegido`;
DELIMITER $$
CREATE TRIGGER `actualizacion_prestamo_protegido` BEFORE UPDATE ON `prestamo` FOR EACH ROW BEGIN 
    DECLARE v_estado INT;
    SET v_estado = OLD.id_estado_prestamo;
    IF v_estado IN (4,5) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede modificar un préstamo en estado En mora o cerrado.';
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_auditoria_prestamo`;
DELIMITER $$
CREATE TRIGGER `tr_auditoria_prestamo` AFTER UPDATE ON `prestamo` FOR EACH ROW BEGIN
    INSERT INTO auditoria_cambios(
        tabla_afectada, id_registro, tipo_cambio, fecha_cambio, realizado_por, valores_anteriores, valores_nuevos
    ) VALUES (
        'prestamo',
        OLD.id_prestamo,
        'UPDATE',
        NOW(),
        COALESCE(NEW.creado_por, OLD.creado_por),
        JSON_OBJECT(
            'monto_solicitado', OLD.monto_solicitado,
            'plazo_meses', OLD.plazo_meses,
            'id_estado_prestamo', OLD.id_estado_prestamo
        ),
        JSON_OBJECT(
            'monto_solicitado', NEW.monto_solicitado,
            'plazo_meses', NEW.plazo_meses,
            'id_estado_prestamo', NEW.id_estado_prestamo
        )
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `tr_generar_cronograma`;
DELIMITER $$
CREATE TRIGGER `tr_generar_cronograma` AFTER INSERT ON `prestamo` FOR EACH ROW BEGIN
    CALL generar_cronograma(NEW.id_prestamo);
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `validar_capacidad_pago`;
DELIMITER $$
CREATE TRIGGER `validar_capacidad_pago` BEFORE INSERT ON `prestamo` FOR EACH ROW BEGIN
    DECLARE v_ingresos DECIMAL(14,2) DEFAULT 0.00;
    DECLARE v_egresos DECIMAL(14,2) DEFAULT 0.00;
    DECLARE v_capacidad_pago DECIMAL(14,2);
    DECLARE v_cuota_estimada DECIMAL(14,2);
    DECLARE v_tasa_interes DECIMAL(5,2) DEFAULT 0.00;

    SELECT ingresos_mensuales, egresos_mensuales 
    INTO v_ingresos, v_egresos
    FROM ingresos_egresos
    WHERE id_cliente = NEW.id_cliente
    LIMIT 1;

    IF v_ingresos IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingresos insuficientes';
    END IF;

    SET v_capacidad_pago = v_ingresos - v_egresos;
    IF v_capacidad_pago <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Capacidad de pago insuficiente ';
    END IF;

    SELECT tasa_interes INTO v_tasa_interes 
    FROM tipo_prestamo 
    WHERE id_tipo_prestamo = NEW.id_tipo_prestamo 
    LIMIT 1;

    IF v_tasa_interes IS NULL OR v_tasa_interes = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Interes nulo';
    END IF;

    SET v_cuota_estimada = (NEW.monto_solicitado * (1 + (v_tasa_interes/100) * (NEW.plazo_meses/12))) / NEW.plazo_meses;

    IF v_cuota_estimada / v_capacidad_pago > 0.4 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuota estimada excede el 40% de la capacidad de pago.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamo_hipotecario`
--

DROP TABLE IF EXISTS `prestamo_hipotecario`;
CREATE TABLE IF NOT EXISTS `prestamo_hipotecario` (
  `id_prestamo_hipotecario` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `valor_propiedad` decimal(14,2) NOT NULL,
  `porcentaje_financiamiento` decimal(5,2) NOT NULL,
  `direccion_propiedad` varchar(255) NOT NULL,
  PRIMARY KEY (`id_prestamo_hipotecario`),
  KEY `fk_prestamo_hipotecario` (`id_prestamo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `prestamo_hipotecario`
--

INSERT INTO `prestamo_hipotecario` (`id_prestamo_hipotecario`, `id_prestamo`, `valor_propiedad`, `porcentaje_financiamiento`, `direccion_propiedad`) VALUES
(4, 2, 1800000.00, 66.67, 'C/ Principal #100, Santiago');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamo_personal`
--

DROP TABLE IF EXISTS `prestamo_personal`;
CREATE TABLE IF NOT EXISTS `prestamo_personal` (
  `id_prestamo_personal` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `motivo` varchar(255) NOT NULL,
  PRIMARY KEY (`id_prestamo_personal`),
  KEY `fk_prestamo_personal` (`id_prestamo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `prestamo_personal`
--

INSERT INTO `prestamo_personal` (`id_prestamo_personal`, `id_prestamo`, `motivo`) VALUES
(3, 1, 'Consolidación de deudas'),
(4, 3, 'Compra de equipos');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamo_propiedad`
--

DROP TABLE IF EXISTS `prestamo_propiedad`;
CREATE TABLE IF NOT EXISTS `prestamo_propiedad` (
  `id_prestamo` int NOT NULL,
  `id_propiedad` int NOT NULL,
  `porcentaje_garantia` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id_prestamo`,`id_propiedad`),
  KEY `fk_propiedad_prestamo` (`id_propiedad`),
  KEY `idx_prestamo_propiedad` (`id_prestamo`,`id_propiedad`)
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promociones`
--

DROP TABLE IF EXISTS `promociones`;
CREATE TABLE IF NOT EXISTS `promociones` (
  `id_promocion` int NOT NULL AUTO_INCREMENT,
  `nombre_promocion` varchar(100) NOT NULL,
  `estado` enum('Activa','Inactiva') DEFAULT 'Inactiva',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `id_tipo_promocion` int DEFAULT NULL,
  PRIMARY KEY (`id_promocion`),
  KEY `idx_promocion_tipo` (`id_tipo_promocion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promociones_campana`
--

DROP TABLE IF EXISTS `promociones_campana`;
CREATE TABLE IF NOT EXISTS `promociones_campana` (
  `id_promocion` int NOT NULL,
  `id_campana` int NOT NULL,
  PRIMARY KEY (`id_promocion`,`id_campana`),
  KEY `fk_campana_promocion` (`id_campana`),
  KEY `idx_promocion_campana` (`id_promocion`,`id_campana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `propiedades`
--

DROP TABLE IF EXISTS `propiedades`;
CREATE TABLE IF NOT EXISTS `propiedades` (
  `id_propiedad` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_direccion` int DEFAULT NULL,
  `valor_propiedad` decimal(14,2) NOT NULL,
  `fecha_registro` date NOT NULL,
  PRIMARY KEY (`id_propiedad`),
  KEY `idx_propiedades_cliente` (`id_cliente`),
  KEY `idx_propiedades_direccion` (`id_direccion`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `puntaje_crediticio`
--

DROP TABLE IF EXISTS `puntaje_crediticio`;
CREATE TABLE IF NOT EXISTS `puntaje_crediticio` (
  `id_puntaje_crediticio` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_nivel_riesgo` int DEFAULT NULL,
  `puntaje` int DEFAULT '0',
  `total_transacciones` int NOT NULL,
  PRIMARY KEY (`id_puntaje_crediticio`),
  KEY `fk_nivel_riesgo_puntaje` (`id_nivel_riesgo`),
  KEY `idx_puntaje_cliente` (`id_cliente`,`id_nivel_riesgo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reestructuracion_prestamo`
--

DROP TABLE IF EXISTS `reestructuracion_prestamo`;
CREATE TABLE IF NOT EXISTS `reestructuracion_prestamo` (
  `id_restructuracion_prestamo` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `id_condicion_nueva` int DEFAULT NULL,
  `fecha_reestructuracion` date NOT NULL,
  `aprobado_por` int DEFAULT NULL,
  `notas` text,
  PRIMARY KEY (`id_restructuracion_prestamo`),
  KEY `fk_restructuracion_prestamo` (`id_prestamo`),
  KEY `fk_restructuracion_condicionprestamo` (`id_condicion_nueva`),
  KEY `fk_usuario_restructuracion` (`aprobado_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `relacion_financiador_cliente`
--

DROP TABLE IF EXISTS `relacion_financiador_cliente`;
CREATE TABLE IF NOT EXISTS `relacion_financiador_cliente` (
  `id_relacion_financiador_cliente` int NOT NULL AUTO_INCREMENT,
  `id_financiador` int DEFAULT NULL,
  `id_cliente` int DEFAULT NULL,
  PRIMARY KEY (`id_relacion_financiador_cliente`),
  KEY `fk_relacion_financiador` (`id_financiador`),
  KEY `fk_relacion_cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes`
--

DROP TABLE IF EXISTS `reportes`;
CREATE TABLE IF NOT EXISTS `reportes` (
  `id_reportes` int NOT NULL AUTO_INCREMENT,
  `tipo_reporte` varchar(100) NOT NULL,
  `parametros` text,
  `fecha_generacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `generado_por` int DEFAULT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  PRIMARY KEY (`id_reportes`),
  KEY `fk_usuario_reportes` (`generado_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_cliente`
--

DROP TABLE IF EXISTS `reportes_cliente`;
CREATE TABLE IF NOT EXISTS `reportes_cliente` (
  `id_reporte` int NOT NULL,
  `id_cliente` int NOT NULL,
  PRIMARY KEY (`id_reporte`,`id_cliente`),
  KEY `fk_cliente_reporte` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_prestamo`
--

DROP TABLE IF EXISTS `reportes_prestamo`;
CREATE TABLE IF NOT EXISTS `reportes_prestamo` (
  `id_reporte` int NOT NULL,
  `id_prestamo` int NOT NULL,
  PRIMARY KEY (`id_reporte`,`id_prestamo`),
  KEY `fk_prestamo_reporte` (`id_prestamo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `retiro_garantia`
--

DROP TABLE IF EXISTS `retiro_garantia`;
CREATE TABLE IF NOT EXISTS `retiro_garantia` (
  `id_retiro_garantia` int NOT NULL AUTO_INCREMENT,
  `id_pago` int DEFAULT NULL,
  `id_garantia` int DEFAULT NULL,
  `fecha_uso` date NOT NULL,
  `monto_usado` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id_retiro_garantia`),
  KEY `fk_retiro_garantia` (`id_pago`),
  KEY `fk_garantia` (`id_garantia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `retiro_inversion`
--

DROP TABLE IF EXISTS `retiro_inversion`;
CREATE TABLE IF NOT EXISTS `retiro_inversion` (
  `id_retiro_inversion` int NOT NULL AUTO_INCREMENT,
  `id_inversion` int DEFAULT NULL,
  `fecha_retiro` date NOT NULL,
  `monto_retirado` decimal(14,2) NOT NULL,
  `id_pago` int DEFAULT NULL,
  PRIMARY KEY (`id_retiro_inversion`),
  KEY `fk_pago_retiro_inversion` (`id_pago`),
  KEY `idx_retiro_inversion` (`id_inversion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

DROP TABLE IF EXISTS `rol`;
CREATE TABLE IF NOT EXISTS `rol` (
  `id_rol` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id_rol` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles_permisos`
--

DROP TABLE IF EXISTS `roles_permisos`;
CREATE TABLE IF NOT EXISTS `roles_permisos` (
  `id_rol` int NOT NULL,
  `id_permiso` int NOT NULL,
  PRIMARY KEY (`id_rol`,`id_permiso`),
  KEY `fk_permisos` (`id_permiso`),
  KEY `idx_roles_permisos` (`id_rol`,`id_permiso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permiso`
--

DROP TABLE IF EXISTS `rol_permiso`;
CREATE TABLE IF NOT EXISTS `rol_permiso` (
  `id_rol` int NOT NULL,
  `id_permiso` int NOT NULL,
  PRIMARY KEY (`id_rol`,`id_permiso`),
  KEY `id_rol` (`id_rol`),
  KEY `id_permiso` (`id_permiso`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `telefono`
--

DROP TABLE IF EXISTS `telefono`;
CREATE TABLE IF NOT EXISTS `telefono` (
  `id_telefono` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  `telefono` varchar(20) NOT NULL,
  `es_principal` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_telefono`),
  UNIQUE KEY `uq_telefono` (`id_datos_persona`,`es_principal`),
  KEY `idx_telefono_persona` (`id_datos_persona`,`es_principal`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `telefono`
--

INSERT INTO `telefono` (`id_telefono`, `id_datos_persona`, `telefono`, `es_principal`) VALUES
(12, 16, '809-555-1001', 1),
(13, 17, '809-555-2002', 1),
(14, 18, '809-555-3003', 1),
(15, 19, '809-555-4004', 1),
(16, 20, '809-555-1001', 1),
(19, 23, '809-555-1004', 1),
(20, 24, '809-555-1005', 1),
(22, 26, '809-555-1007', 1),
(30, 34, '809-555-1015', 1),
(31, 35, '809-555-1016', 1),
(34, 38, '809-555-1019', 1),
(37, 41, '809-555-1022', 1),
(40, 44, '809-555-1025', 1),
(46, 50, '809-555-1031', 1),
(49, 53, '809-555-1034', 1),
(50, 54, '809-555-1035', 1),
(52, 56, '809-555-1037', 1),
(60, 64, '809-555-1045', 1),
(61, 65, '809-555-1046', 1),
(64, 68, '809-555-1049', 1),
(67, 71, '809-555-1052', 1),
(70, 74, '809-555-1055', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_prestamo`
--

DROP TABLE IF EXISTS `tipo_prestamo`;
CREATE TABLE IF NOT EXISTS `tipo_prestamo` (
  `id_tipo_prestamo` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tasa_interes` decimal(5,2) NOT NULL,
  `monto_minimo` decimal(14,2) NOT NULL,
  `id_tipo_amortizacion` int NOT NULL,
  `plazo_minimo_meses` int NOT NULL,
  `plazo_maximo_meses` int NOT NULL,
  PRIMARY KEY (`id_tipo_prestamo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `tipo_prestamo`
--

INSERT INTO `tipo_prestamo` (`id_tipo_prestamo`, `nombre`, `tasa_interes`, `monto_minimo`, `id_tipo_amortizacion`, `plazo_minimo_meses`, `plazo_maximo_meses`) VALUES
(1, 'Personal', 12.50, 10000.00, 1, 6, 24),
(2, 'Hipotecario', 8.50, 50000.00, 2, 12, 180);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_promocion`
--

DROP TABLE IF EXISTS `tipo_promocion`;
CREATE TABLE IF NOT EXISTS `tipo_promocion` (
  `id_tipo_promocion` int NOT NULL AUTO_INCREMENT,
  `tipo_promocion` varchar(50) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `puntos_necesarios` int NOT NULL,
  PRIMARY KEY (`id_tipo_promocion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tmp_seed`
--

DROP TABLE IF EXISTS `tmp_seed`;
CREATE TABLE IF NOT EXISTS `tmp_seed` (
  `n` int NOT NULL,
  `nombre` varchar(80) DEFAULT NULL,
  `apellido` varchar(80) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `genero_id` int DEFAULT NULL,
  `id_dp` int DEFAULT NULL,
  `id_cliente` int DEFAULT NULL,
  PRIMARY KEY (`n`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `uso_promocion`
--

DROP TABLE IF EXISTS `uso_promocion`;
CREATE TABLE IF NOT EXISTS `uso_promocion` (
  `id_asignacion_promocion` int NOT NULL,
  `id_prestamo` int NOT NULL,
  `fecha_uso` date NOT NULL,
  PRIMARY KEY (`id_asignacion_promocion`,`id_prestamo`),
  KEY `fk_uso_promocion_prestamo` (`id_prestamo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

DROP TABLE IF EXISTS `usuario`;
CREATE TABLE IF NOT EXISTS `usuario` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  KEY `fk_datospersona_usuario` (`id_datos_persona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_roles`
--

DROP TABLE IF EXISTS `usuarios_roles`;
CREATE TABLE IF NOT EXISTS `usuarios_roles` (
  `id_usuario` int NOT NULL,
  `id_rol` int NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_rol`),
  KEY `fk_rol_usuario` (`id_rol`),
  KEY `idx_usuario_roles` (`id_usuario`,`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_rol`
--

DROP TABLE IF EXISTS `usuario_rol`;
CREATE TABLE IF NOT EXISTS `usuario_rol` (
  `id_usuario` int NOT NULL,
  `id_rol` int NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_rol`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_rol` (`id_rol`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `ajuste_tasa_financiador`
--
ALTER TABLE `ajuste_tasa_financiador`
  ADD CONSTRAINT `fk_financiador_ajuste_tasa` FOREIGN KEY (`id_financiador`) REFERENCES `financiador` (`id_financiador`);

--
-- Filtros para la tabla `asignacion_pago`
--
ALTER TABLE `asignacion_pago`
  ADD CONSTRAINT `fk_asignacion_pago` FOREIGN KEY (`id_pago`) REFERENCES `pago` (`id_pago`),
  ADD CONSTRAINT `fk_cronograma_asignacion` FOREIGN KEY (`id_cronograma_cuota`) REFERENCES `cronograma_cuota` (`id_cronograma_cuota`);

--
-- Filtros para la tabla `asignacion_promocion`
--
ALTER TABLE `asignacion_promocion`
  ADD CONSTRAINT `fk_asignacion_promocion_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_asignacion_promocion_promocion` FOREIGN KEY (`id_promocion`) REFERENCES `promociones` (`id_promocion`);

--
-- Filtros para la tabla `asistencia_empleado`
--
ALTER TABLE `asistencia_empleado`
  ADD CONSTRAINT `fk_asistencia_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`);

--
-- Filtros para la tabla `auditoria_cambios`
--
ALTER TABLE `auditoria_cambios`
  ADD CONSTRAINT `fk_usuario_auditoria` FOREIGN KEY (`realizado_por`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `beneficios_empleado`
--
ALTER TABLE `beneficios_empleado`
  ADD CONSTRAINT `fk_beneficios_nomina` FOREIGN KEY (`id_nomina_empleado`) REFERENCES `nomina_empleado` (`id_nomina_empleado`),
  ADD CONSTRAINT `fk_tipobeneficio_beneficios` FOREIGN KEY (`id_beneficio`) REFERENCES `cat_beneficio` (`id_beneficio`);

--
-- Filtros para la tabla `caja`
--
ALTER TABLE `caja`
  ADD CONSTRAINT `fk_empleado_caja` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`);

--
-- Filtros para la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD CONSTRAINT `fk_datospersona_cliente` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`);

--
-- Filtros para la tabla `condicion_prestamo`
--
ALTER TABLE `condicion_prestamo`
  ADD CONSTRAINT `fk_periodo_pago_condicion` FOREIGN KEY (`id_periodo_pago`) REFERENCES `cat_periodo_pago` (`id_periodo_pago`);

--
-- Filtros para la tabla `cronograma_cuota`
--
ALTER TABLE `cronograma_cuota`
  ADD CONSTRAINT `fk_prestamo_cronograma` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `datos_persona`
--
ALTER TABLE `datos_persona`
  ADD CONSTRAINT `fk_genero` FOREIGN KEY (`genero`) REFERENCES `cat_genero` (`id_genero`);

--
-- Filtros para la tabla `deducciones_empleado`
--
ALTER TABLE `deducciones_empleado`
  ADD CONSTRAINT `fk_deducciones_nomina` FOREIGN KEY (`id_nomina_empleado`) REFERENCES `nomina_empleado` (`id_nomina_empleado`),
  ADD CONSTRAINT `fk_tipodeduccion_deducciones` FOREIGN KEY (`id_tipo_deduccion`) REFERENCES `cat_tipo_deduccion` (`id_tipo_deduccion`);

--
-- Filtros para la tabla `desembolso`
--
ALTER TABLE `desembolso`
  ADD CONSTRAINT `fk_metodo_entrega_desembolso` FOREIGN KEY (`metodo_entrega`) REFERENCES `cat_tipo_pago` (`id_tipo_pago`),
  ADD CONSTRAINT `fk_prestamo_desembolso` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `detalle_evaluacion`
--
ALTER TABLE `detalle_evaluacion`
  ADD CONSTRAINT `fk_detalle_evaluacion_prestamo` FOREIGN KEY (`id_evaluacion_prestamo`) REFERENCES `evaluacion_prestamo` (`id_evaluacion_prestamo`),
  ADD CONSTRAINT `fk_usuario_evaluacion` FOREIGN KEY (`evaluado_por`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `detalle_garantia`
--
ALTER TABLE `detalle_garantia`
  ADD CONSTRAINT `fk_detalle_garantia_tipo` FOREIGN KEY (`id_tipo_garantia`) REFERENCES `cat_tipo_garantia` (`id_tipo_garantia`),
  ADD CONSTRAINT `fk_garantia_detalle_garantia` FOREIGN KEY (`id_garantia`) REFERENCES `garantia` (`id_garantia`);

--
-- Filtros para la tabla `direccion`
--
ALTER TABLE `direccion`
  ADD CONSTRAINT `fk_datospersona_direccion` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`);

--
-- Filtros para la tabla `direccion_entidad`
--
ALTER TABLE `direccion_entidad`
  ADD CONSTRAINT `fk_direccion_entidad` FOREIGN KEY (`id_direccion`) REFERENCES `direccion` (`id_direccion`),
  ADD CONSTRAINT `fk_tipo_entidad` FOREIGN KEY (`id_tipo_entidad`) REFERENCES `cat_tipo_entidad` (`id_tipo_entidad`);

--
-- Filtros para la tabla `documentacion_cliente`
--
ALTER TABLE `documentacion_cliente`
  ADD CONSTRAINT `fk_cliente_documentos` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_tipo_documentoC` FOREIGN KEY (`id_documentacion_cliente`) REFERENCES `cat_documentacion_cliente` (`id_documentacion_cliente`);

--
-- Filtros para la tabla `documento_garante`
--
ALTER TABLE `documento_garante`
  ADD CONSTRAINT `fk_documento_garante` FOREIGN KEY (`id_garante`) REFERENCES `garante` (`id_garante`);

--
-- Filtros para la tabla `documento_garantia`
--
ALTER TABLE `documento_garantia`
  ADD CONSTRAINT `fk_documento_garantia_detalle` FOREIGN KEY (`id_detalle_garantia`) REFERENCES `detalle_garantia` (`id_detalle_garantia`);

--
-- Filtros para la tabla `documento_identidad`
--
ALTER TABLE `documento_identidad`
  ADD CONSTRAINT `fk_datospersona_documentoI` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`),
  ADD CONSTRAINT `fk_tipodocumento_documentoI` FOREIGN KEY (`id_tipo_documento`) REFERENCES `cat_tipo_documento` (`id_tipo_documento`);

--
-- Filtros para la tabla `email`
--
ALTER TABLE `email`
  ADD CONSTRAINT `fk_datospersona_email` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`);

--
-- Filtros para la tabla `empleado`
--
ALTER TABLE `empleado`
  ADD CONSTRAINT `fk_datospersona_empleado` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`),
  ADD CONSTRAINT `fk_tipocontrato_empleado` FOREIGN KEY (`id_tipo_contrato`) REFERENCES `cat_tipo_contrato` (`id_tipo_contrato`);

--
-- Filtros para la tabla `evaluacion_prestamo`
--
ALTER TABLE `evaluacion_prestamo`
  ADD CONSTRAINT `fk_cliente_evaluacion` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_prestamo_evaluacion` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD CONSTRAINT `fk_pago_factura` FOREIGN KEY (`id_pago`) REFERENCES `pago` (`id_pago`);

--
-- Filtros para la tabla `financiador`
--
ALTER TABLE `financiador`
  ADD CONSTRAINT `fk_datospersona_financiador` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`);

--
-- Filtros para la tabla `fuente_ingreso`
--
ALTER TABLE `fuente_ingreso`
  ADD CONSTRAINT `fk_ingresos_fuenteI` FOREIGN KEY (`id_ingresos_egresos`) REFERENCES `ingresos_egresos` (`id_ingresos_egresos`);

--
-- Filtros para la tabla `garante`
--
ALTER TABLE `garante`
  ADD CONSTRAINT `fk_garante_datospersona` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`),
  ADD CONSTRAINT `fk_garante_detalle_garantia` FOREIGN KEY (`id_detalle_garantia`) REFERENCES `detalle_garantia` (`id_detalle_garantia`);

--
-- Filtros para la tabla `garantia`
--
ALTER TABLE `garantia`
  ADD CONSTRAINT `fk_cliente_garantia` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_prestamo_garantia` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `historial_acceso`
--
ALTER TABLE `historial_acceso`
  ADD CONSTRAINT `fk_usuario_historial` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `horas_extras`
--
ALTER TABLE `horas_extras`
  ADD CONSTRAINT `fk_empleado_horas_extra` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`);

--
-- Filtros para la tabla `ingresos_egresos`
--
ALTER TABLE `ingresos_egresos`
  ADD CONSTRAINT `fk_cliente_ingresosegresos` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`);

--
-- Filtros para la tabla `inversion`
--
ALTER TABLE `inversion`
  ADD CONSTRAINT `fk_condicion_inversion` FOREIGN KEY (`id_condicion_inversion`) REFERENCES `condicion_inversion` (`id_condicion_inversion`),
  ADD CONSTRAINT `fk_financiador_inversion` FOREIGN KEY (`id_financiador`) REFERENCES `financiador` (`id_financiador`);

--
-- Filtros para la tabla `movimiento_caja`
--
ALTER TABLE `movimiento_caja`
  ADD CONSTRAINT `fk_caja_movimiento_caja` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`),
  ADD CONSTRAINT `fk_usuario_movimiento_caja` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `movimiento_fondo`
--
ALTER TABLE `movimiento_fondo`
  ADD CONSTRAINT `fk_caja_movimiento_fondo` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`),
  ADD CONSTRAINT `fk_fondo_movimiento` FOREIGN KEY (`id_fondo`) REFERENCES `fondos` (`id_fondo`);

--
-- Filtros para la tabla `nomina_empleado`
--
ALTER TABLE `nomina_empleado`
  ADD CONSTRAINT `fk_empleado_nomina` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`);

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_cliente_notificacion` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_plantilla_notificacion` FOREIGN KEY (`id_plantilla_notificacion`) REFERENCES `plantilla_notificacion` (`id_plantilla_notificacion`);

--
-- Filtros para la tabla `ocupacion`
--
ALTER TABLE `ocupacion`
  ADD CONSTRAINT `fk_datospersona_ocupacion` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`);

--
-- Filtros para la tabla `pago`
--
ALTER TABLE `pago`
  ADD CONSTRAINT `fk_metodo_pagoP` FOREIGN KEY (`metodo_pago`) REFERENCES `cat_tipo_pago` (`id_tipo_pago`),
  ADD CONSTRAINT `fk_prestamo_pago` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`),
  ADD CONSTRAINT `fk_tipo_moneda_pago` FOREIGN KEY (`id_tipo_moneda`) REFERENCES `cat_tipo_moneda` (`id_tipo_moneda`),
  ADD CONSTRAINT `fk_usuario_pago` FOREIGN KEY (`creado_por`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `pago_efectivo`
--
ALTER TABLE `pago_efectivo`
  ADD CONSTRAINT `fk_pago_efectivo` FOREIGN KEY (`id_pago`) REFERENCES `pago` (`id_pago`);

--
-- Filtros para la tabla `pago_transferencia`
--
ALTER TABLE `pago_transferencia`
  ADD CONSTRAINT `fk_pago_transferencia` FOREIGN KEY (`id_pago`) REFERENCES `pago` (`id_pago`);

--
-- Filtros para la tabla `prestamo`
--
ALTER TABLE `prestamo`
  ADD CONSTRAINT `fk_cliente_prestamo` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_estado_prestamo_p` FOREIGN KEY (`id_estado_prestamo`) REFERENCES `cat_estado_prestamo` (`id_estado_prestamo`),
  ADD CONSTRAINT `fk_prestamo_condicion` FOREIGN KEY (`id_condicion_actual`) REFERENCES `condicion_prestamo` (`id_condicion_prestamo`),
  ADD CONSTRAINT `fk_tipo_prestamo_p` FOREIGN KEY (`id_tipo_prestamo`) REFERENCES `tipo_prestamo` (`id_tipo_prestamo`),
  ADD CONSTRAINT `fk_usuario_prestamo` FOREIGN KEY (`creado_por`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `prestamo_hipotecario`
--
ALTER TABLE `prestamo_hipotecario`
  ADD CONSTRAINT `fk_prestamo_hipotecario` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `prestamo_personal`
--
ALTER TABLE `prestamo_personal`
  ADD CONSTRAINT `fk_prestamo_personal` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `prestamo_propiedad`
--
ALTER TABLE `prestamo_propiedad`
  ADD CONSTRAINT `fk_prestamo_propiedad` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`),
  ADD CONSTRAINT `fk_propiedad_prestamo` FOREIGN KEY (`id_propiedad`) REFERENCES `propiedades` (`id_propiedad`);

--
-- Filtros para la tabla `promociones`
--
ALTER TABLE `promociones`
  ADD CONSTRAINT `fk_tipopromocion_promociones` FOREIGN KEY (`id_tipo_promocion`) REFERENCES `tipo_promocion` (`id_tipo_promocion`);

--
-- Filtros para la tabla `promociones_campana`
--
ALTER TABLE `promociones_campana`
  ADD CONSTRAINT `fk_campana_promocion` FOREIGN KEY (`id_campana`) REFERENCES `campanas` (`id_campana`),
  ADD CONSTRAINT `fk_promociones_campana_promocion` FOREIGN KEY (`id_promocion`) REFERENCES `promociones` (`id_promocion`);

--
-- Filtros para la tabla `propiedades`
--
ALTER TABLE `propiedades`
  ADD CONSTRAINT `fk_cliente_propiedad` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_direccion_propiedad` FOREIGN KEY (`id_direccion`) REFERENCES `direccion` (`id_direccion`);

--
-- Filtros para la tabla `puntaje_crediticio`
--
ALTER TABLE `puntaje_crediticio`
  ADD CONSTRAINT `fk_cliente_puntaje` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_nivel_riesgo_puntaje` FOREIGN KEY (`id_nivel_riesgo`) REFERENCES `cat_nivel_riesgo` (`id_nivel_riesgo`);

--
-- Filtros para la tabla `reestructuracion_prestamo`
--
ALTER TABLE `reestructuracion_prestamo`
  ADD CONSTRAINT `fk_restructuracion_condicionprestamo` FOREIGN KEY (`id_condicion_nueva`) REFERENCES `condicion_prestamo` (`id_condicion_prestamo`),
  ADD CONSTRAINT `fk_restructuracion_prestamo` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`),
  ADD CONSTRAINT `fk_usuario_restructuracion` FOREIGN KEY (`aprobado_por`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `relacion_financiador_cliente`
--
ALTER TABLE `relacion_financiador_cliente`
  ADD CONSTRAINT `fk_relacion_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_relacion_financiador` FOREIGN KEY (`id_financiador`) REFERENCES `financiador` (`id_financiador`);

--
-- Filtros para la tabla `reportes`
--
ALTER TABLE `reportes`
  ADD CONSTRAINT `fk_usuario_reportes` FOREIGN KEY (`generado_por`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `reportes_cliente`
--
ALTER TABLE `reportes_cliente`
  ADD CONSTRAINT `fk_cliente_reporte` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `fk_reporteC` FOREIGN KEY (`id_reporte`) REFERENCES `reportes` (`id_reportes`);

--
-- Filtros para la tabla `reportes_prestamo`
--
ALTER TABLE `reportes_prestamo`
  ADD CONSTRAINT `fk_prestamo_reporte` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`),
  ADD CONSTRAINT `fk_reporteP` FOREIGN KEY (`id_reporte`) REFERENCES `reportes` (`id_reportes`);

--
-- Filtros para la tabla `retiro_garantia`
--
ALTER TABLE `retiro_garantia`
  ADD CONSTRAINT `fk_garantia` FOREIGN KEY (`id_garantia`) REFERENCES `garantia` (`id_garantia`),
  ADD CONSTRAINT `fk_retiro_garantia` FOREIGN KEY (`id_pago`) REFERENCES `pago` (`id_pago`);

--
-- Filtros para la tabla `retiro_inversion`
--
ALTER TABLE `retiro_inversion`
  ADD CONSTRAINT `fk_inversion_retiro_inversion` FOREIGN KEY (`id_inversion`) REFERENCES `inversion` (`id_inversion`),
  ADD CONSTRAINT `fk_pago_retiro_inversion` FOREIGN KEY (`id_pago`) REFERENCES `pago` (`id_pago`);

--
-- Filtros para la tabla `roles_permisos`
--
ALTER TABLE `roles_permisos`
  ADD CONSTRAINT `fk_permisos` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`),
  ADD CONSTRAINT `fk_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);

--
-- Filtros para la tabla `telefono`
--
ALTER TABLE `telefono`
  ADD CONSTRAINT `fk_datospersona_telefono` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`);

--
-- Filtros para la tabla `uso_promocion`
--
ALTER TABLE `uso_promocion`
  ADD CONSTRAINT `fk_uso_promocion_asignacionpromocion` FOREIGN KEY (`id_asignacion_promocion`) REFERENCES `asignacion_promocion` (`id_asignacion_promocion`),
  ADD CONSTRAINT `fk_uso_promocion_prestamo` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `fk_datospersona_usuario` FOREIGN KEY (`id_datos_persona`) REFERENCES `datos_persona` (`id_datos_persona`);

--
-- Filtros para la tabla `usuarios_roles`
--
ALTER TABLE `usuarios_roles`
  ADD CONSTRAINT `fk_rol_usuario` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `fk_usuario_roles` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

DELIMITER $$
--
-- Eventos
--
DROP EVENT IF EXISTS `evt_actualizar_mora`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_actualizar_mora` ON SCHEDULE EVERY 2 DAY STARTS '2025-11-07 23:53:01' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    DECLARE v_mora DECIMAL (5,2) DEFAULT 2.0; 

    UPDATE cronograma_cuota 
    SET estado_cuota = 'Vencida',
        cargos_cuota = ROUND(cargos_cuota + (saldo_cuota * (v_mora / 100)), 2)
    WHERE fecha_vencimiento < CURDATE()
        AND estado_cuota = 'Pendiente';
    
    UPDATE prestamo
    SET id_estado_prestamo = (
        SELECT id_estado_prestamo FROM cat_estado_prestamo WHERE estado = 'En mora' LIMIT 1
    )
    WHERE id_prestamo IN (
        SELECT DISTINCT id_prestamo
        FROM cronograma_cuota
        WHERE estado_cuota = 'Vencida'
    )
    AND id_prestamo NOT IN(
        SELECT id_prestamo
        FROM prestamo
        WHERE id_estado_prestamo = (
            SELECT id_estado_prestamo 
            FROM cat_estado_prestamo
            WHERE estado = 'Cerrado'
        )
    );
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
