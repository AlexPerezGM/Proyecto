CREATE DATABASE p_test2;

USE p_test2;

-- Catalogos generales
CREATE TABLE cat_genero(
    id_genero INT AUTO_INCREMENT PRIMARY KEY,
    genero VARCHAR(50) NOT NULL
)ENGINE = INNODB;
Insert into cat_genero (genero) values ('Masculino'), ('Femenino'), ('Otro');

CREATE TABLE cat_tipo_pago(
    id_tipo_pago INT AUTO_INCREMENT PRIMARY KEY,
    tipo_pago VARCHAR(50) NOT NULL
)ENGINE = INNODB;
Insert into cat_tipo_pago (tipo_pago) values ('Efectivo'), ('Transferencia'), ('Cheque');

CREATE TABLE cat_estado_prestamo(
    id_estado_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    estado VARCHAR(50) NOT NULL
)ENGINE = INNODB;
Insert into cat_estado_prestamo (estado) values ('Activo'), ('En evaluacion'), ('Desembolso'),('Rechazado'), ('En mora'), ('Cerrado');

CREATE TABLE cat_periodo_pago(
    id_periodo_pago INT AUTO_INCREMENT PRIMARY KEY,
    periodo VARCHAR(50) NOT NULL
)ENGINE = INNODB;
Insert into cat_periodo_pago (periodo) values ('Semanal'), ('Quincenal'), ('Mensual');

CREATE TABLE cat_tipo_documento(
    id_tipo_documento INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento VARCHAR(50) NOT NULL
)ENGINE = INNODB;
Insert into cat_tipo_documento (tipo_documento) values ('Cedula'), ('Pasaporte'), ('Licencia de conducir');

CREATE TABLE cat_nivel_riesgo(
    id_nivel_riesgo INT AUTO_INCREMENT PRIMARY KEY,
    nivel VARCHAR(50) NOT NULL
)ENGINE = INNODB;
Insert into cat_nivel_riesgo (nivel) values ('Bajo'), ('Medio'), ('Alto');

CREATE TABLE cat_tipo_entidad(
    id_tipo_entidad INT AUTO_INCREMENT PRIMARY KEY,
    tipo_entidad VARCHAR(50) NOT NULL
)ENGINE = INNODB;
Insert into cat_tipo_entidad (tipo_entidad) values ('Cliente'), ('Empleado'), ('Propiedad'), ('Financiador');

CREATE TABLE cat_tipo_contrato(
    id_tipo_contrato INT AUTO_INCREMENT PRIMARY KEY,
    tipo_contrato VARCHAR(50) NOT NULL
)ENGINE = INNODB;
Insert into cat_tipo_contrato (tipo_contrato) values ('Medio tiempo'), ('Tiempo completo'), ('Pasantia');

CREATE TABLE cat_tipo_moneda(
    id_tipo_moneda INT AUTO_INCREMENT PRIMARY KEY,
    tipo_moneda VARCHAR(50) NOT NULL,
    valor Decimal(10,2) NOT NULL
)ENGINE = INNODB;
Insert into cat_tipo_moneda (tipo_moneda, valor) values ('DOP', 1.00), ('USD', 62.97);

CREATE TABLE cat_tipo_deduccion(
    id_tipo_deduccion INT AUTO_INCREMENT PRIMARY KEY,
    tipo_deduccion VARCHAR(50) NOT NULL,
    valor DECIMAL(5,2) NOT NULL
)ENGINE = INNODB;
Insert into cat_tipo_deduccion (tipo_deduccion, valor) values ('ISR', 0.00), ('AFP', 2.87), ('SFS', 3.04);

