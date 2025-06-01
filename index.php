<?php
require_once 'config.php';
require_once 'auth.php';
$auth = new AuthManager();
$user = null;
$is_logged_in = false;

if ($auth->isLoggedIn()) {
    $user = getCurrentUser();
    $is_logged_in = true;
}
function getAllCoachsWithAvailability() {
    $conn = getDbConnection();
    
    $query = "SELECT c.*, 
                     COUNT(cr.id) as nb_creneaux,
                     SUM(CASE WHEN cr.statut = 'libre' THEN 1 ELSE 0 END) as creneaux_libres
              FROM coachs c 
              LEFT JOIN creneaux cr ON c.id = cr.coach_id 
              WHERE c.actif = TRUE
              GROUP BY c.id
              ORDER BY c.id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll();
}
function getCoachStatusDisplay($coach) {
    $status_class = '';
    $status_text = '';
    $is_available = false;
    
    switch ($coach['statut']) {
        case 'disponible':
            $status_class = 'status-available';
            $status_text = '🟢 Disponible';
            $is_available = true;
            break;
        case 'occupe':
            $status_class = 'status-busy';
            $status_text = '🔴 En séance';
            $is_available = false;
            break;
        case 'absent':
            $status_class = 'status-busy';
            $status_text = '⚫ Absent';
            $is_available = false;
            break;
        default:
            $status_class = 'status-busy';
            $status_text = '🔴 Indisponible';
            $is_available = false;
    }
    
    return [
        'class' => $status_class,
        'text' => $status_text,
        'available' => $is_available
    ];
}

