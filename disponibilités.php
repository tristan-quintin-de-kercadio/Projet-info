<?php
require_once 'config.php';
$coach_id = isset($_GET['coach_id']) ? (int)$_GET['coach_id'] : 1;
function getWeekDates() {
    $today = new DateTime();
    $dayOfWeek = $today->format('N');
    $monday = clone $today;
    $monday->sub(new DateInterval('P' . ($dayOfWeek - 1) . 'D'));
    
    $dates = [];
    for ($i = 0; $i < 7; $i++) {
        $date = clone $monday;
        $date->add(new DateInterval('P' . $i . 'D'));
        $dates[] = $date->format('Y-m-d');
    }
    
    return $dates;
}
$dates_semaine = getWeekDates();
$date_debut = $dates_semaine[0];
$date_fin = $dates_semaine[6]; 

$coach = getCoachInfo($coach_id);
if (!$coach) {
    die("Coach non trouvé");
}
function generateFullWeekSchedule($coach_id, $dates_semaine) {
    $conn = getDbConnection();
    $creneaux_types = [
        '08:00:00' => '09:00:00',
        '09:00:00' => '10:00:00', 
        '10:00:00' => '11:00:00',
        '11:00:00' => '12:00:00',
        '14:00:00' => '15:00:00',
        '15:00:00' => '16:00:00',
        '16:00:00' => '17:00:00',
        '17:00:00' => '18:00:00'
    ];
    
    $all_creneaux = [];
    
    foreach ($dates_semaine as $date) {
        $query = "SELECT * FROM creneaux WHERE coach_id = :coach_id AND date_creneau = :date ORDER BY heure_debut";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':coach_id', $coach_id);
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        $existing_creneaux = $stmt->fetchAll();
        if (empty($existing_creneaux)) {
            if (date('N', strtotime($date)) == 7) { 
                foreach (['09:00:00', '10:00:00', '11:00:00', '14:00:00', '15:00:00', '16:00:00'] as $heure_debut) {
                    $heure_fin = $creneaux_types[$heure_debut] ?? date('H:i:s', strtotime($heure_debut . ' +1 hour'));
                    $all_creneaux[$date][] = [
                        'id' => 'virtual_' . $date . '_' . $heure_debut,
                        'date_creneau' => $date,
                        'heure_debut' => $heure_debut,
                        'heure_fin' => $heure_fin,
                        'statut' => 'libre',
                        'motif' => null,
                        'etudiant_nom' => null,
                        'creneau_display' => date('H\h', strtotime($heure_debut)) . '-' . date('H\h', strtotime($heure_fin))
                    ];
                }
            } else {
                foreach ($creneaux_types as $heure_debut => $heure_fin) {
                    $all_creneaux[$date][] = [
                        'id' => 'virtual_' . $date . '_' . $heure_debut,
                        'date_creneau' => $date,
                        'heure_debut' => $heure_debut,
                        'heure_fin' => $heure_fin,
                        'statut' => 'libre',
                        'motif' => null,
                        'etudiant_nom' => null,
                        'creneau_display' => date('H\h', strtotime($heure_debut)) . '-' . date('H\h', strtotime($heure_fin))
                    ];
                }
            }
        } else {
            foreach ($existing_creneaux as $creneau) {
                $creneau['creneau_display'] = date('H\h', strtotime($creneau['heure_debut'])) . '-' . date('H\h', strtotime($creneau['heure_fin']));
                $all_creneaux[$date][] = $creneau;
            }
        }
    }
    
    return $all_creneaux;
}

