<?php
// chatroom.php (nom de fichier corrigé)
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$user = getCurrentUser();

class SimpleChatroom {
    private $conn;
    
    public function __construct() {
        $this->conn = getDbConnection();
    }
    
    // Récupérer les messages prédéfinis avec fallback
    public function getMessagesPredefinisBy($categorie) {
        try {
            // Vérifier si la table existe
            $query = "SHOW TABLES LIKE 'messages_predefinis'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // Table n'existe pas, créer les messages par défaut
                return $this->getDefaultMessages($categorie);
            }
            
            $query = "SELECT id, message_text, response_type, reponse_template 
                     FROM messages_predefinis 
                     WHERE categorie = :categorie AND type_expediteur = 'client' 
                     AND actif = TRUE ORDER BY ordre_affichage";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':categorie', $categorie);
            $stmt->execute();
            
            $results = $stmt->fetchAll();
            
            // Si pas de résultats, utiliser les messages par défaut
            if (empty($results)) {
                return $this->getDefaultMessages($categorie);
            }
            
            return $results;
            
        } catch (Exception $e) {
            // En cas d'erreur, utiliser les messages par défaut
            return $this->getDefaultMessages($categorie);
        }
    }
    
    // Messages par défaut si la DB n'est pas configurée
    private function getDefaultMessages($categorie) {
        $messages = [
            'salutation' => [
                ['id' => 's1', 'message_text' => '👋 Bonjour ! J\'aimerais vous contacter', 'response_type' => 'static', 'reponse_template' => '👋 Bonjour ! Je suis {coach_prenom}, votre coach {coach_specialite}. Comment puis-je vous aider ?'],
                ['id' => 's2', 'message_text' => '😊 Bonsoir, êtes-vous disponible ?', 'response_type' => 'static', 'reponse_template' => '😊 Bonsoir ! Oui je suis disponible. En quoi puis-je vous être utile ?']
            ],
            'rdv' => [
                ['id' => 'r1', 'message_text' => '📅 Je souhaite prendre un rendez-vous', 'response_type' => 'dynamic_schedule', 'reponse_template' => 'Voici mes prochaines disponibilités :\n{available_slots}\n\nCliquez sur "Prendre RDV" pour réserver !'],
                ['id' => 'r2', 'message_text' => '⏰ Quels sont vos créneaux libres ?', 'response_type' => 'dynamic_schedule', 'reponse_template' => 'Mes créneaux libres cette semaine :\n{available_slots}\n\nPour réserver : utilisez le bouton "Prendre RDV" ci-dessous'],
                ['id' => 'r3', 'message_text' => '❌ Je dois annuler mon rendez-vous', 'response_type' => 'dynamic_rdv', 'reponse_template' => '❌ Pas de problème ! {current_appointments}\n\nPour annuler, rendez-vous dans "Mes Réservations".']
            ],
            'info' => [
                ['id' => 'i1', 'message_text' => '❓ Quels sont vos horaires ?', 'response_type' => 'dynamic_info', 'reponse_template' => '⏰ Mes horaires :\n📍 Bureau : {coach_bureau}\n📞 Tél : {coach_telephone}\n🕐 Généralement : Lun-Ven 8h-18h'],
                ['id' => 'i2', 'message_text' => '📍 Où se trouvent vos cours ?', 'response_type' => 'dynamic_info', 'reponse_template' => '📍 Mes cours ont lieu :\n🏢 {coach_bureau}\n📧 Contact : {coach_email}\n🏫 Adresse : 37 Quai de Grenelle, 75015 Paris'],
                ['id' => 'i3', 'message_text' => '💰 Quels sont vos tarifs ?', 'response_type' => 'static', 'reponse_template' => '💰 Tarifs :\n✅ Consultations gratuites pour étudiants Omnes\n💎 Services premium disponibles via le site\n💳 Paiement sécurisé en ligne']
            ],
            'probleme' => [
                ['id' => 'p1', 'message_text' => '😓 J\'ai un problème technique', 'response_type' => 'static', 'reponse_template' => '😓 Je comprends. Décrivez-moi le problème :\n🔧 Problème de réservation ?\n💻 Souci avec le site ?\n📱 Je vais vous aider !'],
                ['id' => 'p2', 'message_text' => '😷 Je suis malade, que faire ?', 'response_type' => 'dynamic_rdv', 'reponse_template' => '😷 Prenez soin de vous ! \n{current_appointments}\n🏥 Reposez-vous, on peut reporter sans frais.']
            ],
            'autre' => [
                ['id' => 'a1', 'message_text' => '💪 Conseils d\'entraînement ?', 'response_type' => 'dynamic_info', 'reponse_template' => '💪 Mes conseils en {coach_specialite} :\n✨ Consultation personnalisée recommandée\n📋 Programme adapté à vos objectifs\n🎯 Réservez un créneau pour en parler !'],
                ['id' => 'a2', 'message_text' => '🙏 Merci pour votre aide !', 'response_type' => 'static', 'reponse_template' => '🙏 De rien ! C\'est un plaisir de vous accompagner dans votre parcours sportif. À très bientôt !']
            ]
        ];
        
        return $messages[$categorie] ?? [];
    }
    
    // Générer une réponse dynamique selon le coach
    public function generateResponse($coach_id, $message_predefini_id, $reponse_template, $response_type) {
        try {
            // Récupérer les infos du coach
            $coach = $this->getCoachInfo($coach_id);
            if (!$coach) return "❌ Coach non trouvé";
            
            $response = $reponse_template;
            
            // Remplacements de base
            $response = str_replace('{coach_prenom}', $coach['prenom'], $response);
            $response = str_replace('{coach_nom}', $coach['nom'], $response);
            $response = str_replace('{coach_specialite}', $coach['specialite'], $response);
            $response = str_replace('{coach_bureau}', $coach['bureau'] ?: 'Bureau non défini', $response);
            $response = str_replace('{coach_telephone}', $coach['telephone'] ?: 'Tél non défini', $response);
            $response = str_replace('{coach_email}', $coach['email'] ?: 'Email non défini', $response);
            $response = str_replace('{coach_id}', $coach_id, $response);
            
            // Réponses dynamiques selon le type
            switch ($response_type) {
                case 'dynamic_schedule':
                    $slots = $this->getAvailableSlots($coach_id);
                    $response = str_replace('{available_slots}', $slots, $response);
                    break;
                    
                case 'dynamic_rdv':
                    $appointments = $this->getCurrentAppointments($coach_id);
                    $response = str_replace('{current_appointments}', $appointments, $response);
                    break;
            }
            
            return $response;
            
        } catch (Exception $e) {
            return "❌ Erreur technique : " . $e->getMessage();
        }
    }
    
    // Récupérer les créneaux disponibles du coach
    private function getAvailableSlots($coach_id, $limit = 5) {
        try {
            $query = "SELECT date_creneau, 
                            TIME_FORMAT(heure_debut, '%Hh%i') as heure_debut,
                            TIME_FORMAT(heure_fin, '%Hh%i') as heure_fin
                     FROM creneaux 
                     WHERE coach_id = :coach_id 
                     AND statut = 'libre' 
                     AND date_creneau >= CURDATE()
                     ORDER BY date_creneau, heure_debut 
                     LIMIT :limit";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':coach_id', $coach_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $slots = $stmt->fetchAll();
            
            if (empty($slots)) {
                return "😔 Aucun créneau libre pour le moment.\nConsultez mon planning complet pour plus d'options.";
            }
            
            $result = "";
            foreach ($slots as $slot) {
                $date_fr = $this->formatDateFr($slot['date_creneau']);
                $result .= "📅 {$date_fr} : {$slot['heure_debut']}-{$slot['heure_fin']}\n";
            }
            
            return $result;
            
        } catch (Exception $e) {
            return "❌ Impossible de récupérer les créneaux : " . $e->getMessage();
        }
    }
    
    // Récupérer les RDV actuels de l'utilisateur avec ce coach
    private function getCurrentAppointments($coach_id) {
        global $user;
        
        try {
            $query = "SELECT date_creneau, 
                            TIME_FORMAT(heure_debut, '%Hh%i') as heure_debut,
                            TIME_FORMAT(heure_fin, '%Hh%i') as heure_fin
                     FROM creneaux 
                     WHERE coach_id = :coach_id 
                     AND etudiant_email = :email
                     AND statut = 'reserve' 
                     AND date_creneau >= CURDATE()
                     ORDER BY date_creneau, heure_debut 
                     LIMIT 3";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':coach_id', $coach_id);
            $stmt->bindParam(':email', $user['email']);
            $stmt->execute();
            
            $appointments = $stmt->fetchAll();
            
            if (empty($appointments)) {
                return "📋 Vous n'avez pas de RDV programmé avec moi.";
            }
            
            $result = "📋 Vos RDV avec moi :\n";
            foreach ($appointments as $apt) {
                $date_fr = $this->formatDateFr($apt['date_creneau']);
                $result .= "📅 {$date_fr} : {$apt['heure_debut']}-{$apt['heure_fin']}\n";
            }
            
            return $result;
            
        } catch (Exception $e) {
            return "❌ Impossible de récupérer vos RDV : " . $e->getMessage();
        }
    }
    
    // Récupérer les infos du coach
    private function getCoachInfo($coach_id) {
        try {
            $query = "SELECT * FROM coachs WHERE id = :id AND actif = TRUE";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $coach_id);
            $stmt->execute();
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    // Formatter la date en français
    private function formatDateFr($date) {
        $jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        $timestamp = strtotime($date);
        $jour_semaine = $jours[date('w', $timestamp)];
        $jour = date('d', $timestamp);
        $mois = date('m', $timestamp);
        
        return "{$jour_semaine} {$jour}/{$mois}";
    }
}

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $chatroom = new SimpleChatroom();
    
    switch ($_POST['action']) {
        case 'get_messages_predefinis':
            $categorie = $_POST['categorie'] ?? 'salutation';
            $messages = $chatroom->getMessagesPredefinisBy($categorie);
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        case 'get_response':
            $coach_id = (int)$_POST['coach_id'];
            $message_predefini_id = $_POST['message_predefini_id'];
            $reponse_template = $_POST['reponse_template'] ?? '';
            $response_type = $_POST['response_type'] ?? 'static';
            
            $response = $chatroom->generateResponse($coach_id, $message_predefini_id, $reponse_template, $response_type);
            echo json_encode(['success' => true, 'response' => $response]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    }
    exit;
}

