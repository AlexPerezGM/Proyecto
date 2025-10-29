CREATE DATABASE prestamos_db;

USE prestamos_db;

-- Catalogos generales
CREATE TABLE cat_genero(
    id_genero INT AUTO_INCREMENT PRIMARY KEY,
    genero VARCHAR(50) NOT NULL
);
Insert into cat_genero (genero) values ('Masculino'), ('Femenino'), ('Otro');

CREATE TABLE cat_tipo_pago(
    id_tipo_pago INT AUTO_INCREMENT PRIMARY KEY,
    tipo_pago VARCHAR(50) NOT NULL
);
Insert into cat_tipo_pago (tipo_pago) values ('Efectivo'), ('Transferencia'), ('Cheque');

CREATE TABLE cat_estado_prestamo(
    id_estado_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    estado VARCHAR(50) NOT NULL
);
Insert into cat_estado_prestamo (estado) values ('Activo'), ('Pendiente'), ('Rechazado'), ('En mora'), ('Pagado');

CREATE TABLE cat_periodo_pago(
    id_periodo_pago INT AUTO_INCREMENT PRIMARY KEY,
    periodo VARCHAR(50) NOT NULL
);
Insert into cat_periodo_pago (periodo) values ('Semanal'), ('Quincenal'), ('Mensual');

CREATE TABLE cat_tipo_documento(
    id_tipo_documento INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento VARCHAR(50) NOT NULL
);
Insert into cat_tipo_documento (tipo_documento) values ('Cedula'), ('Pasaporte'), ('Licencia de conducir');

CREATE TABLE cat_nivel_riesgo(
    id_nivel_riesgo INT AUTO_INCREMENT PRIMARY KEY,
    nivel VARCHAR(50) NOT NULL
);
Insert into cat_nivel_riesgo (nivel) values ('Bajo'), ('Medio'), ('Alto');

CREATE TABLE cat_tipo_promocion(
    id_tipo_promocion INT AUTO_INCREMENT PRIMARY KEY,
    tipo_promocion VARCHAR(50) NOT NULL
);
Insert into cat_tipo_promocion (tipo_promocion) values ('Descuento'), ('Promoción'), ('Tasa interes mas baja');

CREATE TABLE cat_tipo_entidad(
    id_tipo_entidad INT AUTO_INCREMENT PRIMARY KEY,
    tipo_entidad VARCHAR(50) NOT NULL
);
Insert into cat_tipo_entidad (tipo_entidad) values ('Cliente'), ('Empleado'), ('Propiedad');

CREATE TABLE cat_tipo_contrato(
    id_tipo_contrato INT AUTO_INCREMENT PRIMARY KEY,
    tipo_contrato VARCHAR(50) NOT NULL
);
Insert into cat_tipo_contrato (tipo_contrato) values ('Medio tiempo'), ('Tiempo completo'), ('Pasantia');

CREATE TABLE cat_tipo_moneda(
    id_tipo_moneda INT AUTO_INCREMENT PRIMARY KEY,
    tipo_moneda VARCHAR(50) NOT NULL,
    valor Decimal(10,2) NOT NULL
);
Insert into cat_tipo_moneda (tipo_moneda, valor) values ('DOP', 1.00), ('USD', 62.97);

CREATE TABLE cat_tipo_deduccion(
    id_tipo_deduccion INT AUTO_INCREMENT PRIMARY KEY,
    tipo_deduccion VARCHAR(50) NOT NULL
);
Insert into cat_tipo_deduccion (tipo_deduccion) values ('ISR'), ('AFP'), ('SFS');

CREATE TABLE cat_beneficio(
    id_beneficio INT AUTO_INCREMENT PRIMARY KEY,
    tipo_beneficio VARCHAR(50) NOT NULL
);
Insert into cat_beneficio (tipo_beneficio) values ('Seguro médico'), ('Bonificación'), ('Vacaciones pagadas');

-- Tablas principales 

CREATE TABLE datos_persona(
    id_datos_persona INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100)NOT NULL,
    apellido VARCHAR(100)NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    genero INT,
    estado_cliente ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (genero) REFERENCES cat_genero(id_genero)
);

CREATE TABLE email(
    id_email INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    email VARCHAR(100) NOT NULL,
    es_principal BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
);

CREATE TABLE telefono(
    id_telefono INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    telefono VARCHAR(20) NOT NULL,
    es_principal BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
);

