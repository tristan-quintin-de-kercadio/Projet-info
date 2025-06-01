-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 01 juin 2025 à 16:05
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `sportify_db`
--

DELIMITER $$
--
-- Procédures
--
DROP PROCEDURE IF EXISTS `AnnulerReservation`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `AnnulerReservation` (IN `p_creneau_id` INT)   BEGIN
    UPDATE creneaux 
    SET statut = 'libre',
        etudiant_nom = NULL,
        etudiant_email = NULL,
        notes = NULL,
        updated_at = NOW()
    WHERE id = p_creneau_id AND statut = 'reserve';
    
    SELECT 'SUCCESS' as result, 'Réservation annulée' as message;
END$$

DROP PROCEDURE IF EXISTS `CreerCoach`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `CreerCoach` (IN `p_nom` VARCHAR(100), IN `p_prenom` VARCHAR(100), IN `p_email` VARCHAR(100), IN `p_mot_de_passe` VARCHAR(255), IN `p_specialite` VARCHAR(100), IN `p_description` TEXT, IN `p_bureau` VARCHAR(100), IN `p_telephone` VARCHAR(20), IN `p_admin_id` INT)   BEGIN
    DECLARE v_utilisateur_id INT;
    DECLARE v_coach_id INT;
    
    -- Créer l'utilisateur
    INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, type_compte, telephone)
    VALUES (p_nom, p_prenom, p_email, p_mot_de_passe, 'coach', p_telephone);
    
    SET v_utilisateur_id = LAST_INSERT_ID();
    
    -- Créer le coach
    INSERT INTO coachs (utilisateur_id, nom, prenom, specialite, description, bureau, telephone, email, photo)
    VALUES (v_utilisateur_id, p_nom, p_prenom, p_specialite, p_description, p_bureau, p_telephone, p_email, '/images_projet/default_coach.jpg');
    
    SET v_coach_id = LAST_INSERT_ID();
    
    -- Log de l'action
    INSERT INTO admin_logs (admin_id, action, table_affectee, enregistrement_id, details)
    VALUES (p_admin_id, 'CREATION_COACH', 'coachs', v_coach_id, CONCAT('Nouveau coach créé: ', p_prenom, ' ', p_nom));
    
    SELECT 'SUCCESS' as result, 'Coach créé avec succès' as message, v_coach_id as coach_id;
END$$

DROP PROCEDURE IF EXISTS `GetStatistiquesPaiements`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `GetStatistiquesPaiements` (IN `p_date_debut` DATE, IN `p_date_fin` DATE)   BEGIN
    SELECT 
        DATE(date_paiement) as date_paiement,
        COUNT(*) as nombre_transactions,
        SUM(montant) as total_ht,
        SUM(montant_total) as total_ttc,
        AVG(montant_total) as panier_moyen,
        COUNT(CASE WHEN statut_paiement = 'approuve' THEN 1 END) as paiements_approuves,
        COUNT(CASE WHEN statut_paiement = 'refuse' THEN 1 END) as paiements_refuses
    FROM paiements
    WHERE DATE(date_paiement) BETWEEN p_date_debut AND p_date_fin
    GROUP BY DATE(date_paiement)
    ORDER BY date_paiement DESC;
END$$

DROP PROCEDURE IF EXISTS `RembourserPaiement`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `RembourserPaiement` (IN `p_paiement_id` INT, IN `p_motif` VARCHAR(255), IN `p_admin_id` INT)   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Vérifier que le paiement existe et est approuvé
    IF NOT EXISTS (SELECT 1 FROM paiements WHERE id = p_paiement_id AND statut_paiement = 'approuve') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Paiement non trouvé ou déjà remboursé';
    END IF;
    
    -- Mettre à jour le statut
    UPDATE paiements 
    SET statut_paiement = 'rembourse',
        notes = CONCAT(COALESCE(notes, ''), '\nRemboursement: ', p_motif)
    WHERE id = p_paiement_id;
    
    -- Ajouter l'entrée dans l'historique avec l'admin qui a fait le remboursement
    INSERT INTO historique_paiements (paiement_id, ancien_statut, nouveau_statut, motif, utilisateur_modificateur_id)
    VALUES (p_paiement_id, 'approuve', 'rembourse', p_motif, p_admin_id);
    
    COMMIT;
    
    SELECT 'SUCCESS' as result, 'Paiement remboursé avec succès' as message;
END$$

DROP PROCEDURE IF EXISTS `ReserverCreneau`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `ReserverCreneau` (IN `p_creneau_id` INT, IN `p_etudiant_nom` VARCHAR(100), IN `p_etudiant_email` VARCHAR(100), IN `p_notes` TEXT)   BEGIN
    DECLARE v_count INT DEFAULT 0;
    
    -- Vérifier si le créneau est libre
    SELECT COUNT(*) INTO v_count 
    FROM creneaux 
    WHERE id = p_creneau_id AND statut = 'libre';
    
    IF v_count > 0 THEN
        -- Réserver le créneau
        UPDATE creneaux 
        SET statut = 'reserve',
            etudiant_nom = p_etudiant_nom,
            etudiant_email = p_etudiant_email,
            notes = p_notes,
            updated_at = NOW()
        WHERE id = p_creneau_id;
        
        SELECT 'SUCCESS' as result, 'Créneau réservé avec succès' as message;
    ELSE
        SELECT 'ERROR' as result, 'Créneau non disponible' as message;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `SupprimerCoach`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `SupprimerCoach` (IN `p_coach_id` INT, IN `p_admin_id` INT)   BEGIN
    DECLARE v_utilisateur_id INT;
    DECLARE v_coach_nom VARCHAR(200);
    
    -- Récupérer les infos du coach
    SELECT utilisateur_id, CONCAT(prenom, ' ', nom) INTO v_utilisateur_id, v_coach_nom
    FROM coachs WHERE id = p_coach_id;
    
    -- Désactiver le coach et l'utilisateur
    UPDATE coachs SET actif = FALSE WHERE id = p_coach_id;
    UPDATE utilisateurs SET statut = 'inactif' WHERE id = v_utilisateur_id;
    
    -- Annuler tous les créneaux futurs
    UPDATE creneaux SET statut = 'indisponible', motif = 'Coach supprimé' 
    WHERE coach_id = p_coach_id AND date_creneau >= CURDATE();
    
    -- Log de l'action
    INSERT INTO admin_logs (admin_id, action, table_affectee, enregistrement_id, details)
    VALUES (p_admin_id, 'SUPPRESSION_COACH', 'coachs', p_coach_id, CONCAT('Coach supprimé: ', v_coach_nom));
    
    SELECT 'SUCCESS' as result, 'Coach supprimé avec succès' as message;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `activites_sportives`
--

DROP TABLE IF EXISTS `activites_sportives`;
CREATE TABLE IF NOT EXISTS `activites_sportives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#2563eb',
  `coach_principal_id` int DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `coach_principal_id` (`coach_principal_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `activites_sportives`
--

INSERT INTO `activites_sportives` (`id`, `nom`, `description`, `icone`, `couleur`, `coach_principal_id`, `actif`, `created_at`) VALUES
(1, 'Musculation', 'Renforcez votre corps avec nos équipements modernes', '💪', '#2563eb', 1, 1, '2025-05-27 16:08:01'),
(2, 'Fitness', 'Améliorez votre condition physique générale', '❤️', '#dc2626', 8, 1, '2025-05-27 16:08:01'),
(3, 'Cardio-Training', 'Boostez votre endurance cardiovasculaire', '🏃', '#2563eb', 9, 1, '2025-05-27 16:08:01'),
(4, 'Cours Collectifs', 'Motivez-vous en groupe avec nos cours variés', '👥', '#2563eb', 10, 1, '2025-05-27 16:08:01'),
(5, 'Basketball', 'Excellez dans votre discipline avec nos coachs spécialisés', '🏀', '#f59e0b', 2, 1, '2025-05-27 16:08:01'),
(6, 'Football', 'Perfectionnez votre technique et tactique', '⚽', '#10b981', 3, 1, '2025-05-27 16:08:01'),
(7, 'Rugby', 'Développez force et esprit d\'équipe', '🏉', '#dc2626', 4, 1, '2025-05-27 16:08:01'),
(8, 'Tennis', 'Améliorez votre jeu et technique', '🎾', '#8b5cf6', 5, 1, '2025-05-27 16:08:01'),
(9, 'Natation', 'Maîtrisez tous les styles de nage', '🏊', '#06b6d4', 6, 1, '2025-05-27 16:08:01'),
(10, 'Plongée', 'Découvrez les profondeurs aquatiques', '🤿', '#3b82f6', 7, 1, '2025-05-27 16:08:01');

