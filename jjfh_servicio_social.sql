-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-09-2026 a las 02:17:51
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `jjfh_servicio_social`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `access_requests`
--

CREATE TABLE `access_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `identification` varchar(50) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `requested_role_id` tinyint(3) UNSIGNED NOT NULL,
  `modality` enum('academico','tecnico') DEFAULT NULL,
  `technical_program` varchar(120) DEFAULT NULL,
  `grade` varchar(20) DEFAULT NULL,
  `service_site` varchar(150) DEFAULT NULL,
  `service_project` varchar(200) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `status` enum('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
  `validated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `validation_observation` varchar(500) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `validated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `access_requests`
--

INSERT INTO `access_requests` (`id`, `identification`, `first_name`, `last_name`, `email`, `password_hash`, `requested_role_id`, `modality`, `technical_program`, `grade`, `service_site`, `service_project`, `phone`, `status`, `validated_by`, `validation_observation`, `requested_at`, `validated_at`) VALUES
(1, '100000001', 'Juan', 'Prueba', 'juanprueba@gmail.com', '$2y$10$9yZE1imhu.4iDrzsnhgle.UwnSNDxlDC4Lu8VRxzNy63Gj6aiE4Z.', 1, 'academico', NULL, NULL, 'Central JT', NULL, NULL, 'aceptada', 3, NULL, '2026-09-03 00:31:00', '2026-09-03 01:21:37'),
(2, '200000001', 'Federico', 'Prueba', 'profesorprueba@iejosejoaquinflorezhernandez.edu.co', '$2y$10$zT2.6X6Gc7LZkgd70Oed5e8lhVzlC0Y78DYCuwbM7.ohfModxzALS', 2, NULL, NULL, NULL, NULL, NULL, NULL, 'rechazada', 3, NULL, '2026-09-03 00:32:41', '2026-09-03 01:21:08'),
(3, '300000001', 'Admin', 'Prueba', 'adminprueba@iejosejoaquinflorezhernandez.edu.co', '$2y$10$ty6AiC4CCsSdMdzjBJ5dEeX918WVvYq1.qk0YcgSLnzHue3TVBiTq', 3, NULL, NULL, NULL, NULL, NULL, NULL, 'rechazada', 4, NULL, '2026-09-03 00:35:25', '2026-09-03 00:35:55'),
(4, '300000001', 'Carlos', 'Martínez', 'carlosmartinez@iejosejoaquinflorezhernandez.edu.co', '$2y$10$l7Pc/Z0Ei0EDddRYZuqbB.3Jtx0L9XQu6TXVaN7csCYxPsNTGnni6', 2, NULL, NULL, NULL, NULL, NULL, NULL, 'aceptada', 3, NULL, '2026-09-03 01:24:50', '2026-09-03 01:24:58'),
(5, 'EST-0001', 'Miguel Angel', 'Roldan Devia', 'estudiante@gmail.com', '$2y$12$7MqYxuC1sudnt2TnODtlF.To1SHXFNoJS4/H2ScU4HCiroi6pLMBC', 1, 'tecnico', 'Técnica', '11', 'Central JM', NULL, NULL, 'aceptada', NULL, NULL, '2026-09-10 18:12:11', '2026-09-10 18:12:11'),
(6, '1234567890', 'Sans', 'Restrepo', 'sansrestrepo11@gmail.com', '$2y$10$ZmfXmSlIGIY9BpAM8AYvluDMhF1YO0.AC9OAStXKRXxEcWC/Yv0nS', 1, 'academico', NULL, '11°', NULL, NULL, NULL, 'rechazada', 3, NULL, '2026-09-14 22:21:56', '2026-09-14 22:25:06'),
(7, '1234567890', 'Sans', 'Restrepo', 'sansrestrepo10@gmail.com', '$2y$10$y5P0Iv8Ic1HLNDCn//VBoew0Is45i1oCjWZTFGz1YZYoiTVsNL6ba', 1, 'academico', NULL, '10°', NULL, NULL, NULL, 'aceptada', 3, NULL, '2026-09-14 23:30:51', '2026-09-14 23:32:10'),
(8, '1122334455', 'Maria Fernanda', 'Ramires', 'mariafernandaramiresciclov@gmail.com', '$2y$10$k7oyHTgvJvc57YpUm9Z2a.Avv2sXub3WgT0TJLTaoVpsPN9cQzJYu', 1, 'academico', NULL, 'Ciclo V', NULL, NULL, NULL, 'aceptada', 3, NULL, '2026-09-15 00:11:40', '2026-09-15 00:12:27'),
(9, '1298347650', 'Sara Sofia', 'Sanchez Ospina', 'sarasofiasanchezopsina116@gmail.com', '$2y$10$jykiL4V8igYkPZYkE4nTXuVUBVChKLyJxs2wJ1OukPDay363ptPyu', 1, 'tecnico', 'Técnica', '11°', NULL, NULL, NULL, 'aceptada', 3, NULL, '2026-09-15 00:24:48', '2026-09-15 00:25:23'),
(10, '100-000', 'David', 'Montero', 'davidmontero@iejosejoaquinflorezhernandez.edu.co', '$2y$10$qWBWM72e3xXljX5oHVrwaOr44Kfk6P6KvO0QqYeppgH8y2fGBDDY2', 2, NULL, NULL, NULL, NULL, 'Logística y Vigilancia', NULL, 'aceptada', 3, NULL, '2026-09-15 01:27:59', '2026-09-15 01:29:44'),
(11, '123-456', 'Felipe', 'Castro', 'felipecastro@iejosejoaquinflorezhernandez.edu.co', '$2y$10$VPNeYZIO/OfHPkAnQNuDrOy31dcdW8cS7RTixba/3IVLtL3JWEAcu', 2, NULL, NULL, NULL, NULL, 'Acompañamiento a un docente de transición o primaria', '3025648777', 'aceptada', 3, NULL, '2026-09-15 02:00:43', '2026-09-15 02:01:36'),
(12, '123-456', 'Felipe', 'Castro', 'felipecastro@iejosejoaquinflorezhernandez.edu.co', '$2y$10$VPNeYZIO/OfHPkAnQNuDrOy31dcdW8cS7RTixba/3IVLtL3JWEAcu', 3, NULL, NULL, NULL, NULL, 'Acompañamiento a un docente de transición o primaria', NULL, 'rechazada', 3, NULL, '2026-09-15 02:16:30', '2026-09-15 02:17:53'),
(13, '111-222', 'Juan', 'Padilla', 'padilla@iejosejoaquinflorezhernandez.edu.co', '$2y$10$N1HaTbl0oo8r2ehUnWqaJeJ.u2MjVpvynmZ49xIRuXUnZzSktKd.a', 2, NULL, NULL, NULL, NULL, 'Educación Física / Tiempo Libre', '3002222211', 'rechazada', 3, NULL, '2026-09-15 02:30:45', '2026-09-15 02:31:44'),
(14, '100-000', 'David', 'Montero', 'davidmontero@iejosejoaquinflorezhernandez.edu.co', '$2y$10$qWBWM72e3xXljX5oHVrwaOr44Kfk6P6KvO0QqYeppgH8y2fGBDDY2', 3, NULL, NULL, NULL, NULL, 'Logística y Vigilancia', NULL, 'rechazada', 3, NULL, '2026-09-15 02:31:10', '2026-09-15 02:31:37'),
(15, '22222-1', 'Pepe', 'Ramos', 'pepe@iejosejoaquinflorezhernandez.edu.co', '$2y$10$DQX2ry7BAMmayeJAV1tUN.1YxL2moyN.xbxrPrSwJF5LM0ymMcxFC', 2, NULL, NULL, NULL, NULL, 'Proyecto Ambiental', '3112000000', 'rechazada', 3, NULL, '2026-09-15 02:40:38', '2026-09-15 02:41:18'),
(16, 'DOC-0001', 'Profesor', 'de Prueba', 'profesor@iejosejoaquinflorezhernandez.edu.co', '$2y$12$KGMBiqzel2STUXgg8GC7FugPQmOc4z96aXHFD6JV1wIH1qhz64GX6', 3, NULL, NULL, NULL, NULL, 'Educación Física / Tiempo Libre', NULL, 'rechazada', 3, NULL, '2026-09-15 02:40:56', '2026-09-15 02:41:13'),
(17, '1606767676', 'Miguel Gustavo', 'Roldán Sánchez', 'jvhfgfvhjvhjvhvhvhv@gmail.com', '$2y$10$KJOpyQEAVr70qpcz.vt3hudT85OyXb/s9PDP/OS986J3Icvq60MF2', 1, 'academico', NULL, '11°', NULL, NULL, NULL, 'rechazada', 3, NULL, '2026-09-15 14:08:32', '2026-09-15 16:14:45'),
(18, '1030592266', 'Frederick Julian', 'Rozo Montero', 'julianmontero940@gmail.com', '$2y$10$3AAqxhKtMUDTOLopCkeu7.Ol1awusuQ64CWVbZ9LmudH.m/FBGBhS', 1, 'academico', NULL, '11°', NULL, NULL, NULL, 'pendiente', NULL, NULL, '2026-09-15 17:02:44', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hour_records`
--

