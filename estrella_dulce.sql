-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-11-2026 a las 16:58:01
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `estrella_dulce`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Tortas', 'Tortas caseras y especiales.', 'ACTIVA'),
(2, 'Cupcakes', 'Cupcakes decorados y personalizados.', 'ACTIVA'),
(3, 'Postres', 'Postres fríos y deliciosos preparados con amor.', 'ACTIVA'),
(4, 'Galletas', 'Galletas artesanales y decoradas.', 'ACTIVA'),
(5, 'Especiales', 'Productos especiales y personalizados.', 'ACTIVA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedidos`
--

CREATE TABLE `detalle_pedidos` (
  `id_detalle` int(11) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `id_postre` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `detalle_pedidos`
--

INSERT INTO `detalle_pedidos` (`id_detalle`, `id_pedido`, `id_postre`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 1, 1, 8500.00, 8500.00),
(2, 2, 6, 1, 8500.00, 8500.00),
(3, 3, 6, 2, 8500.00, 17000.00),
(4, 3, 2, 1, 8500.00, 8500.00),
(5, 4, 2, 1, 8500.00, 8500.00),
(6, 5, 1, 2, 8500.00, 17000.00),
(7, 6, 1, 1, 8500.00, 8500.00),
(8, 7, 6, 6, 8500.00, 51000.00),
(9, 8, 3, 8, 8500.00, 68000.00),
(10, 9, 4, 7, 8500.00, 59500.00),
(11, 10, 8, 5, 9000.00, 45000.00),
(12, 11, 8, 5, 9000.00, 45000.00),
(13, 12, 7, 1, 10000.00, 10000.00),
(14, 13, 8, 3, 9000.00, 27000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes_contacto`
--

CREATE TABLE `mensajes_contacto` (
  `id_mensaje` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` enum('PENDIENTE','LEIDO','RESPONDIDO') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `mensajes_contacto`
--

INSERT INTO `mensajes_contacto` (`id_mensaje`, `id_usuario`, `nombre`, `correo`, `mensaje`, `estado`, `fecha_envio`) VALUES
(1, NULL, 'Administrador', 'admin@estrelladulce.com', 'Mensaje de prueba del sistema.', 'RESPONDIDO', '2026-09-24 12:35:04'),
(2, 4, 'cindy', 'cindy@example.com', 'hola', 'PENDIENTE', '2026-09-24 12:35:04'),
(3, 4, 'cindy', 'cindy@example.com', 'mnbdbcmn', 'PENDIENTE', '2026-09-24 12:38:46');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id_pedido` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` enum('PENDIENTE','CONFIRMADO','PREPARANDO','LISTO','ENTREGADO','CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
  `direccion_entrega` varchar(255) DEFAULT NULL,
  `telefono_contacto` varchar(30) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id_pedido`, `id_usuario`, `total`, `estado`, `direccion_entrega`, `telefono_contacto`, `observaciones`, `fecha_pedido`) VALUES
(1, 3, 8500.00, 'CONFIRMADO', 'cra 77  # 198-78', '309384874', 'Método de pago: Efectivo | hdjhjfghfh', '2026-09-24 13:21:53'),
(2, 5, 8500.00, 'PENDIENTE', 'cr 76 #67-90', '373646748484', 'Método de pago: Efectivo | ndhjshgvfhdj', '2026-10-21 12:14:43'),
(3, 5, 25500.00, 'PENDIENTE', 'CL 107 NORTE # 74 NORTE - 80 SEGUNDO PISO', '+573013931892', 'Método de pago: Efectivo | pene', '2026-10-29 13:28:05'),
(4, 5, 8500.00, 'PENDIENTE', 'CL 107 NORTE # 74 NORTE - 80 SEGUNDO PISO', '+573013931892', 'Método de pago: Efectivo | btbtybthbtybthbhtthbthb', '2026-10-29 13:41:42'),
(5, 5, 17000.00, 'PENDIENTE', 'CL 107 NORTE # 74 NORTE - 80 SEGUNDO PISO', '+573013931892', 'Método de pago: Efectivo | ddd', '2026-10-29 14:20:13'),
(6, 5, 8500.00, 'PENDIENTE', 'CL 107 NORTE # 74 NORTE - 80 SEGUNDO PISO', '+573013931892', 'Método de pago: Efectivo', '2026-10-29 14:25:46'),
(7, 6, 51000.00, 'ENTREGADO', 'Compra en caja', '', 'Factura de compra en cajero automático. Método de pago: EFECTIVO', '2026-11-03 15:22:43'),
(8, 6, 68000.00, 'ENTREGADO', 'Compra en caja', '', 'Factura de compra en cajero automático. Método de pago: EFECTIVO', '2026-11-03 15:27:05'),
(9, 6, 59500.00, 'ENTREGADO', 'Compra en caja', '', 'Factura de compra en cajero automático. Método de pago: EFECTIVO', '2026-11-03 15:31:16'),
(10, 5, 45000.00, 'ENTREGADO', 'Compra en caja', '', 'Pedido tomado en caja. Cajero: Mariangel Londoño', '2026-11-04 12:44:10'),
(11, 5, 45000.00, 'ENTREGADO', 'Compra en caja', '', 'Pedido tomado en caja. Cajero: Mariangel Londoño', '2026-11-04 12:46:19'),
(12, 5, 10000.00, 'ENTREGADO', 'Compra en caja', '', 'Pedido tomado en caja. Cajero: Mariangel Londoño', '2026-11-04 12:55:49'),
(13, 5, 27000.00, 'ENTREGADO', 'Compra en caja', '', 'Pedido tomado en caja. Cajero: Mariangel Londoño', '2026-11-04 13:09:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `postres`
--

CREATE TABLE `postres` (
  `id_postre` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `imagen` varchar(255) DEFAULT NULL,
  `ingredientes` text DEFAULT NULL,
  `estado` enum('DISPONIBLE','AGOTADO','INACTIVO') NOT NULL DEFAULT 'DISPONIBLE',
  `destacado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `postres`
--

INSERT INTO `postres` (`id_postre`, `id_categoria`, `nombre`, `descripcion`, `precio`, `stock`, `imagen`, `ingredientes`, `estado`, `destacado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 3, 'Postre de Café', 'Delicioso postre con sabor a café.', 8500.00, 16, 'postre_cafe.jpg', 'Café, crema de leche, leche condensada y galletas.', 'DISPONIBLE', 0, '2026-09-24 12:35:04', '2026-10-29 14:25:46'),
(2, 3, 'Postre de Maracuyá', 'Postre cremoso con el delicioso sabor de maracuyá.', 8500.00, 18, 'postre_maracuya.jpg', 'Maracuyá, crema de leche, leche condensada y galletas.', 'DISPONIBLE', 0, '2026-09-24 12:35:04', '2026-10-29 13:41:42'),
(3, 3, 'Postre de Milo', 'Postre cremoso preparado con Milo.', 8500.00, 12, 'postre_milo.jpg', 'Milo, crema de leche, leche condensada y galletas.', 'DISPONIBLE', 1, '2026-09-24 12:35:04', '2026-11-03 15:27:05'),
(4, 3, 'Postre de Limón', 'Postre fresco y cremoso con sabor a limón.', 8500.00, 13, 'postre_limon.jpg', 'Limón, crema de leche, leche condensada y galletas.', 'DISPONIBLE', 1, '2026-09-24 12:35:04', '2026-11-03 15:31:16'),
(5, 3, 'Postre de Oreo', 'Delicioso postre preparado con galletas Oreo.', 9000.00, 20, 'postre_oreo.jpg', 'Galletas Oreo, crema de leche, leche condensada y queso crema.', 'DISPONIBLE', 1, '2026-09-24 12:35:04', '2026-09-24 12:42:07'),
(6, 3, 'Postre de Fresa', 'Postre cremoso con sabor a fresa.', 8500.00, 11, 'postre_fresa.jpg', 'Fresas, crema de leche, leche condensada y galletas.', 'DISPONIBLE', 0, '2026-09-24 12:35:04', '2026-11-03 15:22:43'),
(7, 3, 'Cheesecake con Fresa', 'Cheesecake cremoso acompañado de fresas.', 10000.00, 19, 'cheesecake.jpg', 'Queso crema, fresas, crema de leche y galletas.', 'DISPONIBLE', 0, '2026-09-24 12:35:04', '2026-11-04 12:55:49'),
(8, 3, 'Postre de 3 Leches', 'Suave y delicioso postre de tres leches.', 9000.00, 7, 'postre_3_leches.jpg', 'Leche condensada, leche evaporada, crema de leche y bizcocho.', 'DISPONIBLE', 0, '2026-09-24 12:35:04', '2026-11-04 13:09:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`) VALUES
(1, 'ADMINISTRADOR'),
(2, 'CLIENTE'),
(3, 'EMPLEADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL DEFAULT 2,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `correo` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `id_rol`, `nombre`, `apellido`, `correo`, `password`, `telefono`, `estado`, `fecha_registro`) VALUES
(1, 1, 'Administrador', 'Principal', 'admin@estrelladulce.com', '2008', '3000000000', 'ACTIVO', '2026-09-24 12:35:04'),
(3, 1, 'Administrador', 'Estrella Dulce', 'administrador2@estrelladulce.com', 'Estrella2026', '3000000000', 'ACTIVO', '2026-09-24 12:35:04'),
(4, 2, 'cindy', '', 'cindy@example.com', '123456', NULL, 'ACTIVO', '2026-09-24 12:35:04'),
(5, 2, 'Emanuel Avendaño', '', 'emanuelavendano287@gmail.com', 'LmariaA/69', NULL, 'ACTIVO', '2026-09-29 15:25:07'),
(6, 2, 'Mariangel Londoño U.', '', 'mariusuga72@gmail.com', 'mariangel12', NULL, 'ACTIVO', '2026-11-03 12:20:38');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `uk_categorias_nombre` (`nombre`);

--
-- Indices de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `idx_detalle_pedido` (`id_pedido`),
  ADD KEY `idx_detalle_postre` (`id_postre`);

--
-- Indices de la tabla `mensajes_contacto`
--
ALTER TABLE `mensajes_contacto`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `idx_mensajes_usuario` (`id_usuario`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `idx_pedidos_usuario` (`id_usuario`);

--
-- Indices de la tabla `postres`
--
ALTER TABLE `postres`
  ADD PRIMARY KEY (`id_postre`),
  ADD KEY `idx_postres_categoria` (`id_categoria`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `uk_roles_nombre` (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uk_usuarios_correo` (`correo`),
  ADD KEY `idx_usuarios_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `mensajes_contacto`
--
ALTER TABLE `mensajes_contacto`
  MODIFY `id_mensaje` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `postres`
--
ALTER TABLE `postres`
  MODIFY `id_postre` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_postre` FOREIGN KEY (`id_postre`) REFERENCES `postres` (`id_postre`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `mensajes_contacto`
--
ALTER TABLE `mensajes_contacto`
  ADD CONSTRAINT `fk_mensajes_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `postres`
--
ALTER TABLE `postres`
  ADD CONSTRAINT `fk_postres_categorias` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
