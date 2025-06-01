<?php
require_once 'auth.php';
requireAdmin();

$user = getCurrentUser();
function getDashboardStats() {
    $conn = getDbConnection();
    
    $query = "SELECT 
                (SELECT COUNT(*) FROM coachs WHERE actif = TRUE) as nb_coachs_actifs,
                (SELECT COUNT(*) FROM creneaux WHERE date_creneau >= CURDATE() AND statut = 'libre') as nb_creneaux_libres,
                (SELECT COUNT(*) FROM creneaux WHERE date_creneau >= CURDATE() AND statut = 'reserve') as nb_reservations,
                (SELECT COUNT(*) FROM utilisateurs WHERE type_compte = 'client' AND statut = 'actif') as nb_clients_actifs,
                (SELECT COUNT(*) FROM admin_logs WHERE DATE(date_action) = CURDATE()) as nb_actions_aujourd_hui";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetch();
}

// liste de coachs
function getAllCoachs() {
    $conn = getDbConnection();
    
    $query = "SELECT c.*, 
                     COALESCE(u.email, c.email) as email, 
                     COALESCE(u.statut, 'actif') as user_statut, 
                     u.derniere_connexion,
                     COUNT(cr.id) as nb_creneaux_total,
                     SUM(CASE WHEN cr.statut = 'libre' AND cr.date_creneau >= CURDATE() THEN 1 ELSE 0 END) as nb_creneaux_libres,
                     SUM(CASE WHEN cr.statut = 'reserve' AND cr.date_creneau >= CURDATE() THEN 1 ELSE 0 END) as nb_reservations
              FROM coachs c 
              LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
              LEFT JOIN creneaux cr ON c.id = cr.coach_id
              WHERE c.nom IS NOT NULL AND c.prenom IS NOT NULL
              GROUP BY c.id
              ORDER BY c.nom, c.prenom";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// logs
function getRecentAdminLogs($limit = 10) {
    $conn = getDbConnection();
    
    $query = "SELECT al.*, u.nom, u.prenom 
              FROM admin_logs al
              LEFT JOIN utilisateurs u ON al.admin_id = u.id
              ORDER BY al.date_action DESC
              LIMIT :limit";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_coach':
            $conn = getDbConnection();
            
            try {
                $mot_de_passe_hash = $_POST['mot_de_passe'];
                $query = "CALL CreerCoach(:nom, :prenom, :email, :mot_de_passe, :specialite, :description, :bureau, :telephone, :admin_id)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':nom', $_POST['nom']);
                $stmt->bindParam(':prenom', $_POST['prenom']);
                $stmt->bindParam(':email', $_POST['email']);
                $stmt->bindParam(':mot_de_passe', $mot_de_passe_hash);
                $stmt->bindParam(':specialite', $_POST['specialite']);
                $stmt->bindParam(':description', $_POST['description']);
                $stmt->bindParam(':bureau', $_POST['bureau']);
                $stmt->bindParam(':telephone', $_POST['telephone']);
                $stmt->bindParam(':admin_id', $user['id']);
                
                $stmt->execute();
                $result = $stmt->fetch();
                
                echo json_encode($result);
                
            } catch (Exception $e) {
                echo json_encode(['result' => 'ERROR', 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete_coach':
            $conn = getDbConnection();
            
            try {
                $query = "CALL SupprimerCoach(:coach_id, :admin_id)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':coach_id', $_POST['coach_id']);
                $stmt->bindParam(':admin_id', $user['id']);
                
                $stmt->execute();
                $result = $stmt->fetch();
                
                echo json_encode($result);
                
            } catch (Exception $e) {
                echo json_encode(['result' => 'ERROR', 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'create_cv_xml':
            try {
        $cv_manager = new CVManager();

        $formations = json_decode($_POST['formations'], true) ?: [];
        $experiences = json_decode($_POST['experiences'], true) ?: [];
        $certifications = json_decode($_POST['certifications'], true) ?: [];
        $specialites = json_decode($_POST['specialites'], true) ?: [];

        $result = $cv_manager->createCoachXML(
            $_POST['coach_id'],
            $formations,
            $experiences,
            $certifications,
            $specialites
        );

        header('Content-Type: application/json');
        echo json_encode($result);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur interne : ' . $e->getMessage()
        ]);
    }
            exit;
    }
}

$stats = getDashboardStats();
$coachs = getAllCoachs();
$logs = getRecentAdminLogs(15);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Sportify | Omnes Education</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f8fafc;
            color: #333;
        }
        .admin-header {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .admin-nav {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .admin-logo {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 4px solid #2563eb;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        .coachs-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #1e40af;
            margin: 0;
        }

        .add-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s;
        }

        .add-btn:hover {
            transform: translateY(-2px);
        }
        .coachs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .coachs-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        .coachs-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .coachs-table tr:hover {
            background: #f8fafc;
        }

        .coach-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-actif {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactif {
            background: #fee2e2;
            color: #dc2626;
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .btn-edit {
            background: #3b82f6;
            color: white;
        }

        .btn-cv {
            background: #8b5cf6;
            color: white;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .sidebar-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .sidebar-title {
            font-size: 1.2rem;
            color: #1e40af;
            margin-bottom: 1rem;
        }
        .log-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.9rem;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-time {
            color: #64748b;
            font-size: 0.8rem;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .modal-title {
            font-size: 1.5rem;
            color: #1e40af;
            margin: 0;
        }

        .close {
            color: #aaa;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 1rem;
            }
            
            .coachs-table {
                font-size: 0.9rem;
            }
            
            .coachs-table th,
            .coachs-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <nav class="admin-nav">
            <div class="admin-logo">
                🔐 Administration Sportify
            </div>
            <div class="admin-user">
                <span>👋 Bienvenue, <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></span>
                <a href="logout.php" class="logout-btn">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="admin-container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['nb_coachs_actifs'] ?></div>
                <div class="stat-label">Coachs actifs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['nb_creneaux_libres'] ?></div>
                <div class="stat-label">Créneaux libres</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['nb_reservations'] ?></div>
                <div class="stat-label">Réservations actives</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['nb_clients_actifs'] ?></div>
                <div class="stat-label">Clients inscrits</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['nb_actions_aujourd_hui'] ?></div>
                <div class="stat-label">Actions aujourd'hui</div>
            </div>
        </div>

        <div class="main-content">
            <!-- gestion coach -->
            <div class="coachs-section">
                <div class="section-header">
                    <h2 class="section-title">👥 Gestion des Coachs</h2>
                    <button class="add-btn" onclick="openAddCoachModal()">
                        ➕ Ajouter un Coach
                    </button>
                </div>

                <table class="coachs-table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Nom</th>
                            <th>Spécialité</th>
                            <th>Email</th>
                            <th>Statut</th>
                            <th>Créneaux</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coachs as $coach): ?>
                        <tr>
                            <td>
                                <img src="<?= htmlspecialchars($coach['photo'] ?? '/images_projet/default_coach.jpg') ?>" alt="Photo" class="coach-photo">
                            </td>
                            <td>
                                <strong><?= htmlspecialchars(($coach['prenom'] ?? 'Prénom') . ' ' . ($coach['nom'] ?? 'Nom')) ?></strong><br>
                                <small><?= htmlspecialchars($coach['bureau'] ?? 'Bureau non défini') ?></small>
                            </td>
                            <td><?= htmlspecialchars($coach['specialite'] ?? 'Spécialité non définie') ?></td>
                            <td><?= htmlspecialchars($coach['email'] ?? 'Email non défini') ?></td>
                            <td>
                                <span class="status-badge <?= $coach['actif'] ? 'status-actif' : 'status-inactif' ?>">
                                    <?= $coach['actif'] ? 'Actif' : 'Inactif' ?>
                                </span>
                            </td>
                            <td>
                                <small>
                                    <?= $coach['nb_creneaux_libres'] ?> libres<br>
                                    <?= $coach['nb_reservations'] ?> réservés
                                </small>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-sm btn-edit" onclick="editCoach(<?= $coach['id'] ?>)">
                                        ✏️ Modifier
                                    </button>
                                    <button class="btn-sm btn-cv" onclick="openCVModal(<?= $coach['id'] ?>, '<?= htmlspecialchars(($coach['prenom'] ?? 'Coach') . ' ' . ($coach['nom'] ?? '')) ?>')">
                                        📄 CV XML
                                    </button>
                                    <?php if ($coach['actif']): ?>
                                    <button class="btn-sm btn-delete" onclick="deleteCoach(<?= $coach['id'] ?>, '<?= htmlspecialchars(($coach['prenom'] ?? 'Coach') . ' ' . ($coach['nom'] ?? '')) ?>')">
                                        🗑️ Supprimer
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="sidebar">
                <div class="sidebar-card">
                    <h3 class="sidebar-title">⚡ Actions Rapides</h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <button class="add-btn" onclick="openAddCoachModal()">
                            ➕ Nouveau Coach
                        </button>
                        <button class="add-btn" onclick="exportData()" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                            📊 Exporter Données
                        </button>
                        <button class="add-btn" onclick="viewReports()" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            📈 Rapports
                        </button>
                    </div>
                </div>
                <div class="sidebar-card">
                    <h3 class="sidebar-title">📋 Activité Récente</h3>
                    <div>
                        <?php foreach ($logs as $log): ?>
                        <div class="log-item">
                            <div>
                                <strong><?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?></strong>
                                <?= htmlspecialchars($log['action']) ?>
                            </div>
                            <div class="log-time">
                                <?= date('d/m/Y H:i', strtotime($log['date_action'])) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sidebar-card">
                    <h3 class="sidebar-title">🏢 Infos Salle de Sport</h3>
                    <div style="font-size: 0.9rem; line-height: 1.6;">
                        <p><strong>📍 Adresse :</strong><br>37 Quai de Grenelle, 75015 Paris</p>
                        <p><strong>📞 Téléphone :</strong><br>+33 1 44 39 06 00</p>
                        <p><strong>⏰ Horaires :</strong><br>
                        Lun-Ven : 6h00-22h00<br>
                        Week-end : 8h00-20h00</p>
                        <p><strong>👥 Capacité :</strong><br>150 personnes simultanément</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="addCoachModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ Ajouter un Nouveau Coach</h2>
                <span class="close" onclick="closeAddCoachModal()">&times;</span>
            </div>
            <form id="addCoachForm">
                <div class="form-group">
                    <label for="coach-nom">Nom :</label>
                    <input type="text" id="coach-nom" name="nom" required>
                </div>
                <div class="form-group">
                    <label for="coach-prenom">Prénom :</label>
                    <input type="text" id="coach-prenom" name="prenom" required>
                </div>
                <div class="form-group">
                    <label for="coach-email">Email :</label>
                    <input type="email" id="coach-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="coach-password">Mot de passe :</label>
                    <input type="password" id="coach-password" name="mot_de_passe" required>
                </div>
                <div class="form-group">
                    <label for="coach-specialite">Spécialité :</label>
                    <select id="coach-specialite" name="specialite" required>
                        <option value="">Sélectionner une spécialité</option>
                        <option value="Spécialiste Musculation">Spécialiste Musculation</option>
                        <option value="Spécialiste Fitness">Spécialiste Fitness</option>
                        <option value="Spécialiste Cardio-Training">Spécialiste Cardio-Training</option>
                        <option value="Instructrice Cours Collectifs">Instructrice Cours Collectifs</option>
                        <option value="Coach Basketball">Coach Basketball</option>
                        <option value="Coach Football">Coach Football</option>
                        <option value="Coach Rugby">Coach Rugby</option>
                        <option value="Coach Tennis">Coach Tennis</option>
                        <option value="Coach Natation">Coach Natation</option>
                        <option value="Coach Plongée">Coach Plongée</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="coach-bureau">Bureau/Lieu :</label>
                    <input type="text" id="coach-bureau" name="bureau" required>
                </div>
                <div class="form-group">
                    <label for="coach-telephone">Téléphone :</label>
                    <input type="tel" id="coach-telephone" name="telephone">
                </div>
                <div class="form-group">
                    <label for="coach-description">Description :</label>
                    <textarea id="coach-description" name="description" placeholder="Décrivez l'expérience et les qualifications du coach..."></textarea>
                </div>
                <button type="submit" class="submit-btn">✅ Créer le Coach</button>
            </form>
        </div>
    </div>

    <!--XML-->
    <div id="cvModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">📄 Créer CV XML - <span id="cv-coach-name"></span></h2>
                <span class="close" onclick="closeCVModal()">&times;</span>
            </div>
            <form id="cvForm">
                <input type="hidden" id="cv-coach-id" name="coach_id">
                
                <h3>🎓 Formations</h3>
                <div id="formations-container">
                    <div class="formation-item">
                        <div class="form-group">
                            <label>Titre de la formation :</label>
                            <input type="text" name="formation_titre[]" placeholder="Master STAPS">
                        </div>
                        <div class="form-group">
                            <label>Établissement :</label>
                            <input type="text" name="formation_etablissement[]" placeholder="Université Paris Sud">
                        </div>
                        <div class="form-group">
                            <label>Année :</label>
                            <input type="text" name="formation_annee[]" placeholder="2015-2017">
                        </div>
                    </div>
                </div>
                <button type="button" onclick="addFormation()" style="margin-bottom: 2rem; background: #10b981; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px;">➕ Ajouter Formation</button>

                <h3>💼 Expériences</h3>
                <div id="experiences-container">
                    <div class="experience-item">
                        <div class="form-group">
                            <label>Poste :</label>
                            <input type="text" name="experience_poste[]" placeholder="Coach Sportify">
                        </div>
                        <div class="form-group">
                            <label>Entreprise :</label>
                            <input type="text" name="experience_entreprise[]" placeholder="Omnes Education">
                        </div>
                        <div class="form-group">
                            <label>Période :</label>
                            <input type="text" name="experience_periode[]" placeholder="2019 - Présent">
                        </div>
                        <div class="form-group">
                            <label>Description :</label>
                            <textarea name="experience_description[]" placeholder="Accompagnement personnalisé des étudiants..."></textarea>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="addExperience()" style="margin-bottom: 2rem; background: #3b82f6; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px;">➕ Ajouter Expérience</button>

                <h3>📜 Certifications</h3>
                <div id="certifications-container">
                    <div class="certification-item">
                        <div class="form-group">
                            <label>Nom de la certification :</label>
                            <input type="text" name="certification_nom[]" placeholder="Préparateur Physique FSCF">
                        </div>
                        <div class="form-group">
                            <label>Organisme :</label>
                            <input type="text" name="certification_organisme[]" placeholder="FSCF">
                        </div>
                        <div class="form-group">
                            <label>Date d'obtention :</label>
                            <input type="text" name="certification_date[]" placeholder="2018">
                        </div>
                    </div>
                </div>
                <button type="button" onclick="addCertification()" style="margin-bottom: 2rem; background: #8b5cf6; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px;">➕ Ajouter Certification</button>

                <h3>⭐ Spécialités</h3>
                <div class="form-group">
                    <textarea id="specialites-text" placeholder="Musculation & Force, Préparation physique, Réhabilitation sportive (une par ligne)"></textarea>
                </div>

                <button type="submit" class="submit-btn">💾 Générer CV XML</button>
            </form>
        </div>
    </div>

    <script>
        function openAddCoachModal() {
            document.getElementById('addCoachModal').style.display = 'block';
        }

        function closeAddCoachModal() {
            document.getElementById('addCoachModal').style.display = 'none';
            document.getElementById('addCoachForm').reset();
        }
        function openCVModal(coachId, coachName) {
            document.getElementById('cv-coach-id').value = coachId;
            document.getElementById('cv-coach-name').textContent = coachName;
            document.getElementById('cvModal').style.display = 'block';
        }

        function closeCVModal() {
            document.getElementById('cvModal').style.display = 'none';
            document.getElementById('cvForm').reset();
        }
        window.onclick = function(event) {
            const addModal = document.getElementById('addCoachModal');
            const cvModal = document.getElementById('cvModal');
            
            if (event.target === addModal) {
                closeAddCoachModal();
            }
            if (event.target === cvModal) {
                closeCVModal();
            }
        }
        function addFormation() {
            const container = document.getElementById('formations-container');
            const div = document.createElement('div');
            div.className = 'formation-item';
            div.innerHTML = `
                <div class="form-group">
                    <label>Titre de la formation :</label>
                    <input type="text" name="formation_titre[]">
                </div>
                <div class="form-group">
                    <label>Établissement :</label>
                    <input type="text" name="formation_etablissement[]">
                </div>
                <div class="form-group">
                    <label>Année :</label>
                    <input type="text" name="formation_annee[]">
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="background: #ef4444; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 3px; margin-bottom: 1rem;">❌ Supprimer</button>
            `;
            container.appendChild(div);
        }

        function addExperience() {
            const container = document.getElementById('experiences-container');
            const div = document.createElement('div');
            div.className = 'experience-item';
            div.innerHTML = `
                <div class="form-group">
                    <label>Poste :</label>
                    <input type="text" name="experience_poste[]">
                </div>
                <div class="form-group">
                    <label>Entreprise :</label>
                    <input type="text" name="experience_entreprise[]">
                </div>
                <div class="form-group">
                    <label>Période :</label>
                    <input type="text" name="experience_periode[]">
                </div>
                <div class="form-group">
                    <label>Description :</label>
                    <textarea name="experience_description[]"></textarea>
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="background: #ef4444; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 3px; margin-bottom: 1rem;">❌ Supprimer</button>
            `;
            container.appendChild(div);
        }

        function addCertification() {
            const container = document.getElementById('certifications-container');
            const div = document.createElement('div');
            div.className = 'certification-item';
            div.innerHTML = `
                <div class="form-group">
                    <label>Nom de la certification :</label>
                    <input type="text" name="certification_nom[]">
                </div>
                <div class="form-group">
                    <label>Organisme :</label>
                    <input type="text" name="certification_organisme[]">
                </div>
                <div class="form-group">
                    <label>Date d'obtention :</label>
                    <input type="text" name="certification_date[]">
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="background: #ef4444; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 3px; margin-bottom: 1rem;">❌ Supprimer</button>
            `;
            container.appendChild(div);
        }
        document.getElementById('addCoachForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_coach');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.result === 'SUCCESS') {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Erreur de connexion: ' + error);
            });
        });
        document.getElementById('cvForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'create_cv_xml');
            formData.append('coach_id', document.getElementById('cv-coach-id').value);
            const formations = [];
            const formationItems = document.querySelectorAll('.formation-item');
            formationItems.forEach(item => {
                const titre = item.querySelector('input[name="formation_titre[]"]').value;
                const etablissement = item.querySelector('input[name="formation_etablissement[]"]').value;
                const annee = item.querySelector('input[name="formation_annee[]"]').value;
                
                if (titre || etablissement || annee) {
                    formations.push({titre, etablissement, annee});
                }
            });
            formData.append('formations', JSON.stringify(formations));
            const experiences = [];
            const experienceItems = document.querySelectorAll('.experience-item');
            experienceItems.forEach(item => {
                const poste = item.querySelector('input[name="experience_poste[]"]').value;
                const entreprise = item.querySelector('input[name="experience_entreprise[]"]').value;
                const periode = item.querySelector('input[name="experience_periode[]"]').value;
                const description = item.querySelector('textarea[name="experience_description[]"]').value;
                
                if (poste || entreprise || periode) {
                    experiences.push({poste, entreprise, periode, description});
                }
            });
            formData.append('experiences', JSON.stringify(experiences));
            const certifications = [];
            const certificationItems = document.querySelectorAll('.certification-item');
            certificationItems.forEach(item => {
                const nom = item.querySelector('input[name="certification_nom[]"]').value;
                const organisme = item.querySelector('input[name="certification_organisme[]"]').value;
                const date = item.querySelector('input[name="certification_date[]"]').value;
                
                if (nom || organisme || date) {
                    certifications.push({nom, organisme, date});
                }
            });
            formData.append('certifications', JSON.stringify(certifications));
            const specialitesText = document.getElementById('specialites-text').value;
            const specialites = specialitesText.split('\n').filter(s => s.trim());
            formData.append('specialites', JSON.stringify(specialites));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Fichier CV XML créé avec succès !');
                    closeCVModal();
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Erreur: ' + error);
            });
        });
        function deleteCoach(coachId, coachName) {
            if (confirm('⚠️ Êtes-vous sûr de vouloir supprimer le coach ' + coachName + ' ?\n\nCette action est irréversible et annulera tous ses créneaux futurs.')) {
                const formData = new FormData();
                formData.append('action', 'delete_coach');
                formData.append('coach_id', coachId);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.result === 'SUCCESS') {
                        alert('✅ ' + data.message);
                        location.reload();
                    } else {
                        alert('❌ ' + data.message);
                    }
                })
                .catch(error => {
                    alert('❌ Erreur: ' + error);
                });
            }
        }
        function editCoach(coachId) {
            alert('🚧 Fonction de modification en cours de développement...');
        }

        function exportData() {
            alert('📊 Fonction d\'export en cours de développement...');
        }

        function viewReports() {
            alert('📈 Fonction de rapports en cours de développement...');
        }
    </script>
</body>
</html>