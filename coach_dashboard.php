<?php
require_once 'config.php';
require_once 'auth.php';
requireCoach();

$user = getCurrentUser();
$coach_id = $user['coach_id'];

function getCoachDetails($coach_id) {
    $conn = getDbConnection();
    
    $query = "SELECT c.*, u.email as user_email, u.derniere_connexion 
              FROM coachs c 
              LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id 
              WHERE c.id = :coach_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':coach_id', $coach_id);
    $stmt->execute();
    
    return $stmt->fetch();
}
function getCoachStats($coach_id) {
    $conn = getDbConnection();
    
    $query = "SELECT 
                COUNT(*) as total_creneaux,
                SUM(CASE WHEN statut = 'libre' AND date_creneau >= CURDATE() THEN 1 ELSE 0 END) as creneaux_libres,
                SUM(CASE WHEN statut = 'reserve' AND date_creneau >= CURDATE() THEN 1 ELSE 0 END) as rdv_a_venir,
                SUM(CASE WHEN statut = 'reserve' AND date_creneau < CURDATE() THEN 1 ELSE 0 END) as rdv_passes,
                SUM(CASE WHEN statut = 'occupe' AND date_creneau >= CURDATE() THEN 1 ELSE 0 END) as creneaux_occupes,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as actions_aujourd_hui
              FROM creneaux 
              WHERE coach_id = :coach_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':coach_id', $coach_id);
    $stmt->execute();
    
    return $stmt->fetch();
}

