<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sportify_db');
define('DB_USER', 'root');
define('DB_PASS', '');     
define('DB_CHARSET', 'utf8mb4');
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    public $conn;
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            echo "Erreur de connexion : " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}
function getdbConnection() {
    $database = new Database();
    return $database->getConnection();
}
function formatDateFr($date) {
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
    
    $timestamp = strtotime($date);
    $jour_semaine = $jours[date('w', $timestamp)];
    $jour = date('d', $timestamp);
    $mois_nom = $mois[date('n', $timestamp)];
    
    return [
        'jour_complet' => $jour_semaine,
        'jour_court' => substr($jour_semaine, 0, 3),
        'date_courte' => $jour . '/' . date('m', $timestamp)
    ];
}
function getSemaineCourrante($date_reference = null) {
    if ($date_reference === null) {
        $date_reference = new DateTime();
    } else {
        $date_reference = new DateTime($date_reference);
    }
    
    $jour_semaine = $date_reference->format('N');

    $lundi = clone $date_reference;
    $lundi->sub(new DateInterval('P' . ($jour_semaine - 1) . 'D'));
    $dates_semaine = [];
    for ($i = 0; $i < 7; $i++) {
        $date = clone $lundi;
        $date->add(new DateInterval('P' . $i . 'D'));
        $dates_semaine[] = $date->format('Y-m-d');
    }
    
    return [
        'dates' => $dates_semaine,
        'debut' => $dates_semaine[0], 
        'fin' => $dates_semaine[6]    
    ];
}
function getCreneauxCoach($coach_id, $date_debut, $date_fin) {
    $conn = getDbConnection();
    
    $query = "SELECT 
                c.id,
                c.date_creneau,
                c.heure_debut,
                c.heure_fin,
                c.statut,
                c.motif,
                c.etudiant_nom,
                c.etudiant_email,
                TIME_FORMAT(c.heure_debut, '%Hh') as heure_debut_format,
                TIME_FORMAT(c.heure_fin, '%Hh') as heure_fin_format,
                CONCAT(TIME_FORMAT(c.heure_debut, '%Hh'), '-', TIME_FORMAT(c.heure_fin, '%Hh')) as creneau_display
              FROM creneaux c
              WHERE c.coach_id = :coach_id 
                AND c.date_creneau >= :date_debut 
                AND c.date_creneau <= :date_fin
              ORDER BY c.date_creneau, c.heure_debut";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':coach_id', $coach_id);
    $stmt->bindParam(':date_debut', $date_debut);
    $stmt->bindParam(':date_fin', $date_fin);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function ensureCreneauxExistent($coach_id, $dates_semaine) {
    $conn = getDbConnection();
    
    // Créneaux par défaut
    $creneaux_defaut = [
        '08:00:00' => '09:00:00',
        '09:00:00' => '10:00:00',
        '10:00:00' => '11:00:00',
        '11:00:00' => '12:00:00',
        '14:00:00' => '15:00:00',
        '15:00:00' => '16:00:00',
        '16:00:00' => '17:00:00',
        '17:00:00' => '18:00:00'
    ];
    
    foreach ($dates_semaine as $date) {
        if (date('N', strtotime($date)) == 7) { 
            continue; 
        }
        $query_check = "SELECT COUNT(*) as nb FROM creneaux WHERE coach_id = :coach_id AND date_creneau = :date";
        $stmt_check = $conn->prepare($query_check);
        $stmt_check->bindParam(':coach_id', $coach_id);
        $stmt_check->bindParam(':date', $date);
        $stmt_check->execute();
        $result = $stmt_check->fetch();
        if ($result['nb'] == 0) {
            foreach ($creneaux_defaut as $heure_debut => $heure_fin) {
                $query_insert = "INSERT INTO creneaux (coach_id, date_creneau, heure_debut, heure_fin, statut) 
                                VALUES (:coach_id, :date, :heure_debut, :heure_fin, 'libre')";
                $stmt_insert = $conn->prepare($query_insert);
                $stmt_insert->bindParam(':coach_id', $coach_id);
                $stmt_insert->bindParam(':date', $date);
                $stmt_insert->bindParam(':heure_debut', $heure_debut);
                $stmt_insert->bindParam(':heure_fin', $heure_fin);
                $stmt_insert->execute();
            }
        }
    }
}
function getCoachInfo($coach_id) {
    $conn = getDbConnection();
    
    $query = "SELECT * FROM coachs WHERE id = :coach_id AND actif = TRUE";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':coach_id', $coach_id);
    $stmt->execute();
    
    return $stmt->fetch();
}

function reserverCreneau($creneau_id, $etudiant_nom, $etudiant_email, $notes = '') {
    $conn = getDbConnection();
    
    try {
        $query_check = "SELECT statut FROM creneaux WHERE id = :creneau_id";
        $stmt_check = $conn->prepare($query_check);
        $stmt_check->bindParam(':creneau_id', $creneau_id);
        $stmt_check->execute();
        $creneau = $stmt_check->fetch();
        
        if (!$creneau || $creneau['statut'] !== 'libre') {
            return ['success' => false, 'message' => 'Créneau non disponible'];
        }
        $query_update = "UPDATE creneaux 
                        SET statut = 'reserve',
                            etudiant_nom = :etudiant_nom,
                            etudiant_email = :etudiant_email,
                            notes = :notes,
                            updated_at = NOW()
                        WHERE id = :creneau_id";
        
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bindParam(':creneau_id', $creneau_id);
        $stmt_update->bindParam(':etudiant_nom', $etudiant_nom);
        $stmt_update->bindParam(':etudiant_email', $etudiant_email);
        $stmt_update->bindParam(':notes', $notes);
        
        if ($stmt_update->execute()) {
            return ['success' => true, 'message' => 'Créneau réservé avec succès'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la réservation'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function getPlanningCompletCoach($coach_id, $dates_semaine = null) {
    if ($dates_semaine === null) {
        $semaine = getSemaineCourrante();
        $dates_semaine = $semaine['dates'];
    }
    ensureCreneauxExistent($coach_id, $dates_semaine);
    $debut = $dates_semaine[0];
    $fin = $dates_semaine[6];
    $creneaux = getCreneauxCoach($coach_id, $debut, $fin);
    $planning = [];
    foreach ($dates_semaine as $date) {
        $planning[$date] = [];
    }
    
    foreach ($creneaux as $creneau) {
        if (isset($planning[$creneau['date_creneau']])) {
            $planning[$creneau['date_creneau']][] = $creneau;
        }
    }
    
    return $planning;
}
function getJourSemaineFr($date, $format = 'court') {
    $jours_complets = [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi', 
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche' 
    ];
    
    $jours_courts = [
        'Monday' => 'LUN',
        'Tuesday' => 'MAR',
        'Wednesday' => 'MER',
        'Thursday' => 'JEU', 
        'Friday' => 'VEN',
        'Saturday' => 'SAM',
        'Sunday' => 'DIM' 
    ];
    
    $jour_anglais = date('l', strtotime($date));
    
    if ($format === 'court') {
        return $jours_courts[$jour_anglais] ?? 'N/A';
    } else {
        return $jours_complets[$jour_anglais] ?? 'N/A';
    }
}

date_default_timezone_set('Europe/Paris');
?>