$all_coachs = getAllCoachsWithAvailability();
$coachs_by_category = [
    'musculation' => null,
    'fitness' => null,
    'cardio' => null,
    'collectifs' => null,
    'basketball' => null,
    'football' => null,
    'rugby' => null,
    'tennis' => null,
    'natation' => null,
    'plongee' => null
];
foreach ($all_coachs as $coach) {
    switch ($coach['specialite']) {
        case 'Spécialiste Musculation':
            $coachs_by_category['musculation'] = $coach;
            break;
        case 'Spécialiste Fitness':
            $coachs_by_category['fitness'] = $coach;
            break;
        case 'Spécialiste Cardio-Training':
            $coachs_by_category['cardio'] = $coach;
            break;
        case 'Instructrice Cours Collectifs':
            $coachs_by_category['collectifs'] = $coach;
            break;
        case 'Coach Basketball':
            $coachs_by_category['basketball'] = $coach;
            break;
        case 'Coach Football':
            $coachs_by_category['football'] = $coach;
            break;
        case 'Coach Rugby':
            $coachs_by_category['rugby'] = $coach;
            break;
        case 'Coach Tennis':
            $coachs_by_category['tennis'] = $coach;
            break;
        case 'Coach Natation':
            $coachs_by_category['natation'] = $coach;
            break;
        case 'Coach Plongée':
            $coachs_by_category['plongee'] = $coach;
            break;
    }
}
$carousel_coachs = array_slice($all_coachs, 0, 3);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sportify - Accueil | Omnes Education</title>
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

        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            align-items: center;
        }

        .nav-menu li {
            margin-left: 2rem;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
        }

        .nav-menu a:hover {
            color: #f59e0b;
        }
        .user-menu {
            position: relative;
        }

        .user-menu-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            color: white;
            text-decoration: none;
        }

        .dropdown-arrow {
            font-size: 0.8rem;
            transition: transform 0.3s;
        }

        .user-menu-btn:hover .dropdown-arrow {
            transform: rotate(180deg);
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            min-width: 280px;
            display: none;
            z-index: 1000;
            margin-top: 0.5rem;
            border: 1px solid #e5e7eb;
        }

        .user-dropdown.show {
            display: block;
        }

        .user-info {
            padding: 1.5rem;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border-radius: 12px 12px 0 0;
            text-align: center;
        }

        .user-info strong {
            display: block;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .user-info small {
            display: block;
            opacity: 0.8;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .user-type {
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .dropdown-item {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.3s;
            border: none;
            width: 100%;
            text-align: left;
        }

        .dropdown-item:hover {
            background: #f3f4f6;
            color: #2563eb;
        }

        .dropdown-item.logout {
            color: #dc2626;
        }

        .dropdown-item.logout:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 0.5rem 0;
        }
        .hero {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        .cta-button {
            background-color: #f59e0b;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .cta-button:hover {
            background-color: #d97706;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .weekly-event {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 2rem 0;
            overflow: hidden;
        }

        .event-header {
            background: #f59e0b;
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .event-content {
            padding: 2rem;
        }

        .event-badge {
            background: #2563eb;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .section {
            padding: 3rem 0;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #1f2937;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .activity-card {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .activity-card:hover {
            transform: translateY(-5px);
        }

        .sport-card {
            text-align: center;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
        }

        .sport-card:hover {
            border-color: #f59e0b;
            transform: translateY(-3px);
        }

        .sport-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .coach-status {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .status-available {
            background: rgba(34, 197, 94, 0.1);
            color: #059669;
        }

        .status-busy {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        .contact-buttons {
            margin-top: 1rem;
        }

        .contact-btn {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            margin: 0.2rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .contact-btn:hover {
            background: #d97706;
        }

        .contact-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .carousel {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .carousel-item {
            display: none;
            text-align: center;
        }

        .carousel-item.active {
            display: block;
        }

        .carousel img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 15px;
            object-fit: cover;
        }

        .coach-name {
            background: #2563eb;
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 20px;
            margin-top: 1rem;
            display: inline-block;
        }

        .carousel-controls {
            text-align: center;
            margin-top: 1rem;
        }

        .carousel-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            margin: 0 0.5rem;
            cursor: pointer;
        }

        .carousel-btn:hover {
            background: #1e40af;
        }
        .news-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #2563eb;
        }

        .news-date {
            color: #f59e0b;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .map-container {
            margin: 2rem 0;
            text-align: center;
        }
        .search-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 2rem 0;
            display: none;
        }

        .search-box {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 25px;
            font-size: 1.1rem;
        }

        .search-btn {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 25px;
            cursor: pointer;
        }
        .premium-service-card {
            background: rgba(255,255,255,0.1);
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            backdrop-filter: blur(10px);
            position: relative;
        }

        .premium-service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .premium-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .premium-price {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 1rem 0;
            color: #fff;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .premium-btn {
            background: white;
            color: #f59e0b;
            text-decoration: none;
            padding: 1rem 2rem;
            border-radius: 25px;
            font-weight: bold;
            display: inline-block;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .premium-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            color: #d97706;
        }


        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(220, 38, 38, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
            }
        }
        .footer {
            background: #1f2937;
            color: white;
            padding: 3rem 0 2rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .footer h5 {
            color: #f59e0b;
            margin-bottom: 1rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .contact-item i {
            margin-right: 1rem;
            color: #f59e0b;
        }
        @media (max-width: 1024px) {
            .user-dropdown {
                position: fixed;
                top: 70px;
                left: 1rem;
                right: 1rem;
                width: auto;
                min-width: auto;
            }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .nav-menu {
                flex-direction: column;
                gap: 0.5rem;
                align-items: stretch;
            }
            
            .nav-menu li {
                margin: 0.25rem 0;
            }
            
            .user-menu {
                width: 100%;
            }
            
            .user-menu-btn {
                justify-content: center;
            }

            .premium-service-card {
                padding: 1.5rem;
            }
            
            .premium-icon {
                font-size: 2.5rem;
            }
            
            .premium-price {
                font-size: 1.3rem;
            }
            
            .premium-btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo">🏋️ Sportify</a>
            <ul class="nav-menu">
                <li><a href="#accueil">Accueil</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#recherche" onclick="toggleSearch()">Recherche</a></li>
                <li><a href="#coachs">Nos Coachs</a></li>
                <li><a href="#rendez-vous">Rendez-vous</a></li>
                
                <?php if ($is_logged_in): ?>
                    <li><a href="checkout.php">💎 Services Premium</a></li>
                    <li class="user-menu">
                        <a href="#" onclick="toggleUserMenu()" class="user-menu-btn">
                            👤 <?= htmlspecialchars($user['prenom']) ?>
                            <span class="dropdown-arrow">▼</span>
                        </a>
                        <div class="user-dropdown" id="userDropdown">
                            <div class="user-info">
                                <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
                                <small><?= htmlspecialchars($user['email']) ?></small>
                                <span class="user-type"><?= ucfirst($user['type']) ?></span>
                            </div>
                            <div class="dropdown-divider"></div>
                            
                            <?php if ($user['type'] === 'administrateur'): ?>
                                <a href="admin_dashboard.php" class="dropdown-item">
                                    🔐 Administration
                                </a>
                                <a href="admin_payments.php" class="dropdown-item">
                                    💳 Gestion Paiements
                                </a>
                            <?php elseif ($user['type'] === 'coach'): ?>
                                <a href="coach_dashboard.php" class="dropdown-item">
                                    📊 Mon Dashboard
                                </a>
                            <?php endif; ?>
                            
                            <a href="mon_profil.php" class="dropdown-item">
                                👤 Mon Profil
                            </a>
                            <a href="mes_reservations.php" class="dropdown-item">
                                📅 Mes Réservations
                            </a>
                            <a href="checkout.php" class="dropdown-item">
                                💎 Services Premium
                            </a>
                            <a href="parametres.php" class="dropdown-item">
                                ⚙️ Paramètres
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item logout">
                                🚪 Déconnexion
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li><a href="login.php">Votre Compte</a></li>
                <?php endif; ?>
                
                <li><a href="#contact">Contact</a></li>
            </ul>
        </div>
    </nav>
    <section class="hero" id="accueil">
        <div class="hero-container">
            <h1>Bienvenue sur <span style="color: #f59e0b;">Sportify</span></h1>
            <p>Votre plateforme dédiée aux rendez-vous sportifs à Omnes Education.<br>
            Connectez-vous avec nos spécialistes, réservez vos séances et atteignez vos objectifs sportifs !</p>
            <?php if ($is_logged_in): ?>
                <a href="#coachs" class="cta-button">📅 Prendre un rendez-vous</a>
            <?php else: ?>
                <a href="login.php" class="cta-button">📅 Se connecter pour réserver</a>
            <?php endif; ?>
        </div>
    </section>
    <div class="container">
        <div class="search-container" id="searchContainer">
            <h3 style="color: #2563eb; text-align: center; margin-bottom: 1rem;">🔍 Recherche Rapide</h3>
            <div class="search-box">
                <input type="text" class="search-input" placeholder="Nom du coach, spécialité ou service...">
                <button class="search-btn">Rechercher</button>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="weekly-event">
            <div class="event-header">
                <h2>⭐ Événement de la semaine</h2>
            </div>
            <div class="event-content">
                <span class="event-badge">📅 29 Mai 2025</span>
                <h3>Match de Rugby : Omnes Education vs Sup de PUB</h3>
                <p>Ne manquez pas le grand match de rugby qui opposera notre équipe d'Omnes Education 
                à nos rivaux traditionnels ! Venez encourager nos joueurs et vibrer au rythme du sport.</p>
                <p><strong>🕕 18h00 - 20h00 | 📍 Stade Omnes | 🎫 Entrée gratuite</strong></p>
            </div>
        </div>
    </div>
    <section class="section" id="coachs">
        <div class="container">
            <h2 class="section-title">Nos Spécialistes Sportifs</h2>
            
            <div class="carousel">
                <?php foreach ($carousel_coachs as $index => $coach): ?>
                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                    <img src="<?= htmlspecialchars($coach['photo']) ?>" alt="<?= htmlspecialchars($coach['prenom'] . ' ' . $coach['nom']) ?>">
                    <div class="coach-name"><?= htmlspecialchars($coach['prenom'] . ' ' . $coach['nom'] . ' - ' . $coach['specialite']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="carousel-controls">
                <button class="carousel-btn" onclick="previousSlide()">❮ Précédent</button>
                <button class="carousel-btn" onclick="nextSlide()">Suivant ❯</button>
            </div>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <h2 class="section-title">Bulletin Sportif de la Semaine</h2>
            <div class="grid-2">
                <div class="news-card">
                    <div class="news-date">26 Mai 2025</div>
                    <h4>🏆 Championnat Universitaire de Tennis</h4>
                    <p>Nos étudiants brillent au championnat inter-universitaire ! 
                    Marie Dupont se qualifie pour les demi-finales.</p>
                </div>
                <div class="news-card">
                    <div class="news-date">24 Mai 2025</div>
                    <h4>🏃‍♂️ Nouveau Programme de Running</h4>
                    <p>Découvrez notre nouveau programme d'entraînement running 
                    adapté à tous les niveaux, du débutant au coureur confirmé.</p>
                </div>
                <div class="news-card">
                    <div class="news-date">22 Mai 2025</div>
                    <h4>🤸‍♀️ Atelier Yoga & Bien-être</h4>
                    <p>Rejoignez nos séances de yoga hebdomadaires pour améliorer votre 
                    flexibilité et réduire le stress procuré par l'électromagnétisme.</p>
                </div>
                <div class="news-card">
                    <div class="news-date">20 Mai 2025</div>
                    <h4>🏀 Match Amical Basketball</h4>
                    <p>L'équipe de basketball d'Omnes affrontera l'équipe de l'ESSEC 
                    ce samedi dans un match amical très attendu.</p>
                </div>
            </div>
        </div>
    </section>
    <section class="section" id="services">
        <div class="container">
            <h2 class="section-title">Découvrez Nos Services</h2>
           <div class="card" style="background: #f59e0b; color: white; margin-bottom: 2rem;">
    <h3 style="text-align: center; color: white; margin-bottom: 1.5rem;">Services Premium</h3>
    <p style="text-align: center; margin-bottom: 2rem;">
        Services payants pour une expérience sportive personnalisée
    </p>
    
    <div class="grid">
        <div style="background: rgba(255,255,255,0.1); padding: 2rem; border-radius: 10px; text-align: center;">
            <h4 style="color: white; margin-bottom: 1rem;">Coaching Personnel</h4>
            <p style="margin-bottom: 1rem;">Séances individuelles avec nos coaches</p>
            <div style="font-size: 1.3rem; font-weight: bold; margin-bottom: 1rem;">45€/séance</div>
            <?php if ($is_logged_in): ?>
                <a href="checkout.php" style="background: white; color: #f59e0b; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 5px; font-weight: bold; display: inline-block;">
                    Réserver
                </a>
            <?php else: ?>
                <a href="login.php" style="background: white; color: #f59e0b; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 5px; font-weight: bold; display: inline-block;">
                    Se connecter
                </a>
            <?php endif; ?>
        </div>
        
        <div style="background: rgba(255,255,255,0.1); padding: 2rem; border-radius: 10px; text-align: center;">
            <h4 style="color: white; margin-bottom: 1rem;">Salle VIP</h4>
            <p style="margin-bottom: 1rem;">Accès aux équipements premium</p>
            <div style="font-size: 1.3rem; font-weight: bold; margin-bottom: 1rem;">89€/mois</div>
            <?php if ($is_logged_in): ?>
                <a href="checkout.php" style="background: white; color: #f59e0b; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 5px; font-weight: bold; display: inline-block;">
                    S'abonner
                </a>
            <?php else: ?>
                <a href="login.php" style="background: white; color: #f59e0b; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 5px; font-weight: bold; display: inline-block;">
                    Se connecter
                </a>
            <?php endif; ?>
        </div>
        
        <div style="background: rgba(255,255,255,0.1); padding: 2rem; border-radius: 10px; text-align: center;">
            <h4 style="color: white; margin-bottom: 1rem;">Nutrition</h4>
            <p style="margin-bottom: 1rem;">Plan alimentaire personnalisé</p>
            <div style="font-size: 1.3rem; font-weight: bold; margin-bottom: 1rem;">120€</div>
            <?php if ($is_logged_in): ?>
                <a href="checkout.php" style="background: white; color: #f59e0b; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 5px; font-weight: bold; display: inline-block;">
                    Commander
                </a>
            <?php else: ?>
                <a href="login.php" style="background: white; color: #f59e0b; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 5px; font-weight: bold; display: inline-block;">
                    Se connecter
                </a>
            <?php endif; ?>
        </div>
        
        <div style="background: rgba(255,255,255,0.1); padding: 2rem; border-radius: 10px; text-align: center;">
            <h4 style="color: white; margin-bottom: 1rem;">Cours Natation</h4>
            <p style="margin-bottom: 1rem;">Leçons privées de natation</p>
            <div style="font-size: 1.3rem; font-weight: bold; margin-bottom: 1rem;">55€/séance</div>
            <?php if ($is_logged_in): ?>
                <a href="checkout.php" style="background: white; color: #f59e0b; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 5px; font-weight: bold; display: inline-block;">
                    Réserver
                </a>
            <?php else: ?>
                <a href="login.php" style="background: white; color: #f59e0b; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 5px; font-weight: bold; display: inline-block;">
                    Se connecter
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 2rem; padding: 1rem; background: rgba(255,255,255,0.1); border-radius: 10px;">
        <h5 style="color: white; margin-bottom: 1rem;">Avantages Étudiants</h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div>
                <strong>Tarifs Réduits</strong><br>
                <small>Prix spéciaux étudiants</small>
            </div>
            <div>
                <strong>Paiement Flexible</strong><br>
                <small>Plusieurs fois possible</small>
            </div>
            <div>
                <strong>Support Dédié</strong><br>
                <small>Aide personnalisée</small>
            </div>
        </div>
    </div>
</div>
            <div class="card">
                <h3 style="text-align: center; color: #2563eb;">🏋️ Activités Sportives</h3>
                <p style="text-align: center;">Participez à nos activités sportives ouvertes à tous les membres d'Omnes Education</p>
                
                <div class="grid">
                    <?php 
                    $musculation_coach = $coachs_by_category['musculation'];
                    $musculation_status = $musculation_coach ? getCoachStatusDisplay($musculation_coach) : ['class' => 'status-busy', 'text' => '🔴 Aucun coach'];
                    ?>
                    <a href="coach.php?coach_id=<?= $musculation_coach ? $musculation_coach['id'] : 1 ?>" style="text-decoration:none; color:inherit;">
                        <div class="activity-card">
                            <h5>💪 Musculation</h5>
                            <p>Renforcez votre corps avec nos équipements modernes</p>
                            <div class="coach-status <?= $musculation_status['class'] ?>">
                                <?= $musculation_status['text'] ?> 
                                <?= $musculation_coach ? htmlspecialchars($musculation_coach['prenom']) : '' ?>
                            </div>
                        </div>
                    </a>
                    
                    <?php 
                    $fitness_coach = $coachs_by_category['fitness'];
                    $fitness_status = $fitness_coach ? getCoachStatusDisplay($fitness_coach) : ['class' => 'status-busy', 'text' => '🔴 Aucun coach'];
                    ?>
                    <a href="coach.php?coach_id=<?= $fitness_coach ? $fitness_coach['id'] : 8 ?>" style="text-decoration:none; color:inherit;">
                        <div class="activity-card">
                            <h5>❤️ Fitness</h5>
                            <p>Améliorez votre condition physique générale</p>
                            <div class="coach-status <?= $fitness_status['class'] ?>">
                                <?= $fitness_status['text'] ?> 
                                <?= $fitness_coach ? htmlspecialchars($fitness_coach['prenom']) : 'Sarah' ?>
                            </div>
                        </div>
                    </a>
                    
                    <?php 
                    $cardio_coach = $coachs_by_category['cardio'];
                    $cardio_status = $cardio_coach ? getCoachStatusDisplay($cardio_coach) : ['class' => 'status-available', 'text' => '🟢 Disponible'];
                    ?>
                    <a href="coach.php?coach_id=<?= $cardio_coach ? $cardio_coach['id'] : 9 ?>" style="text-decoration:none; color:inherit;">
                        <div class="activity-card">
                            <h5>🏃 Cardio-Training</h5>
                            <p>Boostez votre endurance cardiovasculaire</p>
                            <div class="coach-status <?= $cardio_status['class'] ?>">
                                <?= $cardio_status['text'] ?> 
                                <?= $cardio_coach ? htmlspecialchars($cardio_coach['prenom']) : 'Emma' ?>
                            </div>
                        </div>
                    </a>
                    
                    <?php 
                    $collectifs_coach = $coachs_by_category['collectifs'];
                    $collectifs_status = $collectifs_coach ? getCoachStatusDisplay($collectifs_coach) : ['class' => 'status-available', 'text' => '🟢 Disponible'];
                    ?>
                    <a href="coach.php?coach_id=<?= $collectifs_coach ? $collectifs_coach['id'] : 10 ?>" style="text-decoration:none; color:inherit;">
                        <div class="activity-card">
                            <h5>👥 Cours Collectifs</h5>
                            <p>Motivez-vous en groupe avec nos cours variés</p>
                            <div class="coach-status <?= $collectifs_status['class'] ?>">
                                <?= $collectifs_status['text'] ?> 
                                <?= $collectifs_coach ? htmlspecialchars($collectifs_coach['prenom']) : 'Julie' ?>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            <div class="card">
                <h3 style="text-align: center; color: #2563eb;">🏆 Sports de Compétition</h3>
                <p style="text-align: center;">Excellez dans votre discipline avec nos coachs spécialisés</p>
                
                <div class="grid">
                    <?php 
                    $basketball_coach = $coachs_by_category['basketball'];
                    $basketball_status = $basketball_coach ? getCoachStatusDisplay($basketball_coach) : ['class' => 'status-busy', 'text' => '🔴 Aucun coach', 'available' => false];
                    ?>
                    <a href="coach.php?coach_id=<?= $basketball_coach ? $basketball_coach['id'] : 2 ?>" style="text-decoration:none; color:inherit;">
                        <div class="sport-card">
                            <div class="sport-icon">🏀</div>
                            <h5>Basketball</h5>
                            <p><?= $basketball_coach ? htmlspecialchars($basketball_coach['prenom'] . ' ' . $basketball_coach['nom']) : 'Coach Pierre Dubois' ?></p>
                            <div class="coach-status <?= $basketball_status['class'] ?>"><?= $basketball_status['text'] ?></div>
                            <div class="contact-buttons">
                                <button class="contact-btn" onclick="contactCoach('<?= $basketball_coach ? $basketball_coach['prenom'] : 'Pierre' ?>', 'message')" <?= !$basketball_status['available'] ? 'disabled' : '' ?>>💬</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $basketball_coach ? $basketball_coach['prenom'] : 'Pierre' ?>', 'appel')" <?= !$basketball_status['available'] ? 'disabled' : '' ?>>📞</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $basketball_coach ? $basketball_coach['prenom'] : 'Pierre' ?>', 'video')" <?= !$basketball_status['available'] ? 'disabled' : '' ?>>📹</button>
                            </div>
                        </div>
                    </a>
                    
                    <?php 
                    $football_coach = $coachs_by_category['football'];
                    $football_status = $football_coach ? getCoachStatusDisplay($football_coach) : ['class' => 'status-busy', 'text' => '🔴 Aucun coach', 'available' => false];
                    ?>
                    <a href="coach.php?coach_id=<?= $football_coach ? $football_coach['id'] : 3 ?>" style="text-decoration:none; color:inherit;">
                        <div class="sport-card">
                            <div class="sport-icon">⚽</div>
                            <h5>Football</h5>
                            <p><?= $football_coach ? htmlspecialchars($football_coach['prenom'] . ' ' . $football_coach['nom']) : 'Coach Antoine Lefèvre' ?></p>
                            <div class="coach-status <?= $football_status['class'] ?>"><?= $football_status['text'] ?></div>
                            <div class="contact-buttons">
                                <button class="contact-btn" onclick="contactCoach('<?= $football_coach ? $football_coach['prenom'] : 'Antoine' ?>', 'message')" <?= !$football_status['available'] ? 'disabled' : '' ?>>💬</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $football_coach ? $football_coach['prenom'] : 'Antoine' ?>', 'appel')" <?= !$football_status['available'] ? 'disabled' : '' ?>>📞</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $football_coach ? $football_coach['prenom'] : 'Antoine' ?>', 'video')" <?= !$football_status['available'] ? 'disabled' : '' ?>>📹</button>
                            </div>
                        </div>
                    </a>
                    
                    <?php 
                    $rugby_coach = $coachs_by_category['rugby'];
                    $rugby_status = $rugby_coach ? getCoachStatusDisplay($rugby_coach) : ['class' => 'status-busy', 'text' => '🔴 Aucun coach', 'available' => false];
                    ?>
                    <a href="coach.php?coach_id=<?= $rugby_coach ? $rugby_coach['id'] : 4 ?>" style="text-decoration:none; color:inherit;">
                        <div class="sport-card">
                            <div class="sport-icon">🏉</div>
                            <h5>Rugby</h5>
                            <p><?= $rugby_coach ? htmlspecialchars($rugby_coach['prenom'] . ' ' . $rugby_coach['nom']) : 'Coach Marc Rousseau' ?></p>
                            <div class="coach-status <?= $rugby_status['class'] ?>"><?= $rugby_status['text'] ?></div>
                            <div class="contact-buttons">
                                <button class="contact-btn" onclick="contactCoach('<?= $rugby_coach ? $rugby_coach['prenom'] : 'Marc' ?>', 'message')" <?= !$rugby_status['available'] ? 'disabled' : '' ?>>💬</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $rugby_coach ? $rugby_coach['prenom'] : 'Marc' ?>', 'appel')" <?= !$rugby_status['available'] ? 'disabled' : '' ?>>📞</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $rugby_coach ? $rugby_coach['prenom'] : 'Marc' ?>', 'video')" <?= !$rugby_status['available'] ? 'disabled' : '' ?>>📹</button>
                            </div>
                        </div>
                    </a>
                    
                    <?php 
                    $tennis_coach = $coachs_by_category['tennis'];
                    $tennis_status = $tennis_coach ? getCoachStatusDisplay($tennis_coach) : ['class' => 'status-busy', 'text' => '🔴 Aucun coach', 'available' => false];
                    ?>
                    <a href="coach.php?coach_id=<?= $tennis_coach ? $tennis_coach['id'] : 5 ?>" style="text-decoration:none; color:inherit;">
                        <div class="sport-card">
                            <div class="sport-icon">🎾</div>
                            <h5>Tennis</h5>
                            <p><?= $tennis_coach ? htmlspecialchars($tennis_coach['prenom'] . ' ' . $tennis_coach['nom']) : 'Coach Isabelle Martin' ?></p>
                            <div class="coach-status <?= $tennis_status['class'] ?>"><?= $tennis_status['text'] ?></div>
                            <div class="contact-buttons">
                                <button class="contact-btn" onclick="contactCoach('<?= $tennis_coach ? $tennis_coach['prenom'] : 'Isabelle' ?>', 'message')" <?= !$tennis_status['available'] ? 'disabled' : '' ?>>💬</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $tennis_coach ? $tennis_coach['prenom'] : 'Isabelle' ?>', 'appel')" <?= !$tennis_status['available'] ? 'disabled' : '' ?>>📞</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $tennis_coach ? $tennis_coach['prenom'] : 'Isabelle' ?>', 'video')" <?= !$tennis_status['available'] ? 'disabled' : '' ?>>📹</button>
                            </div>
                        </div>
                    </a>
                    
                    <?php 
                    $natation_coach = $coachs_by_category['natation'];
                    $natation_status = $natation_coach ? getCoachStatusDisplay($natation_coach) : ['class' => 'status-busy', 'text' => '🔴 Aucun coach', 'available' => false];
                    ?>
                    <a href="coach.php?coach_id=<?= $natation_coach ? $natation_coach['id'] : 6 ?>" style="text-decoration:none; color:inherit;">
                        <div class="sport-card">
                            <div class="sport-icon">🏊‍♂️</div>
                            <h5>Natation</h5>
                            <p><?= $natation_coach ? htmlspecialchars($natation_coach['prenom'] . ' ' . $natation_coach['nom']) : 'Coach Sophie Blanc' ?></p>
                            <div class="coach-status <?= $natation_status['class'] ?>"><?= $natation_status['text'] ?></div>
                            <div class="contact-buttons">
                                <button class="contact-btn" onclick="contactCoach('<?= $natation_coach ? $natation_coach['prenom'] : 'Sophie' ?>', 'message')" <?= !$natation_status['available'] ? 'disabled' : '' ?>>💬</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $natation_coach ? $natation_coach['prenom'] : 'Sophie' ?>', 'appel')" <?= !$natation_status['available'] ? 'disabled' : '' ?>>📞</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $natation_coach ? $natation_coach['prenom'] : 'Sophie' ?>', 'video')" <?= !$natation_status['available'] ? 'disabled' : '' ?>>📹</button>
                            </div>
                        </div>
                    </a>

                    <?php 
                    $plongee_coach = $coachs_by_category['plongee'];  
                    $plongee_status = $plongee_coach ? getCoachStatusDisplay($plongee_coach) : ['class' => 'status-busy', 'text' => '🔴 Aucun coach', 'available' => false];
                    ?>
                    <a href="coach.php?coach_id=<?= $plongee_coach ? $plongee_coach['id'] : 7 ?>" style="text-decoration:none; color:inherit;">
                        <div class="sport-card">
                            <div class="sport-icon">🤿</div>
                            <h5>Plongée</h5>
                            <p><?= $plongee_coach ? htmlspecialchars($plongee_coach['prenom'] . ' ' . $plongee_coach['nom']) : 'Coach Thomas Noir' ?></p>
                            <div class="coach-status <?= $plongee_status['class'] ?>"><?= $plongee_status['text'] ?></div>
                            <div class="contact-buttons">
                                <button class="contact-btn" onclick="contactCoach('<?= $plongee_coach ? $plongee_coach['prenom'] : 'Thomas' ?>', 'message')" <?= !$plongee_status['available'] ? 'disabled' : '' ?>>💬</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $plongee_coach ? $plongee_coach['prenom'] : 'Thomas' ?>', 'appel')" <?= !$plongee_status['available'] ? 'disabled' : '' ?>>📞</button>
                                <button class="contact-btn" onclick="contactCoach('<?= $plongee_coach ? $plongee_coach['prenom'] : 'Thomas' ?>', 'video')" <?= !$plongee_status['available'] ? 'disabled' : '' ?>>📹</button>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            <div class="card">
                <h3 style="text-align: center; color: #2563eb;">🏢 Salle de Sport Omnes</h3>
                <div class="grid-2">
                    <div>
                        <h5>📋 Règles d'Utilisation</h5>
                        <ul>
                            <li>✅ Nettoyez les équipements après usage</li>
                            <li>✅ Respectez les créneaux horaires</li>
                            <li>✅ Portez des chaussures adaptées</li>
                            <li>✅ Hydratez-vous régulièrement</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h5>🕒 Horaires de la Gym</h5>
                        <p><strong>Lundi - Vendredi :</strong> 6h00 - 22h00</p>
                        <p><strong>Week-end :</strong> 8h00 - 20h00</p>
                        
                        <h5>👥 Responsables</h5>
                        <p><strong>Directeur :</strong> Jean Dupont</p>
                        <p><strong>Superviseur :</strong> Marie Leroy</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <h2 class="section-title">Où nous trouver ?</h2>
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2625.303996653704!2d2.285991!3d48.8512252!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47e6701b4f58251b%3A0x167f5a60fb94aa76!2sECE%20-%20Ecole%20d'ing%C3%A9nieurs%20-%20Campus%20de%20Paris!5e0!3m2!1sfr!2sfr!4v1716736742853!5m2!1sfr!2sfr" 
                    width="100%" 
                    height="400" 
                    style="border:0; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);" 
                    allowfullscreen="" 
                    loading="lazy">
                </iframe>
            </div>
        </div>
    </section>
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h5>🏋️ Sportify</h5>
                    <p>Votre partenaire sportif à Omnes Education. Ensemble, dépassons vos limites !</p>
                    <?php if ($is_logged_in): ?>
                        <p><strong>Connecté en tant que :</strong> <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?><br>
                        <small>Type de compte : <?= ucfirst($user['type']) ?></small></p>
                    <?php endif; ?>
                </div>
                <div>
                    <h5>Contact</h5>
                    <div class="contact-item">
                        <i>📧</i>
                        <span>contact@sportify-omnes.fr</span>
                    </div>
                    <div class="contact-item">
                        <i>📞</i>
                        <span>+33 1 44 39 06 00</span>
                    </div>
                    <div class="contact-item">
                        <i>📍</i>
                        <span>37 Quai de Grenelle, 75015 Paris</span>
                    </div>
                </div>
                <div>
                    <h5>Horaires d'ouverture</h5>
                    <div class="contact-item">
                        <i>🕒</i>
                        <span>Lun - Ven : 8h00 - 20h00</span>
                    </div>
                    <div class="contact-item">
                        <i>🕒</i>
                        <span>Samedi : 9h00 - 18h00</span>
                    </div>
                    <div class="contact-item">
                        <i>🕒</i>
                        <span>Dimanche : 10h00 - 16h00</span>
                    </div>
                </div>
                <?php if ($is_logged_in && $user['type'] === 'administrateur'): ?>
                <div>
                    <h5>🔐 Administration</h5>
                    <p>Accès administrateur détecté</p>
                    <div class="contact-item">
                        <i>⚙️</i>
                        <span><a href="admin_dashboard.php" style="color: #f59e0b;">Panneau d'administration</a></span>
                    </div>
                    <div class="contact-item">
                        <i>💳</i>
                        <span><a href="admin_payments.php" style="color: #f59e0b;">Gestion des paiements</a></span>
                    </div>
                    <div class="contact-item">
                        <i>📊</i>
                        <span>Gestion complète du système</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <hr style="margin: 2rem 0; border-color: #374151;">
            <div style="text-align: center;">
                <p>&copy; 2025 Sportify - Omnes Education. Tous droits réservés.</p>
                <?php if ($is_logged_in): ?>
                    <p><small>Session active depuis <?= date('H:i') ?> - <a href="logout.php" style="color: #f59e0b;">Se déconnecter</a></small></p>
                <?php else: ?>
                    <p><small><a href="login.php" style="color: #f59e0b;">Se connecter</a> pour accéder à toutes les fonctionnalités</small></p>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <script>
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            const dropdown = document.getElementById('userDropdown');
            
            if (userMenu && !userMenu.contains(event.target) && dropdown) {
                dropdown.classList.remove('show');
            }
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown) {
                    dropdown.classList.remove('show');
                }
            }
        });
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-item');
        
        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            if (slides[index]) {
                slides[index].classList.add('active');
            }
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }
        
        function previousSlide() {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            showSlide(currentSlide);
        }

        if (slides.length > 0) {
            setInterval(nextSlide, 4000);
        }
        function toggleSearch() {
            const searchContainer = document.getElementById('searchContainer');
            if (searchContainer.style.display === 'none' || searchContainer.style.display === '') {
                searchContainer.style.display = 'block';
            } else {
                searchContainer.style.display = 'none';
            }
        }
        
        function contactCoach(coachName, method) {
            <?php if ($is_logged_in): ?>
                const methods = {
                    'message': 'Message envoyé',
                    'appel': 'Appel initié',
                    'video': 'Visioconférence démarrée'
                };
                alert(methods[method] + ' avec ' + coachName + ' !');
            <?php else: ?>
                if (confirm('Vous devez être connecté pour contacter un coach. Souhaitez-vous vous connecter maintenant ?')) {
                    window.location.href = 'login.php';
                }
            <?php endif; ?>
        }
        function trackPremiumClick(serviceName) {
            console.log('Premium service clicked:', serviceName);
            if (typeof gtag !== 'undefined') {
                gtag('event', 'premium_service_click', {
                    'service_name': serviceName,
                    'user_logged_in': <?= $is_logged_in ? 'true' : 'false' ?>
                });
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const premiumCards = document.querySelectorAll('.premium-service-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 200);
                    }
                });
            });
            
            premiumCards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s, transform 0.6s';
                observer.observe(card);
            });
            document.querySelectorAll('.premium-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const serviceName = this.closest('.premium-service-card').querySelector('h4').textContent;
                    trackPremiumClick(serviceName);
                });
            });
        });
        document.querySelectorAll('a[href^="#"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href.includes('#') && !href.includes('recherche') && !href.includes('rendez-vous') && !href.includes('compte')) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                }
            });
        });
        document.querySelector('.search-btn').addEventListener('click', function() {
            const searchTerm = document.querySelector('.search-input').value.toLowerCase();
            
            if (searchTerm.trim() === '') {
                alert('Veuillez saisir un terme de recherche');
                return;
            }
            const coachCards = document.querySelectorAll('.sport-card');
            let found = false;
            
            coachCards.forEach(card => {
                const coachName = card.querySelector('p').textContent.toLowerCase();
                const sportName = card.querySelector('h5').textContent.toLowerCase();
                
                if (coachName.includes(searchTerm) || sportName.includes(searchTerm)) {
                    card.style.border = '3px solid #f59e0b';
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    found = true;
                } else {
                    card.style.border = '2px solid transparent';
                }
            });
            
            if (!found) {
                alert('Aucun résultat trouvé pour "' + searchTerm + '"');
            }
        });
        document.querySelector('.search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('.search-btn').click();
            }
        });
        <?php if ($is_logged_in): ?>
            setTimeout(function() {
                console.log('👋 Bienvenue <?= htmlspecialchars($user['prenom']) ?> ! Vous êtes connecté en tant que <?= $user['type'] ?>.');
                <?php if ($user['type'] === 'client'): ?>
                setTimeout(function() {
                    const premiumSection = document.querySelector('.card[style*="linear-gradient(135deg, #f59e0b, #d97706)"]');
                    if (premiumSection) {
                        premiumSection.style.boxShadow = '0 0 20px rgba(245, 158, 11, 0.3)';
                        setTimeout(() => {
                            premiumSection.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
                        }, 3000);
                    }
                }, 2000);
                <?php endif; ?>
            }, 1000);
        <?php endif; ?>
        document.querySelectorAll('.premium-service-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
                this.style.boxShadow = '0 20px 40px rgba(0,0,0,0.3)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '0 15px 35px rgba(0,0,0,0.2)';
            });
        });
        document.querySelectorAll('a[href="login.php"]').forEach(link => {
            if (link.closest('.premium-service-card')) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (confirm('🌟 Connectez-vous pour accéder aux services premium de Sportify !\n\nVous découvrirez des offres exclusives réservées aux étudiants Omnes Education.\n\nSouhaitez-vous vous connecter maintenant ?')) {
                        window.location.href = 'login.php';
                    }
                });
            }
        });
        document.querySelectorAll('.new-badge').forEach(badge => {
            setInterval(() => {
                badge.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    badge.style.transform = 'scale(1)';
                }, 200);
            }, 3000);
        });
        function preloadImages() {
            const images = [
                '/images_projet/default_coach.jpg',
            ];
            
            images.forEach(src => {
                const img = new Image();
                img.src = src;
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            preloadImages();

            if (typeof CSS !== 'undefined' && CSS.supports('scroll-behavior', 'smooth')) {
                document.documentElement.style.scrollBehavior = 'smooth';
            }
        });
    </script>
</body>
</html>