CREATE TABLE documento_identidad(
    id_documento_identidad INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    id_tipo_documento INT,
    numero_documento VARCHAR(50) NOT NULL UNIQUE,
    fecha_emision DATE NOT NULL,
    FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona),
    FOREIGN KEY (id_tipo_documento) REFERENCES cat_tipo_documento(id_tipo_documento)
);

CREATE TABLE direccion(
    id_direccion INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    ciudad VARCHAR(100) NOT NULL,
    sector VARCHAR(100) NOT NULL,
    calle VARCHAR(100) NOT NULL,
    numero_casa INT NOT NULL,
    es_principal BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)   
);

CREATE TABLE direccion_entidad(
    id_direccion_entidad INT AUTO_INCREMENT PRIMARY KEY,
    id_direccion INT,
    id_tipo_entidad INT,
    tipo_direccion VARCHAR(50) NOT NULL,
    FOREIGN KEY (id_direccion) REFERENCES direccion(id_direccion),
    FOREIGN KEY (id_tipo_entidad) REFERENCES cat_tipo_entidad(id_tipo_entidad)
);

CREATE TABLE ocupacion(
    id_ocupacion INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    ocupacion VARCHAR(100) NOT NULL,
    empresa VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
);

-- Tablas para clientes 

CREATE TABLE cliente(
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
);

CREATE TABLE ingresos_egresos(
    id_ingresos_egresos INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    ingresos_mensuales DECIMAL(10,2) NOT NULL,
    egresos_mensuales DECIMAL(10,2) NOT NULL,
    CONSTRAINT chk_ingresos_egresos CHECK (ingresos_mensuales >= 0 AND egresos_mensuales >= 0),
    CONSTRAINT chk_saldo CHECK (ingresos_mensuales >= egresos_mensuales),
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
);

CREATE TABLE fuente_ingreso(
    id_fuente_ingreso INT AUTO_INCREMENT PRIMARY KEY,
    id_ingresos_egresos INT,
    fuente VARCHAR(100) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_ingresos_egresos) REFERENCES ingresos_egresos(id_ingresos_egresos)
);

CREATE TABLE documentacion_cliente(
    id_documentacion_cliente INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    id_tipo_documento INT,
    ruta_documento VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_tipo_documento) REFERENCES cat_tipo_documento(id_tipo_documento)
);

CREATE TABLE puntaje_crediticio(
    id_puntaje_crediciticio INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    id_nivel_riesgo INT,
    puntaje INT DEFAULT 0,
    total_transacciones INT NOT NULL,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_nivel_riesgo) REFERENCES cat_nivel_riesgo(id_nivel_riesgo)
);

-- Tabla prestamos 

CREATE TABLE tipo_prestamo(
    id_tipo_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tasa_interes DECIMAL(5,2) NOT NULL,
    monto_minimo DECIMAL(10,2) NOT NULL,
    tipo_amortizacion VARCHAR(50) NOT NULL,
    plazo_minimo_meses INT NOT NULL,
    plazo_maximo_meses INT NOT NULL
);

CREATE TABLE prestamo(
    id_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT, 
    id_tipo_prestamo INT,
    monto_solicitado DECIMAL(10,2) NOT NULL,
    fecha_solicitud DATE NOT NULL,
    fecha_otrogamiento DATE,
    plazo_meses INT NOT NULL,
    id_estado_prestamo INT,
    id_condicion_actual INT,
    creado_por INT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_monto_solicitado CHECK (monto_solicitado > 0),
    CONSTRAINT chk_plazo_meses CHECK (plazo_meses BETWEEN 1 AND 50),
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_tipo_prestamo) REFERENCES tipo_prestamo(id_tipo_prestamo),
    FOREIGN KEY (id_estado_prestamo) REFERENCES cat_estado_prestamo(id_estado_prestamo),
    FOREIGN KEY (id_condicion_actual) REFERENCES condicion_prestamo(id_condicion_prestamo),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario)
);

CREATE TABLE prestamos_hipotecario(
    id_prestamo_hipotecario INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    valor_propiedad DECIMAL(10,2) NOT NULL,
    porcentaje_financiamiento DECIMAL(5,2) NOT NULL,
    direccion_propiedad VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
);

CREATE TABLE condicion_prestamo(
    id_condicion_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    tasa_interes DECIMAL(5,2) NOT NULL,
    tipo_interes VARCHAR(50) NOT NULL,
    tipo_amortizacion VARCHAR(50) NOT NULL,
    id_periodo_pago INT,
    vigente_desde DATE NOT NULL,
    vigente_hasta DATE,
    esta_activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo),
    FOREIGN KEY (id_periodo_pago) REFERENCES cat_periodo_pago(id_periodo_pago)
);