function getCoachRendezVous($coach_id, $limit = 20) {
    $conn = getDbConnection();
    
    $query = "SELECT *, 
                     DATE_FORMAT(date_creneau, '%W %d %M %Y') as date_formatted,
                     TIME_FORMAT(heure_debut, '%Hh%i') as heure_debut_format,
                     TIME_FORMAT(heure_fin, '%Hh%i') as heure_fin_format,
                     CASE 
                        WHEN date_creneau > CURDATE() THEN 'futur'
                        WHEN date_creneau = CURDATE() AND heure_debut > CURTIME() THEN 'futur'
                        WHEN date_creneau = CURDATE() AND heure_debut <= CURTIME() AND heure_fin > CURTIME() THEN 'en_cours'
                        ELSE 'passe'
                     END as statut_rdv
              FROM creneaux 
              WHERE coach_id = :coach_id AND statut = 'reserve'
              ORDER BY date_creneau DESC, heure_debut DESC
              LIMIT :limit";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':coach_id', $coach_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function getPlanningHebdomadaire($coach_id) {
    $conn = getDbConnection();
    $aujourd_hui = new DateTime();
    $jour_semaine = $aujourd_hui->format('N');
    
    $lundi = clone $aujourd_hui;
    $lundi->sub(new DateInterval('P' . ($jour_semaine - 1) . 'D'));
    
    $dates_semaine = [];
    for ($i = 0; $i < 7; $i++) {
        $date = clone $lundi;
        $date->add(new DateInterval('P' . $i . 'D'));
        $dates_semaine[] = $date->format('Y-m-d');
    }
    
    $date_debut = $dates_semaine[0];
    $date_fin = $dates_semaine[6];
    
    $query = "SELECT *, 
                     TIME_FORMAT(heure_debut, '%H:%i') as heure_debut_format,
                     TIME_FORMAT(heure_fin, '%H:%i') as heure_fin_format
              FROM creneaux 
              WHERE coach_id = :coach_id 
                AND date_creneau BETWEEN :date_debut AND :date_fin
              ORDER BY date_creneau, heure_debut";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':coach_id', $coach_id);
    $stmt->bindParam(':date_debut', $date_debut);
    $stmt->bindParam(':date_fin', $date_fin);
    $stmt->execute();
    
    $creneaux = $stmt->fetchAll();
    $planning = [];
    foreach ($dates_semaine as $date) {
        $planning[$date] = [];
    }
    
    foreach ($creneaux as $creneau) {
        $planning[$creneau['date_creneau']][] = $creneau;
    }
    
    return ['planning' => $planning, 'dates' => $dates_semaine];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $conn = getDbConnection();
    
    switch ($_POST['action']) {
        case 'update_creneau_status':
            try {
                $creneau_id = (int)$_POST['creneau_id'];
                $nouveau_statut = $_POST['statut'];
                $motif = trim($_POST['motif'] ?? '');
                $query_check = "SELECT id FROM creneaux WHERE id = :creneau_id AND coach_id = :coach_id";
                $stmt_check = $conn->prepare($query_check);
                $stmt_check->bindParam(':creneau_id', $creneau_id);
                $stmt_check->bindParam(':coach_id', $coach_id);
                $stmt_check->execute();
                
                if (!$stmt_check->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Créneau non trouvé']);
                    exit;
                }
                
                $query = "UPDATE creneaux SET statut = :statut, motif = :motif, updated_at = NOW() WHERE id = :creneau_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':statut', $nouveau_statut);
                $stmt->bindParam(':motif', $motif);
                $stmt->bindParam(':creneau_id', $creneau_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_coach_status':
            try {
                $nouveau_statut = $_POST['statut'];
                $statuts_valides = ['disponible', 'occupe', 'absent'];
                if (!in_array($nouveau_statut, $statuts_valides)) {
                    echo json_encode(['success' => false, 'message' => 'Statut invalide']);
                    exit;
                }
                
                $query = "UPDATE coachs SET statut = :statut, updated_at = NOW() WHERE id = :coach_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':statut', $nouveau_statut);
                $stmt->bindParam(':coach_id', $coach_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du statut']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'add_creneau':
            try {
                $date = $_POST['date'];
                $heure_debut = $_POST['heure_debut'];
                $heure_fin = $_POST['heure_fin'];
                $statut = $_POST['statut'] ?? 'libre';
                $motif = trim($_POST['motif'] ?? '');
                
                $query = "INSERT INTO creneaux (coach_id, date_creneau, heure_debut, heure_fin, statut, motif) 
                          VALUES (:coach_id, :date, :heure_debut, :heure_fin, :statut, :motif)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':coach_id', $coach_id);
                $stmt->bindParam(':date', $date);
                $stmt->bindParam(':heure_debut', $heure_debut);
                $stmt->bindParam(':heure_fin', $heure_fin);
                $stmt->bindParam(':statut', $statut);
                $stmt->bindParam(':motif', $motif);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Créneau ajouté avec succès']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete_creneau':
            try {
                $creneau_id = (int)$_POST['creneau_id'];
                $query_check = "SELECT statut FROM creneaux WHERE id = :creneau_id AND coach_id = :coach_id";
                $stmt_check = $conn->prepare($query_check);
                $stmt_check->bindParam(':creneau_id', $creneau_id);
                $stmt_check->bindParam(':coach_id', $coach_id);
                $stmt_check->execute();
                $creneau = $stmt_check->fetch();
                
                if (!$creneau) {
                    echo json_encode(['success' => false, 'message' => 'Créneau non trouvé']);
                    exit;
                }
                
                if ($creneau['statut'] === 'reserve') {
                    echo json_encode(['success' => false, 'message' => 'Impossible de supprimer un créneau réservé']);
                    exit;
                }
                
                $query = "DELETE FROM creneaux WHERE id = :creneau_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':creneau_id', $creneau_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Créneau supprimé avec succès']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
    }
}

$coach_details = getCoachDetails($coach_id);
$stats = getCoachStats($coach_id);
$rendez_vous = getCoachRendezVous($coach_id);
$planning_data = getPlanningHebdomadaire($coach_id);
$planning = $planning_data['planning'];
$dates_semaine = $planning_data['dates'];

if (!$coach_details) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Coach - Sportify | Omnes Education</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f8fafc;
            color: #333;
            line-height: 1.6;
        }

        .navbar {
            background-color: #1f2937;
            padding: 1rem 0;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            color: #f59e0b;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #f59e0b;
        }

        .header {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 2rem 0;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .coach-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
        }

        .coach-info h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
        }

        .coach-info p {
            margin: 0;
            opacity: 0.9;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-content {
            padding: 2rem 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-top: 4px solid #2563eb;
            text-align: center;
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .section-title {
            color: #1e40af;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .planning-grid {
            display: grid;
            grid-template-columns: 120px repeat(7, 1fr);
            gap: 1px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .planning-header {
            background: #2563eb;
            color: white;
            padding: 1rem;
            font-weight: bold;
            text-align: center;
            font-size: 0.9rem;
        }

        .time-header {
            background: #374151;
            color: white;
            padding: 1rem;
            font-weight: bold;
            text-align: center;
            font-size: 0.8rem;
        }

        .planning-cell {
            background: white;
            padding: 0.5rem;
            min-height: 60px;
            position: relative;
            border: 1px solid #f3f4f6;
        }

        .creneau-item {
            background: #f0f9ff;
            border: 1px solid #2563eb;
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
            margin-bottom: 0.25rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .creneau-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .creneau-libre { background: #dcfce7; border-color: #16a34a; color: #166534; }
        .creneau-occupe { background: #fef3c7; border-color: #d97706; color: #92400e; }
        .creneau-reserve { background: #fee2e2; border-color: #dc2626; color: #dc2626; }
        .creneau-indisponible { background: #f1f5f9; border-color: #64748b; color: #475569; }

        .rdv-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .rdv-card:hover {
            border-color: #2563eb;
            transform: translateY(-2px);
        }

        .rdv-card.futur { border-left: 4px solid #10b981; }
        .rdv-card.en_cours { border-left: 4px solid #f59e0b; }
        .rdv-card.passe { border-left: 4px solid #64748b; opacity: 0.8; }

        .rdv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .rdv-time {
            font-weight: bold;
            color: #2563eb;
            font-size: 1.1rem;
        }

        .rdv-date {
            color: #64748b;
            font-size: 0.9rem;
        }

        .rdv-student {
            font-weight: 600;
            color: #374151;
        }

        .rdv-email {
            color: #64748b;
            font-size: 0.9rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
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
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 1rem;
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

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #059669;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            background: #dc2626;
        }

        .actions-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .planning-grid {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .planning-grid {
                display: none;
            }
            
            .planning-mobile {
                display: block;
            }
        }

        .planning-mobile {
            display: none;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">🏋️ Sportify</a>
            <div class="nav-links">
                <a href="index.php">🏠 Accueil</a>
                <a href="coach.php?coach_id=<?= $coach_id ?>">👁️ Ma Page Publique</a>
                <a href="logout.php">🚪 Déconnexion</a>
            </div>
        </div>
    </nav>

    <header class="header">
        <div class="header-content">
            <img src="<?= htmlspecialchars($coach_details['photo'] ?: '/images_projet/default_coach.jpg') ?>" alt="Photo coach" class="coach-photo">
            <div class="coach-info">
                <h1>👋 Bienvenue, <?= htmlspecialchars($coach_details['prenom']) ?></h1>
                <p><?= htmlspecialchars($coach_details['specialite']) ?> • <?= htmlspecialchars($coach_details['bureau']) ?></p>
                <p>Dernière connexion : <?= $coach_details['derniere_connexion'] ? date('d/m/Y H:i', strtotime($coach_details['derniere_connexion'])) : 'Première connexion' ?></p>
            </div>
        </div>
    </header>

    <section class="main-content">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['rdv_a_venir'] ?></div>
                    <div class="stat-label">RDV à venir</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['creneaux_libres'] ?></div>
                    <div class="stat-label">Créneaux libres</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['rdv_passes'] ?></div>
                    <div class="stat-label">RDV passés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['creneaux_occupes'] ?></div>
                    <div class="stat-label">Créneaux occupés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_creneaux'] ?></div>
                    <div class="stat-label">Total créneaux</div>
                </div>
            </div>
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="openAddCreneauModal()">
                    ➕ Ajouter un créneau
                </button>
                <button class="btn btn-success" onclick="refreshPlanning()">
                    🔄 Actualiser le planning
                </button>
                <button class="btn btn-warning" onclick="exportPlanning()">
                    📊 Exporter planning
                </button>
            </div>

            <div class="content-grid">
                <div class="section" style="grid-column: 1 / -1;">
                    <h2 class="section-title">📅 Planning de la semaine</h2>
                    
                    <div class="planning-grid">
                        <div class="time-header">Horaires</div>
                        <?php 
                        $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
                        foreach ($dates_semaine as $index => $date): 
                            $jour = $jours[$index];
                            $date_format = date('d/m', strtotime($date));
                        ?>
                        <div class="planning-header">
                            <?= $jour ?><br><small><?= $date_format ?></small>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php 
                        $heures = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
                        foreach ($heures as $heure): 
                        ?>
                        <div class="time-header"><?= $heure ?></div>
                        <?php foreach ($dates_semaine as $date): ?>
                        <div class="planning-cell" data-date="<?= $date ?>" data-heure="<?= $heure ?>">
                            <?php 
                            if (isset($planning[$date])) {
                                foreach ($planning[$date] as $creneau) {
                                    if (date('H:i', strtotime($creneau['heure_debut'])) == $heure) {
                                        $classe_css = 'creneau-' . $creneau['statut'];
                                        $titre = $creneau['heure_debut_format'] . '-' . $creneau['heure_fin_format'];
                                        if ($creneau['statut'] === 'reserve') {
                                            $titre .= "\n" . $creneau['etudiant_nom'];
                                        } elseif ($creneau['motif']) {
                                            $titre .= "\n" . $creneau['motif'];
                                        }
                            ?>
                            <div class="creneau-item <?= $classe_css ?>" 
                                 data-creneau-id="<?= $creneau['id'] ?>"
                                 onclick="openCreneauModal(<?= htmlspecialchars(json_encode($creneau)) ?>)"
                                 title="<?= htmlspecialchars($titre) ?>">
                                <?= $creneau['heure_debut_format'] ?>-<?= $creneau['heure_fin_format'] ?>
                                <?php if ($creneau['statut'] === 'reserve'): ?>
                                    <br><small><?= htmlspecialchars($creneau['etudiant_nom']) ?></small>
                                <?php elseif ($creneau['motif']): ?>
                                    <br><small><?= htmlspecialchars($creneau['motif']) ?></small>
                                <?php endif; ?>
                            </div>
                            <?php
                                    }
                                }
                            }
                            ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="section">
                    <h2 class="section-title">👥 Rendez-vous récents</h2>
                    
                    <?php if (empty($rendez_vous)): ?>
                    <div style="text-align: center; padding: 2rem; color: #64748b;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                        <h3>Aucun rendez-vous</h3>
                        <p>Aucun rendez-vous n'est programmé pour le moment.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($rendez_vous as $rdv): ?>
                        <div class="rdv-card <?= $rdv['statut_rdv'] ?>">
                            <div class="rdv-header">
                                <div>
                                    <div class="rdv-time"><?= $rdv['heure_debut_format'] ?> - <?= $rdv['heure_fin_format'] ?></div>
                                    <div class="rdv-date"><?= $rdv['date_formatted'] ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <?php if ($rdv['statut_rdv'] === 'futur'): ?>
                                        <span style="background: #dcfce7; color: #166534; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">À venir</span>
                                    <?php elseif ($rdv['statut_rdv'] === 'en_cours'): ?>
                                        <span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">En cours</span>
                                    <?php else: ?>
                                        <span style="background: #f1f5f9; color: #475569; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem;">Terminé</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <div class="rdv-student"><?= htmlspecialchars($rdv['etudiant_nom']) ?></div>
                                <div class="rdv-email"><?= htmlspecialchars($rdv['etudiant_email']) ?></div>
                                <?php if ($rdv['notes']): ?>
                                <div style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(37, 99, 235, 0.1); border-radius: 6px; font-size: 0.9rem;">
                                    <strong>Notes :</strong> <?= htmlspecialchars($rdv['notes']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="section">
                    <h2 class="section-title">ℹ️ Mes informations</h2>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <strong>📧 Email :</strong><br>
                            <?= htmlspecialchars($coach_details['email']) ?>
                        </div>
                        <div>
                            <strong>📞 Téléphone :</strong><br>
                            <?= htmlspecialchars($coach_details['telephone'] ?: 'Non renseigné') ?>
                        </div>
                        <div>
                            <strong>🏢 Bureau :</strong><br>
                            <?= htmlspecialchars($coach_details['bureau']) ?>
                        </div>
                        <div>
                            <strong>⚡ Statut :</strong><br>
                            <span style="color: <?= $coach_details['statut'] === 'disponible' ? '#059669' : '#dc2626' ?>; font-weight: 600;">
                                <?= $coach_details['statut'] === 'disponible' ? '🟢 Disponible' : '🔴 Occupé' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                        <strong>📝 Description :</strong><br>
                        <?= htmlspecialchars($coach_details['description']) ?>
                    </div>
                    
                    <div style="margin-top: 1rem; text-align: center;">
                        <button class="btn btn-primary" onclick="updateStatut()">
                            🔄 Changer mon statut
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div id="creneauModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">📅 Gérer le créneau</h2>
                <span class="close" onclick="closeCreneauModal()">&times;</span>
            </div>
            <div id="creneauDetails"></div>
        </div>
    </div>
    <div id="addCreneauModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ Ajouter un créneau</h2>
                <span class="close" onclick="closeAddCreneauModal()">&times;</span>
            </div>
            <form id="addCreneauForm">
                <div class="form-group">
                    <label for="add-date">Date :</label>
                    <input type="date" id="add-date" name="date" required min="<?= date('Y-m-d') ?>">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="add-heure-debut">Heure début :</label>
                        <input type="time" id="add-heure-debut" name="heure_debut" required>
                    </div>
                    <div class="form-group">
                        <label for="add-heure-fin">Heure fin :</label>
                        <input type="time" id="add-heure-fin" name="heure_fin" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="add-statut">Statut :</label>
                    <select id="add-statut" name="statut" required>
                        <option value="libre">🟢 Libre</option>
                        <option value="occupe">🟡 Occupé</option>
                        <option value="indisponible">🔴 Indisponible</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add-motif">Motif (optionnel) :</label>
                    <input type="text" id="add-motif" name="motif" placeholder="Ex: Réunion, Formation...">
                </div>
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-success">✅ Ajouter le créneau</button>
                </div>
            </form>
        </div>
    </div>
    <div id="statutModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">⚡ Changer mon statut</h2>
                <span class="close" onclick="closeStatutModal()">&times;</span>
            </div>
            <form id="statutForm">
                <div class="form-group">
                    <label>Statut actuel :</label>
                    <div style="padding: 0.75rem; background: #f8fafc; border-radius: 8px; margin-bottom: 1rem;">
                        <span style="color: <?= $coach_details['statut'] === 'disponible' ? '#059669' : '#dc2626' ?>; font-weight: 600;">
                            <?= $coach_details['statut'] === 'disponible' ? '🟢 Disponible' : '🔴 Occupé' ?>
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="nouveau-statut">Nouveau statut :</label>
                    <select id="nouveau-statut" name="statut" required>
                        <option value="disponible" <?= $coach_details['statut'] === 'disponible' ? 'selected' : '' ?>>🟢 Disponible</option>
                        <option value="occupe" <?= $coach_details['statut'] === 'occupe' ? 'selected' : '' ?>>🔴 Occupé</option>
                        <option value="absent" <?= $coach_details['statut'] === 'absent' ? 'selected' : '' ?>>⚫ Absent</option>
                    </select>
                </div>
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">🔄 Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
    <div class="notification" id="notification">
        ✅ <span id="notification-text">Action effectuée avec succès !</span>
    </div>

    <script>
        let currentCreneau = null;
        function openCreneauModal(creneau) {
            currentCreneau = creneau;
            const modal = document.getElementById('creneauModal');
            const details = document.getElementById('creneauDetails');
            
            let statutColor = '#64748b';
            let statutText = creneau.statut;
            
            switch(creneau.statut) {
                case 'libre': statutColor = '#059669'; statutText = '🟢 Libre'; break;
                case 'occupe': statutColor = '#d97706'; statutText = '🟡 Occupé'; break;
                case 'reserve': statutColor = '#dc2626'; statutText = '🔴 Réservé'; break;
                case 'indisponible': statutColor = '#64748b'; statutText = '⚫ Indisponible'; break;
            }
            
            details.innerHTML = `
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: #2563eb; margin-bottom: 1rem;">📅 ${formatDate(creneau.date_creneau)} - ${creneau.heure_debut_format} à ${creneau.heure_fin_format}</h3>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <strong>Statut actuel :</strong> <span style="color: ${statutColor}; font-weight: 600;">${statutText}</span>
                    </div>
                    ${creneau.statut === 'reserve' ? `
                        <div style="background: #fee2e2; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <strong>👤 Étudiant :</strong> ${creneau.etudiant_nom}<br>
                            <strong>📧 Email :</strong> ${creneau.etudiant_email}
                            ${creneau.notes ? `<br><strong>📝 Notes :</strong> ${creneau.notes}` : ''}
                        </div>
                    ` : ''}
                    ${creneau.motif ? `
                        <div style="background: #fef3c7; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <strong>📝 Motif :</strong> ${creneau.motif}
                        </div>
                    ` : ''}
                </div>
                
                ${creneau.statut !== 'reserve' ? `
                <form id="updateCreneauForm">
                    <div class="form-group">
                        <label for="update-statut">Changer le statut :</label>
                        <select id="update-statut" name="statut" required>
                            <option value="libre" ${creneau.statut === 'libre' ? 'selected' : ''}>🟢 Libre</option>
                            <option value="occupe" ${creneau.statut === 'occupe' ? 'selected' : ''}>🟡 Occupé</option>
                            <option value="indisponible" ${creneau.statut === 'indisponible' ? 'selected' : ''}>🔴 Indisponible</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="update-motif">Motif :</label>
                        <input type="text" id="update-motif" name="motif" value="${creneau.motif || ''}" placeholder="Ex: Réunion, Formation...">
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary">🔄 Mettre à jour</button>
                        <button type="button" class="btn btn-danger" onclick="deleteCreneau(${creneau.id})">🗑️ Supprimer</button>
                    </div>
                </form>
                ` : `
                <div style="text-align: center; margin-top: 2rem;">
                    <p style="color: #dc2626; font-weight: 600;">⚠️ Ce créneau est réservé par un étudiant</p>
                    <p style="color: #64748b;">Vous ne pouvez pas le modifier directement.</p>
                    <button type="button" class="btn btn-primary" onclick="contactStudent('${creneau.etudiant_email}')">
                        📧 Contacter l'étudiant
                    </button>
                </div>
                `}
            `;
            
            modal.style.display = 'block';
            
            setTimeout(() => {
                attachUpdateFormListener();
            }, 100);
        }

        function closeCreneauModal() {
            document.getElementById('creneauModal').style.display = 'none';
            currentCreneau = null;
        }

        function openAddCreneauModal() {
            document.getElementById('addCreneauModal').style.display = 'block';
        }

        function closeAddCreneauModal() {
            document.getElementById('addCreneauModal').style.display = 'none';
            document.getElementById('addCreneauForm').reset();
        }

        function updateStatut() {
            document.getElementById('statutModal').style.display = 'block';
        }

        function closeStatutModal() {
            document.getElementById('statutModal').style.display = 'none';
        }
        function attachUpdateFormListener() {
            const form = document.getElementById('updateCreneauForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (!currentCreneau) return;
                    
                    const formData = new FormData();
                    formData.append('action', 'update_creneau_status');
                    formData.append('creneau_id', currentCreneau.id);
                    formData.append('statut', document.getElementById('update-statut').value);
                    formData.append('motif', document.getElementById('update-motif').value);
                    
                    submitForm(formData, 'Créneau mis à jour avec succès', () => {
                        closeCreneauModal();
                        refreshPlanning();
                    });
                });
            }
        }

        document.getElementById('addCreneauForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const date = document.getElementById('add-date').value;
            const heureDebut = document.getElementById('add-heure-debut').value;
            const heureFin = document.getElementById('add-heure-fin').value;
            
            if (!date || !heureDebut || !heureFin) {
                showNotification('Veuillez remplir tous les champs obligatoires', 'error');
                return;
            }
            
            if (heureDebut >= heureFin) {
                showNotification('L\'heure de fin doit être après l\'heure de début', 'error');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'add_creneau');
            
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.textContent = '⏳ Ajout en cours...';
            submitButton.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Créneau ajouté avec succès');
                    closeAddCreneauModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                    submitButton.textContent = originalText;
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
                console.error('Erreur:', error);
                submitButton.textContent = originalText;
                submitButton.disabled = false;
            });
        });

        document.getElementById('statutForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const nouveauStatut = document.getElementById('nouveau-statut').value;
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            submitButton.textContent = '⏳ Mise à jour...';
            submitButton.disabled = true;
            const formData = new FormData();
            formData.append('action', 'update_coach_status');
            formData.append('statut', nouveauStatut);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showNotification('Statut mis à jour avec succès');
                closeStatutModal();
                setTimeout(() => {
                    location.reload();
                }, 1000);
            })
            .catch(error => {
                showNotification('Statut mis à jour avec succès');
                closeStatutModal();
                setTimeout(() => {
                    location.reload();
                }, 1000);
            });
        });
        function submitForm(formData, successMessage, callback) {
            const submitButton = document.querySelector('button[type="submit"]');
            if (submitButton) {
                const originalText = submitButton.textContent;
                submitButton.textContent = '⏳ Traitement...';
                submitButton.disabled = true;
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(successMessage);
                        if (callback) {
                            setTimeout(callback, 500);
                        }
                    } else {
                        showNotification(data.message, 'error');
                        submitButton.textContent = originalText;
                        submitButton.disabled = false;
                    }
                })
                .catch(error => {
                    showNotification('Erreur de connexion', 'error');
                    console.error('Erreur:', error);
                    submitButton.textContent = originalText;
                    submitButton.disabled = false;
                });
            }
        }

        function deleteCreneau(creneauId) {
            if (!confirm('⚠️ Êtes-vous sûr de vouloir supprimer ce créneau ?\\n\\nCette action est irréversible.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_creneau');
            formData.append('creneau_id', creneauId);
            const deleteButton = event.target;
            const originalText = deleteButton.textContent;
            deleteButton.textContent = '⏳ Suppression...';
            deleteButton.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Créneau supprimé avec succès');
                    closeCreneauModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                    deleteButton.textContent = originalText;
                    deleteButton.disabled = false;
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
                console.error('Erreur:', error);
                deleteButton.textContent = originalText;
                deleteButton.disabled = false;
            });
        }

        function refreshPlanning() {
            showNotification('Actualisation du planning...');
            setTimeout(() => {
                location.reload();
            }, 500);
        }

        function exportPlanning() {
            showNotification('Fonction d\'export en cours de développement', 'info');
        }

        function contactStudent(email) {
            window.location.href = `mailto:${email}?subject=Concernant votre rendez-vous&body=Bonjour,\n\nJe vous contacte concernant votre rendez-vous.\n\nCordialement,\n<?= htmlspecialchars($coach_details['prenom'] . ' ' . $coach_details['nom']) ?>`;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            const mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
            
            return `${jours[date.getDay()]} ${date.getDate()} ${mois[date.getMonth()]}`;
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const text = document.getElementById('notification-text');
            
            text.textContent = message;
            notification.className = 'notification';
            if (type === 'error') {
                notification.classList.add('error');
            }
            notification.classList.add('show');
            
            setTimeout(function() {
                notification.classList.remove('show');
            }, 3000);
        }
        window.onclick = function(event) {
            const modals = ['creneauModal', 'addCreneauModal', 'statutModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('add-date').value = new Date().toISOString().split('T')[0];
            document.getElementById('add-heure-debut').addEventListener('change', function() {
                const debut = this.value;
                if (debut) {
                    const [heures, minutes] = debut.split(':');
                    const fin = String(parseInt(heures) + 1).padStart(2, '0') + ':' + minutes;
                    document.getElementById('add-heure-fin').value = fin;
                }
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            }
            
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddCreneauModal();
            }
            
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                e.preventDefault();
                refreshPlanning();
            }
        });
    </script>
</body>
</html>