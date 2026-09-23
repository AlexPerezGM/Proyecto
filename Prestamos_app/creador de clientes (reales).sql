USE p_test2;

-- ============================================
-- FECHA BASE PARA QUE TODO QUEDE CERCA DE HOY
-- ============================================
SET @today = CURDATE();

-- ============================================
-- ASEGURAR USUARIO ADMIN BÁSICO
-- ============================================
-- Si ya existe 'admin', esto no lo duplicará
INSERT IGNORE INTO usuario (id_datos_persona, nombre_usuario, contrasena)
VALUES (NULL, 'admin', 'admin123');

SELECT id_usuario INTO @user_admin
FROM usuario
WHERE nombre_usuario = 'admin'
LIMIT 1;

-- Asignar rol Administrador si no lo tiene
INSERT IGNORE INTO usuarios_roles (id_usuario, id_rol)
SELECT @user_admin, r.id_rol
FROM roles r
WHERE r.nombre_rol = 'Administrador';

-- ============================================
-- HELPERS: IDs DE CATÁLOGOS
-- ============================================
SELECT id_tipo_prestamo
INTO @tp_personal
FROM tipo_prestamo
WHERE nombre = 'Personal'
LIMIT 1;

SELECT id_tipo_prestamo
INTO @tp_hipotecario
FROM tipo_prestamo
WHERE nombre = 'Hipotecario'
LIMIT 1;

SELECT id_estado_prestamo
INTO @est_activo
FROM cat_estado_prestamo
WHERE estado = 'Activo'
LIMIT 1;

SELECT id_estado_prestamo
INTO @est_mora
FROM cat_estado_prestamo
WHERE estado = 'En mora'
LIMIT 1;

SELECT id_estado_prestamo
INTO @est_cerrado
FROM cat_estado_prestamo
WHERE estado = 'Cerrado'
LIMIT 1;

SELECT id_tipo_pago
INTO @pago_efectivo
FROM cat_tipo_pago
WHERE tipo_pago = 'Efectivo'
LIMIT 1;

SELECT id_tipo_pago
INTO @pago_transferencia
FROM cat_tipo_pago
WHERE tipo_pago = 'Transferencia'
LIMIT 1;

SELECT id_tipo_moneda
INTO @moneda_dop
FROM cat_tipo_moneda
WHERE tipo_moneda = 'DOP'
LIMIT 1;

SELECT id_tipo_moneda
INTO @moneda_usd
FROM cat_tipo_moneda
WHERE tipo_moneda = 'USD'
LIMIT 1;

SELECT id_documentacion_cliente
INTO @doc_ident
FROM cat_documentacion_cliente
WHERE tipo_documentacion = 'Identificacion oficial'
LIMIT 1;

SELECT id_documentacion_cliente
INTO @doc_ing
FROM cat_documentacion_cliente
WHERE tipo_documentacion = 'Comprobante de ingresos'
LIMIT 1;

SELECT id_documentacion_cliente
INTO @doc_ref
FROM cat_documentacion_cliente
WHERE tipo_documentacion = 'Referencias personales'
LIMIT 1;

SELECT id_genero
INTO @gen_m
FROM cat_genero
WHERE genero = 'Masculino'
LIMIT 1;

SELECT id_genero
INTO @gen_f
FROM cat_genero
WHERE genero = 'Femenino'
LIMIT 1;

SELECT id_tipo_documento
INTO @td_cedula
FROM cat_tipo_documento
WHERE tipo_documento = 'Cedula'
LIMIT 1;

SELECT id_nivel_riesgo
INTO @riesgo_bajo
FROM cat_nivel_riesgo
WHERE nivel = 'Bajo'
LIMIT 1;

SELECT id_nivel_riesgo
INTO @riesgo_medio
FROM cat_nivel_riesgo
WHERE nivel = 'Medio'
LIMIT 1;

SELECT id_nivel_riesgo
INTO @riesgo_alto
FROM cat_nivel_riesgo
WHERE nivel = 'Alto'
LIMIT 1;

SELECT id_tipo_entidad
INTO @ent_cliente
FROM cat_tipo_entidad
WHERE tipo_entidad = 'Cliente'
LIMIT 1;

