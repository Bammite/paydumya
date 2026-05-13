<?php 
// Inclure l'autoloader de Composer pour charger les dépendances
require_once __DIR__ . '/../../vendor/autoload.php';

// Charger les variables d'environnement depuis le fichier .env à la racine du projet
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/.env');
$dotenv->load();

// Récupérer les identifiants de la base de données depuis les variables d'environnement
$server = $_ENV['DB_HOST'];
$user = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];
$db = $_ENV['DB_DATABASE'];

    try {
        $connexion = new PDO("mysql:host=$server;dbname=$db", $user, $password);
        $connexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $th) {
        // En production, on ne doit jamais afficher les détails de l'erreur de connexion.
        // On log l'erreur pour le développeur.
        error_log("FATAL: Erreur de connexion à la base de données: " . $th->getMessage());
        // On relance l'exception pour qu'elle soit gérée par le script appelant (ex: dans un bloc try/catch global).
        throw $th;
    }
?>