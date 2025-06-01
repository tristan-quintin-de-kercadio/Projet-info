<?php
require_once 'config.php';
require_once 'auth.php';
requireAdmin();
$user = getCurrentUser();
function getAllPaiements($limit = 50, $offset = 0) {
    $conn = getDbConnection();
    
    $query = "SELECT p.*, 
                     u.nom as client_nom, 
                     u.prenom as client_prenom, 
                     u.email as client_email,
                     af.ville, af.pays
              FROM paiements p
              JOIN utilisateurs u ON p.utilisateur_id = u.id
              JOIN adresses_facturation af ON p.adresse_facturation_id = af.id
              ORDER BY p.date_paiement DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}
function getStatistiquesPaiements() {
    $conn = getDbConnection();
    
    $query = "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN statut_paiement = 'approuve' THEN 1 ELSE 0 END) as transactions_approuvees,
                SUM(CASE WHEN statut_paiement = 'refuse' THEN 1 ELSE 0 END) as transactions_refusees,
                SUM(CASE WHEN statut_paiement = 'rembourse' THEN 1 ELSE 0 END) as transactions_remboursees,
                SUM(CASE WHEN statut_paiement = 'approuve' THEN montant_total ELSE 0 END) as revenus_total,
                AVG(CASE WHEN statut_paiement = 'approuve' THEN montant_total ELSE NULL END) as panier_moyen,
                COUNT(DISTINCT utilisateur_id) as clients_uniques,
                COUNT(CASE WHEN DATE(date_paiement) = CURDATE() THEN 1 END) as transactions_aujourd_hui,
                SUM(CASE WHEN DATE(date_paiement) = CURDATE() AND statut_paiement = 'approuve' THEN montant_total ELSE 0 END) as revenus_aujourd_hui
              FROM paiements";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetch();
}
function getRevenusByService() {
    $conn = getDbConnection();
    
    $query = "SELECT 
                service_nom,
                COUNT(*) as nombre_ventes,
                SUM(montant) as revenus_ht,
                SUM(montant_total) as revenus_ttc,
                AVG(montant_total) as prix_moyen
              FROM paiements 
              WHERE statut_paiement = 'approuve'
              GROUP BY service_id, service_nom
              ORDER BY revenus_ttc DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'refund_payment':
            try {
                $conn = getDbConnection();
                $paiement_id = (int)$_POST['paiement_id'];
                $motif = trim($_POST['motif']);
                $query = "CALL RembourserPaiement(:paiement_id, :motif, :admin_id)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':paiement_id', $paiement_id);
                $stmt->bindParam(':motif', $motif);
                $stmt->bindParam(':admin_id', $user['id']);
                
                $stmt->execute();
                $result = $stmt->fetch();
                
                echo json_encode($result);
                
            } catch (Exception $e) {
                echo json_encode(['result' => 'ERROR', 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_payment_details':
            try {
                $conn = getDbConnection();
                $paiement_id = (int)$_POST['paiement_id'];
                
                $query = "SELECT p.*, 
                                 u.nom as client_nom, u.prenom as client_prenom, u.email as client_email,
                                 af.nom as fact_nom, af.prenom as fact_prenom, af.adresse_ligne1, af.adresse_ligne2,
                                 af.ville, af.code_postal, af.pays, af.telephone, af.carte_etudiant
                          FROM paiements p
                          JOIN utilisateurs u ON p.utilisateur_id = u.id
                          JOIN adresses_facturation af ON p.adresse_facturation_id = af.id
                          WHERE p.id = :paiement_id";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':paiement_id', $paiement_id);
                $stmt->execute();
                
                $paiement = $stmt->fetch();
                
                if ($paiement) {
                    echo json_encode(['success' => true, 'data' => $paiement]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Paiement non trouvé']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
    }
}

$stats = getStatistiquesPaiements();
$paiements = getAllPaiements(100, 0);
$revenus_services = getRevenusByService();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration des Paiements - Sportify</title>
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

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: background-color 0.3s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(255,255,255,0.2);
        }
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
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
            border-left: 4px solid #2563eb;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card.revenue {
            border-left-color: #10b981;
        }

        .stat-card.warning {
            border-left-color: #f59e0b;
        }

        .stat-card.danger {
            border-left-color: #dc2626;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 0.5rem;
        }

        .stat-number.revenue {
            color: #10b981;
        }

        .stat-number.warning {
            color: #f59e0b;
        }

        .stat-number.danger {
            color: #dc2626;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        .section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .section-title {
            color: #1e40af;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-approuve {
            background: #dcfce7;
            color: #166534;
        }

        .status-refuse {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-rembourse {
            background: #fef3c7;
            color: #d97706;
        }

        .status-en_attente {
            background: #f3f4f6;
            color: #6b7280;
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
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-info {
            background: #3b82f6;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-success {
            background: #10b981;
            color: white;
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
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
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

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .detail-section {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 10px;
        }

        .detail-section h4 {
            color: #2563eb;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }

        .detail-label {
            font-weight: 600;
            color: #374151;
        }

        .detail-value {
            color: #64748b;
        }
        .refund-form {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
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
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .chart-bar {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }

        .chart-label {
            width: 150px;
            font-weight: 600;
            color: #374151;
        }

        .chart-progress {
            flex: 1;
            height: 20px;
            background: #f3f4f6;
            border-radius: 10px;
            margin: 0 1rem;
            overflow: hidden;
        }

        .chart-fill {
            height: 100%;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 10px;
            transition: width 0.3s;
        }

        .chart-value {
            font-weight: 600;
            color: #2563eb;
        }
        @media (max-width: 768px) {
            .admin-container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                font-size: 0.9rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <nav class="admin-nav">
            <div class="admin-logo">
                💳 Administration Paiements - Sportify
            </div>
            <div class="nav-links">
                <a href="admin_dashboard.php">🏠 Dashboard</a>
                <a href="admin_payments.php" class="active">💳 Paiements</a>
                <a href="logout.php">🚪 Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="admin-container">
        <div class="stats-grid">
            <div class="stat-card revenue">
                <div class="stat-number revenue"><?= number_format($stats['revenus_total'] ?? 0, 2) ?> €</div>
                <div class="stat-label">Revenus Total (TTC)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_transactions'] ?? 0 ?></div>
                <div class="stat-label">Transactions Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['transactions_approuvees'] ?? 0 ?></div>
                <div class="stat-label">Paiements Approuvés</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number warning"><?= $stats['transactions_refusees'] ?? 0 ?></div>
                <div class="stat-label">Paiements Refusés</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-number danger"><?= $stats['transactions_remboursees'] ?? 0 ?></div>
                <div class="stat-label">Remboursements</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-number revenue"><?= number_format($stats['panier_moyen'] ?? 0, 2) ?> €</div>
                <div class="stat-label">Panier Moyen</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['clients_uniques'] ?? 0 ?></div>
                <div class="stat-label">Clients Uniques</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-number revenue"><?= number_format($stats['revenus_aujourd_hui'] ?? 0, 2) ?> €</div>
                <div class="stat-label">Revenus Aujourd'hui</div>
            </div>
        </div>
        <div class="chart-container">
            <h3 style="color: #1e40af; margin-bottom: 1.5rem;">📊 Revenus par Service</h3>
            <?php if (!empty($revenus_services)): ?>
                <?php 
                $max_revenus = max(array_column($revenus_services, 'revenus_ttc'));
                foreach ($revenus_services as $service): 
                    $pourcentage = $max_revenus > 0 ? ($service['revenus_ttc'] / $max_revenus) * 100 : 0;
                ?>
                <div class="chart-bar">
                    <div class="chart-label"><?= htmlspecialchars($service['service_nom']) ?></div>
                    <div class="chart-progress">
                        <div class="chart-fill" style="width: <?= $pourcentage ?>%"></div>
                    </div>
                    <div class="chart-value"><?= number_format($service['revenus_ttc'], 0) ?> € (<?= $service['nombre_ventes'] ?>)</div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #64748b;">Aucune donnée de revenus disponible</p>
            <?php endif; ?>
        </div>
        <div class="section">
            <h2 class="section-title">💳 Transactions Récentes</h2>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID Transaction</th>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paiements as $paiement): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($paiement['numero_transaction']) ?></strong><br>
                            <small style="color: #64748b;">ID: <?= $paiement['id'] ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($paiement['client_prenom'] . ' ' . $paiement['client_nom']) ?></strong><br>
                            <small style="color: #64748b;"><?= htmlspecialchars($paiement['client_email']) ?></small><br>
                            <small style="color: #64748b;"><?= htmlspecialchars($paiement['ville'] . ', ' . $paiement['pays']) ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($paiement['service_nom']) ?></strong><br>
                            <small style="color: #64748b;"><?= htmlspecialchars($paiement['type_carte']) ?> <?= htmlspecialchars($paiement['numero_carte_masque']) ?></small>
                        </td>
                        <td>
                            <strong><?= number_format($paiement['montant_total'], 2) ?> €</strong><br>
                            <small style="color: #64748b;">HT: <?= number_format($paiement['montant'], 2) ?> €</small>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $paiement['statut_paiement'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $paiement['statut_paiement'])) ?>
                            </span>
                        </td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($paiement['date_paiement'])) ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-sm btn-info" onclick="viewPaymentDetails(<?= $paiement['id'] ?>)">
                                    👁️ Détails
                                </button>
                                <?php if ($paiement['statut_paiement'] === 'approuve'): ?>
                                <button class="btn-sm btn-warning" onclick="refundPayment(<?= $paiement['id'] ?>, '<?= htmlspecialchars($paiement['numero_transaction']) ?>')">
                                    💰 Rembourser
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div id="paymentDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">📋 Détails du Paiement</h2>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            <div id="paymentDetailsContent">
            </div>
        </div>
    </div>
    <div id="refundModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">💰 Remboursement</h2>
                <span class="close" onclick="closeRefundModal()">&times;</span>
            </div>
            <div class="refund-form">
                <h4>⚠️ Confirmer le remboursement</h4>
                <p>Transaction : <strong id="refund-transaction-id"></strong></p>
                <form id="refundForm">
                    <input type="hidden" id="refund-paiement-id" name="paiement_id">
                    <div class="form-group">
                        <label for="refund-motif">Motif du remboursement <span style="color: #dc2626;">*</span></label>
                        <textarea id="refund-motif" name="motif" required placeholder="Expliquez la raison du remboursement..."></textarea>
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button type="submit" class="btn-sm btn-danger">
                            ✅ Confirmer le Remboursement
                        </button>
                        <button type="button" class="btn-sm" onclick="closeRefundModal()">
                            ❌ Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function viewPaymentDetails(paiementId) {
            const formData = new FormData();
            formData.append('action', 'get_payment_details');
            formData.append('paiement_id', paiementId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPaymentDetails(data.data);
                    document.getElementById('paymentDetailsModal').style.display = 'block';
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erreur de connexion: ' + error);
            });
        }

        function displayPaymentDetails(payment) {
            const statusClass = 'status-' + payment.statut_paiement;
            const statusText = payment.statut_paiement.replace('_', ' ');
            
            document.getElementById('paymentDetailsContent').innerHTML = `
                <div class="details-grid">
                    <div class="detail-section">
                        <h4>💳 Informations de Paiement</h4>
                        <div class="detail-item">
                            <span class="detail-label">N° Transaction:</span>
                            <span class="detail-value">${payment.numero_transaction}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Service:</span>
                            <span class="detail-value">${payment.service_nom}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Montant HT:</span>
                            <span class="detail-value">${parseFloat(payment.montant).toFixed(2)} €</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">TVA:</span>
                            <span class="detail-value">${parseFloat(payment.tva).toFixed(2)} €</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><strong>Total TTC:</strong></span>
                            <span class="detail-value"><strong>${parseFloat(payment.montant_total).toFixed(2)} €</strong></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Statut:</span>
                            <span class="status-badge ${statusClass}">${statusText.charAt(0).toUpperCase() + statusText.slice(1)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date paiement:</span>
                            <span class="detail-value">${new Date(payment.date_paiement).toLocaleString('fr-FR')}</span>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4>👤 Informations Client</h4>
                        <div class="detail-item">
                            <span class="detail-label">Nom complet:</span>
                            <span class="detail-value">${payment.client_prenom} ${payment.client_nom}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value">${payment.client_email}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Téléphone:</span>
                            <span class="detail-value">${payment.telephone}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Carte Étudiant:</span>
                            <span class="detail-value">${payment.carte_etudiant}</span>
                        </div>
                    </div>
                </div>
                
                <div class="details-grid" style="margin-top: 1.5rem;">
                    <div class="detail-section">
                        <h4>🏠 Adresse de Facturation</h4>
                        <div class="detail-item">
                            <span class="detail-label">Nom/Prénom:</span>
                            <span class="detail-value">${payment.fact_prenom} ${payment.fact_nom}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Adresse:</span>
                            <span class="detail-value">${payment.adresse_ligne1}</span>
                        </div>
                        ${payment.adresse_ligne2 ? `<div class="detail-item">
                            <span class="detail-label">Complément:</span>
                            <span class="detail-value">${payment.adresse_ligne2}</span>
                        </div>` : ''}
                        <div class="detail-item">
                            <span class="detail-label">Ville:</span>
                            <span class="detail-value">${payment.code_postal} ${payment.ville}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Pays:</span>
                            <span class="detail-value">${payment.pays}</span>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4>💳 Méthode de Paiement</h4>
                        <div class="detail-item">
                            <span class="detail-label">Type de carte:</span>
                            <span class="detail-value">${payment.type_carte}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Numéro:</span>
                            <span class="detail-value">${payment.numero_carte_masque}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Nom sur carte:</span>
                            <span class="detail-value">${payment.nom_carte}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Expiration:</span>
                            <span class="detail-value">${payment.date_expiration}</span>
                        </div>
                        ${payment.notes ? `<div class="detail-item">
                            <span class="detail-label">Notes:</span>
                            <span class="detail-value">${payment.notes}</span>
                        </div>` : ''}
                    </div>
                </div>
            `;
        }
        function closePaymentModal() {
            document.getElementById('paymentDetailsModal').style.display = 'none';
        }
        function refundPayment(paiementId, transactionId) {
            document.getElementById('refund-paiement-id').value = paiementId;
            document.getElementById('refund-transaction-id').textContent = transactionId;
            document.getElementById('refundModal').style.display = 'block';
        }
        function closeRefundModal() {
            document.getElementById('refundModal').style.display = 'none';
            document.getElementById('refundForm').reset();
        }
        document.getElementById('refundForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('⚠️ Êtes-vous sûr de vouloir effectuer ce remboursement ?\n\nCette action est irréversible.')) {
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'refund_payment');
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Traitement...';
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.result === 'SUCCESS') {
                    alert('✅ ' + data.message);
                    closeRefundModal();
                    location.reload(); 
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Erreur de connexion: ' + error);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
        window.onclick = function(event) {
            const paymentModal = document.getElementById('paymentDetailsModal');
            const refundModal = document.getElementById('refundModal');
            
            if (event.target === paymentModal) {
                closePaymentModal();
            }
            if (event.target === refundModal) {
                closeRefundModal();
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const chartFills = document.querySelectorAll('.chart-fill');
            chartFills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0%';
                setTimeout(() => {
                    fill.style.width = width;
                }, 500);
            });

        });

        function exportPayments() {
            alert('🚧 Fonction d\'export en cours de développement...');
        }

        function printPaymentDetails(paiementId) {
  
            window.print();
        }
        function addSearchFunctionality() {
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = '🔍 Rechercher par nom, email, transaction...';
            searchInput.style.cssText = `
                width: 100%;
                padding: 0.75rem;
                margin-bottom: 1rem;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                font-size: 1rem;
            `;
            
            const table = document.querySelector('.data-table');
            table.parentNode.insertBefore(searchInput, table);
            
            searchInput.addEventListener('input', function() {
                const filter = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            addSearchFunctionality();
        });
        function refreshStats() {
            location.reload();
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePaymentModal();
                closeRefundModal();
            }
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshStats();
            }
        });
        function showTooltip(element, message) {
        }
        function validateAmount(amount) {
            return !isNaN(amount) && amount > 0;
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(amount);
        }
        function logAdminAction(action, details) {

            console.log(`Admin Action: ${action}`, details);
        }
    </script>
</body>
</html>