SELECT id_tipo_entidad
INTO @ent_propiedad
FROM cat_tipo_entidad
WHERE tipo_entidad = 'Propiedad'
LIMIT 1;

SELECT id_tipo_garantia
INTO @gar_prop
FROM cat_tipo_garantia
WHERE tipo_garantia = 'Propiedad inmueble'
LIMIT 1;

SELECT id_periodo_pago
INTO @periodo_mensual
FROM cat_periodo_pago
WHERE periodo = 'Mensual'
LIMIT 1;

SELECT id_tipo_amortizacion
INTO @amort_frances
FROM cat_tipo_amortizacion
WHERE tipo_amortizacion = 'Metodo Francés'
LIMIT 1;

-- ============================================
-- CONDICIONES DE PRÉSTAMO (PRODUCTOS)
-- ============================================

-- Condición para préstamos personales (mensual, método francés)
INSERT INTO condicion_prestamo (
    tasa_interes,
    id_tipo_amortizacion,
    id_periodo_pago,
    vigente_desde,
    vigente_hasta,
    esta_activo
)
VALUES (
    12.50,
    @amort_frances,
    @periodo_mensual,
    DATE_SUB(@today, INTERVAL 6 MONTH),
    NULL,
    TRUE
);

-- Condición para préstamos hipotecarios (mensual, método francés)
INSERT INTO condicion_prestamo (
    tasa_interes,
    id_tipo_amortizacion,
    id_periodo_pago,
    vigente_desde,
    vigente_hasta,
    esta_activo
)
VALUES (
    8.50,
    @amort_frances,
    @periodo_mensual,
    DATE_SUB(@today, INTERVAL 6 MONTH),
    NULL,
    TRUE
);

SELECT id_condicion_prestamo
INTO @cond_personal
FROM condicion_prestamo
WHERE tasa_interes = 12.50
  AND id_tipo_amortizacion = @amort_frances
  AND id_periodo_pago = @periodo_mensual
ORDER BY vigente_desde DESC
LIMIT 1;

SELECT id_condicion_prestamo
INTO @cond_hipotecario
FROM condicion_prestamo
WHERE tasa_interes = 8.50
  AND id_tipo_amortizacion = @amort_frances
  AND id_periodo_pago = @periodo_mensual
ORDER BY vigente_desde DESC
LIMIT 1;

-- ============================================
-- DATOS DE PRUEBA
-- ============================================
START TRANSACTION;

-- ===================================================
-- CLIENTE 1 — Juan Pérez — PRÉSTAMO PERSONAL ACTIVO
-- ===================================================
INSERT INTO datos_persona (nombre, apellido, fecha_nacimiento, genero)
VALUES ('Juan', 'Perez', '1988-03-15', @gen_m);
SET @dp1 = LAST_INSERT_ID();

INSERT INTO email (id_datos_persona, email, es_principal)
VALUES (@dp1, 'juan.perez@example.com', TRUE);

INSERT INTO telefono (id_datos_persona, telefono, es_principal)
VALUES (@dp1, '+1-809-555-1001', TRUE);

INSERT INTO documento_identidad (id_datos_persona, id_tipo_documento, numero_documento, fecha_emision)
VALUES (@dp1, @td_cedula, '001-1234567-1', DATE_SUB(@today, INTERVAL 9 YEAR));

INSERT INTO direccion (id_datos_persona, ciudad, sector, calle, numero_casa)
VALUES (@dp1, 'Santiago', 'Los Jardines', 'Calle 1ra', 12);
SET @dir1 = LAST_INSERT_ID();

INSERT INTO direccion_entidad (id_direccion, id_tipo_entidad, tipo_direccion)
VALUES (@dir1, @ent_cliente, 'Residencial');

INSERT INTO ocupacion (id_datos_persona, ocupacion, empresa)
VALUES (@dp1, 'Analista de sistemas', 'TechMax SRL');

INSERT INTO cliente (id_datos_persona)
VALUES (@dp1);
SET @cli1 = LAST_INSERT_ID();

INSERT INTO ingresos_egresos (id_cliente, ingresos_mensuales, egresos_mensuales)
VALUES (@cli1, 60000.00, 25000.00);
SET @ie1 = LAST_INSERT_ID();