CREATE TABLE propiedades(
    id_propiedad INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT, 
    id_direccion INT,
    area_m2 DECIMAL(10,2) NOT NULL,
    valor_propiedad DECIMAL(10,2) NOT NULL,
    fecha_registro DATE NOT NULL,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_direccion) REFERENCES direccion(id_direccion)
);

CREATE TABLE prestamo_propiedad(
    id_prestamo_propiedad INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    id_propiedad INT,
    porcentaje_garantia DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo),
    FOREIGN KEY (id_propiedad) REFERENCES propiedades(id_propiedad)
);

CREATE TABLE garantia(
    id_garantia INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    descripcion VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    id_cliente INT,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
);

CREATE TABLE retiro_garantia(
    id_retiro_garantia INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT,
    id_garantia INT,
    fecha_uso DATE NOT NULL,
    monto_usado DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pago) REFERENCES pago(id_pago),
    FOREIGN KEY (id_garantia) REFERENCES garantia(id_garantia)
);

CREATE TABLE desembolso(
    id_desembolso INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    monto_desembolsado DECIMAL(10,2) NOT NULL,
    fecha_desembolso DATE NOT NULL,
    metodo_entrega INT,
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo),
    FOREIGN KEY (metodo_entrega) REFERENCES cat_tipo_pago(id_tipo_pago)
);

CREATE TABLE cronograma_cuota(
    id_cronograma_cuota INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    numero_cuota INT NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    capital_cuota DECIMAL(10,2) NOT NULL,
    interes_cuota DECIMAL(10,2) NOT NULL,
    cargos_cuota DECIMAL(10,2) DEFAULT 0.00,
    total_monto DECIMAL(10,2) NOT NULL,
    saldo_pendiente DECIMAL(10,2) NOT NULL,
    estado_cuota ENUM('Pendiente', 'Pagada', 'Vencida') DEFAULT 'Pendiente',
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
);

-- Seguimiento de prestamos 

CREATE TABLE evaluacion_prestamo(
    id_evaluacion_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    id_prestamo INT,
    capacidad_pago DECIMAL(10,2) NOT NULL,
    nivel_riesgo INT,
    estado_evaluacion ENUM('Aprobado', 'Rechazado', 'Pendiente') NOT NULL,
    fecha_evaluacion DATE NOT NULL,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
);

CREATE TABLE detalle_evaluacion(
    id_detalle_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
    id_evaluacion_prestamo INT,
    fecha DATE NOT NULL,
    observacion TEXT NOT NULL,
    evaluado_por INT,
    FOREIGN KEY (id_evaluacion_prestamo) REFERENCES evaluacion_prestamo(id_evaluacion_prestamo),
    FOREIGN KEY (evaluado_por) REFERENCES usuario(id_usuario)
);

-- notificaciones 

CREATE TABLE plantilla_notificacion(
    id_plantilla_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    tipo_notificacion VARCHAR(100) NOT NULL,
    asunto VARCHAR(150) NOT NULL,
    cuerpo TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notificaciones(
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    id_plantilla_notificacion INT,
    canal_envio VARCHAR(100) NOT NULL,
    programada_para TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado_envio ENUM('Enviado', 'Fallido', 'Pendiente') DEFAULT 'Pendiente',
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_plantilla_notificacion) REFERENCES plantilla_notificacion(id_plantilla_notificacion)
);

-- Reestructuracion de prestamos 

CREATE TABLE reestructuracion_prestamo(
    id_restructuracion_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    id_condicion_nueva INT,
    fecha_reestructuracion DATE NOT NULL,
    aprobado_por INT,
    notas TEXT,
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo),
    FOREIGN KEY (id_condicion_nueva) REFERENCES condicion_prestamo(id_condicion_prestamo),
    FOREIGN KEY (aprobado_por) REFERENCES usuario(id_usuario)
);

-- Pagos

CREATE TABLE pago(
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    fecha_pago DATE NOT NULL,
    monto_pagado DECIMAL(10,2) NOT NULL,
    metodo_pago INT,
    id_tipo_moneda INT,
    creado_por INT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_monto_pagado CHECK (monto_pagado > 0),
    CONSTRAINT chk_fecha_pago CHECK (fecha_pago <= CURRENT_DATE),
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo),
    FOREIGN KEY (metodo_pago) REFERENCES cat_tipo_pago(id_tipo_pago),
    FOREIGN KEY (id_tipo_moneda) REFERENCES cat_tipo_moneda(id_tipo_moneda),
    FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario)
);

