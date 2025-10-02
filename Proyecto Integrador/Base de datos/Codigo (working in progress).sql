CREATE DATABASE IF NOT EXISTS sistema_prestamos DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_prestamos;

CREATE TABLE clientes (
  id_cliente BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  apellido VARCHAR(150) NOT NULL,
  edad TINYINT UNSIGNED,
  documento_identidad VARCHAR(50) NOT NULL UNIQUE,
  direccion VARCHAR(300),
  telefono VARCHAR(50),
  email VARCHAR(255) NOT NULL UNIQUE,
  estado ENUM('activo','inactivo','eliminado') NOT NULL DEFAULT 'activo',
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ocupacion VARCHAR(150)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ingresos_egresos (
  id_ingreso_egreso BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED NOT NULL,
  ingresos_mensuales DECIMAL(15,2) DEFAULT 0.00,
  egresos_mensuales DECIMAL(15,2) DEFAULT 0.00,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_cliente),
  CONSTRAINT fk_ingresos_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historial_clientes (
  id_historial BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED NOT NULL,
  campo_modificado VARCHAR(150) NOT NULL,
  valor_anterior TEXT,
  valor_nuevo TEXT,
  fecha_cambio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_responsable BIGINT UNSIGNED,
  INDEX (id_cliente),
  CONSTRAINT fk_historial_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expedientes (
  id_expediente BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado_expediente VARCHAR(100),
  INDEX (id_cliente),
  CONSTRAINT fk_expediente_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historial_crediticio (
  id_historial_crediticio BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_expediente BIGINT UNSIGNED NOT NULL,
  detalle TEXT,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_expediente),
  CONSTRAINT fk_histcred_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes(id_expediente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documentos_clientes (
  id_documento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED NOT NULL,
  tipo_documento VARCHAR(100),
  ruta_archivo VARCHAR(500) NOT NULL,
  fecha_subida TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_cliente),
  CONSTRAINT fk_documento_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE prestamos (
  id_prestamo BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED NOT NULL,
  tipo_prestamo ENUM('personal','hipotecario','otro') NOT NULL DEFAULT 'personal',
  plazo_meses SMALLINT UNSIGNED,
  frecuencia_pagos ENUM('mensual','quincenal','semanal') DEFAULT 'mensual',
  estado ENUM('pendiente','aprobado','activo','moroso','cerrado') DEFAULT 'pendiente',
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_cliente),
  CONSTRAINT fk_prestamo_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prestamos_personales (
  id_prestamo_personal BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED NOT NULL,
  contrato_digital VARCHAR(500),
  monto_solicitado DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  monto_aprobado DECIMAL(18,2) DEFAULT 0.00,
  tasa_interes DECIMAL(6,4),
  tipo_interes ENUM('simple','compuesto') DEFAULT 'simple',
  fecha_inicio DATE,
  fecha_fin DATE,
  INDEX (id_prestamo),
  CONSTRAINT fk_personal_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE garantias (
  id_garantia BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo_hipotecario BIGINT UNSIGNED,
  tipo_garantia VARCHAR(100),
  descripcion TEXT,
  valor_garantia DECIMAL(18,2) DEFAULT 0.00,
  estado_garantia ENUM('activa','ejecutada','liberada') DEFAULT 'activa',
  INDEX (id_prestamo_hipotecario),
  CONSTRAINT fk_garantia_hipoteca FOREIGN KEY (id_prestamo_hipotecario) REFERENCES prestamos_hipotecarios(id_prestamo_hipotecario) -- se crea después de la tabla prestamos_hipotecarios
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prestamos_hipotecarios (
  id_prestamo_hipotecario BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED NOT NULL,
  id_garantia BIGINT UNSIGNED,
  direccion_inmueble VARCHAR(400),
  valor_inmueble DECIMAL(18,2),
  contrato_digital VARCHAR(500),
  monto_solicitado DECIMAL(18,2) DEFAULT 0.00,
  tasa_interes DECIMAL(6,4),
  fecha_inicio DATE,
  fecha_fin DATE,
  INDEX (id_prestamo),
  INDEX (id_garantia),
  CONSTRAINT fk_hipoteca_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE
  -- fk a garantias lo manejamos desde la tabla garantias (id_prestamo_hipotecario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- actualizar FK en garantias ahora que prestamos_hipotecarios existe:
ALTER TABLE garantias
  ADD CONSTRAINT fk_garantia_hipoteca FOREIGN KEY (id_prestamo_hipotecario) REFERENCES prestamos_hipotecarios(id_prestamo_hipotecario) ON DELETE SET NULL ON UPDATE CASCADE;


CREATE TABLE cronogramas_pago (
  id_cuota BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED NOT NULL,
  numero_cuota INT UNSIGNED NOT NULL,
  fecha_pago_programada DATE,
  monto_cuota DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  monto_interes DECIMAL(18,2) DEFAULT 0.00,
  monto_capital DECIMAL(18,2) DEFAULT 0.00,
  estado_cuota ENUM('pendiente','pagada','vencida','renegociada') DEFAULT 'pendiente',
  INDEX (id_prestamo),
  CONSTRAINT fk_cronograma_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagos (
  id_pago BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cuota BIGINT UNSIGNED,
  id_prestamo BIGINT UNSIGNED,
  fecha_pago_real TIMESTAMP,
  monto_pagado DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  tipo_pago ENUM('efectivo','transferencia','garantia','otro') DEFAULT 'efectivo',
  estado_pago ENUM('pendiente_validacion','confirmado','rechazado') DEFAULT 'pendiente_validacion',
  metodo_pago ENUM('efectivo','transferencia','tarjeta','cheque','garantia','otro') DEFAULT 'transferencia',
  comprobante VARCHAR(500),
  referencia_pago VARCHAR(200),
  observaciones TEXT,
  INDEX (id_cuota),
  INDEX (id_prestamo),
  CONSTRAINT fk_pago_cuota FOREIGN KEY (id_cuota) REFERENCES cronogramas_pago(id_cuota) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pago_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluaciones_credito (
  id_evaluacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED NOT NULL,
  id_prestamo BIGINT UNSIGNED,
  capacidad_pago DECIMAL(18,2),
  resultado ENUM('aprobado','rechazado','en_revision') DEFAULT 'en_revision',
  observaciones TEXT,
  fecha_evaluacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_cliente),
  INDEX (id_prestamo),
  CONSTRAINT fk_eval_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_eval_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE desembolsos (
  id_desembolso BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED NOT NULL,
  monto_desembolsado DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  fecha_desembolso TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metodo_entrega ENUM('efectivo','transferencia','cheque','otro') DEFAULT 'transferencia',
  INDEX (id_prestamo),
  CONSTRAINT fk_desembolso_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contratos (
  id_contrato BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED NOT NULL,
  tipo_contrato ENUM('personal','hipotecario','otro') DEFAULT 'personal',
  ruta_archivo VARCHAR(500),
  fecha_generacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_prestamo),
  CONSTRAINT fk_contrato_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE moras (
  id_mora BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED NOT NULL,
  id_cuota BIGINT UNSIGNED,
  monto_mora DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  fecha_generacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('pendiente','pagada') DEFAULT 'pendiente',
  INDEX (id_prestamo),
  INDEX (id_cuota),
  CONSTRAINT fk_mora_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_mora_cuota FOREIGN KEY (id_cuota) REFERENCES cronogramas_pago(id_cuota) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE pagos_efectivo (
  id_pago_efectivo BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_pago BIGINT UNSIGNED NOT NULL,
  monto_entregado DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  tipo_moneda VARCHAR(10),
  validacion ENUM('valido','rechazado') DEFAULT 'valido',
  INDEX (id_pago),
  CONSTRAINT fk_pagoefectivo_pago FOREIGN KEY (id_pago) REFERENCES pagos(id_pago) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagos_transferencias (
  id_pago_transferencia BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_pago BIGINT UNSIGNED NOT NULL,
  numero_referencia VARCHAR(200),
  monto_transferido DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  fecha_acreditacion TIMESTAMP,
  estado_validacion ENUM('pendiente','confirmado','rechazado') DEFAULT 'pendiente',
  INDEX (id_pago),
  CONSTRAINT fk_pagotrans_pago FOREIGN KEY (id_pago) REFERENCES pagos(id_pago) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagos_garantias (
  id_pago_garantia BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_pago BIGINT UNSIGNED NOT NULL,
  id_garantia BIGINT UNSIGNED NOT NULL,
  fecha_uso TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  monto_cubierto DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  INDEX (id_pago),
  INDEX (id_garantia),
  CONSTRAINT fk_pagogar_pago FOREIGN KEY (id_pago) REFERENCES pagos(id_pago) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pagogar_garantia FOREIGN KEY (id_garantia) REFERENCES garantias(id_garantia) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE facturas (
  id_factura BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_pago BIGINT UNSIGNED NOT NULL,
  numero_factura VARCHAR(100) NOT NULL UNIQUE,
  fecha_emision DATE NOT NULL,
  monto_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  estado_factura ENUM('emitida','anulada') DEFAULT 'emitida',
  INDEX (id_pago),
  CONSTRAINT fk_factura_pago FOREIGN KEY (id_pago) REFERENCES pagos(id_pago) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cierres_prestamos (
  id_cierre BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED NOT NULL,
  fecha_cierre TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  monto_total_pagado DECIMAL(18,2) DEFAULT 0.00,
  estado_cierre ENUM('cerrado','cancelado_anticipado') DEFAULT 'cerrado',
  observaciones TEXT,
  INDEX (id_prestamo),
  CONSTRAINT fk_cierre_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE configuracion_notificaciones (
  id_notificacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED,
  tipo_notificacion VARCHAR(100),
  destinatario VARCHAR(150),
  estado ENUM('pendiente','enviada') DEFAULT 'pendiente',
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_prestamo),
  CONSTRAINT fk_confignot_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE estados_prestamo (
  id_estado_prestamo BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED NOT NULL,
  estado ENUM('normal','moroso','refinanciamiento','cerrado') NOT NULL,
  fecha_inicio DATE,
  fecha_fin DATE,
  observaciones TEXT,
  INDEX (id_prestamo),
  CONSTRAINT fk_estadoprest_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refinanciamientos (
  id_refinanciamiento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED NOT NULL,
  causa TEXT,
  nueva_tasa_interes DECIMAL(6,4),
  nuevo_plazo_meses SMALLINT UNSIGNED,
  nuevo_monto_cuota DECIMAL(18,2),
  estado_refinanciamiento ENUM('solicitado','aprobado','rechazado') DEFAULT 'solicitado',
  fecha_solicitud TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_aprobacion TIMESTAMP NULL,
  INDEX (id_prestamo),
  CONSTRAINT fk_refi_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendarios_pago (
  id_calendario BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tipo_calendario ENUM('inicial','recalculado','refinanciamiento') DEFAULT 'inicial',
  fecha_recalculo TIMESTAMP NULL,
  id_prestamo BIGINT UNSIGNED,
  INDEX (id_prestamo),
  CONSTRAINT fk_calendario_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avisos_clientes (
  id_aviso BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_prestamo BIGINT UNSIGNED,
  id_cliente BIGINT UNSIGNED,
  tipo_aviso VARCHAR(100),
  plantilla_mensaje TEXT,
  medio ENUM('SMS','email','carta','llamada','interno') DEFAULT 'email',
  estado_envio ENUM('enviado','pendiente','fallido') DEFAULT 'pendiente',
  fecha_envio TIMESTAMP NULL,
  INDEX (id_prestamo),
  INDEX (id_cliente),
  CONSTRAINT fk_aviso_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_aviso_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE reportes (
  id_reporte BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tipo_reporte VARCHAR(150),
  parametros JSON,
  fecha_generacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_genero BIGINT UNSIGNED,
  ruta_archivo VARCHAR(500),
  CONSTRAINT chk_reportes_parametros CHECK (JSON_VALID(parametros))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reportes_prestamos (
  id_reporte_prestamo BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_reporte BIGINT UNSIGNED NOT NULL,
  id_prestamo BIGINT UNSIGNED,
  estado ENUM('normal','moroso','cerrado'),
  saldo_pendiente DECIMAL(18,2) DEFAULT 0.00,
  total_pagado DECIMAL(18,2) DEFAULT 0.00,
  INDEX (id_reporte),
  INDEX (id_prestamo),
  CONSTRAINT fk_rp_reporte FOREIGN KEY (id_reporte) REFERENCES reportes(id_reporte) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rp_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reportes_clientes (
  id_reporte_cliente BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_reporte BIGINT UNSIGNED NOT NULL,
  id_cliente BIGINT UNSIGNED,
  cantidad_prestamos INT DEFAULT 0,
  total_deuda DECIMAL(18,2) DEFAULT 0.00,
  estado_cliente ENUM('activo','moroso','cancelado'),
  INDEX (id_reporte),
  INDEX (id_cliente),
  CONSTRAINT fk_rc_reporte FOREIGN KEY (id_reporte) REFERENCES reportes(id_reporte) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rc_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reportes_financieros (
  id_reporte_financiero BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_reporte BIGINT UNSIGNED NOT NULL,
  ingresos_intereses DECIMAL(18,2) DEFAULT 0.00,
  prestamos_otorgados DECIMAL(18,2) DEFAULT 0.00,
  prestamos_pagados DECIMAL(18,2) DEFAULT 0.00,
  balance_general DECIMAL(18,2) DEFAULT 0.00,
  INDEX (id_reporte),
  CONSTRAINT fk_rf_reporte FOREIGN KEY (id_reporte) REFERENCES reportes(id_reporte) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reportes_comparativos (
  id_reporte_comparativo BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_reporte BIGINT UNSIGNED NOT NULL,
  total_activos DECIMAL(18,2) DEFAULT 0.00,
  total_morosos DECIMAL(18,2) DEFAULT 0.00,
  porcentaje_morosidad DECIMAL(5,2) DEFAULT 0.00,
  INDEX (id_reporte),
  CONSTRAINT fk_rcmp_reporte FOREIGN KEY (id_reporte) REFERENCES reportes(id_reporte) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE roles (
  id_rol BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre_rol VARCHAR(100) NOT NULL UNIQUE,
  descripcion TEXT,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
  id_permiso BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre_permiso VARCHAR(150) NOT NULL UNIQUE,
  descripcion TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles_permisos (
  id_rol_permiso BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_rol BIGINT UNSIGNED NOT NULL,
  id_permiso BIGINT UNSIGNED NOT NULL,
  INDEX (id_rol),
  INDEX (id_permiso),
  CONSTRAINT fk_rp_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (id_permiso) REFERENCES permisos(id_permiso) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
  id_usuario BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre_usuario VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nombre_completo VARCHAR(250),
  email VARCHAR(255) UNIQUE,
  telefono VARCHAR(50),
  id_rol BIGINT UNSIGNED,
  estado ENUM('activo','bloqueado','eliminado') DEFAULT 'activo',
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso TIMESTAMP NULL,
  INDEX (id_rol),
  CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios_historial_acceso (
  id_historial BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NOT NULL,
  accion VARCHAR(150),
  ip_origen VARCHAR(100),
  fecha_evento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_admin_responsable BIGINT UNSIGNED,
  INDEX (id_usuario),
  CONSTRAINT fk_uh_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_uh_admin FOREIGN KEY (usuario_admin_responsable) REFERENCES usuarios(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE puntaje_crediticio (
  id_puntaje BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED NOT NULL,
  puntaje_actual INT DEFAULT 0,
  total_transacciones INT DEFAULT 0,
  nivel_riesgo ENUM('bajo','medio','alto') DEFAULT 'medio',
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_cliente),
  CONSTRAINT fk_puntaje_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historial_puntaje (
  id_historial_puntaje BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED NOT NULL,
  id_prestamo BIGINT UNSIGNED,
  id_pago BIGINT UNSIGNED,
  variacion INT NOT NULL,
  puntaje_resultante INT NOT NULL,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_cliente),
  INDEX (id_prestamo),
  INDEX (id_pago),
  CONSTRAINT fk_hp_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_hp_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_hp_pago FOREIGN KEY (id_pago) REFERENCES pagos(id_pago) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notificaciones_pago (
  id_notificacion_pago BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED,
  id_prestamo BIGINT UNSIGNED,
  id_cuota BIGINT UNSIGNED,
  tipo_notificacion VARCHAR(150),
  mensaje TEXT,
  medio ENUM('SMS','email','llamada') DEFAULT 'email',
  estado_envio ENUM('pendiente','enviado','fallido') DEFAULT 'pendiente',
  fecha_generacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_envio TIMESTAMP NULL,
  INDEX (id_cliente),
  INDEX (id_prestamo),
  INDEX (id_cuota),
  CONSTRAINT fk_notp_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_notp_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_notp_cuota FOREIGN KEY (id_cuota) REFERENCES cronogramas_pago(id_cuota) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plantillas_notificaciones (
  id_plantilla BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(100),
  asunto VARCHAR(255),
  contenido TEXT,
  medio_predeterminado ENUM('email','SMS') DEFAULT 'email',
  estado ENUM('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE empleados (
  id_empleado BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  apellido VARCHAR(150) NOT NULL,
  edad TINYINT UNSIGNED,
  documento_identidad VARCHAR(50) UNIQUE,
  fecha_contratacion DATE,
  tipo_contrato VARCHAR(100),
  estado ENUM('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asistencias (
  id_asistencia BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_empleado BIGINT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  hora_entrada TIME,
  hora_salida TIME,
  estado_asistencia ENUM('puntual','retraso','ausencia','justificado') DEFAULT 'puntual',
  INDEX (id_empleado),
  CONSTRAINT fk_asistencia_empleado FOREIGN KEY (id_empleado) REFERENCES empleados(id_empleado) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nominas (
  id_nomina BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_empleado BIGINT UNSIGNED NOT NULL,
  sueldo_base DECIMAL(18,2),
  horas_extras DECIMAL(10,2),
  descuentos DECIMAL(18,2),
  deducciones_legales DECIMAL(18,2),
  deducciones_adicionales DECIMAL(18,2),
  beneficios DECIMAL(18,2),
  sueldo_neto DECIMAL(18,2),
  periodo_pago VARCHAR(50),
  fecha_generacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_empleado),
  CONSTRAINT fk_nomina_empleado FOREIGN KEY (id_empleado) REFERENCES empleados(id_empleado) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reportes_nomina (
  id_reporte_nomina BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_nomina BIGINT UNSIGNED NOT NULL,
  tipo_reporte ENUM('individual','consolidado') DEFAULT 'individual',
  ruta_archivo VARCHAR(500),
  fecha_generacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_nomina),
  CONSTRAINT fk_rnom_nomina FOREIGN KEY (id_nomina) REFERENCES nominas(id_nomina) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE citas (
  id_cita BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cliente BIGINT UNSIGNED,
  id_empleado BIGINT UNSIGNED,
  fecha_cita DATE,
  hora_cita TIME,
  motivo VARCHAR(400),
  estado_cita ENUM('programada','modificada','cancelada','realizada') DEFAULT 'programada',
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (id_cliente),
  INDEX (id_empleado),
  CONSTRAINT fk_cita_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_cita_empleado FOREIGN KEY (id_empleado) REFERENCES empleados(id_empleado) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notificaciones_citas (
  id_notificacion_cita BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_cita BIGINT UNSIGNED NOT NULL,
  tipo_notificacion VARCHAR(100),
  mensaje TEXT,
  medio ENUM('correo','SMS','interno') DEFAULT 'correo',
  estado_envio ENUM('pendiente','enviado','fallido') DEFAULT 'pendiente',
  fecha_envio TIMESTAMP NULL,
  INDEX (id_cita),
  CONSTRAINT fk_notc_cita FOREIGN KEY (id_cita) REFERENCES citas(id_cita) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
