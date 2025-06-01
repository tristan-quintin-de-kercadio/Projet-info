<?php
require_once 'config.php';
$coach_id = isset($_GET['coach_id']) ? (int)$_GET['coach_id'] : 1;
$coach = getCoachInfo($coach_id);
if (!$coach) {
    die("Coach non trouvé");
}
function getStatutAffichage($statut) {
    switch ($statut) {
        case 'disponible':
            return ['text' => '🟢 Disponible maintenant', 'class' => 'status-available'];
        case 'occupe':
            return ['text' => '🔴 Occupé actuellement', 'class' => 'status-busy'];
        case 'absent':
            return ['text' => '⚫ Absent aujourd\'hui', 'class' => 'status-busy'];
        default:
            return ['text' => '🟡 Statut inconnu', 'class' => 'status-busy'];
    }
}

$statut_info = getStatutAffichage($coach['statut']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Coach - Sportify | Omnes Education</title>
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
        .coach-header {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 3rem 0;
            text-align: center;
        }

        .coach-photo {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin-bottom: 2rem;
        }

        .coach-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .coach-header h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #f59e0b;
        }

        .coach-header p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: bold;
            margin: 0.5rem;
        }

        .status-available {
            background: rgba(5, 150, 105, 0.2);
            color: #059669;
            border: 2px solid #059669;
        }

        .status-busy {
            background: rgba(220, 38, 38, 0.2);
            color: #dc2626;
            border: 2px solid #dc2626;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-content {
            padding: 3rem 0;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-top: 4px solid #2563eb;
            transition: transform 0.3s;
        }

        .card-title {
            color: #2563eb;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
        }
        .contact-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        .contact-btn {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-message {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-call {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .btn-video {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            grid-column: 1 / -1;
        }

        .contact-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .cv-section {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .cv-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .cv-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .cv-icon {
            background: #2563eb;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        .appointment-section {
            text-align: center;
            margin-top: 3rem;
        }

        .main-appointment-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 1.5rem 3rem;
            border: none;
            border-radius: 15px;
            font-size: 1.3rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
            text-decoration: none;
            display: inline-block;
        }

        .main-appointment-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(245, 158, 11, 0.4);
        }
        .info-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
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
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .contact-buttons {
                grid-template-columns: 1fr;
            }
            
            .coach-photo {
                width: 150px;
                height: 150px;
            }
            
            .coach-header h1 {
                font-size: 2rem;
            }
            
            .main-appointment-btn {
                padding: 1rem 2rem;
                font-size: 1.1rem;
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
    <section class="coach-header">
        <div class="container">
            <img src="<?= htmlspecialchars($coach['photo']) ?>" alt="Photo du coach" class="coach-photo">
            <h1><?= htmlspecialchars($coach['prenom'] . ' ' . $coach['nom']) ?></h1>
            <h3><?= htmlspecialchars($coach['specialite']) ?></h3>
            <p><?= htmlspecialchars($coach['description']) ?></p>
            <div>
                <span class="status-badge <?= $statut_info['class'] ?>">
                    <?= $statut_info['text'] ?>
                </span>
                <span style="color: #f8f9fa;">
                    📍 <span><?= htmlspecialchars($coach['bureau']) ?></span>
                </span>
            </div>
        </div>
    </section>
    <section class="main-content">
        <div class="container">
            <div class="content-grid">
                <div class="info-card">
                    <h3 class="card-title">🎓 Curriculum Vitae</h3>
                    
                    <div class="cv-section">
                        <div class="cv-item">
                            <div class="cv-icon">🎓</div>
                            <div>
                                <h6 style="font-weight: bold; margin-bottom: 0.5rem;">Formation</h6>
                                <p style="margin: 0; font-size: 0.9rem;">Master STAPS - Université Paris Sud<br>
                                <span style="color: #666;">2015-2017</span></p>
                            </div>
                        </div>
                        
                        <div class="cv-item">
                            <div class="cv-icon">💼</div>
                            <div>
                                <h6 style="font-weight: bold; margin-bottom: 0.5rem;">Expérience</h6>
                                <p style="margin: 0; font-size: 0.9rem;">Coach Sportify Omnes<br>
                                <span style="color: #666;">2019 - Présent</span></p>
                            </div>
                        </div>
                        
                        <div class="cv-item">
                            <div class="cv-icon">📜</div>
                            <div>
                                <h6 style="font-weight: bold; margin-bottom: 0.5rem;">Certifications</h6>
                                <p style="margin: 0; font-size: 0.9rem;">• Préparateur Physique FSCF<br>
                                • Formation Nutrition Sportive<br>
                                • Secourisme PSC1</p>
                            </div>
                        </div>
                        
                        <div class="cv-item">
                            <div class="cv-icon">⭐</div>
                            <div>
                                <h6 style="font-weight: bold; margin-bottom: 0.5rem;">Spécialités</h6>
                                <p style="margin: 0; font-size: 0.9rem;">• Musculation & Force<br>
                                • Préparation physique<br>
                                • Réhabilitation sportive</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="info-card">
                    <h3 class="card-title">💬 Contacter le coach</h3>
                    
                    <div class="contact-buttons">
    <button class="contact-btn btn-message" onclick="openChat(<?= $coach_id ?>)">
        💬 Chat en Direct
    </button>
    
    <button class="contact-btn btn-call" onclick="makeCall('<?= htmlspecialchars($coach['telephone']) ?>')">
        📞 Appel Vocal
    </button>
    
    <button class="contact-btn btn-video" onclick="startVideo('<?= htmlspecialchars($coach['email']) ?>')">
        📹 Visioconférence
    </button>
</div>
                    
                    <div class="info-box">
                        <h6>ℹ️ Informations pratiques</h6>
                        <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem;">
                            <li>⏱️ Durée des séances : 1h</li>
                            <li>💰 Gratuit pour étudiants Omnes</li>
                            <li>👥 Séances individuelles ou collectives</li>
                            <?php if ($coach['telephone']): ?>
                            <li>📞 Tél: <?= htmlspecialchars($coach['telephone']) ?></li>
                            <?php endif; ?>
                            <?php if ($coach['email']): ?>
                            <li>✉️ Email: <?= htmlspecialchars($coach['email']) ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="appointment-section">
                <a href="disponibilités.php?coach_id=<?= $coach_id ?>" class="main-appointment-btn">
                    📅 Prendre un Rendez-Vous
                </a>
                <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                    Cliquez pour voir les disponibilités et réserver votre créneau
                </p>
            </div>
        </div>
    </section>
    <div class="notification" id="notification">
        ✅ <span id="notification-text">Action effectuée avec succès !</span>
    </div>

    <script>
        function openChat(coachId) {
    // Ouvrir la chatroom dans une nouvelle fenêtre
    const chatWindow = window.open(
        `chatroom.php?coach_id=${coachId}`, 
        'chatroom',
        'width=900,height=700,scrollbars=yes,resizable=yes'
    );
    
    if (chatWindow) {
        chatWindow.focus();
    } else {
        // Si popup bloquée, rediriger dans la même fenêtre
        window.location.href = `chatroom.php?coach_id=${coachId}`;
    }
}

// Fonctions existantes...
function sendMessage(email) {
    openChat(<?= $coach_id ?>);
}

function makeCall(telephone) {
    if (telephone) {
        showNotification(`Appel en cours vers ${telephone}...`);
    } else {
        showNotification('Numéro de téléphone non disponible', 'error');
    }
}

function startVideo(email) {
    if (email) {
        showNotification('Démarrage de la visioconférence...');
    } else {
        showNotification('Contact non disponible pour la visio', 'error');
    }
}
        function sendMessage(email) {
            if (email) {
                showNotification(`Message envoyé à ${email} !`);
            } else {
                showNotification('Email non disponible', 'error');
            }
        }

        function makeCall(telephone) {
            if (telephone) {
                showNotification(`Appel en cours vers ${telephone}...`);
            } else {
                showNotification('Numéro de téléphone non disponible', 'error');
            }
        }

        function startVideo(email) {
            if (email) {
                showNotification('Démarrage de la visioconférence...');
            } else {
                showNotification('Contact non disponible pour la visio', 'error');
            }
        }
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const text = document.getElementById('notification-text');
            
            text.textContent = message;
            notification.className = 'notification';
            if (type === 'error') {
                notification.style.background = '#dc2626';
            } else {
                notification.style.background = '#059669';
            }
            notification.classList.add('show');
            
            setTimeout(function() {
                notification.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>