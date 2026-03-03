<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class AccessController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }


    /* =========================
       CHECK CHAPTER ACCESS
    ========================= */

    public function checkChapterAccess(){

        $user = Auth::check();
        $chapter_id = $_GET["chapter_id"] ?? null;

        if(!is_numeric($chapter_id))
            Response::error("Chapter ID missing");

        /* GET AUTHOR + BOOK */

        $stmt=$this->db->prepare("
            SELECT b.id AS book_id, b.user_id AS author_id
            FROM chapters c
            JOIN books b ON c.book_id=b.id
            WHERE c.id=?
        ");
        $stmt->execute([$chapter_id]);
        $chapter=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$chapter) Response::error("Chapter not found");

        /* AUTHOR FREE */

        if($chapter["author_id"]==$user["id"]){
            Response::success("Access granted",["access"=>true]);
            return;
        }

        /* FULL BOOK PURCHASE */

        $check=$this->db->prepare("
            SELECT id FROM purchases
            WHERE user_id=? AND book_id=? AND chapter_id IS NULL
        ");
        $check->execute([$user["id"],$chapter["book_id"]]);

        if($check->rowCount()>0){
            Response::success("Access granted",["access"=>true]);
            return;
        }

        /* CHAPTER PURCHASE */

        $check=$this->db->prepare("
            SELECT id FROM purchases
            WHERE user_id=? AND chapter_id=?
        ");
        $check->execute([$user["id"],$chapter_id]);

        if($check->rowCount()>0){
            Response::success("Access granted",["access"=>true]);
            return;
        }

        Response::error("Access denied. Please purchase.");
    }



    /* =========================
       BUY BOOK WITH WALLET
    ========================= */
public function buyWithWallet(){

$user=Auth::check();

$data=json_decode(file_get_contents("php://input"),true);
$book_id=intval($data["book_id"] ?? 0);

if(!$book_id){
Response::error("Book missing");
return;
}

/* GET BOOK */

$stmt=$this->db->prepare("
SELECT price,user_id AS author_id
FROM books
WHERE id=?
");
$stmt->execute([$book_id]);
$book=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$book){
Response::error("Book not found");
return;
}

/* OWNER FREE */

if($book["author_id"]==$user["id"]){
Response::success("Owner access");
return;
}

/* CHECK WALLET BALANCE */

$balStmt=$this->db->prepare("
SELECT IFNULL(
SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0
) AS bal
FROM wallet_transactions
WHERE user_id=?
");

$balStmt->execute([$user["id"]]);
$row=$balStmt->fetch(PDO::FETCH_ASSOC);
$bal=floatval($row["bal"]);

if($bal < $book["price"]){
Response::error("Not enough coins");
return;
}

try{

$this->db->beginTransaction();

/* 🔒 CHECK AGAIN INSIDE TRANSACTION */

$chk=$this->db->prepare("
SELECT id FROM purchases
WHERE user_id=? AND book_id=? AND chapter_id IS NULL
FOR UPDATE
");

$chk->execute([$user["id"],$book_id]);

if($chk->rowCount()>0){
$this->db->rollBack();
Response::success("Already purchased");
return;
}

/* USER DEBIT */

$this->db->prepare("
INSERT INTO wallet_transactions(user_id,type,amount,reference)
VALUES(?, 'debit', ?, ?)
")->execute([
$user["id"],
$book["price"],
"wallet_book_".$book_id
]);

/* AUTHOR CREDIT */

$this->db->prepare("
INSERT INTO wallet_transactions(user_id,type,amount,reference)
VALUES(?, 'credit', ?, ?)
")->execute([
$book["author_id"],
$book["price"],
"wallet_sale_".$book_id
]);

/* SAVE PURCHASE */

$this->db->prepare("
INSERT INTO purchases(user_id,book_id,chapter_id,amount)
VALUES(?,?,NULL,?)
")->execute([
$user["id"],
$book_id,
$book["price"]
]);

$this->db->commit();

Response::success("Purchased successfully");

}catch(Exception $e){

$this->db->rollBack();
Response::serverError("Purchase failed");

}
}
}