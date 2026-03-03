<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class BookmarkController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // =========================
    // SAVE / UPDATE BOOKMARK
    // =========================
    public function save() {
        $user = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);

        $book_id    = $data["book_id"] ?? null;
        $chapter_id = $data["chapter_id"] ?? null;
        $pdf_page   = $data["pdf_page"] ?? 1;

        if (!$book_id) {
            Response::error("Book ID required");
        }

        $stmt = $this->db->prepare("
            INSERT INTO bookmarks (user_id, book_id, chapter_id, pdf_page)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              chapter_id = VALUES(chapter_id),
              pdf_page = VALUES(pdf_page)
        ");

        $stmt->execute([
            $user["id"],
            $book_id,
            $chapter_id,
            $pdf_page
        ]);

        Response::success("Book bookmarked");
    }

    // =========================
    // GET BOOKMARK FOR ONE BOOK
    // =========================
    public function get() {
        $user = Auth::check();
        $book_id = $_GET["book_id"] ?? null;

        if (!$book_id) {
            Response::error("Book ID required");
        }

        $stmt = $this->db->prepare("
            SELECT * FROM bookmarks
            WHERE user_id = ? AND book_id = ?
        ");
        $stmt->execute([$user["id"], $book_id]);

        Response::success("Bookmark", $stmt->fetch());
    }

    // =========================
    // MY LIBRARY (DASHBOARD)
    // =========================
    public function myLibrary() {
        $user = Auth::check();

        $stmt = $this->db->prepare("
            SELECT 
                b.id AS book_id,
                b.title,
                b.cover_image,
                bm.chapter_id,
                bm.pdf_page,
                bm.created_at
            FROM bookmarks bm
            JOIN books b ON bm.book_id = b.id
            WHERE bm.user_id = ?
            ORDER BY bm.created_at DESC
        ");

        $stmt->execute([$user["id"]]);
        $books = $stmt->fetchAll();

        Response::success("My Library", $books);
    }

    // =========================
    // REMOVE BOOKMARK
    // =========================
    public function remove() {
        $user = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);
        $book_id = $data["book_id"] ?? null;

        if (!$book_id) {
            Response::error("Book ID required");
        }

        $stmt = $this->db->prepare("
            DELETE FROM bookmarks
            WHERE user_id = ? AND book_id = ?
        ");
        $stmt->execute([$user["id"], $book_id]);

        Response::success("Bookmark removed");
    }
    
}
