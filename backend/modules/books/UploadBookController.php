<?php
// backend/modules/books/UploadBookController.php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class UploadBookController
{

    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // =========================
    // UPLOAD BOOK FILE (PDF, EPUB, CBZ, ZIP) - up to 700MB
    // =========================
    public function uploadBook()
    {
        $user = Auth::check();

        $book_id = $_POST["book_id"] ?? null;
        $price   = floatval($_POST["price"] ?? 0);

        if (!is_numeric($book_id)) {
            Response::error("Invalid book_id");
        }

        if (!isset($_FILES["file"])) {
            Response::error("Book file required");
        }

        if ($price < 0) {
            Response::error("Invalid price");
        }

        // Verify ownership
        $check = $this->db->prepare("SELECT id FROM books WHERE id = ? AND user_id = ?");
        $check->execute([$book_id, $user["id"]]);
        if (!$check->fetch()) {
            Response::unauthorized("You do not own this book");
        }

        $file = $_FILES["file"];
        if ($file["error"] !== UPLOAD_ERR_OK) {
            Response::error("File upload failed with error code: " . $file["error"]);
        }

        // Allowed extensions
        $allowedExt = ["pdf", "epub", "cbz", "zip"];
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            Response::error("Invalid file type. Allowed: pdf, epub, cbz, zip");
        }

        // Max 700MB
        $maxSize = 700 * 1024 * 1024; // 700MB
        if ($file["size"] > $maxSize) {
            Response::error("File too large (max 700MB)");
        }

        // =========================
        // SAVE FILE TO UPLOADS
        // =========================
        $uploadDir = __DIR__ . "/../../uploads/books/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $safeName = uniqid("book_", true) . "." . $ext;
        $targetPath = $uploadDir . $safeName;

        if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
            Response::serverError("Failed to save file");
        }

        // =========================
        // INSERT RECORD INTO DB
        // =========================
        $stmt = $this->db->prepare("
            INSERT INTO uploaded_books 
            (user_id, book_id, file_name, file_path, file_type, file_size, price, is_published)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");

        if ($stmt->execute([
            $user["id"],
            $book_id,
            $file["name"],
            $safeName,
            $ext,
            $file["size"],
            $price
        ])) {
            Response::success("Book file uploaded successfully", [
                "book_id"   => $book_id,
                "file_path" => $safeName,
                "file_type" => $ext,
                "price"     => $price
            ]);
        } else {
            Response::serverError("Database insert failed");
        }
    }

    // =========================
    // LIST ALL PUBLIC FILES
    // =========================
    public function listFiles()
    {
        $stmt = $this->db->query("
            SELECT 
                ub.id, ub.file_name, ub.file_type, ub.price, ub.file_path,
                b.title, b.cover_image
            FROM uploaded_books ub
            JOIN books b ON ub.book_id = b.id
            WHERE ub.is_published = 1 AND b.is_published = 1
            ORDER BY ub.created_at DESC
        ");

        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success("Uploaded books list", $files);
    }

    // =========================
    // LIST MY FILES (AUTHOR)
    // =========================
    public function myFiles()
    {
        $user = Auth::check();

        $stmt = $this->db->prepare("
            SELECT 
                ub.id, ub.file_name, ub.file_type, ub.price, ub.is_published,
                b.title
            FROM uploaded_books ub
            JOIN books b ON ub.book_id = b.id
            WHERE ub.user_id = ?
            ORDER BY ub.created_at DESC
        ");
        $stmt->execute([$user["id"]]);

        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success("My uploaded books", $files);
    }

    // =========================
    // PUBLISH / UNPUBLISH FILE
    // =========================
    public function publish()
    {
        $user = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);

        $file_id = $data["file_id"] ?? null;
        $status  = $data["status"] ?? null;

        if (!is_numeric($file_id) || !in_array((string)$status, ["0", "1"], true)) {
            Response::error("Invalid file_id or status");
        }

        $stmt = $this->db->prepare("
            UPDATE uploaded_books 
            SET is_published = ? 
            WHERE id = ? AND user_id = ?
        ");

        if ($stmt->execute([$status, $file_id, $user["id"]])) {
            Response::success("File publish status updated", [
                "file_id" => $file_id,
                "is_published" => (int)$status
            ]);
        } else {
            Response::serverError("Update failed");
        }
    }
}
