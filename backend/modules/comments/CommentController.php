<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class CommentController {

private $db;

public function __construct(){
$this->db = Database::getConnection();
}

/* =========================
ADD COMMENT
========================= */

public function add(){

$user = Auth::check();

$data = json_decode(file_get_contents("php://input"), true);

$book_id    = intval($data["book_id"] ?? 0);
$chapter_id = intval($data["chapter_id"] ?? 0);

$comment = trim($data["comment"] ?? "");

if(!$comment){
Response::error("Comment is required");
return;
}

/* sanitize against XSS */
$comment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');

if(!$book_id && !$chapter_id){
Response::error("Book ID or Chapter ID required");
return;
}

$stmt=$this->db->prepare("
INSERT INTO comments(user_id,book_id,chapter_id,comment)
VALUES(?,?,?,?)
");

$stmt->execute([
$user["id"],
$book_id ?: null,
$chapter_id ?: null,
$comment
]);

Response::success("Comment added");

}


/* =========================
GET BOOK COMMENTS
========================= */

public function bookComments(){

Auth::check();

$book_id=intval($_GET["book_id"] ?? 0);

if(!$book_id){
Response::error("Book ID required");
return;
}

$stmt=$this->db->prepare("
SELECT 
c.id,
c.comment,
c.created_at,
u.name AS user_name
FROM comments c
JOIN users u ON c.user_id=u.id
WHERE c.book_id=?
ORDER BY c.created_at DESC
");

$stmt->execute([$book_id]);

Response::success("Book comments",$stmt->fetchAll(PDO::FETCH_ASSOC));

}


/* =========================
GET CHAPTER COMMENTS
========================= */

public function chapterComments(){

$user=Auth::check();

$chapter_id=intval($_GET["chapter_id"] ?? 0);

if(!$chapter_id){
Response::error("Chapter ID required");
return;
}

/* OPTIONAL: enforce access before showing comments */
/*
require_once "../access/AccessController.php";
(new AccessController())->checkChapterAccess();
*/

$stmt=$this->db->prepare("
SELECT 
c.id,
c.comment,
c.created_at,
u.name AS user_name
FROM comments c
JOIN users u ON c.user_id=u.id
WHERE c.chapter_id=?
ORDER BY c.created_at DESC
");

$stmt->execute([$chapter_id]);

Response::success("Chapter comments",$stmt->fetchAll(PDO::FETCH_ASSOC));

}


/* =========================
DELETE OWN COMMENT
========================= */

public function delete(){

$user=Auth::check();

$data=json_decode(file_get_contents("php://input"),true);

$comment_id=intval($data["comment_id"] ?? 0);

if(!$comment_id){
Response::error("Comment ID required");
return;
}

$stmt=$this->db->prepare("
DELETE FROM comments
WHERE id=? AND user_id=?
");

$stmt->execute([$comment_id,$user["id"]]);

if(!$stmt->rowCount()){
Response::error("Comment not found or not allowed");
return;
}

Response::success("Comment deleted");

}

}