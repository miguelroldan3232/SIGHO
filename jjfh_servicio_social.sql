-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 24-10-2026 a las 19:11:24
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
  `document_type` varchar(50) DEFAULT NULL,
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
-- Estructura de tabla para la tabla `access_request_email_verifications`
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hour_record_evidence`
--

CREATE TABLE `hour_record_evidence` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `record_id` bigint(20) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `evidence_path` varchar(500) NOT NULL,
  `evidence_mime` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hour_record_evidence_record` (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Estructura de tabla para la tabla `account_email_verifications`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.account_email_verifications: #1932 - Table &#039;jjfh_servicio_social.account_email_verifications&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.account_email_verifications: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`account_email_verifications`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hour_records`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.hour_records: #1932 - Table &#039;jjfh_servicio_social.hour_records&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.hour_records: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`hour_records`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hour_record_evidence`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.hour_record_evidence: #1932 - Table &#039;jjfh_servicio_social.hour_record_evidence&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.hour_record_evidence: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`hour_record_evidence`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_attempts`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.login_attempts: #1932 - Table &#039;jjfh_servicio_social.login_attempts&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.login_attempts: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`login_attempts`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permissions`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.permissions: #1932 - Table &#039;jjfh_servicio_social.permissions&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.permissions: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`permissions`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `review_history`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.review_history: #1932 - Table &#039;jjfh_servicio_social.review_history&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.review_history: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`review_history`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.roles: #1932 - Table &#039;jjfh_servicio_social.roles&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.roles: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`roles`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role_permissions`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.role_permissions: #1932 - Table &#039;jjfh_servicio_social.role_permissions&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.role_permissions: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`role_permissions`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sigho_role_permissions`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.sigho_role_permissions: #1932 - Table &#039;jjfh_servicio_social.sigho_role_permissions&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.sigho_role_permissions: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`sigho_role_permissions`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sigho_settings`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.sigho_settings: #1932 - Table &#039;jjfh_servicio_social.sigho_settings&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.sigho_settings: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`sigho_settings`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `staff`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.staff: #1932 - Table &#039;jjfh_servicio_social.staff&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.staff: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`staff`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `students`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.students: #1932 - Table &#039;jjfh_servicio_social.students&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.students: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`students`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `student_applications`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.student_applications: #1932 - Table &#039;jjfh_servicio_social.student_applications&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.student_applications: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`student_applications`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_settings`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.system_settings: #1932 - Table &#039;jjfh_servicio_social.system_settings&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.system_settings: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`system_settings`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--
-- Error leyendo la estructura de la tabla jjfh_servicio_social.users: #1932 - Table &#039;jjfh_servicio_social.users&#039; doesn&#039;t exist in engine
-- Error leyendo datos de la tabla jjfh_servicio_social.users: #1064 - Algo está equivocado en su sintax cerca &#039;FROM `jjfh_servicio_social`.`users`&#039; en la linea 1

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_review_history`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_review_history` (
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_student_progress`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_student_progress` (
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
-- Filtros para la tabla `hour_record_evidence`
--
ALTER TABLE `hour_record_evidence`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  ADD CONSTRAINT `fk_hour_record_evidence_record` FOREIGN KEY (`record_id`) REFERENCES `hour_records` (`id`) ON DELETE CASCADE;

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
