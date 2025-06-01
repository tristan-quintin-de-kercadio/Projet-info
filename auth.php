<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
class AuthManager {
    private $conn;
    
    public function __construct() {
        $this->conn = getdbConnection();
    }
    public function login($email, $mot_de_passe) {
        try {
            $query = "SELECT u.*, 
                             CASE 
                                WHEN u.type_compte = 'coach' THEN c.id 
                                ELSE NULL 
                             END as coach_id
                      FROM utilisateurs u 
                      LEFT JOIN coachs c ON u.id = c.utilisateur_id 
                      WHERE u.email = :email AND u.statut = 'actif'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['type_compte'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_prenom'] = $user['prenom'];
                $_SESSION['coach_id'] = $user['coach_id'];
                $this->updateLastLogin($user['id']);
                $token = $this->createSessionToken($user['id']);
                $_SESSION['token'] = $token;
                
                return [
                    'success' => true,
                    'user' => $user,
                    'redirect' => $this->getRedirectUrl($user['type_compte'])
                ];
            }
            
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur de connexion: ' . $e->getMessage()];
        }
    }
    public function logout() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['token'])) {
            try {
                $query = "UPDATE sessions_utilisateurs SET actif = 0 WHERE token_session = :token";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':token', $_SESSION['token']);
                $stmt->execute();
            } catch (Exception $e) {
            }
        }
        session_destroy();
        return true;
    }
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['token']);
    }
    public function hasRole($role) {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === $role;
    }
    private function createSessionToken($user_id) {
        $token = bin2hex(random_bytes(32));
        $expiration = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $query = "INSERT INTO sessions_utilisateurs (utilisateur_id, token_session, ip_address, user_agent, date_expiration) 
                      VALUES (:user_id, :token, :ip, :user_agent, :expiration)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':ip', $ip_address);
            $stmt->bindParam(':user_agent', $user_agent);
            $stmt->bindParam(':expiration', $expiration);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Erreur création token session: " . $e->getMessage());
        }
        
        return $token;
    }
    private function updateLastLogin($user_id) {
        try {
            $query = "UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Erreur mise à jour dernière connexion: " . $e->getMessage());
        }
    }
    private function getRedirectUrl($type_compte) {
        switch ($type_compte) {
            case 'administrateur':
                return 'admin_dashboard.php';
            case 'coach':
                return 'coach_dashboard.php';
            case 'client':
                return 'index.php';
            default:
                return 'index.php';
        }
    }
    public function registerClient($nom, $prenom, $email, $mot_de_passe, $telephone = null) {
        try {
            $query = "SELECT id FROM utilisateurs WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Cet email est déjà utilisé'];
            }
            
            $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $query = "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, type_compte, telephone) 
                      VALUES (:nom, :prenom, :email, :mot_de_passe, 'client', :telephone)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':prenom', $prenom);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':mot_de_passe', $mot_de_passe_hash);
            $stmt->bindParam(':telephone', $telephone);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Compte créé avec succès'];
            }
            
            return ['success' => false, 'message' => 'Erreur lors de la création du compte'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
}
function requireLogin() {
    $auth = new AuthManager();
    if (!$auth->isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    $auth = new AuthManager();
    if (!$auth->isLoggedIn() || !$auth->hasRole('administrateur')) {
        header('Location: login.php?error=access_denied');
        exit;
    }
}

function requireCoach() {
    $auth = new AuthManager();
    if (!$auth->isLoggedIn() || !$auth->hasRole('coach')) {
        header('Location: login.php?error=access_denied');
        exit;
    }
}

function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'type' => $_SESSION['user_type'],
            'nom' => $_SESSION['user_nom'],
            'prenom' => $_SESSION['user_prenom'],
            'coach_id' => $_SESSION['coach_id'] ?? null
        ];
    }
    return null;
}
class CVManager {
    private $conn;
    