CREATE TABLE `hour_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `entry_time` time NOT NULL,
  `exit_time` time NOT NULL,
  `hours` decimal(6,2) NOT NULL,
  `activity_description` text NOT NULL,
  `evidence_original_name` varchar(255) DEFAULT NULL,
  `evidence_path` varchar(500) DEFAULT NULL,
  `evidence_mime` varchar(100) DEFAULT NULL,
  `status` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `observation` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `hour_records`
--

INSERT INTO `hour_records` (`id`, `student_id`, `record_date`, `entry_time`, `exit_time`, `hours`, `activity_description`, `evidence_original_name`, `evidence_path`, `evidence_mime`, `status`, `observation`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(4, 1, '2026-09-10', '08:00:00', '10:00:00', 2.00, 'Archivos', 'escudo_jjfh.jpg', 'uploads/evidence/37fb2419ff31d1a7f235c247072ec4ad.jpg', 'image/jpeg', 'aprobada', 'Buen trabajo', NULL, 3, '2026-09-10 18:16:47', '2026-09-10 18:13:39', '2026-09-10 18:16:47'),
(5, 5, '2026-09-10', '13:00:00', '17:00:00', 4.00, 'Edu. Fisica', 'escudo_jjfh.jpg', 'uploads/evidence/1780b638d6f7856070791a8e4c269dd1.jpg', 'image/jpeg', 'rechazada', 'No vino ese dia', 'No vino ese dia', 1, '2026-09-10 18:19:39', '2026-09-10 18:18:13', '2026-09-10 18:19:39'),
(6, 1, '2026-09-14', '06:00:00', '08:00:00', 2.00, 'archivos', 'images.jpg', 'uploads/evidence/f7e8f9cb0563ba7048780b40c8a3988a.jpg', 'image/jpeg', 'aprobada', 'Si vino al servicio', NULL, 3, '2026-09-15 17:21:49', '2026-09-14 13:46:12', '2026-09-15 17:21:49'),
(7, 6, '2026-09-15', '14:00:00', '18:00:00', 4.00, 'Acompañamiento docente', 'images.jpg', 'uploads/evidence/a1c6c4fd64c8037ca72166f4f69ef347.jpg', 'image/jpeg', 'aprobada', 'Si vino', NULL, 5, '2026-09-15 02:02:37', '2026-09-15 00:44:25', '2026-09-15 02:02:37'),
(8, 1, '2026-09-10', '14:00:00', '17:00:00', 3.00, 'archivos', 'sigho.png', 'uploads/evidence/6c03b57f1730dc1b06bac5e00ae8281f.png', 'image/png', 'aprobada', '', NULL, 3, '2026-09-15 17:22:21', '2026-09-15 01:11:40', '2026-09-15 17:22:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `review_history`
--

CREATE TABLE `review_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hour_record_id` bigint(20) UNSIGNED NOT NULL,
  `reviewer_staff_id` bigint(20) UNSIGNED NOT NULL,
  `previous_status` enum('pendiente','aprobada','rechazada') DEFAULT NULL,
  `new_status` enum('pendiente','aprobada','rechazada') NOT NULL,
  `observation` text DEFAULT NULL,
  `reviewed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `review_history`