$planning = generateFullWeekSchedule($coach_id, $dates_semaine);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'reserver') {
        $creneau_id = $_POST['creneau_id'];
        $etudiant_nom = $_POST['etudiant_nom'] ?? 'Étudiant Anonyme';
        $etudiant_email = $_POST['etudiant_email'] ?? '';
        if (strpos($creneau_id, 'virtual_') === 0) {
            $parts = explode('_', $creneau_id);
            $date = $parts[1];
            $heure = $parts[2];
            
            $conn = getDbConnection();
            $query = "INSERT INTO creneaux (coach_id, date_creneau, heure_debut, heure_fin, statut, etudiant_nom, etudiant_email) 
                      VALUES (:coach_id, :date, :heure_debut, :heure_fin, 'reserve', :etudiant_nom, :etudiant_email)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':coach_id', $coach_id);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':heure_debut', $heure);
            $stmt->bindParam(':heure_fin', date('H:i:s', strtotime($heure . ' +1 hour')));
            $stmt->bindParam(':etudiant_nom', $etudiant_nom);
            $stmt->bindParam(':etudiant_email', $etudiant_email);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Créneau réservé avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la réservation']);
            }
        } else {
            $result = reserverCreneau($creneau_id, $etudiant_nom, $etudiant_email);
            echo json_encode($result);
        }
        exit;
    }
}
function getNomJourFr($date) {
    $jours = [
        'Monday' => 'LUN',
        'Tuesday' => 'MAR', 
        'Wednesday' => 'MER',
        'Thursday' => 'JEU',
        'Friday' => 'VEN',
        'Saturday' => 'SAM',
        'Sunday' => 'DIM' 
    ];
    
    $jour_anglais = date('l', strtotime($date));
    return $jours[$jour_anglais] ?? 'N/A';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation Rendez-vous - Sportify | Omnes Education</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        .navbar {
            background-color: #1f2937;
            padding: 1rem 0;
        }

        .nav-container {
            max-width: 1200px;
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
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #f59e0b;
        }
        .page-header {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 2rem 0;
            text-align: center;
        }

        .page-header h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            margin-bottom: 0;
            opacity: 0.9;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-content {
            padding: 3rem 0;
        }
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-top: 4px solid #2563eb;
        }

        .card-title {
            color: #2563eb;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .day-column {
            text-align: center;
        }

        .day-header {
            background: #2563eb;
            color: white;
            padding: 0.8rem;
            border-radius: 10px 10px 0 0;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .time-slots {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 0 0 10px 10px;
            min-height: 300px;
        }

        .time-slot {
            padding: 0.5rem 0.3rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .time-slot:hover:not(.slot-busy):not(.slot-reserve):not(.slot-indisponible) {
            background: #f0f9ff;
        }

        .slot-free {
            background: #f0fdf4;
            color: #059669;
            font-weight: bold;
        }

        .slot-busy {
            background: #fef2f2;
            color: #dc2626;
            font-weight: bold;
            cursor: not-allowed;
        }

        .slot-reserve {
            background: #fef3c7;
            color: #d97706;
            font-weight: bold;
            cursor: not-allowed;
        }

        .slot-indisponible {
            background: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }

        .slot-selected {
            background: #f59e0b;
            color: white;
            font-weight: bold;
        }
        .action-section {
            text-align: center;
            margin-top: 2rem;
        }

        .selected-slot-info {
            background: #e0f2fe;
            border: 1px solid #0288d1;
            color: #01579b;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: none;
        }

        .confirm-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 0.5rem;
        }

        .confirm-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        }

        .confirm-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .cancel-btn {
            background: #6b7280;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 0.5rem;
            text-decoration: none;
            display: inline-block;
        }

        .cancel-btn:hover {
            background: #4b5563;
        }
        .legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .legend-free {
            background: #f0fdf4;
            color: #059669;
        }

        .legend-busy {
            background: #fef2f2;
            color: #dc2626;
        }

        .legend-reserve {
            background: #fef3c7;
            color: #d97706;
        }

        .legend-selected {
            background: #f59e0b;
            color: white;
        }

        .legend-indisponible {
            background: #f3f4f6;
            color: #6b7280;
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
            margin: 15% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            color: #2563eb;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #2563eb;
            outline: none;
        }
        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .legend {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
            
            .action-section {
                margin-top: 1rem;
            }
            
            .confirm-btn, .cancel-btn {
                display: block;
                width: 100%;
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">🏋️ Sportify</a>
            <div class="nav-links">
                <a href="index.php">🏠 Accueil</a>
                <a href="#" onclick="history.back()">⬅️ Retour</a>
            </div>
        </div>
    </nav>
    <section class="page-header">
        <div class="container">
            <h1>📅 Réserver un Rendez-vous</h1>
            <p>Choisissez un créneau libre pour prendre rendez-vous avec <?= htmlspecialchars($coach['prenom'] . ' ' . $coach['nom']) ?></p>
        </div>
    </section>
    <section class="main-content">
        <div class="container">
            <div class="info-card">
                <h3 class="card-title">📅 Disponibilités de la semaine (<?= date('d/m', strtotime($dates_semaine[0])) ?> au <?= date('d/m', strtotime($dates_semaine[6])) ?>)</h3>
                <p style="color: #666; margin-bottom: 1rem;">Cliquez sur un créneau libre pour le sélectionner</p>
                
                <div class="calendar-grid">
                    <?php foreach ($dates_semaine as $date): 
                        $nom_jour = getNomJourFr($date);
                        $jour_numero = date('d', strtotime($date));
                        $mois_numero = date('m', strtotime($date));
                        $creneaux_jour = $planning[$date] ?? [];
                        $est_dimanche = (date('N', strtotime($date)) == 7);
                    ?>
                    <div class="day-column">
                        <div class="day-header">
                            <?= $nom_jour ?><br>
                            <small><?= $jour_numero ?>/<?= $mois_numero ?></small>
                            <?php if ($est_dimanche): ?>
                                <br><small style="font-size: 0.7rem;">Repos</small>
                            <?php endif; ?>
                        </div>
                        <div class="time-slots">
                            <?php if ($est_dimanche): ?>
                                <div class="time-slot slot-indisponible" style="padding: 2rem 0.5rem; text-align: center;">
                                    <strong>Jour de repos</strong><br>
                                    <small>Aucun créneau disponible</small>
                                </div>
                            <?php elseif (empty($creneaux_jour)): ?>
                                <div class="time-slot slot-indisponible">Aucun créneau</div>
                            <?php else: ?>
                                <?php foreach ($creneaux_jour as $creneau): 
                                    $classe_css = '';
                                    $onclick = '';
                                    $contenu = $creneau['creneau_display'];
                                    
                                    switch ($creneau['statut']) {
                                        case 'libre':
                                            $classe_css = 'slot-free';
                                            $onclick = "selectSlot(this, '{$creneau['id']}', '{$creneau['creneau_display']}', '{$nom_jour} {$creneau['creneau_display']}')";
                                            break;
                                        case 'occupe':
                                            $classe_css = 'slot-busy';
                                            $contenu .= '<br><small>' . htmlspecialchars($creneau['motif']) . '</small>';
                                            break;
                                        case 'reserve':
                                            $classe_css = 'slot-reserve';
                                            $contenu .= '<br><small>Réservé</small>';
                                            break;
                                        case 'indisponible':
                                            $classe_css = 'slot-indisponible';
                                            $contenu .= '<br><small>' . htmlspecialchars($creneau['motif']) . '</small>';
                                            break;
                                    }
                                ?>
                                <div class="time-slot <?= $classe_css ?>" 
                                     <?= $onclick ? "onclick=\"$onclick\"" : '' ?>>
                                    <?= $contenu ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="legend">
                    <span class="legend-item legend-free">🟢 Libre</span>
                    <span class="legend-item legend-busy">🔴 Occupé</span>
                    <span class="legend-item legend-reserve">🟡 Réservé</span>
                    <span class="legend-item legend-selected">🔵 Sélectionné</span>
                    <span class="legend-item legend-indisponible">⚫ Indisponible</span>
                </div>
            </div>
            <div class="action-section">
                <div class="selected-slot-info" id="selected-slot">
                    ✅ <span>Créneau sélectionné : <strong id="selected-time"></strong></span>
                </div>
                
                <button class="confirm-btn" onclick="openReservationModal()" id="confirm-btn" disabled>
                    ✅ Confirmer le Rendez-vous
                </button>
                
                <a href="coach.php?coach_id=<?= $coach_id ?>" class="cancel-btn">
                    ❌ Annuler
                </a>
            </div>
        </div>
    </section>
    <div id="reservationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                📅 Confirmer votre réservation
            </div>
            <form id="reservationForm">
                <div class="form-group">
                    <label for="etudiant_nom">Votre nom complet :</label>
                    <input type="text" id="etudiant_nom" name="etudiant_nom" required>
                </div>
                <div class="form-group">
                    <label for="etudiant_email">Votre email :</label>
                    <input type="email" id="etudiant_email" name="etudiant_email" required>
                </div>
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="confirm-btn">Confirmer</button>
                    <button type="button" class="cancel-btn" onclick="closeReservationModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    <div class="notification" id="notification">
        ✅ <span id="notification-text">Action effectuée avec succès !</span>
    </div>

    <script>
        let selectedSlot = null;
        let selectedCreneauId = null;

        function selectSlot(element, creneauId, timeDisplay, fullDisplay) {
            document.querySelectorAll('.slot-selected').forEach(slot => {
                slot.classList.remove('slot-selected');
                slot.classList.add('slot-free');
            });
            element.classList.remove('slot-free');
            element.classList.add('slot-selected');
            
            selectedSlot = fullDisplay;
            selectedCreneauId = creneauId;

            document.getElementById('selected-slot').style.display = 'block';
            document.getElementById('selected-time').textContent = fullDisplay;

            document.getElementById('confirm-btn').disabled = false;
        }

        function openReservationModal() {
            if (selectedSlot && selectedCreneauId) {
                document.getElementById('reservationModal').style.display = 'block';
            }
        }
        function closeReservationModal() {
            document.getElementById('reservationModal').style.display = 'none';
        }
        document.getElementById('reservationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!selectedCreneauId) {
                showNotification('Erreur : Aucun créneau sélectionné', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'reserver');
            formData.append('creneau_id', selectedCreneauId);
            formData.append('etudiant_nom', document.getElementById('etudiant_nom').value);
            formData.append('etudiant_email', document.getElementById('etudiant_email').value);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeReservationModal();
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
                console.error('Erreur:', error);
            });
        });
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
            const modal = document.getElementById('reservationModal');
            if (event.target === modal) {
                closeReservationModal();
            }
        }
    </script>
</body>
</html>