CREATE TABLE asignacion_pago(
    id_asignacion_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT,
    id_cronograma_cuota INT,
    monto_asignado DECIMAL(10,2) NOT NULL,
    tipo_asignacion ENUM('Capital', 'Interes', 'Cargos') NOT NULL,
    FOREIGN KEY (id_pago) REFERENCES pago(id_pago),
    FOREIGN KEY (id_cronograma_cuota) REFERENCES cronograma_cuota(id_cronograma_cuota)
);

CREATE TABLE facturas(
    id_factura INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT,
    numero_factura VARCHAR(50) unique NOT NULL,
    fecha_emision DATE NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_pago) REFERENCES pago(id_pago)
);

-- Reportes 

CREATE TABLE reportes(
    id_reportes INT AUTO_INCREMENT PRIMARY KEY,
    tipo_reporte VARCHAR(100) NOT NULL,
    parametros TEXT,
    fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generado_por INT,
    ruta_archivo VARCHAR(255) NOT NULL,
    FOREIGN KEY (generado_por) REFERENCES usuario(id_usuario)
);

CREATE TABLE reportes_prestamo(
    id_reportes_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_reporte INT,
    id_prestamo INT,
    FOREIGN KEY (id_reporte) REFERENCES reportes(id_reportes),
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
);

CREATE TABLE reportes_cliente(
    id_reportes_cliente INT AUTO_INCREMENT PRIMARY KEY,
    id_reporte INT,
    id_cliente INT,
    FOREIGN KEY (id_reporte) REFERENCES reportes(id_reportes),
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
);

-- Usuarios y roles 

CREATE TABLE usuarios(
    id_usuarios INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    nombre_usuario VARCHAR(50) UNIQUE NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
);

CREATE TABLE roles(
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) UNIQUE NOT NULL,
    descripcion VARCHAR(255) NOT NULL
);

CREATE TABLE permisos(
    id_permiso INT AUTO_INCREMENT PRIMARY KEY,
    nombre_permiso VARCHAR(100) UNIQUE NOT NULL,
    descripcion VARCHAR(255) NOT NULL
);

CREATE TABLE usuarios_roles(
    id_usuario_rol INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    id_rol INT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuarios),
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
);

CREATE TABLE roles_permisos(
    id_rol_permiso INT AUTO_INCREMENT PRIMARY KEY,
    id_rol INT,
    id_permiso INT,
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol),
    FOREIGN KEY (id_permiso) REFERENCES permisos(id_permiso)
);

CREATE TABLE historial_acceso(
    id_historial_acceso INT AUTO_INCREMENT PRIMARY KEY,
    id_usuarios INT,
    fecha_acceso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_acceso VARCHAR(45),
    accion VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_usuarios) REFERENCES usuarios(id_usuarios)
);

-- Administracion de empleados 

CREATE TABLE empleado(
    id_empleado INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    id_tipo_contrato INT,
    rol VARCHAR(100) NOT NULL,
    salario DECIMAL(10,2) NOT NULL,
    fecha_contratacion DATE NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona),
    FOREIGN KEY (id_tipo_contrato) REFERENCES cat_tipo_contrato(id_tipo_contrato)
);

CREATE TABLE asistencia_empleado(
    id_asistencia_empleado INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT,
    hora_entrada TIMESTAMP NOT NULL,
    hora_salida TIMESTAMP,
    retrasos INT DEFAULT 0,
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
);

CREATE TABLE nomina_empleado(
    id_nomina_empleado INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT,
    sueldo_base DECIMAL(10,2) NOT NULL,
    descuento DECIMAL(10,2) DEFAULT 0.00,
    sueldo_neto DECIMAL(10,2) NOT NULL,
    fecha_pago DATE NOT NULL,
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
);

CREATE TABLE deducciones_empleado(
    id_empleado_deduccion INT AUTO_INCREMENT PRIMARY KEY,
    id_nomina_empleado INT,
    id_tipo_deduccion INT,
    monto DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_nomina_empleado) REFERENCES nomina_empleado(id_nomina_empleado),
    FOREIGN KEY (id_tipo_deduccion) REFERENCES cat_tipo_deduccion(id_tipo_deduccion)
);

