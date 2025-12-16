-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 16-12-2025 a las 22:23:42
-- Versión del servidor: 11.4.9-MariaDB
-- Versión de PHP: 8.4.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `grupoecc_proyectmaster`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `parent_area_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `default_series_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `series_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `areas`
--

INSERT INTO `areas` (`id`, `project_id`, `parent_area_id`, `name`, `default_series_id`, `brand_id`, `series_id`, `created_at`) VALUES
(1, 2, NULL, 'cocina', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(2, 2, NULL, 'sala', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(3, 2, NULL, 'cuarto principal', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(4, 2, NULL, 'piscina', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(5, 2, NULL, 'piscina', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(6, 2, NULL, 'family room', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(7, 3, NULL, 'COCINA', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(8, 3, NULL, 'FAMILY ROOM', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(9, 3, NULL, 'SALA', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(10, 3, NULL, 'BAÑO VISITAS', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(11, 3, NULL, 'CUARTO PPAL', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(12, 3, NULL, 'CUARTO SALVA', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(13, 3, NULL, 'CUARTO LUCIO', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(14, 3, NULL, 'BAÑO NIÑOS', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(15, 3, NULL, 'BAÑO PPAL', NULL, NULL, NULL, '2025-12-13 21:31:57'),
(17, 4, NULL, 'planta alta', NULL, NULL, NULL, '2025-12-13 21:32:02'),
(18, 4, NULL, 'planta baja', NULL, NULL, NULL, '2025-12-13 21:32:57'),
(19, 4, 18, 'cuartos', NULL, NULL, NULL, '2025-12-13 21:36:34'),
(20, 4, 17, 'cocina', NULL, NULL, NULL, '2025-12-13 21:36:49'),
(21, 4, 18, 'family room', NULL, NULL, NULL, '2025-12-13 21:36:56'),
(23, 6, NULL, 'planta baja', NULL, NULL, NULL, '2025-12-13 21:55:36'),
(25, 6, 23, 'cocina', NULL, NULL, NULL, '2025-12-13 21:55:36'),
(26, 6, 23, 'family room', NULL, NULL, NULL, '2025-12-13 21:55:36'),
(31, 4, 17, 'prueba', NULL, NULL, NULL, '2025-12-15 03:18:16'),
(32, 10, NULL, 'Planta alta', NULL, NULL, NULL, '2025-12-15 15:06:11'),
(33, 10, NULL, 'Planta baja', NULL, NULL, NULL, '2025-12-15 15:06:17'),
(34, 10, 32, 'Sala', NULL, NULL, NULL, '2025-12-15 15:06:30'),
(35, 10, 32, 'Cuarto ppal', NULL, NULL, NULL, '2025-12-15 15:06:48'),
(36, 10, 32, 'Cocina', NULL, NULL, NULL, '2025-12-15 15:06:56'),
(37, 10, 33, 'Family room', NULL, NULL, NULL, '2025-12-15 15:07:12'),
(38, 4, 18, 'cuartos (Copia)', NULL, NULL, NULL, '2025-12-16 19:14:31');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `area_divisions`
--

CREATE TABLE `area_divisions` (
  `area_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `area_divisions`
--

INSERT INTO `area_divisions` (`area_id`, `division_id`) VALUES
(1, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 2),
(8, 2),
(9, 2),
(10, 2),
(11, 2),
(12, 2),
(13, 2),
(14, 2),
(15, 2),
(1, 3),
(2, 3),
(3, 3),
(4, 3),
(5, 3),
(6, 3),
(7, 3),
(8, 3),
(9, 3),
(10, 3),
(11, 3),
(12, 3),
(13, 3),
(14, 3),
(15, 3),
(7, 4),
(8, 4),
(9, 4),
(11, 4),
(12, 4),
(13, 4),
(1, 8),
(2, 8),
(3, 8),
(4, 8),
(5, 8),
(6, 8),
(7, 8),
(13, 8);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `area_division_counters`
--

