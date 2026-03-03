<?php
// backend/modules/auth/AuthController.php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class AuthController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // =========================
    // REGISTER
    // =========================
    public function register() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data["name"]) || empty($data["email"]) || empty($data["password"])) {
            Response::error("All fields are required");
        }

        $name = trim($data["name"]);
        $email = trim($data["email"]);
        $password = password_hash($data["password"], PASSWORD_BCRYPT);
        $role = "reader";

        // Check if email exists
        $check = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            Response::error("Email already registered");
        }

        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
        );

        if ($stmt->execute([$name, $email, $password, $role])) {
            Response::success("User registered successfully");
        } else {
            Response::serverError("Registration failed");
        }
    }

    // =========================
    // LOGIN
    // =========================
    public function login() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data["email"]) || empty($data["password"])) {
            Response::error("Email and password required");
        }

        $email = trim($data["email"]);
        $password = $data["password"];

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error("User not found");
        }

        if (!password_verify($password, $user["password"])) {
            Response::error("Invalid password");
        }

        $token = Auth::generateToken($user);

        Response::success("Login successful", [
            "token" => $token,
            "user" => [
                "id" => $user["id"],
                "name" => $user["name"],
                "email" => $user["email"],
                "role" => $user["role"]
            ]
        ]);
    }

    // =========================
    // PROFILE (Protected)
    // =========================
    public function profile() {
        $jwtUser = Auth::check();

        $stmt = $this->db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->execute([$jwtUser["id"]]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error("User not found");
        }

        Response::success("User profile", $user);
    }
}
