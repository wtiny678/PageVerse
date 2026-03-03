<?php
// backend/modules/search/SearchController.php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class SearchController
{

    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // =========================
  public function books()
{
    $keyword = $_GET["q"] ?? "";

    if (!$keyword) {
        Response::error("Search keyword required");
    }

    $like = "%" . $keyword . "%";

    // ================= BOOKS =================
    $stmt1 = $this->db->prepare("
        SELECT 
            b.id,
            b.title,
            b.description,
            b.type,
            b.price,
            b.cover_image,
            u.name AS author_name,
            'text' AS source
        FROM books b
        JOIN users u ON b.user_id = u.id
        WHERE (b.title LIKE ? OR b.description LIKE ? OR u.name LIKE ?)
    ");
    $stmt1->execute([$like, $like, $like]);
    $books = $stmt1->fetchAll(PDO::FETCH_ASSOC);


    // ================= UPLOADED FILES =================
    $stmt2 = $this->db->prepare("
        SELECT 
            b.id,
            b.title,
            b.description,
            b.type,
            ub.price,
            b.cover_image,
            u.name AS author_name,
            'file' AS source
        FROM uploaded_books ub
        JOIN books b ON ub.book_id = b.id
        JOIN users u ON b.user_id = u.id
        WHERE (b.title LIKE ? OR b.description LIKE ? OR u.name LIKE ?)
    ");
    $stmt2->execute([$like, $like, $like]);
    $uploaded = $stmt2->fetchAll(PDO::FETCH_ASSOC);


    // ================= USERS =================
    $stmt3 = $this->db->prepare("
        SELECT 
            id,
            name AS title,
            email AS description,
            NULL AS type,
            NULL AS price,
            NULL AS cover_image,
            name AS author_name,
            'user' AS source
        FROM users
        WHERE name LIKE ?
    ");
    $stmt3->execute([$like]);
    $users = $stmt3->fetchAll(PDO::FETCH_ASSOC);


    // ================= MERGE =================
    $results = array_merge($books, $uploaded, $users);

    Response::success("Books found", $results);
}
}