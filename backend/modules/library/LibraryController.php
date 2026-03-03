<?php
// backend/modules/library/LibraryController.php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class LibraryController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // =========================
    // USER LIBRARY (CHAPTERS)
    // =========================
    public function myLibrary() {
        $user = Auth::check();

        $stmt = $this->db->prepare("
            SELECT 
                p.id AS purchase_id,
                c.id AS chapter_id,
                c.title AS chapter_title,
                b.title AS book_title,
                c.type,
                c.chapter_no,
                p.price,
                p.created_at
            FROM purchases p
            JOIN chapters c ON p.chapter_id = c.id
            JOIN books b ON c.book_id = b.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$user["id"]]);
        $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Uploaded books bought
        $fileStmt = $this->db->prepare("
            SELECT 
                p.id AS purchase_id,
                u.id AS file_id,
                u.title,
                u.file_path,
                p.price,
                p.created_at
            FROM purchases p
            JOIN uploaded_books u ON p.chapter_id IS NULL
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $fileStmt->execute([$user["id"]]);
        $files = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success("Your library", [
            "chapters" => $chapters,
            "files" => $files
        ]);
    }
}
