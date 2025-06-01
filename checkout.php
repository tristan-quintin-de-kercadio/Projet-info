<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$user = getCurrentUser();
function getServicesPayants() {
    return [
        [
            'id' => 1,
            'nom' => 'Coaching Personnel Premium',
            'description' => 'Séances individuelles avec coach personnel (1h)',
            'prix' => 45.00,
            'duree' => '1 heure',
            'type' => 'seance'
        ],
        [
            'id' => 2,
            'nom' => 'Abonnement Salle VIP',
            'description' => 'Accès illimité à la salle VIP avec équipements haut de gamme',
            'prix' => 89.99,
            'duree' => '1 mois',
            'type' => 'abonnement'
        ],
        [
            'id' => 3,
            'nom' => 'Programme Nutrition Personnalisé',
            'description' => 'Consultation nutritionniste + plan alimentaire sur mesure',
            'prix' => 120.00,
            'duree' => 'Plan 3 mois',
            'type' => 'programme'
        ],
        [
            'id' => 4,
            'nom' => 'Cours Particulier Natation',
            'description' => 'Leçons privées de natation avec coach certifié',
            'prix' => 55.00,
            'duree' => '1 heure',
            'type' => 'seance'
        ]
    ];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'process_payment') {
        try {
            $conn = getDbConnection();
            $nom = trim($_POST['nom']);
            $prenom = trim($_POST['prenom']);
            $adresse_ligne1 = trim($_POST['adresse_ligne1']);
            $adresse_ligne2 = trim($_POST['adresse_ligne2']);
            $ville = trim($_POST['ville']);
            $code_postal = trim($_POST['code_postal']);
            $pays = trim($_POST['pays']);
            $telephone = trim($_POST['telephone']);
            $carte_etudiant = trim($_POST['carte_etudiant']);
            $type_carte = $_POST['type_carte'];
            $numero_carte = $_POST['numero_carte'];
            $nom_carte = trim($_POST['nom_carte']);
            $date_expiration = $_POST['date_expiration'];
            $code_securite = $_POST['code_securite'];
            $service_id = (int)$_POST['service_id'];
            $services = getServicesPayants();
            $service_selectionne = null;
            
            foreach ($services as $service) {
                if ($service['id'] == $service_id) {
                    $service_selectionne = $service;
                    break;
                }
            }
            if (!$service_selectionne) {
                echo json_encode(['success' => false, 'message' => 'Service non trouvé']);
                exit;
            }
            if (empty($nom) || empty($prenom) || empty($numero_carte) || empty($code_securite)) {
                echo json_encode(['success' => false, 'message' => 'Tous les champs obligatoires doivent être remplis']);
                exit;
            }
            $validation_result = validerCartePaiement($numero_carte, $nom_carte, $date_expiration, $code_securite, $type_carte);
            
            if (!$validation_result['valide']) {
                echo json_encode(['success' => false, 'message' => $validation_result['message']]);
                exit;
            }
            $conn->beginTransaction();
            
            try {
                $query_adresse = "INSERT INTO adresses_facturation 
                                 (utilisateur_id, nom, prenom, adresse_ligne1, adresse_ligne2, ville, code_postal, pays, telephone, carte_etudiant)
                                 VALUES (:user_id, :nom, :prenom, :adresse1, :adresse2, :ville, :code_postal, :pays, :telephone, :carte_etudiant)";
                
                $stmt_adresse = $conn->prepare($query_adresse);
                $stmt_adresse->bindParam(':user_id', $user['id']);
                $stmt_adresse->bindParam(':nom', $nom);
                $stmt_adresse->bindParam(':prenom', $prenom);
                $stmt_adresse->bindParam(':adresse1', $adresse_ligne1);
                $stmt_adresse->bindParam(':adresse2', $adresse_ligne2);
                $stmt_adresse->bindParam(':ville', $ville);
                $stmt_adresse->bindParam(':code_postal', $code_postal);
                $stmt_adresse->bindParam(':pays', $pays);
                $stmt_adresse->bindParam(':telephone', $telephone);
                $stmt_adresse->bindParam(':carte_etudiant', $carte_etudiant);
                $stmt_adresse->execute();
                
                $adresse_id = $conn->lastInsertId();
                $numero_transaction = 'TXN_' . date('YmdHis') . '_' . rand(1000, 9999);
                $montant = $service_selectionne['prix'];
                
                $query_paiement = "INSERT INTO paiements 
                                  (utilisateur_id, adresse_facturation_id, service_id, service_nom, montant, 
                                   type_carte, numero_carte_masque, nom_carte, date_expiration, 
                                   numero_transaction, statut_paiement, date_paiement)
                                  VALUES (:user_id, :adresse_id, :service_id, :service_nom, :montant,
                                          :type_carte, :numero_masque, :nom_carte, :date_expiration,
                                          :numero_transaction, 'approuve', NOW())";
                
                $stmt_paiement = $conn->prepare($query_paiement);
                $stmt_paiement->bindParam(':user_id', $user['id']);
                $stmt_paiement->bindParam(':adresse_id', $adresse_id);
                $stmt_paiement->bindParam(':service_id', $service_id);
                $stmt_paiement->bindParam(':service_nom', $service_selectionne['nom']);
                $stmt_paiement->bindParam(':montant', $montant);
                $stmt_paiement->bindParam(':type_carte', $type_carte);
                $numero_masque = '**** **** **** ' . substr($numero_carte, -4);
                $stmt_paiement->bindParam(':numero_masque', $numero_masque);
                $stmt_paiement->bindParam(':nom_carte', $nom_carte);
                $stmt_paiement->bindParam(':date_expiration', $date_expiration);
                $stmt_paiement->bindParam(':numero_transaction', $numero_transaction);
                $stmt_paiement->execute();
                $conn->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Paiement effectué avec succès !',
                    'numero_transaction' => $numero_transaction,
                    'montant' => $montant,
                    'service' => $service_selectionne['nom']
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Erreur lors du traitement du paiement: ' . $e->getMessage()]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        exit;
    }
}
function validerCartePaiement($numero_carte, $nom_carte, $date_expiration, $code_securite, $type_carte) {
    $conn = getDbConnection();
    $query = "SELECT * FROM cartes_test 
              WHERE numero_carte = :numero_carte 
              AND nom_carte = :nom_carte 
              AND date_expiration = :date_expiration 
              AND code_securite = :code_securite
              AND type_carte = :type_carte
              AND actif = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':numero_carte', $numero_carte);
    $stmt->bindParam(':nom_carte', $nom_carte);
    $stmt->bindParam(':date_expiration', $date_expiration);
    $stmt->bindParam(':code_securite', $code_securite);
    $stmt->bindParam(':type_carte', $type_carte);
    $stmt->execute();
    
    $carte = $stmt->fetch();
    
    if ($carte) {
        $date_exp = DateTime::createFromFormat('m/y', $date_expiration);
        $aujourd_hui = new DateTime();
        
        if ($date_exp < $aujourd_hui) {
            return ['valide' => false, 'message' => 'Carte expirée'];
        }
        if ($carte['solde_disponible'] < 200) {
            return ['valide' => false, 'message' => 'Solde insuffisant'];
        }
        
        return ['valide' => true, 'message' => 'Carte validée'];
    }
    
    return ['valide' => false, 'message' => 'Informations de carte invalides'];
}