CREATE TABLE cat_documentacion_cliente(
    id_documentacion_cliente INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documentacion VARCHAR (100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL
)ENGINE = INNODB;
INSERT INTO cat_documentacion_cliente (tipo_documentacion, descripcion) VALUES 
('Identificacion oficial', 'Cedula, pasaporte o licencia de conducir'),
('Comprobante de ingresos', 'Recibos de sueldo, estados de cuenta bancarios'),
('Referencias personales', 'Contactos que puedan dar referencias sobre el cliente');

CREATE TABLE cat_documentacion_empleado(
    id_documentacion_empleado INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documentacion VARCHAR (100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL
)ENGINE = INNODB;
INSERT INTO cat_documentacion_empleado (tipo_documentacion, descripcion) VALUES 
('Identificacion oficial', 'Cedula, pasaporte o licencia de conducir'),
('Contrato', 'Contrato laboral firmado'),
('CV', 'Curriculum Vitae del empleado');


CREATE TABLE cat_beneficio(
    id_beneficio INT AUTO_INCREMENT PRIMARY KEY,
    tipo_beneficio VARCHAR(50) NOT NULL,
    valor DECIMAL(5,2) NOT NULL
)ENGINE = INNODB;
Insert into cat_beneficio (tipo_beneficio, valor) values ('Seguro médico', 0.00), ('Bonificación', 0.00), ('Vacaciones pagadas', 0.00);

CREATE TABLE cat_tipo_amortizacion(
    id_tipo_amortizacion INT AUTO_INCREMENT PRIMARY KEY,
    tipo_amortizacion VARCHAR(50) NOT NULL
)ENGINE = INNODB;
Insert into cat_tipo_amortizacion (tipo_amortizacion) values ('Metodo Francés'), ('Metodo Alemán'), ('Diferida');

CREATE TABLE cat_tipo_garantia(
    id_tipo_garantia INT AUTO_INCREMENT PRIMARY KEY,
    tipo_garantia VARCHAR(100) NOT NULL,
    descripcion VARCHAR (255)
)ENGINE = INNODB;
Insert into cat_tipo_garantia (tipo_garantia, descripcion) values 
('Propiedad inmueble', 'Bienes raíces como casas o terrenos'),
('Vehículo', 'Automóviles, motocicletas u otros vehículos'),
('Prendas u otros', 'Joyas, electrodomésticos u objetos de valor'),
('Garante', 'Compromiso de un tercero para pagar en caso de incumplimiento');

CREATE TABLE configuracion (
    id_configuracion INT AUTO_INCREMENT PRIMARY KEY,
    nombre_configuracion VARCHAR(100) NOT NULL,
    valor_decimal DECIMAL(14,2) NOT NULL,
    estado ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)ENGINE = INNODB;
INSERT INTO configuracion (nombre_configuracion, valor_decimal) VALUES 
('AFP_PORCENTAJE', 2.87),
('SFS_PORCENTAJE', 3.04),
('ISR_TRAMO1', 52152),
('ISR_TRAMO2', 78281),
('ISR_TRAMO3', 109324),
('ISR_TASA_TRAMO2', 0.15),
('ISR_TASA_TRAMO3', 0.20),
('ISR_TASA_TRAMO4', 0.25),
('MAX_PORCENTAJE_CAPACIDAD_PAGO', 0.40),
('precio_horas_extra', 150.00),
('BENEFICIO_SEGURO_MEDICO', 0.00),
('BENEFICIO_BONIFICACION', 0.00),
('BENEFICIO_VACACIONES_PAGADAS', 0.00);


CREATE TABLE config_mora (
    id_mora INT AUTO_INCREMENT PRIMARY KEY,
    fecha_registro DATE NOT NULL,
    dias_gracia INT NOT NULL DEFAULT 3, 
    porcentaje_mora DECIMAL(14,2) NOT NULL,
    vigente_desde DATE NOT NULL,
    vigente_hasta DATE,
    estado ENUM('Activo', 'Inactivo') DEFAULT 'Activo'
)ENGINE = INNODB;
INSERT INTO config_mora (fecha_registro, porcentaje_mora, dias_gracia, vigente_desde, estado)
VALUES (CURDATE() ,10.00, 3, CURDATE(), 'Activo');

 CREATE TABLE config_notificacion (
    id_config INT PRIMARY KEY AUTO_INCREMENT,
    dias_anticipacion INT DEFAULT 0,
    metodo VARCHAR(20) DEFAULT 'email',
    fecha_envio DATE NULL,
    escalamiento TEXT NULL
  ) ENGINE=INNODB;

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
    CONSTRAINT fk_genero FOREIGN KEY (genero) REFERENCES cat_genero(id_genero)
)ENGINE = INNODB;

CREATE TABLE email(
    id_email INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    email VARCHAR(100) NOT NULL,
    es_principal BOOLEAN DEFAULT TRUE,
    CONSTRAINT uq_email UNIQUE (id_datos_persona, email),
    CONSTRAINT fk_datospersona_email FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE telefono(
    id_telefono INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT NOT NULL,
    telefono VARCHAR(20) NOT NULL ,
    es_principal BOOLEAN DEFAULT TRUE,
    CONSTRAINT uq_telefono UNIQUE (id_datos_persona, telefono),
    CONSTRAINT fk_datospersona_telefono FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE documento_identidad(
    id_documento_identidad INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT NOT NULL,
    id_tipo_documento INT NOT NULL,
    numero_documento VARCHAR(50) NOT NULL,
    fecha_emision DATE NOT NULL,
    UNIQUE (id_tipo_documento, numero_documento),
    CONSTRAINT fk_datospersona_documentoI FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tipodocumento_documentoI FOREIGN KEY (id_tipo_documento) REFERENCES cat_tipo_documento(id_tipo_documento)
)ENGINE = INNODB;

CREATE TABLE direccion(
    id_direccion INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    sector VARCHAR(100) NOT NULL,
    calle VARCHAR(100) NOT NULL,
    numero_casa INT NOT NULL,
    CONSTRAINT fk_datospersona_direccion FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE direccion_entidad(
    id_direccion INT,
    id_tipo_entidad INT,
    tipo_direccion VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_direccion, id_tipo_entidad),
    CONSTRAINT fk_direccion_entidad FOREIGN KEY (id_direccion) REFERENCES direccion(id_direccion)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tipo_entidad FOREIGN KEY (id_tipo_entidad) REFERENCES cat_tipo_entidad(id_tipo_entidad)
)ENGINE = INNODB;

-- Usuarios y roles 

CREATE TABLE usuario(
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    nombre_usuario VARCHAR(50) UNIQUE NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    CONSTRAINT fk_datospersona_usuario FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE roles(
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) UNIQUE NOT NULL,
    descripcion VARCHAR(255) NOT NULL
)ENGINE = INNODB;
INSERT INTO roles (nombre_rol, descripcion) VALUES
('Administrador', 'Acceso completo al sistema'),
('Empleado', 'Acceso limitado a funciones especificas'),
('Supervisor', 'Acceso a funciones de supervisión y reportes');

CREATE TABLE permisos(
    id_permiso INT AUTO_INCREMENT PRIMARY KEY,
    nombre_permiso VARCHAR(100) UNIQUE NOT NULL,
    descripcion VARCHAR(255) NOT NULL
)ENGINE = INNODB;
INSERT INTO permisos (nombre_permiso, descripcion) VALUES
('Admin', 'Administracion general'),
('Prestamos', 'Administrar prestamos'),
('Clientes', 'Administrar clientes'),
('rrhh', 'Administrar recursos humanos');

CREATE TABLE usuarios_roles(
    id_usuario INT,
    id_rol INT,
    PRIMARY KEY (id_usuario, id_rol),
    CONSTRAINT fk_usuario_roles FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_rol_usuario FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
)ENGINE = INNODB;

CREATE TABLE roles_permisos(
    id_rol INT,
    id_permiso INT,
    PRIMARY KEY (id_rol, id_permiso),
    CONSTRAINT fk_roles FOREIGN KEY (id_rol) REFERENCES roles(id_rol),
    CONSTRAINT fk_permisos FOREIGN KEY (id_permiso) REFERENCES permisos(id_permiso)
)ENGINE = INNODB;
INSERT INTO roles_permisos (id_rol, id_permiso) VALUES
(1, 1), (1, 2), (1, 3), (1, 4);

CREATE TABLE historial_acceso(
    id_historial_acceso INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    fecha_acceso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_acceso VARCHAR(45),
    accion VARCHAR(255) NOT NULL,
    CONSTRAINT fk_usuario_historial FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
)ENGINE = INNODB;

-- Tablas para clientes

CREATE TABLE cliente(
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT UNIQUE,
    CONSTRAINT fk_datospersona_cliente FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE ingresos_egresos(
    id_ingresos_egresos INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    ingresos_mensuales DECIMAL(14,2) NOT NULL,
    egresos_mensuales DECIMAL(14,2) NOT NULL,
    CONSTRAINT chk_ingresos_egresos CHECK (ingresos_mensuales >= 10000 AND egresos_mensuales < ingresos_mensuales),
    CONSTRAINT fk_cliente_ingresosegresos FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE fuente_ingreso(
    id_fuente_ingreso INT AUTO_INCREMENT PRIMARY KEY,
    id_ingresos_egresos INT,
    fuente VARCHAR(100) NOT NULL,
    CONSTRAINT fk_ingresos_fuenteI FOREIGN KEY (id_ingresos_egresos) REFERENCES ingresos_egresos(id_ingresos_egresos)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE ocupacion(
    id_ocupacion INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    ocupacion VARCHAR(100) NOT NULL,
    empresa VARCHAR(100) NOT NULL,
    CONSTRAINT fk_datospersona_ocupacion FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE documentacion_cliente(
    id_cliente INT,
    id_documentacion_cliente INT,
    ruta_documento VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_cliente, id_documentacion_cliente),
    CONSTRAINT fk_cliente_documentos FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tipo_documentoC FOREIGN KEY (id_documentacion_cliente) REFERENCES cat_documentacion_cliente(id_documentacion_cliente)
)ENGINE = INNODB;

CREATE TABLE puntaje_crediticio(
    id_puntaje_crediticio INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    id_nivel_riesgo INT,
    puntaje INT DEFAULT 0,
    total_transacciones INT NOT NULL,
    CONSTRAINT fk_cliente_puntaje FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_nivel_riesgo_puntaje FOREIGN KEY (id_nivel_riesgo) REFERENCES cat_nivel_riesgo(id_nivel_riesgo)
)ENGINE = INNODB;

-- Tabla prestamos 

CREATE TABLE tipo_prestamo(
    id_tipo_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tasa_interes DECIMAL(5,2) NOT NULL,
    monto_minimo DECIMAL(14,2) NOT NULL,
    id_tipo_amortizacion INT NOT NULL,
    plazo_minimo_meses INT NOT NULL,
    plazo_maximo_meses INT NOT NULL,
    CONSTRAINT fk_tipo_amortizacion FOREIGN KEY (id_tipo_amortizacion) REFERENCES cat_tipo_amortizacion(id_tipo_amortizacion)
)ENGINE = INNODB;
INSERT INTO tipo_prestamo (nombre, tasa_interes, monto_minimo, id_tipo_amortizacion, plazo_minimo_meses, plazo_maximo_meses)
VALUES 
('Personal', 12.5, 10000.00, 1, 6, 24),
('Hipotecario', 8.5, 50000.00, 2, 12, 180);

CREATE TABLE condicion_prestamo(
    id_condicion_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    tasa_interes DECIMAL(5,2) NOT NULL,
    id_tipo_amortizacion INT NOT NULL,
    id_periodo_pago INT NOT NULL,
    vigente_desde DATE NOT NULL,
    vigente_hasta DATE,
    esta_activo BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_periodo_pago_condicion FOREIGN KEY (id_periodo_pago) REFERENCES cat_periodo_pago(id_periodo_pago)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tipo_amortizacion_condicion FOREIGN KEY (id_tipo_amortizacion) REFERENCES cat_tipo_amortizacion(id_tipo_amortizacion)
)ENGINE = INNODB;

CREATE TABLE prestamo(
    id_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT, 
    id_tipo_prestamo INT,
    numero_contrato VARCHAR(75) UNIQUE NOT NULL,
    monto_solicitado DECIMAL(14,2) NOT NULL,
    fecha_solicitud DATE NOT NULL,
    plazo_meses INT NOT NULL,
    id_estado_prestamo INT,
    id_condicion_actual INT,
    creado_por INT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_monto_solicitado CHECK (monto_solicitado >= 10000.00),
    CONSTRAINT chk_plazo_meses CHECK (plazo_meses >= 6 AND plazo_meses <= 180),
    CONSTRAINT fk_cliente_prestamo FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tipo_prestamo_p FOREIGN KEY (id_tipo_prestamo) REFERENCES tipo_prestamo(id_tipo_prestamo),
    CONSTRAINT fk_estado_prestamo_p FOREIGN KEY (id_estado_prestamo) REFERENCES cat_estado_prestamo(id_estado_prestamo),
    CONSTRAINT fk_prestamo_condicion FOREIGN KEY (id_condicion_actual) REFERENCES condicion_prestamo(id_condicion_prestamo),
    CONSTRAINT fk_usuario_prestamo FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario)
)ENGINE = INNODB;

CREATE TABLE prestamo_personal(
    id_prestamo_personal INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    motivo VARCHAR(255) NOT NULL,
    CONSTRAINT fk_prestamo_personal FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE prestamo_hipotecario(
    id_prestamo_hipotecario INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    valor_propiedad DECIMAL(14,2) NOT NULL,
    porcentaje_financiamiento DECIMAL(5,2) NOT NULL,
    direccion_propiedad VARCHAR(255) NOT NULL,
    CONSTRAINT fk_prestamo_hipotecario FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE propiedades(
    id_propiedad INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT, 
    id_direccion INT,
    valor_propiedad DECIMAL(14,2) NOT NULL,
    fecha_registro DATE NOT NULL,
    CONSTRAINT fk_cliente_propiedad FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    CONSTRAINT fk_direccion_propiedad FOREIGN KEY (id_direccion) REFERENCES direccion(id_direccion)
)ENGINE = INNODB;

CREATE TABLE prestamo_propiedad(
    id_prestamo INT,
    id_propiedad INT,
    porcentaje_garantia DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (id_prestamo, id_propiedad),
    CONSTRAINT chk_porcentaje_garantia CHECK (porcentaje_garantia <= 80),
    CONSTRAINT fk_prestamo_propiedad FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_propiedad_prestamo FOREIGN KEY (id_propiedad) REFERENCES propiedades(id_propiedad)
)ENGINE = INNODB;

CREATE TABLE garantia(
    id_garantia INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    id_cliente INT,
    descripcion VARCHAR(255) NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    CONSTRAINT fk_cliente_garantia FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    CONSTRAINT fk_prestamo_garantia FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
)ENGINE = INNODB;

CREATE TABLE detalle_garantia(
    id_detalle_garantia INT AUTO_INCREMENT PRIMARY KEY,
    id_garantia INT,
    id_tipo_garantia INT,
    descripcion VARCHAR(255) NOT NULL,
    valor_estimado DECIMAL(14,2) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado_garantia ENUM('Activa', 'Liberada', 'En uso', 'En verificacion') DEFAULT 'Activa',
    CONSTRAINT fk_garantia_detalle_garantia FOREIGN KEY (id_garantia) REFERENCES garantia(id_garantia)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detalle_garantia_tipo FOREIGN KEY (id_tipo_garantia) REFERENCES cat_tipo_garantia(id_tipo_garantia)   
)ENGINE = INNODB;

CREATE TABLE documento_garantia (
    id_documento_garantia INT AUTO_INCREMENT PRIMARY KEY,
    id_detalle_garantia INT,
    ruta_documento VARCHAR(255) NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documento_garantia_detalle FOREIGN KEY (id_detalle_garantia) REFERENCES detalle_garantia(id_detalle_garantia)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE desembolso(
    id_desembolso INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    monto_desembolsado DECIMAL(14,2) NOT NULL,
    fecha_desembolso DATE NOT NULL,
    metodo_entrega INT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT,
    CONSTRAINT chk_monto_desembolsado CHECK (monto_desembolsado > 0),
    CONSTRAINT fk_prestamo_desembolso FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_metodo_entrega_desembolso FOREIGN KEY (metodo_entrega) REFERENCES cat_tipo_pago(id_tipo_pago),
    CONSTRAINT fk_usuario_desembolso FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario)
)ENGINE = INNODB;

CREATE TABLE cronograma_cuota(
    id_cronograma_cuota INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    numero_cuota INT NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    capital_cuota DECIMAL(14,2) NOT NULL,
    interes_cuota DECIMAL(14,2) NOT NULL,
    cargos_cuota DECIMAL(14,2) DEFAULT 0.00,
    total_monto DECIMAL(14,2) NOT NULL,
    saldo_cuota DECIMAL(14,2) NOT NULL,
    estado_cuota ENUM('Pendiente', 'Pagada', 'Vencida') DEFAULT 'Pendiente',
    CONSTRAINT uq_numero_cuota UNIQUE (id_prestamo, numero_cuota),
    CONSTRAINT fk_prestamo_cronograma FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE resumen_cronograma (
    id_resumen_cronograma INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT NOT NULL,
    total_capital DECIMAL (14,2) DEFAULT 0.00,
    total_interes DECIMAL (14,2) DEFAULT 0.00,
    total_pagar DECIMAL (14,2) DEFAULT 0.00,
    CONSTRAINT fk_resumen_cronograma_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

-- Garantes

CREATE TABLE garante(
    id_garante INT AUTO_INCREMENT PRIMARY KEY,
    id_detalle_garantia INT,
    id_datos_persona INT,
    relacion_con_cliente VARCHAR(100),
    estado_garante ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    CONSTRAINT fk_garante_detalle_garantia FOREIGN KEY (id_detalle_garantia) REFERENCES detalle_garantia(id_detalle_garantia)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_garante_datospersona FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
)ENGINE = INNODB;

CREATE TABLE documento_garante (
    id_documento_garante INT AUTO_INCREMENT PRIMARY KEY,
    id_garante INT,
    ruta_documento VARCHAR(255) NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documento_garante FOREIGN KEY (id_garante) REFERENCES garante(id_garante)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

-- Seguimiento de prestamos 

CREATE TABLE evaluacion_prestamo(
    id_evaluacion_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    id_prestamo INT,
    capacidad_pago DECIMAL(14,2) NOT NULL,
    nivel_riesgo INT,
    estado_evaluacion ENUM('Aprobado', 'Rechazado', 'Pendiente') NOT NULL DEFAULT 'Pendiente',
    fecha_evaluacion DATE NOT NULL,
    CONSTRAINT fk_cliente_evaluacion FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    CONSTRAINT fk_prestamo_evaluacion FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo),
    CONSTRAINT fk_nivel_riesgo_evaluacion FOREIGN KEY (nivel_riesgo) REFERENCES cat_nivel_riesgo(id_nivel_riesgo)
)ENGINE = INNODB;

CREATE TABLE detalle_evaluacion(
    id_detalle_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
    id_evaluacion_prestamo INT,
    fecha DATE NOT NULL,
    observacion TEXT NOT NULL,
    evaluado_por INT,
    CONSTRAINT fk_detalle_evaluacion_prestamo FOREIGN KEY (id_evaluacion_prestamo) REFERENCES evaluacion_prestamo(id_evaluacion_prestamo),
    CONSTRAINT fk_usuario_evaluacion FOREIGN KEY (evaluado_por) REFERENCES usuario(id_usuario)
)ENGINE = INNODB;

-- notificaciones 

CREATE TABLE plantilla_notificacion(
    id_plantilla_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    tipo_notificacion VARCHAR(100) NOT NULL,
    asunto VARCHAR(150) NOT NULL,
    cuerpo TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)ENGINE = INNODB;

CREATE TABLE notificaciones(
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    id_plantilla_notificacion INT,
    canal_envio VARCHAR(100) NOT NULL,
    programada_para TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado_envio ENUM('Enviado', 'Fallido', 'Pendiente') DEFAULT 'Pendiente',
    CONSTRAINT fk_cliente_notificacion FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_plantilla_notificacion FOREIGN KEY (id_plantilla_notificacion) REFERENCES plantilla_notificacion(id_plantilla_notificacion)
)ENGINE = INNODB;

-- Reestructuracion de prestamos 

CREATE TABLE restructuracion_prestamo(
    id_restructuracion_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    id_condicion_nueva INT,
    fecha_reestructuracion DATE NOT NULL,
    aprobado_por INT,
    notas TEXT,
    CONSTRAINT fk_restructuracion_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo),
    CONSTRAINT fk_restructuracion_condicionprestamo FOREIGN KEY (id_condicion_nueva) REFERENCES condicion_prestamo(id_condicion_prestamo),
    CONSTRAINT fk_usuario_restructuracion FOREIGN KEY (aprobado_por) REFERENCES usuario(id_usuario)
)ENGINE = INNODB;

-- Pagos

CREATE TABLE pago(
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT,
    fecha_pago DATE NOT NULL,
    monto_pagado DECIMAL(14,2) NOT NULL,
    metodo_pago INT,
    id_tipo_moneda INT,
    creado_por INT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_monto_pagado CHECK (monto_pagado > 0),
    CONSTRAINT fk_prestamo_pago FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_metodo_pagoP FOREIGN KEY (metodo_pago) REFERENCES cat_tipo_pago(id_tipo_pago),
    CONSTRAINT fk_tipo_moneda_pago FOREIGN KEY (id_tipo_moneda) REFERENCES cat_tipo_moneda(id_tipo_moneda),
    CONSTRAINT fk_usuario_pago FOREIGN KEY (creado_por) REFERENCES usuario(id_usuario)
)ENGINE = INNODB;

CREATE TABLE pago_efectivo(
    id_pago INT PRIMARY KEY,
    recibo_numero VARCHAR(50) NOT NULL UNIQUE,
    CONSTRAINT fk_pago_efectivo FOREIGN KEY (id_pago) REFERENCES pago(id_pago)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE pago_transferencia(
    id_pago INT PRIMARY KEY,
    codigo_transferencia VARCHAR(100) NOT NULL UNIQUE,
    banco VARCHAR(100) NOT NULL,
    CONSTRAINT fk_pago_transferencia FOREIGN KEY (id_pago) REFERENCES pago(id_pago)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE retiro_garantia(
    id_retiro_garantia INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT,
    id_garantia INT,
    fecha_uso DATE NOT NULL,
    monto_usado DECIMAL(14,2) NOT NULL,
    CONSTRAINT fk_retiro_garantia FOREIGN KEY (id_pago) REFERENCES pago(id_pago),
    CONSTRAINT fk_garantia FOREIGN KEY (id_garantia) REFERENCES garantia(id_garantia)
)ENGINE = INNODB;

CREATE TABLE asignacion_pago(
    id_pago INT,
    id_cronograma_cuota INT,
    monto_asignado DECIMAL(14,2) NOT NULL,
    tipo_asignacion ENUM('Capital', 'Intereses', 'Cargos') NOT NULL DEFAULT 'Cargos',
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pago, id_cronograma_cuota, tipo_asignacion),
    CONSTRAINT fk_asignacion_pago FOREIGN KEY (id_pago) REFERENCES pago(id_pago)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cronograma_asignacion FOREIGN KEY (id_cronograma_cuota) REFERENCES cronograma_cuota(id_cronograma_cuota)
)ENGINE = INNODB;

CREATE TABLE facturas(
    id_factura INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT,
    numero_factura VARCHAR(50) unique NOT NULL,
    fecha_emision DATE NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    CONSTRAINT fk_pago_factura FOREIGN KEY (id_pago) REFERENCES pago(id_pago)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

-- Reportes 

CREATE TABLE reportes(
    id_reportes INT AUTO_INCREMENT PRIMARY KEY,
    tipo_reporte VARCHAR(100) NOT NULL,
    parametros TEXT,
    fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generado_por INT,
    ruta_archivo VARCHAR(255) NOT NULL,
    CONSTRAINT fk_usuario_reportes FOREIGN KEY (generado_por) REFERENCES usuario(id_usuario)
)ENGINE = INNODB;

CREATE TABLE reportes_prestamo(
    id_reporte INT,
    id_prestamo INT,
    PRIMARY KEY (id_reporte, id_prestamo),
    CONSTRAINT fk_reporteP FOREIGN KEY (id_reporte) REFERENCES reportes(id_reportes),
    CONSTRAINT fk_prestamo_reporte FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
)ENGINE = INNODB;

CREATE TABLE reportes_cliente(
    id_reporte INT,
    id_cliente INT,
    PRIMARY KEY (id_reporte, id_cliente),
    CONSTRAINT fk_reporteC FOREIGN KEY (id_reporte) REFERENCES reportes(id_reportes),
    CONSTRAINT fk_cliente_reporte FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
)ENGINE = INNODB;

-- Administracion de empleados recursos humanos

CREATE TABLE empleado(
    id_empleado INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    CONSTRAINT fk_datospersona_empleado FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
)ENGINE = INNODB;

CREATE TABLE contrato_empleado(
    id_contrato_empleado INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT,
    cargo VARCHAR(100) NOT NULL,
    id_tipo_contrato INT,
    departamento VARCHAR(100) NULL,
    fecha_contratacion DATE NOT NULL,
    salario_base DECIMAL(14,2) NOT NULL,
    id_jefe INT,
    vigente BOOLEAN DEFAULT TRUE,
    fecha_fin DATE,
    estado_contrato ENUM('Activo', 'Finalizado', 'En renovacion') DEFAULT 'Activo',
    CONSTRAINT fk_empleado_contrato FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tipocontrato_empleado FOREIGN KEY (id_tipo_contrato) REFERENCES cat_tipo_contrato(id_tipo_contrato),
    CONSTRAINT fk_jefe_empleado FOREIGN KEY (id_jefe) REFERENCES empleado(id_empleado)
)ENGINE = INNODB;

CREATE TABLE documentacion_empleado(
    id_empleado INT,
    id_documentacion_empleado INT,
    ruta_documento VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_empleado, id_documentacion_empleado),
    CONSTRAINT fk_empleado_documentos FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tipo_documentacion_empleado FOREIGN KEY (id_documentacion_empleado) REFERENCES cat_documentacion_empleado(id_documentacion_empleado)
)ENGINE = INNODB;

CREATE TABLE asistencia_empleado(
    id_asistencia_empleado INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT,
    hora_entrada TIMESTAMP NOT NULL,
    hora_salida TIMESTAMP,
    retrasos INT DEFAULT 0,
    CONSTRAINT fk_asistencia_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE nomina (
    id_nomina INT AUTO_INCREMENT PRIMARY KEY,
    periodo VARCHAR(50) NOT NULL,
    generado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)ENGINE = INNODB;

CREATE TABLE nomina_empleado(
    id_nomina_empleado INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT,
    id_nomina INT,
    salario_base DECIMAL(14,2) NOT NULL,
    salario_neto DECIMAL(14,2) NOT NULL,
    CONSTRAINT fk_empleado_nomina FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_nomina_empleado_nomina FOREIGN KEY (id_nomina) REFERENCES nomina(id_nomina)
)ENGINE = INNODB;

CREATE TABLE deducciones_empleado(
    id_nomina_empleado INT,
    id_tipo_deduccion INT,
    monto DECIMAL(14,2) NOT NULL,
    fecha DATE NOT NULL,
    PRIMARY KEY (id_nomina_empleado, id_tipo_deduccion),
    CONSTRAINT fk_deducciones_nomina FOREIGN KEY (id_nomina_empleado) REFERENCES nomina_empleado(id_nomina_empleado)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tipodeduccion_deducciones FOREIGN KEY (id_tipo_deduccion) REFERENCES cat_tipo_deduccion(id_tipo_deduccion)
)ENGINE = INNODB;

CREATE TABLE beneficios_empleado(
    id_nomina_empleado INT,
    id_beneficio INT,
    monto DECIMAL(14,2) NOT NULL,
    fecha DATE NOT NULL,
    PRIMARY KEY (id_nomina_empleado, id_beneficio),
    CONSTRAINT fk_beneficios_nomina FOREIGN KEY (id_nomina_empleado) REFERENCES nomina_empleado(id_nomina_empleado)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tipobeneficio_beneficios FOREIGN KEY (id_beneficio) REFERENCES cat_beneficio(id_beneficio)
)ENGINE = INNODB;

CREATE TABLE horas_extras(
    id_horas_extras INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT,
    cantidad_horas INT NOT NULL,
    pago_horas_extras DECIMAL(14,2) NOT NULL,
    monto_total DECIMAL(14,2) NOT NULL,
    fecha DATE NOT NULL,
    CONSTRAINT fk_empleado_horas_extra FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

-- Promociones y descuentos 
CREATE TABLE tipo_promocion(
    id_tipo_promocion INT AUTO_INCREMENT PRIMARY KEY,
    tipo_promocion VARCHAR(50) NOT NULL
)ENGINE = INNODB;

CREATE TABLE promociones(
    id_promocion INT AUTO_INCREMENT PRIMARY KEY,
    nombre_promocion VARCHAR(100) NOT NULL,
    estado ENUM('Activa', 'Inactiva') DEFAULT 'Inactiva',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    puntos_necesarios INT NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    id_tipo_promocion INT,
    CONSTRAINT fk_tipopromocion_promociones FOREIGN KEY (id_tipo_promocion) REFERENCES tipo_promocion(id_tipo_promocion)
)ENGINE = INNODB;

CREATE TABLE asignacion_promocion(
    id_asignacion_promocion INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    id_promocion INT,
    fecha_asignacion DATE NOT NULL,
    estado_promocion ENUM('Activa', 'Usada', 'Expirada') DEFAULT 'Activa',
    CONSTRAINT fk_asignacion_promocion_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asignacion_promocion_promocion FOREIGN KEY (id_promocion) REFERENCES promociones(id_promocion)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE uso_promocion(
    id_asignacion_promocion INT,
    id_prestamo INT,
    fecha_uso DATE NOT NULL,
    PRIMARY KEY (id_asignacion_promocion, id_prestamo),
    CONSTRAINT fk_uso_promocion_asignacionpromocion FOREIGN KEY (id_asignacion_promocion) REFERENCES asignacion_promocion(id_asignacion_promocion)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_uso_promocion_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamo(id_prestamo)
)ENGINE = INNODB;

CREATE TABLE campanas(
    id_campana INT AUTO_INCREMENT PRIMARY KEY,
    nombre_campana VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado_campana ENUM('Activa', 'Inactiva') DEFAULT 'Inactiva'
)ENGINE = INNODB;

CREATE TABLE promociones_campana(
    id_promocion INT,
    id_campana INT,
    PRIMARY KEY (id_promocion, id_campana),
    CONSTRAINT fk_promociones_campana_promocion FOREIGN KEY (id_promocion) REFERENCES promociones(id_promocion)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_campana_promocion FOREIGN KEY (id_campana) REFERENCES campanas(id_campana)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

-- Tablas a considerar 

CREATE TABLE financiador(
    id_financiador INT AUTO_INCREMENT PRIMARY KEY,
    id_datos_persona INT,
    nombre_empresa VARCHAR(100) NOT NULL,
    estado_financiador ENUM ('Activo', 'Inactivo') DEFAULT 'Activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_datospersona_financiador FOREIGN KEY (id_datos_persona) REFERENCES datos_persona(id_datos_persona)
)ENGINE = INNODB;

CREATE TABLE relacion_financiador_cliente(
    id_relacion_financiador_cliente INT AUTO_INCREMENT PRIMARY KEY,
    id_financiador INT,
    id_cliente INT,
    CONSTRAINT fk_relacion_financiador FOREIGN KEY (id_financiador) REFERENCES financiador(id_financiador)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_relacion_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE ajuste_tasa_financiador(
    id_ajuste_tasa INT AUTO_INCREMENT PRIMARY KEY,
    id_financiador INT,
    monto_minimo DECIMAL(14,2) NOT NULL,
    descuento_tasa_interes DECIMAL(5,2) NOT NULL,
    tasa_financiacion_base DECIMAL(5,2) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE,
    estado_ajuste ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    CONSTRAINT fk_financiador_ajuste_tasa FOREIGN KEY (id_financiador) REFERENCES financiador(id_financiador)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE condicion_inversion(
    id_condicion_inversion INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(255) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)ENGINE = INNODB;

CREATE TABLE inversion(
    id_inversion INT AUTO_INCREMENT PRIMARY KEY,
    id_financiador INT, 
    monto_invertido DECIMAL(14,2) NOT NULL,
    fecha_inversion DATE NOT NULL,
    plazo_meses INT NOT NULL,
    id_condicion_inversion INT,
    tasa_interes DECIMAL(5,2) NOT NULL,
    CONSTRAINT fk_condicion_inversion FOREIGN KEY (id_condicion_inversion) REFERENCES condicion_inversion(id_condicion_inversion),
    CONSTRAINT fk_financiador_inversion FOREIGN KEY (id_financiador) REFERENCES financiador(id_financiador)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE retiro_inversion(
    id_retiro_inversion INT AUTO_INCREMENT PRIMARY KEY,
    id_inversion INT,
    fecha_retiro DATE NOT NULL,
    monto_retirado DECIMAL(14,2) NOT NULL,
    id_pago INT, 
    CONSTRAINT fk_inversion_retiro_inversion FOREIGN KEY (id_inversion) REFERENCES inversion(id_inversion)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pago_retiro_inversion FOREIGN KEY (id_pago) REFERENCES pago(id_pago)
)ENGINE = INNODB;

-- CAJA

CREATE TABLE caja(
    id_caja INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT,
    monto_asignado DECIMAL(14,2) NOT NULL,
    fecha_apertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre TIMESTAMP,
    estado_caja ENUM('Abierta', 'Cerrada') DEFAULT 'Abierta',
    CONSTRAINT fk_empleado_caja FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

CREATE TABLE movimiento_caja(
    id_movimiento_caja INT AUTO_INCREMENT PRIMARY KEY,
    id_caja INT,
    id_usuario INT,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_movimiento ENUM('Ingreso', 'Egreso') NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    CONSTRAINT fk_caja_movimiento_caja FOREIGN KEY (id_caja) REFERENCES caja(id_caja)
    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_movimiento_caja FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
)ENGINE = INNODB;

-- fondos 

CREATE TABLE fondos(
    id_fondo INT AUTO_INCREMENT PRIMARY KEY,
    cartera_normal DECIMAL(14,2) NOT NULL,
    cartera_vencida DECIMAL(14,2) NOT NULL,
    total_fondos DECIMAL(14,2) NOT NULL,
    actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)ENGINE = INNODB;

CREATE TABLE movimiento_fondo(
    id_mov_fondo INT AUTO_INCREMENT PRIMARY KEY,
    id_fondo INT,
    id_caja INT,
    monto DECIMAL(14,2) NOT NULL,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_movimiento ENUM('aporte', 'desembolso') NOT NULL,
    CONSTRAINT fk_fondo_movimiento FOREIGN KEY (id_fondo) REFERENCES fondos(id_fondo),
    CONSTRAINT fk_caja_movimiento_fondo FOREIGN KEY (id_caja) REFERENCES caja(id_caja)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE = INNODB;

-- Auditoria

CREATE TABLE auditoria_cambios(
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
    tabla_afectada VARCHAR(100) NOT NULL,
    id_registro INT NOT NULL,
    tipo_cambio ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL DEFAULT 'UPDATE',
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valores_anteriores JSON,
    valores_nuevos JSON,
    realizado_por INT,
    CONSTRAINT fk_usuario_auditoria FOREIGN KEY (realizado_por) REFERENCES usuario(id_usuario)
)ENGINE = INNODB;

CREATE TABLE ajuste_empleado(
  id_ajuste_empleado INT AUTO_INCREMENT PRIMARY KEY,
  id_empleado INT NOT NULL,
  tipo ENUM('Deduccion','Beneficio') NOT NULL,
  id_tipo_deduccion INT NULL,
  id_beneficio INT NULL,
  porcentaje DECIMAL(5,2) DEFAULT NULL,
  monto DECIMAL(14,2) DEFAULT NULL,
  vigente_desde DATE NOT NULL,
  vigente_hasta DATE NOT NULL,
  estado ENUM('Activo','Inactivo') DEFAULT 'Activo',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ajuste_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado) 
  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ajuste_deduccion FOREIGN KEY (id_tipo_deduccion) REFERENCES cat_tipo_deduccion(id_tipo_deduccion),
  CONSTRAINT fk_ajuste_beneficio FOREIGN KEY (id_beneficio) REFERENCES cat_beneficio(id_beneficio),
  CONSTRAINT chk_cap_tipo_catalogo CHECK (
    (tipo='Deduccion' AND id_tipo_deduccion IS NOT NULL AND id_beneficio IS NULL) OR
    (tipo='Beneficio' AND id_beneficio IS NOT NULL AND id_tipo_deduccion IS NULL)
  )
) ENGINE=INNODB;

CREATE INDEX idx_ajuste_empleado ON ajuste_empleado(id_empleado, tipo);

-- Indices 
    
CREATE INDEX idx_persona_busqueda ON datos_persona(nombre, apellido);
CREATE INDEX idx_email_persona ON email(id_datos_persona, es_principal);
CREATE INDEX idx_telefono_persona ON telefono(id_datos_persona, es_principal);

CREATE INDEX idx_ingresos_cliente ON ingresos_egresos(id_cliente);
CREATE INDEX idx_fuente_ingreso ON fuente_ingreso(id_ingresos_egresos);
CREATE INDEX idx_puntaje_cliente ON puntaje_crediticio(id_cliente, id_nivel_riesgo);
CREATE INDEX idx_prestamo_cliente_estado ON prestamo(id_cliente, id_estado_prestamo);

CREATE INDEX idx_prestamo_estado ON prestamo(id_estado_prestamo);
CREATE INDEX idx_prestamo_tipo ON prestamo(id_tipo_prestamo);
CREATE INDEX idx_prestamo_fecha ON prestamo (fecha_solicitud);
CREATE INDEX idx_prestamo_creador ON prestamo (creado_por);
CREATE INDEX idx_prestamo_condicion ON prestamo (id_condicion_actual);
CREATE INDEX idx_cronograma_prestamo ON cronograma_cuota(id_prestamo,estado_cuota);
CREATE INDEX idx_cronograma_fecha ON cronograma_cuota(id_prestamo, fecha_vencimiento);

CREATE INDEX idx_pago_prestamo ON pago(id_prestamo);
CREATE INDEX idx_pago_fecha ON pago(fecha_pago);
CREATE INDEX idx_pago_metodo ON pago(metodo_pago);
CREATE INDEX idx_factura_pago ON facturas(id_pago);
CREATE INDEX idx_pago_rango_fechas ON pago(fecha_pago, id_prestamo);
CREATE INDEX idx_asignacion_por_cuota ON asignacion_pago(id_cronograma_cuota);
CREATE INDEX idx_pago_prestamo_fecha ON pago(id_prestamo, fecha_pago);

CREATE INDEX idx_propiedades_cliente ON propiedades(id_cliente);
CREATE INDEX idx_propiedades_direccion ON propiedades(id_direccion);
CREATE INDEX idx_garantia_prestamo_cliente ON garantia(id_prestamo, id_cliente);

CREATE INDEX idx_evaluacion_cliente ON evaluacion_prestamo(id_cliente, id_prestamo);
CREATE INDEX idx_detalle_evaluacion ON detalle_evaluacion(id_evaluacion_prestamo);
CREATE INDEX idx_evaluacion_estado ON evaluacion_prestamo(estado_evaluacion);

CREATE INDEX idx_notificacion_cliente ON notificaciones(id_cliente);
CREATE INDEX idx_notificacion_plantilla ON notificaciones(id_plantilla_notificacion);

CREATE INDEX idx_empeleado_datos ON empleado(id_datos_persona);
CREATE INDEX idx_asistencia_empleado ON asistencia_empleado(id_empleado);
CREATE INDEX idx_nomina_empleado ON nomina_empleado(id_empleado);
CREATE INDEX idx_deducciones_nomina ON deducciones_empleado(id_nomina_empleado);
CREATE INDEX idx_beneficios_nomina ON beneficios_empleado(id_nomina_empleado);
CREATE INDEX idx_horas_extra ON horas_extras(id_empleado);

CREATE INDEX idx_asignacion_promocion ON asignacion_promocion(id_cliente);
CREATE INDEX idx_promocion_tipo ON promociones(id_tipo_promocion);
CREATE INDEX idx_promocion_campana ON promociones_campana(id_promocion, id_campana);

CREATE INDEX idx_financiador_persona ON financiador(id_datos_persona);
CREATE INDEX idx_inversion_financiador ON inversion(id_financiador);
CREATE INDEX idx_retiro_inversion ON retiro_inversion(id_inversion);

CREATE INDEX idx_caja_empleado ON caja(id_empleado);
CREATE INDEX idx_movimiento_caja ON movimiento_caja(id_caja);
CREATE INDEX idx_movimiento_fondo ON movimiento_fondo(id_fondo, id_caja);

CREATE INDEX idx_auditoria_tabla_fecha ON auditoria_cambios(tabla_afectada, fecha_cambio);

-- Logica de negocio

DELIMITER //
CREATE PROCEDURE generar_cronograma(IN p_id_prestamo INT,
IN p_fecha_inicio DATE)
BEGIN
    DECLARE v_monto_total DECIMAL(14,2);
    DECLARE v_plazo INT;
    DECLARE v_tasa_interes DECIMAL(5,2);
    DECLARE v_id_tipo_amortizacion INT;
    DECLARE v_id_periodo_pago INT;
    DECLARE v_fecha_inicial DATE;
    DECLARE v_cuota DECIMAL(14,2);
    DECLARE v_interes_cuota DECIMAL(14,2);
    DECLARE v_capital_cuota DECIMAL(14,2);
    DECLARE v_saldo DECIMAL(14,2);
    DECLARE v_r DECIMAL(14,6);
    DECLARE v_pow DECIMAL(14,6);
    DECLARE v_fecha_vencimiento DATE;
    DECLARE v_incremento_periodo INT;
    DECLARE i INT DEFAULT 1;
    DECLARE v_ultima_cuota_pagada INT DEFAULT 0;

    DECLARE v_total_pagar DECIMAL(14,2) DEFAULT 0.00;
    DECLARE v_total_interes DECIMAL(14,2) DEFAULT 0.00;
    DECLARE v_total_capital DECIMAL(14,2) DEFAULT 0.00;
    START TRANSACTION;

    SELECT 
        p.monto_solicitado, 
        p.plazo_meses, 
        cp.tasa_interes, 
        cp.id_tipo_amortizacion,
        cp.id_periodo_pago
    INTO 
        v_monto_total, 
        v_plazo, 
        v_tasa_interes, 
        v_id_tipo_amortizacion,
        v_id_periodo_pago
    FROM prestamo p
    JOIN condicion_prestamo cp ON p.id_condicion_actual = cp.id_condicion_prestamo
    WHERE p.id_prestamo = p_id_prestamo;
    SET v_fecha_inicial = p_fecha_inicio;

    IF v_fecha_inicial IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha de inicio no puede ser nula';
    END IF;

    SET v_saldo = v_monto_total;

    SELECT COALESCE(MAX(numero_cuota), 0) INTO v_ultima_cuota_pagada
    FROM cronograma_cuota
    WHERE id_prestamo = p_id_prestamo AND estado_cuota = 'Pagada';

    DELETE FROM cronograma_cuota 
    WHERE id_prestamo = p_id_prestamo AND estado_cuota != 'Pagada';

    IF v_monto_total IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Prestamo o condición no encontrada';
    END IF;

    IF v_plazo IS NULL OR v_plazo <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Plazo no válido para el préstamo';
    END IF;

    CASE v_id_periodo_pago
        WHEN 1 THEN SET v_incremento_periodo = 7;
        WHEN 2 THEN SET v_incremento_periodo = 15;     
        WHEN 3 THEN SET v_incremento_periodo = 30;   
        ELSE SET v_incremento_periodo = 30;          
    END CASE;
    -- Método Francés (cuota fija)
    IF v_id_tipo_amortizacion = 1 THEN
        SET v_r = CASE v_id_periodo_pago
            WHEN 1 THEN (v_tasa_interes / 100) / 52  
            WHEN 2 THEN (v_tasa_interes / 100) / 24  
            ELSE (v_tasa_interes / 100) / 12         
        END;
        
        SET v_pow = POW(1 + v_r, v_plazo);
        SET v_cuota = v_monto_total * ((v_r * v_pow) / (v_pow - 1));

        WHILE i <= v_plazo DO
            SET v_interes_cuota = v_saldo * v_r;
            SET v_capital_cuota = v_cuota - v_interes_cuota;
            SET v_saldo = v_saldo - v_capital_cuota;

            IF i = v_plazo THEN
                IF ABS(v_saldo) < 0.9 THEN
                    SET v_capital_cuota = v_capital_cuota + v_saldo;
                    SET v_cuota = v_capital_cuota + v_interes_cuota;
                    SET v_saldo = 0;
                END IF;
            END IF;

            IF v_id_periodo_pago = 3 THEN
                SET v_fecha_vencimiento = DATE_ADD(v_fecha_inicial, INTERVAL i MONTH);
            ELSE
                SET v_fecha_vencimiento = DATE_ADD(v_fecha_inicial, INTERVAL (i * v_incremento_periodo) DAY);
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
                i + v_ultima_cuota_pagada,
                v_fecha_vencimiento,
                ROUND(v_capital_cuota, 2),
                ROUND(v_interes_cuota, 2),
                ROUND(v_cuota, 2),
                ROUND(v_saldo, 2),
                'Pendiente'
            );

            SET v_total_capital = v_total_capital + v_capital_cuota;
            SET v_total_interes = v_total_interes + v_interes_cuota;
            SET v_total_pagar = v_total_pagar + v_cuota;
            SET i = i + 1;
        END WHILE;
    -- Método Alemán (capital fijo)
    ELSEIF v_id_tipo_amortizacion = 2 THEN
        SET v_capital_cuota = v_monto_total / v_plazo;
        
        WHILE i <= v_plazo DO
            SET v_interes_cuota = v_saldo * CASE v_id_periodo_pago
                WHEN 1 THEN (v_tasa_interes / 100) / 52  
                WHEN 2 THEN (v_tasa_interes / 100) / 24  
                ELSE (v_tasa_interes / 100) / 12         
            END;
            
            SET v_cuota = v_capital_cuota + v_interes_cuota;
            SET v_saldo = v_saldo - v_capital_cuota;

            IF i = v_plazo THEN
                IF ABS(v_saldo) < 0.9 THEN
                    SET v_capital_cuota = v_capital_cuota + v_saldo;
                    SET v_saldo = 0;
                    SET v_cuota = v_capital_cuota + v_interes_cuota;
                END IF;
            END IF;

            IF v_id_periodo_pago = 3 THEN
                SET v_fecha_vencimiento = DATE_ADD(v_fecha_inicial, INTERVAL i MONTH);
            ELSE
                SET v_fecha_vencimiento = DATE_ADD(v_fecha_inicial, INTERVAL (i * v_incremento_periodo) DAY);
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
                i + v_ultima_cuota_pagada,
                v_fecha_vencimiento,
                ROUND(v_capital_cuota, 2),
                ROUND(v_interes_cuota, 2),
                ROUND(v_cuota, 2),
                ROUND(v_saldo, 2),
                'Pendiente'
            );

            SET v_total_capital = v_total_capital + v_capital_cuota;
            SET v_total_interes = v_total_interes + v_interes_cuota;
            SET v_total_pagar = v_total_pagar + v_cuota;
            SET i = i + 1;
        END WHILE;
    -- Método Diferido (solo intereses, capital al final)
    ELSEIF v_id_tipo_amortizacion = 3 THEN
        SET v_interes_cuota = v_monto_total * CASE v_id_periodo_pago
            WHEN 1 THEN (v_tasa_interes / 100) / 52  
            WHEN 2 THEN (v_tasa_interes / 100) / 24  
            ELSE (v_tasa_interes / 100) / 12         
        END;

        WHILE i <= v_plazo DO
            IF i = v_plazo THEN
                SET v_capital_cuota = 0;
                SET v_cuota = v_interes_cuota;
            ELSE 
                SET v_capital_cuota = v_monto_total;
                SET v_cuota = ROUND(v_monto_total + v_interes_cuota, 2);
                SET v_saldo = 0.00;
            END IF;

            IF v_id_periodo_pago = 3 THEN
                SET v_fecha_vencimiento = DATE_ADD(v_fecha_inicial, INTERVAL i MONTH);
            ELSE
                SET v_fecha_vencimiento = DATE_ADD(v_fecha_inicial, INTERVAL (i * v_incremento_periodo) DAY);
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
                i + v_ultima_cuota_pagada,
                v_fecha_vencimiento,
                ROUND(v_capital_cuota, 2),
                ROUND(v_interes_cuota, 2),
                ROUND(v_cuota, 2),
                ROUND(v_saldo, 2),
                'Pendiente'
            );

            SET v_total_capital = v_total_capital + v_capital_cuota;
            SET v_total_interes = v_total_interes + v_interes_cuota;
            SET v_total_pagar = v_total_pagar + v_cuota;
            SET i = i + 1;
        END WHILE;
    END IF;

    INSERT INTO resumen_cronograma (
        id_prestamo,
        total_capital,
        total_interes,
        total_pagar
    ) VALUES (
        p_id_prestamo,
        ROUND(v_total_capital, 2),
        ROUND(v_total_interes, 2),
        ROUND(v_total_pagar, 2)
    )
    ON DUPLICATE KEY UPDATE
        total_capital = VALUES(total_capital),
        total_interes = VALUES(total_interes),
        total_pagar = VALUES(total_pagar)
    ;
    COMMIT;
END//
DELIMITER ;

DELIMITER //
CREATE PROCEDURE otorgar_prestamo_financiador(
    IN p_id_financiador INT,
    IN p_monto DECIMAL(14,2),
    IN p_plazo INT,
    IN p_creado_por INT
)
BEGIN
    DECLARE v_tasa_base DECIMAL(5,2);
    DECLARE v_tasa_descuento DECIMAL(5,2);
    DECLARE v_tasa_final DECIMAL(5,2);
    DECLARE v_id_cliente INT;
    DECLARE v_id_condicion INT;

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

    IF (SELECT COUNT(*) FROM condicion_prestamo
        WHERE vigente_desde <= CURDATE()) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay condiciones de préstamo vigentes.';
    END IF;

    INSERT INTO condicion_prestamo(
        tasa_interes, 
        id_tipo_amortizacion, 
        id_periodo_pago, 
        vigente_desde, 
        vigente_hasta, 
        esta_activo
    )VALUES (
        v_tasa_final,
        1,  
        3,  
        CURDATE(),
        NULL,
        TRUE
    );  
    SET v_id_condicion = LAST_INSERT_ID();

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
        v_id_cliente,
        (SELECT id_tipo_prestamo 
        FROM tipo_prestamo
        WHERE id_tipo_prestamo = 1 LIMIT 1),
        CONCAT('FIN-', UUID()),
        p_monto,
        CURDATE(),
        p_plazo,
        (SELECT id_estado_prestamo
        FROM cat_estado_prestamo 
        WHERE estado = 'Activo' LIMIT 1),
        v_id_condicion,
        p_creado_por
    );
END//
DELIMITER ;

DELIMITER //
CREATE PROCEDURE reestructurar_prestamo(
    IN p_id_prestamo INT,
    IN p_nuevo_plazo INT,
    IN p_nueva_tasa DECIMAL(5,2),
    IN p_tipo_amortizacion INT,
    IN p_id_periodo_pago INT,
    IN p_usuario INT
)
BEGIN
    DECLARE v_id_condicion_nueva INT;
    DECLARE v_fecha DATE DEFAULT CURDATE();
    DECLARE v_saldo_pendiente DECIMAL(14,2);

    SELECT COALESCE(SUM(capital_cuota), 0)
    INTO v_saldo_pendiente
    FROM cronograma_cuota
    WHERE id_prestamo = p_id_prestamo AND estado_cuota != 'Pagada';

    IF v_saldo_pendiente <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El prestamo no tiene saldo pendiente';
    END IF;

    INSERT INTO condicion_prestamo(
        tasa_interes, 
        id_tipo_amortizacion, 
        id_periodo_pago, 
        vigente_desde, 
        vigente_hasta, 
        esta_activo)
    VALUES (
        p_nueva_tasa,
        p_tipo_amortizacion,
        p_id_periodo_pago,
        v_fecha,
        NULL,
        TRUE
    );
    SET v_id_condicion_nueva = LAST_INSERT_ID();

    INSERT INTO restructuracion_prestamo(
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
    SET monto_solicitado = v_saldo_pendiente,
        plazo_meses = p_nuevo_plazo,
        id_condicion_actual = v_id_condicion_nueva,
        id_estado_prestamo = (
            SELECT id_estado_prestamo 
            FROM cat_estado_prestamo 
            WHERE estado = 'Activo' 
            LIMIT 1
        )
    WHERE id_prestamo = p_id_prestamo;

    DELETE FROM cronograma_cuota 
    WHERE id_prestamo = p_id_prestamo AND estado_cuota != 'Pagada';

    CALL generar_cronograma(p_id_prestamo, v_fecha);
END//
DELIMITER ;

DELIMITER $$
CREATE TRIGGER tr_generar_cronograma
AFTER INSERT ON prestamo
FOR EACH ROW
BEGIN
    CALL generar_cronograma(NEW.id_prestamo, NEW.fecha_solicitud);
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER validar_capacidad_pago
BEFORE INSERT ON prestamo
FOR EACH ROW 
BEGIN
    DECLARE v_ingresos DECIMAL(14,2) DEFAULT 0.00;
    DECLARE v_egresos DECIMAL(14,2) DEFAULT 0.00;
    DECLARE v_capacidad_pago DECIMAL(14,2);
    DECLARE v_cuota_estimada DECIMAL(14,2);
    DECLARE v_tasa_interes DECIMAL(5,2) DEFAULT 0.00;
    DECLARE v_porcentaje_valido DECIMAL(5,2) DEFAULT 0.40;
    DECLARE v_tipo_amortizacion INT;
    DECLARE r DECIMAL (14,6);

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
    FROM condicion_prestamo cp
    WHERE cp.id_condicion_prestamo = NEW.id_condicion_actual 
    LIMIT 1;

    IF v_tasa_interes IS NULL OR v_tasa_interes = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Interes nulo';
    END IF;

    SET r = CASE 
            WHEN (SELECT id_periodo_pago
                  FROM condicion_prestamo
                  WHERE id_condicion_prestamo = NEW.id_condicion_actual) = 1 
                  THEN (v_tasa_interes / 100) / 52  
            WHEN (SELECT id_periodo_pago
                  FROM condicion_prestamo
                  WHERE id_condicion_prestamo = NEW.id_condicion_actual) = 2 
                  THEN (v_tasa_interes / 100) / 24  
            ELSE (v_tasa_interes / 100) / 12
    END;
    IF r = 0 THEN
        SET v_cuota_estimada = NEW.monto_solicitado / NEW.plazo_meses;
    ELSE
        SET v_cuota_estimada = (NEW.monto_solicitado * r) / (1 - POW(1 + r, - NEW.plazo_meses));
    END IF;

    SELECT id_tipo_amortizacion 
    INTO v_tipo_amortizacion
    FROM condicion_prestamo
    WHERE id_condicion_prestamo = NEW.id_condicion_actual;

    IF v_tipo_amortizacion IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tipo de amortización no definido.';
    END IF;

    IF v_tipo_amortizacion = 1 THEN
        SET v_cuota_estimada = (NEW.monto_solicitado * r) / (1 - POW(1 + r, - NEW.plazo_meses));
    END IF;
    IF v_tipo_amortizacion = 2 THEN
        SET v_cuota_estimada = NEW.monto_solicitado / NEW.plazo_meses + (NEW.monto_solicitado * r);
    END IF;

    SELECT valor_decimal INTO v_porcentaje_valido
    FROM configuracion
    WHERE nombre_configuracion = 'MAX_PORCENTAJE_CAPACIDAD_PAGO';

    IF v_cuota_estimada / v_capacidad_pago > v_porcentaje_valido THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuota estimada excede el 40% de la capacidad de pago.';
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER actualizar_estado_prestamo
AFTER UPDATE ON cronograma_cuota 
FOR EACH ROW 
BEGIN 
    IF NEW.estado_cuota = 'Pagada' AND NEW.cargos_cuota = 0 THEN
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
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE generar_nomina_empleado(
    IN p_periodo VARCHAR(7)
)
BEGIN
    DECLARE v_id_nomina INT;
    DECLARE v_periodo DATE;
    DECLARE v_id_empleado INT;
    DECLARE v_salario_base DECIMAL(14,2);
    DECLARE v_horas_extra DECIMAL(14,2);
    DECLARE v_afp_pc DECIMAL(10,4);
    DECLARE v_sfs_pc DECIMAL(10,4);
    DECLARE v_t1 DECIMAL(14,2);
    DECLARE v_t2 DECIMAL(14,2);
    Declare v_t3 DECIMAL(14,2);
    DECLARE v_r2 DECIMAL(10,4);
    DECLARE v_r3 DECIMAL(10,4);
    DECLARE v_r4 DECIMAL(10,4);
    DECLARE v_isr DECIMAL(14,2);
    DECLARE v_afp DECIMAL(14,2);
    DECLARE v_sfs DECIMAL(14,2);
    DECLARE v_beneficios_t DECIMAL(14,2);
    DECLARE v_neto DECIMAL(14,2);
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_last INT DEFAULT 0;

    DECLARE empleado_cursor CURSOR FOR
        SELECT e.id_empleado, c.salario_base
        FROM empleado e
        JOIN contrato_empleado c ON e.id_empleado = c.id_empleado AND c.vigente = TRUE
        WHERE c.salario_base > 0
        ORDER BY e.id_empleado;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    SET v_periodo = STR_TO_DATE(CONCAT(p_periodo, '-01'), '%Y-%m-%d');

    START TRANSACTION;

    SELECT id_nomina INTO v_id_nomina FROM nomina WHERE periodo = p_periodo LIMIT 1;
    IF v_id_nomina IS NOT NULL THEN 
        DELETE be FROM beneficios_empleado be
        JOIN nomina_empleado ne ON ne.id_nomina_empleado = be.id_nomina_empleado
        WHERE ne.id_nomina = v_id_nomina;
        DELETE dd FROM deducciones_empleado dd
        JOIN nomina_empleado ne2 ON ne2.id_nomina_empleado = dd.id_nomina_empleado
        WHERE ne2.id_nomina = v_id_nomina;
        DELETE FROM nomina_empleado WHERE id_nomina = v_id_nomina;
    ELSE 
        INSERT INTO nomina (periodo) VALUES (p_periodo);
        SET v_id_nomina = LAST_INSERT_ID();
    END IF;

    SELECT
        COALESCE(MAX(CASE WHEN nombre_configuracion = 'AFP_PORCENTAJE' THEN valor_decimal END ),2.87),
        COALESCE(MAX(CASE WHEN nombre_configuracion = 'SFS_PORCENTAJE' THEN valor_decimal END ),3.04),
        COALESCE(MAX(CASE WHEN nombre_configuracion = 'ISR_TRAMO1' THEN valor_decimal END), 52152),
        COALESCE(MAX(CASE WHEN nombre_configuracion = 'ISR_TRAMO2' THEN valor_decimal END), 78281),
        COALESCE(MAX(CASE WHEN nombre_configuracion = 'ISR_TRAMO3' THEN valor_decimal END), 109324),
        COALESCE(MAX(CASE WHEN nombre_configuracion = 'ISR_TASA_TRAMO2' THEN valor_decimal END), 0.15),
        COALESCE(MAX(CASE WHEN nombre_configuracion = 'ISR_TASA_TRAMO3' THEN valor_decimal END), 0.20),
        COALESCE(MAX(CASE WHEN nombre_configuracion = 'ISR_TASA_TRAMO4' THEN valor_decimal END), 0.25)
    INTO 
        v_afp_pc,
        v_sfs_pc,
        v_t1,
        v_t2,
        v_t3,
        v_r2,
        v_r3,
        v_r4
    FROM configuracion
    WHERE nombre_configuracion IN (
        'AFP_PORCENTAJE', 'SFS_PORCENTAJE', 
        'ISR_TRAMO1', 'ISR_TRAMO2', 'ISR_TRAMO3',
        'ISR_TASA_TRAMO2', 'ISR_TASA_TRAMO3', 'ISR_TASA_TRAMO4'
    );

    CREATE TEMPORARY TABLE IF NOT EXISTS temp_beneficios(
        id_beneficio INT PRIMARY KEY,
        monto DECIMAL(14,2)
    )ENGINE = INNODB;

    TRUNCATE TABLE temp_beneficios;

    INSERT INTO temp_beneficios (id_beneficio, monto)
    SELECT b.id_beneficio, 
        COALESCE((SELECT valor_decimal FROM configuracion c
                  WHERE c.nombre_configuracion = CONCAT('BENEFICIO_', UPPER(REPLACE(REPLACE(b.tipo_beneficio, ' ', '_'), '-', '_')))
                  LIMIT 1), b.valor)
    FROM cat_beneficio b;

    OPEN empleado_cursor;
    empleado_loop: LOOP
        FETCH empleado_cursor INTO v_id_empleado, v_salario_base;
        IF v_done =1 THEN
            LEAVE empleado_loop;
        END IF;

        SELECT COALESCE(SUM(monto_total), 0) INTO v_horas_extra
        FROM horas_extras
        WHERE id_empleado = v_id_empleado
            AND DATE_FORMAT(fecha, '%Y-%m') = p_periodo;
        
        SET v_afp = ROUND(v_salario_base * (v_afp_pc / 100), 2);
        SET v_sfs = ROUND(v_salario_base * (v_sfs_pc / 100), 2);

        SET v_isr = 0.00;
        IF v_salario_base > v_t1 AND v_salario_base <= v_t2 THEN
            SET v_isr = ROUND((v_salario_base - v_t1)* v_r2, 2);
        ELSEIF v_salario_base > v_t2 AND v_salario_base <= v_t3 THEN
            SET v_isr = ROUND(((v_salario_base - v_t2)* v_r3) + ((v_t2 - v_t1)* v_r2),2);
        ELSEIF v_salario_base > v_t3 THEN
            SET v_isr = ROUND(((v_salario_base - v_t3)* v_r4)+ ((v_t3 - v_t2)* v_r3)+ ((v_t2 - v_t1)* v_r2),2);
        END IF;

        SELECT COALESCE(SUM(monto), 0) INTO v_beneficios_t 
        FROM temp_beneficios;

        SELECT COALESCE(SUM(
            CASE
                WHEN ae.porcentaje IS NOT NULL THEN ROUND(v_salario_base * (ae.porcentaje/ 100), 2)
                WHEN ae.monto IS NOT NULL THEN ae.monto
                ELSE 0
            END
        ), 0)
        INTO @ajuste_beneficio_empleado
        FROM ajuste_empleado ae 
        WHERE ae.id_empleado = v_id_empleado
            AND ae.tipo = 'Beneficio'
            AND ae.estado = 'Activo'
            AND ae.vigente_desde <= v_periodo;
        SET v_beneficios_t = ROUND(v_beneficios_t + COALESCE(@ajuste_beneficio_empleado, 0), 2);

        SET v_neto = ROUND(v_salario_base + v_horas_extra + v_beneficios_t - (v_afp + v_sfs + v_isr), 2);

        INSERT INTO nomina_empleado (
            id_nomina,
            id_empleado,
            salario_base,
            salario_neto
        ) VALUES (
            v_id_nomina,
            v_id_empleado,
            v_salario_base,
            v_neto
        ); SET v_last = LAST_INSERT_ID();

        INSERT INTO deducciones_empleado (
            id_nomina_empleado,
            id_tipo_deduccion,
            monto,
            fecha
        ) SELECT v_last, td.id_tipo_deduccion,
            CASE 
                WHEN UPPER(td.tipo_deduccion) LIKE '%AFP%' THEN v_afp
                WHEN UPPER(td.tipo_deduccion) LIKE '%SFS%' THEN v_sfs
                WHEN UPPER(td.tipo_deduccion) LIKE '%ISR%' THEN v_isr
                ELSE 0.00
            END,
            v_periodo
        FROM cat_tipo_deduccion td
        WHERE (UPPER(td.tipo_deduccion) LIKE '%AFP%' AND v_afp > 0)
            OR (UPPER(td.tipo_deduccion) LIKE '%SFS%' AND v_sfs > 0)
            OR (UPPER(td.tipo_deduccion) LIKE '%ISR%' AND v_isr > 0);

        
        INSERT INTO deducciones_empleado (
            id_nomina_empleado,
            id_tipo_deduccion,
            monto,
            fecha
        ) SELECT v_last,
            COALESCE(ae.id_tipo_deduccion, (SELECT id_tipo_deduccion FROM cat_tipo_deduccion WHERE tipo_deduccion = 'Otro' LIMIT 1)),
            CASE
                WHEN ae.porcentaje IS NOT NULL THEN ROUND(v_salario_base * (ae.porcentaje/100), 2)
                WHEN ae.monto IS NOT NULL THEN ae.monto
                ELSE 0
            END,
            v_periodo
        FROM ajuste_empleado ae
        WHERE ae.id_empleado = v_id_empleado
            AND ae.tipo = 'Deduccion'
            AND ae.estado = 'Activo'
            AND ae.vigente_desde <= v_periodo;
        

        INSERT INTO beneficios_empleado (
            id_nomina_empleado,
            id_beneficio,
            monto,
            fecha
        ) SELECT v_last, tb.id_beneficio, tb.monto, v_periodo
        FROM temp_beneficios tb
        WHERE tb.monto > 0;

        INSERT INTO beneficios_empleado (id_nomina_empleado, id_beneficio, monto, fecha)
        SELECT v_last,
            COALESCE(ae.id_beneficio, (SELECT id_beneficio FROM cat_beneficio WHERE tipo_beneficio = 'Otro' LIMIT 1)),
            CASE
                WHEN ae.porcentaje IS NOT NULL THEN ROUND(v_salario_base * (ae.porcentaje/100), 2)
                WHEN ae.monto IS NOT NULL THEN ae.monto
                ELSE 0
            END,
            v_periodo
        FROM ajuste_empleado ae
        WHERE ae.id_empleado = v_id_empleado
            AND ae.tipo = 'Beneficio'
            AND ae.estado = 'Activo'
            AND ae.vigente_desde <= v_periodo;
        
        END LOOP empleado_loop;
        CLOSE empleado_cursor;

        DROP TEMPORARY TABLE IF EXISTS temp_beneficios;
        COMMIT;

        SELECT v_id_nomina AS id_nomina,
            CONCAT('{\"OK\":', 'true', ',\"id_nomina\":', v_id_nomina, '}') AS resultado_json;
END$$
DELIMITER ;

DELIMITER $$
CREATE EVENT evt_actualizar_mora
ON SCHEDULE EVERY 1 DAY
STARTS CASE 
   WHEN NOW() < CONCAT(CURDATE(), ' 00:05:00')
    THEN CONCAT(CURDATE(), ' 00:05:00')
   ELSE CONCAT(CURDATE() + INTERVAL 1 DAY, ' 00:05:00')
   END
ON COMPLETION PRESERVE
DO
BEGIN
    DECLARE v_mora DECIMAL (5,2); 
    DECLARE v_gracia INT;

    SELECT porcentaje_mora, dias_gracia INTO v_mora, v_gracia
    FROM config_mora
    WHERE estado = 'Activo'
    ORDER BY vigente_desde DESC
    LIMIT 1;

    UPDATE cronograma_cuota 
    SET cargos_cuota = ROUND(cargos_cuota + (capital_cuota * (v_mora / 100)), 2)
    WHERE estado_cuota = 'Vencida'
        AND total_monto > 0
        AND DATE_ADD(fecha_vencimiento, INTERVAL v_gracia DAY) < CURDATE();

    UPDATE cronograma_cuota 
    SET estado_cuota = 'Vencida',
        cargos_cuota = ROUND(cargos_cuota + (capital_cuota * (v_mora / 100)), 2)
    WHERE estado_cuota = 'Pendiente'
        AND DATE_ADD(fecha_vencimiento, INTERVAL v_gracia DAY) < CURDATE(); 
    
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

    UPDATE resumen_cronograma rc 
    JOIN (
        SELECT id_prestamo,
            SUM(capital_cuota) AS capital,
            SUM(interes_cuota) AS interes,
            SUM(capital_cuota + interes_cuota + cargos_cuota) AS total
            FROM cronograma_cuota
            WHERE id_prestamo IN ('Pendiente', 'Vencida', 'Pagada')
            GROUP BY id_prestamo
    ) t ON rc.id_prestamo = t.id_prestamo
    SET rc.total_capital = ROUND(t.capital, 2),
        rc.total_interes = ROUND(t.interes, 2),
        rc.total_pagar = ROUND(t.total, 2);    
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER tr_auditoria_prestamo
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
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER tr_auditoria_desembolso
AFTER UPDATE ON desembolso
FOR EACH ROW
BEGIN
    INSERT INTO auditoria_cambios(
        tabla_afectada, id_registro, tipo_cambio, fecha_cambio, realizado_por, valores_anteriores, valores_nuevos
    )
    VALUES (
        'desembolso',
        OLD.id_desembolso,
        'UPDATE',
        NOW(),
        NEW.creado_por,
        JSON_OBJECT(
            'id_prestamo', OLD.id_prestamo,
            'monto_desembolsado', OLD.monto_desembolsado,
            'fecha_desembolso', OLD.fecha_desembolso,
            'metodo_entrega', OLD.metodo_entrega,
            'creado_por', OLD.creado_por,
            'creado_en', OLD.creado_en
        ),
        JSON_OBJECT(
            'id_prestamo', NEW.id_prestamo,
            'monto_desembolsado', NEW.monto_desembolsado,
            'fecha_desembolso', NEW.fecha_desembolso,
            'metodo_entrega', NEW.metodo_entrega,
            'creado_por', NEW.creado_por,
            'creado_en', NEW.creado_en
        )
    );
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER tr_auditoria_pago
AFTER INSERT ON pago
FOR EACH ROW 
BEGIN
    INSERT INTO auditoria_cambios(
        tabla_afectada, id_registro, tipo_cambio, fecha_cambio, realizado_por, valores_anteriores, valores_nuevos
    )
    VALUES (
        'Pago',
        NEW.id_pago,
        'UPDATE',
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
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER tr_auditoria_cuota
AFTER UPDATE ON cronograma_cuota
FOR EACH ROW
BEGIN 
    IF NEW.estado_cuota != OLD.estado_cuota THEN
        INSERT INTO auditoria_cambios(
            tabla_afectada, id_registro, tipo_cambio, fecha_cambio, realizado_por, valores_anteriores, valores_nuevos
        )
        VALUES (
            'Cronograma_Cuota',
            NEW.id_cronograma_cuota,
            'UPDATE',
            NOW(),
            COALESCE(NEW.creado_por, OLD.creado_por),
            JSON_OBJECT(
                'estado_cuota', OLD.estado_cuota
            ),
            JSON_OBJECT(
                'estado_cuota', NEW.estado_cuota
            )
        );
    END IF;
END$$
DELIMITER ;

DELIMITER $$

CREATE TRIGGER actualizacion_prestamo_protegido
BEFORE UPDATE ON prestamo
FOR EACH ROW 
BEGIN 
    DECLARE v_estado INT;
    SET v_estado = OLD.id_estado_prestamo;
    IF OLD.id_estado_prestamo IN (
        SELECT id_estado_prestamo FROM cat_estado_prestamo WHERE estado IN ('Cerrado')
    ) AND (NEW.monto_solicitado != OLD.monto_solicitado 
    OR NEW.plazo_meses != OLD.plazo_meses) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'No se puede modificar un préstamo ya cerrado.';
    END IF;
END$$
DELIMITER ;

INSERT INTO datos_persona (nombre, apellido, fecha_nacimiento, genero)
VALUES ('Administrador', 'General', '2000-01-1', 3);
SET @id_datos_persona := LAST_INSERT_ID();

INSERT INTO empleado (id_datos_persona)
VALUES (@id_datos_persona);
SET @id_empleado := LAST_INSERT_ID();

INSERT INTO usuario (id_datos_persona, nombre_usuario, contrasena)
VALUES (@id_datos_persona, 'admin', '$2y$10$6fBV8TQkvtowteYkN4o3r.bkvay.DO/RvYPPvnMLJkQtMSTK9SDqy');
SET @id_usuario := LAST_INSERT_ID();

INSERT INTO roles (nombre_rol, descripcion)
VALUES ('Administrador', 'Acceso completo al sistema')
ON DUPLICATE KEY UPDATE id_rol = LAST_INSERT_ID(id_rol);
SET @id_rol := LAST_INSERT_ID();

INSERT INTO usuarios_roles (id_usuario, id_rol)
VALUES (@id_usuario, @id_rol);