<?php
require_once 'auth.php';

$auth = new AuthManager();
$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $email = trim($_POST['email']);
        $mot_de_passe = $_POST['mot_de_passe'];
        
        $result = $auth->login($email, $mot_de_passe);
        
        if ($result['success']) {
            header('Location: ' . $result['redirect']);
            exit;
        } else {
            $error_message = $result['message'];
        }
    } elseif ($_POST['action'] === 'register') {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $mot_de_passe = $_POST['mot_de_passe'];
        $telephone = trim($_POST['telephone']);
        
        $result = $auth->registerClient($nom, $prenom, $email, $mot_de_passe, $telephone);
        
        if ($result['success']) {
            $success_message = $result['message'];
        } else {
            $error_message = $result['message'];
        }
    }
}
if ($auth->isLoggedIn()) {
    $user_type = $_SESSION['user_type'];
    switch ($user_type) {
        case 'administrateur':
            header('Location: admin_dashboard.php');
            break;
        case 'coach':
            header('Location: coach_dashboard.php');
            break;
        default:
            header('Location: index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion / Inscription - Sportify | Omnes Education</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 600px;
            display: flex;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .login-right {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .welcome-text {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            text-align: left;
        }

        .feature-list li {
            margin: 1rem 0;
            padding-left: 2rem;
            position: relative;
        }

        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        .form-container {
            width: 100%;
        }

        .form-toggle {
            display: flex;
            margin-bottom: 2rem;
            border-radius: 10px;
            background: #f3f4f6;
            padding: 0.5rem;
        }

        .toggle-btn {
            flex: 1;
            padding: 1rem;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .toggle-btn.active {
            background: #2563eb;
            color: white;
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

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #059669;
            border: 1px solid #bbf7d0;
        }

        .back-link {
            text-align: center;
            margin-top: 2rem;
        }

        .back-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .admin-access {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 2rem;
            text-align: center;
        }

        .admin-access h4 {
            color: #1e40af;
            margin-bottom: 0.5rem;
        }

        .admin-access p {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                margin: 1rem;
                max-width: none;
            }
            
            .login-left, .login-right {
                padding: 2rem;
            }
            
            .login-left {
                order: 2;
            }
            
            .login-right {
                order: 1;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="logo">🏋️ Sportify</div>
            <div class="welcome-text">
                Votre plateforme de consultation sportive à Omnes Education
            </div>
            <ul class="feature-list">
                <li>Réservez facilement vos séances avec nos coachs experts</li>
                <li>Accédez à tous les sports et activités du campus</li>
                <li>Consultations personnalisées selon vos objectifs</li>
                <li>Chat, appel et visioconférence avec vos coachs</li>
                <li>Planning en temps réel et notifications</li>
            </ul>
        </div>
        <div class="login-right">
            <div class="form-container">
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                <div class="form-toggle">
                    <button type="button" class="toggle-btn active" onclick="showLogin()">
                        Connexion
                    </button>
                    <button type="button" class="toggle-btn" onclick="showRegister()">
                        Inscription
                    </button>
                </div>
                <form id="login-form" method="POST" style="display: block;">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="login-email">Email :</label>
                        <input type="email" id="login-email" name="email" required 
                               placeholder="votre.email@omnes.fr">
                    </div>

                    <div class="form-group">
                        <label for="login-password">Mot de passe :</label>
                        <input type="password" id="login-password" name="mot_de_passe" required 
                               placeholder="Votre mot de passe">
                    </div>

                    <button type="submit" class="login-btn">
                        Se connecter
                    </button>
                </form>
                <form id="register-form" method="POST" style="display: none;">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label for="register-nom">Nom :</label>
                        <input type="text" id="register-nom" name="nom" required 
                               placeholder="Votre nom de famille">
                    </div>

                    <div class="form-group">
                        <label for="register-prenom">Prénom :</label>
                        <input type="text" id="register-prenom" name="prenom" required 
                               placeholder="Votre prénom">
                    </div>

                    <div class="form-group">
                        <label for="register-email">Email :</label>
                        <input type="email" id="register-email" name="email" required 
                               placeholder="votre.email@omnes.fr">
                    </div>

                    <div class="form-group">
                        <label for="register-telephone">Téléphone (optionnel) :</label>
                        <input type="tel" id="register-telephone" name="telephone" 
                               placeholder="01.23.45.67.89">
                    </div>

                    <div class="form-group">
                        <label for="register-password">Mot de passe :</label>
                        <input type="password" id="register-password" name="mot_de_passe" required 
                               placeholder="Choisissez un mot de passe sécurisé">
                    </div>

                    <button type="submit" class="login-btn">
                        Créer mon compte
                    </button>
                </form>
                <div class="admin-access">
                    <h4>🔐 Accès Administrateur</h4>
                    <p>Utilisez vos identifiants administrateur pour accéder au panneau de gestion.</p>
                    <p><strong>Email de test :</strong> admin@sportify-omnes.fr</p>
                    <p><strong>Mot de passe :</strong> password</p>
                </div>
                <div class="back-link">
                    <a href="index.php">← Retour à l'accueil</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showLogin() {
            document.getElementById('login-form').style.display = 'block';
            document.getElementById('register-form').style.display = 'none';
            document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        function showRegister() {
            document.getElementById('login-form').style.display = 'none';
            document.getElementById('register-form').style.display = 'block';
            document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }
        document.getElementById('register-form').addEventListener('submit', function(e) {
            const password = document.getElementById('register-password').value;
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères.');
                return false;
            }
        });
        document.getElementById('login-email').focus();
    </script>
</body>
</html>