INSERT INTO fuente_ingreso (id_ingresos_egresos, fuente)
VALUES (@ie1, 'Salario fijo'),
       (@ie1, 'Freelance');

INSERT INTO documentacion_cliente (id_cliente, id_documentacion_cliente, ruta_documento)
VALUES (@cli1, @doc_ident, '/docs/juan/cedula.pdf'),
       (@cli1, @doc_ing,   '/docs/juan/nominas.pdf'),
       (@cli1, @doc_ref,   '/docs/juan/referencias.pdf');

INSERT INTO puntaje_crediticio (id_cliente, id_nivel_riesgo, puntaje, total_transacciones)
VALUES (@cli1, @riesgo_medio, 650, 12);

-- Préstamo PERSONAL ACTIVO
SET @fecha_sol1 = DATE_SUB(@today, INTERVAL 1 MONTH);

INSERT INTO prestamo (
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
VALUES (
    @cli1,
    @tp_personal,
    CONCAT('PER-', LPAD(@cli1, 4, '0'), '-', DATE_FORMAT(@fecha_sol1, '%Y%m%d')),
    120000.00,
    @fecha_sol1,
    12,
    @est_activo,
    @cond_personal,
    @user_admin
);
SET @pre1 = LAST_INSERT_ID();

INSERT INTO prestamo_personal (id_prestamo, motivo)
VALUES (@pre1, 'Consolidación de deudas y gastos educativos');

SET @fecha_des1 = DATE_ADD(@fecha_sol1, INTERVAL 2 DAY);

INSERT INTO desembolso (id_prestamo, monto_desembolsado, fecha_desembolso, metodo_entrega, creado_por)
VALUES (@pre1, 120000.00, @fecha_des1, @pago_transferencia, @user_admin);

-- Cronograma del préstamo 1
CALL generar_cronograma(@pre1, @fecha_des1);

-- Evaluación del préstamo 1
INSERT INTO evaluacion_prestamo (
    id_cliente,
    id_prestamo,
    capacidad_pago,
    nivel_riesgo,
    estado_evaluacion,
    fecha_evaluacion
) VALUES (
    @cli1,
    @pre1,
    35000.00,
    @riesgo_medio,
    'Aprobado',
    DATE_SUB(@today, INTERVAL 25 DAY)
);
SET @eval1 = LAST_INSERT_ID();

INSERT INTO detalle_evaluacion (
    id_evaluacion_prestamo,
    fecha,
    observacion,
    evaluado_por
) VALUES (
    @eval1,
    DATE_SUB(@today, INTERVAL 25 DAY),
    'Cliente con ingresos estables y relación cuota/ingreso aceptable.',
    @user_admin
);

-- ===========================================================
-- CLIENTE 2 — María Gómez — PRÉSTAMO HIPOTECARIO (ACTIVO)
-- ===========================================================
INSERT INTO datos_persona (nombre, apellido, fecha_nacimiento, genero)
VALUES ('Maria', 'Gomez', '1985-07-21', @gen_f);
SET @dp2 = LAST_INSERT_ID();

INSERT INTO email (id_datos_persona, email, es_principal)
VALUES (@dp2, 'maria.gomez@example.com', TRUE);

INSERT INTO telefono (id_datos_persona, telefono, es_principal)
VALUES (@dp2, '+1-809-555-2002', TRUE);

INSERT INTO documento_identidad (id_datos_persona, id_tipo_documento, numero_documento, fecha_emision)
VALUES (@dp2, @td_cedula, '002-7654321-2', DATE_SUB(@today, INTERVAL 10 YEAR));

INSERT INTO direccion (id_datos_persona, ciudad, sector, calle, numero_casa)
VALUES (@dp2, 'Santo Domingo', 'Naco', 'Av. Principal', 101);
SET @dir2 = LAST_INSERT_ID();

INSERT INTO direccion_entidad (id_direccion, id_tipo_entidad, tipo_direccion)
VALUES (@dir2, @ent_cliente, 'Residencial');

INSERT INTO ocupacion (id_datos_persona, ocupacion, empresa)
VALUES (@dp2, 'Gerente Financiera', 'Banco Popular');

INSERT INTO cliente (id_datos_persona)
VALUES (@dp2);
SET @cli2 = LAST_INSERT_ID();

INSERT INTO ingresos_egresos (id_cliente, ingresos_mensuales, egresos_mensuales)
VALUES (@cli2, 600000.00, 150000.00);
SET @ie2 = LAST_INSERT_ID();

INSERT INTO fuente_ingreso (id_ingresos_egresos, fuente)
VALUES (@ie2, 'Salario gerencial');

INSERT INTO documentacion_cliente (id_cliente, id_documentacion_cliente, ruta_documento)
VALUES (@cli2, @doc_ident, '/docs/maria/cedula.pdf'),
       (@cli2, @doc_ing,   '/docs/maria/estados_bancarios.pdf'),
       (@cli2, @doc_ref,   '/docs/maria/referencias.pdf');

INSERT INTO puntaje_crediticio (id_cliente, id_nivel_riesgo, puntaje, total_transacciones)
VALUES (@cli2, @riesgo_bajo, 780, 40);

-- Dirección de la PROPIEDAD (usando un datos_persona válido, NO NULL)
INSERT INTO direccion (id_datos_persona, ciudad, sector, calle, numero_casa)
VALUES (@dp2, 'Santo Domingo', 'Piantini', 'Calle Las Rosas', 50);
SET @dir_prop2 = LAST_INSERT_ID();

INSERT INTO direccion_entidad (id_direccion, id_tipo_entidad, tipo_direccion)
VALUES (@dir_prop2, @ent_propiedad, 'Propiedad');

-- Propiedad registrada a nombre del cliente
INSERT INTO propiedades (id_cliente, id_direccion, valor_propiedad, fecha_registro)
VALUES (@cli2, @dir_prop2, 4500000.00, DATE_SUB(@today, INTERVAL 5 MONTH));
SET @prop2 = LAST_INSERT_ID();

-- Préstamo HIPOTECARIO
SET @fecha_sol2 = DATE_SUB(@today, INTERVAL 5 MONTH);

INSERT INTO prestamo (
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
VALUES (
    @cli2,
    @tp_hipotecario,
    CONCAT('HIP-', LPAD(@cli2, 4, '0'), '-', DATE_FORMAT(@fecha_sol2, '%Y%m%d')),
    3000000.00,
    @fecha_sol2,
    24,
    @est_activo,
    @cond_hipotecario,
    @user_admin
);
SET @pre2 = LAST_INSERT_ID();

INSERT INTO prestamo_hipotecario (id_prestamo, valor_propiedad, porcentaje_financiamiento, direccion_propiedad)
VALUES (
    @pre2,
    4500000.00,
    66.67,
    'Calle Las Rosas #50, Piantini, Santo Domingo'
);

INSERT INTO prestamo_propiedad (id_prestamo, id_propiedad, porcentaje_garantia)
VALUES (@pre2, @prop2, 70.00);

-- Garantía sobre la propiedad
INSERT INTO garantia (id_prestamo, id_cliente, descripcion, valor)
VALUES (@pre2, @cli2, 'Hipoteca sobre propiedad principal', 3000000.00);
SET @gar2 = LAST_INSERT_ID();

INSERT INTO detalle_garantia (
    id_garantia,
    id_tipo_garantia,
    descripcion,
    valor_estimado
) VALUES (
    @gar2,
    @gar_prop,
    'Casa en Piantini',
    4500000.00
);
SET @detgar2 = LAST_INSERT_ID();

INSERT INTO documento_garantia (id_detalle_garantia, ruta_documento)
VALUES (@detgar2, '/docs/maria/hipoteca.pdf');

-- Desembolso del hipotecario
SET @fecha_des2 = DATE_ADD(@fecha_sol2, INTERVAL 3 DAY);

INSERT INTO desembolso (id_prestamo, monto_desembolsado, fecha_desembolso, metodo_entrega, creado_por)
VALUES (@pre2, 3000000.00, @fecha_des2, @pago_transferencia, @user_admin);

-- Cronograma del préstamo 2
CALL generar_cronograma(@pre2, @fecha_des2);

-- Evaluación del préstamo 2
INSERT INTO evaluacion_prestamo (
    id_cliente,
    id_prestamo,
    capacidad_pago,
    nivel_riesgo,
    estado_evaluacion,
    fecha_evaluacion
) VALUES (
    @cli2,
    @pre2,
    200000.00,
    @riesgo_bajo,
    'Aprobado',
    DATE_SUB(@today, INTERVAL 4 MONTH)
);
SET @eval2 = LAST_INSERT_ID();

INSERT INTO detalle_evaluacion (
    id_evaluacion_prestamo,
    fecha,
    observacion,
    evaluado_por
) VALUES (
    @eval2,
    DATE_SUB(@today, INTERVAL 4 MONTH),
    'Cliente con alta capacidad de pago y excelente historial crediticio.',
    @user_admin
);

-- =====================================================
-- CLIENTE 3 — Carlos Ruiz — PRÉSTAMO PERSONAL EN MORA
-- =====================================================
INSERT INTO datos_persona (nombre, apellido, fecha_nacimiento, genero)
VALUES ('Carlos', 'Ruiz', '1992-11-09', @gen_m);
SET @dp3 = LAST_INSERT_ID();

INSERT INTO email (id_datos_persona, email, es_principal)
VALUES (@dp3, 'carlos.ruiz@example.com', TRUE);

INSERT INTO telefono (id_datos_persona, telefono, es_principal)
VALUES (@dp3, '+1-809-555-3003', TRUE);

INSERT INTO documento_identidad (id_datos_persona, id_tipo_documento, numero_documento, fecha_emision)
VALUES (@dp3, @td_cedula, '003-0001112-3', DATE_SUB(@today, INTERVAL 8 YEAR));

INSERT INTO direccion (id_datos_persona, ciudad, sector, calle, numero_casa)
VALUES (@dp3, 'La Vega', 'Florencio', 'Calle 2', 9);
SET @dir3 = LAST_INSERT_ID();

INSERT INTO direccion_entidad (id_direccion, id_tipo_entidad, tipo_direccion)
VALUES (@dir3, @ent_cliente, 'Residencial');

INSERT INTO ocupacion (id_datos_persona, ocupacion, empresa)
VALUES (@dp3, 'Técnico', 'ServiTech');

INSERT INTO cliente (id_datos_persona)
VALUES (@dp3);
SET @cli3 = LAST_INSERT_ID();

INSERT INTO ingresos_egresos (id_cliente, ingresos_mensuales, egresos_mensuales)
VALUES (@cli3, 120000.00, 60000.00);
SET @ie3 = LAST_INSERT_ID();

INSERT INTO fuente_ingreso (id_ingresos_egresos, fuente)
VALUES (@ie3, 'Salario'),
       (@ie3, 'Servicios por contrato');

INSERT INTO documentacion_cliente (id_cliente, id_documentacion_cliente, ruta_documento)
VALUES (@cli3, @doc_ident, '/docs/carlos/cedula.pdf'),
       (@cli3, @doc_ing,   '/docs/carlos/recibos.pdf'),
       (@cli3, @doc_ref,   '/docs/carlos/referencias.pdf');

INSERT INTO puntaje_crediticio (id_cliente, id_nivel_riesgo, puntaje, total_transacciones)
VALUES (@cli3, @riesgo_medio, 610, 18);

-- Préstamo PERSONAL que quedará EN MORA
SET @fecha_sol3 = DATE_SUB(@today, INTERVAL 4 MONTH);

INSERT INTO prestamo (
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
VALUES (
    @cli3,
    @tp_personal,
    CONCAT('PER-', LPAD(@cli3, 4, '0'), '-', DATE_FORMAT(@fecha_sol3, '%Y%m%d')),
    200000.00,
    @fecha_sol3,
    12,
    @est_activo,
    @cond_personal,
    @user_admin
);
SET @pre3 = LAST_INSERT_ID();

INSERT INTO prestamo_personal (id_prestamo, motivo)
VALUES (@pre3, 'Gastos médicos y reforma del hogar');

SET @fecha_des3 = DATE_ADD(@fecha_sol3, INTERVAL 2 DAY);

INSERT INTO desembolso (id_prestamo, monto_desembolsado, fecha_desembolso, metodo_entrega, creado_por)
VALUES (@pre3, 200000.00, @fecha_des3, @pago_efectivo, @user_admin);

-- Cronograma del préstamo 3
CALL generar_cronograma(@pre3, @fecha_des3);

-- Simular mora: cuotas vencidas
UPDATE cronograma_cuota
SET estado_cuota = 'Vencida',
    cargos_cuota = ROUND(cargos_cuota + (saldo_cuota * 0.02), 2)
WHERE id_prestamo = @pre3
  AND fecha_vencimiento < @today
  AND estado_cuota = 'Pendiente';

UPDATE prestamo
SET id_estado_prestamo = @est_mora
WHERE id_prestamo = @pre3;

-- Evaluación del préstamo 3
INSERT INTO evaluacion_prestamo (
    id_cliente,
    id_prestamo,
    capacidad_pago,
    nivel_riesgo,
    estado_evaluacion,
    fecha_evaluacion
) VALUES (
    @cli3,
    @pre3,
    30000.00,
    @riesgo_medio,
    'Aprobado',
    DATE_SUB(@today, INTERVAL 3 MONTH)
);
SET @eval3 = LAST_INSERT_ID();

INSERT INTO detalle_evaluacion (
    id_evaluacion_prestamo,
    fecha,
    observacion,
    evaluado_por
) VALUES (
    @eval3,
    DATE_SUB(@today, INTERVAL 3 MONTH),
    'Cliente con capacidad ajustada; monitorear cumplimiento. Actualmente en mora.',
    @user_admin
);

-- =====================================================
-- CLIENTE 4 — Lucia Torres — REGISTRADA SIN PRÉSTAMO
-- =====================================================
INSERT INTO datos_persona (nombre, apellido, fecha_nacimiento, genero)
VALUES ('Lucia', 'Torres', '1998-01-28', @gen_f);
SET @dp4 = LAST_INSERT_ID();

INSERT INTO email (id_datos_persona, email, es_principal)
VALUES (@dp4, 'lucia.torres@example.com', TRUE);

INSERT INTO telefono (id_datos_persona, telefono, es_principal)
VALUES (@dp4, '+1-809-555-4004', TRUE);

INSERT INTO documento_identidad (id_datos_persona, id_tipo_documento, numero_documento, fecha_emision)
VALUES (@dp4, @td_cedula, '004-9998887-4', DATE_SUB(@today, INTERVAL 5 YEAR));

INSERT INTO direccion (id_datos_persona, ciudad, sector, calle, numero_casa)
VALUES (@dp4, 'Puerto Plata', 'Centro', 'Calle Marina', 7);
SET @dir4 = LAST_INSERT_ID();

INSERT INTO direccion_entidad (id_direccion, id_tipo_entidad, tipo_direccion)
VALUES (@dir4, @ent_cliente, 'Residencial');

INSERT INTO ocupacion (id_datos_persona, ocupacion, empresa)
VALUES (@dp4, 'Diseñadora', 'Creativa Studio');

INSERT INTO cliente (id_datos_persona)
VALUES (@dp4);
SET @cli4 = LAST_INSERT_ID();

INSERT INTO ingresos_egresos (id_cliente, ingresos_mensuales, egresos_mensuales)
VALUES (@cli4, 85000.00, 30000.00);
SET @ie4 = LAST_INSERT_ID();

INSERT INTO fuente_ingreso (id_ingresos_egresos, fuente)
VALUES (@ie4, 'Honorarios de diseño');

INSERT INTO documentacion_cliente (id_cliente, id_documentacion_cliente, ruta_documento)
VALUES (@cli4, @doc_ident, '/docs/lucia/cedula.pdf'),
       (@cli4, @doc_ing,   '/docs/lucia/facturas.pdf'),
       (@cli4, @doc_ref,   '/docs/lucia/referencias.pdf');

INSERT INTO puntaje_crediticio (id_cliente, id_nivel_riesgo, puntaje, total_transacciones)
VALUES (@cli4, @riesgo_bajo, 720, 9);

COMMIT;
