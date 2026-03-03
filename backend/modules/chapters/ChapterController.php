<?php
// backend/modules/chapters/ChapterController.php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class ChapterController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // =========================
    // CREATE CHAPTER
    // =========================
    public function create() {
        $user = Auth::check();

        $data = json_decode(file_get_contents("php://input"), true);

        $book_id    = $data["book_id"] ?? null;
        $title      = trim($data["title"] ?? "");
        $content    = $data["content"] ?? "";
        $pages      = $data["pages"] ?? [];
        $price      = floatval($data["price"] ?? 0);
        $chapter_no = intval($data["chapter_no"] ?? 1);
        $type = isset($data["type"]) && in_array($data["type"], ["novel","comic"])
        ? $data["type"]
        : "novel";

        if (!is_numeric($book_id) || $title === "") {
            Response::error("Missing or invalid fields");
        }

        if ($price < 0) {
            Response::error("Price cannot be negative");
        }

        // check ownership
        $check = $this->db->prepare("SELECT id FROM books WHERE id = ? AND user_id = ?");
        $check->execute([$book_id, $user["id"]]);
        if ($check->rowCount() === 0) {
            Response::unauthorized("You are not the owner of this book");
        }

        $stmt = $this->db->prepare("
            INSERT INTO chapters (book_id, title, type, content, chapter_no, price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $book_id,
            $title,
            $type,
            ($type === "comic" && count($pages)) ? null : $content,
            $chapter_no,
            $price
        ]);

        $chapter_id = $this->db->lastInsertId();

        // =========================
        // COMIC PAGES
        // =========================
        if ($type === "comic" && is_array($pages)) {
            $uploadDir = __DIR__ . "/../../uploads/comics/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $pageNo = 1;
            foreach ($pages as $base64) {
                if (strpos($base64, "data:image") !== 0) continue;

                $img = explode(",", $base64);
                $binary = base64_decode($img[1]);

                $file = uniqid("page_", true) . ".png";
                file_put_contents($uploadDir . $file, $binary);

                $this->db->prepare("
                    INSERT INTO chapter_images (chapter_id, image_path, page_no)
                    VALUES (?, ?, ?)
                ")->execute([$chapter_id, "/uploads/comics/" . $file, $pageNo]);

                $pageNo++;
            }
        }

        Response::success("Chapter created", ["chapter_id" => $chapter_id]);
    }

    // =========================
    // VIEW ALL CHAPTERS OF BOOK
    // ROUTE: /chapters/view?book_id=1
    // =========================
    public function view() {
        Auth::check(false);

        $book_id = $_GET["book_id"] ?? null;
        if (!is_numeric($book_id)) Response::error("Invalid book_id");

        $stmt = $this->db->prepare("
            SELECT id, title, chapter_no, price, type
            FROM chapters
            WHERE book_id = ?
            ORDER BY chapter_no ASC
        ");
        $stmt->execute([$book_id]);

        Response::success("Chapters list", $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // =========================
    // VIEW SINGLE CHAPTER (ADMIN / DEBUG)
    // ROUTE: /chapters/single?id=1
    // =========================
    public function single() {
        $id = $_GET["id"] ?? null;
        if (!is_numeric($id)) Response::error("Invalid chapter id");

        $stmt = $this->db->prepare("SELECT * FROM chapters WHERE id = ?");
        $stmt->execute([$id]);
        $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$chapter) Response::error("Chapter not found");

        $imgStmt = $this->db->prepare("
            SELECT image_path FROM chapter_images
            WHERE chapter_id = ?
            ORDER BY page_no ASC
        ");
        $imgStmt->execute([$id]);
        $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($images) {
            Response::success("Comic chapter", [
                "type" => "comic",
                "title" => $chapter["title"],
                "pages" => $images
            ]);
        } else {
            Response::success("Novel chapter", [
                "type" => "novel",
                "title" => $chapter["title"],
                "content" => $chapter["content"]
            ]);
        }
    }

    // =========================
    // AUTHOR DASHBOARD
    // =========================
    public function myChapters() {
        $user = Auth::check();

        $stmt = $this->db->prepare("
            SELECT c.id, c.title, c.chapter_no, c.price, c.type,
                   b.title AS book_title
            FROM chapters c
            JOIN books b ON c.book_id = b.id
            WHERE b.user_id = ?
            ORDER BY c.chapter_no ASC
        ");
        $stmt->execute([$user["id"]]);

        Response::success("My chapters", $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