    public function __construct() {
        $this->conn = getDbConnection();
    }
    public function createCoachXML($coach_id, $formations, $experiences, $certifications, $specialites) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        $root = $xml->createElement('cv_coach');
        $xml->appendChild($root);
        $coach = $this->getCoachInfo($coach_id);
        if ($coach) {
            $infos = $xml->createElement('informations_generales');
            $infos->appendChild($xml->createElement('nom', htmlspecialchars($coach['nom'])));
            $infos->appendChild($xml->createElement('prenom', htmlspecialchars($coach['prenom'])));
            $infos->appendChild($xml->createElement('specialite', htmlspecialchars($coach['specialite'])));
            $infos->appendChild($xml->createElement('email', htmlspecialchars($coach['email'])));
            $root->appendChild($infos);
        }
        $formations_node = $xml->createElement('formations');
        foreach ($formations as $formation) {
            $formation_node = $xml->createElement('formation');
            $formation_node->appendChild($xml->createElement('titre', htmlspecialchars($formation['titre'])));
            $formation_node->appendChild($xml->createElement('etablissement', htmlspecialchars($formation['etablissement'])));
            $formation_node->appendChild($xml->createElement('annee', htmlspecialchars($formation['annee'])));
            $formations_node->appendChild($formation_node);
        }
        $root->appendChild($formations_node);
        $experiences_node = $xml->createElement('experiences');
        foreach ($experiences as $experience) {
            $exp_node = $xml->createElement('experience');
            $exp_node->appendChild($xml->createElement('poste', htmlspecialchars($experience['poste'])));
            $exp_node->appendChild($xml->createElement('entreprise', htmlspecialchars($experience['entreprise'])));
            $exp_node->appendChild($xml->createElement('periode', htmlspecialchars($experience['periode'])));
            $exp_node->appendChild($xml->createElement('description', htmlspecialchars($experience['description'])));
            $experiences_node->appendChild($exp_node);
        }
        $root->appendChild($experiences_node);
        
        $certifications_node = $xml->createElement('certifications');
        foreach ($certifications as $certification) {
            $cert_node = $xml->createElement('certification');
            $cert_node->appendChild($xml->createElement('nom', htmlspecialchars($certification['nom'])));
            $cert_node->appendChild($xml->createElement('organisme', htmlspecialchars($certification['organisme'])));
            $cert_node->appendChild($xml->createElement('date_obtention', htmlspecialchars($certification['date'])));
            $certifications_node->appendChild($cert_node);
        }
        $root->appendChild($certifications_node);
        
        $specialites_node = $xml->createElement('specialites');
        foreach ($specialites as $specialite) {
            $spec_node = $xml->createElement('specialite', htmlspecialchars($specialite));
            $specialites_node->appendChild($spec_node);
        }
        $root->appendChild($specialites_node);
        $filename = "cv_coach_" . $coach_id . "_" . date('Y-m-d_H-i-s') . ".xml";
        $filepath = "uploads/cv/" . $filename;
        if (!file_exists('uploads/cv/')) {
            mkdir('uploads/cv/', 0777, true);
        }
        
        if ($xml->save($filepath)) {
            try {
                $query = "INSERT INTO cv_fichiers (coach_id, type_fichier, nom_fichier, chemin_fichier, contenu_xml, taille_fichier) 
                          VALUES (:coach_id, 'xml', :nom_fichier, :chemin_fichier, :contenu_xml, :taille)";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':coach_id', $coach_id);
                $stmt->bindParam(':nom_fichier', $filename);
                $stmt->bindParam(':chemin_fichier', $filepath);
                $stmt->bindParam(':contenu_xml', $xml->saveXML());
                $stmt->bindParam(':taille', filesize($filepath));
                if ($stmt->execute()) {
                    $cv_id = $this->conn->lastInsertId();
                    $update_query = "UPDATE coachs SET cv_xml_id = :cv_id WHERE id = :coach_id";
                    $update_stmt = $this->conn->prepare($update_query);
                    $update_stmt->bindParam(':cv_id', $cv_id);
                    $update_stmt->bindParam(':coach_id', $coach_id);
                    $update_stmt->execute();
                    
                    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
                }
            } catch (Exception $e) {
                error_log("Erreur sauvegarde CV XML: " . $e->getMessage());
            }
        }
        
        return ['success' => false, 'message' => 'Erreur lors de la création du fichier XML'];
    }
    private function getCoachInfo($coach_id) {
        try {
            $query = "SELECT * FROM coachs WHERE id = :coach_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':coach_id', $coach_id);
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erreur récupération info coach: " . $e->getMessage());
            return null;
        }
    }
    public function readCoachXML($coach_id) {
        try {
            $query = "SELECT * FROM cv_fichiers WHERE coach_id = :coach_id AND type_fichier = 'xml' AND actif = 1 ORDER BY date_upload DESC LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':coach_id', $coach_id);
            $stmt->execute();
            
            $cv_file = $stmt->fetch();
            if ($cv_file && file_exists($cv_file['chemin_fichier'])) {
                $xml = simplexml_load_file($cv_file['chemin_fichier']);
                return [
                    'success' => true,
                    'data' => $xml,
                    'file_info' => $cv_file
                ];
            }
        } catch (Exception $e) {
            error_log("Erreur lecture CV XML: " . $e->getMessage());
        }
        
        return ['success' => false, 'message' => 'Fichier CV non trouvé'];
    }
}
?>