$services = getServicesPayants();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - Sportify | Omnes Education</title>
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
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-content {
            padding: 3rem 0;
        }

        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
        }
        .service-selection {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .service-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .service-card:hover {
            border-color: #2563eb;
        }

        .service-card.selected {
            border-color: #f59e0b;
            background: #fffbeb;
        }

        .service-card h4 {
            color: #2563eb;
            margin-bottom: 0.5rem;
        }

        .service-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #059669;
            margin-top: 1rem;
        }
        .checkout-form {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            color: #1e40af;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .required {
            color: #dc2626;
        }
        .checkout-sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .order-summary {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .summary-title {
            color: #1e40af;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            color: #2563eb;
        }

        .total-amount {
            font-size: 1.5rem;
            color: #059669;
        }
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        }

        .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }
        .security-info {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        .security-info h5 {
            color: #0369a1;
            margin-bottom: 0.5rem;
        }
        .card-types {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .card-type {
            padding: 0.25rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            font-size: 0.8rem;
            background: white;
            cursor: pointer;
        }

        .card-type.selected {
            border-color: #2563eb;
            background: #dbeafe;
            color: #2563eb;
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
            margin: 10% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .modal-title {
            color: #059669;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .transaction-details {
            background: #f0fdf4;
            border: 1px solid #22c55e;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .checkout-sidebar {
                position: static;
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
                <a href="mes_reservations.php">📅 Mes Réservations</a>
                <a href="mon_profil.php">👤 Mon Profil</a>
                <a href="logout.php">🚪 Déconnexion</a>
            </div>
        </div>
    </nav>
    <section class="page-header">
        <div class="container">
            <h1>💳 Paiement Sécurisé</h1>
            <p>Finalisez votre achat pour accéder aux services premium de Sportify</p>
        </div>
    </section>
    <section class="main-content">
        <div class="container">
            <div class="checkout-layout">
                <div>
                    <div class="service-selection">
                        <h3 style="color: #2563eb; margin-bottom: 1.5rem;">🎯 Choisissez votre service</h3>
                        <?php foreach ($services as $service): ?>
                        <div class="service-card" onclick="selectService(<?= $service['id'] ?>, '<?= htmlspecialchars($service['nom']) ?>', <?= $service['prix'] ?>)">
                            <h4><?= htmlspecialchars($service['nom']) ?></h4>
                            <p><?= htmlspecialchars($service['description']) ?></p>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span><strong>Durée :</strong> <?= htmlspecialchars($service['duree']) ?></span>
                                <span class="service-price"><?= number_format($service['prix'], 2) ?> €</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="checkout-form">
                        <form id="checkoutForm">
                            <input type="hidden" id="selected_service_id" name="service_id" required>
                            <div class="form-section">
                                <h3 class="section-title">👤 Informations Personnelles</h3>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nom">Nom <span class="required">*</span></label>
                                        <input type="text" id="nom" name="nom" required value="<?= htmlspecialchars($user['nom'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="prenom">Prénom <span class="required">*</span></label>
                                        <input type="text" id="prenom" name="prenom" required value="<?= htmlspecialchars($user['prenom'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="carte_etudiant">Numéro de Carte Étudiant <span class="required">*</span></label>
                                    <input type="text" id="carte_etudiant" name="carte_etudiant" required placeholder="Ex: ETU2025001234">
                                </div>
                                
                                <div class="form-group">
                                    <label for="telephone">Numéro de Téléphone <span class="required">*</span></label>
                                    <input type="tel" id="telephone" name="telephone" required placeholder="+33 1 23 45 67 89">
                                </div>
                            </div>
                            <div class="form-section">
                                <h3 class="section-title">📍 Adresse de Facturation</h3>
                                
                                <div class="form-group">
                                    <label for="adresse_ligne1">Adresse Ligne 1 <span class="required">*</span></label>
                                    <input type="text" id="adresse_ligne1" name="adresse_ligne1" required placeholder="Numéro et nom de rue">
                                </div>
                                
                                <div class="form-group">
                                    <label for="adresse_ligne2">Adresse Ligne 2</label>
                                    <input type="text" id="adresse_ligne2" name="adresse_ligne2" placeholder="Appartement, étage, bâtiment (optionnel)">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="ville">Ville <span class="required">*</span></label>
                                        <input type="text" id="ville" name="ville" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="code_postal">Code Postal <span class="required">*</span></label>
                                        <input type="text" id="code_postal" name="code_postal" required pattern="[0-9]{5}" placeholder="75015">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="pays">Pays <span class="required">*</span></label>
                                    <select id="pays" name="pays" required>
                                        <option value="">Sélectionner un pays</option>
                                        <option value="France" selected>France</option>
                                        <option value="Belgique">Belgique</option>
                                        <option value="Suisse">Suisse</option>
                                        <option value="Canada">Canada</option>
                                        <option value="Autre">Autre</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-section">
                                <h3 class="section-title">💳 Informations de Paiement</h3>
                                
                                <div class="form-group">
                                    <label for="type_carte">Type de Carte <span class="required">*</span></label>
                                    <div class="card-types">
                                        <div class="card-type" onclick="selectCardType('Visa')">💳 Visa</div>
                                        <div class="card-type" onclick="selectCardType('MasterCard')">💳 MasterCard</div>
                                        <div class="card-type" onclick="selectCardType('American Express')">💳 American Express</div>
                                        <div class="card-type" onclick="selectCardType('PayPal')">💰 PayPal</div>
                                    </div>
                                    <input type="hidden" id="type_carte" name="type_carte" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="numero_carte">Numéro de Carte <span class="required">*</span></label>
                                    <input type="text" id="numero_carte" name="numero_carte" required 
                                           placeholder="1234 5678 9012 3456" maxlength="19"
                                           oninput="formatCardNumber(this)">
                                </div>
                                
                                <div class="form-group">
                                    <label for="nom_carte">Nom sur la Carte <span class="required">*</span></label>
                                    <input type="text" id="nom_carte" name="nom_carte" required 
                                           placeholder="Nom tel qu'affiché sur la carte" style="text-transform: uppercase;">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="date_expiration">Date d'Expiration <span class="required">*</span></label>
                                        <input type="text" id="date_expiration" name="date_expiration" required 
                                               placeholder="MM/AA" maxlength="5" oninput="formatExpiryDate(this)">
                                    </div>
                                    <div class="form-group">
                                        <label for="code_securite">Code de Sécurité <span class="required">*</span></label>
                                        <input type="password" id="code_securite" name="code_securite" required 
                                               placeholder="123" maxlength="4" pattern="[0-9]{3,4}">
                                    </div>
                                </div>
                                
                                <div class="security-info">
                                    <h5>🔒 Paiement 100% Sécurisé</h5>
                                    <p>Vos informations de paiement sont cryptées et sécurisées. Nous ne stockons jamais vos données bancaires complètes.</p>
                                    <p><strong>Carte de test disponible :</strong><br>
                                    Numéro: 4532015112830366 | Nom: JEAN MARTIN | Exp: 12/26 | CVV: 123</p>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" id="payButton" disabled>
                                🔒 Finaliser le Paiement
                            </button>
                        </form>
                    </div>
                </div>
                <div class="checkout-sidebar">
                    <div class="order-summary">
                        <h3 class="summary-title">📋 Récapitulatif de Commande</h3>
                        <div id="order-details">
                            <div class="summary-item">
                                <span>Service sélectionné :</span>
                                <span id="selected-service-name">Aucun service sélectionné</span>
                            </div>
                            <div class="summary-item">
                                <span>Prix unitaire :</span>
                                <span id="selected-service-price">0.00 €</span>
                            </div>
                            <div class="summary-item">
                                <span>TVA (20%) :</span>
                                <span id="tva-amount">0.00 €</span>
                            </div>
                            <div class="summary-item" style="border-top: 2px solid #2563eb; margin-top: 1rem; padding-top: 1rem;">
                                <span>Total à payer :</span>
                                <span class="total-amount" id="total-amount">0.00 €</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="security-info">
                        <h5>✅ Avantages Étudiants</h5>
                        <ul style="margin: 0; padding-left: 1.2rem;">
                            <li>Tarifs préférentiels exclusifs</li>
                            <li>Paiement en plusieurs fois possible</li>
                            <li>Remboursement sous 7 jours</li>
                            <li>Support client dédié</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">✅ Paiement Réussi !</div>
            <p>Votre paiement a été traité avec succès.</p>
            <div class="transaction-details" id="transaction-info">
            </div>
            <div style="margin-top: 2rem;">
                <button class="btn btn-primary" onclick="closeSuccessModal()">
                    Continuer
                </button>
            </div>
        </div>
    </div>
    <div class="notification" id="notification">
        ✅ <span id="notification-text">Action effectuée avec succès !</span>
    </div>

    <script>
        let selectedServiceId = null;
        let selectedServicePrice = 0;
        function selectService(serviceId, serviceName, servicePrice) {
            document.querySelectorAll('.service-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            selectedServiceId = serviceId;
            selectedServicePrice = servicePrice;
            document.getElementById('selected_service_id').value = serviceId;
            document.getElementById('selected-service-name').textContent = serviceName;
            document.getElementById('selected-service-price').textContent = servicePrice.toFixed(2) + ' €';
            const tva = servicePrice * 0.20;
            const total = servicePrice + tva;
            
            document.getElementById('tva-amount').textContent = tva.toFixed(2) + ' €';
            document.getElementById('total-amount').textContent = total.toFixed(2) + ' €';
            checkFormValidity();
        }
        function selectCardType(cardType) {
            document.querySelectorAll('.card-type').forEach(type => {
                type.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('type_carte').value = cardType;
            const numeroCarteInput = document.getElementById('numero_carte');
            switch(cardType) {
                case 'American Express':
                    numeroCarteInput.placeholder = '1234 567890 12345';
                    numeroCarteInput.maxLength = 17;
                    break;
                case 'PayPal':
                    numeroCarteInput.placeholder = 'Numéro de compte PayPal';
                    numeroCarteInput.maxLength = 25;
                    break;
                default:
                    numeroCarteInput.placeholder = '1234 5678 9012 3456';
                    numeroCarteInput.maxLength = 19;
            }
            
            checkFormValidity();
        }
        function formatCardNumber(input) {
            let value = input.value.replace(/\D/g, '');
            let formattedValue = '';
            
            const cardType = document.getElementById('type_carte').value;
            
            if (cardType === 'American Express') {
                for (let i = 0; i < value.length; i++) {
                    if (i === 4 || i === 10) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
            } else {
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
            }
            
            input.value = formattedValue;
        }
        function formatExpiryDate(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            input.value = value;
        }
        function checkFormValidity() {
            const serviceSelected = selectedServiceId !== null;
            const cardTypeSelected = document.getElementById('type_carte').value !== '';
            const payButton = document.getElementById('payButton');
            
            if (serviceSelected && cardTypeSelected) {
                payButton.disabled = false;
                payButton.style.opacity = '1';
            } else {
                payButton.disabled = true;
                payButton.style.opacity = '0.5';
            }
        }
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!selectedServiceId) {
                showNotification('Veuillez sélectionner un service', 'error');
                return;
            }
            const requiredFields = this.querySelectorAll('input[required], select[required]');
            let allValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc2626';
                    allValid = false;
                } else {
                    field.style.borderColor = '#e5e7eb';
                }
            });
            
            if (!allValid) {
                showNotification('Veuillez remplir tous les champs obligatoires', 'error');
                return;
            }
            const numeroCarteValue = document.getElementById('numero_carte').value.replace(/\s/g, '');
            if (numeroCarteValue.length < 13 || numeroCarteValue.length > 19) {
                showNotification('Numéro de carte invalide', 'error');
                return;
            }
            const dateExpiration = document.getElementById('date_expiration').value;
            if (!/^\d{2}\/\d{2}$/.test(dateExpiration)) {
                showNotification('Format de date d\'expiration invalide (MM/AA)', 'error');
                return;
            }
            const payButton = document.getElementById('payButton');
            payButton.disabled = true;
            payButton.textContent = '⏳ Traitement en cours...';
            const formData = new FormData(this);
            formData.append('action', 'process_payment');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal(data);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
                console.error('Erreur:', error);
            })
            .finally(() => {
                payButton.disabled = false;
                payButton.textContent = '🔒 Finaliser le Paiement';
            });
        });
        function showSuccessModal(data) {
            const transactionInfo = document.getElementById('transaction-info');
            transactionInfo.innerHTML = `
                <strong>Service acheté :</strong> ${data.service}<br>
                <strong>Montant payé :</strong> ${data.montant} €<br>
                <strong>Numéro de transaction :</strong> ${data.numero_transaction}<br>
                <strong>Date :</strong> ${new Date().toLocaleDateString('fr-FR')}
            `;
            
            document.getElementById('successModal').style.display = 'block';
            showNotification(data.message, 'success');
        }
        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
            window.location.href = 'mes_reservations.php';
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
            }, 4000);
        }
        window.onclick = function(event) {
            const modal = document.getElementById('successModal');
            if (event.target === modal) {
                closeSuccessModal();
            }
        }

        document.getElementById('nom_carte').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        document.getElementById('code_postal').addEventListener('input', function() {
            const value = this.value;
            if (value.length === 5 && /^\d{5}$/.test(value)) {
                this.style.borderColor = '#059669';
            } else if (value.length > 0) {
                this.style.borderColor = '#dc2626';
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const userEmail = '<?= htmlspecialchars($user['email'] ?? '') ?>';
            if (userEmail.includes('@')) {
                document.getElementById('pays').value = 'France';
            }
        });
    </script>
</body>
</html>