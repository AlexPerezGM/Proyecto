-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 29-10-2025 a las 22:53:16
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignacion_pago`
--

DROP TABLE IF EXISTS `asignacion_pago`;
CREATE TABLE IF NOT EXISTS `asignacion_pago` (
  `id_asignacion_pago` int NOT NULL AUTO_INCREMENT,
  `id_pago` int DEFAULT NULL,
  `id_cronograma_cuota` int DEFAULT NULL,
  `monto_asignado` decimal(10,2) NOT NULL,
  `tipo_asignacion` enum('Capital','Interes','Cargos') NOT NULL,
  PRIMARY KEY (`id_asignacion_pago`),
  KEY `id_pago` (`id_pago`),
  KEY `id_cronograma_cuota` (`id_cronograma_cuota`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `asignacion_pago`
--

INSERT INTO `asignacion_pago` (`id_asignacion_pago`, `id_pago`, `id_cronograma_cuota`, `monto_asignado`, `tipo_asignacion`) VALUES
(1, 1, 5, 200.00, 'Interes'),
(2, 2, 7, 50.00, 'Interes');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_beneficio`
--

DROP TABLE IF EXISTS `cat_beneficio`;
CREATE TABLE IF NOT EXISTS `cat_beneficio` (
  `id_beneficio` int NOT NULL AUTO_INCREMENT,
  `tipo_beneficio` varchar(50) NOT NULL,
  PRIMARY KEY (`id_beneficio`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_beneficio`
--

INSERT INTO `cat_beneficio` (`id_beneficio`, `tipo_beneficio`) VALUES
(1, 'Seguro médico'),
(2, 'Bonificación'),
(3, 'Vacaciones pagadas'),
(4, 'Seguro médico'),
(5, 'Bonificación'),
(6, 'Vacaciones pagadas'),
(7, 'Seguro médico'),
(8, 'Bonificación'),
(9, 'Vacaciones pagadas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_estado_prestamo`
--

DROP TABLE IF EXISTS `cat_estado_prestamo`;
CREATE TABLE IF NOT EXISTS `cat_estado_prestamo` (
  `id_estado_prestamo` int NOT NULL AUTO_INCREMENT,
  `estado` varchar(50) NOT NULL,
  PRIMARY KEY (`id_estado_prestamo`)
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_estado_prestamo`
--

INSERT INTO `cat_estado_prestamo` (`id_estado_prestamo`, `estado`) VALUES
(1, 'Activo'),
(2, 'Pendiente'),
(3, 'Rechazado'),
(4, 'En mora'),
(5, 'Pagado'),
(6, 'Activo'),
(7, 'Pendiente'),
(8, 'Rechazado'),
(9, 'En mora'),
(10, 'Pagado'),
(11, 'Activo'),
(12, 'Pendiente'),
(13, 'Rechazado'),
(14, 'En mora'),
(15, 'Pagado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_genero`
--

DROP TABLE IF EXISTS `cat_genero`;
CREATE TABLE IF NOT EXISTS `cat_genero` (
  `id_genero` int NOT NULL AUTO_INCREMENT,
  `genero` varchar(50) NOT NULL,
  PRIMARY KEY (`id_genero`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_genero`
--

INSERT INTO `cat_genero` (`id_genero`, `genero`) VALUES
(1, 'Masculino'),
(2, 'Femenino'),
(3, 'Otro'),
(4, 'Masculino'),
(5, 'Femenino'),
(6, 'Otro'),
(7, 'Masculino'),
(8, 'Femenino'),
(9, 'Otro');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_nivel_riesgo`
--

DROP TABLE IF EXISTS `cat_nivel_riesgo`;
CREATE TABLE IF NOT EXISTS `cat_nivel_riesgo` (
  `id_nivel_riesgo` int NOT NULL AUTO_INCREMENT,
  `nivel` varchar(50) NOT NULL,
  PRIMARY KEY (`id_nivel_riesgo`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_nivel_riesgo`
--

INSERT INTO `cat_nivel_riesgo` (`id_nivel_riesgo`, `nivel`) VALUES
(1, 'Bajo'),
(2, 'Medio'),
(3, 'Alto'),
(4, 'Bajo'),
(5, 'Medio'),
(6, 'Alto'),
(7, 'Bajo'),
(8, 'Medio'),
(9, 'Alto');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_periodo_pago`
--

DROP TABLE IF EXISTS `cat_periodo_pago`;
CREATE TABLE IF NOT EXISTS `cat_periodo_pago` (
  `id_periodo_pago` int NOT NULL AUTO_INCREMENT,
  `periodo` varchar(50) NOT NULL,
  PRIMARY KEY (`id_periodo_pago`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_periodo_pago`
--

INSERT INTO `cat_periodo_pago` (`id_periodo_pago`, `periodo`) VALUES
(1, 'Semanal'),
(2, 'Quincenal'),
(3, 'Mensual'),
(4, 'Semanal'),
(5, 'Quincenal'),
(6, 'Mensual'),
(7, 'Semanal'),
(8, 'Quincenal'),
(9, 'Mensual');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_contrato`
--

DROP TABLE IF EXISTS `cat_tipo_contrato`;
CREATE TABLE IF NOT EXISTS `cat_tipo_contrato` (
  `id_tipo_contrato` int NOT NULL AUTO_INCREMENT,
  `tipo_contrato` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_contrato`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_contrato`
--

INSERT INTO `cat_tipo_contrato` (`id_tipo_contrato`, `tipo_contrato`) VALUES
(1, 'Medio tiempo'),
(2, 'Tiempo completo'),
(3, 'Pasantia'),
(4, 'Medio tiempo'),
(5, 'Tiempo completo'),
(6, 'Pasantia'),
(7, 'Medio tiempo'),
(8, 'Tiempo completo'),
(9, 'Pasantia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_deduccion`
--

DROP TABLE IF EXISTS `cat_tipo_deduccion`;
CREATE TABLE IF NOT EXISTS `cat_tipo_deduccion` (
  `id_tipo_deduccion` int NOT NULL AUTO_INCREMENT,
  `tipo_deduccion` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_deduccion`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_deduccion`
--

INSERT INTO `cat_tipo_deduccion` (`id_tipo_deduccion`, `tipo_deduccion`) VALUES
(1, 'ISR'),
(2, 'AFP'),
(3, 'SFS'),
(4, 'ISR'),
(5, 'AFP'),
(6, 'SFS'),
(7, 'ISR'),
(8, 'AFP'),
(9, 'SFS');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_documento`
--

DROP TABLE IF EXISTS `cat_tipo_documento`;
CREATE TABLE IF NOT EXISTS `cat_tipo_documento` (
  `id_tipo_documento` int NOT NULL AUTO_INCREMENT,
  `tipo_documento` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_documento`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_documento`
--

INSERT INTO `cat_tipo_documento` (`id_tipo_documento`, `tipo_documento`) VALUES
(1, 'Cedula'),
(2, 'Pasaporte'),
(3, 'Licencia de conducir'),
(4, 'Cedula'),
(5, 'Pasaporte'),
(6, 'Licencia de conducir'),
(7, 'Cedula'),
(8, 'Pasaporte'),
(9, 'Licencia de conducir');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_entidad`
--

DROP TABLE IF EXISTS `cat_tipo_entidad`;
CREATE TABLE IF NOT EXISTS `cat_tipo_entidad` (
  `id_tipo_entidad` int NOT NULL AUTO_INCREMENT,
  `tipo_entidad` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_entidad`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_entidad`
--

INSERT INTO `cat_tipo_entidad` (`id_tipo_entidad`, `tipo_entidad`) VALUES
(1, 'Cliente'),
(2, 'Empleado'),
(3, 'Propiedad'),
(4, 'Cliente'),
(5, 'Empleado'),
(6, 'Propiedad'),
(7, 'Cliente'),
(8, 'Empleado'),
(9, 'Propiedad');

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
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_moneda`
--

INSERT INTO `cat_tipo_moneda` (`id_tipo_moneda`, `tipo_moneda`, `valor`) VALUES
(1, 'DOP', 1.00),
(2, 'USD', 62.97),
(3, 'DOP', 1.00),
(4, 'USD', 62.97),
(5, 'DOP', 1.00),
(6, 'USD', 62.97);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_pago`
--

DROP TABLE IF EXISTS `cat_tipo_pago`;
CREATE TABLE IF NOT EXISTS `cat_tipo_pago` (
  `id_tipo_pago` int NOT NULL AUTO_INCREMENT,
  `tipo_pago` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_pago`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_pago`
--

INSERT INTO `cat_tipo_pago` (`id_tipo_pago`, `tipo_pago`) VALUES
(1, 'Efectivo'),
(2, 'Transferencia'),
(3, 'Cheque'),
(4, 'Efectivo'),
(5, 'Transferencia'),
(6, 'Cheque'),
(7, 'Efectivo'),
(8, 'Transferencia'),
(9, 'Cheque');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cat_tipo_promocion`
--

DROP TABLE IF EXISTS `cat_tipo_promocion`;
CREATE TABLE IF NOT EXISTS `cat_tipo_promocion` (
  `id_tipo_promocion` int NOT NULL AUTO_INCREMENT,
  `tipo_promocion` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_promocion`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cat_tipo_promocion`
--

INSERT INTO `cat_tipo_promocion` (`id_tipo_promocion`, `tipo_promocion`) VALUES
(1, 'Descuento'),
(2, 'Promoción'),
(3, 'Tasa interes mas baja'),
(4, 'Descuento'),
(5, 'Promoción'),
(6, 'Tasa interes mas baja'),
(7, 'Descuento'),
(8, 'Promoción'),
(9, 'Tasa interes mas baja');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--

DROP TABLE IF EXISTS `cliente`;
CREATE TABLE IF NOT EXISTS `cliente` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  KEY `id_datos_persona` (`id_datos_persona`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cliente`
--

INSERT INTO `cliente` (`id_cliente`, `id_datos_persona`) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 5),
(6, 6);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `condicion_prestamo`
--

DROP TABLE IF EXISTS `condicion_prestamo`;
CREATE TABLE IF NOT EXISTS `condicion_prestamo` (
  `id_condicion_prestamo` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `tasa_interes` decimal(5,2) NOT NULL,
  `tipo_interes` varchar(50) NOT NULL,
  `tipo_amortizacion` varchar(50) NOT NULL,
  `id_periodo_pago` int DEFAULT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `esta_activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_condicion_prestamo`),
  KEY `id_prestamo` (`id_prestamo`),
  KEY `id_periodo_pago` (`id_periodo_pago`)
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
  `capital_cuota` decimal(10,2) NOT NULL,
  `interes_cuota` decimal(10,2) NOT NULL,
  `cargos_cuota` decimal(10,2) DEFAULT '0.00',
  `total_monto` decimal(10,2) NOT NULL,
  `saldo_pendiente` decimal(10,2) NOT NULL,
  `estado_cuota` enum('Pendiente','Pagada','Vencida') DEFAULT 'Pendiente',
  PRIMARY KEY (`id_cronograma_cuota`),
  KEY `id_prestamo` (`id_prestamo`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cronograma_cuota`
--

INSERT INTO `cronograma_cuota` (`id_cronograma_cuota`, `id_prestamo`, `numero_cuota`, `fecha_vencimiento`, `capital_cuota`, `interes_cuota`, `cargos_cuota`, `total_monto`, `saldo_pendiente`, `estado_cuota`) VALUES
(1, 1, 1, '2025-10-26', 3000.00, 200.00, 0.00, 3200.00, 3200.00, 'Pendiente'),
(2, 1, 2, '2025-10-28', 3000.00, 200.00, 0.00, 3200.00, 3200.00, 'Pendiente'),
(3, 2, 3, '2025-10-15', 7000.00, 500.00, 100.00, 7600.00, 7600.00, 'Vencida'),
(4, 2, 4, '2025-10-20', 7000.00, 500.00, 100.00, 7600.00, 7600.00, 'Vencida'),
(5, 3, 5, '2025-10-10', 2500.00, 200.00, 0.00, 2700.00, 0.00, 'Pagada'),
(6, 4, 1, '2025-11-05', 900.00, 100.00, 0.00, 1000.00, 1000.00, 'Pendiente'),
(7, 5, 8, '2025-09-30', 450.00, 50.00, 0.00, 500.00, 0.00, 'Pagada');

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
  KEY `genero` (`genero`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `datos_persona`
--

INSERT INTO `datos_persona` (`id_datos_persona`, `nombre`, `apellido`, `fecha_nacimiento`, `genero`, `estado_cliente`, `creado_en`, `actualizado_en`) VALUES
(1, 'Juan', 'Pérez', '1985-04-10', 1, 'Activo', '2025-09-15 14:00:00', '2025-10-27 00:13:54'),
(2, 'María', 'Gómez', '1990-07-02', 2, 'Activo', '2025-07-10 13:30:00', '2025-10-27 00:13:54'),
(3, 'Carlos', 'Ruiz', '1982-12-01', 1, 'Activo', '2025-05-03 15:00:00', '2025-10-27 00:13:54'),
(4, 'Ana', 'Torres', '1995-03-14', 2, 'Activo', '2025-03-14 18:00:00', '2025-10-27 00:13:54'),
(5, 'Luis', 'Fernández', '1978-01-22', 1, 'Activo', '2025-01-10 20:30:00', '2025-10-27 00:13:54'),
(6, 'Sofía', 'Martínez', '1999-11-30', 2, 'Activo', '2025-10-25 13:00:00', '2025-10-27 00:13:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `desembolso`
--

DROP TABLE IF EXISTS `desembolso`;
CREATE TABLE IF NOT EXISTS `desembolso` (
  `id_desembolso` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `monto_desembolsado` decimal(10,2) NOT NULL,
  `fecha_desembolso` date NOT NULL,
  `metodo_entrega` int DEFAULT NULL,
  PRIMARY KEY (`id_desembolso`),
  KEY `id_prestamo` (`id_prestamo`),
  KEY `metodo_entrega` (`metodo_entrega`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  KEY `id_evaluacion_prestamo` (`id_evaluacion_prestamo`),
  KEY `evaluado_por` (`evaluado_por`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `es_principal` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_direccion`),
  KEY `id_datos_persona` (`id_datos_persona`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `direccion_entidad`
--

DROP TABLE IF EXISTS `direccion_entidad`;
CREATE TABLE IF NOT EXISTS `direccion_entidad` (
  `id_direccion_entidad` int NOT NULL AUTO_INCREMENT,
  `id_direccion` int DEFAULT NULL,
  `id_tipo_entidad` int DEFAULT NULL,
  `tipo_direccion` varchar(50) NOT NULL,
  PRIMARY KEY (`id_direccion_entidad`),
  KEY `id_direccion` (`id_direccion`),
  KEY `id_tipo_entidad` (`id_tipo_entidad`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentacion_cliente`
--

DROP TABLE IF EXISTS `documentacion_cliente`;
CREATE TABLE IF NOT EXISTS `documentacion_cliente` (
  `id_documentacion_cliente` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_tipo_documento` int DEFAULT NULL,
  `ruta_documento` varchar(255) NOT NULL,
  PRIMARY KEY (`id_documentacion_cliente`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_tipo_documento` (`id_tipo_documento`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  KEY `id_datos_persona` (`id_datos_persona`),
  KEY `id_tipo_documento` (`id_tipo_documento`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `email`
--

DROP TABLE IF EXISTS `email`;
CREATE TABLE IF NOT EXISTS `email` (
  `id_email` int NOT NULL AUTO_INCREMENT,
  `id_datos_persona` int DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `es_principal` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_email`),
  KEY `id_datos_persona` (`id_datos_persona`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluacion_prestamo`
--

DROP TABLE IF EXISTS `evaluacion_prestamo`;
CREATE TABLE IF NOT EXISTS `evaluacion_prestamo` (
  `id_evaluacion_prestamo` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_prestamo` int DEFAULT NULL,
  `capacidad_pago` decimal(10,2) NOT NULL,
  `nivel_riesgo` int DEFAULT NULL,
  `estado_evaluacion` enum('Aprobado','Rechazado','Pendiente') NOT NULL,
  `fecha_evaluacion` date NOT NULL,
  PRIMARY KEY (`id_evaluacion_prestamo`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_prestamo` (`id_prestamo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fondos`
--

DROP TABLE IF EXISTS `fondos`;
CREATE TABLE IF NOT EXISTS `fondos` (
  `id_fondo` int NOT NULL AUTO_INCREMENT,
  `cartera_normal` decimal(15,2) NOT NULL,
  `cartera_vencida` decimal(15,2) NOT NULL,
  `total_fondos` decimal(15,2) NOT NULL,
  `actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_fondo`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `fondos`
--

INSERT INTO `fondos` (`id_fondo`, `cartera_normal`, `cartera_vencida`, `total_fondos`, `actualizacion`) VALUES
(1, 50000.00, 8000.00, 58000.00, '2025-10-27 00:13:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fuente_ingreso`
--

DROP TABLE IF EXISTS `fuente_ingreso`;
CREATE TABLE IF NOT EXISTS `fuente_ingreso` (
  `id_fuente_ingreso` int NOT NULL AUTO_INCREMENT,
  `id_ingresos_egresos` int DEFAULT NULL,
  `fuente` varchar(100) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_fuente_ingreso`),
  KEY `id_ingresos_egresos` (`id_ingresos_egresos`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `garantia`
--

DROP TABLE IF EXISTS `garantia`;
CREATE TABLE IF NOT EXISTS `garantia` (
  `id_garantia` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `id_cliente` int DEFAULT NULL,
  PRIMARY KEY (`id_garantia`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_prestamo` (`id_prestamo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingresos_egresos`
--

DROP TABLE IF EXISTS `ingresos_egresos`;
CREATE TABLE IF NOT EXISTS `ingresos_egresos` (
  `id_ingresos_egresos` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `ingresos_mensuales` decimal(10,2) NOT NULL,
  `egresos_mensuales` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_ingresos_egresos`),
  KEY `id_cliente` (`id_cliente`)
) ;

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
  KEY `id_datos_persona` (`id_datos_persona`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago`
--

DROP TABLE IF EXISTS `pago`;
CREATE TABLE IF NOT EXISTS `pago` (
  `id_pago` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int NOT NULL,
  `fecha_pago` date NOT NULL,
  `monto_pagado` decimal(10,2) NOT NULL,
  `metodo_pago` int DEFAULT NULL,
  `id_tipo_moneda` int DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pago`),
  KEY `fk_pago_prestamo` (`id_prestamo`),
  KEY `fk_pago_tipopago` (`metodo_pago`),
  KEY `fk_pago_moneda` (`id_tipo_moneda`),
  KEY `fk_pago_usuario` (`creado_por`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `pago`
--

INSERT INTO `pago` (`id_pago`, `id_prestamo`, `fecha_pago`, `monto_pagado`, `metodo_pago`, `id_tipo_moneda`, `creado_por`, `creado_en`) VALUES
(1, 3, '2025-10-10', 2700.00, 1, 1, 1, '2025-10-27 00:13:54'),
(2, 5, '2025-09-30', 500.00, 2, 1, 1, '2025-10-27 00:13:54');

--
-- Disparadores `pago`
--
DROP TRIGGER IF EXISTS `trg_pago_fecha`;
DELIMITER $$
CREATE TRIGGER `trg_pago_fecha` BEFORE INSERT ON `pago` FOR EACH ROW BEGIN
  IF NEW.fecha_pago > CURRENT_DATE() THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'fecha_pago no puede ser futura';
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_pago_fecha_upd`;
DELIMITER $$
CREATE TRIGGER `trg_pago_fecha_upd` BEFORE UPDATE ON `pago` FOR EACH ROW BEGIN
  IF NEW.fecha_pago > CURRENT_DATE() THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'fecha_pago no puede ser futura (update)';
  END IF;
END
$$
DELIMITER ;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamo`
--

DROP TABLE IF EXISTS `prestamo`;
CREATE TABLE IF NOT EXISTS `prestamo` (
  `id_prestamo` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_tipo_prestamo` int DEFAULT NULL,
  `monto_solicitado` decimal(10,2) NOT NULL,
  `fecha_solicitud` date NOT NULL,
  `fecha_otrogamiento` date DEFAULT NULL,
  `plazo_meses` int NOT NULL,
  `id_estado_prestamo` int DEFAULT NULL,
  `id_condicion_actual` int DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_prestamo`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_tipo_prestamo` (`id_tipo_prestamo`),
  KEY `id_estado_prestamo` (`id_estado_prestamo`),
  KEY `creado_por` (`creado_por`),
  KEY `fk_prestamo_condicion` (`id_condicion_actual`)
) ;

--
-- Volcado de datos para la tabla `prestamo`
--

INSERT INTO `prestamo` (`id_prestamo`, `id_cliente`, `id_tipo_prestamo`, `monto_solicitado`, `fecha_solicitud`, `fecha_otrogamiento`, `plazo_meses`, `id_estado_prestamo`, `id_condicion_actual`, `creado_por`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 1, 5000.00, '2025-09-20', '2025-09-21', 12, 1, NULL, 1, '2025-09-21 13:00:00', '2025-10-01 14:00:00'),
(2, 2, 1, 8000.00, '2025-07-12', '2025-07-12', 10, 4, NULL, 1, '2025-07-12 14:00:00', '2025-10-20 14:00:00'),
(3, 3, 1, 3000.00, '2025-06-01', '2025-06-02', 6, 1, NULL, 1, '2025-06-02 13:00:00', '2025-10-10 14:00:00'),
(4, 4, 2, 15000.00, '2025-09-01', '2025-09-02', 24, 1, NULL, 1, '2025-09-02 13:00:00', '2025-10-01 14:00:00'),
(5, 5, 1, 4000.00, '2025-02-01', '2025-02-01', 8, 5, NULL, 1, '2025-02-01 13:00:00', '2025-09-30 22:00:00'),
(6, 6, 1, 2500.00, '2025-10-25', NULL, 12, 2, NULL, 1, '2025-10-25 13:30:00', '2025-10-25 13:30:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos_hipotecario`
--

DROP TABLE IF EXISTS `prestamos_hipotecario`;
CREATE TABLE IF NOT EXISTS `prestamos_hipotecario` (
  `id_prestamo_hipotecario` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `valor_propiedad` decimal(10,2) NOT NULL,
  `porcentaje_financiamiento` decimal(5,2) NOT NULL,
  `direccion_propiedad` varchar(255) NOT NULL,
  PRIMARY KEY (`id_prestamo_hipotecario`),
  KEY `id_prestamo` (`id_prestamo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamo_propiedad`
--

DROP TABLE IF EXISTS `prestamo_propiedad`;
CREATE TABLE IF NOT EXISTS `prestamo_propiedad` (
  `id_prestamo_propiedad` int NOT NULL AUTO_INCREMENT,
  `id_prestamo` int DEFAULT NULL,
  `id_propiedad` int DEFAULT NULL,
  `porcentaje_garantia` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id_prestamo_propiedad`),
  KEY `id_prestamo` (`id_prestamo`),
  KEY `id_propiedad` (`id_propiedad`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `propiedades`
--

DROP TABLE IF EXISTS `propiedades`;
CREATE TABLE IF NOT EXISTS `propiedades` (
  `id_propiedad` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_direccion` int DEFAULT NULL,
  `area_m2` decimal(10,2) NOT NULL,
  `valor_propiedad` decimal(10,2) NOT NULL,
  `fecha_registro` date NOT NULL,
  PRIMARY KEY (`id_propiedad`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_direccion` (`id_direccion`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `puntaje_crediticio`
--

DROP TABLE IF EXISTS `puntaje_crediticio`;
CREATE TABLE IF NOT EXISTS `puntaje_crediticio` (
  `id_puntaje_crediciticio` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_nivel_riesgo` int DEFAULT NULL,
  `puntaje` int DEFAULT '0',
  `total_transacciones` int NOT NULL,
  PRIMARY KEY (`id_puntaje_crediciticio`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_nivel_riesgo` (`id_nivel_riesgo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `monto_usado` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_retiro_garantia`),
  KEY `id_pago` (`id_pago`),
  KEY `id_garantia` (`id_garantia`)
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
  KEY `id_datos_persona` (`id_datos_persona`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_prestamo`
--

DROP TABLE IF EXISTS `tipo_prestamo`;
CREATE TABLE IF NOT EXISTS `tipo_prestamo` (
  `id_tipo_prestamo` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tasa_interes` decimal(5,2) NOT NULL,
  `monto_minimo` decimal(10,2) NOT NULL,
  `tipo_amortizacion` varchar(50) NOT NULL,
  `plazo_minimo_meses` int NOT NULL,
  `plazo_maximo_meses` int NOT NULL,
  PRIMARY KEY (`id_tipo_prestamo`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `tipo_prestamo`
--

INSERT INTO `tipo_prestamo` (`id_tipo_prestamo`, `nombre`, `tasa_interes`, `monto_minimo`, `tipo_amortizacion`, `plazo_minimo_meses`, `plazo_maximo_meses`) VALUES
(1, 'Personal', 10.00, 1000.00, 'Frances', 6, 24),
(2, 'Comercial', 8.50, 5000.00, 'Frances', 12, 36);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

DROP TABLE IF EXISTS `usuario`;
CREATE TABLE IF NOT EXISTS `usuario` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `nombre_usuario` varchar(50) DEFAULT NULL,
  `contrasena` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_usuario`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nombre_usuario`, `contrasena`) VALUES
(1, 'admin', 'admin123');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