CREATE TABLE beneficios_empleado(
    id_beneficio_empleado INT AUTO_INCREMENT PRIMARY KEY,
    id_nomina_empleado INT,
    id_beneficio INT,
    monto DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_nomina_empleado) REFERENCES nomina_empleado(id_nomina_empleado),
    FOREIGN KEY (id_beneficio) REFERENCES cat_beneficio(id_beneficio)
);

CREATE TABLE horas_extras(
    id_horas_extras INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT,
    cantidad_horas INT NOT NULL,
    pago_horas_extras DECIMAL(10,2) NOT NULL,
    monto_total DECIMAL(10,2) NOT NULL,
    fecha DATE NOT NULL,
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
);

-- Promociones y descuentos 
CREATE TABLE tipo_beneficio(
    id_tipo_beneficio INT AUTO_INCREMENT PRIMARY KEY,
    tipo_beneficio VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    puntos_necesarios INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE promociones(
    id_promocion INT AUTO_INCREMENT PRIMARY KEY,
    nombre_promocion VARCHAR(100) NOT NULL,
    estado ENUM('Activa', 'Inactiva') DEFAULT 'Inactiva',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    id_tipo_promocion INT,
    id_tipo_beneficio INT,
    FOREIGN KEY (id_tipo_promocion) REFERENCES cat_tipo_promocion(id_tipo_promocion),
    FOREIGN KEY (id_tipo_beneficio) REFERENCES tipo_beneficio(id_tipo_beneficio)
);

CREATE TABLE asignacion_promocion(
    id_asignacion_promocion INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    id_promocion INT,
    fecha_asignacion DATE NOT NULL,
    estado_promocion ENUM('Activa', 'Usada', 'Expirada') DEFAULT 'Activa',
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_promocion) REFERENCES promociones(id_promocion)
);

CREATE TABLE uso_promocion(
    id_uso_promocion INT AUTO_INCREMENT PRIMARY KEY,
    id_asignacion_promocion INT,
    id_prestamo INT,
    fecha_uso DATE NOT NULL,
    FOREIGN KEY (id_asignacion_promocion) REFERENCES asignacion_promocion(id_asignacion_promocion),
    FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
);

CREATE TABLE campañas_promocion(
    id_campaña_promocion INT AUTO_INCREMENT PRIMARY KEY,
    nombre_campaña VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado_campaña ENUM('Activa', 'Inactiva') DEFAULT 'Inactiva'
);

CREATE TABLE promociones_campaña(
    id_promocion_campaña INT AUTO_INCREMENT PRIMARY KEY,
    id_promocion INT,
    id_campaña_promocion INT,
    FOREIGN KEY (id_promocion) REFERENCES promociones(id_promocion),
    FOREIGN KEY (id_campaña_promocion) REFERENCES campañas_promocion(id_campaña_promocion)
);

--Tablas a considerar 

CREATE TABLE financiador(
    id_financiador INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    nombre_empresa VARCHAR(100) NOT NULL,
    estado_financiador BOOLEAN DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
);

CREATE TABLE inversion(
    id_inversion INT AUTO_INCREMENT PRIMARY KEY,
    id_financiador INT, 
    monto_invertido DECIMAL(10,2) NOT NULL,
    fecha_inversion DATE NOT NULL,
    plazo_meses INT NOT NULL,
    id_condiciones INT,
    tasa_interes DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (id_condiciones) REFERENCES condiciones(id_condiciones),
    FOREIGN KEY (id_financiador) REFERENCES financiador(id_financiador)
);

