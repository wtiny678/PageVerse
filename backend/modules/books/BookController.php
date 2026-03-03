<?php
// backend/modules/books/BookController.php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class BookController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // =========================
    // CREATE BOOK (NOVEL / COMIC) + optional file upload
    // =========================
    public function create() {
        $user = Auth::check();

        $title = trim($_POST["title"] ?? "");
        $type  = $_POST["type"] ?? null; // novel | comic
        $price = floatval($_POST["price"] ?? 0);

        if ($title === "" || !in_array($type, ["novel", "comic"])) {
            Response::error("Title and valid type (novel/comic) required");
        }

        if ($price < 0) {
            Response::error("Price cannot be negative");
        }

        // =========================
        // COVER IMAGE
        // =========================
        if (!isset($_FILES["cover_image"])) {
            Response::error("Cover image required");
        }

        $file = $_FILES["cover_image"];
        if ($file["error"] !== UPLOAD_ERR_OK) {
            Response::error("Cover image upload failed");
        }

        $allowedExt = ["jpg","jpeg","png","webp"];
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt)) {
            Response::error("Invalid cover image type (jpg, png, webp only)");
        }

        if ($file["size"] > 5 * 1024 * 1024) {
            Response::error("Cover image too large (max 5MB)");
        }

        $uploadDir = __DIR__ . "/../../uploads/covers/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = uniqid("cover_") . "." . $ext;
        $targetPath = $uploadDir . $fileName;

        if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
            Response::serverError("Failed to upload cover image");
        }

        $coverPath = $fileName;

        // =========================
        // INSERT BOOK
        // =========================
        $stmt = $this->db->prepare("
            INSERT INTO books (user_id, title, type, price, cover_image, is_published)
            VALUES (?, ?, ?, ?, ?, 0)
        ");

        if (!$stmt->execute([$user["id"], $title, $type, $price, $coverPath])) {
            Response::serverError("Failed to create book");
        }

        $bookId = $this->db->lastInsertId();

        // =========================
        // OPTIONAL: UPLOAD BOOK FILE
        // =========================
        $bookFilePath = null;
        $bookFileType = null;

        if (isset($_FILES["file"])) {
            $file = $_FILES["file"];
            if ($file["error"] === UPLOAD_ERR_OK) {
                $allowedExt = ["pdf", "epub", "cbz", "zip"];
                $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt)) {
                    Response::error("Invalid book file type (pdf, epub, cbz, zip only)");
                }

                if ($file["size"] > 50 * 1024 * 1024) {
                    Response::error("Book file too large (max 50MB)");
                }

                $uploadDir = __DIR__ . "/../../uploads/books/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $safeName = uniqid("book_", true) . "." . $ext;
                $targetPath = $uploadDir . $safeName;

                if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
                    Response::serverError("Failed to save book file");
                }

                // INSERT INTO uploaded_books
                $stmt2 = $this->db->prepare("
                    INSERT INTO uploaded_books 
                    (user_id, book_id, file_name, file_path, file_type, file_size, price, is_published)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                ");

                $stmt2->execute([
                    $user["id"],
                    $bookId,
                    $file["name"],
                    $safeName,
                    $ext,
                    $file["size"],
                    $price
                ]);

                $bookFilePath = $safeName;
                $bookFileType = $ext;
            }
        }

        Response::success("Book created successfully", [
            "id" => $bookId,
            "title" => $title,
            "type" => $type,
            "price" => $price,
            "cover_image" => $coverPath,
            "file_path" => $bookFilePath,
            "file_type" => $bookFileType,
            "is_published" => 0
        ]);
    }

    // =========================
    // LIST ALL PUBLISHED BOOKS
    // =========================
    public function list() {
        $stmt = $this->db->query("
            SELECT 
                b.id, b.title, b.type, b.cover_image, b.price,
                u.name AS author_name
            FROM books b
            JOIN users u ON b.user_id = u.id
            WHERE b.is_published = 1
            ORDER BY b.created_at DESC
        ");

        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success("Books list", $books);
    }

    // =========================
    // LIST MY BOOKS (AUTHOR)
    // =========================
    public function myBooks() {
        $user = Auth::check();

        $stmt = $this->db->prepare("
            SELECT id, title, type, cover_image, price, is_published, created_at
            FROM books
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user["id"]]);

        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success("My books", $books);
    }

    // =========================
    // VIEW SINGLE BOOK
    // =========================
    public function view() {
        $book_id = $_GET["id"] ?? null;
        if (!$book_id) Response::error("Book ID missing");

        try {
            $user = Auth::check(false);
        } catch (Exception $e) {
            $user = null;
        }

        if ($user) {
            $stmt = $this->db->prepare("
                SELECT 
                    b.id, b.title, b.type, b.cover_image, b.price, b.is_published,
                    u.name AS author_name
                FROM books b
                JOIN users u ON b.user_id = u.id
                WHERE b.id = ? AND (b.is_published = 1 OR b.user_id = ?)
            ");
            $stmt->execute([$book_id, $user["id"]]);
        } else {
            $stmt = $this->db->prepare("
                SELECT 
                    b.id, b.title, b.type, b.cover_image, b.price, b.is_published,
                    u.name AS author_name
                FROM books b
                JOIN users u ON b.user_id = u.id
                WHERE b.id = ? AND b.is_published = 1
            ");
            $stmt->execute([$book_id]);
        }

        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$book) Response::error("Book not found");

        Response::success("Book details", $book);
    }

    // =========================
    // PUBLISH / UNPUBLISH BOOK
    // =========================
    public function publish() {
        $user = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);

        $book_id = $data["book_id"] ?? null;
        $status  = $data["status"] ?? null;

        if (!is_numeric($book_id) || !in_array((string)$status, ["0","1"], true)) {
            Response::error("Invalid book_id or status");
        }

        $check = $this->db->prepare("SELECT id FROM books WHERE id = ? AND user_id = ?");
        $check->execute([$book_id, $user["id"]]);

        if (!$check->fetch()) Response::unauthorized("You do not own this book");

        $stmt = $this->db->prepare("
            UPDATE books 
            SET is_published = ?
            WHERE id = ?
        ");

        if ($stmt->execute([$status, $book_id])) {
            Response::success("Book publish status updated", [
                "book_id" => $book_id,
                "is_published" => (int)$status
            ]);
        } else {
            Response::serverError("Update failed");
        }
    }
public function getFileByBook(){

    $bookId = $_GET["book_id"] ?? null;

    if(!$bookId || !is_numeric($bookId)){
        Response::error("Invalid book_id");
    }

    $stmt=$this->db->prepare("
        SELECT id,file_path,file_name,file_type
        FROM uploaded_books
        WHERE book_id=?
        LIMIT 1
    ");

    $stmt->execute([$bookId]);
    $file=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$file){
        Response::error("No file for this book");
    }

    Response::success("File found",$file);
}
public function delete(){

$user = Auth::check();

$data=json_decode(file_get_contents("php://input"),true);
$id=$data["book_id"]??null;

if(!$id) Response::error("Book missing");

/* DELETE CHAPTERS FIRST */
$this->db->prepare("
DELETE FROM chapters WHERE book_id=?
")->execute([$id]);

/* DELETE PURCHASES */
$this->db->prepare("
DELETE FROM purchases WHERE book_id=?
")->execute([$id]);

/* DELETE BOOK */
$this->db->prepare("
DELETE FROM books WHERE id=? AND user_id=?
")->execute([$id,$user["id"]]);

Response::success("Book deleted");

}
}