--

INSERT INTO `review_history` (`id`, `hour_record_id`, `reviewer_staff_id`, `previous_status`, `new_status`, `observation`, `reviewed_at`) VALUES
(7, 4, 3, 'pendiente', 'aprobada', 'Buen trabajo', '2026-09-10 18:16:47'),
(8, 5, 1, 'pendiente', 'rechazada', 'No vino ese dia', '2026-09-10 18:19:39'),
(9, 7, 5, 'pendiente', 'aprobada', 'Si vino', '2026-09-15 02:02:37'),
(10, 6, 3, 'pendiente', 'aprobada', 'Si vino al servicio', '2026-09-15 17:21:49'),
(11, 8, 3, 'pendiente', 'aprobada', NULL, '2026-09-15 17:22:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `code`, `name`, `description`) VALUES
(1, 'estudiante', 'Estudiante', 'Registra sus horas de servicio social'),
(2, 'profesor', 'Profesor', 'Revisa registros de servicio social'),
(3, 'administrador', 'Administrador', 'Docente con permisos administrativos'),
(4, 'superadmin', 'Super Administrador', 'Control general del sistema');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `staff`
--

CREATE TABLE `staff` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `staff_code` varchar(50) NOT NULL,
  `position` varchar(80) NOT NULL DEFAULT 'Docente',
  `service_site` varchar(150) DEFAULT NULL,
  `service_project` varchar(200) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `staff_code`, `position`, `service_site`, `service_project`, `phone`, `created_at`) VALUES