-- --------------------------------------------------------

--
-- Structure de la table `admin_logs`
--

DROP TABLE IF EXISTS `admin_logs`;
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_affectee` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enregistrement_id` int DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_action` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_date` (`date_action`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `table_affectee`, `enregistrement_id`, `details`, `ip_address`, `date_action`) VALUES
(1, 1, 'CREATION_COACH', 'coachs', 11, 'Nouveau coach créé: edis yesiltas ', NULL, '2025-06-01 14:29:13'),
(2, 1, 'SUPPRESSION_COACH', 'coachs', 11, 'Coach supprimé: edis yesiltas ', NULL, '2025-06-01 14:29:40');

-- --------------------------------------------------------

--
-- Structure de la table `adresses_facturation`
--

DROP TABLE IF EXISTS `adresses_facturation`;
CREATE TABLE IF NOT EXISTS `adresses_facturation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `adresse_ligne1` varchar(255) NOT NULL,
  `adresse_ligne2` varchar(255) DEFAULT NULL,
  `ville` varchar(100) NOT NULL,
  `code_postal` varchar(10) NOT NULL,
  `pays` varchar(100) NOT NULL DEFAULT 'France',
  `telephone` varchar(20) NOT NULL,
  `carte_etudiant` varchar(50) NOT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_utilisateur` (`utilisateur_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Adresses de facturation des clients pour les paiements';

-- --------------------------------------------------------

--
-- Structure de la table `cartes_test`
--

DROP TABLE IF EXISTS `cartes_test`;
CREATE TABLE IF NOT EXISTS `cartes_test` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_carte` varchar(20) NOT NULL,
  `nom_carte` varchar(100) NOT NULL,
  `date_expiration` varchar(5) NOT NULL,
  `code_securite` varchar(4) NOT NULL,
  `type_carte` enum('Visa','MasterCard','American Express','PayPal') NOT NULL,
  `solde_disponible` decimal(10,2) DEFAULT '1000.00',
  `actif` tinyint(1) DEFAULT '1',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_carte` (`numero_carte`,`nom_carte`),
  KEY `idx_type` (`type_carte`),
  KEY `idx_actif` (`actif`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cartes de test pour simulation paiement (À SUPPRIMER EN PRODUCTION)';

--
-- Déchargement des données de la table `cartes_test`
--

INSERT INTO `cartes_test` (`id`, `numero_carte`, `nom_carte`, `date_expiration`, `code_securite`, `type_carte`, `solde_disponible`, `actif`, `date_creation`) VALUES
(1, '4532015112830366', 'JEAN MARTIN', '12/26', '123', 'Visa', 2500.00, 1, '2025-05-29 23:06:41'),
(2, '4532015112830367', 'MARIE DUPONT', '06/27', '456', 'Visa', 1800.00, 1, '2025-05-29 23:06:41'),
(3, '5425233430109903', 'PIERRE BERNARD', '09/25', '789', 'MasterCard', 3200.00, 1, '2025-05-29 23:06:41'),
(4, '5425233430109904', 'SOPHIE LEROY', '11/28', '321', 'MasterCard', 1500.00, 1, '2025-05-29 23:06:41'),
(5, '374245455400126', 'THOMAS DUBOIS', '03/26', '1234', 'American Express', 5000.00, 1, '2025-05-29 23:06:41'),
(6, '374245455400127', 'EMMA MARTIN', '08/27', '5678', 'American Express', 2800.00, 1, '2025-05-29 23:06:41'),
(7, 'paypal.user@example.', 'JULIEN MOREAU', '12/29', '999', 'PayPal', 1200.00, 1, '2025-05-29 23:06:41');

-- --------------------------------------------------------

--
-- Structure de la table `coachs`
--

DROP TABLE IF EXISTS `coachs`;
CREATE TABLE IF NOT EXISTS `coachs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int DEFAULT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialite` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cv_xml_id` int DEFAULT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_presentation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bureau` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('disponible','occupe','absent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'disponible',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `coachs`
--

INSERT INTO `coachs` (`id`, `utilisateur_id`, `nom`, `prenom`, `specialite`, `description`, `cv_xml_id`, `photo`, `video_presentation`, `bureau`, `telephone`, `email`, `statut`, `actif`, `created_at`, `updated_at`) VALUES
(1, 2, 'Dubois', 'Martin', 'Spécialiste Musculation', 'Expert en musculation et préparation physique avec plus de 8 ans d\'expérience. Passionné par l\'accompagnement des étudiants vers leurs objectifs sportifs.', 10, '/images_projet/coach2.jpg', NULL, 'Bureau 201 - Salle de Musculation', '01.23.45.67.89', 'martin.dubois@omnes.fr', 'disponible', 1, '2025-05-27 16:01:35', '2025-06-01 14:30:27'),
(2, 3, 'Dukos', 'Pierre', 'Coach Basketball', 'Ancien joueur professionnel avec 12 ans d\'expérience en compétition. Spécialisé dans le développement technique et tactique des joueurs.', NULL, '/images_projet/coach_basketball.jpg', NULL, 'Gymnase A - Terrain Basketball', '01.23.45.67.90', 'pierre.dubois@omnes.fr', 'disponible', 1, '2025-05-27 16:08:01', '2025-06-01 13:21:14'),
(3, NULL, 'Lefèvre', 'Antoine', 'Coach Football', 'Entraîneur diplômé UEFA B, passionné par le football moderne. Expert en préparation physique et tactique collective.', NULL, '/images_projet/coach_football.jpg', NULL, 'Terrain de Football - Extérieur', '01.23.45.67.91', 'antoine.lefevre@omnes.fr', 'disponible', 1, '2025-05-27 16:08:01', '2025-05-27 16:08:01'),
(4, NULL, 'Rousseau', 'Marc', 'Coach Rugby', 'Ancien joueur de Top 14, spécialisé dans la formation des avants et la mêlée. Prône les valeurs de respect et de solidarité.', NULL, '/images_projet/coach_rugby.jpg', NULL, 'Terrain de Rugby - Complexe Sportif', '01.23.45.67.92', 'marc.rousseau@omnes.fr', 'occupe', 1, '2025-05-27 16:08:01', '2025-05-27 16:08:01'),
(5, NULL, 'Martin', 'Isabelle', 'Coach Tennis', 'Professeure de tennis certifiée FFT, spécialisée dans l\'enseignement technique et mental. 15 ans d\'expérience en coaching.', NULL, '/images_projet/coach_tennis.jpg', NULL, 'Courts de Tennis - Complexe Sportif', '01.23.45.67.93', 'isabelle.martin@omnes.fr', 'disponible', 1, '2025-05-27 16:08:01', '2025-05-27 16:08:01'),
(6, NULL, 'Blanc', 'Sophie', 'Coach Natation', 'Maître-nageur sauveteur et entraîneuse de natation. Spécialisée dans l\'amélioration technique et l\'endurance aquatique.', 9, '/images_projet/coach_natation.jpg', NULL, 'Piscine Olympique - Centre Aquatique', '01.23.45.67.94', 'sophie.blanc@omnes.fr', 'disponible', 1, '2025-05-27 16:08:01', '2025-06-01 14:29:55'),
(7, NULL, 'Louka', 'Thomas', 'Coach Plongée', 'Instructeur PADI Advanced Open Water, passionné par les sports aquatiques. Initiation et perfectionnement en plongée sous-marine.', NULL, '/images_projet/coach_plongee.jpg', NULL, 'Centre de Plongée - Piscine 4m', '01.23.45.67.95', 'thomas.noir@omnes.fr', 'disponible', 1, '2025-05-27 16:08:01', '2025-06-01 13:20:34'),
(8, NULL, 'Lefort', 'Sarah', 'Spécialiste Fitness', 'Certifiée fitness et remise en forme, spécialisée dans l\'accompagnement personnalisé et les programmes de bien-être adaptés à tous niveaux.', NULL, '/images_projet/coach3.jpg', NULL, 'Bureau 105 - Studio Fitness', '01.23.45.67.96', 'sarah.lefort@omnes.fr', 'occupe', 1, '2025-05-27 18:39:46', '2025-05-27 18:39:46'),
(9, NULL, 'Moreau', 'Emma', 'Spécialiste Cardio-Training', 'Master en sciences du sport, experte en entraînement cardiovasculaire et en amélioration des performances d\'endurance. Passionnée de course à pied.', NULL, '/images_projet/coach2.jpg', NULL, 'Salle Cardio - Niveau 1', '01.23.45.67.97', 'emma.moreau@omnes.fr', 'disponible', 1, '2025-05-27 18:39:46', '2025-05-27 18:39:46'),
(10, NULL, 'Petit', 'Julie', 'Instructrice Cours Collectifs', 'Instructrice diplômée avec 5 ans d\'expérience dans l\'animation de cours collectifs variés : Zumba, Step, Pilates, Yoga et bien plus encore.', NULL, '/images_projet/coach3.jpg', NULL, 'Studio Cours Collectifs', '01.23.45.67.98', 'julie.petit@omnes.fr', 'disponible', 1, '2025-05-27 18:39:46', '2025-05-27 18:39:46'),
(11, 9, 'yesiltas ', 'edis', 'Spécialiste Fitness', '', NULL, '/images_projet/default_coach.jpg', NULL, 'proutland', '0102030405', 'edis.pamuk@gmail.com', 'disponible', 0, '2025-06-01 14:29:13', '2025-06-01 14:29:40');

-- --------------------------------------------------------

--
-- Structure de la table `creneaux`
--

DROP TABLE IF EXISTS `creneaux`;
CREATE TABLE IF NOT EXISTS `creneaux` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coach_id` int NOT NULL,
  `date_creneau` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `statut` enum('libre','occupe','reserve','indisponible') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'libre',
  `motif` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etudiant_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etudiant_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_creneau` (`coach_id`,`date_creneau`,`heure_debut`),
  KEY `idx_coach_date` (`coach_id`,`date_creneau`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_statut` (`date_creneau`,`statut`),
  KEY `idx_coach_date_heure` (`coach_id`,`date_creneau`,`heure_debut`)
) ENGINE=MyISAM AUTO_INCREMENT=1127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `creneaux`
--