CREATE TABLE `area_division_counters` (
  `area_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `area_division_counters`
--

INSERT INTO `area_division_counters` (`area_id`, `division_id`, `last_number`) VALUES
(17, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articles`
--

CREATE TABLE `articles` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `series_id` int(11) DEFAULT NULL,
  `article_type` varchar(32) NOT NULL DEFAULT 'otro',
  `article_type_id` int(11) NOT NULL,
  `fruit_subtype` varchar(100) DEFAULT NULL,
  `orientation` enum('H','V','NA') NOT NULL DEFAULT 'NA',
  `modules` int(11) DEFAULT NULL,
  `requires_cover` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `articles`
--

INSERT INTO `articles` (`id`, `code`, `name`, `brand_id`, `series_id`, `article_type`, `article_type_id`, `fruit_subtype`, `orientation`, `modules`, `requires_cover`) VALUES
(11, '09041', 'Modulo ciego', 1, 5, 'otro', 4, NULL, 'NA', 1, 0),
(12, '09597', 'Gateway IoT', 1, 5, 'otro', 4, NULL, 'NA', 2, 0),
(13, '09592', 'Conmutador conectado IOT', 1, 5, 'otro', 6, NULL, 'NA', 1, 1),
(14, '09613', 'Placa 3 modulos', 1, 19, 'otro', 1, NULL, 'NA', 3, 0),
(15, 'USW-8', 'Switche Poe 8 puertos', 4, 16, 'otro', 14, NULL, 'NA', NULL, 0),
(16, 'Arc Ultra', 'Sonos Arc Ultra Soundbar', 10, NULL, 'otro', 19, NULL, 'NA', NULL, 0),
(17, 'Sonos Amp', 'Sonos Amp', 10, NULL, 'otro', 20, NULL, 'NA', NULL, 0),
(18, 'Sonos Port', 'Sonos Port', 10, NULL, 'otro', 20, NULL, 'NA', NULL, 0),
(19, 'camip-prueba16mp', 'CAMARA IP DE PRUEBA', 2, 13, 'otro', 18, NULL, 'NA', NULL, 0),
(20, 'camip-prueba 2.8mm', 'CAMARA IP DE PRUEBA 2.8mm', 2, 13, 'otro', 18, NULL, 'NA', NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `article_divisions`
--

CREATE TABLE `article_divisions` (
  `article_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `article_divisions`
--

INSERT INTO `article_divisions` (`article_id`, `division_id`) VALUES
(11, 2),
(12, 2),
(13, 2),
(14, 2),
(11, 3),
(14, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `article_types`
--

CREATE TABLE `article_types` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `article_types`
--

INSERT INTO `article_types` (`id`, `code`, `name`) VALUES
(1, 'soporte', 'Soporte'),
(2, 'placa', 'Placa'),
(3, 'cajetin', 'Cajetín'),
(4, 'fruto', 'Fruto'),
(5, 'cubretecla', 'Cubretecla'),
(6, 'mecanismo', 'Mecanismo'),
(7, 'otro', 'Otro'),
(13, 'AP', 'Acces Point'),
(14, 'switche', 'Switche'),
(15, 'rack', 'Rack'),
(16, 'router', 'Router'),
(17, 'NVR', 'NVR'),
(18, 'camara', 'Camara'),
(19, 'corneta', 'Corneta'),
(20, 'amp', 'Amplificador'),
(21, 'tab', 'Tablero'),
(22, 'filtro', 'Filtro'),
(24, 'KNXAct', 'ActuadorKNX'),
(25, 'KNXCont', 'Controlador KNX'),
(26, 'KNXPant', 'Pantalla KNX');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `attachments`
--

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `entity_type` enum('project','area') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `file_type_id` int(11) DEFAULT NULL,
  `stored_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime` varchar(120) DEFAULT NULL,
  `size_bytes` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `attachments`
--

INSERT INTO `attachments` (`id`, `entity_type`, `entity_id`, `file_type_id`, `stored_path`, `original_name`, `mime`, `size_bytes`, `uploaded_by`, `created_at`) VALUES
(1, 'area', 19, 2, 'uploads/areas/area_19_20251213_213729_1d8d0891_314025dccbf6bb89a5d4b80ca656d87a.jpg', '314025dccbf6bb89a5d4b80ca656d87a.jpg', 'image/jpeg', 781959, 1, '2025-12-13 21:37:29'),
(2, 'project', 4, 2, 'uploads/projects/project_4_20251213_213828_2a45137f_Imagen1.png', 'Imagen1.png', 'image/png', 5712814, 1, '2025-12-13 21:38:28'),
(3, 'project', 4, 3, 'uploads/projects/project_4_20251213_213845_bc8fac11_1234.pdf', '1234.pdf', 'application/pdf', 184345, 1, '2025-12-13 21:38:45'),
(4, 'project', 6, 2, 'uploads/projects/project_6_20251214_155633_a1e93cf2_large_vimar.png', 'large_vimar.png', 'image/png', 155832, 1, '2025-12-14 15:56:33'),
(5, 'area', 38, 3, 'uploads/areas/area_38_20251216_191550_3accf7bc_Instructivo_ZKBio_Premier_v2.pdf', 'Instructivo_ZKBio_Premier_v2.pdf', 'application/pdf', 3954, 1, '2025-12-16 19:15:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `has_series` tinyint(1) NOT NULL DEFAULT 1,
  `tipo_proyecto` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `brands`
--

INSERT INTO `brands` (`id`, `name`, `logo_path`, `has_series`, `tipo_proyecto`, `is_active`) VALUES
(1, 'VIMAR', 'uploads/brands/brand_1.png', 1, NULL, 1),
(2, 'HIKVISION', 'uploads/brands/brand_2.jpg', 1, NULL, 1),
(4, 'UNIFI', 'uploads/brands/brand_4.png', 1, NULL, 1),
(5, 'TPLINK', 'uploads/brands/brand_5.png', 0, NULL, 1),
(6, 'MIKROTIK', 'uploads/brands/brand_6.jpg', 0, NULL, 1),
(7, 'ZKTECO', 'uploads/brands/brand_7.png', 1, NULL, 1),
(8, 'PANASONIC', 'uploads/brands/brand_8.png', 0, NULL, 1),
(9, 'GRANDSTREAM', 'uploads/brands/brand_9.png', 0, NULL, 1),
(10, 'SONOS', 'uploads/brands/brand_10.png', 0, NULL, 1),
(11, 'BOSE', 'uploads/brands/brand_11.png', 0, NULL, 1),
(12, 'ZENNIO', 'uploads/brands/brand_12.png', 0, NULL, 1),
(13, 'ARAGLASS', 'uploads/brands/brand_13.png', 0, NULL, 1),
(14, '1HOME', 'uploads/brands/brand_14.png', 0, NULL, 1),
(15, 'ZOWA', 'uploads/brands/brand_15.png', 1, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `brand_article_types`
--

CREATE TABLE `brand_article_types` (
  `brand_id` int(11) NOT NULL,
  `article_type_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `brand_article_types`
--

INSERT INTO `brand_article_types` (`brand_id`, `article_type_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(4, 13),
(6, 13),
(4, 14),
(5, 14),
(6, 14),
(4, 16),
(5, 16),
(6, 16),
(2, 17),
(4, 17),
(2, 18),
(4, 18),
(10, 19),
(11, 19),
(10, 20),
(11, 20),
(15, 22),
(12, 24),
(14, 25),
(12, 26);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `divisions`
--

CREATE TABLE `divisions` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `prefix` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `divisions`
--

INSERT INTO `divisions` (`id`, `name`, `prefix`) VALUES
(1, 'CCTV', 'CCTV'),
(2, 'DOMOTICA', 'DOM'),
(3, 'ELECTRICO', 'PE'),
(4, 'PERSIANAS', 'ROL'),
(5, 'CRISTALERIA', 'GLA'),
(6, 'INCENDIO', 'FP'),
(7, 'ALARMA Y SEGURIDAD', 'SEG'),
(8, 'REDES', 'DATA'),
(9, 'TELEFONIA', 'TEL'),
(10, 'CONTROL DE ACCESO', 'ACC'),
(11, 'SONIDO', 'SON'),
(12, 'KNK', 'KNK');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `division_brands`
--

CREATE TABLE `division_brands` (
  `division_id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `division_brands`
--

INSERT INTO `division_brands` (`division_id`, `brand_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(7, 1),
(8, 1),
(10, 1),
(12, 1),
(1, 2),
(1, 4),
(8, 4),
(9, 4),
(10, 4),
(8, 6),
(10, 7),
(9, 8),
(9, 9),
(11, 10),
(11, 11),
(12, 12),
(5, 13),
(12, 14);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `file_types`
--

CREATE TABLE `file_types` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `extensions` varchar(200) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `file_types`
--

INSERT INTO `file_types` (`id`, `code`, `name`, `extensions`, `created_at`) VALUES
(1, 'plans', 'Planos', 'pdf,dwg', '2025-12-13 01:09:35'),
(2, 'photos', 'Fotos', 'jpg,jpeg,png', '2025-12-13 01:09:35'),
(3, 'docs', 'Documentos', 'pdf,xlsx,xls', '2025-12-13 01:09:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `homologations`
--

CREATE TABLE `homologations` (
  `id` int(11) NOT NULL,
  `article_id_1` int(11) NOT NULL,
  `article_id_2` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `code` varchar(120) NOT NULL,
  `label` varchar(120) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `permissions`
--

INSERT INTO `permissions` (`id`, `module`, `action`, `code`, `label`, `created_at`) VALUES
(1, 'projects', 'view', 'projects.view', 'Ver proyectos', '2025-12-16 02:51:03'),
(2, 'projects', 'create', 'projects.create', 'Crear proyectos', '2025-12-16 02:51:03'),
(3, 'projects', 'edit', 'projects.edit', 'Editar proyectos', '2025-12-16 02:51:03'),
(4, 'projects', 'delete', 'projects.delete', 'Eliminar proyectos', '2025-12-16 02:51:03'),
(5, 'projects', 'duplicate', 'projects.duplicate', 'Duplicar proyectos', '2025-12-16 02:51:03'),
(6, 'areas', 'view', 'areas.view', 'Ver áreas', '2025-12-16 02:51:03'),
(7, 'areas', 'create', 'areas.create', 'Crear áreas', '2025-12-16 02:51:03'),
(8, 'areas', 'edit', 'areas.edit', 'Editar áreas', '2025-12-16 02:51:03'),
(9, 'areas', 'delete', 'areas.delete', 'Eliminar áreas', '2025-12-16 02:51:03'),
(10, 'areas', 'move', 'areas.move', 'Mover áreas', '2025-12-16 02:51:03'),
(11, 'points', 'view', 'points.view', 'Ver puntos', '2025-12-16 02:51:03'),
(12, 'points', 'create', 'points.create', 'Crear puntos', '2025-12-16 02:51:03'),
(13, 'points', 'edit', 'points.edit', 'Editar puntos', '2025-12-16 02:51:03'),
(14, 'points', 'delete', 'points.delete', 'Eliminar puntos', '2025-12-16 02:51:03'),
(15, 'points', 'duplicate', 'points.duplicate', 'Duplicar puntos', '2025-12-16 02:51:03'),
(16, 'points', 'move', 'points.move', 'Mover puntos', '2025-12-16 02:51:03'),
(17, 'templates', 'view', 'templates.view', 'Ver plantillas', '2025-12-16 02:51:03'),
(18, 'templates', 'create', 'templates.create', 'Crear plantillas', '2025-12-16 02:51:03'),
(19, 'templates', 'edit', 'templates.edit', 'Editar plantillas', '2025-12-16 02:51:03'),
(20, 'templates', 'delete', 'templates.delete', 'Eliminar plantillas', '2025-12-16 02:51:03'),
(21, 'catalog', 'view', 'catalog.view', 'Ver catálogos', '2025-12-16 02:51:03'),
(22, 'catalog', 'edit', 'catalog.edit', 'Editar catálogos', '2025-12-16 02:51:03'),
(23, 'rules', 'view', 'rules.view', 'Ver reglas', '2025-12-16 02:51:03'),
(24, 'rules', 'edit', 'rules.edit', 'Editar reglas', '2025-12-16 02:51:03'),
(25, 'articles', 'view', 'articles.view', 'Ver artículos', '2025-12-16 02:51:03'),
(26, 'articles', 'create', 'articles.create', 'Crear artículos', '2025-12-16 02:51:03'),
(27, 'articles', 'edit', 'articles.edit', 'Editar artículos', '2025-12-16 02:51:03'),
(28, 'articles', 'delete', 'articles.delete', 'Eliminar artículos', '2025-12-16 02:51:03'),
(29, 'users', 'view', 'users.view', 'Ver usuarios', '2025-12-16 02:51:03'),
(30, 'users', 'create', 'users.create', 'Crear usuarios', '2025-12-16 02:51:03'),
(31, 'users', 'edit', 'users.edit', 'Editar usuarios', '2025-12-16 02:51:03'),
(32, 'users', 'disable', 'users.disable', 'Activar/Desactivar usuarios', '2025-12-16 02:51:03'),
(33, 'users', 'roles', 'users.roles', 'Asignar roles a usuarios', '2025-12-16 02:51:03'),
(34, 'roles', 'view', 'roles.view', 'Ver roles', '2025-12-16 02:51:03'),
(35, 'roles', 'create', 'roles.create', 'Crear roles', '2025-12-16 02:51:03'),
(36, 'roles', 'edit', 'roles.edit', 'Editar roles', '2025-12-16 02:51:03'),
(37, 'roles', 'delete', 'roles.delete', 'Eliminar roles', '2025-12-16 02:51:03'),
(38, 'roles', 'permissions', 'roles.permissions', 'Configurar permisos', '2025-12-16 02:51:03'),
(39, 'logs', 'view', 'logs.view', 'Ver logs', '2025-12-16 02:51:03'),
(40, 'areas', 'duplicate', 'areas.duplicate', 'AREAS - duplicate', '2025-12-16 02:51:18'),
(41, 'areas', 'export', 'areas.export', 'AREAS - export', '2025-12-16 02:51:18'),
(42, 'areas', 'import', 'areas.import', 'AREAS - import', '2025-12-16 02:51:18'),
(43, 'areas', 'manage', 'areas.manage', 'AREAS - manage', '2025-12-16 02:51:18'),
(44, 'areas', 'report', 'areas.report', 'AREAS - report', '2025-12-16 02:51:18'),
(45, 'articles', 'duplicate', 'articles.duplicate', 'ARTICLES - duplicate', '2025-12-16 02:51:18'),
(46, 'articles', 'export', 'articles.export', 'ARTICLES - export', '2025-12-16 02:51:18'),
(47, 'articles', 'import', 'articles.import', 'ARTICLES - import', '2025-12-16 02:51:18'),
(48, 'articles', 'manage', 'articles.manage', 'ARTICLES - manage', '2025-12-16 02:51:18'),
(49, 'articles', 'move', 'articles.move', 'ARTICLES - move', '2025-12-16 02:51:18'),
(50, 'articles', 'report', 'articles.report', 'ARTICLES - report', '2025-12-16 02:51:18'),
(51, 'brands', 'create', 'brands.create', 'BRANDS - create', '2025-12-16 02:51:18'),
(52, 'brands', 'delete', 'brands.delete', 'BRANDS - delete', '2025-12-16 02:51:18'),
(53, 'brands', 'duplicate', 'brands.duplicate', 'BRANDS - duplicate', '2025-12-16 02:51:18'),
(54, 'brands', 'edit', 'brands.edit', 'BRANDS - edit', '2025-12-16 02:51:18'),
(55, 'brands', 'export', 'brands.export', 'BRANDS - export', '2025-12-16 02:51:18'),
(56, 'brands', 'import', 'brands.import', 'BRANDS - import', '2025-12-16 02:51:18'),
(57, 'brands', 'manage', 'brands.manage', 'BRANDS - manage', '2025-12-16 02:51:18'),
(58, 'brands', 'move', 'brands.move', 'BRANDS - move', '2025-12-16 02:51:18'),
(59, 'brands', 'report', 'brands.report', 'BRANDS - report', '2025-12-16 02:51:18'),
(60, 'brands', 'view', 'brands.view', 'BRANDS - view', '2025-12-16 02:51:18'),
(61, 'catalog', 'create', 'catalog.create', 'CATALOG - create', '2025-12-16 02:51:18'),
(62, 'catalog', 'delete', 'catalog.delete', 'CATALOG - delete', '2025-12-16 02:51:18'),
(63, 'catalog', 'duplicate', 'catalog.duplicate', 'CATALOG - duplicate', '2025-12-16 02:51:18'),
(64, 'catalog', 'export', 'catalog.export', 'CATALOG - export', '2025-12-16 02:51:18'),
(65, 'catalog', 'import', 'catalog.import', 'CATALOG - import', '2025-12-16 02:51:18'),
(66, 'catalog', 'manage', 'catalog.manage', 'CATALOG - manage', '2025-12-16 02:51:18'),
(67, 'catalog', 'move', 'catalog.move', 'CATALOG - move', '2025-12-16 02:51:18'),
(68, 'catalog', 'report', 'catalog.report', 'CATALOG - report', '2025-12-16 02:51:18'),
(69, 'divisions', 'create', 'divisions.create', 'DIVISIONS - create', '2025-12-16 02:51:18'),
(70, 'divisions', 'delete', 'divisions.delete', 'DIVISIONS - delete', '2025-12-16 02:51:18'),
(71, 'divisions', 'duplicate', 'divisions.duplicate', 'DIVISIONS - duplicate', '2025-12-16 02:51:18'),
(72, 'divisions', 'edit', 'divisions.edit', 'DIVISIONS - edit', '2025-12-16 02:51:18'),
(73, 'divisions', 'export', 'divisions.export', 'DIVISIONS - export', '2025-12-16 02:51:18'),
(74, 'divisions', 'import', 'divisions.import', 'DIVISIONS - import', '2025-12-16 02:51:18'),
(75, 'divisions', 'manage', 'divisions.manage', 'DIVISIONS - manage', '2025-12-16 02:51:18'),
(76, 'divisions', 'move', 'divisions.move', 'DIVISIONS - move', '2025-12-16 02:51:18'),
(77, 'divisions', 'report', 'divisions.report', 'DIVISIONS - report', '2025-12-16 02:51:18'),
(78, 'divisions', 'view', 'divisions.view', 'DIVISIONS - view', '2025-12-16 02:51:18'),
(79, 'explosion', 'create', 'explosion.create', 'EXPLOSION - create', '2025-12-16 02:51:18'),
(80, 'explosion', 'delete', 'explosion.delete', 'EXPLOSION - delete', '2025-12-16 02:51:18'),
(81, 'explosion', 'duplicate', 'explosion.duplicate', 'EXPLOSION - duplicate', '2025-12-16 02:51:18'),
(82, 'explosion', 'edit', 'explosion.edit', 'EXPLOSION - edit', '2025-12-16 02:51:18'),
(83, 'explosion', 'export', 'explosion.export', 'EXPLOSION - export', '2025-12-16 02:51:18'),
(84, 'explosion', 'import', 'explosion.import', 'EXPLOSION - import', '2025-12-16 02:51:18'),
(85, 'explosion', 'manage', 'explosion.manage', 'EXPLOSION - manage', '2025-12-16 02:51:18'),
(86, 'explosion', 'move', 'explosion.move', 'EXPLOSION - move', '2025-12-16 02:51:18'),
(87, 'explosion', 'report', 'explosion.report', 'EXPLOSION - report', '2025-12-16 02:51:18'),
(88, 'explosion', 'view', 'explosion.view', 'EXPLOSION - view', '2025-12-16 02:51:18'),
(89, 'homologations', 'create', 'homologations.create', 'HOMOLOGATIONS - create', '2025-12-16 02:51:18'),
(90, 'homologations', 'delete', 'homologations.delete', 'HOMOLOGATIONS - delete', '2025-12-16 02:51:18'),
(91, 'homologations', 'duplicate', 'homologations.duplicate', 'HOMOLOGATIONS - duplicate', '2025-12-16 02:51:18'),
(92, 'homologations', 'edit', 'homologations.edit', 'HOMOLOGATIONS - edit', '2025-12-16 02:51:18'),
(93, 'homologations', 'export', 'homologations.export', 'HOMOLOGATIONS - export', '2025-12-16 02:51:18'),
(94, 'homologations', 'import', 'homologations.import', 'HOMOLOGATIONS - import', '2025-12-16 02:51:18'),
(95, 'homologations', 'manage', 'homologations.manage', 'HOMOLOGATIONS - manage', '2025-12-16 02:51:18'),
(96, 'homologations', 'move', 'homologations.move', 'HOMOLOGATIONS - move', '2025-12-16 02:51:18'),
(97, 'homologations', 'report', 'homologations.report', 'HOMOLOGATIONS - report', '2025-12-16 02:51:18'),
(98, 'homologations', 'view', 'homologations.view', 'HOMOLOGATIONS - view', '2025-12-16 02:51:18'),
(99, 'logs', 'create', 'logs.create', 'LOGS - create', '2025-12-16 02:51:18'),
(100, 'logs', 'delete', 'logs.delete', 'LOGS - delete', '2025-12-16 02:51:18'),
(101, 'logs', 'duplicate', 'logs.duplicate', 'LOGS - duplicate', '2025-12-16 02:51:18'),
(102, 'logs', 'edit', 'logs.edit', 'LOGS - edit', '2025-12-16 02:51:18'),
(103, 'logs', 'export', 'logs.export', 'LOGS - export', '2025-12-16 02:51:18'),
(104, 'logs', 'import', 'logs.import', 'LOGS - import', '2025-12-16 02:51:18'),
(105, 'logs', 'manage', 'logs.manage', 'LOGS - manage', '2025-12-16 02:51:18'),
(106, 'logs', 'move', 'logs.move', 'LOGS - move', '2025-12-16 02:51:18'),
(107, 'logs', 'report', 'logs.report', 'LOGS - report', '2025-12-16 02:51:18'),
(108, 'points', 'export', 'points.export', 'POINTS - export', '2025-12-16 02:51:18'),
(109, 'points', 'import', 'points.import', 'POINTS - import', '2025-12-16 02:51:18'),
(110, 'points', 'manage', 'points.manage', 'POINTS - manage', '2025-12-16 02:51:18'),
(111, 'points', 'report', 'points.report', 'POINTS - report', '2025-12-16 02:51:18'),
(112, 'projects', 'export', 'projects.export', 'PROJECTS - export', '2025-12-16 02:51:18'),
(113, 'projects', 'import', 'projects.import', 'PROJECTS - import', '2025-12-16 02:51:18'),
(114, 'projects', 'manage', 'projects.manage', 'PROJECTS - manage', '2025-12-16 02:51:18'),
(115, 'projects', 'move', 'projects.move', 'PROJECTS - move', '2025-12-16 02:51:18'),
(116, 'projects', 'report', 'projects.report', 'PROJECTS - report', '2025-12-16 02:51:18'),
(117, 'roles', 'duplicate', 'roles.duplicate', 'ROLES - duplicate', '2025-12-16 02:51:18'),
(118, 'roles', 'export', 'roles.export', 'ROLES - export', '2025-12-16 02:51:18'),
(119, 'roles', 'import', 'roles.import', 'ROLES - import', '2025-12-16 02:51:18'),
(120, 'roles', 'manage', 'roles.manage', 'ROLES - manage', '2025-12-16 02:51:18'),
(121, 'roles', 'move', 'roles.move', 'ROLES - move', '2025-12-16 02:51:18'),
(122, 'roles', 'report', 'roles.report', 'ROLES - report', '2025-12-16 02:51:18'),
(123, 'rules', 'create', 'rules.create', 'RULES - create', '2025-12-16 02:51:18'),
(124, 'rules', 'delete', 'rules.delete', 'RULES - delete', '2025-12-16 02:51:18'),
(125, 'rules', 'duplicate', 'rules.duplicate', 'RULES - duplicate', '2025-12-16 02:51:18'),
(126, 'rules', 'export', 'rules.export', 'RULES - export', '2025-12-16 02:51:18'),
(127, 'rules', 'import', 'rules.import', 'RULES - import', '2025-12-16 02:51:18'),
(128, 'rules', 'manage', 'rules.manage', 'RULES - manage', '2025-12-16 02:51:18'),
(129, 'rules', 'move', 'rules.move', 'RULES - move', '2025-12-16 02:51:18'),
(130, 'rules', 'report', 'rules.report', 'RULES - report', '2025-12-16 02:51:18'),
(131, 'series', 'create', 'series.create', 'SERIES - create', '2025-12-16 02:51:18'),
(132, 'series', 'delete', 'series.delete', 'SERIES - delete', '2025-12-16 02:51:18'),
(133, 'series', 'duplicate', 'series.duplicate', 'SERIES - duplicate', '2025-12-16 02:51:18'),
(134, 'series', 'edit', 'series.edit', 'SERIES - edit', '2025-12-16 02:51:18'),
(135, 'series', 'export', 'series.export', 'SERIES - export', '2025-12-16 02:51:18'),
(136, 'series', 'import', 'series.import', 'SERIES - import', '2025-12-16 02:51:18'),
(137, 'series', 'manage', 'series.manage', 'SERIES - manage', '2025-12-16 02:51:18'),
(138, 'series', 'move', 'series.move', 'SERIES - move', '2025-12-16 02:51:18'),
(139, 'series', 'report', 'series.report', 'SERIES - report', '2025-12-16 02:51:18'),
(140, 'series', 'view', 'series.view', 'SERIES - view', '2025-12-16 02:51:18'),
(141, 'settings', 'create', 'settings.create', 'SETTINGS - create', '2025-12-16 02:51:18'),
(142, 'settings', 'delete', 'settings.delete', 'SETTINGS - delete', '2025-12-16 02:51:18'),
(143, 'settings', 'duplicate', 'settings.duplicate', 'SETTINGS - duplicate', '2025-12-16 02:51:18'),
(144, 'settings', 'edit', 'settings.edit', 'SETTINGS - edit', '2025-12-16 02:51:18'),
(145, 'settings', 'export', 'settings.export', 'SETTINGS - export', '2025-12-16 02:51:18'),
(146, 'settings', 'import', 'settings.import', 'SETTINGS - import', '2025-12-16 02:51:18'),
(147, 'settings', 'manage', 'settings.manage', 'SETTINGS - manage', '2025-12-16 02:51:18'),
(148, 'settings', 'move', 'settings.move', 'SETTINGS - move', '2025-12-16 02:51:18'),
(149, 'settings', 'report', 'settings.report', 'SETTINGS - report', '2025-12-16 02:51:18'),
(150, 'settings', 'view', 'settings.view', 'SETTINGS - view', '2025-12-16 02:51:18'),
(151, 'templates', 'duplicate', 'templates.duplicate', 'TEMPLATES - duplicate', '2025-12-16 02:51:18'),
(152, 'templates', 'export', 'templates.export', 'TEMPLATES - export', '2025-12-16 02:51:18'),
(153, 'templates', 'import', 'templates.import', 'TEMPLATES - import', '2025-12-16 02:51:18'),
(154, 'templates', 'manage', 'templates.manage', 'TEMPLATES - manage', '2025-12-16 02:51:18'),
(155, 'templates', 'move', 'templates.move', 'TEMPLATES - move', '2025-12-16 02:51:18'),
(156, 'templates', 'report', 'templates.report', 'TEMPLATES - report', '2025-12-16 02:51:18'),
(157, 'users', 'delete', 'users.delete', 'USERS - delete', '2025-12-16 02:51:18'),
(158, 'users', 'duplicate', 'users.duplicate', 'USERS - duplicate', '2025-12-16 02:51:18'),
(159, 'users', 'export', 'users.export', 'USERS - export', '2025-12-16 02:51:18'),
(160, 'users', 'import', 'users.import', 'USERS - import', '2025-12-16 02:51:18'),
(161, 'users', 'manage', 'users.manage', 'USERS - manage', '2025-12-16 02:51:18'),
(162, 'users', 'move', 'users.move', 'USERS - move', '2025-12-16 02:51:18'),
(163, 'users', 'report', 'users.report', 'USERS - report', '2025-12-16 02:51:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `points`
--

CREATE TABLE `points` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `division_id` int(11) DEFAULT NULL,
  `seq` int(11) DEFAULT NULL,
  `point_code` varchar(20) DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `system_type` varchar(50) NOT NULL,
  `orientation` enum('H','V','NA') NOT NULL DEFAULT 'NA',
  `brand_id` int(11) DEFAULT NULL,
  `series_id` int(11) DEFAULT NULL,
  `modules` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `support_article_id` int(11) DEFAULT NULL,
  `box_article_id` int(11) DEFAULT NULL,
  `plate_article_id` int(11) DEFAULT NULL,
  `cover_article_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `points`
--

INSERT INTO `points` (`id`, `project_id`, `area_id`, `division_id`, `seq`, `point_code`, `code`, `description`, `location`, `system_type`, `orientation`, `brand_id`, `series_id`, `modules`, `quantity`, `support_article_id`, `box_article_id`, `plate_article_id`, `cover_article_id`) VALUES
(1, 2, 1, NULL, NULL, NULL, '574', 'interruptor triple', NULL, 'electrico', 'H', NULL, NULL, 2, 1, NULL, NULL, NULL, NULL),
(2, 2, NULL, NULL, NULL, NULL, '574', 'interruptor triple', NULL, 'wifi', 'H', NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(3, 4, 19, NULL, NULL, NULL, 'qa', 'punto1', NULL, 'electrico', 'H', NULL, NULL, 2, 1, NULL, NULL, NULL, NULL),
(4, 4, 38, NULL, NULL, NULL, 'qa', 'punto1', NULL, 'electrico', 'H', NULL, NULL, 2, 1, NULL, NULL, NULL, NULL),
(5, 4, 38, NULL, NULL, NULL, 'qa', 'punto1', NULL, 'electrico', 'H', NULL, NULL, 2, 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `point_components`
--

CREATE TABLE `point_components` (
  `id` int(11) NOT NULL,
  `point_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `position` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `point_templates`
--

CREATE TABLE `point_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `series_id` int(11) DEFAULT NULL,
  `modules` int(11) NOT NULL,
  `support_article_id` int(11) DEFAULT NULL,
  `plate_article_id` int(11) DEFAULT NULL,
  `cover_article_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `point_template_components`
--

CREATE TABLE `point_template_components` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `position` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `point_types`
--

CREATE TABLE `point_types` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `system_type_code` varchar(50) NOT NULL,
  `require_support` tinyint(1) NOT NULL DEFAULT 0,
  `use_orientation` tinyint(1) NOT NULL DEFAULT 0,
  `use_modules` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `client` varchar(200) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `default_brand_id` int(11) DEFAULT NULL,
  `default_series_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `projects`
--

INSERT INTO `projects` (`id`, `name`, `is_closed`, `client`, `address`, `default_brand_id`, `default_series_id`, `description`, `created_by`, `created_at`) VALUES
(2, 'proyecto1', 0, 'cliente1', 'a', 1, 1, '', NULL, '2025-12-12 03:19:03'),
(3, 'CASA SALVATORE', 1, 'SALVATORE CAMMARANO', 'TERRAZAS DEL AVILA', NULL, NULL, '', NULL, '2025-12-12 04:57:31'),
(4, 'CASA VIGLIOTTI', 0, '', '', NULL, NULL, '', NULL, '2025-12-12 16:01:39'),
(5, 'CASA VIGLIOTTI (Copia)', 1, NULL, NULL, NULL, NULL, NULL, 1, '2025-12-13 20:02:44'),
(6, 'CASA VIGLIOTTI (Copia)', 1, NULL, NULL, NULL, NULL, NULL, 1, '2025-12-13 21:55:36'),
(9, 'Casa 420', 0, 'Jorge Pacheco', NULL, NULL, NULL, NULL, 1, '2025-12-15 04:05:42'),
(10, 'CAPRILES', 0, '', NULL, NULL, NULL, NULL, 1, '2025-12-15 15:04:48'),
(11, 'prueba', 0, '', NULL, NULL, NULL, NULL, 1, '2025-12-16 03:15:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `project_brands`
--

CREATE TABLE `project_brands` (
  `project_id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `project_divisions`
--

CREATE TABLE `project_divisions` (
  `project_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `project_divisions`
--

INSERT INTO `project_divisions` (`project_id`, `division_id`) VALUES
(2, 1),
(4, 1),
(10, 1),
(3, 2),
(4, 2),
(10, 2),
(2, 3),
(3, 3),
(4, 3),
(10, 3),
(3, 4),
(9, 7),
(2, 8),
(3, 8),
(4, 8),
(10, 8),
(9, 10),
(9, 11);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `project_series`
--

CREATE TABLE `project_series` (
  `project_id` int(11) NOT NULL,
  `series_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'admin', 1, 1, '2025-12-16 02:51:03', '2025-12-16 02:51:03'),
(2, 'Editor', 'editor', 1, 1, '2025-12-16 02:51:03', '2025-12-16 02:51:03'),
(3, 'Viewer', 'viewer', 1, 1, '2025-12-16 02:51:03', '2025-12-16 02:51:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `allowed`) VALUES
(1, 1, 1),
(1, 2, 1),
(1, 3, 1),
(1, 4, 1),
(1, 5, 1),
(1, 6, 1),
(1, 7, 1),
(1, 8, 1),
(1, 9, 1),
(1, 10, 1),
(1, 11, 1),
(1, 12, 1),
(1, 13, 1),
(1, 14, 1),
(1, 15, 1),
(1, 16, 1),
(1, 17, 1),
(1, 18, 1),
(1, 19, 1),
(1, 20, 1),
(1, 21, 1),
(1, 22, 1),
(1, 23, 1),
(1, 24, 1),
(1, 25, 1),
(1, 26, 1),
(1, 27, 1),
(1, 28, 1),
(1, 29, 1),
(1, 30, 1),
(1, 31, 1),
(1, 32, 1),
(1, 33, 1),
(1, 34, 1),
(1, 35, 1),
(1, 36, 1),
(1, 37, 1),
(1, 38, 1),
(1, 39, 1),
(1, 40, 1),
(1, 41, 1),
(1, 42, 1),
(1, 43, 1),
(1, 44, 1),
(1, 45, 1),
(1, 46, 1),
(1, 47, 1),
(1, 48, 1),
(1, 49, 1),
(1, 50, 1),
(1, 51, 1),
(1, 52, 1),
(1, 53, 1),
(1, 54, 1),
(1, 55, 1),
(1, 56, 1),
(1, 57, 1),
(1, 58, 1),
(1, 59, 1),
(1, 60, 1),
(1, 61, 1),
(1, 62, 1),
(1, 63, 1),
(1, 64, 1),
(1, 65, 1),
(1, 66, 1),
(1, 67, 1),
(1, 68, 1),
(1, 69, 1),
(1, 70, 1),
(1, 71, 1),
(1, 72, 1),
(1, 73, 1),
(1, 74, 1),
(1, 75, 1),
(1, 76, 1),
(1, 77, 1),
(1, 78, 1),
(1, 79, 1),
(1, 80, 1),
(1, 81, 1),
(1, 82, 1),
(1, 83, 1),
(1, 84, 1),
(1, 85, 1),
(1, 86, 1),
(1, 87, 1),
(1, 88, 1),
(1, 89, 1),
(1, 90, 1),
(1, 91, 1),
(1, 92, 1),
(1, 93, 1),
(1, 94, 1),
(1, 95, 1),
(1, 96, 1),
(1, 97, 1),
(1, 98, 1),
(1, 99, 1),
(1, 100, 1),
(1, 101, 1),
(1, 102, 1),
(1, 103, 1),
(1, 104, 1),
(1, 105, 1),
(1, 106, 1),
(1, 107, 1),
(1, 108, 1),
(1, 109, 1),
(1, 110, 1),
(1, 111, 1),
(1, 112, 1),
(1, 113, 1),
(1, 114, 1),
(1, 115, 1),
(1, 116, 1),
(1, 117, 1),
(1, 118, 1),
(1, 119, 1),
(1, 120, 1),
(1, 121, 1),
(1, 122, 1),
(1, 123, 1),
(1, 124, 1),
(1, 125, 1),
(1, 126, 1),
(1, 127, 1),
(1, 128, 1),
(1, 129, 1),
(1, 130, 1),
(1, 131, 1),
(1, 132, 1),
(1, 133, 1),
(1, 134, 1),
(1, 135, 1),
(1, 136, 1),
(1, 137, 1),
(1, 138, 1),
(1, 139, 1),
(1, 140, 1),
(1, 141, 1),
(1, 142, 1),
(1, 143, 1),
(1, 144, 1),
(1, 145, 1),
(1, 146, 1),
(1, 147, 1),
(1, 148, 1),
(1, 149, 1),
(1, 150, 1),
(1, 151, 1),
(1, 152, 1),
(1, 153, 1),
(1, 154, 1),
(1, 155, 1),
(1, 156, 1),
(1, 157, 1),
(1, 158, 1),
(1, 159, 1),
(1, 160, 1),
(1, 161, 1),
(1, 162, 1),
(1, 163, 1),
(2, 1, 1),
(2, 2, 0),
(2, 3, 0),
(2, 4, 0),
(2, 5, 0),
(2, 6, 1),
(2, 7, 0),
(2, 8, 0),
(2, 9, 0),
(2, 10, 0),
(2, 11, 1),
(2, 12, 0),
(2, 13, 0),
(2, 14, 0),
(2, 15, 0),
(2, 16, 0),
(2, 17, 1),
(2, 18, 0),
(2, 19, 0),
(2, 20, 0),
(2, 21, 1),
(2, 22, 0),
(2, 23, 1),
(2, 24, 0),
(2, 25, 1),
(2, 26, 0),
(2, 27, 0),
(2, 28, 0),
(2, 29, 1),
(2, 30, 0),
(2, 31, 0),
(2, 32, 0),
(2, 33, 0),
(2, 34, 1),
(2, 35, 0),
(2, 36, 0),
(2, 37, 0),
(2, 38, 0),
(2, 39, 1),
(2, 40, 0),
(2, 41, 0),
(2, 42, 0),
(2, 43, 0),
(2, 44, 0),
(2, 45, 0),
(2, 46, 0),
(2, 47, 0),
(2, 48, 0),
(2, 49, 0),
(2, 50, 0),
(2, 51, 0),
(2, 52, 0),
(2, 53, 0),
(2, 54, 0),
(2, 55, 0),
(2, 56, 0),
(2, 57, 0),
(2, 58, 0),
(2, 59, 0),
(2, 60, 1),
(2, 61, 0),
(2, 62, 0),
(2, 63, 0),
(2, 64, 0),
(2, 65, 0),
(2, 66, 0),
(2, 67, 0),
(2, 68, 0),
(2, 69, 0),
(2, 70, 0),
(2, 71, 0),
(2, 72, 0),
(2, 73, 0),
(2, 74, 0),
(2, 75, 0),
(2, 76, 0),
(2, 77, 0),
(2, 78, 1),
(2, 79, 0),
(2, 80, 0),
(2, 81, 0),
(2, 82, 0),
(2, 83, 0),
(2, 84, 0),
(2, 85, 0),
(2, 86, 0),
(2, 87, 0),
(2, 88, 1),
(2, 89, 0),
(2, 90, 0),
(2, 91, 0),
(2, 92, 0),
(2, 93, 0),
(2, 94, 0),
(2, 95, 0),
(2, 96, 0),
(2, 97, 0),
(2, 98, 1),
(2, 99, 0),
(2, 100, 0),
(2, 101, 0),
(2, 102, 0),
(2, 103, 0),
(2, 104, 0),
(2, 105, 0),
(2, 106, 0),
(2, 107, 0),
(2, 108, 0),
(2, 109, 0),
(2, 110, 0),
(2, 111, 0),
(2, 112, 0),
(2, 113, 0),
(2, 114, 0),
(2, 115, 0),
(2, 116, 0),
(2, 117, 0),
(2, 118, 0),
(2, 119, 0),
(2, 120, 0),
(2, 121, 0),
(2, 122, 0),
(2, 123, 0),
(2, 124, 0),
(2, 125, 0),
(2, 126, 0),
(2, 127, 0),
(2, 128, 0),
(2, 129, 0),
(2, 130, 0),
(2, 131, 0),
(2, 132, 0),
(2, 133, 0),
(2, 134, 0),
(2, 135, 0),
(2, 136, 0),
(2, 137, 0),
(2, 138, 0),
(2, 139, 0),
(2, 140, 1),
(2, 141, 0),
(2, 142, 0),
(2, 143, 0),
(2, 144, 0),
(2, 145, 0),
(2, 146, 0),
(2, 147, 0),
(2, 148, 0),
(2, 149, 0),
(2, 150, 1),
(2, 151, 0),
(2, 152, 0),
(2, 153, 0),
(2, 154, 0),
(2, 155, 0),
(2, 156, 0),
(2, 157, 0),
(2, 158, 0),
(2, 159, 0),
(2, 160, 0),
(2, 161, 0),
(2, 162, 0),
(2, 163, 0),
(3, 1, 1),
(3, 2, 0),
(3, 3, 0),
(3, 4, 0),
(3, 5, 0),
(3, 6, 1),
(3, 7, 0),
(3, 8, 0),
(3, 9, 0),
(3, 10, 0),
(3, 11, 1),
(3, 12, 0),
(3, 13, 0),
(3, 14, 0),
(3, 15, 0),
(3, 16, 0),
(3, 17, 1),
(3, 18, 0),
(3, 19, 0),
(3, 20, 0),
(3, 21, 1),
(3, 22, 0),
(3, 23, 1),
(3, 24, 0),
(3, 25, 1),
(3, 26, 0),
(3, 27, 0),
(3, 28, 0),
(3, 29, 1),
(3, 30, 0),
(3, 31, 0),
(3, 32, 0),
(3, 33, 0),
(3, 34, 1),
(3, 35, 0),
(3, 36, 0),
(3, 37, 0),
(3, 38, 0),
(3, 39, 1),
(3, 40, 0),
(3, 41, 0),
(3, 42, 0),
(3, 43, 0),
(3, 44, 0),
(3, 45, 0),
(3, 46, 0),
(3, 47, 0),
(3, 48, 0),
(3, 49, 0),
(3, 50, 0),
(3, 51, 0),
(3, 52, 0),
(3, 53, 0),
(3, 54, 0),
(3, 55, 0),
(3, 56, 0),
(3, 57, 0),
(3, 58, 0),
(3, 59, 0),
(3, 60, 0),
(3, 61, 0),
(3, 62, 0),
(3, 63, 0),
(3, 64, 0),
(3, 65, 0),
(3, 66, 0),
(3, 67, 0),
(3, 68, 0),
(3, 69, 0),
(3, 70, 0),
(3, 71, 0),
(3, 72, 0),
(3, 73, 0),
(3, 74, 0),
(3, 75, 0),
(3, 76, 0),
(3, 77, 0),
(3, 78, 0),
(3, 79, 0),
(3, 80, 0),
(3, 81, 0),
(3, 82, 0),
(3, 83, 0),
(3, 84, 0),
(3, 85, 0),
(3, 86, 0),
(3, 87, 0),
(3, 88, 0),
(3, 89, 0),
(3, 90, 0),
(3, 91, 0),
(3, 92, 0),
(3, 93, 0),
(3, 94, 0),
(3, 95, 0),
(3, 96, 0),
(3, 97, 0),
(3, 98, 0),
(3, 99, 0),
(3, 100, 0),
(3, 101, 0),
(3, 102, 0),
(3, 103, 0),
(3, 104, 0),
(3, 105, 0),
(3, 106, 0),
(3, 107, 0),
(3, 108, 0),
(3, 109, 0),
(3, 110, 0),
(3, 111, 0),
(3, 112, 0),
(3, 113, 0),
(3, 114, 0),
(3, 115, 0),
(3, 116, 0),
(3, 117, 0),
(3, 118, 0),
(3, 119, 0),
(3, 120, 0),
(3, 121, 0),
(3, 122, 0),
(3, 123, 0),
(3, 124, 0),
(3, 125, 0),
(3, 126, 0),
(3, 127, 0),
(3, 128, 0),
(3, 129, 0),
(3, 130, 0),
(3, 131, 0),
(3, 132, 0),
(3, 133, 0),
(3, 134, 0),
(3, 135, 0),
(3, 136, 0),
(3, 137, 0),
(3, 138, 0),
(3, 139, 0),
(3, 140, 0),
(3, 141, 0),
(3, 142, 0),
(3, 143, 0),
(3, 144, 0),
(3, 145, 0),
(3, 146, 0),
(3, 147, 0),
(3, 148, 0),
(3, 149, 0),
(3, 150, 0),
(3, 151, 0),
(3, 152, 0),
(3, 153, 0),
(3, 154, 0),
(3, 155, 0),
(3, 156, 0),
(3, 157, 0),
(3, 158, 0),
(3, 159, 0),
(3, 160, 0),
(3, 161, 0),
(3, 162, 0),
(3, 163, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `series`
--

CREATE TABLE `series` (
  `id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `parent_series_id` int(11) DEFAULT NULL,
  `family_key` varchar(60) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `is_base` tinyint(1) NOT NULL DEFAULT 0,
  `manufacturer_series_id` varchar(60) DEFAULT NULL,
  `base_color_code` varchar(7) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `series`
--

INSERT INTO `series` (`id`, `brand_id`, `parent_series_id`, `family_key`, `name`, `is_base`, `manufacturer_series_id`, `base_color_code`, `is_active`) VALUES
(1, 1, 18, NULL, 'LINEA NEGRO', 0, NULL, NULL, 1),
(2, 1, 19, NULL, 'NEVE UP CARBON MATTE', 0, NULL, NULL, 1),
(3, 1, 18, NULL, 'LINEA BLANCO', 0, NULL, NULL, 1),
(4, 1, 18, NULL, 'LINEA CAÑAMO', 1, NULL, NULL, 1),
(5, 1, 19, NULL, 'NEVE UP BLANCO', 1, NULL, NULL, 1),
(6, 1, 20, NULL, 'PLANA SILVER', 0, NULL, NULL, 1),
(7, 1, NULL, NULL, 'PLANA UP', 0, NULL, NULL, 1),
(8, 1, 17, 'ARKÉ', 'ARKÉ GRIS', 1, NULL, NULL, 1),
(9, 1, 17, 'ARKÉ', 'ARKÉ BLANCO', 0, NULL, NULL, 1),
(10, 1, 17, NULL, 'ARKÉ METAL', 0, NULL, NULL, 1),
(11, 2, NULL, NULL, 'NVRS', 0, NULL, NULL, 1),
(12, 2, NULL, NULL, 'DVRS', 0, NULL, NULL, 1),
(13, 2, NULL, NULL, 'CAMARAS IP', 0, NULL, NULL, 1),
(15, 4, NULL, NULL, 'CAMARAS', 0, NULL, NULL, 1),
(16, 4, NULL, NULL, 'ROUTER', 0, NULL, NULL, 1),
(17, 1, NULL, NULL, 'ARKÉ', 0, NULL, NULL, 0),
(18, 1, NULL, NULL, 'LINEA', 0, NULL, NULL, 1),
(19, 1, NULL, NULL, 'NEVE UP', 0, NULL, NULL, 1),
(20, 1, NULL, NULL, 'PLANA', 0, NULL, NULL, 1),
(21, 1, 20, NULL, 'PLANA BLANCA', 1, NULL, NULL, 1),
(22, 4, NULL, NULL, 'ACCESS POINTS', 0, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_types`
--

CREATE TABLE `system_types` (
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `system_types`
--

INSERT INTO `system_types` (`code`, `name`, `active`) VALUES
('CAM', 'CCTV (CAM)', 1),
('cctv', 'CCTV', 1),
('DATA', 'Redes (DATA)', 1),
('datos', 'Datos', 1),
('electrico', 'Eléctrico', 1),
('otros', 'Otros', 1),
('PE', 'Eléctrico (PE)', 1),
('persianas', 'Persianas', 1),
('redes', 'Redes / Data', 1),
('wifi', 'WiFi', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$10$6q619Gipv5EXPeYHQ/N05.EY6ksB/eLa3J0rXRyS8vTGwdkumDqHy', 'admin', 1, '2025-12-10 04:01:23'),
(2, 'toto', '$2y$10$6q619Gipv5EXPeYHQ/N05.EY6ksB/eLa3J0rXRyS8vTGwdkumDqHy', 'viewer', 1, '2025-12-10 04:01:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `object_type` varchar(50) NOT NULL,
  `object_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `user_logs`
--

INSERT INTO `user_logs` (`id`, `action`, `entity_type`, `entity_id`, `object_type`, `object_id`, `user_id`, `timestamp`, `description`, `created_at`) VALUES
(1, 'project_divisions_set', 'project', 2, '', NULL, 1, '2025-12-12 04:53:35', 'divisions=1,3,8', '2025-12-12 04:53:35'),
(2, 'area_create', 'area', 6, '', NULL, 1, '2025-12-12 04:53:54', 'project=2 name=family room', '2025-12-12 04:53:54'),
(3, 'area_divisions_set', 'area', 1, '', NULL, 1, '2025-12-12 04:54:12', 'divisions=1,3,8', '2025-12-12 04:54:12'),
(4, 'area_divisions_set', 'area', 3, '', NULL, 1, '2025-12-12 04:54:19', 'divisions=3,8', '2025-12-12 04:54:19'),
(5, 'area_divisions_set', 'area', 6, '', NULL, 1, '2025-12-12 04:54:26', 'divisions=1,3,8', '2025-12-12 04:54:26'),
(6, 'area_divisions_set', 'area', 4, '', NULL, 1, '2025-12-12 04:54:30', 'divisions=1,3,8', '2025-12-12 04:54:30'),
(7, 'area_divisions_set', 'area', 5, '', NULL, 1, '2025-12-12 04:54:36', 'divisions=1,3,8', '2025-12-12 04:54:36'),
(8, 'area_divisions_set', 'area', 2, '', NULL, 1, '2025-12-12 04:54:42', 'divisions=3,8', '2025-12-12 04:54:42'),
(9, 'project_divisions_set', 'project', 3, '', NULL, 1, '2025-12-12 04:57:54', 'divisions=7,1,2,3,4,8', '2025-12-12 04:57:54'),
(10, 'area_create', 'area', 7, '', NULL, 1, '2025-12-12 04:58:02', 'project=3 name=COCINA', '2025-12-12 04:58:02'),
(11, 'area_create', 'area', 8, '', NULL, 1, '2025-12-12 04:58:12', 'project=3 name=FAMILY ROOM', '2025-12-12 04:58:12'),
(12, 'area_create', 'area', 9, '', NULL, 1, '2025-12-12 04:58:17', 'project=3 name=SALA', '2025-12-12 04:58:17'),
(13, 'area_create', 'area', 10, '', NULL, 1, '2025-12-12 04:58:26', 'project=3 name=BAÑO VISITAS', '2025-12-12 04:58:26'),
(14, 'area_create', 'area', 11, '', NULL, 1, '2025-12-12 04:58:37', 'project=3 name=CUARTO PPAL', '2025-12-12 04:58:37'),
(15, 'area_create', 'area', 12, '', NULL, 1, '2025-12-12 04:58:44', 'project=3 name=CUARTO SALVA', '2025-12-12 04:58:44'),
(16, 'area_create', 'area', 13, '', NULL, 1, '2025-12-12 04:58:50', 'project=3 name=CUARTO LUCIO', '2025-12-12 04:58:50'),
(17, 'area_create', 'area', 14, '', NULL, 1, '2025-12-12 04:59:00', 'project=3 name=BAÑO NIÑOS', '2025-12-12 04:59:00'),
(18, 'area_create', 'area', 15, '', NULL, 1, '2025-12-12 04:59:08', 'project=3 name=BAÑO PPAL', '2025-12-12 04:59:08'),
(19, 'area_divisions_set', 'area', 14, '', NULL, 1, '2025-12-12 04:59:29', 'divisions=2,3', '2025-12-12 04:59:29'),
(20, 'area_divisions_set', 'area', 15, '', NULL, 1, '2025-12-12 04:59:33', 'divisions=2,3', '2025-12-12 04:59:33'),
(21, 'area_divisions_set', 'area', 10, '', NULL, 1, '2025-12-12 04:59:37', 'divisions=2,3', '2025-12-12 04:59:37'),
(22, 'project_divisions_set', 'project', 3, '', NULL, 1, '2025-12-12 04:59:43', 'divisions=7,2,3,4,8', '2025-12-12 04:59:43'),
(23, 'project_divisions_set', 'project', 3, '', NULL, 1, '2025-12-12 04:59:53', 'divisions=2,3,4,8', '2025-12-12 04:59:53'),
(24, 'area_divisions_set', 'area', 7, '', NULL, 1, '2025-12-12 05:00:12', 'divisions=2,3,4,8', '2025-12-12 05:00:12'),
(25, 'area_divisions_set', 'area', 13, '', NULL, 1, '2025-12-12 05:00:19', 'divisions=2,3,4,8', '2025-12-12 05:00:19'),
(26, 'area_divisions_set', 'area', 11, '', NULL, 1, '2025-12-12 05:00:27', 'divisions=2,3,4', '2025-12-12 05:00:27'),
(27, 'area_divisions_set', 'area', 12, '', NULL, 1, '2025-12-12 05:00:36', 'divisions=2,3,4', '2025-12-12 05:00:36'),
(28, 'area_divisions_set', 'area', 8, '', NULL, 1, '2025-12-12 05:00:46', 'divisions=2,3,4', '2025-12-12 05:00:46'),
(29, 'area_divisions_set', 'area', 9, '', NULL, 1, '2025-12-12 05:00:58', 'divisions=2,3,4', '2025-12-12 05:00:58'),
(30, 'area_rename', 'area', 14, '', NULL, 1, '2025-12-12 14:03:42', 'name=BAÑO NIÑOS', '2025-12-12 14:03:42'),
(31, 'project_divisions_set', 'project', 4, '', NULL, 1, '2025-12-12 16:01:54', 'divisions=1,2,3,8', '2025-12-12 16:01:54'),
(32, 'area_create', 'area', 16, '', NULL, 1, '2025-12-13 01:30:26', 'project=4 name=cuarto', '2025-12-13 01:30:26'),
(33, 'area_move', 'area', 19, '', NULL, 1, '2025-12-13 22:43:40', 'Move parent_area_id to 20', '2025-12-13 22:43:40'),
(34, 'area_move', 'area', 20, '', NULL, 1, '2025-12-13 22:43:46', 'Move parent_area_id to NULL', '2025-12-13 22:43:46'),
(35, 'area_move', 'area', 17, '', NULL, 1, '2025-12-13 22:43:52', 'Move parent_area_id to 19', '2025-12-13 22:43:52'),
(36, 'area_move', 'area', 17, '', NULL, 1, '2025-12-13 22:44:04', 'Move parent_area_id to NULL', '2025-12-13 22:44:04'),
(37, 'area_move', 'area', 20, '', NULL, 1, '2025-12-13 22:44:09', 'Move parent_area_id to 17', '2025-12-13 22:44:09'),
(38, 'area_move', 'area', 19, '', NULL, 1, '2025-12-13 22:44:14', 'Move parent_area_id to 17', '2025-12-13 22:44:14'),
(39, 'area_move', 'area', 20, '', NULL, 1, '2025-12-13 22:44:21', 'Move parent_area_id to 18', '2025-12-13 22:44:21'),
(40, 'division_brands_update', 'division', 9, '', NULL, 1, '2025-12-13 23:54:50', 'brands map', '2025-12-13 23:54:50'),
(41, 'article_update', 'article', 1, '', NULL, 1, '2025-12-14 00:03:11', '09041', '2025-12-14 00:03:11'),
(42, 'article_delete', 'article', 1, '', NULL, 1, '2025-12-14 00:04:04', '', '2025-12-14 00:04:04'),
(43, 'division_brands_update', 'division', 12, '', NULL, 1, '2025-12-15 03:47:09', 'brands map', '2025-12-15 03:47:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(2, 3);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `brand_id` (`brand_id`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `idx_areas_parent` (`parent_area_id`),
  ADD KEY `fk_areas_default_series` (`default_series_id`);

--
-- Indices de la tabla `area_divisions`
--
ALTER TABLE `area_divisions`
  ADD PRIMARY KEY (`area_id`,`division_id`),
  ADD KEY `fk_ad_division` (`division_id`);

--
-- Indices de la tabla `area_division_counters`
--
ALTER TABLE `area_division_counters`
  ADD PRIMARY KEY (`area_id`,`division_id`),
  ADD KEY `fk_adc_div` (`division_id`);

--
-- Indices de la tabla `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `brand_id` (`brand_id`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `article_type_id` (`article_type_id`);

--
-- Indices de la tabla `article_divisions`
--
ALTER TABLE `article_divisions`
  ADD PRIMARY KEY (`article_id`,`division_id`),
  ADD KEY `fk_article_divisions_division` (`division_id`);

--
-- Indices de la tabla `article_types`
--
ALTER TABLE `article_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indices de la tabla `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_att_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_att_file_type` (`file_type_id`),
  ADD KEY `fk_att_user` (`uploaded_by`);

--
-- Indices de la tabla `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `brand_article_types`
--
ALTER TABLE `brand_article_types`
  ADD PRIMARY KEY (`brand_id`,`article_type_id`),
  ADD KEY `fk_bat_type` (`article_type_id`);

--
-- Indices de la tabla `divisions`
--
ALTER TABLE `divisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indices de la tabla `division_brands`
--
ALTER TABLE `division_brands`
  ADD PRIMARY KEY (`division_id`,`brand_id`),
  ADD KEY `fk_div_brand_brand` (`brand_id`);

--
-- Indices de la tabla `file_types`
--
ALTER TABLE `file_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indices de la tabla `homologations`
--
ALTER TABLE `homologations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pair` (`article_id_1`,`article_id_2`),
  ADD KEY `article_id_2` (`article_id_2`);

--
-- Indices de la tabla `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `uq_perm` (`module`,`action`);

--
-- Indices de la tabla `points`
--
ALTER TABLE `points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `brand_id` (`brand_id`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `support_article_id` (`support_article_id`),
  ADD KEY `box_article_id` (`box_article_id`),
  ADD KEY `plate_article_id` (`plate_article_id`),
  ADD KEY `cover_article_id` (`cover_article_id`),
  ADD KEY `fk_points_division` (`division_id`);

--
-- Indices de la tabla `point_components`
--
ALTER TABLE `point_components`
  ADD PRIMARY KEY (`id`),
  ADD KEY `point_id` (`point_id`),
  ADD KEY `article_id` (`article_id`);

--
-- Indices de la tabla `point_templates`
--
ALTER TABLE `point_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `brand_id` (`brand_id`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `support_article_id` (`support_article_id`),
  ADD KEY `plate_article_id` (`plate_article_id`),
  ADD KEY `cover_article_id` (`cover_article_id`);

--
-- Indices de la tabla `point_template_components`
--
ALTER TABLE `point_template_components`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `article_id` (`article_id`);

--
-- Indices de la tabla `point_types`
--
ALTER TABLE `point_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `system_type_code` (`system_type_code`);

--
-- Indices de la tabla `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_projects_default_brand` (`default_brand_id`),
  ADD KEY `idx_projects_default_series` (`default_series_id`),
  ADD KEY `idx_projects_closed` (`is_closed`);

--
-- Indices de la tabla `project_brands`
--
ALTER TABLE `project_brands`
  ADD PRIMARY KEY (`project_id`,`brand_id`),
  ADD KEY `brand_id` (`brand_id`);

--
-- Indices de la tabla `project_divisions`
--
ALTER TABLE `project_divisions`
  ADD PRIMARY KEY (`project_id`,`division_id`),
  ADD KEY `fk_pd_division` (`division_id`);

--
-- Indices de la tabla `project_series`
--
ALTER TABLE `project_series`
  ADD PRIMARY KEY (`project_id`,`series_id`),
  ADD KEY `series_id` (`series_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indices de la tabla `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_perm` (`permission_id`);

--
-- Indices de la tabla `series`
--
ALTER TABLE `series`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_series_brand_id` (`brand_id`),
  ADD KEY `idx_series_parent_series_id` (`parent_series_id`);

--
-- Indices de la tabla `system_types`
--
ALTER TABLE `system_types`
  ADD PRIMARY KEY (`code`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indices de la tabla `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_ur_role` (`role_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de la tabla `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `article_types`
--
ALTER TABLE `article_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `divisions`
--
ALTER TABLE `divisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `file_types`
--
ALTER TABLE `file_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `homologations`
--
ALTER TABLE `homologations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT de la tabla `points`
--
ALTER TABLE `points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `point_components`
--
ALTER TABLE `point_components`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `point_templates`
--
ALTER TABLE `point_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `point_template_components`
--
ALTER TABLE `point_template_components`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `point_types`
--
ALTER TABLE `point_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `series`
--
ALTER TABLE `series`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `areas_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `areas_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  ADD CONSTRAINT `areas_ibfk_3` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `fk_areas_default_series` FOREIGN KEY (`default_series_id`) REFERENCES `series` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_areas_parent` FOREIGN KEY (`parent_area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `area_divisions`
--
ALTER TABLE `area_divisions`
  ADD CONSTRAINT `fk_ad_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ad_division` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `area_division_counters`
--
ALTER TABLE `area_division_counters`
  ADD CONSTRAINT `fk_adc_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adc_div` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  ADD CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `articles_ibfk_3` FOREIGN KEY (`article_type_id`) REFERENCES `article_types` (`id`);

--
-- Filtros para la tabla `article_divisions`
--
ALTER TABLE `article_divisions`
  ADD CONSTRAINT `fk_article_divisions_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_article_divisions_division` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `fk_att_file_type` FOREIGN KEY (`file_type_id`) REFERENCES `file_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_att_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `brand_article_types`
--
ALTER TABLE `brand_article_types`
  ADD CONSTRAINT `fk_bat_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bat_type` FOREIGN KEY (`article_type_id`) REFERENCES `article_types` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `division_brands`
--
ALTER TABLE `division_brands`
  ADD CONSTRAINT `fk_div_brand_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_div_brand_div` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `homologations`
--
ALTER TABLE `homologations`
  ADD CONSTRAINT `homologations_ibfk_1` FOREIGN KEY (`article_id_1`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `homologations_ibfk_2` FOREIGN KEY (`article_id_2`) REFERENCES `articles` (`id`);

--
-- Filtros para la tabla `points`
--
ALTER TABLE `points`
  ADD CONSTRAINT `fk_points_division` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`),
  ADD CONSTRAINT `points_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `points_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  ADD CONSTRAINT `points_ibfk_3` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  ADD CONSTRAINT `points_ibfk_4` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `points_ibfk_5` FOREIGN KEY (`support_article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `points_ibfk_6` FOREIGN KEY (`box_article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `points_ibfk_7` FOREIGN KEY (`plate_article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `points_ibfk_8` FOREIGN KEY (`cover_article_id`) REFERENCES `articles` (`id`);

--
-- Filtros para la tabla `point_components`
--
ALTER TABLE `point_components`
  ADD CONSTRAINT `point_components_ibfk_1` FOREIGN KEY (`point_id`) REFERENCES `points` (`id`),
  ADD CONSTRAINT `point_components_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`);

--
-- Filtros para la tabla `point_templates`
--
ALTER TABLE `point_templates`
  ADD CONSTRAINT `point_templates_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  ADD CONSTRAINT `point_templates_ibfk_2` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `point_templates_ibfk_3` FOREIGN KEY (`support_article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `point_templates_ibfk_4` FOREIGN KEY (`plate_article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `point_templates_ibfk_5` FOREIGN KEY (`cover_article_id`) REFERENCES `articles` (`id`);

--
-- Filtros para la tabla `point_template_components`
--
ALTER TABLE `point_template_components`
  ADD CONSTRAINT `point_template_components_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `point_templates` (`id`),
  ADD CONSTRAINT `point_template_components_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`);

--
-- Filtros para la tabla `point_types`
--
ALTER TABLE `point_types`
  ADD CONSTRAINT `point_types_ibfk_1` FOREIGN KEY (`system_type_code`) REFERENCES `system_types` (`code`);

--
-- Filtros para la tabla `project_brands`
--
ALTER TABLE `project_brands`
  ADD CONSTRAINT `project_brands_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_brands_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`);

--
-- Filtros para la tabla `project_divisions`
--
ALTER TABLE `project_divisions`
  ADD CONSTRAINT `fk_pd_division` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pd_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `project_series`
--
ALTER TABLE `project_series`
  ADD CONSTRAINT `project_series_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_series_ibfk_2` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`);

--
-- Filtros para la tabla `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Filtros para la tabla `series`
--
ALTER TABLE `series`
  ADD CONSTRAINT `fk_series_parent` FOREIGN KEY (`parent_series_id`) REFERENCES `series` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `series_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`);

--
-- Filtros para la tabla `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `user_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