CREATE TABLE condiciones(
    id_condiciones INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(255) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE retiro_inversion(
    id_retiro_inversion INT AUTO_INCREMENT PRIMARY KEY,
    id_inversion INT,
    fecha_retiro DATE NOT NULL,
    monto_retirado DECIMAL(10,2) NOT NULL,
    id_pago INT, 
    FOREIGN KEY (id_inversion) REFERENCES inversion(id_inversion),
    FOREIGN KEY (id_pago) REFERENCES pago(id_pago)
);

-- CAJA

CREATE TABLE caja(
    id_caja INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT,
    monto_asignado DECIMAL(10,2) NOT NULL,
    fecha_apertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre TIMESTAMP,
    estado_caja ENUM('Abierta', 'Cerrada') DEFAULT 'Abierta',
    FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
);

CREATE TABLE movimiento_caja(
    id_movimiento_caja INT AUTO_INCREMENT PRIMARY KEY,
    id_caja INT,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_movimiento ENUM('Ingreso', 'Egreso') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    id_usuario INT,
    FOREIGN KEY (id_caja) REFERENCES caja(id_caja),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

-- fondos 

CREATE TABLE fondos(
    id_fondo INT AUTO_INCREMENT PRIMARY KEY,
    cartera_normal DECIMAL(15,2) NOT NULL,
    cartera_vencida DECIMAL(15,2) NOT NULL,
    total_fondos DECIMAL(15,2) NOT NULL,
    actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE movimiento_fondo(
    id_mov_fondo INT AUTO_INCREMENT PRIMARY KEY,
    id_fondo INT,
    id_caja INT,
    monto DECIMAL(15,2) NOT NULL,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_movimiento ENUM('aporte', 'desembolso') NOT NULL,
    FOREIGN KEY (id_fondo) REFERENCES fondos(id_fondo),
    FOREIGN KEY (id_caja) REFERENCES caja(id_caja)
);

-- Auditoria

CREATE TABLE auditoria_cambios(
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
    tabla_afectada VARCHAR(100) NOT NULL,
    id_registro INT NOT NULL,
    tipo_cambio ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    realizado_por INT,
    valores_anteriores JSON,
    valores_nuevos JSON,
    FOREIGN KEY (realizado_por) REFERENCES usuario(id_usuario)
);

-- Indices 

CREATE INDEX idx_datos_persona_genero ON datos_persona(genero);
CREATE UNIQUE INDEX idx_documento_identidad_numero ON documento_identidad(numero_documento);

CREATE INDEX idx_email_persona ON email(id_datos_persona, es_principal);
CREATE INDEX idx_telefono_persona ON telefono(id_datos_persona, es_principal);

CREATE INDEX idx_cliente_datos ON cliente(id_datos_persona);
CREATE INDEX idx_ingresos_cliente ON ingresos_egresos(id_cliente);
CREATE INDEX idx_fuente_ingreso ON fuente_ingreso(id_ingresos_egresos);
CREATE INDEX idx_doc_cliente ON documentacion_cliente(id_cliente,id_tipo_documento);
CREATE INDEX idx_puntaje_cliente ON puntaje_crediticio(id_cliente, id_nivel_riesgo);

CREATE INDEX idx_prestamo_cliente ON prestamo(id_cliente);
CREATE INDEX idx_prestamo_estado ON prestamo(id_estado_prestamo);
CREATE INDEX idx_prestamo_tipo ON prestamo(id_tipo_prestamo);
CREATE INDEX idx_prestamo_fecha ON prestamo (fecha_solicitud);
CREATE INDEX idx_prestamo_creador ON prestamo (creado_por);
CREATE INDEX idx_cronograma_prestamo ON cronograma_cuota(id_prestamo,estado_cuota);

CREATE INDEX idx_pago_prestamo ON pago(id_prestamo);
CREATE INDEX idx_pago_metodo ON pago(metodo_pago);
CREATE INDEX idx_asignacion_pago ON asignacion_pago(id_pago, id_cronograma_cuota);
CREATE INDEX idx_factura_pago ON facturas(id_pago);

CREATE INDEX idx_propiedades_cliente ON propiedades(id_cliente);
CREATE INDEX idx_propiedades_direccion ON propiedades(id_direccion);
CREATE INDEX idx_prestamo_propiedad ON prestamo_propiedad(id_prestamo, id_propiedad);
CREATE INDEX idx_garantia_prestamo_cliente ON garantia(id_prestamo, id_cliente);

CREATE INDEX idx_evaluacion_cliente ON evaluacion_prestamo(id_cliente, id_prestamo);
CREATE INDEX idx_detalle_evaluacion ON detalle_evaluacion(id_evaluacion_prestamo);

CREATE UNIQUE INDEX idx_usuario_nombre ON usuarios(nombre_usuario);
CREATE INDEX idx_usuario_roles ON usuario_roles(id_usuario, id_rol);
CREATE INDEX idx_roles_permisos ON roles_permisos(id_rol, id_permiso);

CREATE INDEX idx_notificacion_cliente ON notificaciones(id_cliente);
CREATE INDEX idx_notificacion_plantilla ON notificaciones(id_plantilla_notificacion);

CREATE INDEX idx_empeleado_datos ON empleado(id_datos_persona);
CREATE INDEX idx_asistencia_empleado ON asistencia_empleado(id_empleado);
CREATE INDEX idx_nomina_empleado ON nomina_empleado(id_empleado);
CREATE INDEX idx_deducciones_nomina ON deducciones_empleado(id_nomina_empleado);
CREATE INDEX idx_beneficios_nomina ON beneficios_empleado(id_nomina_empleado);
CREATE INDEX idx_horas_extra ON horas_extra(id_empleado);

CREATE INDEX idx_asignacion_promocion ON asignacion_promocion(id_cliente);
CREATE INDEX idx_promocion_tipo ON promociones(id_tipo_promocion);
CREATE INDEX idx_promocion_campaña ON promociones_campaña(id_promocion, id_campaña_promocion);

CREATE INDEX idx_financiador_persona ON financiador(id_datos_persona);
CREATE INDEX idx_inversion_financiador ON inversion(id_financiador);
CREATE INDEX idx_retiro_inversion ON retiro_inversion(id_inversion);

CREATE INDEX idx_caja_empleado ON caja(id_empleado);
CREATE INDEX idx_movimiento_caja ON movimiento_caja(id_caja);
CREATE INDEX idx_movimiento_fondo ON movimiento_fondo(id_fondo, id_caja);

CREATE INDEX idx_auditoria_tabla_fecha ON auditoria_cambios(tabla_afectada, fecha_cambio);

-- Logica de negocio

CREATE PROCEDURE generar_pago(IN p_id_prestamo INT, IN p_monto_pago DECIMAL(10,2), IN p_metodo_pago INT, IN p_tipo_moneda INT, IN p_creado_por INT, IN p_fecha_pago DATE, OUT p_id_pago INT)
BEGIN
    DECLARE v_saldo_restante DECIMAL(10,2);
    DECLARE v_monto DECIMAL(10,2);
    DECLARE v_id_cuota INT;
    DECLARE v_total_prestamo INT;
    DECLARE v_estado VARCHAR(20);

    SELECT COUNT(*) INTO v_total_prestamo FROM prestamo WHERE id_prestamo = p_id_prestamo;
    IF v_total_prestamo = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = "El prestamo no existe.";
    END IF;

    IF p_monto_pago <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = "El monto del pagado no es valido.";
    END IF;

    INSERT INTO pago (id_prestamo, fecha_pago, monto_pagado, metodo_pago, id_tipo_moneda, creado_por);
    VALUES (p_id_prestamo, p_fecha_pago, p_monto_pago, p_metodo_pago, p_tipo_moneda, p_creado_por);
    SET p_id_pago = LAST_INSERT_ID();
    SET v_monto = p_monto_pago;

    SELECT COUNT(*) INTO v_total_prestamo
    FROM cronograma_cuota
    WHERE id_prestamo = p_id_prestamo AND estado_cuota != 'Pagada';

    IF v_total_prestamo = 0 THEN
        UPDATE prestamo SET id_estado_prestamo = (SELECT id_estado_prestamo FROM cat_estado_prestamo WHERE estado_prestamo = 'Pagado' LIMIT 1)
        WHERE id_prestamo = p_id_prestamo;
    END IF;
END;

CREATE PROCEDURE generar_cronograma(IN p_id_prestamo INT)
BEGIN
    DECLARE v_monto_total DECIMAL(10,2);
    DECLARE v_plazo INT;
    DECLARE v_tasa_interes DECIMAL(5,2);
    DECLARE v_num_cuotas INT;
    DECLARE v_interes_total DECIMAL(10,2);
    DECLARE v_fecha_inicial DATE;
    DECLARE v_capital_cuota DECIMAL(10,2);
    DECLARE v_interes_cuota DECIMAL(10,2);
    DECLARE i INT DEFAULT 1;

    SELECT monto_solicitado, plazo_meses, tp.tasa_interes
    INTO v_monto_total, v_plazo, v_tasa_interes
    FROM prestamo p
    JOIN tipo_prestamo tp ON p.id_tipo_prestamo = tp.id_tipo_prestamo
    WHERE p.id_prestamo = p_id_prestamo;
    IF v_plazo IS NULL OR v_plazo = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Plazo no válido para el préstamo.';
    END IF;
    SET v_fecha_inicial = (SELECT COALESCE(fecha_otorgamiento, CURDATE())FROM prestamo WHERE id_prestamo = p_id_prestamo);

    SET v_interes_total = v_monto_total * (v_tasa_interes / 100) * (v_plazo / 12);
    SET v_num_cuotas = (v_monto_total + v_interes_total) / v_plazo;
    SET v_capital_cuota = v_monto_total / v_plazo;
    SET v_interes_cuota = v_interes_total / v_plazo;

    WHILE i <= v_plazo DO
        INSERT INTO cronograma_cuota (id_prestamo, numero_cuota, fecha_vencimiento, capital_cuota, interes_cuota, total_monto, saldo_pendiente, estado_cuota)
        VALUES (
            p_id_prestamo,
            i,
            DATE_ADD(v_fecha_inicial, INTERVAL i MONTH),
            ROUND(v_capital_cuota, 2),
            ROUND(v_interes_cuota, 2),
            ROUND(v_num_cuotas, 2)
            ROUND(GREATEST(v_monto_total - (v_capital_cuota * (i-1)), 0), 2),
            'Pendiente'
        );
        SET i = i + 1;
    END WHILE;
END;

CREATE TRIGGER validar_capacidad_pago
BEFORE INSERT ON prestamo
FOR EACH ROW 
BEGIN
    DECLARE v_ingresos DECIMAL(20,2) DEFAULT 0.00;
    DECLARE v_egresos DECIMAL(20,2) DEFAULT 0.00;
    DECLARE v_capacidad_pago DECIMAL(20,2);
    DECLARE v_cuota_estimada DECIMAL(20,2);

    SELECT ingresos_mensuales, egresos_mensuales INTO v_ingresos, v_egresos
    FROM ingresos_egresos
    WHERE id_cliente = NEW.id_cliente
    LIMIT 1;

    IF v_ingresos IS NULL THEN
        SET v_capacidad_pago = v_ingresos - v_egresos;
        IF v_capacidad_pago <= 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Capacidad de pago insuficiente ';
        END IF;
        
        DECLARE v_tasa_interes DECIMAL(5.2); DEFAULT 0;
        SELECT tasa_interes INTO v_tasa_interes FROM tipo_prestamo WHERE id_tipo_prestamo = NEW.id_tipo_prestamo LIMIT 1;
        IF v_tasa_interes IS NULL THEN SET v_tasa_interes = 0; END IF;
        SET v_cuota_estimada = (NEW.monto_solicitado * (NEW.monto_solicitado * (v_tasa_interes/100) * (NEW.plazo_meses/12))) / NEW.plazo_meses;

        IF v_cuota_estimada / v_capacidad_pago > 0.4 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuota estimada excede el 40% de la capacidad de pago.';
        END IF;
    END IF;
END;

CREATE TRIGGER actualizar_estado_prestamo
AFTER UPDATE ON cronograma_cuota 
FOR EACH ROW 
BEGIN 
    DECLARE v_total_pendientes INT DEFAULT 0;
    DECLARE v_total_vencidad INT DEFAULT 0;
    DECLARE v_id_prestamo INT;
    SET v_id_prestamo = NEW.id_prestamo;

    SELECT COUNT(*) INTO v_total_pendientes FROM cronograma_cuota WHERE id_prestamo = v_id_prestamo AND estado_cuota = 'Pendiente';
    SELECT COUNT(*) INTO v_total_vencidad FROM cronograma_cuota WHERE id_prestamo = v_id_prestamo AND estado_cuota = 'Vencida';

    IF v_total_pendientes = 0 AND v_total_vencidad = 0 THEN
        UPDATE prestamo SET estado_prestamo = 'Pagado' WHERE id_prestamo = v_id_prestamo;
    ELSE
        UPDATE prestamo SET estado_prestamo = 'En curso' WHERE id_prestamo = v_id_prestamo;
    END IF;
END;

CREATE TRIGGER auditoria_cambios
AFTER UPDATE ON prestamo 
FOR EACH ROW 
BEGIN
    INSERT INTO auditoria_cambios(
        tabla_afectada, id_registro, tipo_cambio, fecha_cambio, realizado_por, valores_anteriores, valores_nuevos
    ) VALUES (
        'prestamo',
        OLD.id_prestamo,
        'UPDATE',
        NOW(),
        COALESCE(NEW.creado_por, OLD.creado_por)
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
END;

CREATE TRIGGER actualizacion_prestamo_protegido
BEFORE UPDATE ON prestamo
FOR EACH ROW 
BEGIN 
    DECLARE v_estado INT;
    SET v_estado = OLD.id_estado_prestamo;
    IF v_estado IN (3,5) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede modificar un préstamo en estado En mora o Pagado.';
    END IF;
END;