// Affichage de la page chatroom
$coach_id = isset($_GET['coach_id']) ? (int)$_GET['coach_id'] : 1;
$coach = getCoachInfo($coach_id);

if (!$coach) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat avec <?= htmlspecialchars($coach['prenom'] . ' ' . $coach['nom']) ?> - Sportify</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .coach-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .coach-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid white;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .messages-area {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            max-height: calc(100vh - 250px);
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .message.sent {
            flex-direction: row-reverse;
        }

        .message-bubble {
            max-width: 80%;
            padding: 0.75rem 1rem;
            border-radius: 18px;
            word-wrap: break-word;
            white-space: pre-line;
        }

        .message.received .message-bubble {
            background: #f1f5f9;
            color: #334155;
        }

        .message.sent .message-bubble {
            background: #2563eb;
            color: white;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.6;
            margin-top: 0.25rem;
        }

        .quick-replies {
            padding: 1rem;
            border-top: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .category-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .category-tab {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .category-tab.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .replies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            max-height: 120px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        .reply-btn {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
            font-size: 0.9rem;
        }

        .reply-btn:hover {
            border-color: #2563eb;
            background: #f0f9ff;
            transform: translateY(-1px);
        }

        .typing-indicator {
            display: none;
            padding: 0.5rem 1rem;
            font-style: italic;
            color: #64748b;
            font-size: 0.9rem;
        }

        .status-online {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .debug-info {
            background: #fee2e2;
            border: 1px solid #fecaca;
            padding: 0.5rem;
            margin: 0.5rem;
            border-radius: 8px;
            font-size: 0.8rem;
            display: none;
        }

        @media (max-width: 768px) {
            .replies-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="chat-header">
        <div class="coach-info">
            <img src="<?= htmlspecialchars($coach['photo'] ?: '/images_projet/default_coach.jpg') ?>" alt="Coach" class="coach-avatar">
            <div>
                <h3 style="margin: 0;"><?= htmlspecialchars($coach['prenom'] . ' ' . $coach['nom']) ?></h3>
                <small><span class="status-online"></span><?= htmlspecialchars($coach['specialite']) ?></small>
            </div>
        </div>
        <div>
            <button onclick="closeChat()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer;">
                ✕ Fermer
            </button>
        </div>
    </div>

    <div class="chat-container">
        <div class="messages-area" id="messagesList">
            <div class="message received">
                <div class="message-bubble">
                    👋 Salut ! Je suis <?= htmlspecialchars($coach['prenom']) ?>, votre coach <?= htmlspecialchars($coach['specialite']) ?>. 
                    
                    Choisissez une option ci-dessous pour me poser vos questions !
                    <div class="message-time">Maintenant</div>
                </div>
            </div>
        </div>

        <div class="typing-indicator" id="typingIndicator">
            <?= htmlspecialchars($coach['prenom']) ?> est en train de répondre...
        </div>

        <div class="quick-replies">
            <div class="category-tabs">
                <button class="category-tab active" data-category="salutation">👋 Salut</button>
                <button class="category-tab" data-category="rdv">📅 RDV</button>
                <button class="category-tab" data-category="info">❓ Infos</button>
                <button class="category-tab" data-category="probleme">😓 Aide</button>
                <button class="category-tab" data-category="autre">💪 Conseils</button>
            </div>
            
            <div class="replies-grid" id="repliesGrid">
                <div style="grid-column: 1/-1; text-align: center; color: #64748b; padding: 1rem;">
                    Chargement des options...
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="action-btn btn-primary" onclick="takeAppointment()">
                    📅 Prendre RDV
                </button>
                <button class="action-btn btn-success" onclick="viewMyReservations()">
                    👀 Mes Réservations
                </button>
            </div>
        </div>
    </div>

    <div class="debug-info" id="debugInfo">Debug: </div>

    <script>
        const coachId = <?= $coach_id ?>;
        let currentCategory = 'salutation';

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Chatroom initialisée pour coach ID:', coachId);
            loadQuickReplies('salutation');
            setupCategoryTabs();
        });

        function setupCategoryTabs() {
            document.querySelectorAll('.category-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    currentCategory = this.dataset.category;
                    console.log('📂 Catégorie changée:', currentCategory);
                    loadQuickReplies(currentCategory);
                });
            });
        }

        function loadQuickReplies(category) {
            console.log('🔄 Chargement des messages pour:', category);
            
            const grid = document.getElementById('repliesGrid');
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #64748b; padding: 1rem;">Chargement...</div>';
            
            fetch('chatroom.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_messages_predefinis&categorie=${category}`
            })
            .then(response => {
                console.log('📡 Réponse reçue:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('📋 Données reçues:', data);
                
                if (data.success && data.messages && data.messages.length > 0) {
                    grid.innerHTML = '';
                    
                    data.messages.forEach((msg, index) => {
                        console.log(`📝 Message ${index}:`, msg.message_text);
                        const button = document.createElement('button');
                        button.className = 'reply-btn';
                        button.textContent = msg.message_text;
                        button.onclick = () => sendMessage(msg.message_text, msg.id, msg.reponse_template, msg.response_type);
                        grid.appendChild(button);
                    });
                } else {
                    console.log('❌ Aucun message trouvé pour:', category);
                    grid.innerHTML = `
                        <div style="grid-column: 1/-1; text-align: center; color: #dc2626; padding: 1rem;">
                            ❌ Aucun message disponible pour "${category}"
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('💥 Erreur lors du chargement:', error);
                grid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; color: #dc2626; padding: 1rem;">
                        ❌ Erreur de chargement
                    </div>
                `;
            });
        }

        function sendMessage(message, messagePredefiniId, reponseTemplate, responseType) {
            console.log('💬 Envoi message:', message);
            console.log('🎯 Template:', reponseTemplate);
            console.log('⚙️ Type:', responseType);
            
            // Ajouter le message utilisateur
            addMessage(message, 'sent');
            
            // Simuler la frappe du coach
            showTypingIndicator();
            
            // Récupérer la réponse dynamique
            fetch('chatroom.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_response&coach_id=${coachId}&message_predefini_id=${messagePredefiniId}&reponse_template=${encodeURIComponent(reponseTemplate)}&response_type=${responseType}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('🤖 Réponse coach:', data);
                hideTypingIndicator();
                if (data.success) {
                    addMessage(data.response, 'received');
                } else {
                    addMessage('❌ Erreur technique. Réessayez dans un moment.', 'received');
                }
            })
            .catch(error => {
                console.error('💥 Erreur envoi:', error);
                hideTypingIndicator();
                addMessage('❌ Problème de connexion. Vérifiez votre réseau.', 'received');
            });
        }

        function addMessage(text, type) {
            const messagesList = document.getElementById('messagesList');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            
            const timeStr = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
            
            messageDiv.innerHTML = `
                <div class="message-bubble">
                    ${text}
                    <div class="message-time">${timeStr}</div>
                </div>
            `;
            
            messagesList.appendChild(messageDiv);
            messagesList.scrollTop = messagesList.scrollHeight;
        }

        function showTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'block';
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'none';
        }

        function takeAppointment() {
            window.open(`disponibilités.php?coach_id=${coachId}`, '_blank');
        }

        function viewMyReservations() {
            window.open('mes_reservations.php', '_blank');
        }

        function closeChat() {
            window.location.href = `coach.php?coach_id=${coachId}`;
        }

        // Afficher les erreurs console dans l'interface pour debug
        window.addEventListener('error', function(e) {
            console.error('🚨 Erreur JavaScript:', e.error);
        });
    </script>
</body>
</html>