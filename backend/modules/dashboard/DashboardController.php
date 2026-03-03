<?php
// backend/modules/dashboard/DashboardController.php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class DashboardController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }
private function uploadsPath(){
    return dirname(__DIR__,3) . "/uploads/";
}
    // =========================
    // LOAD USER DASHBOARD
    // =========================
    public function load() {
        $user = Auth::check();

        // USER INFO
        $stmt = $this->db->prepare("
            SELECT id, name, email, role
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$user["id"]]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            Response::notFound("User not found");
        }

        // AUTHOR BOOKS
        $booksStmt = $this->db->prepare("
            SELECT id, title, cover_image, price, created_at
            FROM books
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $booksStmt->execute([$user["id"]]);
        $books = $booksStmt->fetchAll(PDO::FETCH_ASSOC);

        // UPLOADED BOOK FILES INFO (for reader & storage)
        $filesStmt = $this->db->prepare("
            SELECT id, book_id, file_name, file_path, file_type, file_size
            FROM uploaded_books
            WHERE user_id = ?
        ");
        $filesStmt->execute([$user["id"]]);
        $uploadedFiles = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

        // TOTAL STORAGE USED (in MB)
        $totalStorage = 0;
        foreach ($uploadedFiles as $f) {
            $totalStorage += $f["file_size"];
        }
        $totalStorageMB = round($totalStorage / (1024*1024), 2);

        // WALLET BALANCE
        $walletStmt = $this->db->prepare("
            SELECT IFNULL(
                SUM(
                    CASE 
                        WHEN type = 'credit' THEN amount
                        WHEN type = 'debit' THEN -amount
                        ELSE 0
                    END
                ), 0
            ) AS balance
            FROM wallet_transactions
            WHERE user_id = ?
        ");
        $walletStmt->execute([$user["id"]]);
        $balanceRow = $walletStmt->fetch(PDO::FETCH_ASSOC);
        $balance = $balanceRow ? (float)$balanceRow["balance"] : 0;

        Response::success("Dashboard data", [
            "user" => $userData,
            "books" => $books,
            "uploaded_files" => $uploadedFiles,
            "total_storage_MB" => $totalStorageMB,
            "wallet_balance" => $balance,
            "mode" => $userData["role"],
            "book_count" => count($books)
        ]);
    }

    // =========================
    // SWITCH MODE
    // =========================
    public function switchMode() {
        $user = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);
        $mode = $data["mode"] ?? null;

        if (!in_array($mode, ["reader", "author"])) {
            Response::error("Invalid mode");
        }

        $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");

        if ($stmt->execute([$mode, $user["id"]])) {
            Response::success("Mode switched", ["mode" => $mode]);
        } else {
            Response::serverError("Failed to switch mode");
        }
    }
    
    // =========================
    // USER LIBRARY
    // =========================
    public function library() {
        $user = Auth::check();

        $stmt = $this->db->prepare("
            SELECT 
                b.id, 
                b.title, 
                b.cover_image, 
                b.price
            FROM purchases p
            INNER JOIN books b ON b.id = p.book_id
            WHERE p.user_id = ?
        ");
        $stmt->execute([$user["id"]]);

        $library = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success("My Library", $library);
    }

    // =========================
    // OPEN BOOK IN READER
    // =========================
    public function read() {
        $user = Auth::check();
        $bookId = $_GET["book_id"] ?? null;

        if (!$bookId || !is_numeric($bookId)) {
            Response::error("Invalid book_id");
        }

        // Check ownership OR purchase
        $stmt = $this->db->prepare("
            SELECT ub.id, ub.file_path, ub.file_type, ub.file_name
            FROM uploaded_books ub
            JOIN books b ON b.id = ub.book_id
            LEFT JOIN purchases p ON p.book_id = b.id AND p.user_id = ?
            WHERE ub.book_id = ? AND (b.user_id = ? OR p.id IS NOT NULL)
            LIMIT 1
        ");
        $stmt->execute([$user["id"], $bookId, $user["id"]]);
        $bookFile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bookFile) {
            Response::unauthorized("You do not have access to this book");
        }

        // Return file info for reader
        Response::success("Book ready to read", [
            "file_path" => $bookFile["file_path"],
            "file_type" => $bookFile["file_type"],
            "file_name" => $bookFile["file_name"]
        ]);
    }

    // =========================
    // DELETE BOOK (AUTHOR ONLY)
    // =========================
    public function deleteBook() {
        $user = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);
        $bookId = $data["book_id"] ?? null;

        if (!$bookId || !is_numeric($bookId)) {
            Response::error("Invalid book_id");
        }

        // Verify ownership
        $check = $this->db->prepare("SELECT id FROM books WHERE id = ? AND user_id = ?");
        $check->execute([$bookId, $user["id"]]);
        if (!$check->fetch()) {
            Response::unauthorized("You do not own this book");
        }

        // Delete uploaded files
        $filesStmt = $this->db->prepare("SELECT file_path FROM uploaded_books WHERE book_id = ?");
        $filesStmt->execute([$bookId]);
        $files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($files as $f) {
            $filePath = $this->uploadsPath() . "books/" . $f["file_path"];
            if (file_exists($filePath)) unlink($filePath);
        }

        // Delete records from uploaded_books
        $this->db->prepare("DELETE FROM uploaded_books WHERE book_id = ?")->execute([$bookId]);

        // Delete cover image
        $coverStmt = $this->db->prepare("SELECT cover_image FROM books WHERE id = ?");
        $coverStmt->execute([$bookId]);
        $cover = $coverStmt->fetch(PDO::FETCH_ASSOC);
        if ($cover && file_exists($this->uploadsPath() . "covers/" . $cover["cover_image"])) {
            unlink($this->uploadsPath() . "covers/" . $cover["cover_image"]);
        }

        // Delete book record
        $this->db->prepare("DELETE FROM books WHERE id = ?")->execute([$bookId]);

        Response::success("Book deleted successfully", ["book_id" => $bookId]);
    }
}