(1, 2, 'DOC-0001', 'Docente', 'Central JT', 'Educación Física / Tiempo Libre', NULL, '2026-09-03 00:22:46'),
(2, 3, 'ADM-0001', 'Docente Administrador', NULL, NULL, NULL, '2026-09-03 00:22:46'),
(3, 9, '300000001', 'Docente', 'Central JM', 'Secretaría y/o Archivo', NULL, '2026-09-03 01:24:58'),
(4, 13, '100-000', 'Docente', 'Central JM', 'Logística y Vigilancia', NULL, '2026-09-15 01:29:44'),
(5, 14, '123-456', 'Docente', 'Central JT', 'Acompañamiento a un docente de transición o primaria', '3025648744', '2026-09-15 02:01:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `students`
--

CREATE TABLE `students` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `grade` varchar(20) DEFAULT NULL,
  `modality` enum('academico','tecnico') NOT NULL,
  `technical_program` varchar(120) DEFAULT NULL,
  `target_hours` decimal(6,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_code`, `grade`, `modality`, `technical_program`, `target_hours`, `created_at`) VALUES
(1, 1, 'EST-0001', '11', 'tecnico', 'Técnica', 100.00, '2026-09-03 00:22:46'),
(5, 8, '100000001', NULL, 'academico', NULL, 120.00, '2026-09-03 01:21:37'),
(6, 10, '1234567890', '10°', 'academico', NULL, 120.00, '2026-09-14 23:32:10'),
(7, 11, '1122334455', 'Ciclo V', 'academico', NULL, 120.00, '2026-09-15 00:12:27'),
(8, 12, '1298347650', '11°', 'tecnico', 'Técnica', 100.00, '2026-09-15 00:25:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `student_applications`
--

CREATE TABLE `student_applications` (
  `id` int(11) NOT NULL,
  `access_request_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `service_year` smallint(6) NOT NULL,
  `first_surname` varchar(100) NOT NULL,
  `second_surname` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `second_name` varchar(100) DEFAULT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_number` varchar(50) NOT NULL,
  `grade` varchar(20) NOT NULL,
  `birth_date` date NOT NULL,
  `residence_address` text NOT NULL,
  `phone` varchar(30) NOT NULL,
  `eps` varchar(150) NOT NULL,
  `guardian_name` varchar(200) NOT NULL,
  `guardian_phone` varchar(30) NOT NULL,
  `service_site` varchar(150) NOT NULL,
  `project` varchar(200) NOT NULL,
  `project_other` varchar(255) DEFAULT NULL,
  `service_days` text NOT NULL,
  `service_shift` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `student_applications`
--

INSERT INTO `student_applications` (`id`, `access_request_id`, `user_id`, `student_id`, `service_year`, `first_surname`, `second_surname`, `first_name`, `second_name`, `document_type`, `document_number`, `grade`, `birth_date`, `residence_address`, `phone`, `eps`, `guardian_name`, `guardian_phone`, `service_site`, `project`, `project_other`, `service_days`, `service_shift`, `created_at`, `updated_at`) VALUES
(1, 5, 1, 1, 2026, 'Roldan', 'Devia', 'Miguel Angel', NULL, 'CC', 'EST-0001', '11', '2008-01-01', 'Dato de prueba', '3000000000', 'Dato de prueba', 'Acudien de prueba', '3000000000', 'Central JM', 'Secretaría y/o Archivo', NULL, '[\"Lunes\",\"Martes\",\"Miércoles\",\"Jueves\",\"Viernes\"]', 'Mañana', '2026-09-10 23:12:11', '2026-09-14 12:20:06'),
(2, 1, 8, 5, 2026, 'Prueba', NULL, 'Juan', NULL, 'CC', '100000001', '11', '2008-01-01', 'Dato de prueba', '3000000010', 'Capresoca', 'Acudiente De Prueba', '3000000000', 'Central JT', 'Educación Física / Tiempo Libre', NULL, '[\"Lunes\",\"Martes\",\"Miércoles\",\"Jueves\",\"Viernes\"]', 'Mañana', '2026-09-10 23:12:11', '2026-09-15 02:57:15'),
(3, 6, NULL, NULL, 2026, 'Restrepo', NULL, 'Sans', NULL, 'Tarjeta de identidad', '1234567890', '11°', '2010-07-06', 'Calle 20 #14-2 Av. Guabinal', '0000200200', 'EPM – Unidad de Salud', 'Fernando Restrepo', '0000000000', 'Central JT', 'Acompañamiento a un docente de transición o primaria', NULL, '[\"Lunes\",\"Martes\",\"Miércoles\",\"Jueves\",\"Viernes\"]', 'JM', '2026-09-15 03:21:56', '2026-09-15 03:21:56'),
(4, 7, 10, 6, 2026, 'Restrepo', NULL, 'Sans', NULL, 'Tarjeta de identidad', '1234567890', '10°', '2010-03-14', 'Calle 15 #5-10', '3214567890', 'Pijaos Salud EPSI', 'Fernando Restrepo', '3201541694', 'Central JT', 'Acompañamiento a un docente de transición o primaria', NULL, '[\"Lunes\",\"Martes\",\"Miércoles\",\"Jueves\",\"Viernes\"]', 'JM', '2026-09-15 04:30:51', '2026-09-15 04:32:10'),
(5, 8, 11, 7, 2026, 'Ramires', NULL, 'Maria', 'Fernanda', 'Cédula de ciudadanía', '1122334455', 'Ciclo V', '2005-02-22', 'SPMZ 10 MZ 1 Casa 9', '3974522220', 'EPS Sanitas', 'Laura Valentina Ramires', '3203214000', 'Central JM', 'Logística y Vigilancia', NULL, '[\"Lunes\",\"Martes\",\"Miércoles\",\"Jueves\",\"Viernes\"]', 'JN', '2026-09-15 05:11:40', '2026-09-15 05:20:33'),
(6, 9, 12, 8, 2026, 'Sanchez', 'Ospina', 'Sara', 'Sofia', 'Tarjeta de identidad', '1298347650', '11°', '2010-01-11', 'Calle 5 #2-8', '3210455564', 'Salud Total EPS', 'Luz Myriam Ospina', '3241100057', 'Central JM', 'Otro', 'Emisora', '[\"Lunes\",\"Martes\",\"Miércoles\",\"Jueves\",\"Viernes\"]', 'JM', '2026-09-15 05:24:48', '2026-09-15 05:26:01'),
(7, 17, NULL, NULL, 2026, 'Roldán', 'Sánchez', 'Miguel', 'Gustavo', 'Tarjeta de identidad', '1606767676', '11°', '2010-09-15', 'callle 29 manzana w casa 200', '3232312132', 'Salud Total EPS', 'Andrea Lorena', '1221334242', 'Picaleña', 'Logística y Vigilancia', NULL, '[\"Lunes\",\"Martes\",\"Miércoles\",\"Jueves\",\"Viernes\",\"Sábado\"]', 'JM', '2026-09-15 19:08:32', '2026-09-15 19:08:32'),
(8, 18, NULL, NULL, 2026, 'Rozo', 'Montero', 'Frederick', 'Julian', 'Tarjeta de identidad', '1030592266', '11°', '2009-05-21', 'Manzana 1 casa 2', '3238816121', 'Salud Total EPS', 'Bea', '3197012840', 'Central JT', 'Proyecto Ambiental', NULL, '[\"Lunes\",\"Martes\",\"Miércoles\",\"Jueves\",\"Viernes\",\"Sábado\"]', 'JM', '2026-09-15 22:02:44', '2026-09-15 22:02:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('dominio_institucional', '@iejosejoaquinflorezhernandez.edu.co', 'Dominio para docentes y administradores'),
('meta_horas_academico', '120', 'Meta de estudiantes académicos'),
('meta_horas_tecnico', '100', 'Meta de estudiantes de la técnica'),
('tamano_max_evidencia_mb', '5', 'Tamaño máximo recomendado de evidencia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `identification` varchar(50) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `status` enum('pendiente','activo','rechazado','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `identification`, `first_name`, `last_name`, `email`, `password_hash`, `role_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'EST-0001', 'Miguel Angel', 'Roldan Devia', 'estudiante@gmail.com', '$2y$12$7MqYxuC1sudnt2TnODtlF.To1SHXFNoJS4/H2ScU4HCiroi6pLMBC', 1, 'activo', '2026-09-03 00:22:46', '2026-09-03 00:22:46'),
(2, 'DOC-0001', 'Profesor', 'de Prueba', 'profesor@iejosejoaquinflorezhernandez.edu.co', '$2y$12$KGMBiqzel2STUXgg8GC7FugPQmOc4z96aXHFD6JV1wIH1qhz64GX6', 2, 'activo', '2026-09-03 00:22:46', '2026-09-08 17:24:49'),
(3, 'ADM-0001', 'Administrador', 'de Prueba', 'admin@iejosejoaquinflorezhernandez.edu.co', '$2y$12$Q0u1kknZ0R97zFoxdnh6z.m2y5C69bHhwz678AjK86bVXt8KS9d3a', 3, 'activo', '2026-09-03 00:22:46', '2026-09-08 17:24:49'),
(4, 'SADM-0001', 'Super', 'Administrador', 'admintotaljjfh@gmail.com', '$2y$12$0XUqSV8zpAIm6XAWOmpU1O9V.mHHk0lDKoCAwGmWB/kbc34VJjHLu', 4, 'activo', '2026-09-03 00:22:46', '2026-09-03 00:22:46'),
(8, '100000001', 'Juan', 'Prueba', 'juanprueba@gmail.com', '$2y$10$9yZE1imhu.4iDrzsnhgle.UwnSNDxlDC4Lu8VRxzNy63Gj6aiE4Z.', 1, 'activo', '2026-09-03 01:21:37', '2026-09-03 01:21:37'),
(9, '300000001', 'Carlos', 'Martínez', 'carlosmartinez@iejosejoaquinflorezhernandez.edu.co', '$2y$10$l7Pc/Z0Ei0EDddRYZuqbB.3Jtx0L9XQu6TXVaN7csCYxPsNTGnni6', 2, 'activo', '2026-09-03 01:24:58', '2026-09-08 17:24:49'),
(10, '1234567890', 'Sans', 'Restrepo', 'sansrestrepo101@gmail.com', '$2y$10$y5P0Iv8Ic1HLNDCn//VBoew0Is45i1oCjWZTFGz1YZYoiTVsNL6ba', 1, 'activo', '2026-09-14 23:32:10', '2026-09-15 00:29:52'),
(11, '1122334455', 'Maria Fernanda', 'Ramires', 'mariafernandaramiresciclov@gmail.com', '$2y$10$k7oyHTgvJvc57YpUm9Z2a.Avv2sXub3WgT0TJLTaoVpsPN9cQzJYu', 1, 'activo', '2026-09-15 00:12:27', '2026-09-15 00:12:27'),
(12, '1298347650', 'Sara Sofia', 'Sanchez Ospina', 'sarasofiasanchezopsina116@gmail.com', '$2y$10$jykiL4V8igYkPZYkE4nTXuVUBVChKLyJxs2wJ1OukPDay363ptPyu', 1, 'activo', '2026-09-15 00:25:23', '2026-09-15 00:25:23'),
(13, '100-000', 'David', 'Montero', 'davidmontero@iejosejoaquinflorezhernandez.edu.co', '$2y$10$qWBWM72e3xXljX5oHVrwaOr44Kfk6P6KvO0QqYeppgH8y2fGBDDY2', 2, 'activo', '2026-09-15 01:29:44', '2026-09-15 01:29:44'),
(14, '123-456', 'Felipe', 'Castro', 'felipecastro@iejosejoaquinflorezhernandez.edu.co', '$2y$10$VPNeYZIO/OfHPkAnQNuDrOy31dcdW8cS7RTixba/3IVLtL3JWEAcu', 2, 'activo', '2026-09-15 02:01:36', '2026-09-15 02:01:36');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_review_history`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_review_history` (
`hour_record_id` bigint(20) unsigned
,`student_name` varchar(181)
,`student_email` varchar(150)
,`reviewer_name` varchar(181)
,`reviewer_email` varchar(150)
,`staff_code` varchar(50)
,`record_date` date
,`entry_time` time
,`exit_time` time
,`hours` decimal(6,2)
,`activity_description` text
,`evidence_original_name` varchar(255)
,`evidence_path` varchar(500)
,`evidence_mime` varchar(100)
,`status` enum('pendiente','aprobada','rechazada')
,`observation` text
,`rejection_reason` text
,`reviewed_at` datetime
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_student_progress`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_student_progress` (
`student_id` bigint(20) unsigned
,`identification` varchar(50)
,`student_code` varchar(50)
,`student_name` varchar(181)
,`email` varchar(150)
,`grade` varchar(20)
,`modality` enum('academico','tecnico')
,`technical_program` varchar(120)
,`target_hours` decimal(6,2)
,`approved_hours` decimal(28,2)
,`remaining_hours` decimal(29,2)
,`progress_percent` decimal(37,6)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_review_history`
--
DROP TABLE IF EXISTS `v_review_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_review_history`  AS SELECT `hr`.`id` AS `hour_record_id`, concat(`su`.`first_name`,' ',`su`.`last_name`) AS `student_name`, `su`.`email` AS `student_email`, concat(`pu`.`first_name`,' ',`pu`.`last_name`) AS `reviewer_name`, `pu`.`email` AS `reviewer_email`, `st`.`staff_code` AS `staff_code`, `hr`.`record_date` AS `record_date`, `hr`.`entry_time` AS `entry_time`, `hr`.`exit_time` AS `exit_time`, `hr`.`hours` AS `hours`, `hr`.`activity_description` AS `activity_description`, `hr`.`evidence_original_name` AS `evidence_original_name`, `hr`.`evidence_path` AS `evidence_path`, `hr`.`evidence_mime` AS `evidence_mime`, `hr`.`status` AS `status`, `hr`.`observation` AS `observation`, `hr`.`rejection_reason` AS `rejection_reason`, `hr`.`reviewed_at` AS `reviewed_at` FROM ((((`hour_records` `hr` join `students` `s` on(`s`.`id` = `hr`.`student_id`)) join `users` `su` on(`su`.`id` = `s`.`user_id`)) left join `staff` `st` on(`st`.`id` = `hr`.`reviewed_by`)) left join `users` `pu` on(`pu`.`id` = `st`.`user_id`)) WHERE `hr`.`status` <> 'pendiente' ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_student_progress`
--
DROP TABLE IF EXISTS `v_student_progress`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_progress`  AS SELECT `s`.`id` AS `student_id`, `u`.`identification` AS `identification`, `s`.`student_code` AS `student_code`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `student_name`, `u`.`email` AS `email`, `s`.`grade` AS `grade`, `s`.`modality` AS `modality`, `s`.`technical_program` AS `technical_program`, `s`.`target_hours` AS `target_hours`, coalesce(sum(case when `hr`.`status` = 'aprobada' then `hr`.`hours` else 0 end),0) AS `approved_hours`, greatest(`s`.`target_hours` - coalesce(sum(case when `hr`.`status` = 'aprobada' then `hr`.`hours` else 0 end),0),0) AS `remaining_hours`, least(100,coalesce(sum(case when `hr`.`status` = 'aprobada' then `hr`.`hours` else 0 end),0) / `s`.`target_hours` * 100) AS `progress_percent` FROM ((`students` `s` join `users` `u` on(`u`.`id` = `s`.`user_id`)) left join `hour_records` `hr` on(`hr`.`student_id` = `s`.`id`)) GROUP BY `s`.`id`, `u`.`identification`, `s`.`student_code`, `u`.`first_name`, `u`.`last_name`, `u`.`email`, `s`.`grade`, `s`.`modality`, `s`.`technical_program`, `s`.`target_hours` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `access_requests`
--
ALTER TABLE `access_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_requests_role` (`requested_role_id`),
  ADD KEY `fk_requests_validator` (`validated_by`),
  ADD KEY `idx_requests_status_role` (`status`,`requested_role_id`),
  ADD KEY `idx_requests_email` (`email`);

--
-- Indices de la tabla `hour_records`
--
ALTER TABLE `hour_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hours_student_status` (`student_id`,`status`),
  ADD KEY `idx_hours_reviewer_status` (`reviewed_by`,`status`),
  ADD KEY `idx_hours_date` (`record_date`);

--
-- Indices de la tabla `review_history`
--
ALTER TABLE `review_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_review_record` (`hour_record_id`),
  ADD KEY `idx_review_staff_date` (`reviewer_staff_id`,`reviewed_at`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indices de la tabla `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `staff_code` (`staff_code`);

--
-- Indices de la tabla `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_code` (`student_code`),
  ADD KEY `idx_students_modality` (`modality`);

--
-- Indices de la tabla `student_applications`
--
ALTER TABLE `student_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_app_access_request` (`access_request_id`),
  ADD KEY `idx_student_app_user` (`user_id`),
  ADD KEY `idx_student_app_student` (`student_id`),
  ADD KEY `idx_student_app_year_document` (`service_year`,`document_number`);

--
-- Indices de la tabla `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `identification` (`identification`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role_status` (`role_id`,`status`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `access_requests`
--
ALTER TABLE `access_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `hour_records`
--
ALTER TABLE `hour_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `review_history`
--
ALTER TABLE `review_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `staff`
--
ALTER TABLE `staff`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `student_applications`
--
ALTER TABLE `student_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `access_requests`
--
ALTER TABLE `access_requests`
  ADD CONSTRAINT `fk_requests_role` FOREIGN KEY (`requested_role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_requests_validator` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `hour_records`
--
ALTER TABLE `hour_records`
  ADD CONSTRAINT `fk_hours_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hours_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `review_history`
--
ALTER TABLE `review_history`
  ADD CONSTRAINT `fk_review_record` FOREIGN KEY (`hour_record_id`) REFERENCES `hour_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_staff` FOREIGN KEY (`reviewer_staff_id`) REFERENCES `staff` (`id`);

--
-- Filtros para la tabla `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