INSERT INTO `creneaux` (`id`, `coach_id`, `date_creneau`, `heure_debut`, `heure_fin`, `statut`, `motif`, `etudiant_nom`, `etudiant_email`, `notes`, `created_at`, `updated_at`) VALUES
(644, 1, '2025-05-26', '08:00:00', '09:00:00', 'occupe', 'Séance force débutants', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(645, 1, '2025-05-26', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, '', '2025-05-27 20:56:10', '2025-05-28 21:17:03'),
(646, 1, '2025-05-26', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 21:27:22'),
(647, 1, '2025-05-26', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(648, 1, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(649, 1, '2025-05-26', '15:00:00', '16:00:00', 'occupe', 'Personal training VIP', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(650, 1, '2025-05-26', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(651, 1, '2025-05-26', '17:00:00', '18:00:00', 'occupe', 'Cours powerlifting', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(652, 1, '2025-05-27', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(653, 1, '2025-05-27', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(654, 1, '2025-05-27', '10:00:00', '11:00:00', 'occupe', 'Séance force débutants', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(655, 1, '2025-05-27', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(656, 1, '2025-05-27', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(657, 1, '2025-05-27', '15:00:00', '16:00:00', 'occupe', 'Personal training', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(658, 1, '2025-05-27', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(659, 1, '2025-05-27', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(660, 1, '2025-05-28', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(661, 1, '2025-05-28', '09:00:00', '10:00:00', 'occupe', 'Formation continue', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(662, 1, '2025-05-28', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(663, 1, '2025-05-28', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(664, 1, '2025-05-28', '14:00:00', '15:00:00', 'occupe', 'Cours haltérophilie', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(665, 1, '2025-05-28', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(666, 1, '2025-05-28', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(667, 1, '2025-05-28', '17:00:00', '18:00:00', 'occupe', 'Musculation avancée', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(668, 1, '2025-05-29', '08:00:00', '09:00:00', 'occupe', 'Powerlifting', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(669, 1, '2025-05-29', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(670, 1, '2025-05-29', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(671, 1, '2025-05-29', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(672, 1, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-28 21:16:25'),
(673, 1, '2025-05-29', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(674, 1, '2025-05-29', '16:00:00', '17:00:00', 'occupe', 'Séance bodybuilding', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(675, 1, '2025-05-29', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(676, 1, '2025-05-30', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(677, 1, '2025-05-30', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(678, 1, '2025-05-30', '10:00:00', '11:00:00', 'occupe', 'Réunion équipe', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(679, 1, '2025-05-30', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(680, 1, '2025-05-30', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(681, 1, '2025-05-30', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(682, 1, '2025-05-30', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(683, 1, '2025-05-30', '17:00:00', '18:00:00', 'occupe', 'Préparation physique', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(684, 1, '2025-05-31', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(685, 1, '2025-05-31', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(686, 1, '2025-05-31', '11:00:00', '12:00:00', 'occupe', 'Cours CrossFit', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(687, 1, '2025-05-31', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(688, 1, '2025-05-31', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(689, 1, '2025-05-31', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(690, 2, '2025-05-26', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(691, 2, '2025-05-26', '09:00:00', '10:00:00', 'occupe', 'Entraînement physique basket', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(692, 2, '2025-05-26', '10:00:00', '11:00:00', 'reserve', '', 'Desire doue', 'desire@gmail.com', '', '2025-05-27 20:56:10', '2025-06-01 14:05:26'),
(693, 2, '2025-05-26', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(694, 2, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 22:18:30'),
(695, 2, '2025-05-26', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(696, 2, '2025-05-26', '16:00:00', '17:00:00', 'occupe', 'Technique dribble', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(697, 2, '2025-05-26', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(698, 2, '2025-05-27', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(699, 2, '2025-05-27', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(700, 2, '2025-05-27', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(701, 2, '2025-05-27', '11:00:00', '12:00:00', 'occupe', 'Entraînement équipe senior', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(702, 2, '2025-05-27', '14:00:00', '15:00:00', 'occupe', 'Cours technique tir', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(703, 2, '2025-05-27', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(704, 2, '2025-05-27', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(705, 2, '2025-05-27', '17:00:00', '18:00:00', 'occupe', 'Match amical', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(706, 2, '2025-05-28', '08:00:00', '09:00:00', 'occupe', 'Préparation physique basket', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(707, 2, '2025-05-28', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(708, 2, '2025-05-28', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(709, 2, '2025-05-28', '11:00:00', '12:00:00', 'reserve', NULL, 'Desire doue', 'desire@gmail.com', '', '2025-05-27 20:56:10', '2025-06-01 14:19:37'),
(710, 2, '2025-05-28', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(711, 2, '2025-05-28', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(712, 2, '2025-05-28', '16:00:00', '17:00:00', 'occupe', 'Cours débutants', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(713, 2, '2025-05-28', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(714, 2, '2025-05-29', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(715, 2, '2025-05-29', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(716, 2, '2025-05-29', '10:00:00', '11:00:00', 'occupe', 'Analyse vidéo tactique', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(717, 2, '2025-05-29', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(718, 2, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(719, 2, '2025-05-29', '15:00:00', '16:00:00', 'occupe', 'Perfectionnement dribble', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(720, 2, '2025-05-29', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(721, 2, '2025-05-29', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(722, 2, '2025-05-30', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(723, 2, '2025-05-30', '09:00:00', '10:00:00', 'occupe', 'Formation arbitrage', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(724, 2, '2025-05-30', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(725, 2, '2025-05-30', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(726, 2, '2025-05-30', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(727, 2, '2025-05-30', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(728, 2, '2025-05-30', '16:00:00', '17:00:00', 'occupe', 'Entraînement physique', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(729, 2, '2025-05-30', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(730, 2, '2025-05-31', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(731, 2, '2025-05-31', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(732, 2, '2025-05-31', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(733, 2, '2025-05-31', '14:00:00', '15:00:00', 'occupe', 'Préparation match', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(734, 2, '2025-05-31', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(735, 2, '2025-05-31', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(736, 3, '2025-05-26', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(737, 3, '2025-05-26', '09:00:00', '10:00:00', 'occupe', 'Préparation physique', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(738, 3, '2025-05-26', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(739, 3, '2025-05-26', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(740, 3, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 22:18:22'),
(741, 3, '2025-05-26', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(742, 3, '2025-05-26', '16:00:00', '17:00:00', 'occupe', 'Technique de frappe', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(743, 3, '2025-05-26', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(744, 3, '2025-05-27', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(745, 3, '2025-05-27', '09:00:00', '10:00:00', 'occupe', 'Entraînement gardiens', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(746, 3, '2025-05-27', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(747, 3, '2025-05-27', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(748, 3, '2025-05-27', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(749, 3, '2025-05-27', '15:00:00', '16:00:00', 'occupe', 'Technique passes', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(750, 3, '2025-05-27', '16:00:00', '17:00:00', 'occupe', 'Entraînement collectif', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(751, 3, '2025-05-27', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(752, 3, '2025-05-28', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(753, 3, '2025-05-28', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(754, 3, '2025-05-28', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(755, 3, '2025-05-28', '11:00:00', '12:00:00', 'occupe', 'Réunion tactique', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(756, 3, '2025-05-28', '14:00:00', '15:00:00', 'occupe', 'Formation UEFA', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(757, 3, '2025-05-28', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(758, 3, '2025-05-28', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(759, 3, '2025-05-28', '17:00:00', '18:00:00', 'occupe', 'Match championnat', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(760, 3, '2025-05-29', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(761, 3, '2025-05-29', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(762, 3, '2025-05-29', '10:00:00', '11:00:00', 'occupe', 'Séance conditionnement', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(763, 3, '2025-05-29', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(764, 3, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(765, 3, '2025-05-29', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(766, 3, '2025-05-29', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(767, 3, '2025-05-29', '17:00:00', '18:00:00', 'occupe', 'Cours jeunes', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(768, 3, '2025-05-30', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(769, 3, '2025-05-30', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(770, 3, '2025-05-30', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(771, 3, '2025-05-30', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(772, 3, '2025-05-30', '14:00:00', '15:00:00', 'occupe', 'Préparation weekend', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(773, 3, '2025-05-30', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(774, 3, '2025-05-30', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(775, 3, '2025-05-30', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(776, 3, '2025-05-31', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(777, 3, '2025-05-31', '10:00:00', '11:00:00', 'occupe', 'Match équipe A', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(778, 3, '2025-05-31', '11:00:00', '12:00:00', 'occupe', 'Match équipe A', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(779, 3, '2025-05-31', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(780, 3, '2025-05-31', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(781, 3, '2025-05-31', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(782, 4, '2025-05-26', '08:00:00', '09:00:00', 'occupe', 'Musculation spécialisée', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(783, 4, '2025-05-26', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(784, 4, '2025-05-26', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(785, 4, '2025-05-26', '11:00:00', '12:00:00', 'occupe', 'Technique mêlée', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(786, 4, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(787, 4, '2025-05-26', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 22:18:07'),
(788, 4, '2025-05-26', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(789, 4, '2025-05-26', '17:00:00', '18:00:00', 'occupe', 'Entraînement pack', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(790, 4, '2025-05-27', '08:00:00', '09:00:00', 'occupe', 'Préparation mêlée', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(791, 4, '2025-05-27', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(792, 4, '2025-05-27', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(793, 4, '2025-05-27', '11:00:00', '12:00:00', 'occupe', 'Technique plaquage', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(794, 4, '2025-05-27', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(795, 4, '2025-05-27', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(796, 4, '2025-05-27', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(797, 4, '2025-05-27', '17:00:00', '18:00:00', 'occupe', 'Match senior', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(798, 4, '2025-05-28', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(799, 4, '2025-05-28', '09:00:00', '10:00:00', 'occupe', 'Entraînement avants', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(800, 4, '2025-05-28', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(801, 4, '2025-05-28', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(802, 4, '2025-05-28', '14:00:00', '15:00:00', 'occupe', 'Séance vidéo', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(803, 4, '2025-05-28', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(804, 4, '2025-05-28', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(805, 4, '2025-05-28', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(806, 4, '2025-05-29', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(807, 4, '2025-05-29', '09:00:00', '10:00:00', 'occupe', 'Conditionnement physique', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(808, 4, '2025-05-29', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(809, 4, '2025-05-29', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(810, 4, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(811, 4, '2025-05-29', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(812, 4, '2025-05-29', '16:00:00', '17:00:00', 'occupe', 'Technique touche', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(813, 4, '2025-05-29', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(814, 4, '2025-05-30', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(815, 4, '2025-05-30', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(816, 4, '2025-05-30', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(817, 4, '2025-05-30', '11:00:00', '12:00:00', 'occupe', 'Briefing équipe', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(818, 4, '2025-05-30', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(819, 4, '2025-05-30', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(820, 4, '2025-05-30', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(821, 4, '2025-05-30', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(822, 4, '2025-05-31', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(823, 4, '2025-05-31', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(824, 4, '2025-05-31', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(825, 4, '2025-05-31', '14:00:00', '15:00:00', 'occupe', 'Match important', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(826, 4, '2025-05-31', '15:00:00', '16:00:00', 'occupe', 'Match important', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(827, 4, '2025-05-31', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(828, 5, '2025-05-26', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(829, 5, '2025-05-26', '09:00:00', '10:00:00', 'occupe', 'Cours junior élite', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(830, 5, '2025-05-26', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(831, 5, '2025-05-26', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 22:15:08'),
(832, 5, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(833, 5, '2025-05-26', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(834, 5, '2025-05-26', '16:00:00', '17:00:00', 'occupe', 'Perfectionnement revers', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(835, 5, '2025-05-26', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(836, 5, '2025-05-27', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(837, 5, '2025-05-27', '09:00:00', '10:00:00', 'occupe', 'Cours particulier', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(838, 5, '2025-05-27', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(839, 5, '2025-05-27', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(840, 5, '2025-05-27', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(841, 5, '2025-05-27', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(842, 5, '2025-05-27', '16:00:00', '17:00:00', 'occupe', 'Perfectionnement service', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(843, 5, '2025-05-27', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(844, 5, '2025-05-28', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(845, 5, '2025-05-28', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(846, 5, '2025-05-28', '10:00:00', '11:00:00', 'occupe', 'Stage perfectionnement', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(847, 5, '2025-05-28', '11:00:00', '12:00:00', 'occupe', 'Stage perfectionnement', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(848, 5, '2025-05-28', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(849, 5, '2025-05-28', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(850, 5, '2025-05-28', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(851, 5, '2025-05-28', '17:00:00', '18:00:00', 'occupe', 'Tournoi interne', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(852, 5, '2025-05-29', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(853, 5, '2025-05-29', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(854, 5, '2025-05-29', '10:00:00', '11:00:00', 'occupe', 'Technique coup droit', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(855, 5, '2025-05-29', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(856, 5, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(857, 5, '2025-05-29', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(858, 5, '2025-05-29', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(859, 5, '2025-05-29', '17:00:00', '18:00:00', 'occupe', 'Cours avancé', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(860, 5, '2025-05-30', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(861, 5, '2025-05-30', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(862, 5, '2025-05-30', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(863, 5, '2025-05-30', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(864, 5, '2025-05-30', '14:00:00', '15:00:00', 'occupe', 'Préparation tournoi', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(865, 5, '2025-05-30', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(866, 5, '2025-05-30', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(867, 5, '2025-05-30', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(868, 5, '2025-05-31', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(869, 5, '2025-05-31', '10:00:00', '11:00:00', 'occupe', 'Tournoi weekend', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(870, 5, '2025-05-31', '11:00:00', '12:00:00', 'occupe', 'Tournoi weekend', NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(871, 5, '2025-05-31', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(872, 5, '2025-05-31', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(873, 5, '2025-05-31', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:10', '2025-05-27 20:56:10'),
(874, 6, '2025-05-26', '07:00:00', '08:00:00', 'occupe', 'Aquagym seniors', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(875, 6, '2025-05-26', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(876, 6, '2025-05-26', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(877, 6, '2025-05-26', '10:00:00', '11:00:00', 'occupe', 'Cours crawl perfectionnement', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(878, 6, '2025-05-26', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(879, 6, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 22:22:02'),
(880, 6, '2025-05-26', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(881, 6, '2025-05-26', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(882, 6, '2025-05-26', '17:00:00', '18:00:00', 'occupe', 'Entraînement compétition', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(883, 6, '2025-05-27', '07:00:00', '08:00:00', 'occupe', 'Aquagym matinal', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(884, 6, '2025-05-27', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(885, 6, '2025-05-27', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(886, 6, '2025-05-27', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(887, 6, '2025-05-27', '11:00:00', '12:00:00', 'occupe', 'Cours crawl débutant', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(888, 6, '2025-05-27', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(889, 6, '2025-05-27', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(890, 6, '2025-05-27', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(891, 6, '2025-05-27', '17:00:00', '18:00:00', 'occupe', 'Entraînement compétition', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(892, 6, '2025-05-28', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(893, 6, '2025-05-28', '08:00:00', '09:00:00', 'occupe', 'Perfectionnement brasse', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(894, 6, '2025-05-28', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(895, 6, '2025-05-28', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(896, 6, '2025-05-28', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(897, 6, '2025-05-28', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(898, 6, '2025-05-28', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(899, 6, '2025-05-28', '16:00:00', '17:00:00', 'occupe', 'Sauvetage aquatique', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(900, 6, '2025-05-28', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(901, 6, '2025-05-29', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(902, 6, '2025-05-29', '08:00:00', '09:00:00', 'occupe', 'Technique papillon', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(903, 6, '2025-05-29', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(904, 6, '2025-05-29', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(905, 6, '2025-05-29', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(906, 6, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(907, 6, '2025-05-29', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(908, 6, '2025-05-29', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(909, 6, '2025-05-29', '17:00:00', '18:00:00', 'occupe', 'Waterpolo', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(910, 6, '2025-05-30', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(911, 6, '2025-05-30', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(912, 6, '2025-05-30', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(913, 6, '2025-05-30', '10:00:00', '11:00:00', 'occupe', 'Formation sauvetage', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(914, 6, '2025-05-30', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(915, 6, '2025-05-30', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(916, 6, '2025-05-30', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(917, 6, '2025-05-30', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(918, 6, '2025-05-30', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(919, 6, '2025-05-31', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(920, 6, '2025-05-31', '09:00:00', '10:00:00', 'occupe', 'Compétition natation', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(921, 6, '2025-05-31', '10:00:00', '11:00:00', 'occupe', 'Compétition natation', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(922, 6, '2025-05-31', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(923, 6, '2025-05-31', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(924, 6, '2025-05-31', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(925, 7, '2025-05-26', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(926, 7, '2025-05-26', '10:00:00', '11:00:00', 'occupe', 'Formation N1 théorie', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(927, 7, '2025-05-26', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(928, 7, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(929, 7, '2025-05-26', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 22:14:26'),
(930, 7, '2025-05-26', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(931, 7, '2025-05-26', '17:00:00', '18:00:00', 'occupe', 'Plongée technique', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(932, 7, '2025-05-27', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(933, 7, '2025-05-27', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(934, 7, '2025-05-27', '11:00:00', '12:00:00', 'occupe', 'Baptême plongée', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(935, 7, '2025-05-27', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(936, 7, '2025-05-27', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(937, 7, '2025-05-27', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(938, 7, '2025-05-27', '17:00:00', '18:00:00', 'occupe', 'Formation N1', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(939, 7, '2025-05-28', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(940, 7, '2025-05-28', '10:00:00', '11:00:00', 'occupe', 'Théorie plongée', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(941, 7, '2025-05-28', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(942, 7, '2025-05-28', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(943, 7, '2025-05-28', '15:00:00', '16:00:00', 'occupe', 'Pratique piscine', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(944, 7, '2025-05-28', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(945, 7, '2025-05-28', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(946, 7, '2025-05-29', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(947, 7, '2025-05-29', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(948, 7, '2025-05-29', '11:00:00', '12:00:00', 'occupe', 'Formation N2', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(949, 7, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(950, 7, '2025-05-29', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(951, 7, '2025-05-29', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(952, 7, '2025-05-29', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(953, 7, '2025-05-30', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(954, 7, '2025-05-30', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(955, 7, '2025-05-30', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(956, 7, '2025-05-30', '14:00:00', '15:00:00', 'occupe', 'Sortie mer', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(957, 7, '2025-05-30', '15:00:00', '16:00:00', 'occupe', 'Sortie mer', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(958, 7, '2025-05-30', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(959, 7, '2025-05-30', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(960, 7, '2025-05-31', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(961, 7, '2025-05-31', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(962, 7, '2025-05-31', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(963, 7, '2025-05-31', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(964, 7, '2025-05-31', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(965, 7, '2025-05-31', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(966, 8, '2025-05-26', '07:00:00', '08:00:00', 'occupe', 'Cours fitness matinal', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(967, 8, '2025-05-26', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(968, 8, '2025-05-26', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(969, 8, '2025-05-26', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 22:13:48'),
(970, 8, '2025-05-26', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(971, 8, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(972, 8, '2025-05-26', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(973, 8, '2025-05-26', '16:00:00', '17:00:00', 'occupe', 'Séance bien-être', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(974, 8, '2025-05-26', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(975, 8, '2025-05-27', '07:00:00', '08:00:00', 'occupe', 'Cours fitness matinal', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(976, 8, '2025-05-27', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(977, 8, '2025-05-27', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(978, 8, '2025-05-27', '10:00:00', '11:00:00', 'occupe', 'Personal training', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(979, 8, '2025-05-27', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(980, 8, '2025-05-27', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(981, 8, '2025-05-27', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(982, 8, '2025-05-27', '16:00:00', '17:00:00', 'occupe', 'Séance bien-être', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(983, 8, '2025-05-27', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(984, 8, '2025-05-28', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(985, 8, '2025-05-28', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(986, 8, '2025-05-28', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(987, 8, '2025-05-28', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(988, 8, '2025-05-28', '11:00:00', '12:00:00', 'occupe', 'Formation continue', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(989, 8, '2025-05-28', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(990, 8, '2025-05-28', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(991, 8, '2025-05-28', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(992, 8, '2025-05-28', '17:00:00', '18:00:00', 'occupe', 'Cours collectif fitness', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(993, 8, '2025-05-29', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(994, 8, '2025-05-29', '08:00:00', '09:00:00', 'occupe', 'Body sculpt', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(995, 8, '2025-05-29', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(996, 8, '2025-05-29', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(997, 8, '2025-05-29', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(998, 8, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(999, 8, '2025-05-29', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1000, 8, '2025-05-29', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1001, 8, '2025-05-29', '17:00:00', '18:00:00', 'occupe', 'Stretching détente', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1002, 8, '2025-05-30', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1003, 8, '2025-05-30', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1004, 8, '2025-05-30', '09:00:00', '10:00:00', 'occupe', 'Coaching minceur', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1005, 8, '2025-05-30', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1006, 8, '2025-05-30', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1007, 8, '2025-05-30', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1008, 8, '2025-05-30', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1009, 8, '2025-05-30', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1010, 8, '2025-05-30', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1011, 8, '2025-05-31', '08:00:00', '09:00:00', 'occupe', 'TRX matinal', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1012, 8, '2025-05-31', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1013, 8, '2025-05-31', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1014, 8, '2025-05-31', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1015, 8, '2025-05-31', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1016, 8, '2025-05-31', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1017, 9, '2025-05-26', '06:00:00', '07:00:00', 'occupe', 'Course matinale', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1018, 9, '2025-05-26', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1019, 9, '2025-05-26', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1020, 9, '2025-05-26', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1021, 9, '2025-05-26', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1022, 9, '2025-05-26', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 22:12:52'),
(1023, 9, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1024, 9, '2025-05-26', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1025, 9, '2025-05-26', '16:00:00', '17:00:00', 'occupe', 'HIIT avancé', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1026, 9, '2025-05-26', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1027, 9, '2025-05-27', '06:00:00', '07:00:00', 'occupe', 'Course matinale', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11');
INSERT INTO `creneaux` (`id`, `coach_id`, `date_creneau`, `heure_debut`, `heure_fin`, `statut`, `motif`, `etudiant_nom`, `etudiant_email`, `notes`, `created_at`, `updated_at`) VALUES
(1028, 9, '2025-05-27', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1029, 9, '2025-05-27', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1030, 9, '2025-05-27', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1031, 9, '2025-05-27', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1032, 9, '2025-05-27', '11:00:00', '12:00:00', 'occupe', 'Entraînement running', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1033, 9, '2025-05-27', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1034, 9, '2025-05-27', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1035, 9, '2025-05-27', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1036, 9, '2025-05-27', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1037, 9, '2025-05-28', '06:00:00', '07:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1038, 9, '2025-05-28', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1039, 9, '2025-05-28', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1040, 9, '2025-05-28', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1041, 9, '2025-05-28', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1042, 9, '2025-05-28', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1043, 9, '2025-05-28', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1044, 9, '2025-05-28', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1045, 9, '2025-05-28', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1046, 9, '2025-05-28', '17:00:00', '18:00:00', 'occupe', 'Cours vélo spinning', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1047, 9, '2025-05-29', '06:00:00', '07:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1048, 9, '2025-05-29', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1049, 9, '2025-05-29', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1050, 9, '2025-05-29', '09:00:00', '10:00:00', 'occupe', 'Préparation marathon', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1051, 9, '2025-05-29', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1052, 9, '2025-05-29', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1053, 9, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1054, 9, '2025-05-29', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1055, 9, '2025-05-29', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1056, 9, '2025-05-29', '17:00:00', '18:00:00', 'occupe', 'HIIT cardio', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1057, 9, '2025-05-30', '06:00:00', '07:00:00', 'occupe', 'Footing groupe', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1058, 9, '2025-05-30', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1059, 9, '2025-05-30', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1060, 9, '2025-05-30', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1061, 9, '2025-05-30', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1062, 9, '2025-05-30', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1063, 9, '2025-05-30', '14:00:00', '15:00:00', 'occupe', 'Test endurance', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1064, 9, '2025-05-30', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1065, 9, '2025-05-30', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1066, 9, '2025-05-30', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1067, 9, '2025-05-31', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1068, 9, '2025-05-31', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1069, 9, '2025-05-31', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1070, 9, '2025-05-31', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1071, 9, '2025-05-31', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1072, 9, '2025-05-31', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1073, 9, '2025-05-31', '15:00:00', '16:00:00', 'occupe', 'Cross training', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1074, 10, '2025-05-26', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1075, 10, '2025-05-26', '08:00:00', '09:00:00', 'occupe', 'Yoga matinal', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1076, 10, '2025-05-26', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1077, 10, '2025-05-26', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1078, 10, '2025-05-26', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1079, 10, '2025-05-26', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 22:20:21'),
(1080, 10, '2025-05-26', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1081, 10, '2025-05-26', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1082, 10, '2025-05-26', '17:00:00', '18:00:00', 'occupe', 'Zumba', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1083, 10, '2025-05-27', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1084, 10, '2025-05-27', '08:00:00', '09:00:00', 'occupe', 'Yoga matinal', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1085, 10, '2025-05-27', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1086, 10, '2025-05-27', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1087, 10, '2025-05-27', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1088, 10, '2025-05-27', '14:00:00', '15:00:00', 'occupe', 'Pilates', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1089, 10, '2025-05-27', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1090, 10, '2025-05-27', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1091, 10, '2025-05-27', '17:00:00', '18:00:00', 'occupe', 'Zumba', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1092, 10, '2025-05-28', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1093, 10, '2025-05-28', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1094, 10, '2025-05-28', '09:00:00', '10:00:00', 'occupe', 'Step débutant', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1095, 10, '2025-05-28', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1096, 10, '2025-05-28', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1097, 10, '2025-05-28', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1098, 10, '2025-05-28', '15:00:00', '16:00:00', 'occupe', 'Body Pump', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1099, 10, '2025-05-28', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1100, 10, '2025-05-28', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1101, 10, '2025-05-29', '07:00:00', '08:00:00', 'occupe', 'Méditation guidée', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1102, 10, '2025-05-29', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1103, 10, '2025-05-29', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1104, 10, '2025-05-29', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1105, 10, '2025-05-29', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1106, 10, '2025-05-29', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1107, 10, '2025-05-29', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1108, 10, '2025-05-29', '16:00:00', '17:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1109, 10, '2025-05-29', '17:00:00', '18:00:00', 'occupe', 'Body Combat', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1110, 10, '2025-05-30', '07:00:00', '08:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1111, 10, '2025-05-30', '08:00:00', '09:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1112, 10, '2025-05-30', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1113, 10, '2025-05-30', '10:00:00', '11:00:00', 'occupe', 'Formation Pilates', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1114, 10, '2025-05-30', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1115, 10, '2025-05-30', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1116, 10, '2025-05-30', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1117, 10, '2025-05-30', '16:00:00', '17:00:00', 'occupe', 'Stretching postural', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1118, 10, '2025-05-30', '17:00:00', '18:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1119, 10, '2025-05-31', '08:00:00', '09:00:00', 'occupe', 'Yoga Vinyasa', NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1120, 10, '2025-05-31', '09:00:00', '10:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1121, 10, '2025-05-31', '10:00:00', '11:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1122, 10, '2025-05-31', '11:00:00', '12:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1123, 10, '2025-05-31', '14:00:00', '15:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1124, 10, '2025-05-31', '15:00:00', '16:00:00', 'libre', NULL, NULL, NULL, NULL, '2025-05-27 20:56:11', '2025-05-27 20:56:11'),
(1125, 2, '2025-06-01', '18:00:00', '19:00:00', 'libre', '', NULL, NULL, NULL, '2025-06-01 14:20:46', '2025-06-01 14:20:46'),
(1126, 2, '2025-06-02', '14:00:00', '15:00:00', 'libre', '', NULL, NULL, NULL, '2025-06-01 14:21:23', '2025-06-01 14:21:23');

-- --------------------------------------------------------

--
-- Structure de la table `cv_fichiers`
--

DROP TABLE IF EXISTS `cv_fichiers`;
CREATE TABLE IF NOT EXISTS `cv_fichiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coach_id` int NOT NULL,
  `type_fichier` enum('xml','pdf','doc','video') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'xml',
  `nom_fichier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin_fichier` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu_xml` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `taille_fichier` int DEFAULT NULL,
  `date_upload` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `uploade_par` int DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_coach_id` (`coach_id`),
  KEY `idx_type_fichier` (`type_fichier`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cv_fichiers`
--

INSERT INTO `cv_fichiers` (`id`, `coach_id`, `type_fichier`, `nom_fichier`, `chemin_fichier`, `contenu_xml`, `taille_fichier`, `date_upload`, `uploade_par`, `actif`) VALUES
(10, 1, 'xml', 'cv_coach_1_2025-06-01_16-30-27.xml', 'uploads/cv/cv_coach_1_2025-06-01_16-30-27.xml', '<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<cv_coach>\n  <informations_generales>\n    <nom>Dubois</nom>\n    <prenom>Martin</prenom>\n    <specialite>Spécialiste Musculation</specialite>\n    <email>martin.dubois@omnes.fr</email>\n  </informations_generales>\n  <formations>\n    <formation>\n      <titre/>\n      <etablissement>balezrerlalzlerrerz</etablissement>\n      <annee/>\n    </formation>\n  </formations>\n  <experiences/>\n  <certifications/>\n  <specialites/>\n</cv_coach>\n', 468, '2025-06-01 14:30:27', NULL, 1),
(9, 6, 'xml', 'cv_coach_6_2025-06-01_16-29-55.xml', 'uploads/cv/cv_coach_6_2025-06-01_16-29-55.xml', '<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<cv_coach>\n  <informations_generales>\n    <nom>Blanc</nom>\n    <prenom>Sophie</prenom>\n    <specialite>Coach Natation</specialite>\n    <email>sophie.blanc@omnes.fr</email>\n  </informations_generales>\n  <formations/>\n  <experiences/>\n  <certifications/>\n  <specialites/>\n</cv_coach>\n', 321, '2025-06-01 14:29:55', NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `historique_paiements`
--

DROP TABLE IF EXISTS `historique_paiements`;
CREATE TABLE IF NOT EXISTS `historique_paiements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paiement_id` int NOT NULL,
  `ancien_statut` enum('en_attente','approuve','refuse','rembourse') DEFAULT NULL,
  `nouveau_statut` enum('en_attente','approuve','refuse','rembourse') DEFAULT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `utilisateur_modificateur_id` int DEFAULT NULL,
  `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_paiement` (`paiement_id`),
  KEY `idx_date` (`date_modification`),
  KEY `utilisateur_modificateur_id` (`utilisateur_modificateur_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Audit trail des modifications de statut de paiement';

-- --------------------------------------------------------

--
-- Structure de la table `messages_predefinis`
--

DROP TABLE IF EXISTS `messages_predefinis`;
CREATE TABLE IF NOT EXISTS `messages_predefinis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categorie` enum('salutation','rdv','info','probleme','autre') NOT NULL,
  `type_expediteur` enum('client','coach') NOT NULL,
  `message_text` text NOT NULL,
  `response_type` enum('static','dynamic_schedule','dynamic_info','dynamic_rdv') DEFAULT 'static',
  `reponse_template` text,
  `ordre_affichage` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `messages_predefinis`
--

INSERT INTO `messages_predefinis` (`id`, `categorie`, `type_expediteur`, `message_text`, `response_type`, `reponse_template`, `ordre_affichage`, `actif`) VALUES
(1, 'salutation', 'client', '👋 Bonjour ! J\'aimerais vous contacter', 'static', '👋 Bonjour ! Je suis {coach_prenom}, votre coach {coach_specialite}. Comment puis-je vous aider ?', 1, 1),
(2, 'salutation', 'client', '😊 Bonsoir, êtes-vous disponible ?', 'static', '😊 Bonsoir ! Oui je suis disponible. En quoi puis-je vous être utile ?', 2, 1),
(3, 'rdv', 'client', '📅 Je souhaite prendre un rendez-vous', 'dynamic_schedule', 'Voici mes prochaines disponibilités :\n{available_slots}\n\nCliquez sur \"Prendre RDV\" pour réserver !', 3, 1),
(4, 'rdv', 'client', '⏰ Quels sont vos créneaux libres ?', 'dynamic_schedule', 'Mes créneaux libres cette semaine :\n{available_slots}\n\nPour réserver : [Prendre RDV](disponibilités.php?coach_id={coach_id})', 4, 1),
(5, 'rdv', 'client', '❌ Je dois annuler mon rendez-vous', 'dynamic_rdv', '❌ Pas de problème ! {current_appointments}\n\nPour annuler, rendez-vous dans \"Mes Réservations\".', 5, 1),
(6, 'info', 'client', '❓ Quels sont vos horaires ?', 'dynamic_info', '⏰ Mes horaires :\n📍 Bureau : {coach_bureau}\n📞 Tél : {coach_telephone}\n🕐 Généralement : Lun-Ven 8h-18h', 6, 1),
(7, 'info', 'client', '📍 Où se trouvent vos cours ?', 'dynamic_info', '📍 Mes cours ont lieu :\n🏢 {coach_bureau}\n📧 Contact : {coach_email}\n🏫 Adresse : 37 Quai de Grenelle, 75015 Paris', 7, 1),
(8, 'info', 'client', '💰 Quels sont vos tarifs ?', 'static', '💰 Tarifs :\n✅ Consultations gratuites pour étudiants Omnes\n💎 Services premium disponibles via le site\n💳 Paiement sécurisé en ligne', 8, 1),
(9, 'probleme', 'client', '😓 J\'ai un problème technique', 'static', '😓 Je comprends. Décrivez-moi le problème :\n🔧 Problème de réservation ?\n💻 Souci avec le site ?\n📱 Je vais vous aider !', 9, 1),
(10, 'probleme', 'client', '😷 Je suis malade, que faire ?', 'dynamic_rdv', '😷 Prenez soin de vous ! \n{current_appointments}\n🏥 Reposez-vous, on peut reporter sans frais.', 10, 1),
(11, 'autre', 'client', '💪 Conseils d\'entraînement ?', 'dynamic_info', '💪 Mes conseils en {coach_specialite} :\n✨ Consultation personnalisée recommandée\n📋 Programme adapté à vos objectifs\n🎯 Réservez un créneau pour en parler !', 11, 1),
(12, 'autre', 'client', '🙏 Merci pour votre aide !', 'static', '🙏 De rien ! C\'est un plaisir de vous accompagner dans votre parcours sportif. À très bientôt !', 12, 1);

-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

DROP TABLE IF EXISTS `paiements`;
CREATE TABLE IF NOT EXISTS `paiements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `adresse_facturation_id` int NOT NULL,
  `service_id` int NOT NULL,
  `service_nom` varchar(255) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `tva` decimal(10,2) GENERATED ALWAYS AS ((`montant` * 0.20)) STORED,
  `montant_total` decimal(10,2) GENERATED ALWAYS AS ((`montant` + (`montant` * 0.20))) STORED,
  `type_carte` enum('Visa','MasterCard','American Express','PayPal') NOT NULL,
  `numero_carte_masque` varchar(20) NOT NULL,
  `nom_carte` varchar(100) NOT NULL,
  `date_expiration` varchar(5) NOT NULL,
  `numero_transaction` varchar(50) NOT NULL,
  `statut_paiement` enum('en_attente','approuve','refuse','rembourse') DEFAULT 'en_attente',
  `date_paiement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip_client` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_transaction` (`numero_transaction`),
  KEY `idx_utilisateur` (`utilisateur_id`),
  KEY `idx_transaction` (`numero_transaction`),
  KEY `idx_statut` (`statut_paiement`),
  KEY `idx_date` (`date_paiement`),
  KEY `adresse_facturation_id` (`adresse_facturation_id`),
  KEY `idx_paiements_utilisateur_date` (`utilisateur_id`,`date_paiement`),
  KEY `idx_paiements_statut_date` (`statut_paiement`,`date_paiement`),
  KEY `idx_paiements_service_date` (`service_id`,`date_paiement`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Transactions de paiement avec informations sécurisées';

--
-- Déclencheurs `paiements`
--
DROP TRIGGER IF EXISTS `audit_paiements_update`;
DELIMITER $$
CREATE TRIGGER `audit_paiements_update` AFTER UPDATE ON `paiements` FOR EACH ROW BEGIN
    IF OLD.statut_paiement != NEW.statut_paiement THEN
        INSERT INTO historique_paiements (paiement_id, ancien_statut, nouveau_statut, date_modification)
        VALUES (NEW.id, OLD.statut_paiement, NEW.statut_paiement, NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `services_payants`
--

DROP TABLE IF EXISTS `services_payants`;
CREATE TABLE IF NOT EXISTS `services_payants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `duree` varchar(100) NOT NULL,
  `type_service` enum('seance','abonnement','programme','produit') NOT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type_service`),
  KEY `idx_actif` (`actif`),
  KEY `idx_prix` (`prix`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catalogue des services payants disponibles';

--
-- Déchargement des données de la table `services_payants`
--

INSERT INTO `services_payants` (`id`, `nom`, `description`, `prix`, `duree`, `type_service`, `actif`, `date_creation`, `date_modification`) VALUES
(1, 'Coaching Personnel Premium', 'Séances individuelles avec coach personnel certifié (1h)', 45.00, '1 heure', 'seance', 1, '2025-05-29 23:06:41', '2025-05-29 23:06:41'),
(2, 'Abonnement Salle VIP', 'Accès illimité à la salle VIP avec équipements haut de gamme', 89.99, '1 mois', 'abonnement', 1, '2025-05-29 23:06:41', '2025-05-29 23:06:41'),
(3, 'Programme Nutrition Personnalisé', 'Consultation nutritionniste + plan alimentaire sur mesure', 120.00, 'Plan 3 mois', 'programme', 1, '2025-05-29 23:06:41', '2025-05-29 23:06:41'),
(4, 'Cours Particulier Natation', 'Leçons privées de natation avec coach certifié', 55.00, '1 heure', 'seance', 1, '2025-05-29 23:06:41', '2025-05-29 23:06:41'),
(5, 'Pack Préparation Physique', 'Programme intensif de préparation physique (6 séances)', 250.00, '6 séances', 'programme', 1, '2025-05-29 23:06:41', '2025-05-29 23:06:41'),
(6, 'Massage Sportif Thérapeutique', 'Séance de massage sportif avec kinésithérapeute', 65.00, '45 minutes', 'seance', 1, '2025-05-29 23:06:41', '2025-05-29 23:06:41');

-- --------------------------------------------------------

--
-- Structure de la table `sessions_utilisateurs`
--

DROP TABLE IF EXISTS `sessions_utilisateurs`;
CREATE TABLE IF NOT EXISTS `sessions_utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `token_session` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_expiration` timestamp NULL DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_utilisateur_id` (`utilisateur_id`),
  KEY `idx_token` (`token_session`(250))
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `sessions_utilisateurs`
--

INSERT INTO `sessions_utilisateurs` (`id`, `utilisateur_id`, `token_session`, `ip_address`, `user_agent`, `date_creation`, `date_expiration`, `actif`) VALUES
(1, 1, '1f0596d950b262b13ef846e698c4eba34d814e339fd1964e166babc329d8f874', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-28 01:52:10', '2025-05-29 01:52:10', 1),
(2, 5, 'e00e5342e8d33d127a5cbdb53c62e4c458c9f56d5f1456a162786fd82de1304c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-28 20:18:49', '2025-05-29 20:18:49', 1),
(3, 6, 'e9c349bd4a9e6eb2412bc33875c337225e5ad0baa713b2dc80854c031b650502', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-28 21:13:56', '2025-05-29 21:13:56', 0),
(4, 7, 'a6a89698973c30f14a715553b71c38610d3443351687c6aeb384a4acb952f2ad', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-05-30 00:45:53', '2025-05-31 00:45:53', 1),
(5, 1, '072f90464e1c6ec9285d62dab75ebd297e6a95b247f9c052bfb7220d0ecf1bf9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 10:51:29', '2025-06-02 10:51:29', 0),
(6, 8, 'e56d30c96a4d57e27348c0b88f6c607ee881880f82708566665f440b63f92449', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 11:01:00', '2025-06-02 11:01:00', 0),
(7, 1, '21dc7e814328e2fc232e91f032a1bf933bd47559aa324fa7f6360de7d3403855', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 11:03:58', '2025-06-02 11:03:58', 0),
(8, 8, '3489c50f7bceed45d22e6d23bc8eb2cfdc4fc1281358f57c970a1c5b1c0ec8ef', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 12:38:18', '2025-06-02 12:38:18', 0),
(9, 3, 'd4a8d6d18d2accb515b31065e272db9c653d098051505974263dd6efb9e59694', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 13:44:25', '2025-06-02 13:44:25', 0),
(10, 8, '0397ee44fc1f0c71e88da33b07da559e31df4aabad773ef0f64404cb8b8acf04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 14:05:06', '2025-06-02 14:05:06', 0),
(11, 3, 'ebb174f523711c691a1cd4afd881bf3200999c674b372f15d8c8bc347d385d5d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 14:05:49', '2025-06-02 14:05:49', 0),
(12, 8, '3f0cd6f390e944bb480925ad4a43c6697485d47a8df21ec49988cb144f2be09a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 14:16:19', '2025-06-02 14:16:19', 0),
(13, 3, '15008b9317752ef97a6d9d48f7d8eddc297048a82c45f54bcc6a2dd5e2dc56d1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 14:20:15', '2025-06-02 14:20:15', 0),
(14, 8, '0352953b3e94864be95cb2b58cbcd70b5b03b37cce56021bc20aeef71a9f824c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 14:23:23', '2025-06-02 14:23:23', 0),
(15, 3, 'd3c08885dc89dfc6f0d2ca91bea3f29cc287a21e713c13bf6ddb625a0f41b787', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 14:24:07', '2025-06-02 14:24:07', 0),
(16, 1, '1985d03e76cb42eae4d4cbd7f233b116788cf76145f02bfa77a13891fcc55ac0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 14:24:46', '2025-06-02 14:24:46', 0),
(17, 1, '557f03536946ab1555e58a6d9d04af8c928e66e525b745e8311c6c90930ad9b8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-01 14:28:34', '2025-06-02 14:28:34', 0);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_compte` enum('administrateur','coach','client') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'client',
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_profil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('actif','suspendu','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_type_compte` (`type_compte`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `type_compte`, `telephone`, `photo_profil`, `statut`, `date_creation`, `derniere_connexion`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'Système', 'admin@sportify-omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrateur', '01.44.39.06.00', '/images_projet/admin_profile.jpg', 'actif', '2025-05-28 01:13:01', '2025-06-01 14:28:34', '2025-05-28 01:13:01', '2025-06-01 14:28:34'),
(2, 'Dubois', 'Martin', 'martin.dubois@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coach', '01.23.45.67.89', '/images_projet/coach2.jpg', 'actif', '2025-05-28 01:13:01', NULL, '2025-05-28 01:13:01', '2025-05-28 01:13:01'),
(3, 'Dukos', 'Pierre', 'pierre.dubois@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coach', '01.23.45.67.90', '/images_projet/coach_basketball.jpg', 'actif', '2025-05-28 01:13:01', '2025-06-01 14:24:07', '2025-05-28 01:13:01', '2025-06-01 14:24:07'),
(7, 'Yesiltas', 'edis', 'edis.yesiltas@edu.ece.fr', '$2y$10$n2ttLbs36JID7jbQcp164.v8QUFyIZVOnr32FP1NQDX6xtvN0HCOO', 'client', '', NULL, 'actif', '2025-05-30 00:45:47', '2025-05-30 00:45:53', '2025-05-30 00:45:47', '2025-05-30 00:45:53');

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_dashboard_admin`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_dashboard_admin`;
CREATE TABLE IF NOT EXISTS `vue_dashboard_admin` (
`nb_coachs_actifs` bigint
,`nb_creneaux_libres` bigint
,`nb_reservations` bigint
,`nb_clients_actifs` bigint
,`nb_actions_aujourd_hui` bigint
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_paiements_utilisateur`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_paiements_utilisateur`;
CREATE TABLE IF NOT EXISTS `vue_paiements_utilisateur` (
`utilisateur_id` int
,`prenom` varchar(100)
,`nom` varchar(100)
,`email` varchar(100)
,`nombre_paiements` bigint
,`total_depense` decimal(32,2)
,`moyenne_paiement` decimal(14,6)
,`dernier_paiement` timestamp
,`statut_paiement` enum('en_attente','approuve','refuse','rembourse')
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_planning_hebdomadaire`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_planning_hebdomadaire`;
CREATE TABLE IF NOT EXISTS `vue_planning_hebdomadaire` (
`creneau_id` int
,`coach_nom` varchar(100)
,`coach_prenom` varchar(100)
,`jour_semaine` varchar(64)
,`date_affichage` varchar(5)
,`date_creneau` date
,`heure_debut_affichage` varchar(8)
,`heure_fin_affichage` varchar(8)
,`creneau_affichage` varchar(17)
,`statut` enum('libre','occupe','reserve','indisponible')
,`motif` varchar(100)
,`etudiant_nom` varchar(100)
,`etudiant_email` varchar(100)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_revenus_services`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_revenus_services`;
CREATE TABLE IF NOT EXISTS `vue_revenus_services` (
`service_nom` varchar(255)
,`type_service` enum('seance','abonnement','programme','produit')
,`nombre_ventes` bigint
,`revenus_ht` decimal(32,2)
,`revenus_ttc` decimal(32,2)
,`prix_moyen` decimal(14,6)
,`premiere_vente` timestamp
,`derniere_vente` timestamp
);

-- --------------------------------------------------------

--
-- Structure de la vue `vue_dashboard_admin`
--
DROP TABLE IF EXISTS `vue_dashboard_admin`;

DROP VIEW IF EXISTS `vue_dashboard_admin`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_dashboard_admin`  AS SELECT (select count(0) from `coachs` where (`coachs`.`actif` = true)) AS `nb_coachs_actifs`, (select count(0) from `creneaux` where ((`creneaux`.`date_creneau` >= curdate()) and (`creneaux`.`statut` = 'libre'))) AS `nb_creneaux_libres`, (select count(0) from `creneaux` where ((`creneaux`.`date_creneau` >= curdate()) and (`creneaux`.`statut` = 'reserve'))) AS `nb_reservations`, (select count(0) from `utilisateurs` where ((`utilisateurs`.`type_compte` = 'client') and (`utilisateurs`.`statut` = 'actif'))) AS `nb_clients_actifs`, (select count(0) from `admin_logs` where (cast(`admin_logs`.`date_action` as date) = curdate())) AS `nb_actions_aujourd_hui` ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_paiements_utilisateur`
--
DROP TABLE IF EXISTS `vue_paiements_utilisateur`;

DROP VIEW IF EXISTS `vue_paiements_utilisateur`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_paiements_utilisateur`  AS SELECT `u`.`id` AS `utilisateur_id`, `u`.`prenom` AS `prenom`, `u`.`nom` AS `nom`, `u`.`email` AS `email`, count(`p`.`id`) AS `nombre_paiements`, sum(`p`.`montant_total`) AS `total_depense`, avg(`p`.`montant_total`) AS `moyenne_paiement`, max(`p`.`date_paiement`) AS `dernier_paiement`, `p`.`statut_paiement` AS `statut_paiement` FROM (`utilisateurs` `u` left join `paiements` `p` on((`u`.`id` = `p`.`utilisateur_id`))) WHERE (`u`.`type_compte` = 'client') GROUP BY `u`.`id`, `p`.`statut_paiement` ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_planning_hebdomadaire`
--
DROP TABLE IF EXISTS `vue_planning_hebdomadaire`;

DROP VIEW IF EXISTS `vue_planning_hebdomadaire`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_planning_hebdomadaire`  AS SELECT `c`.`id` AS `creneau_id`, `co`.`nom` AS `coach_nom`, `co`.`prenom` AS `coach_prenom`, date_format(`c`.`date_creneau`,'%W') AS `jour_semaine`, date_format(`c`.`date_creneau`,'%d/%m') AS `date_affichage`, `c`.`date_creneau` AS `date_creneau`, time_format(`c`.`heure_debut`,'%Hh') AS `heure_debut_affichage`, time_format(`c`.`heure_fin`,'%Hh') AS `heure_fin_affichage`, concat(time_format(`c`.`heure_debut`,'%Hh'),'-',time_format(`c`.`heure_fin`,'%Hh')) AS `creneau_affichage`, `c`.`statut` AS `statut`, `c`.`motif` AS `motif`, `c`.`etudiant_nom` AS `etudiant_nom`, `c`.`etudiant_email` AS `etudiant_email` FROM (`creneaux` `c` join `coachs` `co` on((`c`.`coach_id` = `co`.`id`))) WHERE (`co`.`actif` = true) ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_revenus_services`
--
DROP TABLE IF EXISTS `vue_revenus_services`;

DROP VIEW IF EXISTS `vue_revenus_services`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_revenus_services`  AS SELECT `sp`.`nom` AS `service_nom`, `sp`.`type_service` AS `type_service`, count(`p`.`id`) AS `nombre_ventes`, sum(`p`.`montant`) AS `revenus_ht`, sum(`p`.`montant_total`) AS `revenus_ttc`, avg(`p`.`montant_total`) AS `prix_moyen`, min(`p`.`date_paiement`) AS `premiere_vente`, max(`p`.`date_paiement`) AS `derniere_vente` FROM (`services_payants` `sp` left join `paiements` `p` on(((`sp`.`id` = `p`.`service_id`) and (`p`.`statut_paiement` = 'approuve')))) WHERE (`sp`.`actif` = true) GROUP BY `sp`.`id` ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
